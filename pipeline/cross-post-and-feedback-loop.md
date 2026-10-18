# Cross-posting pipeline + analytics feedback loop

Written Oct 18 2026 from what's actually in the workspace: Notion "Unicorn Calendar v2" (schema read in full), "Automations" (Jun 1), "Ops v2" (Feb 18), "Creator OS" (Feb 19), "Content Strategy Workflow — Weekly Batching System" (Sep 30), the YouTube Scoreboard, and this repo's YouTube pipeline (`README.md`, `MENTOR.md`, `ROUTINE.md`).

Confidence labels: **high** = verified in your workspace or well-documented platform behaviour; **moderate** = known platform behaviour that changes often, check before building; **low** = my estimate.

---

## 0. Diagnosis

1. **You don't need more automation ideas. You need finished plumbing.** There are at least five overlapping operating docs (Ops v2, Creator OS, Automations, Weekly Batching, this repo's README), and each one partly redesigns the last. The Automations page lists ~25 workflows, and many of them are marked "Blocker", "Manual", "need to check" or "Not codified". A v4 redesign is the failure mode. Don't start one. (high)
2. **No feedback loop is possible on the current data model.** Unicorn Calendar v2 has 50+ properties and **not one metric field**. There's no post URL per platform, no post ID and no views. You can't learn from posts you can't join to their numbers. (high, read from the schema)
3. **You already built one loop, and it isn't running.** The YouTube Scoreboard W1 verdict says *"the scheduled check-in didn't run (routine connectors still not attached)"*. W1's YouTube URL is blank. W2 was last edited Oct 7 and still says `Planned`, past its Oct 11 due date. Fix this first. It takes about 10 minutes in the Routines UI. (high)
4. **The volume plans are fiction.** The Sep 30 batching plan calls for ~120 pieces/week and 3 long-form/week. The baseline is 2 long-form in 90 days (`MENTOR.md`), and the current commitment is 1/week. Creator OS targets 50K views per YouTube video, against a ~10.1k median. Cross-posting multiplies **distribution**, not **production**. If the core asset doesn't ship, there's nothing to cascade. (high)
5. **Your data will be noisy.** At ~1 long-form and ~3–5 shorts a week, most "insights" from comparing post A with post B will be noise. The loop below is designed around that. (high)
6. **Approval bottleneck.** Ops v2 already names this: Delia approves per platform, so travel stalls everything. Approve **once per content piece** and let the derivatives cascade. (high, your own finding; never implemented)

---

## 1. Data model (do this before any automation)

### 1a. Clean `Unicorn Calendar v2` (the Content DB: one row per core asset)

| Change | Why |
|---|---|
| Move `Type = Kanban` rows to a separate Tasks DB | Tasks and content in one table break every view and every count |
| Collapse Status to: `Idea → Scripted → Ready to film → Filmed → Editing → Review → Approved → Scheduled → Published` | Today there are 12 options with near-duplicates ("Ready to film" vs "Ready to film (confirmed)", "Ready to Get Started", "In Trial", "Needs me to Fix") |
| Dedupe `Platform`: drop the emoji twins (`Twitter 🐦`, `YouTube 🎥`, `LinkedIn 💼`, etc.) and the non-platforms (`Microsoft`, `Apollo`, `Calendar`, `ManyChat`, `Email`) | Grouping by platform is wrong today |
| Delete or hide the 4 `Migration*` fields, plus `Automation Stat`, `Previous Action`, `Refresh action`, `Num` | Leftover cruft. Every extra field lowers fill rate |
| **Add** `Pillar` (single select: AI & Tech / Ex-Google insider / Founder / Lifestyle) | The independent variable for the loop |
| **Add** `Format` (talking head / screen rec / skit / news react / tutorial / carousel) | Same |
| **Add** `Hook type` (contrarian / insider secret / number / question / story / live demo) + `Hook text` | Hooks are the variable most likely to show a real effect |
| **Add** `Approved` (checkbox) + `Approved by` + `Approved at` | Makes approve-once enforceable |
| **Add** `Parent` (self-relation) | A clip or LinkedIn version points to the long-form it came from |

Keep the tag lists **short and fixed**. If a tag has fewer than ~5 posts a month, it can't teach you anything. Merge it.

### 1b. New `Posts` DB: one row per platform publication

This is the join table that makes analytics possible. One core asset produces N Posts.

