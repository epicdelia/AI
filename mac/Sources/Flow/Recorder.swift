import AVFoundation
import FlowCore

/// Microphone -> 16 kHz mono 16-bit PCM in 100 ms chunks (AssemblyAI wants 50-1000 ms per message).
final class Recorder {
    var onChunk: ((Data) -> Void)?
    private var engine: AVAudioEngine?
    private let queue = DispatchQueue(label: "flow.recorder")
    private var pending = Data()
    private static let chunkBytes = 3200   // 100 ms
    private static let minBytes = 1600     // 50 ms

    static var permission: AVAuthorizationStatus { AVCaptureDevice.authorizationStatus(for: .audio) }

    static func requestPermission() async -> Bool {
        if permission == .authorized { return true }
        return await AVCaptureDevice.requestAccess(for: .audio)
    }

    func start() throws {
        stop(flush: false)
        let engine = AVAudioEngine()  // a fresh engine picks up the current default mic (e.g. AirPods)
        let input = engine.inputNode
        let inFormat = input.outputFormat(forBus: 0)
        guard inFormat.sampleRate > 0, inFormat.channelCount > 0 else { throw FlowError("No microphone found.") }
        guard let outFormat = AVAudioFormat(commonFormat: .pcmFormatInt16, sampleRate: 16000, channels: 1, interleaved: true),
              let converter = AVAudioConverter(from: inFormat, to: outFormat) else {
            throw FlowError("This microphone's audio format isn't supported.")
        }
        queue.sync { pending = Data() }
        input.installTap(onBus: 0, bufferSize: 4096, format: inFormat) { [weak self] buffer, _ in
            guard let self else { return }
            let capacity = AVAudioFrameCount(Double(buffer.frameLength) * 16000 / inFormat.sampleRate) + 32
            guard let out = AVAudioPCMBuffer(pcmFormat: outFormat, frameCapacity: capacity) else { return }
            var fed = false
            var error: NSError?
            converter.convert(to: out, error: &error) { _, status in
                if fed { status.pointee = .noDataNow; return nil }
                fed = true
                status.pointee = .haveData
                return buffer
            }
            guard error == nil, out.frameLength > 0, let samples = out.int16ChannelData else { return }
            let data = Data(bytes: samples[0], count: Int(out.frameLength) * 2)
            self.queue.async { self.append(data) }
        }
        engine.prepare()
        do { try engine.start() } catch {
            input.removeTap(onBus: 0)
            throw FlowError("Couldn't start the microphone: \(error.localizedDescription)")
        }
        self.engine = engine
    }

    private func append(_ data: Data) {
        pending.append(data)
        while pending.count >= Recorder.chunkBytes {
            emit(pending.prefix(Recorder.chunkBytes))
            pending.removeFirst(Recorder.chunkBytes)
        }
    }

    private func emit(_ chunk: Data) {
        guard let onChunk else { return }
        let copy = Data(chunk)
        DispatchQueue.main.async { onChunk(copy) }
    }

    /// Stops the mic; with flush, sends what's left (padded with silence to the 50 ms minimum).
    func stop(flush: Bool = true) {
        guard let engine else { return }
        engine.inputNode.removeTap(onBus: 0)
        engine.stop()
        self.engine = nil
        queue.sync {
            if flush && !pending.isEmpty {
                if pending.count < Recorder.minBytes { pending.append(Data(count: Recorder.minBytes - pending.count)) }
                emit(pending)
            }
            pending = Data()
        }
    }
}
