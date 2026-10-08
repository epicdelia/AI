import AppKit

/// Option+Space anywhere in macOS, via a keyboard event tap (needs Accessibility permission).
/// The keys are swallowed so the app you're in doesn't type a space. Key repeat is ignored.
final class Hotkey {
    var onDown: (@MainActor () -> Void)?
    var onUp: (@MainActor () -> Void)?
    private var tap: CFMachPort?
    private var spaceHeld = false
    private static let space: Int64 = 49

    /// False if macOS refused the tap (Accessibility not granted yet).
    var isRunning: Bool { tap.map { CGEvent.tapIsEnabled(tap: $0) } ?? false }

    @discardableResult
    func start() -> Bool {
        if isRunning { return true }
        let mask = (1 << CGEventType.keyDown.rawValue) | (1 << CGEventType.keyUp.rawValue)
        guard let tap = CGEvent.tapCreate(tap: .cgSessionEventTap, place: .headInsertEventTap, options: .defaultTap,
                                          eventsOfInterest: CGEventMask(mask), callback: hotkeyCallback,
                                          userInfo: Unmanaged.passUnretained(self).toOpaque()) else { return false }
        let source = CFMachPortCreateRunLoopSource(kCFAllocatorDefault, tap, 0)
        CFRunLoopAddSource(CFRunLoopGetMain(), source, .commonModes)
        CGEvent.tapEnable(tap: tap, enable: true)
        self.tap = tap
        return true
    }

    fileprivate func handle(_ type: CGEventType, _ event: CGEvent) -> Unmanaged<CGEvent>? {
        if type == .tapDisabledByTimeout || type == .tapDisabledByUserInput {
            if let tap { CGEvent.tapEnable(tap: tap, enable: true) }
            return Unmanaged.passUnretained(event)
        }
        guard event.getIntegerValueField(.keyboardEventKeycode) == Hotkey.space else { return Unmanaged.passUnretained(event) }
        if type == .keyDown {
            let f = event.flags
            let combo = f.contains(.maskAlternate) && !f.contains(.maskCommand) && !f.contains(.maskControl)
            if !combo && !spaceHeld { return Unmanaged.passUnretained(event) }
            if !spaceHeld {
                spaceHeld = true
                if let onDown { onMain(onDown) }
            }
            return nil
        }
        if type == .keyUp && spaceHeld {
            spaceHeld = false
            if let onUp { onMain(onUp) }
            return nil
        }
        return Unmanaged.passUnretained(event)
    }
}

private func hotkeyCallback(proxy: CGEventTapProxy, type: CGEventType, event: CGEvent,
                            refcon: UnsafeMutableRawPointer?) -> Unmanaged<CGEvent>? {
    guard let refcon else { return Unmanaged.passUnretained(event) }
    return Unmanaged<Hotkey>.fromOpaque(refcon).takeUnretainedValue().handle(type, event)
}
