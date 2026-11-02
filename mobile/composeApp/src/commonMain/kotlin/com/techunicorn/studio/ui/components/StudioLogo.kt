package com.techunicorn.studio.ui.components

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.size
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.drawscope.Fill
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.techunicorn.studio.ui.theme.StudioCoral
import com.techunicorn.studio.ui.theme.StudioViolet

/**
 * Official Creator Studio isometric voxel prism brand mark.
 * Reconstructed directly from creator-studio/public/js/icons.js (brandMark):
 *
 * <svg viewBox="0 0 24 24">
 *   <path d="M12 3L20 7.5L12 12L4 7.5Z"/> (Top Face, 100% opacity)
 *   <path opacity=".7" d="M4 7.5L12 12L12 21L4 16.5Z"/> (Left Face, 70% opacity)
 *   <path opacity=".4" d="M20 7.5L12 12L12 21L20 16.5Z"/> (Right Face, 40% opacity)
 * </svg>
 */
@Composable
fun StudioLogo(
    modifier: Modifier = Modifier,
    size: Dp = 24.dp,
    tint: Color = StudioCoral,
    useGradient: Boolean = false
) {
    Box(
        modifier = modifier.size(size),
        contentAlignment = Alignment.Center
    ) {
        Canvas(modifier = Modifier.size(size)) {
            val w = this.size.width
            val h = this.size.height

            // Normalized coordinate multipliers from 24x24 viewBox
            val sx = w / 24f
            val sy = h / 24f

            val primaryColor = if (useGradient) StudioCoral else tint
            val secondaryColor = if (useGradient) StudioViolet else tint

            // 1. Top Face: M12 3L20 7.5L12 12L4 7.5Z (Full Opacity)
            val topFace = Path().apply {
                moveTo(12f * sx, 3f * sy)
                lineTo(20f * sx, 7.5f * sy)
                lineTo(12f * sx, 12f * sy)
                lineTo(4f * sx, 7.5f * sy)
                close()
            }
            drawPath(
                path = topFace,
                brush = SolidColor(primaryColor.copy(alpha = 1.0f)),
                style = Fill
            )

            // 2. Left Face: M4 7.5L12 12L12 21L4 16.5Z (70% Opacity)
            val leftFace = Path().apply {
                moveTo(4f * sx, 7.5f * sy)
                lineTo(12f * sx, 12f * sy)
                lineTo(12f * sx, 21f * sy)
                lineTo(4f * sx, 16.5f * sy)
                close()
            }
            drawPath(
                path = leftFace,
                brush = SolidColor(secondaryColor.copy(alpha = 0.70f)),
                style = Fill
            )

            // 3. Right Face: M20 7.5L12 12L12 21L20 16.5Z (40% Opacity)
            val rightFace = Path().apply {
                moveTo(20f * sx, 7.5f * sy)
                lineTo(12f * sx, 12f * sy)
                lineTo(12f * sx, 21f * sy)
                lineTo(20f * sx, 16.5f * sy)
                close()
            }
            drawPath(
                path = rightFace,
                brush = SolidColor(primaryColor.copy(alpha = 0.40f)),
                style = Fill
            )
        }
    }
}

