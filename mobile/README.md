# Creator Studio Mobile (Kotlin Multiplatform + Jetpack Compose)

This is the native mobile companion app for **Creator Studio** (`epicdelia/creator-studio`), built with **Kotlin Multiplatform (Android + Desktop)** and **Jetpack Compose / Compose Multiplatform**.

It runs natively as an Android App (`.apk`) and can also be launched directly on your workstation as a **Live Mobile Desktop Preview** with zero emulator overhead.

---

## Features Implemented

1. **Dashboard & Mentor Streak**:
   - Day-at-a-glance overview, video due dates, weekly goal streak tracker.
   - Quick action shortcuts (Dictate, Scripts, Deals).
   - Real-time studio stats (Audience reach, active brand pipeline).

2. **Flow Voice Dictation**:
   - Push-to-talk voice recorder HUD with live waveform animations.
   - Speech-to-text transcript viewer and AI polishing engine.
   - One-tap "Save as YouTube Idea" conversion.

3. **YouTube Studio & Script Hub**:
   - **Breakout Radar**: Videos beating their channel median by multiplier (e.g. `4.37x`).
   - **Idea Bank**: Card deck with instant greenlighting and archiving.
   - **Script Studio**: Chapter alignment, word count, and real-time `ai_tells` retention warnings.
   - **Teleprompter Mode**: Fullscreen, auto-scrolling studio teleprompter with variable speed controls (0.5x – 5x) and camera mirror toggle.

4. **Social Suite & Repurposing**:
   - Repurposing Arbitrage Engine (turning scripts into native posts for X, LinkedIn, Instagram, TikTok, Threads).
   - Platform filters, scheduled post badges, and character counters.

5. **Brand Deals CRM**:
   - Pipeline value calculator, contract stage badges, due dates, and deliverables tracker.

6. **Autonomous Multi-Agent Swarms**:
   - Mission status tracker (`ScriptRetentionAuditor`, `SponsorNegotiator`, `EvergreenRecycler`).
   - Live step progress bars and agent deliberation logs.

7. **Universal Agentic Copilot**:
   - Floating action drawer accessible across all screens.
   - Streaming responses with Markdown rendering.
   - Interactive Action Cards (e.g. "Apply hook update into Script #101").

---

## How to Run & View Locally

### 1. View Locally on your PC (Compose Desktop Preview)

You can view and test the mobile application inside an interactive mobile viewport window directly on Windows without starting an emulator:

```bash
cd c:\AI-master\mobile
./gradlew run
```

*Note: Requires JDK 17 or 21 (e.g., Eclipse Temurin or OpenJDK).*

### 2. Run on Android Device / Emulator

To build and run the native Android app:

```bash
cd c:\AI-master\mobile

# Build the debug APK:
./gradlew assembleDebug

# The generated APK will be at:
# composeApp/build/outputs/apk/debug/composeApp-debug.apk

# Install to connected device or emulator via adb:
adb install composeApp/build/outputs/apk/debug/composeApp-debug.apk
```

---

## Project Structure

```
mobile/
├── composeApp/
│   ├── build.gradle.kts
│   └── src/
│       ├── commonMain/kotlin/com/techunicorn/studio/
│       │   ├── App.kt                             # Shell, Scaffold & Nav
│       │   ├── domain/models/Models.kt            # Studio Data Models
│       │   ├── data/api/StudioApiClient.kt        # Ktor Client & Endpoints
│       │   ├── ui/
│       │   │   ├── theme/                         # Brand Colors, Type & Theme
│       │   │   ├── navigation/Destinations.kt     # Bottom Navigation
│       │   │   ├── components/CopilotDrawer.kt    # Agentic Copilot Sheet
│       │   │   └── screens/
│       │   │       ├── dashboard/                 # Overview & Streak
│       │   │       ├── flow/                      # Voice Dictation & Polishing
│       │   │       ├── scripts/                   # Radar, Ideas & Teleprompter
│       │   │       ├── social/                    # Repurposing & Scheduling
│       │   │       ├── deals/                     # Sponsorship CRM
│       │   │       └── agents/                    # Swarm Missions & Evals
│       ├── androidMain/kotlin/com/techunicorn/studio/
│       │   └── MainActivity.kt                    # Android ComponentActivity
│       └── desktopMain/kotlin/com/techunicorn/studio/
│           └── Main.kt                            # Desktop Live Mobile Window
├── gradle/
│   ├── libs.versions.toml                         # Dependency Version Catalog
│   └── wrapper/                                   # Gradle Wrapper
├── build.gradle.kts
├── settings.gradle.kts
└── gradlew.bat
```

