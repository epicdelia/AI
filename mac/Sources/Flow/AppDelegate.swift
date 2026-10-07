import AppKit
import FlowCore
import ServiceManagement

@MainActor
final class AppDelegate: NSObject, NSApplicationDelegate, NSMenuDelegate {
    private let dictation = Dictation()
    private let hotkey = Hotkey()
    private var statusItem: NSStatusItem!
    private let menu = NSMenu()
    private let settings = SettingsWindow()
    private var tapRetry: Timer?

    func applicationDidFinishLaunching(_ notification: Notification) {
        statusItem = NSStatusBar.system.statusItem(withLength: NSStatusItem.squareLength)
        setIcon(recording: false)
        menu.delegate = self
        statusItem.menu = menu
        dictation.onPhase = { [weak self] phase in self?.setIcon(recording: phase == .recording) }
        hotkey.onDown = { [weak self] in self?.dictation.keyDown() }
        hotkey.onUp = { [weak self] in self?.dictation.keyUp() }
        settings.onCheck = { [weak self] in self?.checkSetup() }

        if Recorder.permission == .notDetermined { Task { _ = await Recorder.requestPermission() } }
        startHotkey(prompt: true)
        if Keychain.apiKey() == nil { settings.show() }
    }

    /// The tap only works once Flow is allowed under Privacy & Security → Accessibility; keep retrying until then.
    private func startHotkey(prompt: Bool) {
        if hotkey.start() { tapRetry?.invalidate(); tapRetry = nil; return }
        if prompt {
            let options = ["AXTrustedCheckOptionPrompt": true] as CFDictionary
            _ = AXIsProcessTrustedWithOptions(options)
        }
        guard tapRetry == nil else { return }
        tapRetry = Timer.scheduledTimer(withTimeInterval: 2, repeats: true) { [weak self] _ in
            MainActor.assumeIsolated { self?.startHotkey(prompt: false) }
        }
    }

    private func setIcon(recording: Bool) {
        let image = NSImage(systemSymbolName: recording ? "mic.fill" : "mic", accessibilityDescription: "Flow")
        image?.isTemplate = true
        statusItem.button?.image = image
    }

    // MARK: menu

    func menuNeedsUpdate(_ menu: NSMenu) {
        menu.removeAllItems()
        menu.addItem(disabled("Hold ⌥Space to dictate · tap it for hands-free"))
        menu.addItem(disabled(readiness()))
        menu.addItem(.separator())

        let style = NSMenuItem(title: "Style", action: nil, keyEquivalent: "")
        style.submenu = NSMenu()
        for (value, title) in [("app", "Match the app (Mail → email, Slack → message, Notion → notes)"),
                               ("message", "Message"), ("email", "Email"), ("notes", "Notes"), ("auto", "Auto")] {
            style.submenu?.addItem(choice(title, value, selected: Prefs.style == value, #selector(pickStyle(_:))))
        }
        menu.addItem(style)
        let lang = NSMenuItem(title: "Language", action: nil, keyEquivalent: "")
        lang.submenu = NSMenu()
        for (value, title) in [("en", "English"), ("multi", "Any language (auto-detect)")] {
            lang.submenu?.addItem(choice(title, value, selected: Prefs.language == value, #selector(pickLanguage(_:))))
        }
        menu.addItem(lang)
        menu.addItem(.separator())

        let paste = item("Paste last result", #selector(pasteLast))
        paste.isEnabled = dictation.lastResult != nil
        menu.addItem(paste)
        menu.addItem(item("Settings…", #selector(openSettings), key: ","))
        menu.addItem(item("Check setup", #selector(runCheck)))
        let login = item("Open at login", #selector(toggleLogin))
        login.state = SMAppService.mainApp.status == .enabled ? .on : .off
        menu.addItem(login)
        menu.addItem(.separator())
        menu.addItem(item("Quit Flow", #selector(quit), key: "q"))
    }

    private func readiness() -> String {
        if Keychain.apiKey() == nil { return "⚠︎ Add your AssemblyAI API key in Settings" }
        if Recorder.permission != .authorized { return "⚠︎ Allow microphone access in Settings" }
        if !Inserter.canType || !hotkey.isRunning { return "⚠︎ Allow Flow under Privacy & Security → Accessibility" }
        return "✓ Ready"
    }

    private func disabled(_ title: String) -> NSMenuItem {
        let i = NSMenuItem(title: title, action: nil, keyEquivalent: "")
        i.isEnabled = false
        return i
    }

    private func item(_ title: String, _ action: Selector, key: String = "") -> NSMenuItem {
        let i = NSMenuItem(title: title, action: action, keyEquivalent: key)
        i.target = self
        return i
    }

    private func choice(_ title: String, _ value: String, selected: Bool, _ action: Selector) -> NSMenuItem {
        let i = item(title, action)
        i.representedObject = value
        i.state = selected ? .on : .off
        return i
    }

    @objc private func pickStyle(_ sender: NSMenuItem) { Prefs.style = sender.representedObject as? String ?? "app" }
    @objc private func pickLanguage(_ sender: NSMenuItem) { Prefs.language = sender.representedObject as? String ?? "en" }
    @objc private func pasteLast() { dictation.pasteLast() }
    @objc private func openSettings() { settings.show() }
    @objc private func runCheck() { checkSetup() }
    @objc private func quit() { NSApp.terminate(nil) }

    @objc private func toggleLogin() {
        do {
            if SMAppService.mainApp.status == .enabled { try SMAppService.mainApp.unregister() } else { try SMAppService.mainApp.register() }
        } catch {
            dictation.pill.show(.error, "Couldn't change Open at login: \(error.localizedDescription)", hideAfter: 5)
        }
    }

    // MARK: check setup

    private func checkSetup() {
        settings.show()
        settings.setStatus("Checking…")
        startHotkey(prompt: false)
        Task {
            var rows: [String] = []
            func row(_ ok: Bool, _ text: String) { rows.append("\(ok ? "✓" : "✗") \(text)") }
            row(Recorder.permission == .authorized, Recorder.permission == .authorized ? "Microphone allowed"
                : "Microphone not allowed: click Allow microphone")
            row(Inserter.canType && hotkey.isRunning, Inserter.canType && hotkey.isRunning ? "⌥Space works and Flow can type"
                : "Allow Flow under System Settings → Privacy & Security → Accessibility")
            if let key = Keychain.apiKey() {
                do {
                    _ = try await AssemblyAI.streamingToken(key: key)
                    row(true, "Live transcription is reachable")
                } catch {
                    row(false, "Live transcription: \(error.localizedDescription)")
                }
                let gateway = Gateway(key: key, workingModel: Prefs.workingModel)
                do {
                    _ = try await gateway.complete(system: "Reply with the single word OK.", user: "Say OK.")
                    Prefs.workingModel = gateway.workingModel
                    row(true, "AI polish works with \(gateway.workingModel ?? "the default model")")
                } catch {
                    row(false, "AI polish: \(error.localizedDescription)")
                }
            } else {
                row(false, "No AssemblyAI API key yet: paste it above and press Save")
            }
            settings.setStatus(rows.joined(separator: "\n"))
        }
    }
}
