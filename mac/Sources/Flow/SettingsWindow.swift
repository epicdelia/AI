import AppKit
import AVFoundation

/// API key, personal dictionary, snippets and permission buttons.
@MainActor
final class SettingsWindow: NSObject, NSWindowDelegate {
    var onCheck: (() -> Void)?
    private var window: NSWindow?
    private let keyField = NSSecureTextField()
    private let dictView = NSTextView()
    private let snippetView = NSTextView()
    private let status = NSTextField(wrappingLabelWithString: "")

    func show() {
        if window == nil { build() }
        keyField.stringValue = Keychain.apiKey() ?? ""
        dictView.string = Prefs.dictionary
        snippetView.string = Prefs.snippets
        NSApp.activate(ignoringOtherApps: true)
        window?.makeKeyAndOrderFront(nil)
    }

    func setStatus(_ text: String) { status.stringValue = text }

    private func build() {
        let w = NSWindow(contentRect: NSRect(x: 0, y: 0, width: 520, height: 600),
                         styleMask: [.titled, .closable, .miniaturizable], backing: .buffered, defer: false)
        w.title = "Flow Settings"
        w.isReleasedWhenClosed = false
        w.delegate = self

        keyField.placeholderString = "Paste your AssemblyAI API key"
        let intro = NSTextField(wrappingLabelWithString:
            "Click into any text box in any app, hold ⌥Space, speak, and let go: polished text is typed where your cursor is. "
            + "Tap ⌥Space once for hands-free, then tap again to finish. Select text first to edit it by voice.")
        let keyHelp = note("Get a key at assemblyai.com/dashboard. It's stored in your Mac's Keychain and only sent to AssemblyAI.")

        let stack = NSStackView(views: [
            intro,
            label("AssemblyAI API key"), keyField, keyHelp,
            label("Personal dictionary (names and jargon, one per line)"), scroll(dictView, placeholder: "AssemblyAI\nSiobhan\nKubernetes"),
            label("Snippets: say the cue, get the exact text (one per line: cue => text)"),
            scroll(snippetView, placeholder: "my calendly link => https://calendly.com/you/30min"),
            row([button("Save", #selector(save)), button("Check setup", #selector(check)),
                 button("Allow microphone", #selector(allowMic)), button("Allow Accessibility", #selector(allowAX))]),
            status,
        ])
        stack.orientation = .vertical
        stack.alignment = .leading
        stack.spacing = 8
        stack.edgeInsets = NSEdgeInsets(top: 20, left: 20, bottom: 20, right: 20)
        stack.translatesAutoresizingMaskIntoConstraints = false
        let content = NSView()
        content.addSubview(stack)
        NSLayoutConstraint.activate([
            stack.leadingAnchor.constraint(equalTo: content.leadingAnchor),
            stack.trailingAnchor.constraint(equalTo: content.trailingAnchor),
            stack.topAnchor.constraint(equalTo: content.topAnchor),
            stack.bottomAnchor.constraint(lessThanOrEqualTo: content.bottomAnchor),
        ])
        for v in stack.arrangedSubviews where !(v is NSStackView) {
            v.widthAnchor.constraint(equalTo: stack.widthAnchor, constant: -40).isActive = true
        }
        w.contentView = content
        w.center()
        window = w
    }

    private func label(_ text: String) -> NSTextField {
        let l = NSTextField(labelWithString: text)
        l.font = .systemFont(ofSize: 12, weight: .semibold)
        return l
    }

    private func note(_ text: String) -> NSTextField {
        let l = NSTextField(wrappingLabelWithString: text)
        l.font = .systemFont(ofSize: 11)
        l.textColor = .secondaryLabelColor
        return l
    }

    private func scroll(_ view: NSTextView, placeholder: String) -> NSScrollView {
        let s = NSScrollView()
        s.hasVerticalScroller = true
        s.borderType = .bezelBorder
        view.isRichText = false
        view.font = .systemFont(ofSize: 13)
        view.isAutomaticQuoteSubstitutionEnabled = false
        view.isAutomaticDashSubstitutionEnabled = false
        view.autoresizingMask = [.width]
        view.toolTip = "For example:\n" + placeholder
        s.documentView = view
        s.heightAnchor.constraint(equalToConstant: 90).isActive = true
        return s
    }

    private func row(_ views: [NSView]) -> NSStackView {
        let r = NSStackView(views: views)
        r.orientation = .horizontal
        r.spacing = 8
        return r
    }

    private func button(_ title: String, _ action: Selector) -> NSButton {
        NSButton(title: title, target: self, action: action)
    }

    @objc private func save() {
        let ok = Keychain.setAPIKey(keyField.stringValue)
        Prefs.dictionary = dictView.string
        Prefs.snippets = snippetView.string
        Prefs.workingModel = nil  // a new key may have access to different models
        setStatus(ok ? "Saved ✓" : "Couldn't save the key to the Keychain.")
    }

    @objc private func check() {
        save()
        onCheck?()
    }

    @objc private func allowMic() {
        Task {
            let ok = await Recorder.requestPermission()
            setStatus(ok ? "Microphone allowed ✓" : "Microphone blocked. Turn Flow on under System Settings → Privacy & Security → Microphone.")
            if !ok, let url = URL(string: "x-apple.systempreferences:com.apple.preference.security?Privacy_Microphone") {
                NSWorkspace.shared.open(url)
            }
        }
    }

    @objc private func allowAX() {
        _ = AXIsProcessTrustedWithOptions(["AXTrustedCheckOptionPrompt": true] as CFDictionary)
        if let url = URL(string: "x-apple.systempreferences:com.apple.preference.security?Privacy_Accessibility") {
            NSWorkspace.shared.open(url)
        }
        setStatus("Turn Flow on in the Accessibility list. ⌥Space starts working within a few seconds.")
    }
}
