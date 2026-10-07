import Foundation

/// What to type after a dictation, decided from the raw transcript. Same behaviour as the Chrome extension:
/// polish failure -> type the raw words with a note; a failed edit of selected text -> change nothing.
public struct Outcome: Equatable, Sendable {
    public let text: String?   // nil = type nothing
    public let note: String?   // shown to the user (why the raw words were used, or why nothing changed)
}

public enum Polish {
    public static func run(raw: String, selection: String?, style: Style, snippets: [Snippet],
                           gateway: Gateway) async -> Outcome {
        let spoken = raw.trimmingCharacters(in: .whitespacesAndNewlines)
        if spoken.isEmpty {
            return Outcome(text: nil, note: "Didn't catch any speech. Hold ⌥Space, speak, then let go.")
        }
        if let selection, !selection.isEmpty {
            do {
                let edited = try await gateway.complete(system: Prompts.command(spoken), user: String(selection.prefix(20000)))
                return Outcome(text: PlainText.from(markdown: edited), note: nil)
            } catch {
                return Outcome(text: nil, note: "Couldn't edit the selection: \(error.localizedDescription) Your text is unchanged.")
            }
        }
        do {
            let polished = try await gateway.complete(system: Prompts.system(style, snippetCues: snippets.map(\.cue)), user: spoken)
            guard !polished.isEmpty else { throw FlowError("empty reply") }
            return Outcome(text: Snippet.expand(PlainText.from(markdown: polished), snippets), note: nil)
        } catch {
            return Outcome(text: spoken, note: "AI polish failed (\(error.localizedDescription)); typed your raw words.")
        }
    }
}
