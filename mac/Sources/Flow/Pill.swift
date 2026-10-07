import AppKit

/// The small floating status pill at the bottom of the screen. Never takes focus or clicks.
final class Pill {
    enum Look { case listening, busy, done, error }

    private let panel: NSPanel
    private let dot = NSView()
    private let label = NSTextField(labelWithString: "")
    private var hideWork: DispatchWorkItem?

    init() {
        panel = NSPanel(contentRect: NSRect(x: 0, y: 0, width: 240, height: 40),
                        styleMask: [.borderless, .nonactivatingPanel], backing: .buffered, defer: true)
        panel.level = .statusBar
        panel.isOpaque = false
        panel.backgroundColor = .clear
        panel.hasShadow = true
        panel.ignoresMouseEvents = true
        panel.collectionBehavior = [.canJoinAllSpaces, .fullScreenAuxiliary, .stationary]
        let box = NSView()
        box.wantsLayer = true
        box.layer?.backgroundColor = NSColor(calibratedRed: 0.06, green: 0.09, blue: 0.16, alpha: 0.96).cgColor
        box.layer?.cornerRadius = 20
        box.layer?.borderWidth = 1
        box.layer?.borderColor = NSColor(calibratedRed: 0.2, green: 0.25, blue: 0.33, alpha: 1).cgColor
        dot.wantsLayer = true
        dot.layer?.cornerRadius = 5
        label.textColor = NSColor(calibratedRed: 0.95, green: 0.96, blue: 0.98, alpha: 1)
        label.font = .systemFont(ofSize: 14, weight: .medium)
        label.lineBreakMode = .byTruncatingHead  // live words: keep the newest visible
        for v in [dot, label] { v.translatesAutoresizingMaskIntoConstraints = false; box.addSubview(v) }
        NSLayoutConstraint.activate([
            dot.widthAnchor.constraint(equalToConstant: 10), dot.heightAnchor.constraint(equalToConstant: 10),
            dot.leadingAnchor.constraint(equalTo: box.leadingAnchor, constant: 16),
            dot.centerYAnchor.constraint(equalTo: box.centerYAnchor),
            label.leadingAnchor.constraint(equalTo: dot.trailingAnchor, constant: 10),
            label.trailingAnchor.constraint(equalTo: box.trailingAnchor, constant: -16),
            label.centerYAnchor.constraint(equalTo: box.centerYAnchor),
        ])
        panel.contentView = box
    }

    func show(_ look: Look, _ text: String, hideAfter: TimeInterval? = nil) {
        hideWork?.cancel()
        let colors: [Look: NSColor] = [
            .listening: NSColor(calibratedRed: 0.96, green: 0.25, blue: 0.37, alpha: 1),
            .busy: NSColor(calibratedRed: 0.22, green: 0.74, blue: 0.97, alpha: 1),
            .done: NSColor(calibratedRed: 0.20, green: 0.83, blue: 0.60, alpha: 1),
            .error: NSColor(calibratedRed: 0.98, green: 0.45, blue: 0.09, alpha: 1),
        ]
        dot.layer?.backgroundColor = colors[look]?.cgColor
        label.stringValue = text
        let screen = NSScreen.screens.first { NSMouseInRect(NSEvent.mouseLocation, $0.frame, false) } ?? NSScreen.main
        let width = min(max(label.intrinsicContentSize.width + 58, 160), 560)
        if let f = screen?.visibleFrame {
            panel.setFrame(NSRect(x: f.midX - width / 2, y: f.minY + 28, width: width, height: 40), display: true)
        }
        panel.orderFrontRegardless()
        if let hideAfter {
            let work = DispatchWorkItem { [weak self] in self?.panel.orderOut(nil) }
            hideWork = work
            DispatchQueue.main.asyncAfter(deadline: .now() + hideAfter, execute: work)
        }
    }

    func hide() {
        hideWork?.cancel()
        panel.orderOut(nil)
    }
}
