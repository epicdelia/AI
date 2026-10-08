import XCTest
@testable import FlowCore

/// A fake HTTP server: answers requests in order and records them.
final class FakeHTTP: @unchecked Sendable {
    private let lock = NSLock()
    private var replies: [(Int, String)]
    private(set) var requests: [URLRequest] = []

    init(_ replies: [(Int, String)]) { self.replies = replies }

    var transport: Transport {
        { [self] request in respond(to: request) }
    }

    private func respond(to request: URLRequest) -> (Data, HTTPURLResponse) {
        lock.lock(); defer { lock.unlock() }
        requests.append(request)
        let (status, body) = replies.isEmpty ? (500, "no more replies") : replies.removeFirst()
        return (Data(body.utf8), HTTPURLResponse(url: request.url!, statusCode: status, httpVersion: nil, headerFields: nil)!)
    }

    func body(_ i: Int) -> [String: Any] {
        (try? JSONSerialization.jsonObject(with: requests[i].httpBody ?? Data()) as? [String: Any]) ?? [:]
    }
}

func completion(_ text: String) -> String {
    let data = try! JSONSerialization.data(withJSONObject: ["choices": [["message": ["content": text]]]])
    return String(decoding: data, as: UTF8.self)
}

final class PromptTests: XCTestCase {
    func testPromptsMatchServer() {
        XCTAssertEqual(Prompts.system(.auto), PromptFixtures.auto)
        XCTAssertEqual(Prompts.system(.message, snippetCues: PromptFixtures.snippetCues), PromptFixtures.messageWithSnippets)
        XCTAssertEqual(Prompts.command(PromptFixtures.instruction), PromptFixtures.command)
        XCTAssertEqual(Prompts.system(.email, snippetCues: ["", "  "]), GeneratedPrompts.system["email"])
    }

    func testStyleFollowsTheApp() {
        XCTAssertEqual(AppStyle.style(setting: "app", bundleID: "com.apple.mail"), .email)
        XCTAssertEqual(AppStyle.style(setting: "app", bundleID: "com.tinyspeck.slackmacgap"), .message)
        XCTAssertEqual(AppStyle.style(setting: "app", bundleID: "notion.id"), .notes)
        XCTAssertEqual(AppStyle.style(setting: "app", bundleID: "com.unknown.app"), .message)
        XCTAssertEqual(AppStyle.style(setting: "app", bundleID: nil), .message)
        XCTAssertEqual(AppStyle.style(setting: "notes", bundleID: "com.apple.mail"), .notes)
    }
}

final class TextTests: XCTestCase {
    func testMarkdownBecomesPlainText() {
        XCTAssertEqual(PlainText.from(markdown: "**Launch update**\n\n- Launch moves to **Friday**\n- QA signs off Thursday"),
                       "Launch update\n\n- Launch moves to Friday\n- QA signs off Thursday")
        XCTAssertEqual(PlainText.from(markdown: "## Plan\n* one\n* *two*\n\n\n\nend"), "Plan\n- one\n- two\n\nend")
        XCTAssertEqual(PlainText.from(markdown: "2 * 3 = 6"), "2 * 3 = 6")
    }

    func testSingleLineFieldsGetOneTidyLine() {
        let note = "Launch update\n\n- Launch moves to Friday\n- QA signs off Thursday"
        XCTAssertEqual(Shape.fit(note, singleLine: true, before: ""), "Launch update. Launch moves to Friday. QA signs off Thursday.")
        XCTAssertEqual(Shape.fit("Book here\nhttps://cal.com/x", singleLine: true, before: nil), "Book here. https://cal.com/x")
        XCTAssertEqual(Shape.fit("Hello there", singleLine: false, before: "Hi team,"), " Hello there")
        XCTAssertEqual(Shape.fit("Hello there", singleLine: false, before: "Hi team, "), "Hello there")
        XCTAssertEqual(Shape.fit("a\nb", singleLine: false, before: nil), "a\nb")
    }

    func testSnippets() {
        let s = Snippet.parse("my calendly link => https://cal.com/d?x=1&y=2\nbad line\n => no cue\nsig => a => b")
        XCTAssertEqual(s, [Snippet(cue: "my calendly link", text: "https://cal.com/d?x=1&y=2"), Snippet(cue: "sig", text: "a => b")])
        XCTAssertEqual(Snippet.expand("Book: [[SNIPPET 1]] / [[SNIPPET 9]] / [[SNIPPET 2]]", s),
                       "Book: https://cal.com/d?x=1&y=2 / [[SNIPPET 9]] / a => b")
    }

    func testDictionaryTerms() {
        XCTAssertEqual(dictionaryTerms("AssemblyAI\nassemblyai, Siobhan\n\n" + String(repeating: "x", count: 51)), ["AssemblyAI", "Siobhan"])
    }
}

