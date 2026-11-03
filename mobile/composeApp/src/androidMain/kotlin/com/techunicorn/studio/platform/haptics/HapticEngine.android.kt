package com.techunicorn.studio.platform.haptics

import android.content.Context
import android.os.Build
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.platform.LocalContext

class AndroidHapticEngine(private val context: Context) : HapticEngine {
    private val vibrator: Vibrator? by lazy {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            val manager = context.getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as? VibratorManager
            manager?.defaultVibrator
        } else {
            @Suppress("DEPRECATION")
            context.getSystemService(Context.VIBRATOR_SERVICE) as? Vibrator
        }
    }

    override fun perform(strength: HapticFeedbackStrength) {
        val vib = vibrator ?: return
        if (!vib.hasVibrator()) return

        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                val effect = when (strength) {
                    HapticFeedbackStrength.LIGHT_TICK -> VibrationEffect.createPredefined(VibrationEffect.EFFECT_TICK)
                    HapticFeedbackStrength.MEDIUM_CLICK -> VibrationEffect.createPredefined(VibrationEffect.EFFECT_CLICK)
                    HapticFeedbackStrength.HEAVY_IMPACT -> VibrationEffect.createPredefined(VibrationEffect.EFFECT_HEAVY_CLICK)
                    HapticFeedbackStrength.SUCCESS_PULSE -> VibrationEffect.createWaveform(
                        longArrayOf(0, 40, 60, 80),
                        intArrayOf(0, 150, 0, 255),
                        -1
                    )
                    HapticFeedbackStrength.ERROR_ALERT -> VibrationEffect.createWaveform(
                        longArrayOf(0, 50, 50, 50, 50, 80),
                        intArrayOf(0, 200, 0, 200, 0, 255),
                        -1
                    )
                }
                vib.vibrate(effect)
            } else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                val millis = when (strength) {
                    HapticFeedbackStrength.LIGHT_TICK -> 15L
                    HapticFeedbackStrength.MEDIUM_CLICK -> 30L
                    HapticFeedbackStrength.HEAVY_IMPACT -> 70L
                    HapticFeedbackStrength.SUCCESS_PULSE -> 90L
                    HapticFeedbackStrength.ERROR_ALERT -> 150L
                }
                vib.vibrate(VibrationEffect.createOneShot(millis, VibrationEffect.DEFAULT_AMPLITUDE))
            } else {
                @Suppress("DEPRECATION")
                vib.vibrate(35L)
            }
        } catch (_: Exception) {
            // Graceful fallback if permission or hardware fails
        }
    }
}

// Global reference holder for non-composable invocations
private var appContext: Context? = null

fun initAndroidHaptics(context: Context) {
    appContext = context.applicationContext
}

actual fun getPlatformHapticEngine(): HapticEngine {
    val ctx = appContext
    return if (ctx != null) AndroidHapticEngine(ctx) else NoOpHapticEngine()
}

@Composable
actual fun rememberPlatformHapticEngine(): HapticEngine {
    val context = LocalContext.current
    return remember(context) { AndroidHapticEngine(context) }
}

