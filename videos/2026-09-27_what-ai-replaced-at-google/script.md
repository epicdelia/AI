# What AI actually replaced at my old Google job

**Source idea:** Sep 26 brief #1 · **Signal:** Delia's 22 Sep reel at 25.9x · **Target:** about 10 min (about 1,500 spoken words)
**Status:** v2, after the linter and combined review (see `review.md`). Every `[DELIA: ...]` slot needs a real story from you. The pipeline never invents your anecdotes.

## Titles
1. What AI actually replaced at my old Google job
2. I sorted my old Google job into "AI does it now" and "AI can't touch it"
3. Ex-Google engineer: the part of my job AI made MORE valuable

## Thumbnail text
1. "It replaced THIS" (you pointing at a crossed-out task list)
2. "Not the coding"
3. "2 jobs left"

## Chapters
00:00 Cold open · 00:40 The rules · 01:30 Gone · 04:00 Helps, but never drives · 06:30 Worth more now · 08:45 So should you still learn to code? · 09:40 Outro

---

## Cold open (0:00 to 0:40)
[VISUAL: you at desk, whiteboard behind you with 7 sticky notes, one per task from your old job. MUST]

Okay so I made a little list. Everything I actually did in a normal week as a software engineer at Google. Not the job description, the real stuff.

[VISUAL: slow push-in on the sticky notes]

And I'm gonna rip off every single thing an AI can do for me now. Like, today, with tools I actually pay for.

[VISUAL: you rip off the first sticky note, fast cut]

For the people who skip to the comments: the coding goes early. What's left on the board at the end surprised me. Kind of a lot.

## The rules (0:40 to 1:30)
[VISUAL: talking head, lower-third "ex-Google · Gmail + Cloud"]

Quick context so you know I'm not making this up. I was on the Gmail team, and then I was a Cloud Technical Solutions Engineer, which is a fancy way of saying customers' stuff broke and I had to figure out why. Fast.

And the stakes are real. In 2024 Google said about a quarter of its new code was AI-generated. This April, Sundar said 75%. That's their number, not mine. Still, that's a big jump in eighteen months.
[VISUAL: on-screen text "25% (Oct 2024) → 75% (Apr 2026)" with source "Alphabet / Sundar Pichai"]

So, three piles.
[VISUAL: three columns drawn on whiteboard: GONE / HELPS / WORTH MORE]

Does it now. Helps, but I don't trust it alone. And the weird one: stuff that got *more* valuable.

And I'm only counting things I've actually tried myself. No "in five years AI will..." stuff. We have enough of that.

## Chapter 1: Gone (1:30 to 4:00)

### Boilerplate and unit tests
[VISUAL: screen rec, Claude generating a test file for a small function. Zoom on the output]

First sticky note. Writing tests for code that already works.

You know this feeling if you've shipped anything. You finished the actual thing, and now you have to write forty lines proving it does what you already know it does.

This is gone. I give it the function, it writes the tests, and they're often better than mine, because it actually tests the edge cases I'd skip at 6pm.

[DELIA: 1 line. Your real "I used to skip this" confession, e.g. how you actually handled test coverage.]

[VISUAL: rip off sticky note]

### Reading code you've never seen
[VISUAL: screen rec, asking Claude to explain an unfamiliar open-source repo]

Second one's bigger than people think. When you join a team at a company that size, you spend weeks just reading. Somebody else's code, written years ago, by someone who left.

Now? I point an AI at a codebase and ask "where does this request actually get handled" and I get an answer in about a minute. Is it always right? No. But it gets me to the right file, and that used to be most of the work.

[DELIA: your onboarding story. How long it actually took you to feel useful on a new team.]

[VISUAL: rip off sticky note]

### The first draft of anything
[VISUAL: design doc template, AI filling in the sections]

Third. Design docs, status updates, that email to three teams explaining why the thing is late. The first draft is gone.

The *draft* is gone. Knowing what goes in it isn't. Keep that in your pocket.

[VISUAL: rip off sticky note. Board now has 4 left]

So that's three stickies in under three minutes, and yeah, that's a chunk of the week. If your whole job was those three things, I'm not gonna lie to you, that's a scary video to watch. I'm sorry.

But look at what's still on the board.

## Chapter 2: Helps, but never drives (4:00 to 6:30)

### Code review
[VISUAL: screen rec of an AI review comment on a PR, highlight one good catch and one confidently wrong one]

