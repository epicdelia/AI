<?php

namespace App\Domains\Copilot;

use App\Domains\Copilot\Models\CopilotMessage;
use App\Domains\Copilot\Models\CopilotThread;
use App\Domains\Engine\Stream\StreamService;
use App\Models\Script;
use App\Models\Setting;
use App\Services\Llm;
use App\Services\Prompts;

/**
 * The Copilot's agent loop: native function calling on the engine in Settings.
 *
 * Each step the model either answers or asks for tools. Read tools run and their results go
 * back to the model; write tools become proposals she approves in the panel; client tools
 * (opening a page) are handed to the browser. Every step is streamed so the panel can show
 * what the Copilot is doing. Ask mode never offers write tools.
 */
class CopilotAgent
{
    public const MAX_STEPS = 8;

    public const HISTORY = 40; // most recent transcript rows sent back to the model

    public function __construct(private Llm $llm, private CopilotTools $tools) {}

    /**
     * @param  array{path?: string, page?: string, script_id?: int, title?: string, selection?: string}  $context
     * @param  array<int, array{name: string, text: string}>  $attachments
     * @return array{message: CopilotMessage, actions: array}
     */
    public function run(CopilotThread $thread, string $content, array $context, array $attachments, StreamService $sse): array
    {
        $userText = $content;
        foreach ($attachments as $f) {
            $userText .= "\n\n--- Attached file: {$f['name']} ---\n".mb_substr($f['text'], 0, 60000);
        }
        $user = $thread->messages()->create(['role' => 'user', 'content' => $userText, 'meta' => [
            'shown' => $content, 'context' => $context, 'attachments' => array_map(fn ($f) => ['name' => $f['name'], 'chars' => mb_strlen($f['text'])], $attachments),
        ]]);
        $sse->emit('message', (string) $user->id);
        if ($thread->title === 'New chat') {
            $thread->update(['title' => mb_strimwidth(preg_replace('/\s+/', ' ', $content), 0, 60, '…') ?: 'New chat']);
        }

        $llm = $this->llm->as('copilot')->model($thread->model);
        $defs = $this->tools->definitions($thread->mode);
        $system = $this->system($thread, $context);
        $actions = [];
        $final = null;

        for ($step = 0; $step < self::MAX_STEPS; $step++) {
            $sse->emit('status', json_encode(['step' => $step === 0 ? 'thinking' : 'working']));
            $turn = $llm->agentTurn($system, $this->transcript($thread), $step === self::MAX_STEPS - 1 ? [] : $defs);

            if (! $turn->wantsTools()) {
                $final = $thread->messages()->create(['role' => 'assistant', 'content' => $turn->text]);
                break;
            }

            $thread->messages()->create(['role' => 'assistant', 'content' => $turn->text, 'meta' => ['tool_calls' => $turn->toolCalls]]);
            if ($turn->text !== '') {
                $sse->emit('note', json_encode($turn->text, JSON_UNESCAPED_UNICODE)); // the model's "I'll check..." aside
            }

            foreach ($turn->toolCalls as $call) {
                $name = $call['name'];
                $kind = CopilotTools::kind($name);
                $step_ = ['id' => $call['id'], 'name' => $name, 'kind' => $kind, 'label' => CopilotTools::label($name, $call['args'])];
                $sse->emit('tool', json_encode($step_ + ['status' => 'running'], JSON_UNESCAPED_UNICODE));

                if ($kind === 'write' && $thread->mode !== 'agent') {
                    $result = 'ERROR: Ask mode can’t change anything. Explain what you would change instead.';
                    $status = 'error';
                } elseif ($kind === 'write') {
                    try {
                        $action = $this->tools->propose($thread, $name, $call['args']);
                        $actions[] = $action;
                        $sse->emit('action', json_encode($action, JSON_UNESCAPED_UNICODE));
                        $result = "Proposed to her as change #{$action->id} (\"{$action->summary}\"). It is NOT applied yet: she decides with the Apply button. Don't propose it again; tell her what you proposed.";
                        $status = 'proposed';
                    } catch (\Throwable $e) {
                        $result = 'ERROR: '.$e->getMessage();
                        $status = 'error';
                    }
                } elseif ($kind === 'client') {
                    $sse->emit('client', json_encode(['name' => $name, 'args' => $call['args']], JSON_UNESCAPED_UNICODE));
                    $result = 'Done: the page was opened for her.';
                    $status = 'done';
                } else {
                    $result = $this->tools->safeRead($name, $call['args']);
                    $status = str_starts_with($result, 'ERROR:') ? 'error' : 'done';
                }

                $thread->messages()->create(['role' => 'tool', 'content' => $result, 'meta' => ['tool_call_id' => $call['id'], 'name' => $name, 'status' => $status]]);
                $sse->emit('tool', json_encode($step_ + ['status' => $status], JSON_UNESCAPED_UNICODE));
            }
        }

        $final ??= $thread->messages()->create(['role' => 'assistant', 'content' => 'I took too many steps on that one. Here’s where I got to: try asking for one thing at a time.']);
        foreach (self::chunks((string) $final->content) as $piece) {
            $sse->chunk($piece);
        }
        $thread->touch();

        return ['message' => $final, 'actions' => $actions];
    }

