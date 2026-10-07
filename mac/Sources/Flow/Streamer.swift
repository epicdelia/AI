import FlowCore
import Foundation

/// One live-transcription session on AssemblyAI's socket. Audio sent before the socket is ready is
/// buffered, so you can start talking the moment you press the keys.
@MainActor
final class Streamer {
    var onPartial: ((String) -> Void)?
    var onError: ((String) -> Void)?
    private var task: URLSessionWebSocketTask?
    private var state = TurnState()
    private var ready = false
    private var closed = false
    private var pending: [Data] = []
    private var finalWaiter: CheckedContinuation<Void, Never>?

    func connect(url: URL) {
        let task = URLSession.shared.webSocketTask(with: url)
        self.task = task
        task.resume()
        receive(task)
    }

    func send(_ chunk: Data) {
        guard !closed else { return }
        if ready, let task { task.send(.data(chunk)) { _ in } } else { pending.append(chunk) }
    }

    private func sendJSON(_ type: String) {
        task?.send(.string("{\"type\":\"\(type)\"}")) { _ in }
    }

    private func receive(_ task: URLSessionWebSocketTask) {
        task.receive { [weak self] result in
            onMain {
                guard let self, self.task === task else { return }
                switch result {
                case .success(let message):
                    switch message {
                    case .string(let s): self.handle(Data(s.utf8))
                    case .data(let d): self.handle(d)
                    @unknown default: break
                    }
                    self.receive(task)
                case .failure(let error):
                    if !self.ready && !self.closed { self.onError?("Couldn't connect to AssemblyAI: \(error.localizedDescription)") }
                    self.closed = true
                    self.resumeFinal()
                }
            }
        }
    }

    private func handle(_ data: Data) {
        switch state.handle(data) {
        case .begin where !ready:
            ready = true
            pending.forEach { task?.send(.data($0)) { _ in } }
            pending = []
        case .turn(let end):
            onPartial?(state.text)
            if end { resumeFinal() }
        case .error(let message):
            onError?("AssemblyAI: \(message)")
        default: break
        }
    }

    private func resumeFinal() {
        finalWaiter?.resume()
        finalWaiter = nil
    }

    /// Ends the session and returns the transcript, or nil if the socket never connected.
    func finish() async -> String? {
        var waited = 0
        while !ready && !closed && waited < 40 {  // released very quickly: give the socket up to 4 s
            try? await Task.sleep(nanoseconds: 100_000_000)
            waited += 1
        }
        guard ready else { cancel(); return nil }
        if closed { return state.text }
        let wait = state.openTurn ? 2.5 : 0.8
        sendJSON("ForceEndpoint")
        await withCheckedContinuation { (c: CheckedContinuation<Void, Never>) in
            finalWaiter = c
            onMain(after: wait) { [weak self] in self?.resumeFinal() }
        }
        sendJSON("Terminate")
        let text = state.text
        let task = self.task
        onMain(after: 0.5) { task?.cancel(with: .normalClosure, reason: nil) }
        closed = true
        return text
    }

    func cancel() {
        closed = true
        task?.cancel(with: .normalClosure, reason: nil)
        resumeFinal()
    }
}
