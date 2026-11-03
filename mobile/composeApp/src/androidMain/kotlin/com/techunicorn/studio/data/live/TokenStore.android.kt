package com.techunicorn.studio.data.live

import android.content.Context
import android.content.SharedPreferences

actual object TokenStore {
    private var prefs: SharedPreferences? = null

    /** Called once from MainActivity. */
    fun init(context: Context) {
        prefs = context.applicationContext.getSharedPreferences("creator_studio_session", Context.MODE_PRIVATE)
    }

    actual fun read(key: String): String? = prefs?.getString(key, null)

    actual fun write(key: String, value: String?) {
        prefs?.edit()?.apply {
            if (value == null) remove(key) else putString(key, value)
            apply()
        }
    }
}

// The Android emulator reaches the host machine at 10.0.2.2.
actual fun defaultServerUrl(): String = "http://10.0.2.2:8000"