final class StreamingTests: XCTestCase {
    func testTurnsBuildTheTranscript() {
        var t = TurnState()
        XCTAssertEqual(t.handle(Data(#"{"type":"Begin","id":"s"}"#.utf8)), .begin)
        XCTAssertEqual(t.handle(Data(#"{"type":"Turn","turn_order":1,"end_of_turn":false,"transcript":"second"}"#.utf8)),
                       .turn(endOfTurn: false))
        XCTAssertTrue(t.openTurn)
        _ = t.handle(Data(#"{"type":"Turn","turn_order":0,"end_of_turn":true,"transcript":"first"}"#.utf8))
        XCTAssertEqual(t.text, "first second")
        XCTAssertEqual(t.handle(Data(#"{"type":"Error","error":"bad audio"}"#.utf8)), .error("bad audio"))
        XCTAssertEqual(t.handle(Data("not json".utf8)), .other)
    }

    func testSocketURL() {
        let url = AssemblyAI.socketURL(token: "tok+1", terms: ["AssemblyAI", "C++"], multilingual: true).absoluteString
        XCTAssertTrue(url.hasPrefix("wss://streaming.assemblyai.com/v3/ws?sample_rate=16000&encoding=pcm_s16le&format_turns=false"))
        XCTAssertTrue(url.contains("token=tok%2B1"))
        XCTAssertTrue(url.contains("speech_model=universal-streaming-multilingual&language_detection=true"))
        let terms = URLComponents(string: url)!.queryItems!.first { $0.name == "keyterms_prompt" }!.value!
        XCTAssertEqual(try JSONSerialization.jsonObject(with: Data(terms.utf8)) as? [String], ["AssemblyAI", "C++"])
        XCTAssertFalse(AssemblyAI.socketURL(token: "t", terms: [], multilingual: false).absoluteString.contains("keyterms"))
    }

    func testTokenRequest() async throws {
        let http = FakeHTTP([(200, #"{"token":"abc"}"#), (401, "nope")])
        let token = try await AssemblyAI.streamingToken(key: "k", transport: http.transport)
        XCTAssertEqual(token, "abc")
        XCTAssertEqual(http.requests[0].value(forHTTPHeaderField: "authorization"), "k")
        XCTAssertTrue(http.requests[0].url!.absoluteString.hasSuffix("/v3/token?expires_in_seconds=60"))
        do {
            _ = try await AssemblyAI.streamingToken(key: "k", transport: http.transport)
            XCTFail("expected an error")
        } catch {
            XCTAssertTrue(error.localizedDescription.contains("rejected your API key"))
        }
    }
}

final class PolishTests: XCTestCase {
    func testFallsBackToAModelTheAccountCanUse() async throws {
        let http = FakeHTTP([
            (400, #"{"error":"Your account does not have access to this LLM Gateway model"}"#),
            (200, #"{"data":[{"id":"big-model"},{"id":"gpt-5-mini"},{"id":"claude-haiku"},{"id":"gemini-flash"}]}"#),
            (200, completion("  **Hi** there  ")),
            (200, completion("again")),
        ])
        let gateway = Gateway(key: "k", transport: http.transport)
        let text = try await gateway.complete(system: "S", user: "U")
        XCTAssertEqual(text, "**Hi** there")
        XCTAssertEqual(gateway.workingModel, "gemini-flash")  // "flash" ranks before "haiku"; gpt-5-mini was already tried
        XCTAssertEqual(http.body(0)["model"] as? String, "gpt-5-mini")
        XCTAssertEqual(http.body(2)["model"] as? String, "gemini-flash")
        XCTAssertEqual(http.body(2)["stream"] as? Bool, false)
        _ = try await gateway.complete(system: "S", user: "U2")
        XCTAssertEqual(http.body(3)["model"] as? String, "gemini-flash")  // remembered, no second fallback
    }

    func testNoModelWorksGivesAPlainEnglishError() async {
        let deny = (400, "no access")
        let http = FakeHTTP([deny, (200, #"{"data":[{"id":"a"}]}"#), deny])
        do {
            _ = try await Gateway(key: "k", transport: http.transport).complete(system: "S", user: "U")
            XCTFail("expected an error")
        } catch {
            XCTAssertTrue(error.localizedDescription.contains("can't use any AI model we tried (a, gpt-5-mini)"))
        }
    }

    func testPolishExpandsSnippetsAndStripsMarkdown() async {
        let http = FakeHTTP([(200, completion("**Book** a time: [[SNIPPET 1]]"))])
        let outcome = await Polish.run(raw: "book a time my link", selection: nil, style: .message,
                                       snippets: [Snippet(cue: "my link", text: "https://cal.com/x")],
                                       gateway: Gateway(key: "k", transport: http.transport))
        XCTAssertEqual(outcome, Outcome(text: "Book a time: https://cal.com/x", note: nil))
        let system = (http.body(0)["messages"] as? [[String: String]])?.first?["content"] ?? ""
        XCTAssertTrue(system.contains(#"1. "my link""#))
    }

    func testPolishFailureTypesTheRawWords() async {
        let http = FakeHTTP([(500, "overloaded")])
        let outcome = await Polish.run(raw: " um hello ", selection: nil, style: .auto, snippets: [],
                                       gateway: Gateway(key: "k", transport: http.transport))
        XCTAssertEqual(outcome.text, "um hello")
        XCTAssertTrue(outcome.note?.contains("AI polish failed") ?? false)
    }

    func testCommandModeEditsOrLeavesTextAlone() async {
        let ok = FakeHTTP([(200, completion("Send it Friday."))])
        let edited = await Polish.run(raw: "make it friday", selection: "Send it Monday.", style: .auto, snippets: [],
                                      gateway: Gateway(key: "k", transport: ok.transport))
        XCTAssertEqual(edited.text, "Send it Friday.")
        let messages = ok.body(0)["messages"] as? [[String: String]]
        XCTAssertTrue(messages?[0]["content"]?.contains("Instruction: make it friday") ?? false)
        XCTAssertEqual(messages?[1]["content"], "Send it Monday.")

        let failing = FakeHTTP([(500, "down")])
        let unchanged = await Polish.run(raw: "make it friday", selection: "Send it Monday.", style: .auto, snippets: [],
                                         gateway: Gateway(key: "k", transport: failing.transport))
        XCTAssertNil(unchanged.text)
        XCTAssertTrue(unchanged.note?.contains("Your text is unchanged") ?? false)
    }

    func testSilenceTypesNothing() async {
        let outcome = await Polish.run(raw: "  ", selection: nil, style: .auto, snippets: [],
                                       gateway: Gateway(key: "k", transport: FakeHTTP([]).transport))
        XCTAssertNil(outcome.text)
    }
}
