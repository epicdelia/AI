import AppKit
import ApplicationServices

/// What's focused when dictation starts, read through the Accessibility API (best effort: some apps,
/// especially Electron ones, expose little).
struct FocusContext {
    var bundleID: String?
    var singleLine = false
    var before: String?      // text before the caret, for smart spacing
    var selection: String?   // selected text -> command mode

    static func capture() -> FocusContext {
        var ctx = FocusContext(bundleID: NSWorkspace.shared.frontmostApplication?.bundleIdentifier)
        let system = AXUIElementCreateSystemWide()
        var focused: CFTypeRef?
        guard AXUIElementCopyAttributeValue(system, kAXFocusedUIElementAttribute as CFString, &focused) == .success,
              let ref = focused, CFGetTypeID(ref) == AXUIElementGetTypeID() else { return ctx }
        let element = ref as! AXUIElement
        func attr(_ name: String) -> CFTypeRef? {
            var v: CFTypeRef?
            return AXUIElementCopyAttributeValue(element, name as CFString, &v) == .success ? v : nil
        }
        let role = attr(kAXRoleAttribute) as? String
        ctx.singleLine = role == (kAXTextFieldRole as String) || role == (kAXComboBoxRole as String)
        if let selected = attr(kAXSelectedTextAttribute) as? String, !selected.isEmpty { ctx.selection = selected }
        if let value = attr(kAXValueAttribute) as? String, let rangeRef = attr(kAXSelectedTextRangeAttribute),
           CFGetTypeID(rangeRef) == AXValueGetTypeID() {
            var range = CFRange()
            if AXValueGetValue(rangeRef as! AXValue, .cfRange, &range) {
                let utf16 = Array(value.utf16)
                let end = max(0, min(range.location, utf16.count))
                ctx.before = String(decoding: utf16[max(0, end - 200)..<end], as: UTF16.self)
            }
        }
        return ctx
    }
}

enum Inserter {
    static var canType: Bool { AXIsProcessTrusted() }

    /// Types the text into the focused app by pasting it, then puts your clipboard back.
    /// Returns false (text left on the clipboard) if Flow isn't allowed to send keystrokes.
    static func type(_ text: String) -> Bool {
        let pb = NSPasteboard.general
        let saved = snapshot(pb)
        pb.clearContents()
        let item = NSPasteboardItem()
        item.setString(text, forType: .string)
        item.setString("", forType: NSPasteboard.PasteboardType("org.nspasteboard.TransientType"))  // clipboard managers skip it
        pb.writeObjects([item])
        guard canType else { return false }
        let source = CGEventSource(stateID: .combinedSessionState)
        let vKey: CGKeyCode = 9
        for down in [true, false] {
            let e = CGEvent(keyboardEventSource: source, virtualKey: vKey, keyDown: down)
            e?.flags = .maskCommand
            e?.post(tap: .cghidEventTap)
        }
        let ours = pb.changeCount
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.8) {
            guard pb.changeCount == ours, !saved.isEmpty else { return }
            pb.clearContents()
            pb.writeObjects(saved)
        }
        return true
    }

    /// Leaves the text on the clipboard for the user to paste.
    static func copy(_ text: String) {
        NSPasteboard.general.clearContents()
        NSPasteboard.general.setString(text, forType: .string)
    }

    private static func snapshot(_ pb: NSPasteboard) -> [NSPasteboardItem] {
        (pb.pasteboardItems ?? []).map { item in
            let copy = NSPasteboardItem()
            for type in item.types { if let data = item.data(forType: type) { copy.setData(data, forType: type) } }
            return copy
        }
    }
}
