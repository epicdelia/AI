import AppKit
import Security

/// Run on the main thread in order (audio chunks must not be reordered, so no unstructured Tasks here).
func onMain(_ body: @escaping @MainActor () -> Void) {
    DispatchQueue.main.async { MainActor.assumeIsolated { body() } }
}

func onMain(after seconds: TimeInterval, _ body: @escaping @MainActor () -> Void) {
    DispatchQueue.main.asyncAfter(deadline: .now() + seconds) { MainActor.assumeIsolated { body() } }
}

/// User settings. The API key is not here: it lives in the Keychain.
enum Prefs {
    private static let d = UserDefaults.standard
    /// "app" = match the app you're typing in, else a Style raw value.
    static var style: String {
        get { d.string(forKey: "style") ?? "app" }
        set { d.set(newValue, forKey: "style") }
    }
    /// "en" or "multi" (any language, auto-detected).
    static var language: String {
        get { d.string(forKey: "language") ?? "en" }
        set { d.set(newValue, forKey: "language") }
    }
    static var dictionary: String {
        get { d.string(forKey: "dictionary") ?? "" }
        set { d.set(newValue, forKey: "dictionary") }
    }
    static var snippets: String {
        get { d.string(forKey: "snippets") ?? "" }
        set { d.set(newValue, forKey: "snippets") }
    }
    static var workingModel: String? {
        get { d.string(forKey: "workingModel") }
        set { d.set(newValue, forKey: "workingModel") }
    }
}

/// The AssemblyAI API key, stored in the login Keychain (never in a file or in UserDefaults).
enum Keychain {
    private static let base: [String: Any] = [
        kSecClass as String: kSecClassGenericPassword,
        kSecAttrService as String: "Flow",
        kSecAttrAccount as String: "assemblyai-api-key",
    ]

    static func apiKey() -> String? {
        var query = base
        query[kSecReturnData as String] = true
        query[kSecMatchLimit as String] = kSecMatchLimitOne
        var out: CFTypeRef?
        guard SecItemCopyMatching(query as CFDictionary, &out) == errSecSuccess, let data = out as? Data,
              let key = String(data: data, encoding: .utf8)?.trimmingCharacters(in: .whitespacesAndNewlines), !key.isEmpty
        else { return nil }
        return key
    }

    @discardableResult
    static func setAPIKey(_ key: String) -> Bool {
        SecItemDelete(base as CFDictionary)
        let trimmed = key.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return true }
        var item = base
        item[kSecValueData as String] = Data(trimmed.utf8)
        return SecItemAdd(item as CFDictionary, nil) == errSecSuccess
    }
}