| Field | Type | Filled by |
|---|---|---|
| Name | title | auto: `<platform> · <content name>` |
| Content | relation → Unicorn Calendar | cascade (A3) |
| Platform | select (YouTube, YT Shorts, Instagram Reel, IG Carousel, TikTok, LinkedIn, X, Substack) | cascade |
| Status | Draft / Ready / Scheduled / Published / Failed | cascade → scheduler |
| Caption / Title | text | caption drafter |
| Scheduled for | date | human or scheduler |
| **Post URL**, **Post ID**, **Published at** | url / text / date | ledger (A1). Required for Published |
| Experiment | select: none / Trial Reel / YT Test & Compare / hook variant | human |
| `Views 1d`, `Views 7d`, `Views 28d` | number | metrics sync (A2) |
| `Reach 7d`, `Shares 7d`, `Saves 7d`, `Comments 7d`, `Follows 7d` | number | A2 |
| `Avg % viewed` (or AVD), `CTR` (YouTube only) | number | A2 |
| `Score 7d` | number = Views 7d ÷ rolling median of the last 20 posts on **that platform** | A2 |
| Last synced | date | A2 |

**Fixed snapshot windows (1d / 7d / 28d), never one "current views" field.** A 3-day-old post and a 30-day-old post aren't comparable. Every comparison uses the same window.

### 1c. New `Learnings` DB

| Field | Notes |
|---|---|
| Claim | e.g. "Contrarian hooks beat question hooks on IG Reels" |
| Metric + window | e.g. `Score 7d`, Shares/Reach |
| Evidence | relation → Posts |
| n per bucket | number |
| Effect | median ratio, e.g. 1.6x |
| Confidence | low / moderate / high (rules in §4) |
| Status | Hypothesis → Testing → Adopted / Rejected |
| Next test | the one experiment that would settle it |

### 1d. `Playbook` page

At most 300 words: the currently **Adopted** learnings, written as instructions ("Open with the claim, not the context. 1.6x Score 7d, n=12 vs 11, moderate"). The weekly retro regenerates it. Every generation skill reads it. **This page is what turns a report into a loop.**

---

## 2. Automations, ranked by value ÷ effort

Build them in this order. Don't start the next one until the previous one has run for a week without anyone babysitting it.

| # | Automation | Trigger | What it does | Value | Effort | Tokens |
|---|---|---|---|---|---|---|
| **A0** | **Fix the existing routines** | now | Attach Sandcastles + Notion connectors to "YouTube idea brief" and "YouTube mentor check-in". Backfill the W1 URL and score W2 | Unblocks the only loop you already have | 10 min | – |
| **A1** | **Publish ledger** | daily sweep (and scheduler webhook later) | Pull each account's last 48h of posts from the platform APIs. Match each one to a `Posts` row by scheduled time + caption prefix. Write URL, ID and Published at, and set Status = Published. Unmatched posts get a new row flagged `Unlinked` | The join key for everything downstream. Without it, A2–A7 are useless | M | 0 (script) |
| **A2** | **Metrics sync** | daily | For every Post at day 1, 7 and 28 after publishing, write that snapshot's fields and compute `Score 7d`. **Deterministic Python, like `ideas/channel_stats.py`, not an LLM.** YouTube + Shorts is built: `ideas/post_snapshots.py` (stateless; emits only due snapshots, keyed for idempotent writes) | The loop's input | M | 0 |
| **A3** | **Approve once → cascade** | Content `Approved` checked | Read the repurposing rules (table in §3) and create one `Posts` row per target platform with status Draft. Draft platform-native copy with the existing `social-caption-writer` / `reel-post-kit` skills. Sponsored rows: also run `content-contract-review` and block until Delia approves | Removes the per-platform approval bottleneck Ops v2 found | M | low |
| **A4** | **Long-form waterfall** | YouTube Post → Published | Transcript → Substack draft (`prompts/04_substack.md`), 3–5 clip candidates with timestamps (Opus Clip or `vidiq_generate_clips`), 1 LinkedIn post, 1 X thread. All land as Draft `Posts` with `Parent` set | Your one weekly anchor becomes 6–10 pieces. Creator OS's target is within 48h | M | medium |
| **A5** | **Weekly retro → Learnings → Playbook** | Sunday, after the mentor check-in | Reads a compact export from A2 (CSV, not Notion queries, to save tokens). Computes median `Score 7d` per Hook type / Format / Pillar per platform. Updates at most 3 Learnings, sets **exactly one** experiment for next week, rewrites the Playbook. The `growth-retro` skill is the natural home | The feedback loop itself | M | medium, once a week |
| **A6** | **Feed the Playbook into generation** | every generation run | The idea brief routine, `delia-ig-reel-writer`, `social-caption-writer` and `prompts/02_script.md` read the Playbook (≤300 words) before writing | Closes the loop. Without it, A5 is a report nobody uses | S | +~400 tokens/run |
| **A7** | **Hygiene validator** | nightly | Flags rule violations: Published without URL, Approved without Assets Link, Editor set without Edit Deadline, duplicate names, Posts with no 7d snapshot. Sends one digest line | Keeps the data trustworthy. Your own Automations page lists this as missing | S | 0 |
| **A8** | **Comment mining → Ideas** | weekly | Pulls the top questions and objections from YouTube/IG comments (`vidiq_comment_insights`) into Ideas, with source links | Ideas your audience already asked for | S | low |
| **A9** | **Scheduler push** | Post → Ready | Push the media from Assets Link plus the copy to the scheduler's API and set Scheduled | Saves ~15 min/day of Albert's time. **Lowest value here at your current volume.** Paid tool, so it's an owner decision | M | 0 |

