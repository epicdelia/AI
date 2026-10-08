import Foundation

public struct FlowError: Error, LocalizedError, Equatable {
    public let message: String
    public init(_ message: String) { self.message = message }
    public var errorDescription: String? { message }
}

/// Sends one HTTP request. Tests swap in a fake; the app uses URLSession.
public typealias Transport = @Sendable (URLRequest) async throws -> (Data, HTTPURLResponse)

public let urlSessionTransport: Transport = { request in
    let (data, response) = try await URLSession.shared.data(for: request)
    guard let http = response as? HTTPURLResponse else { throw FlowError("No HTTP response from \(request.url?.host ?? "server").") }
    return (data, http)
}

public enum AssemblyAI {
    public static let tokenURL = URL(string: "https://streaming.assemblyai.com/v3/token")!
    public static let streamURL = URL(string: "wss://streaming.assemblyai.com/v3/ws")!

    /// A short-lived token for one live-transcription session.
    public static func streamingToken(key: String, transport: Transport = urlSessionTransport) async throws -> String {
        var url = URLComponents(url: tokenURL, resolvingAgainstBaseURL: false)!
        url.queryItems = [URLQueryItem(name: "expires_in_seconds", value: "60")]
        var req = URLRequest(url: url.url!)
        req.setValue(key, forHTTPHeaderField: "authorization")
        req.timeoutInterval = 15
        let (data, resp) = try await transport(req)
        guard resp.statusCode == 200 else {
            if resp.statusCode == 401 { throw FlowError("AssemblyAI rejected your API key. Check it in Flow → Settings.") }
            throw FlowError("Couldn't start transcription (\(resp.statusCode)): \(snippet(data))")
        }
        guard let obj = try? JSONSerialization.jsonObject(with: data) as? [String: Any], let token = obj["token"] as? String else {
            throw FlowError("Unexpected token response from AssemblyAI: \(snippet(data))")
        }
        return token
    }

    /// The live socket URL: 16 kHz 16-bit PCM, unformatted turns (the polish step formats).
    public static func socketURL(token: String, terms: [String], multilingual: Bool, base: URL = streamURL) -> URL {
        var url = URLComponents(url: base, resolvingAgainstBaseURL: false)!
        var items = [
            URLQueryItem(name: "sample_rate", value: "16000"),
            URLQueryItem(name: "encoding", value: "pcm_s16le"),
            URLQueryItem(name: "format_turns", value: "false"),
            URLQueryItem(name: "token", value: token),
        ]
        if multilingual {
            items += [URLQueryItem(name: "speech_model", value: "universal-streaming-multilingual"),
                      URLQueryItem(name: "language_detection", value: "true")]
        }
        if !terms.isEmpty, let json = try? JSONSerialization.data(withJSONObject: terms), let s = String(data: json, encoding: .utf8) {
            items.append(URLQueryItem(name: "keyterms_prompt", value: s))
        }
        url.queryItems = items
        // URLComponents leaves "+" alone, which servers read as a space.
        url.percentEncodedQuery = url.percentEncodedQuery?.replacingOccurrences(of: "+", with: "%2B")
        return url.url!
    }
}

/// Tracks the live transcript from the socket's messages, keyed by turn order like the web app.
public struct TurnState: Sendable {
    public enum Event: Equatable, Sendable {
        case begin, turn(endOfTurn: Bool), termination, error(String), other
    }

    public private(set) var turns: [Int: String] = [:]
    public private(set) var openTurn = false

    public init() {}

    public mutating func handle(_ data: Data) -> Event {
        guard let m = try? JSONSerialization.jsonObject(with: data) as? [String: Any], let type = m["type"] as? String else {
            return .other
        }
        switch type {
        case "Begin": return .begin
        case "Turn":
            let order = (m["turn_order"] as? NSNumber)?.intValue ?? 0
            let end = (m["end_of_turn"] as? Bool) ?? false
            turns[order] = (m["transcript"] as? String) ?? ""
            openTurn = !end
            return .turn(endOfTurn: end)
        case "Termination": return .termination
        case "Error": return .error((m["error"] as? String) ?? "unknown error")
        default: return .other
        }
    }

