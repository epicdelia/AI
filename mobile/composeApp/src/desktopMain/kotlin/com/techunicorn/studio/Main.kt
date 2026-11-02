package com.techunicorn.studio

import androidx.compose.ui.unit.DpSize
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Window
import androidx.compose.ui.window.application
import androidx.compose.ui.window.rememberWindowState

fun main() = application {
    // Flagship smartphone viewport dimensions (412 x 892 dp - Pixel / modern Galaxy size)
    val windowState = rememberWindowState(size = DpSize(420.dp, 880.dp))

    Window(
        onCloseRequest = ::exitApplication,
        state = windowState,
        title = "Creator Studio Mobile · Local Live Viewer",
        resizable = true
    ) {
        App()
    }
}

