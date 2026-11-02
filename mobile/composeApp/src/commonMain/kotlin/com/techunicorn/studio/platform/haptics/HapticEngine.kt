package com.techunicorn.studio.platform.haptics

import androidx.compose.runtime.Composable

enum class HapticFeedbackStrength {
    LIGHT_TICK,
    MEDIUM_CLICK,
    HEAVY_IMPACT,
    SUCCESS_PULSE,
    ERROR_ALERT
}

interface HapticEngine {
    fun perform(strength: HapticFeedbackStrength)
    fun lightTick() = perform(HapticFeedbackStrength.LIGHT_TICK)
    fun click() = perform(HapticFeedbackStrength.MEDIUM_CLICK)
    fun heavy() = perform(HapticFeedbackStrength.HEAVY_IMPACT)
    fun success() = perform(HapticFeedbackStrength.SUCCESS_PULSE)
    fun error() = perform(HapticFeedbackStrength.ERROR_ALERT)
}

class NoOpHapticEngine : HapticEngine {
    override fun perform(strength: HapticFeedbackStrength) {
        // Desktop / No-op fallback
    }
}

expect fun getPlatformHapticEngine(): HapticEngine

@Composable
expect fun rememberPlatformHapticEngine(): HapticEngine