Code review. AI's pretty good at the first pass, I'll give it that. It catches the missing null check, the weird naming, the thing you forgot to close.

But here's what it can't do yet. It doesn't know that this change is gonna break the team next door, because that's not in the code. At least in my experience, that lived in a meeting from three weeks ago. [VERIFY: keep only if true for you]

So it reviews, I decide. It's a really confident intern.

[VISUAL: move sticky note to HELPS column]

### Debugging something that's on fire
[VISUAL: B-ROLL, pager / phone buzzing at night, dark room]

On-call. Something's broken, real users are affected, and your heart rate is doing a thing.

AI is great at "here are the five most likely causes." Genuinely helpful. It's terrible at knowing which of those five is the one, because that takes context about *this* system on *this* day.

[DELIA: a real on-call or Cloud customer fire, told fast. What broke, what the fix actually was, and why you knew. This is the emotional center of the video, so keep it true.]

[VISUAL: move sticky note to HELPS]

## Chapter 3: Worth MORE now (6:30 to 8:45)
[VISUAL: the two sticky notes left on the board, slow push-in]

Okay, this is the part that actually changed how I think about all this.

### Explaining it to a stressed human
[VISUAL: B-ROLL, you on a video call, blurred screen, then cut to a tense clock close-up]

When I was a Cloud TSE, half the job wasn't the fix. It was the customer. Someone whose business is down, on a call, and they need to trust you *before* you have the answer.

AI can write the explanation. It can't be the person the customer trusts on that call.
[VISUAL: split screen, AI-written incident summary on the left, your face on the right]

The more AI writes the code, the more someone has to be the human the customer actually believes.

[DELIA: 1 line, the most stressful customer call you remember, no names.]

### Deciding what NOT to build
Last sticky. Saying no.

When code gets cheap, everyone just builds more junk, faster.
[VISUAL: fast montage of half-built dashboards and abandoned internal tools, 3 seconds]

So the person who looks at a plan and goes "we shouldn't build this at all" just got way more valuable.

[VISUAL: close-up of the two stickies left. Then pull back to show the board]

That's what's left. Neither one is writing code. Weird, right?

## So should you still learn to code? (8:45 to 9:40)
[VISUAL: screen rec of your DMs, blurred names, dozens of "should I still learn to code??"]

Okay, this question lives in my DMs rent-free, so here's my slightly unpopular answer.

Yes, but I'd learn it backwards. I started coding in 2019 and I spent months memorizing syntax. Today I'd skip most of that.
[VISUAL: cut to screen rec, an AI-written function with a bug, you circling it]

Day one, I'd take code an AI wrote, and try to break it. Find the bug it's confident about. Because if 75% of the code is AI-written, the job is catching the part that's wrong, and the AI isn't gonna point at the broken part for you lol.

[DELIA: 1 line, the first bug you remember finding in someone else's code, if you have one.]

## Outro (9:40 to 10:00)
[VISUAL: the board, 2 stickies left]

So that's my old job, ripped apart on a whiteboard. Two stickies left, and honestly? They were always the parts I was best at. I just didn't know it.

Which pile is your job in? Let me know in the comments, I'm genuinely curious. And if you want a part two where I do this for other tech jobs, PM, designer, data, I'll make it.

[END CARD]

---

## Claims list
| # | Claim | Source | Status |
|---|---|---|---|
| 1a | Pichai: "more than a quarter of all new code at Google is generated by AI" (Q3 2024 earnings call, Oct 2024) | https://fortune.com/2024/10/30/googles-code-ai-sundar-pichai | VERIFIED (multiple outlets) |
| 1b | Pichai: 75% of Google's new code is AI-generated (Apr 2026, up from 50% the previous fall) | https://www.semafor.com/article/04/24/2026/google-ceo-says-75-of-companys-new-code-is-ai-generated , https://www.fastcompany.com/91531519/google-ceo-says-75-of-the-companys-code-is-ai-generated | VERIFIED (multiple outlets) |
| 2 | Delia was on the Gmail team, then a Cloud TSE | `delia-voice` skill bio | Delia to confirm |
| 3 | Started coding in 2019, first dev role within a year | `delia-voice` skill bio | Delia to confirm |
| 4 | AI code review misses cross-team context | Opinion, not a fact | Delia to confirm it matches her experience |