    /** Stored rows → the provider-neutral transcript for EngineDriver::toolChat. */
    private function transcript(CopilotThread $thread): array
    {
        $rows = $thread->messages()->reorder()->orderByDesc('id')->take(self::HISTORY)->get()->reverse()->values();
        // Never start on an orphan tool result (its assistant call was cut off by the window).
        while ($rows->isNotEmpty() && $rows->first()->role === 'tool') {
            $rows->shift();
        }

        return $rows->map(fn (CopilotMessage $m) => match ($m->role) {
            'tool' => ['role' => 'tool', 'tool_call_id' => $m->meta['tool_call_id'] ?? '', 'name' => $m->meta['name'] ?? '', 'content' => (string) $m->content],
            'assistant' => ['role' => 'assistant', 'content' => (string) $m->content] + (! empty($m->meta['tool_calls']) ? ['tool_calls' => $m->meta['tool_calls']] : []),
            default => ['role' => 'user', 'content' => (string) $m->content],
        })->all();
    }

    private function system(CopilotThread $thread, array $c): string
    {
        $name = Prompts::name();
        $page = '';
        if (! empty($c['path'])) {
            $page = "She is on the page {$c['path']}".(! empty($c['title']) ? " (\"{$c['title']}\")" : '').'.';
            if (! empty($c['script_id']) && ($s = Script::find($c['script_id']))) {
                $page .= " It's script #{$s->id} \"{$s->title}\" ({$s->status}, {$s->wordCount()} words). Use get_script to read it.";
            }
        }
        if (! empty($c['selection'])) {
            $page .= "\nShe has selected this text (\"this\", \"it\" and \"the selection\" mean this):\n\"\"\"\n".mb_substr($c['selection'], 0, 6000)."\n\"\"\"";
        }
        $mode = $thread->mode === 'agent'
            ? "AGENT mode: you may propose changes with the edit/create/add/save tools. Proposals only run when she presses Apply, so never say a change is done; say what you proposed. For edits to selected text use edit_script with operation replace_text and find = the exact selection."
            : 'ASK mode: answer and explain only. You cannot change anything; if a change would help, describe it.';
        $love = Setting::get('love_notes', '1') === '1' ? " She's loved: be warm with her." : '';

        return <<<TXT
You are the Copilot inside {$name}'s Creator Studio: a fast, practical assistant for her YouTube channel. You can look things up in her studio and{$this->agentHint($thread)} help her write.{$love}

{$this->profile()}

{$mode}

{$page}

How to work:
- Look before you guess: use tools to read scripts, ideas and her past work instead of assuming.
- Keep replies short and skimmable (Markdown, bullets). Lead with the answer.
- Anything you write for her: her voice, contractions, no em dashes, no AI clichés. Run check_ai_tells on longer drafts.
- Facts that may have changed need search_web (if available) with links; otherwise say you're not sure.
- Never invent her stories, opinions or numbers.
TXT;
    }

    private function agentHint(CopilotThread $thread): string
    {
        return $thread->mode === 'agent' ? ' propose changes,' : '';
    }

    private function profile(): string
    {
        return Prompts::profile();
    }

    /** Split the final answer into word-sized pieces for the streaming panel. */
    private static function chunks(string $text): array
    {
        return preg_split('/(?<=\s)/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
    }
}
