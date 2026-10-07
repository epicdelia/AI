import AppKit

// Menu-bar app: no Dock icon (LSUIElement in Info.plist), lives in the status bar.
MainActor.assumeIsolated {
    let app = NSApplication.shared
    let delegate = AppDelegate()
    app.delegate = delegate  // weak reference: keep the delegate alive for the app's lifetime
    app.setActivationPolicy(.accessory)
    withExtendedLifetime(delegate) { app.run() }
}
