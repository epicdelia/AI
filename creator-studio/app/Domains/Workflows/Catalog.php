<?php

namespace App\Domains\Workflows;

/**
 * Every node the builder offers: triggers (what starts a run), logic (conditions, waits) and
 * actions. Fields drive both the config panel and the "not configured yet" check, so a new
 * node type is one entry here plus a handler in Engine.
 *
 * Text fields accept {text}, {author}, {name}, {platform}, {post_title}, {post_url}, {post_text},
 * and for business triggers {deal}, {contact}, {amount}, {source}, {stage}, {fit}.
 */
class Catalog
{
    private const COMMENT_NETS = [['instagram', 'Instagram'], ['threads', 'Threads'], ['youtube', 'YouTube']];

    private const ALL_NETS = [['linkedin', 'LinkedIn'], ['instagram', 'Instagram'], ['x', 'X'], ['tiktok', 'TikTok'], ['threads', 'Threads'], ['youtube', 'YouTube']];

    public static function nodes(): array
    {
        $mode = ['key' => 'mode', 'label' => 'Write it', 'type' => 'select', 'options' => [['ai', 'AI writes it in my voice'], ['template', 'Use my text']], 'default' => 'ai'];

        return [
            // ---- Triggers ----
            'any_comment' => ['kind' => 'trigger', 'label' => 'Any Comment', 'icon' => 'chat', 'tone' => 'blue', 'event' => 'comment',
                'summary' => 'Anyone comments on any post', 'fields' => [
                    ['key' => 'platforms', 'label' => 'On', 'type' => 'multi', 'options' => self::COMMENT_NETS, 'help' => 'none picked = all connected'],
                ]],
            'comment_keyword' => ['kind' => 'trigger', 'label' => 'Comment with keyword', 'icon' => 'chat', 'tone' => 'blue', 'event' => 'comment',
                'summary' => 'A comment contains {keywords}', 'fields' => [
                    ['key' => 'keywords', 'label' => 'Keywords', 'type' => 'keywords', 'required' => true, 'placeholder' => 'guide, link', 'help' => 'whole words, any case'],
                    ['key' => 'platforms', 'label' => 'On', 'type' => 'multi', 'options' => self::COMMENT_NETS],
                ]],
            'new_dm' => ['kind' => 'trigger', 'label' => 'New DM', 'icon' => 'inbox', 'tone' => 'blue', 'event' => 'dm',
                'summary' => 'Someone sends you a DM', 'fields' => [
                    ['key' => 'keywords', 'label' => 'Only if it contains', 'type' => 'keywords', 'placeholder' => 'optional, e.g. collab, price'],
                ]],
            'post_published' => ['kind' => 'trigger', 'label' => 'Post published', 'icon' => 'external', 'tone' => 'blue', 'event' => 'post_published',
                'summary' => 'A post goes live', 'fields' => [
                    ['key' => 'platforms', 'label' => 'On', 'type' => 'multi', 'options' => self::ALL_NETS],
                ]],
            'post_performs' => ['kind' => 'trigger', 'label' => 'Post takes off', 'icon' => 'trend', 'tone' => 'blue', 'event' => 'post_performs',
                'summary' => 'A post gets {multiplier}× your usual engagement', 'fields' => [
                    ['key' => 'multiplier', 'label' => 'Times her usual engagement rate', 'type' => 'number', 'min' => 1.2, 'max' => 10, 'step' => 0.1, 'default' => 2, 'required' => true],
                    ['key' => 'platforms', 'label' => 'On', 'type' => 'multi', 'options' => self::ALL_NETS],
                ]],
            'schedule' => ['kind' => 'trigger', 'label' => 'On a schedule', 'icon' => 'calendar', 'tone' => 'blue', 'event' => 'schedule',
                'summary' => 'Every {every} at {time}', 'fields' => [
                    ['key' => 'every', 'label' => 'Every', 'type' => 'select', 'options' => [['day', 'day'], ['weekday', 'weekday (Mon–Fri)'], ['week', 'week']], 'default' => 'week', 'required' => true],
                    ['key' => 'weekday', 'label' => 'On', 'type' => 'select', 'options' => [['1', 'Monday'], ['2', 'Tuesday'], ['3', 'Wednesday'], ['4', 'Thursday'], ['5', 'Friday'], ['6', 'Saturday'], ['7', 'Sunday']], 'default' => '1', 'show_if' => ['every', 'week']],
                    ['key' => 'time', 'label' => 'At', 'type' => 'time', 'default' => '09:00', 'required' => true],
                ]],
            // ---- Business triggers (Deals, the website, payments) ----
            'new_lead' => ['kind' => 'trigger', 'label' => 'New website lead', 'icon' => 'briefcase', 'tone' => 'blue', 'event' => 'new_lead',
                'summary' => 'Someone books a call or starts a checkout', 'fields' => [
                    ['key' => 'min_fit', 'label' => 'Only if the fit score is at least', 'type' => 'number', 'min' => 0, 'max' => 100, 'step' => 5, 'default' => 0],
                ]],
            'deal_stage' => ['kind' => 'trigger', 'label' => 'Deal moves', 'icon' => 'briefcase', 'tone' => 'blue', 'event' => 'deal_stage',
                'summary' => 'A deal moves to {stage}', 'fields' => [
                    ['key' => 'stage', 'label' => 'To', 'type' => 'select', 'required' => true, 'default' => 'contracted', 'options' => [
                        ['pitching', 'Pitching'], ['negotiating', 'Negotiating'], ['contracted', 'Contracted'], ['production', 'In production'], ['scheduled', 'Scheduled'],
                        ['invoiced', 'Invoiced'], ['paid', 'Paid'], ['lost', 'Lost'],
                    ]],
                ]],
            'payment_received' => ['kind' => 'trigger', 'label' => 'Payment received', 'icon' => 'check', 'tone' => 'blue', 'event' => 'payment',
                'summary' => 'A deal is paid or a retainer renews', 'fields' => []],
            'deal_opened' => ['kind' => 'trigger', 'label' => 'Proposal or kit opened', 'icon' => 'eye', 'tone' => 'blue', 'event' => 'deal_opened',
                'summary' => 'A client opens their {what} link', 'fields' => [
                    ['key' => 'what', 'label' => 'Which link', 'type' => 'select', 'default' => 'any', 'options' => [['any', 'proposal or media kit'], ['proposal', 'proposal'], ['media kit', 'media kit']]],
                ]],
            'testimonial_received' => ['kind' => 'trigger', 'label' => 'Testimonial received', 'icon' => 'star', 'tone' => 'blue', 'event' => 'testimonial',
                'summary' => 'A client sends a testimonial', 'fields' => []],
            'new_subscriber' => ['kind' => 'trigger', 'label' => 'New subscriber', 'icon' => 'mail', 'tone' => 'blue', 'event' => 'subscriber',
                'summary' => 'Someone joins your email list', 'fields' => []],
            'manual' => ['kind' => 'trigger', 'label' => 'Run by hand', 'icon' => 'play', 'tone' => 'blue', 'event' => 'manual',
                'summary' => 'When you press Run', 'fields' => []],

            // ---- Logic ----
            'condition' => ['kind' => 'logic', 'label' => 'Only continue if', 'icon' => 'sliders', 'tone' => 'amber',
                'summary' => '{field} {op} {value}', 'fields' => [
                    ['key' => 'field', 'label' => 'When', 'type' => 'select', 'required' => true, 'default' => 'text', 'options' => [
                        ['text', 'the comment / DM text'], ['intent', 'its type (question, lead, praise, spam)'], ['author', 'the person'], ['platform', 'the network'],
                        ['rate', 'the post’s engagement rate (%)'], ['followers_ratio', 'the post’s rate ÷ her usual'],
                        ['amount', 'the deal amount'], ['fit', 'the lead’s fit score'], ['source', 'where the lead came from'], ['stage', 'the deal stage'],
                    ]],
                    ['key' => 'op', 'label' => 'Is', 'type' => 'select', 'required' => true, 'default' => 'contains', 'options' => [
                        ['contains', 'contains'], ['not_contains', 'doesn’t contain'], ['is', 'is'], ['is_not', 'is not'], ['gt', 'more than'], ['lt', 'less than'],
                    ]],
                    ['key' => 'value', 'label' => 'Value', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. question, or 3'],
                ]],
            'delay' => ['kind' => 'logic', 'label' => 'Wait', 'icon' => 'history', 'tone' => 'amber',
                'summary' => 'Wait {amount} {unit}', 'fields' => [
                    ['key' => 'amount', 'label' => 'For', 'type' => 'number', 'min' => 1, 'max' => 1000, 'default' => 10, 'required' => true],
                    ['key' => 'unit', 'label' => 'Unit', 'type' => 'select', 'options' => [['minutes', 'minutes'], ['hours', 'hours'], ['days', 'days']], 'default' => 'minutes', 'required' => true],
                ]],

            // ---- Actions ----
            'reply_comment' => ['kind' => 'action', 'label' => 'Reply to comment', 'icon' => 'chat', 'tone' => 'pink', 'needs' => 'item',
                'summary' => 'Public reply: {text|instructions}', 'fields' => [
                    $mode,
                    ['key' => 'text', 'label' => 'Reply', 'type' => 'textarea', 'placeholder' => 'Sent it to your DMs {name}', 'show_if' => ['mode', 'template'], 'required_if' => ['mode', 'template']],
                    ['key' => 'instructions', 'label' => 'Guidance for the AI', 'type' => 'textarea', 'placeholder' => 'Facts, links, what to avoid', 'show_if' => ['mode', 'ai']],
                ]],
            'send_dm' => ['kind' => 'action', 'label' => 'Send DM', 'icon' => 'inbox', 'tone' => 'pink', 'needs' => 'item',
                'summary' => 'DM: {text|instructions}', 'fields' => [
                    ['key' => 'mode', 'label' => 'Write it', 'type' => 'select', 'options' => [['template', 'Use my text'], ['ai', 'AI writes it in my voice']], 'default' => 'template'],
                    ['key' => 'text', 'label' => 'Message', 'type' => 'textarea', 'placeholder' => 'Here’s the guide: delia.link/guide', 'show_if' => ['mode', 'template'], 'required_if' => ['mode', 'template']],
                    ['key' => 'instructions', 'label' => 'Guidance for the AI', 'type' => 'textarea', 'show_if' => ['mode', 'ai']],
                ], 'help' => 'Instagram only: a private reply to a comment (one per comment, within 7 days) or an answer to a DM.'],
            'ai_post' => ['kind' => 'action', 'label' => 'Write a post', 'icon' => 'sparkle', 'tone' => 'violet',
                'summary' => 'Write for {platforms}', 'fields' => [
                    ['key' => 'platforms', 'label' => 'For', 'type' => 'multi', 'options' => self::ALL_NETS, 'required' => true],
                    ['key' => 'brief', 'label' => 'About', 'type' => 'textarea', 'required' => true, 'placeholder' => 'e.g. A tip from {post_text}, or: this week’s AI news for creators'],
                    ['key' => 'status', 'label' => 'Then', 'type' => 'select', 'default' => 'draft', 'options' => [['draft', 'Save as drafts for me'], ['scheduled', 'Schedule in the next good slot (publishes where connected)']]],
                ]],
            'repurpose' => ['kind' => 'action', 'label' => 'Repurpose the post', 'icon' => 'repeat', 'tone' => 'violet', 'needs' => 'post',
                'summary' => 'Rewrite it for {platforms}', 'fields' => [
                    ['key' => 'platforms', 'label' => 'For', 'type' => 'multi', 'options' => self::ALL_NETS, 'required' => true],
                    ['key' => 'status', 'label' => 'Then', 'type' => 'select', 'default' => 'draft', 'options' => [['draft', 'Save as drafts for me'], ['scheduled', 'Schedule in the next good slot (publishes where connected)']]],
                ]],
            'run_agent' => ['kind' => 'action', 'label' => 'Run an agent', 'icon' => 'bot', 'tone' => 'violet',
                'summary' => 'Let {agent_name} plan the week', 'fields' => [
                    ['key' => 'agent_id', 'label' => 'Agent', 'type' => 'agent', 'required' => true],
                ]],
            'notify' => ['kind' => 'action', 'label' => 'Notify me', 'icon' => 'bell', 'tone' => 'green',
                'summary' => '{message}', 'fields' => [
                    ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true, 'default' => '{author} on {platform}: {text}'],
                ]],
            'prep_call' => ['kind' => 'action', 'label' => 'Prep the call', 'icon' => 'doc', 'tone' => 'violet', 'needs' => 'deal',
                'summary' => 'Write a prep brief for the intro call', 'fields' => [],
                'help' => 'Research on the company (when web search is set up), questions, objections and the package to suggest. Saved on the deal.'],
            'move_deal' => ['kind' => 'action', 'label' => 'Move the deal', 'icon' => 'briefcase', 'tone' => 'green', 'needs' => 'deal',
                'summary' => 'Move it to {stage}', 'fields' => [
                    ['key' => 'stage', 'label' => 'To', 'type' => 'select', 'required' => true, 'default' => 'pitching', 'options' => [
                        ['pitching', 'Pitching'], ['negotiating', 'Negotiating'], ['contracted', 'Contracted'], ['production', 'In production'], ['invoiced', 'Invoiced'],
                    ]],
                ]],
            'mark' => ['kind' => 'action', 'label' => 'Mark as handled', 'icon' => 'check', 'tone' => 'green', 'needs' => 'item',
                'summary' => 'Move it out of the Inbox ({as})', 'fields' => [
                    ['key' => 'as', 'label' => 'As', 'type' => 'select', 'default' => 'ignored', 'options' => [['ignored', 'ignored'], ['replied', 'answered']]],
                ]],
        ];
    }

