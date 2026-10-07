import Foundation

/// Snippets: "cue => saved text", one per line. Same rules as the Chrome extension.
public struct Snippet: Equatable, Sendable {
    public let cue: String
    public let text: String

    public static func parse(_ raw: String) -> [Snippet] {
        raw.components(separatedBy: "\n").compactMap { line -> Snippet? in
            let parts = line.components(separatedBy: "=>")
            guard parts.count >= 2 else { return nil }
            let cue = parts[0].trimmingCharacters(in: .whitespaces)
            let text = parts.dropFirst().joined(separator: "=>").trimmingCharacters(in: .whitespaces)
            return cue.isEmpty || text.isEmpty ? nil : Snippet(cue: cue, text: text)
        }.prefix(20).map { $0 }
    }

    /// Replace [[SNIPPET n]] placeholders with the saved text; unknown numbers are left alone.
    public static func expand(_ text: String, _ snippets: [Snippet]) -> String {
        replace(#"\[\[SNIPPET (\d+)\]\]"#, in: text) { groups in
            guard let n = Int(groups[1]), n >= 1, n <= snippets.count else { return groups[0] }
            return snippets[n - 1].text
        }
    }
}

/// Personal dictionary -> AssemblyAI keyterms (trimmed, de-duplicated, at most 100).
public func dictionaryTerms(_ raw: String) -> [String] {
    var seen = Set<String>()
    return raw.components(separatedBy: CharacterSet(charactersIn: "\n,"))
        .map { $0.trimmingCharacters(in: .whitespaces) }
        .filter { !$0.isEmpty && $0.count <= 50 && seen.insert($0.lowercased()).inserted }
        .prefix(100).map { $0 }
}

public enum PlainText {
    /// Markdown from the polish -> plain text that reads well when typed into Mail, Slack, a form, etc.
    public static func from(markdown md: String) -> String {
        let lines = md.components(separatedBy: "\n").map { line -> String in
            var l = sub(#"^#{1,6}\s+"#, "", line)
            l = sub(#"\*\*(.+?)\*\*"#, "$1", l)
            l = sub(#"(^|[^*])\*(?!\s)(.+?)\*(?!\*)"#, "$1$2", l)
            return sub(#"^(\s*)[*•]\s+"#, "$1- ", l)
        }
        return sub(#"\n{3,}"#, "\n\n", lines.joined(separator: "\n")).trimmingCharacters(in: .whitespacesAndNewlines)
    }
}

/// Fit the text to where it's going: one line for single-line fields, a space when joining onto earlier text.
public enum Shape {
    public static func fit(_ text: String, singleLine: Bool, before: String?) -> String {
        var out = text
        if singleLine {
            let lines = text.components(separatedBy: "\n")
                .map { sub(#"^\s*[-*•]\s+"#, "", $0).trimmingCharacters(in: .whitespaces) }
                .filter { !$0.isEmpty }
            if lines.count < 2 {
                out = lines.first ?? ""
            } else {
                out = lines.map { l in
                    matches(#"[.!?:;,]$"#, l) || matches(#"(https?://|www\.)\S+$|\S+@\S+\.\S+$"#, l) ? l : l + "."
                }.joined(separator: " ")
            }
        }
        if let before, let last = before.last, !last.isWhitespace { return " " + out }
        return out
    }
}

// MARK: - regex helpers

func regex(_ pattern: String) -> NSRegularExpression {
    // Patterns are compile-time constants in this file; a bad one is a programming error.
    try! NSRegularExpression(pattern: pattern, options: [.anchorsMatchLines])
}

func sub(_ pattern: String, _ template: String, _ s: String) -> String {
    regex(pattern).stringByReplacingMatches(in: s, range: NSRange(s.startIndex..., in: s), withTemplate: template)
}

func matches(_ pattern: String, _ s: String) -> Bool {
    regex(pattern).firstMatch(in: s, range: NSRange(s.startIndex..., in: s)) != nil
}

func replace(_ pattern: String, in s: String, with transform: ([String]) -> String) -> String {
    let re = regex(pattern)
    var out = ""
    var last = s.startIndex
    for m in re.matches(in: s, range: NSRange(s.startIndex..., in: s)) {
        guard let whole = Range(m.range, in: s) else { continue }
        let groups = (0..<m.numberOfRanges).map { i in Range(m.range(at: i), in: s).map { String(s[$0]) } ?? "" }
        out += s[last..<whole.lowerBound] + transform(groups)
        last = whole.upperBound
    }
    return out + s[last...]
}
