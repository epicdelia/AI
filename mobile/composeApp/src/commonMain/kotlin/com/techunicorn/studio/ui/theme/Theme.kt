package com.techunicorn.studio.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val DarkColorScheme = darkColorScheme(
    primary = StudioCoral,
    onPrimary = Color.White,
    primaryContainer = StudioSurfaceVariantDark,
    onPrimaryContainer = StudioTextPrimaryDark,
    secondary = StudioViolet,
    onSecondary = Color.White,
    tertiary = StudioEmerald,
    background = StudioBackgroundDark,
    surface = StudioSurfaceDark,
    surfaceVariant = StudioSurfaceVariantDark,
    onBackground = StudioTextPrimaryDark,
    onSurface = StudioTextPrimaryDark,
    onSurfaceVariant = StudioTextSecondaryDark,
    outline = StudioCardBorderDark
)

private val LightColorScheme = lightColorScheme(
    primary = StudioCoral,
    onPrimary = Color.White,
    primaryContainer = StudioSurfaceVariantLight,
    onPrimaryContainer = StudioTextPrimaryLight,
    secondary = StudioViolet,
    onSecondary = Color.White,
    tertiary = StudioEmerald,
    background = StudioBackgroundLight,
    surface = StudioSurfaceLight,
    surfaceVariant = StudioSurfaceVariantLight,
    onBackground = StudioTextPrimaryLight,
    onSurface = StudioTextPrimaryLight,
    onSurfaceVariant = StudioTextSecondaryLight,
    outline = StudioCardBorderLight
)

@Composable
fun StudioTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val colorScheme = if (darkTheme) DarkColorScheme else LightColorScheme

    MaterialTheme(
        colorScheme = colorScheme,
        typography = StudioTypography,
        content = content
    )
}

