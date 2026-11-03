package com.techunicorn.studio.ui.components

import androidx.compose.animation.core.*
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import com.techunicorn.studio.platform.audio.AudioSample
import com.techunicorn.studio.ui.theme.StudioColors

@Composable
fun AudioWaveformVisualizer(
    sample: AudioSample,
    waveformHistory: List<Float>,
    isRecording: Boolean,
    modifier: Modifier = Modifier
) {
    val infiniteTransition = rememberInfiniteTransition()
    val pulseGlow by infiniteTransition.animateFloat(
        initialValue = 0.7f,
        targetValue = 1.0f,
        animationSpec = infiniteRepeatable(
            animation = tween(800, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        )
    )

    Canvas(
        modifier = modifier
            .fillMaxWidth()
            .height(110.dp)
            .padding(horizontal = 8.dp, vertical = 6.dp)
    ) {
        val width = size.width
        val height = size.height
        val centerY = height / 2f

        // Draw center baseline
        drawLine(
            color = Color.White.copy(alpha = 0.12f),
            start = Offset(0f, centerY),
            end = Offset(width, centerY),
            strokeWidth = 1.5f
        )

        val bands = sample.frequencyBands
        if (bands.isNotEmpty()) {
            val totalBars = bands.size
            val barWidth = (width / (totalBars * 1.6f)).coerceIn(4f, 16f)
            val barSpacing = (width - (totalBars * barWidth)) / (totalBars + 1)

            val gradientBrush = Brush.verticalGradient(
                colors = listOf(
                    StudioColors.NeonPurple,
                    StudioColors.UnicornBlue,
                    StudioColors.NeonPurple.copy(alpha = 0.4f)
                )
            )

            bands.forEachIndexed { i, bandVal ->
                val barHeight = (height * 0.85f * bandVal * if (isRecording) pulseGlow else 0.2f).coerceAtLeast(6f)
                val left = barSpacing + i * (barWidth + barSpacing)
                val top = centerY - (barHeight / 2f)

                drawRoundRect(
                    brush = gradientBrush,
                    topLeft = Offset(left, top),
                    size = Size(barWidth, barHeight),
                    cornerRadius = CornerRadius(barWidth / 2f, barWidth / 2f)
                )
            }
        }
    }
}

