# Creator Studio

A personal YouTube studio: find what's breaking out in your niche, turn it into ideas, write and
lint scripts, package each video, and keep a weekly shipping streak, all in one place.

Laravel 13 JSON API + a plain-JavaScript single-page app (ES modules, no build step, no framework).

## What's inside

| Page | What it does |
|---|---|
| **Dashboard** | This week's commitment, streak, setup checklist, latest ideas/scripts/breakouts/notes |
| **Dictate** | Hold Space, ramble, release: live transcript (AssemblyAI streaming) → clean note, idea, email… |
| **Breakouts** | Watch competitor channels; flags recent long-form videos doing ≥ N× their channel median. "Why it worked" analysis per video. **News feeds**: RSS/Atom feeds whose fresh items also feed the idea generator |
| **Ideas** | AI briefs from breakout signals (or your niche), pick / drop / edit, draft a script in one click |
| **Scripts** | Editor with autosave and a live AI-tell linter, AI review panel, **Package** tab (titles, thumbnails, hooks, description, tags, chapters, Shorts, thread, newsletter), transcript fetch, Substack post, editor brief |
| **Weekly goals** | 12-week plan, scoreboard from your real uploads, blunt mentor verdict |
| **Toolkit** | 20+ creator writing templates in your voice, favourites, and your own templates with `{placeholders}` |
| **AI YouTube** | Paste any video link → why it worked, summary, TL;DR, takeaways, quotes, chapters, blog post |
| **Producer chat** | Persistent chats with an assistant that knows your niche, voice, week and ideas |
| **Library** | Everything you saved: search, star, edit, download as Markdown |
| **Settings** | Profile, brand voice, AI provider, keys (encrypted), web search, Memory, automation, research thresholds, diagnostics, AI usage |
| **Script wizard** | Pick a title → pick and edit a chapter plan → the draft streams in following it |
| **Facts tab** | Every claim in a script checked against live web results (Serper or Perplexity), with links |
| **Memory** | Search across your own scripts, transcripts, Library, notes and ideas; used by drafts and chat |
| **Ctrl+K** | Search everything from anywhere |
| **Upload documents** | PDF, Word, text, Markdown, CSV and SRT/VTT subtitles into the Library (and Memory) |
| **Listen** | Hear a script (or a selection) read aloud to check pacing: OpenAI or ElevenLabs voices |
| **Thumbnail mockups** | 16:9 mockups from a scene + text, optionally with her own photos as reference (OpenAI gpt-image or fal.ai) |
| **Cut Shorts** | Vizard finds the best moments in a published video and returns vertical clips with captions and viral scores |

