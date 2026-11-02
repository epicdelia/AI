package com.techunicorn.studio.platform.haptics

import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember

private val desktopEngine = object : HapticEngine {
    override fun perform(strength: HapticFeedbackStrength) {
        // Desktop environment: no physical vibration motor, silently ignore
    }
}

actual fun getPlatformHapticEngine(): HapticEngine = desktopEngine

@Composable
actual fun rememberPlatformHapticEngine(): HapticEngine = remember { desktopEngine }

