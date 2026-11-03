package com.techunicorn.studio.data.live

import java.util.prefs.Preferences

actual object TokenStore {
    private val prefs: Preferences = Preferences.userRoot().node("com/techunicorn/studio/session")

    actual fun read(key: String): String? = prefs.get(key, null)

    actual fun write(key: String, value: String?) {
        if (value == null) prefs.remove(key) else prefs.put(key, value)
        prefs.flush()
    }
}

actual fun defaultServerUrl(): String = "http://localhost:8000"
