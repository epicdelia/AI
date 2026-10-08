import AppKit
import FlowCore

/// Hold ⌥Space = push-to-talk. A quick tap (< 0.3 s) = hands-free; the next tap stops.
/// Flow: mic -> AssemblyAI live transcript -> LLM Gateway polish -> typed into the focused app.
@MainActor
final class Dictation {
    enum Phase { case idle, recording, finishing }

    let pill = Pill()
    private(set) var phase = Phase.idle
    private(set) var lastResult: String?
    var onPhase: ((Phase) -> Void)?
    private var handsFree = false
    private var pressedAt = Date()
    private var recorder: Recorder?
    private var streamer: Streamer?
    private var context = FocusContext()
    private var apiKey = ""
    private var session = 0  // ignores late callbacks from an earlier dictation

    func keyDown() {
        if phase == .recording && handsFree { handsFree = false; stop(); return }
        guard phase == .idle else { return }
        pressedAt = Date()
        start()
    }

    func keyUp() {
        guard phase == .recording, !handsFree else { return }
        if Date().timeIntervalSince(pressedAt) < 0.3 {
            handsFree = true
            pill.show(.listening, "Hands-free: speak, then tap ⌥Space again to finish")
            return
        }
        stop()
    }

    private func setPhase(_ p: Phase) {
        phase = p
        onPhase?(p)
    }

    private func start() {
        guard let key = Keychain.apiKey() else {
            return fail("Add your AssemblyAI API key: click the Flow mic icon in the menu bar → Settings.")
        }
        guard Recorder.permission == .authorized else {
            Task { _ = await Recorder.requestPermission() }
            return fail("Flow needs microphone access: allow it, then try again.")
        }
        session += 1
        let id = session
        apiKey = key
        context = FocusContext.capture()
        handsFree = false
        setPhase(.recording)
        pill.show(.listening, context.selection != nil ? "Say how to change the selected text…" : "Listening…")

        let streamer = Streamer()
        streamer.onPartial = { [weak self] text in
            guard let self, self.session == id, self.phase == .recording, !text.isEmpty else { return }
            self.pill.show(.listening, text)
        }
        streamer.onError = { [weak self] message in
            guard let self, self.session == id else { return }
            self.abort(message)
        }
        let recorder = Recorder()
        recorder.onChunk = { [weak streamer] chunk in streamer?.send(chunk) }
        self.streamer = streamer
        self.recorder = recorder
        do { try recorder.start() } catch { return abort(error.localizedDescription) }

        let terms = dictionaryTerms(Prefs.dictionary)
        let multilingual = Prefs.language == "multi"
        Task { [weak self] in
            do {
                let token = try await AssemblyAI.streamingToken(key: key)
                guard let self, self.session == id else { return }
                streamer.connect(url: AssemblyAI.socketURL(token: token, terms: terms, multilingual: multilingual))
            } catch {
                guard let self, self.session == id else { return }
                self.abort(error.localizedDescription)
            }
        }
    }

    private func stop() {
        guard phase == .recording, let streamer else { return }
        let id = session
        let key = apiKey
        setPhase(.finishing)
        pill.show(.busy, "Finishing…")
        recorder?.stop()   // queues the last audio on the main queue…
        recorder = nil
        onMain {  // …so this runs after it has been sent
            Task { await self.finish(streamer: streamer, key: key, id: id) }
        }
    }

    private func finish(streamer: Streamer, key: String, id: Int) async {
        guard let raw = await streamer.finish() else {
            if session == id { abort("Couldn't connect to AssemblyAI. Check your internet and API key.") }
            return
        }
        guard session == id else { return }
        let selection = context.selection
        pill.show(.busy, selection != nil ? "Editing your selection…" : "Polishing…")
        let gateway = Gateway(key: key, workingModel: Prefs.workingModel)
        let snippets = Snippet.parse(Prefs.snippets)
        let style = AppStyle.style(setting: Prefs.style, bundleID: context.bundleID)
        let outcome = await Polish.run(raw: raw, selection: selection, style: style, snippets: snippets, gateway: gateway)
        guard session == id else { return }
        if let model = gateway.workingModel { Prefs.workingModel = model }
        self.streamer = nil
        setPhase(.idle)
        guard let text = outcome.text else { return fail(outcome.note ?? "Nothing to type.") }
        let shaped = selection != nil ? text : Shape.fit(text, singleLine: context.singleLine, before: context.before)
        lastResult = shaped
        if Inserter.type(shaped) {
            if let note = outcome.note { pill.show(.error, note, hideAfter: 6) } else { pill.show(.done, "Typed ✓", hideAfter: 1.2) }
        } else {
            pill.show(.error, "Copied. Press ⌘V to paste (allow Flow in Accessibility settings to type for you).", hideAfter: 6)
        }
    }

    /// Paste the last result again (menu item), e.g. after clicking into the right box.
    func pasteLast() {
        guard let lastResult else { return }
        if !Inserter.type(lastResult) { pill.show(.done, "Copied. Press ⌘V to paste.", hideAfter: 4) }
    }

    private func abort(_ message: String) {
        session += 1
        recorder?.stop(flush: false)
        recorder = nil
        streamer?.cancel()
        streamer = nil
        handsFree = false
        setPhase(.idle)
        fail(message)
    }

    private func fail(_ message: String) {
        pill.show(.error, message, hideAfter: 6)
    }
}