| **Thumbnail critic** | AI vision review of her thumbnail (upload, mockup or published video), optionally against up to 3 breakouts |
| **B-roll clips** | Short generated shots for `[B-ROLL]` notes via fal.ai (Kling 3, Veo 3.1 Fast), optionally starting from a mockup or photo |
| **Clean up audio** | Remove background noise from a recording (ElevenLabs voice isolator) |
| **Export** | Scripts (any sections), chats and Library documents as Word, PDF (print), Markdown or text |
| **First-run tour** | A one-minute walkthrough of every page; replay it from the sidebar |
| **Copilot** (Ctrl+I) | Agentic assistant on every page: floating or docked, page-aware (current script, selection), Agent/Ask modes, any model from any provider with a key, `/` commands, file attachments, voice input, stop, history, projects, feedback. In Agent mode it proposes changes you Apply (and can Undo) |
| **Dark mode** | Light / Dark / System switch in the sidebar, login screen and Settings (Ctrl+Shift+L) |
| **Love notes 💕** | A note of the day, a hello each morning and cheers at big moments; sign them and add your own in Settings |
| **Teleprompter** | Full-screen prompter from any script: speed in words per minute with an honest time-left, `[VISUAL]`/`[B-ROLL]` cues hidden (toggle with C), `[YOUR STORY]` highlighted, ←/→ (or a clicker's PageUp/PageDown) to re-read a paragraph, mirror mode, 3-2-1 countdown, screen kept awake |
| **Version history** | Every script keeps old text before it's overwritten: every 10 minutes while typing, before any large edit (AI, Copilot, big paste or delete), and when saved by hand. Compare with now, restore (which itself is kept) |
| **My channel** | Her long-form videos scored against her own median (videos under 7 days marked early), and **Viewer asks**: comments (hers or any video, also from Breakouts) turned into questions, requests, confusion and pushback, with quotes checked word for word against the real comments. One click makes an idea; unused ones feed the next idea batch, alongside her own best and worst performers |
| **Structure check** | Next to the editor: time per section (150 wpm) and retention flags with jump-to-line: opening over 30 s, a section over 3 min, 90 s+ with no `[VISUAL]`/`[B-ROLL]`, too few chapters, a long CTA, unfilled `[YOUR STORY]`/`[VERIFY]`. Copies estimated YouTube chapter timestamps (`ScriptStructure`, no AI) |
| **Next step** | Dashboard card (and Copilot's overview) naming the one thing to do for this week's video: pick, draft, fix hard AI tells, review, fill gaps, film, package, check in, linking straight to the right tab (`NextStep`) |
| **Feed preview** | Package tab: her title candidates and thumbnail (a mockup, or a local image that never leaves the browser) in a YouTube-style grid among the top breakouts from her niche, desktop or phone, with length warnings, a blind shuffle mode and "Use as title" |
| **Social (sidebar section)** | **Calendar**: one week across LinkedIn, Instagram, X and YouTube with drag-to-schedule, YouTube due/published dates and weekly targets per platform. **Repurpose**: a script, transcript, Library document, note or pasted text into native drafts (LinkedIn post/carousel, X post/thread, Instagram carousel/Reel/post/Story, YouTube Short/community post) in one AI call. **LinkedIn / Instagram / X pages**: posts by status, this week's progress, best posts by logged engagement |
| **Post composer** | `/posts/:id`: text plus thread posts, slides or beats; live feed preview with the "see more" cut-off; platform checks (X 280 with links as 23 or Premium, LinkedIn 3,000 and the 140-character hook, Instagram 2,200 / 5-hashtag warning / 20 slides) plus the AI-tell linter; split text into a thread; carousel slides as 1080×1350 PNGs or a LinkedIn PDF; schedule, mark posted, log impressions/likes/comments/shares/saves; copy to another platform. Nothing is posted for her: "Open X/LinkedIn" copies the text and opens the app |
| **Sidebar** | An icon rail by default with an Expand/Collapse toggle (remembered per browser); on phones a top bar and a slide-in drawer that closes on navigation, backdrop tap or Esc |
| **Usage limits** | Monthly caps for AI tokens, mockups and read-aloud characters; an optional spend estimate from her own prices |

Read-aloud, mockups and Shorts are optional and billed by each provider per use.

### Ported from MagicAI's domains

- `app/Domains/Engine/Catalog/ModelCatalog` (from `Entity\Enums\EntityEnum`): known chat models with
  reasoning rules (no temperature, `max_completion_tokens`, `reasoning_effort` resolved to the nearest
  supported value), vision support and deprecation; drives the Settings model picker.
- Vision (from `AiCompletionService::completeWithImage`): `Llm::see()`, images as OpenAI data-URI parts
  or Anthropic base64 blocks.
- Key pool (from `HasApiKeys`): several comma-separated keys per provider; the next one is tried on
  401/403/429/5xx.
- `app/Domains/Usage/UsageLimit` (from `Entity\Concerns\HasCreditLimit`) and per-unit usage rows
  (from `AITokenType` / the Calculate concerns).
- Login throttling (from `Helpers\RateLimiter`): 5 wrong passwords per email + IP → 15-minute lock.
- All 112 chat models from `EntityEnum` (`app/Domains/Engine/Catalog/magicai_models.php`, generated) merged
  with the curated catalog; the Copilot can use any provider via "More AI keys".

### Copilot internals

`app/Domains/Copilot`: `CopilotAgent` runs a native function-calling loop (`EngineDriver::toolChat`:
OpenAI `tools`/`tool_calls`, Anthropic `tool_use`/`tool_result`) for up to 8 steps, streaming
`status` / `tool` / `action` / `client` / `note` / `data` / `done` events. `CopilotTools` defines
read tools (run immediately), client tools (open a page) and write tools, which are stored as
`copilot_actions` proposals and only run on Apply, with Undo. Generated audio
and images are stored in `storage/app/private/media` and only served to the logged-in user.

## Automation

Breakout scan (daily 07:00), feed refresh (every 3 h), weekly check-in (Mondays 08:00), Memory
index (03:30) and clean-up (04:00), in her timezone. Keep the scheduler running next to the app:

```sh
php artisan schedule:work
```

Each job also runs by hand (`php artisan studio:scan`, `studio:feeds`, `studio:checkin`,
`studio:memory`, `studio:prune`) or from Settings → Automation → Run.

## Run it

Requires PHP 8.3+ with `pdo_sqlite`, `mbstring`, `openssl`, `curl` (and `zip` for Word uploads), and Composer.

```sh
cd creator-studio
composer install
cp .env.example .env        # first time only
php artisan key:generate    # first time only
touch database/database.sqlite
php artisan migrate
php artisan serve
```

Open http://localhost:8000 and create your account (the first visit sets up the one and only user).
Then go to **Settings** and add an AI key; YouTube and AssemblyAI keys unlock breakouts/check-ins and
live dictation.

Use `http://localhost` (or HTTPS): browsers only allow the microphone on secure origins.

## AI providers

Pick one in Settings; everything switches over. Drivers live in `app/Domains/Engine`:

OpenAI · Anthropic (Claude) · Google Gemini · OpenRouter · DeepSeek · xAI · Perplexity · Together ·
AssemblyAI LLM Gateway (can reuse the dictation key) · any OpenAI-compatible server.

Every call is logged in `ai_usages` (feature, model, tokens, time) and summarised in Settings.

**Streaming.** Script drafts, reviews, Substack posts, edit briefs, Toolkit runs, AI YouTube and the
producer chat stream their text as it's written (Server-Sent Events, `POST /api/stream/...`).
The transport is ported from MagicAI's `StreamService` but reads the provider's stream
incrementally (`app/Domains/Engine/Stream`). Behind nginx keep `X-Accel-Buffering: no` honoured;
`php artisan serve` handles one request at a time, so other requests wait while a stream runs.

**Recording transcripts.** Upload an audio or video file on a script's Transcript tab (or in AI YouTube).
AssemblyAI handles full video files with the dictation key; OpenAI Whisper (25 MB max) is used when
OpenAI is the AI engine and there's no AssemblyAI key. For big video files raise PHP's limits:

```ini
upload_max_filesize = 512M
post_max_size = 512M
```

## Code map

```
app/Domains/Engine/     provider enum, driver contract, OpenAI-compatible + Anthropic drivers, EngineManager
app/Domains/Toolkit/    ToolRegistry (built-in templates), CustomTool + Document models
app/Domains/Chat/       Conversation + ChatMessage models
app/Domains/Engine/Stream/  SSE transport (StreamService) and incremental upstream SseParser
app/Domains/Feeds/      FeedReader (RSS + Atom, private-address guard) and Feed/FeedItem models
app/Domains/Usage/      AiUsage model and summary
app/Services/           Llm (the one door to the AI), Prompts, AiTellsLinter, YouTube, YouTubeTranscript,
                        Transcription (AssemblyAI + Whisper), Dictation
app/Http/Controllers/   ApiController (core pipeline) and Api/* (Toolkit, Library, YouTube, Chat,
                        ScriptStream, Transcription, Feeds)
routes/web.php          /api/* JSON routes; everything else serves public/index.html
public/js/              core.js (DOM, API, Markdown, router), components.js, icons.js, pages/*.js
public/css/             studio.css (tokens, layout), components.css
```

## Tests

```sh
php vendor/bin/phpunit
```

The AI, YouTube, transcription and feed calls are faked in tests (`Llm::$fake`, `YouTubeTranscript::$fake`,
`Transcription::$fake`, `FeedReader::$fake`, `Http::fake`).

## Notes

- API keys are stored encrypted with `APP_KEY`. Changing `APP_KEY` makes saved keys unreadable; re-enter them.
- Caption fetching uses YouTube's public watch page and is sometimes blocked. Every place that needs a
  transcript also accepts a pasted one.
- `AuthController`, `ChannelController`, `DashboardController`, `DictationController`, `IdeaController`,
  `ScriptController`, `SettingsController` and `WeekController` are from an earlier Blade version.
  They aren't routed and can be removed.