### Why the scheduler is last, not first

Building auto-posting first is the classic mistake: it automates the cheap part and leaves the expensive part (learning what works) at zero. At ~10–20 posts a week, scheduling by hand is ~15 minutes a day. A missing ledger costs you every insight. (high)

### Platform API realities (check before building A1/A2/A9)

| Platform | Posting via API | Metrics via API | Confidence |
|---|---|---|---|
| YouTube | Data API `videos.insert`. Projects that haven't passed Google's API audit can only upload **private** videos. Daily quota is 10k units by default, and an upload costs a lot of it | Data API (views, likes) + Analytics API (CTR, AVD, retention) for your own channel | high on the audit lock; moderate on the exact quota cost |
| Instagram | Graph API content publishing for Business/Creator accounts (Reels, carousels), with a daily cap of dozens of posts | Graph API media insights (reach, shares, saves, plays). vidIQ `instagram_owner_insights` and Sandcastles also expose some of this | high |
| TikTok | Content Posting API. Unaudited apps can only post **private** videos until TikTok's audit passes | Display API: view, like, comment and share counts. Watch-time data is thin | high on the audit lock; moderate on metrics depth |
| LinkedIn | Personal-profile posting works (`w_member_social`) | Personal post analytics through the API are restricted. Plan on the scheduler's numbers or a weekly manual export | moderate |
| X | Paid API tiers. Free-tier write/read is near-useless for this | Use Typefully's/Buffer's analytics | moderate (pricing changes often) |

Conclusion: build A1/A2 **directly** for YouTube and Instagram, where the APIs are good and you own the accounts. For TikTok, LinkedIn and X, take metrics from whichever scheduler you pick, or from a weekly CSV export, rather than writing three more integrations.

---

## 3. Cross-posting rules (the table A3 reads)

The rules from your Sep 30 doc are mostly right. Encode them as data, not prose:

| Source | → Target | Rule |
|---|---|---|
| IG Reel | TikTok, YT Shorts | Always. **Export clean, never with another app's watermark**: Instagram has said it won't recommend Reels carrying visible watermarks from other apps (high) |
| IG Reel | LinkedIn | Only if Pillar ∈ {Ex-Google insider, AI & Tech} and not a meme/trend |
| Carousel | LinkedIn document post | Only if Pillar is career/insider |
| YouTube long-form | Substack, 3–5 clips, 1 LinkedIn, 1 X thread | Always (A4) |
| Hot take / meme / trend-jack | X and TikTok only | Never LinkedIn |
| Personal story | Instagram only | |
| Sponsored | Per contract only | Never cascade automatically. The deliverables list decides the platforms |

**What a cross-post must change per platform** (A3 drafts these): the first-line hook text, the caption length, the hashtag norms, and the on-screen text safe zones. The same file with a fresh caption is fine for Reels → Shorts → TikTok. It is **not** fine for LinkedIn or X, which need a rewrite.

---

## 4. The feedback loop: how to learn without fooling yourself

### What to measure, by platform (leading indicators, not vanity)

| Platform | Primary | Secondary | Confidence it's what the algorithm rewards |
|---|---|---|---|
| IG Reels | Shares (sends) ÷ Reach | Avg watch time, Saves ÷ Reach | moderate-high (Instagram leadership has repeatedly named sends and watch time) |
| TikTok | Avg % viewed / completion | Shares, Profile visits | moderate |
| YouTube long-form | CTR × Avg % viewed (first 30s retention) | Subs gained per 1k views | high |
| LinkedIn | Comments ÷ Impressions | Profile views | low-moderate |

