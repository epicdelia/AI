package com.techunicorn.studio.ui.theme

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.DarkMode
import androidx.compose.material.icons.filled.LightMode
import androidx.compose.material.icons.filled.SettingsBrightness
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

enum class ThemeMode {
    LIGHT, DARK, SYSTEM
}

val LocalThemeMode = compositionLocalOf { ThemeMode.SYSTEM }
val LocalSetThemeMode = compositionLocalOf<(ThemeMode) -> Unit> { {} }

private val DarkColorScheme = darkColorScheme(
    primary = StudioAccentDark,
    onPrimary = Color.White,
    primaryContainer = StudioAccentSoftDark,
    onPrimaryContainer = StudioInkDark,
    secondary = StudioVioletDark,
    onSecondary = Color.White,
    secondaryContainer = StudioVioletSoftDark,
    onSecondaryContainer = StudioInkDark,
    tertiary = StudioGoodDark,
    background = StudioBgDark,
    surface = StudioPanelDark,
    surfaceVariant = StudioFieldDark,
    onBackground = StudioInkDark,
    onSurface = StudioInkDark,
    onSurfaceVariant = StudioMutedDark,
    outline = StudioLineDark,
    outlineVariant = StudioHoverLineDark,
    error = StudioBadDark,
    errorContainer = StudioBadSoftDark,
    onError = Color.White
)

private val LightColorScheme = lightColorScheme(
    primary = StudioAccentLight,
    onPrimary = Color.White,
    primaryContainer = StudioAccentSoftLight,
    onPrimaryContainer = StudioInkLight,
    secondary = StudioVioletLight,
    onSecondary = Color.White,
    secondaryContainer = StudioVioletSoftLight,
    onSecondaryContainer = StudioInkLight,
    tertiary = StudioGoodLight,
    background = StudioBgLight,
    surface = StudioPanelLight,
    surfaceVariant = StudioFieldLight,
    onBackground = StudioInkLight,
    onSurface = StudioInkLight,
    onSurfaceVariant = StudioMutedLight,
    outline = StudioLineLight,
    outlineVariant = StudioHoverLineLight,
    error = StudioBadLight,
    errorContainer = StudioBadSoftLight,
    onError = Color.White
)

@Composable
fun StudioTheme(
    themeMode: ThemeMode = ThemeMode.SYSTEM,
    onThemeChange: (ThemeMode) -> Unit = {},
    content: @Composable () -> Unit
) {
    val systemDark = isSystemInDarkTheme()
    val isDark = when (themeMode) {
        ThemeMode.LIGHT -> false
        ThemeMode.DARK -> true
        ThemeMode.SYSTEM -> systemDark
    }

    val colorScheme = if (isDark) DarkColorScheme else LightColorScheme

    CompositionLocalProvider(
        LocalThemeMode provides themeMode,
        LocalSetThemeMode provides onThemeChange
    ) {
        MaterialTheme(
            colorScheme = colorScheme,
            typography = StudioTypography,
            content = content
        )
    }
}

/**
 * Reusable Segmented Theme Switch matching Creator Studio's theme.js:
 * Three options: Light (sun), Dark (moon), System (monitor).
 */
@Composable
fun ThemeSwitch(
    currentMode: ThemeMode,
    onModeSelected: (ThemeMode) -> Unit,
    modifier: Modifier = Modifier
) {
    val options = listOf(
        Triple(ThemeMode.LIGHT, Icons.Default.LightMode, "Light"),
        Triple(ThemeMode.DARK, Icons.Default.DarkMode, "Dark"),
        Triple(ThemeMode.SYSTEM, Icons.Default.SettingsBrightness, "System")
    )

    Surface(
        modifier = modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(10.dp))
            .border(1.dp, MaterialTheme.colorScheme.outline, RoundedCornerShape(10.dp)),
        color = MaterialTheme.colorScheme.surfaceVariant
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(3.dp),
            horizontalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            options.forEach { (mode, icon, label) ->
                val selected = currentMode == mode
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(8.dp))
                        .background(
                            if (selected) MaterialTheme.colorScheme.surface
                            else Color.Transparent
                        )
                        .clickable { onModeSelected(mode) }
                        .padding(vertical = 8.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.Center
                    ) {
                        Icon(
                            imageVector = icon,
                            contentDescription = label,
                            modifier = Modifier.size(15.dp),
                            tint = if (selected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Spacer(Modifier.width(6.dp))
                        Text(
                            text = label,
                            fontSize = 12.sp,
                            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
                            color = if (selected) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }
        }
    }
}
