package com.techunicorn.studio.ui.screens.flow

import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.ui.theme.StudioCoral
import com.techunicorn.studio.ui.theme.StudioEmerald
import com.techunicorn.studio.ui.theme.StudioViolet
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

@Composable
fun FlowScreen(
    onSaveToIdeas: (String) -> Unit
) {
    var isRecording by remember { mutableStateOf(false) }
    var isPolishing by remember { mutableStateOf(false) }
    var rawTranscript by remember { mutableStateOf("") }
    var polishedMarkdown by remember { mutableStateOf("") }
    val coroutineScope = rememberCoroutineScope()

    // Pulse animation for recording state
    val infiniteTransition = rememberInfiniteTransition()
    val pulseScale by infiniteTransition.animateFloat(
        initialValue = 1f,
        targetValue = 1.25f,
        animationSpec = infiniteRepeatable(
            animation = tween(800, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        )
    )

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        // Header
        Text(
            text = "Flow Dictation",
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold
        )
        Text(
            text = "Speak messy, get polished Markdown",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )

        Spacer(Modifier.height(16.dp))

        // Content Display
        Card(
            modifier = Modifier.weight(1f).fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(16.dp)
                    .verticalScroll(rememberScrollState())
            ) {
                if (polishedMarkdown.isNotEmpty()) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text("POLISHED OUTPUT", color = StudioEmerald, fontWeight = FontWeight.Bold, fontSize = 11.sp)
                        IconButton(onClick = { polishedMarkdown = "" }) {
                            Icon(Icons.Default.Refresh, contentDescription = "Clear", modifier = Modifier.size(18.dp))
                        }
                    }
                    Spacer(Modifier.height(8.dp))
                    Text(polishedMarkdown, style = MaterialTheme.typography.bodyLarge)

                    Spacer(Modifier.height(16.dp))
                    Button(
                        onClick = { onSaveToIdeas(polishedMarkdown) },
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.buttonColors(containerColor = StudioEmerald)
                    ) {
                        Icon(Icons.Default.Lightbulb, contentDescription = null, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("Save as YouTube Idea")
                    }
                } else if (rawTranscript.isNotEmpty()) {
                    Text("LIVE SPEECH TRANSCRIPT", color = StudioViolet, fontWeight = FontWeight.Bold, fontSize = 11.sp)
                    Spacer(Modifier.height(8.dp))
                    Text(rawTranscript, style = MaterialTheme.typography.bodyLarge)
                } else {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Text(
                            text = if (isRecording) "Listening... speak freely" else "Press and hold the button below to dictate your thoughts",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }
        }

        Spacer(Modifier.height(16.dp))

        // Waveform Visualizer Mock
        if (isRecording) {
            Row(
                modifier = Modifier.fillMaxWidth().height(36.dp),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically
            ) {
                for (i in 1..15) {
                    val barHeight = (10..30).random().dp
                    Box(
                        modifier = Modifier
                            .padding(horizontal = 2.dp)
                            .width(4.dp)
                            .height(barHeight)
                            .clip(RoundedCornerShape(2.dp))
                            .background(StudioCoral)
                    )
                }
            }
            Spacer(Modifier.height(12.dp))
        }

        // Push-to-Talk Button
        Box(contentAlignment = Alignment.Center) {
            if (isRecording) {
                Box(
                    modifier = Modifier
                        .size(90.dp)
                        .scale(pulseScale)
                        .clip(CircleShape)
                        .background(StudioCoral.copy(alpha = 0.25f))
                )
            }

            Box(
                modifier = Modifier
                    .size(80.dp)
                    .clip(CircleShape)
                    .background(if (isRecording) StudioCoral else StudioViolet)
                    .pointerInput(Unit) {
                        detectTapGestures(
                            onPress = {
                                isRecording = true
                                rawTranscript = "So what if we make a video explaining why Kotlin multiplatform is actually the best stack for creator tooling, because you get native Android plus desktop preview with zero emulator overhead..."
                                tryAwaitRelease()
                                isRecording = false
                                isPolishing = true
                                coroutineScope.launch {
                                    delay(800)
                                    isPolishing = false
                                    polishedMarkdown = """
# YouTube Idea: Why Kotlin Multiplatform is the Secret Weapon for Creators

### The Core Angle
Why every technical creator building custom tools should look at KMP instead of Flutter or Electron.

### Key Points:
- 100% Native Jetpack Compose UI on Android.
- Instant local desktop preview (`./gradlew run`) without spinning up heavy emulators.
- Shared networking, state management, and offline cache.

### Hook:
"I stopped building web wrappers for my creator studio. Here is why Kotlin Multiplatform is 10x faster."
                                    """.trimIndent()
                                }
                            }
                        )
                    },
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = if (isRecording) Icons.Default.Mic else Icons.Default.MicNone,
                    contentDescription = "Push to Talk",
                    tint = Color.White,
                    modifier = Modifier.size(36.dp)
                )
            }
        }

        Spacer(Modifier.height(12.dp))
        Text(
            text = if (isPolishing) "Polishing with AI..." else if (isRecording) "Release when done" else "Hold to speak",
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}