    public static function get(string $type): ?array
    {
        return self::nodes()[$type] ?? null;
    }

    /** Required fields filled? (drives the ⚠ "not configured" badge and blocks turning a workflow on). */
    public static function configured(array $node): bool
    {
        $def = self::get($node['type'] ?? '');
        if (! $def) {
            return false;
        }
        $cfg = $node['config'] ?? [];
        foreach ($def['fields'] as $f) {
            $value = $cfg[$f['key']] ?? $f['default'] ?? null;
            $needed = ! empty($f['required']) || (isset($f['required_if']) && ($cfg[$f['required_if'][0]] ?? self::fieldDefault($def, $f['required_if'][0])) === $f['required_if'][1]);
            if ($needed && ($value === null || $value === '' || $value === [])) {
                return false;
            }
        }

        return true;
    }

    private static function fieldDefault(array $def, string $key): mixed
    {
        foreach ($def['fields'] as $f) {
            if ($f['key'] === $key) {
                return $f['default'] ?? null;
            }
        }

        return null;
    }

    /** Ready-made workflows for the gallery: the automations creators use most. */
    public static function templates(): array
    {
        $k = fn (string $type, array $config = []) => ['key' => $type.'_'.substr(md5($type.json_encode($config)), 0, 5), 'type' => $type, 'config' => $config, 'enabled' => true];

        return [
            ['id' => 'keyword_dm', 'name' => 'Comment “GUIDE” → DM the link', 'desc' => 'The classic lead magnet: reply publicly and send the link in a private DM.', 'icon' => 'inbox',
                'nodes' => [$k('comment_keyword', ['keywords' => ['guide'], 'platforms' => ['instagram']]),
                    $k('reply_comment', ['mode' => 'template', 'text' => 'Sent it to your DMs {name}']),
                    $k('send_dm', ['mode' => 'template', 'text' => 'Here’s the guide you asked for: (your link)'])]],
            ['id' => 'viral_repurpose', 'name' => 'Post takes off → repurpose everywhere', 'desc' => 'When a post gets 2× your usual engagement, rewrite it for your other networks.', 'icon' => 'trend',
                'nodes' => [$k('post_performs', ['multiplier' => 2]), $k('repurpose', ['platforms' => ['linkedin', 'threads', 'x'], 'status' => 'draft']),
                    $k('notify', ['message' => 'Your {platform} post is taking off. Drafts for your other networks are ready.'])]],
            ['id' => 'lead_dm', 'name' => 'Business DM → notify me + draft a reply', 'desc' => 'Never miss a collab or coaching request in your DMs.', 'icon' => 'briefcase',
                'nodes' => [$k('new_dm', ['keywords' => ['collab', 'sponsor', 'coaching', 'price', 'rates', 'partnership']]),
                    $k('notify', ['message' => 'Possible lead from {author}: {text}']), $k('send_dm', ['mode' => 'ai', 'instructions' => 'Thank them warmly and ask them to email the details.'])]],
            ['id' => 'question_answer', 'name' => 'Questions → answered in 10 minutes', 'desc' => 'Wait a bit (so it feels human), then answer questions in your voice.', 'icon' => 'chat',
                'nodes' => [$k('any_comment', ['platforms' => ['instagram', 'youtube']]), $k('condition', ['field' => 'intent', 'op' => 'is', 'value' => 'question']),
                    $k('delay', ['amount' => 10, 'unit' => 'minutes']), $k('reply_comment', ['mode' => 'ai', 'instructions' => 'Answer briefly and specifically.'])]],
            ['id' => 'monday_plan', 'name' => 'Every Monday → plan the week', 'desc' => 'Your agent fills the week every Monday morning and you get a nudge to approve.', 'icon' => 'calendar',
                'nodes' => [$k('schedule', ['every' => 'week', 'weekday' => '1', 'time' => '08:00']), $k('run_agent', []), $k('notify', ['message' => 'Your week of posts is planned. Approve them in Agents.'])]],
            ['id' => 'lead_alert', 'name' => 'Strong website lead → notify me + prep the call', 'desc' => 'Leads with a fit score of 60+ ping you right away and get a prep brief.', 'icon' => 'briefcase',
                'nodes' => [$k('new_lead', ['min_fit' => 60]), $k('notify', ['message' => 'Strong lead: {deal} ({contact}), fit {fit}, from {source}.']), $k('prep_call', [])]],
            ['id' => 'proposal_opened', 'name' => 'Proposal opened → nudge me', 'desc' => 'The moment a client opens their proposal page, you know: a good time to follow up.', 'icon' => 'eye',
                'nodes' => [$k('deal_opened', ['what' => 'proposal']), $k('notify', ['message' => '{deal} is reading your proposal right now.'])]],
            ['id' => 'paid_review', 'name' => 'Paid → ask for a testimonial in a week', 'desc' => 'A reminder 7 days after each payment to send the client their review link.', 'icon' => 'star',
                'nodes' => [$k('payment_received'), $k('delay', ['amount' => 7, 'unit' => 'days']), $k('notify', ['message' => 'Ask {contact} at {deal} for a testimonial: Deals, Ask for a review.'])]],
            ['id' => 'testimonial_post', 'name' => 'New testimonial → draft a LinkedIn post', 'desc' => 'Turn a client’s kind words into a post draft for you to check (only quote clients who allowed it).', 'icon' => 'star',
                'nodes' => [$k('testimonial_received'), $k('ai_post', ['platforms' => ['linkedin'], 'brief' => 'A short, humble post about what we built together, quoting the client: {text}', 'status' => 'draft'])]],
            ['id' => 'publish_crosspost', 'name' => 'LinkedIn post live → Threads version', 'desc' => 'Every LinkedIn post becomes a shorter Threads post in the next good slot.', 'icon' => 'repeat',
                'nodes' => [$k('post_published', ['platforms' => ['linkedin']]), $k('repurpose', ['platforms' => ['threads'], 'status' => 'scheduled'])]],
        ];
    }
}
