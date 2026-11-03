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

## Advanced Kotlin & Android Studio Architectural Stack

The mobile application is engineered to Google's official Android & Kotlin Multiplatform architecture standards:

### 1. Clean Architecture & Reactive MVVM / MVI (`ui/viewmodels/`)
- **`BaseViewModel`**: Coroutine-backed state holder with `viewModelScope`, immutable `uiState: StateFlow<S>`, and one-off channel events `uiEvents: SharedFlow<E>`.
- **Dedicated Feature ViewModels**:
  - `DashboardViewModel`: Real-time studio stats, weekly mentor streak tracker, and pull-to-refresh cache revalidation.
  - `YouTubeHubViewModel`: High-performance Outlier radar with Kotlin `.debounce(250)` search query filtering and multiplier thresholds.
  - `CameraPrompterViewModel`: Physics-based smooth auto-scrolling, Words-Per-Minute calculator, and teleprompter glass mirror toggling.
  - `FlowVoiceViewModel`: Live decibel spectrum sampler, streaming voice transcription, and AI polish cascades.
  - `DealRoomViewModel`: Multi-tier contract rates, cryptographic Bezier e-signature vector capture, and Stripe payment confirmation.

### 2. Dependency Injection with Koin (`di/AppModule.kt`)
- Singletons for API clients, local caches, network monitors, haptic engines, and audio samplers.
- Factory declarations for ViewModels, allowing clean dependency inversion and modular testability.

### 3. Offline-First Repository & Result Monad (`data/repository/` & `domain/result/`)
- **`StudioRepository`**: Kotlin Flow cache-first strategy emitting instant local memory/serialized data followed by background network sync.
- **`StudioResult<T>`**: Sealed algebraic monad (`Success(data, isFromCache)`, `Error(StudioError)`, `Loading`) with functional chaining (`map`, `onSuccess`, `onError`).

### 4. Hardware & Native Platform Abstractions (`platform/`)
- **Tactile Haptic Feedback Engine (`platform/haptics/`)**:
  - Android native `Vibrator` & `VibrationEffect` (light ticks, medium clicks, heavy impacts, success pulses, error alerts) with desktop fallback.
- **Reactive Network Monitor (`platform/connectivity/`)**:
  - Android `ConnectivityManager.NetworkCallback` + Desktop socket reachability.
  - Material 3 `OfflineBanner` that smoothly slides in when offline.
- **High-Fidelity Audio Sampling Engine (`platform/audio/`)**:
  - Real-time 16-band audio spectrum sampling for the glowing `AudioWaveformVisualizer`.
- **Interactive E-Signature Canvas (`ui/components/SignatureCanvas.kt`)**:
  - Android-grade touch and stylus capture with quadratic Bezier spline curve smoothing.

### 5. Type-Safe Kotlin DSL (`domain/dsl/ScriptDsl.kt`)
- Domain-Specific Language for authoring video scripts and teleprompter chapters programmatically:
  ```kotlin
  val script = creatorScript {
      title = "Building a Production App in 48h with AI"
      hook = "Everyone says AI can build a startup in a weekend..."
      chapter("Architecture", "01:15") {
          point("Clean Domain Separation")
          point("Native Kotlin Compose Multiplatform")
          warning("Add B-roll graphics to prevent drop-off at 02:45")
      }
      cta = "Grab the Creator Studio Starter Kit below."
  }
  ```

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

---

## Live Studio Data (real server, per role)

Four screens run on the real Creator Studio API instead of sample data. Each one asks you to sign in first, with the same email and password as the website (plus your authenticator code if two-factor sign-in is on):

| Screen | Who | What |
|---|---|---|
| **Needs You** | Studio owner | Everything waiting on you: client messages, late reviews, overdue invoices, usage rights ending, payouts to approve, community questions, follow-ups |
| **My Tasks** | Team | Your open production tasks with brief, due date, start/stop timer, +15 min, Done |
| **My Requests** | Clients | Approve work or ask for changes (with its review deadline), the onboarding checklist, payment terms and online payment (deposit/balance), usage rights with "Extend" |
| **Community** | Members | Live sessions (RSVP, join, replays) and courses (lessons, progress) |

- **Server address:** asked for on the sign-in screen. The default is `http://10.0.2.2:8000` (Android emulator → your computer) or `http://localhost:8000` (desktop preview). On a real phone, use your server's https address.
- **Sign-in:** the app calls `POST /api/auth/mobile-token` and gets a 90-day token, stored on the device (Android SharedPreferences, or Java Preferences on desktop). Only the token's SHA-256 hash is kept on the server. You can see and revoke devices with `GET/DELETE /api/account/devices`.
- **Code:** `data/live/` (API + models + token storage) and `ui/screens/live/LiveScreens.kt`.