Normalize everything to **`Score` = metric ÷ your own rolling median on that platform**, the same outlier idea Sandcastles and `find_outliers.py` already use. Never compare yourself to Theo's absolute numbers (`MENTOR.md` rule 4).

### Two kinds of evidence. Prefer the first.

1. **Within-post tests (strong).** The audience, time and topic are the same, so only the variable changes.
   - **Instagram Trial Reels**: shown to non-followers first. Post two hook variants of the same Reel. This is your best hook-testing tool, and it's free. (high that it exists; it's a native IG feature)
   - **YouTube Test & Compare**: thumbnail (and title) A/B on the same video. (high for thumbnails)
2. **Between-post comparisons (weak).** Medians of `Score 7d` per tag bucket. Confounded by topic, news cycle and posting time.

### Decision rules (my heuristic, low confidence on the exact thresholds, but you need *some* rule)

- **Hypothesis**: any pattern a human or the retro notices.
- **Moderate**: between-post, ≥8 posts per bucket, median ratio ≥1.5x, and it holds in both halves of the period.
- **High**: moderate **plus** confirmed by at least 2 within-post tests (Trial Reels / Test & Compare).
- **Adopted** into the Playbook only at moderate or above. **Rejected** if 2 within-post tests disagree.
- **One experiment per week.** Changing hook, length and format at once teaches you nothing.

### The weekly loop

```
Sun  mentor check-in (exists) ─┐
                               ├─▶ A5 retro: medians per tag → ≤3 Learnings updates → 1 experiment → Playbook
A2 metrics (daily, 0 tokens) ──┘                                                        │
                                                                                        ▼
Mon–Sat  idea brief / scripts / captions read the Playbook (A6) ─▶ content tagged with the experiment variable
                                                                                        │
                                       A1 ledger + A2 snapshots ◀── posts published ◀───┘
```

---

## 5. What NOT to build (kill list)

- The 120-pieces/week plan and 8 tweets/day. Volume that doesn't exist can't be automated. Re-baseline to what's actually shipping.
- Custom posting integrations for all 6 platforms. Two direct (YouTube, IG), the rest through a scheduler or CSV.
- Auto-publishing to TikTok or YouTube through your own API app before it passes the platform audit. The posts go out private.
- More "Improve with AI ▶️ Run" buttons in Notion before the data model is clean.
- Reminder and nudge bots. Your own Automations page says they're already noisy.
- Any metric that's a single "current" number with no snapshot window.

---

## 6. Rollout (4 weeks, sized to one person-week of Albert's time each, low confidence)

| Week | Ship | Done when |
|---|---|---|
| 1 | A0. Clean Content DB (§1a). Create Posts + Learnings DBs. Albert pastes the URL of **every** post by hand, daily | 100% of last week's posts have a URL in Posts |
| 2 | A2 for YouTube + IG (script, scheduled). A7 validator | Every post ≥7 days old has a 7d snapshot with no manual entry |
| 3 | A3 approve-once cascade. A4 long-form waterfall | Delia approves in ≤2 sessions/week. Long-form → all derivatives Drafted within 24h |
| 4 | A5 retro + A6 Playbook wired into the skills and the idea brief routine. First Trial Reel hook test | Playbook exists with ≥1 Testing claim, and next week's scripts cite it |
| 5+ | A1 automatic matching (replaces the manual URL paste), A8, then A9 if still wanted | |

### Score the system itself (put these on the Monday digest)

- Ledger coverage: % of published posts with a URL and ID (target 100%)
- Snapshot coverage: % of posts ≥7d old with `Views 7d` (target ≥95%)
- Delia approval sessions per week (target ≤2)
- Long-form → derivatives scheduled, in hours (target ≤48)
- Learnings moved to Adopted or Rejected per month (target ≥2; zero means the loop is decorative)

---

## 7. Note on the Night Shift agent team in this repo

`MISSION.md` currently points the agent team at the Flow dictation app, and agents may not edit it. To have them build A1/A2/A7 (deterministic scripts, good agent work), the owner has to rewrite `MISSION.md`, or set up a separate repo with its own mission. `CLAUDE.md` also forbids agents from adding paid services or sending messages, so the scheduler choice (A9) and any WhatsApp digest wiring stay with you.
