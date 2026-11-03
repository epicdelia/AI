package com.techunicorn.studio.data.live

/**
 * Small key/value storage for the sign-in token and server address
 * (Android: SharedPreferences, desktop: java.util.prefs).
 */
expect object TokenStore {
    fun read(key: String): String?
    fun write(key: String, value: String?)
}

/**
 * Where the Creator Studio server is by default (emulator vs desktop).
 */
expect fun defaultServerUrl(): String
