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

Read-aloud, mockups and Shorts are optional and billed by each provider per use. Generated audio
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
