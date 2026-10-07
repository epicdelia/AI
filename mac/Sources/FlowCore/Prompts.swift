import Foundation

/// How the polished text is formatted. Mirrors PolishStyle in app.py.
public enum Style: String, CaseIterable, Sendable {
    case auto, message, email, notes
}

/// The same prompts the Flow server uses (generated from app.py into GeneratedPrompts.swift).
public enum Prompts {
    public static func system(_ style: Style, snippetCues: [String] = []) -> String {
        let base = GeneratedPrompts.system[style.rawValue] ?? GeneratedPrompts.system["auto"]!
        let rules = snippetRules(snippetCues)
        guard !rules.isEmpty, let marker = base.range(of: "\nReturn only") else { return base }
        return base.replacingCharacters(in: marker, with: rules + "\nReturn only")
    }

    /// Saved snippets: the model writes [[SNIPPET n]] where a cue was said; the app swaps in the exact text.
    public static func snippetRules(_ cues: [String]) -> String {
        let cleaned = cues.map { $0.trimmingCharacters(in: .whitespacesAndNewlines) }
            .filter { !$0.isEmpty }
            .map { String($0.prefix(80)) }
        guard !cleaned.isEmpty else { return "" }
        let listed = cleaned.enumerated().map { "  \($0.offset + 1). \"\($0.element)\"" }.joined(separator: "\n")
        return GeneratedPrompts.snippetHeader + listed
    }

    /// Command mode: the user selected text and spoke an instruction for it.
    public static func command(_ instruction: String) -> String {
        GeneratedPrompts.commandTemplate.replacingOccurrences(
            of: "{{INSTRUCTION}}", with: instruction.trimmingCharacters(in: .whitespacesAndNewlines))
    }
}

/// Picks a style from the app you're typing into, like the extension's "Match the site".
public enum AppStyle {
    static let byBundleID: [String: Style] = [
        "com.apple.mail": .email, "com.microsoft.Outlook": .email, "com.superhuman.electron": .email,
        "com.readdle.smartemail-Mac": .email,
        "com.tinyspeck.slackmacgap": .message, "com.hnc.Discord": .message, "com.apple.MobileSMS": .message,
        "net.whatsapp.WhatsApp": .message, "WhatsApp": .message, "com.microsoft.teams2": .message,
        "com.microsoft.teams": .message, "ru.keepcoder.Telegram": .message,
        "notion.id": .notes, "com.apple.Notes": .notes, "md.obsidian": .notes,
    ]

    /// `setting` is "app" (match the app) or a Style raw value.
    public static func style(setting: String, bundleID: String?) -> Style {
        if let fixed = Style(rawValue: setting) { return fixed }
        return bundleID.flatMap { byBundleID[$0] } ?? .message
    }
}
