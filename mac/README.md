# Flow for Mac

A menu-bar app: click into any text box in any app, hold **⌥Space**, speak, let go. Polished text is typed
where your cursor is. Tap ⌥Space once for hands-free; tap again to finish. Select text first to edit it by voice.

It talks to AssemblyAI directly (live transcription + LLM Gateway polish) with your own API key, stored in the
macOS Keychain. No Flow server is involved. Prompts are generated from `app.py`
(`python scripts/export_mac_prompts.py`) so the Mac app and the web app polish text the same way.

## Install a test build
1. GitHub → Actions → **Mac app** → latest run → download **Flow-mac**, unzip, drag `Flow.app` to Applications.
2. Until builds are signed with the Developer ID: open it once, then System Settings → Privacy & Security →
   **Open Anyway**.
3. Allow **Microphone** and **Accessibility** when asked (Accessibility is what lets ⌥Space work everywhere and
   lets Flow type for you). Unsigned test builds lose the Accessibility permission on every new build: remove
   Flow from the list and add it again.
4. Menu-bar mic icon → Settings → paste your AssemblyAI key → **Check setup**.

## Build locally (needs Xcode command-line tools)
```
cd mac && swift test && ./build_app.sh   # -> mac/build/Flow.app
```