    public var text: String {
        turns.keys.sorted().compactMap { turns[$0] }.joined(separator: " ").trimmingCharacters(in: .whitespacesAndNewlines)
    }
}

/// AssemblyAI's LLM Gateway (OpenAI-shaped). Falls back to another model if the account can't use the default.
public final class Gateway: @unchecked Sendable {
    public static let chatURL = URL(string: "https://llm-gateway.assemblyai.com/v1/chat/completions")!
    public static let modelsURL = URL(string: "https://llm-gateway.assemblyai.com/v1/models")!

    let key: String
    let transport: Transport
    /// The model that last worked; the app saves it so later requests skip the fallback.
    public private(set) var workingModel: String?

    public init(key: String, workingModel: String? = nil, transport: @escaping Transport = urlSessionTransport) {
        self.key = key
        self.workingModel = workingModel
        self.transport = transport
    }

    public func complete(system: String, user: String) async throws -> String {
        let first = workingModel ?? GeneratedPrompts.defaultModel
        var (data, resp) = try await call(model: first, system: system, user: user)
        var model = first
        if noAccess(data, resp) {
            var tried = [first]
            for candidate in try await candidates(excluding: Set(tried)) {
                tried.append(candidate)
                (data, resp) = try await call(model: candidate, system: system, user: user)
                model = candidate
                if !noAccess(data, resp) { break }
            }
            if noAccess(data, resp) {
                throw FlowError("Your AssemblyAI account can't use any AI model we tried (\(tried.sorted().joined(separator: ", "))). "
                                + "The LLM Gateway may need billing enabled at assemblyai.com/dashboard.")
            }
        }
        guard resp.statusCode == 200 else { throw FlowError("AI polish failed (\(resp.statusCode)): \(snippet(data))") }
        guard let obj = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
              let choices = obj["choices"] as? [[String: Any]],
              let message = choices.first?["message"] as? [String: Any],
              let content = message["content"] as? String else {
            throw FlowError("Unexpected AI reply: \(snippet(data))")
        }
        workingModel = model
        return content.trimmingCharacters(in: .whitespacesAndNewlines)
    }

    func call(model: String, system: String, user: String) async throws -> (Data, HTTPURLResponse) {
        var req = URLRequest(url: Gateway.chatURL)
        req.httpMethod = "POST"
        req.setValue(key, forHTTPHeaderField: "authorization")
        req.setValue("application/json", forHTTPHeaderField: "Content-Type")
        req.timeoutInterval = 30
        req.httpBody = try JSONSerialization.data(withJSONObject: [
            "model": model, "stream": false,
            "messages": [["role": "system", "content": system], ["role": "user", "content": user]],
        ] as [String: Any])
        return try await transport(req)
    }

    func noAccess(_ data: Data, _ resp: HTTPURLResponse) -> Bool {
        [400, 403].contains(resp.statusCode) && String(decoding: data, as: UTF8.self).lowercased().contains("access")
    }

    /// Models the gateway lists, fastest-sounding first.
    func candidates(excluding tried: Set<String>) async throws -> [String] {
        var req = URLRequest(url: Gateway.modelsURL)
        req.setValue(key, forHTTPHeaderField: "authorization")
        req.timeoutInterval = 15
        let (data, resp) = try await transport(req)
        guard resp.statusCode == 200,
              let obj = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
              let list = obj["data"] as? [[String: Any]] else { return [] }
        let ids = list.compactMap { $0["id"] as? String }.filter { !tried.contains($0) }
        let rank = { (id: String) in GeneratedPrompts.fastHints.firstIndex { id.lowercased().contains($0) } ?? GeneratedPrompts.fastHints.count }
        return Array(ids.enumerated().sorted { (rank($0.element), $0.offset) < (rank($1.element), $1.offset) }
            .map(\.element).prefix(GeneratedPrompts.maxModelTries))
    }
}

func snippet(_ data: Data) -> String {
    String(String(decoding: data, as: UTF8.self).prefix(300))
}
