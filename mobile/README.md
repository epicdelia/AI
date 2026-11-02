# Creator Studio Mobile (Kotlin Multiplatform + Jetpack Compose)

This is the native mobile companion app for **Creator Studio** (`epicdelia/creator-studio`), built with **Kotlin Multiplatform (Android + Desktop)** and **Jetpack Compose / Compose Multiplatform**.

It runs natively as an Android App (`.apk`) and launches directly on your workstation as an interactive **Live Mobile Desktop Preview** (`./gradlew run`) with zero emulator overhead.

---

## Complete Feature Suite (Full Parity)

### 1. Public Landing Page & Booking Funnel (`LandingScreen.kt`)
- **Hero & Live Metrics**: 472K+ audience reach, 2–4 week MVP timeline, 12+ global keynotes.
- **6 Core Services**: Vibecoded MVPs, Production AI, Ex-Google Advisory, Content Creation, Keynotes, and Brand Partnerships with expandable deliverable details.
- **Keynotes & Stage Reel**: Past talks, attendee reach, and speaking themes.
- **Instant Booking Sheet**: In-app consultation scheduling.

### 2. Multi-Role Authentication & Client Portal (`ClientPortalScreen.kt`)
- **Role Switcher**: Seamless switching between **Studio Mode** (Delia), **Client Portal**, and **Public Landing**.
- **Client Project Tracker**: Open requests, pipeline progress, and milestone calls.
- **Deliverable Annotations**: Interactive revision cards with timestamped comments and one-tap approvals.
- **Brand Vault**: Color palettes, logo assets, guidelines, and VIES EU VAT validation.

### 3. Interactive Deal Room & Stripe Invoicing (`DealRoomScreen.kt`)
- **Deliverable Tier Switcher**: Essential (\$8,500), Growth (\$12,000), and Enterprise (\$18,500) with dynamic rate recalculation.
- **Cryptographic E-Signature**: Legal name signature and GDPR DPA agreement.
- **Stripe Checkout**: Direct payment and retainer confirmation.

### 4. Code Card Studio (`SnippetsScreen.kt`)
- **Ray.so / Carbon-style Card Generator**: Syntax-highlighted code & terminal card generator for social media.
- **5 Gradient Themes**: Unicorn Neon, Cyberpunk Dark, Warm Sunset, Deep Slate, Matrix Emerald.
- **Controls**: macOS traffic-light window headers, line numbers toggle, custom watermark (`@Tech.Unicorn`), and image export.

### 5. Creative Toolkit & Media (`ToolkitScreen.kt`)
- **AI Prompt Templates**: Viral Hook Generator, X Thread Cascader, Sponsor Value Pitch.
- **TTS Narration**: Audio pacing playback using Delia's cloned voice.

### 6. Personal & Love Suite (`LoveScreen.kt`)
- **Love Letters**: Sealed and opened letters with animated card expansion.
- **Memory Jar**: Curated prompt questions with memory answer log.

### 7. Today's Dashboard & Mentor Streak (`DashboardScreen.kt`)
- **Mentor Accountability**: 8-week long-form video filming streak (from `MENTOR.md`).
- **Studio Stats**: Audience reach, active brand deals (\$34.5K pipeline), and next video due date.

### 8. Flow Voice Dictation (`FlowScreen.kt`)
- Push-to-talk voice recorder with live audio wave animations, speech-to-text transcript viewer, AI polishing, and one-tap "Save as YouTube Idea".

### 9. YouTube Radar, Ideas & Teleprompter (`YouTubeHubScreen.kt`)
- **Outlier Breakout Radar**: Videos beating their channel median by multiplier (e.g. `4.37x`).
- **Idea Bank**: Card deck with instant greenlighting and archiving.
- **Teleprompter Mode**: Fullscreen, auto-scrolling teleprompter with variable speed controls (0.5x – 5.0x) and camera mirror toggle.

### 10. Social Suite & Repurposing (`SocialScreen.kt`)
- Repurposing Arbitrage Engine (turning scripts into native posts for X, LinkedIn, Instagram, TikTok, Threads) with platform preview filters and scheduled post statuses.

### 11. Autonomous Multi-Agent Swarms (`AgentsScreen.kt`)
- Multi-agent swarm missions (`ScriptRetentionAuditor`, `SponsorNegotiator`, `EvergreenRecycler`), progress bars, and deliberation logs.

### 12. Universal Agentic Copilot (`CopilotDrawer.kt`)
- Persistent floating bottom-sheet drawer with streaming markdown advice and interactive action cards ("Apply Change", "Dismiss").

---

## How to Run & View Locally

### 1. View Locally on your PC (Compose Desktop Live Window)

```bash
cd c:\AI-master\mobile
.\gradlew.bat run
```

### 2. Run on Android Device / Emulator

```bash
cd c:\AI-master\mobile
.\gradlew.bat assembleDebug

# Install on connected device or emulator via adb:
& "$env:LOCALAPPDATA\Android\Sdk\platform-tools\adb.exe" install composeApp\build\outputs\apk\debug\composeApp-debug.apk
```
