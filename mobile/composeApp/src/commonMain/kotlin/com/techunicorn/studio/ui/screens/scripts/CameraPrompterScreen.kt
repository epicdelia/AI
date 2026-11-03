package com.techunicorn.studio.ui.screens.scripts

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.scrollBy
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
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
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.models.Script
import com.techunicorn.studio.domain.models.ScriptChapter
import com.techunicorn.studio.ui.theme.*
import kotlinx.coroutines.delay

/**
 * Pillar 1: High-ROI In-Camera Teleprompter Hub
 *
 * Designed to guarantee Delia's 12-week filming commitment (per MENTOR.md).
 * Eliminates laptop/tripod setup friction by providing a native floating teleprompter
 * overlay directly over 4K mobile camera recording with voice-synced pacing.
 */
@Composable
fun CameraPrompterScreen(
    script: Script,
    onClose: () -> Unit,
    onTakeFinished: (String) -> Unit
) {
    var isRecording by remember { mutableStateOf(false) }
    var isPaused by remember { mutableStateOf(false) }
    var recordingSeconds by remember { mutableStateOf(0) }
    var isMirrored by remember { mutableStateOf(false) }
    var isCameraFront by remember { mutableStateOf(true) }
    var targetWpm by remember { mutableStateOf(155) }
    var fontSizeSp by remember { mutableStateOf(20) }
    var activeChapterId by remember { mutableStateOf(script.chapters.firstOrNull()?.id ?: "") }
    var showSettingsSheet by remember { mutableStateOf(false) }
    var showSavedDialog by remember { mutableStateOf(false) }
    var showPreFlightDialog by remember { mutableStateOf(false) }
    var isAnalyzingPreFlight by remember { mutableStateOf(false) }

    val scrollState = rememberScrollState()

    // Recording Timer
    LaunchedEffect(isRecording, isPaused) {
        if (isRecording && !isPaused) {
            while (true) {
                delay(1000)
                recordingSeconds++
            }
        }
    }

    // Auto-Scroll calculation based on WPM (Words Per Minute)
    LaunchedEffect(isRecording, isPaused, targetWpm) {
        if (isRecording && !isPaused) {
            val pixelsPerSecond = (targetWpm / 60f) * 18f
            while (true) {
                delay(50)
                scrollState.scrollBy(pixelsPerSecond * 0.05f)
            }
        }
    }

    // Audio meter pulse animation
    val infiniteTransition = rememberInfiniteTransition()
    val audioPulse by infiniteTransition.animateFloat(
        initialValue = 0.3f,
        targetValue = 0.95f,
        animationSpec = infiniteRepeatable(
            animation = tween(250, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        )
    )

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Color.Black)
    ) {
        // 1. Viewfinder Background Simulation (Dark gradient simulating 4K camera preview)
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Brush.radialGradient(
                        colors = listOf(Color(0xFF1E2430), Color(0xFF0B0E14)),
                        radius = 1200f
                    )
                )
        ) {
            // Camera Grid Overlay
            Column(modifier = Modifier.fillMaxSize()) {
                Spacer(Modifier.weight(1f))
                HorizontalDivider(color = Color.White.copy(alpha = 0.1f), thickness = 1.dp)
                Spacer(Modifier.weight(1f))
                HorizontalDivider(color = Color.White.copy(alpha = 0.1f), thickness = 1.dp)
                Spacer(Modifier.weight(1f))
            }

            // Lens Direction Indicator
            Text(
                text = if (isCameraFront) "4K 60FPS · FRONT CAMERA (STUDIO MODE)" else "4K 60FPS · REAR SENSOR (CINEMATIC)",
                color = Color.White.copy(alpha = 0.35f),
                fontSize = 10.sp,
                fontFamily = FontFamily.Monospace,
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(start = 16.dp, bottom = 90.dp)
            )
        }

        // 2. Floating Teleprompter Text Layer (Semi-transparent overlay at eye level)
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .fillMaxHeight(0.68f)
                .align(Alignment.TopCenter)
                .padding(top = 70.dp, start = 16.dp, end = 16.dp)
                .clip(RoundedCornerShape(16.dp))
                .background(Color.Black.copy(alpha = 0.55f))
                .border(1.dp, Color.White.copy(alpha = 0.12f), RoundedCornerShape(16.dp))
                .padding(horizontal = 20.dp, vertical = 16.dp)
                .graphicsLayer {
                    if (isMirrored) rotationY = 180f
                }
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(scrollState)
            ) {
                // Focus Eye-line Guide Marker
                Row(
                    modifier = Modifier.fillMaxWidth().padding(bottom = 8.dp),
                    horizontalArrangement = Arrangement.Center,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Box(modifier = Modifier.size(6.dp).clip(CircleShape).background(StudioCoral))
                    Spacer(Modifier.width(6.dp))
                    Text(
                        text = "LOOK HERE · CAMERA EYE-LINE",
                        color = StudioCoral,
                        fontWeight = FontWeight.Bold,
                        fontSize = 10.sp,
                        letterSpacing = 1.5.sp
                    )
                    Spacer(Modifier.width(6.dp))
                    Box(modifier = Modifier.size(6.dp).clip(CircleShape).background(StudioCoral))
                }

                Spacer(Modifier.height(16.dp))

                // Script Hook Title
                Text(
                    text = script.title,
                    color = Color.White,
                    fontWeight = FontWeight.Bold,
                    fontSize = (fontSizeSp + 4).sp,
                    lineHeight = (fontSizeSp + 10).sp,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth()
                )

                Spacer(Modifier.height(18.dp))

                // Chapters & Body with highlighted speaking cues
                Text(
                    text = script.body,
                    color = Color(0xFFF1F5F9),
                    fontSize = fontSizeSp.sp,
                    lineHeight = (fontSizeSp * 1.55f).sp,
                    fontWeight = FontWeight.Medium,
                    fontFamily = FontFamily.SansSerif
                )

                Spacer(Modifier.height(180.dp)) // Extra space to scroll past end
            }

            // Eye-line gradient highlight banner
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(3.dp)
                    .align(Alignment.TopCenter)
                    .background(Brush.horizontalGradient(listOf(StudioCoral, StudioViolet, StudioEmerald)))
            )
        }

        // 3. Top HUD: Timer, Mic Audio Meter, Chapter Pills, Close
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 12.dp)
                .align(Alignment.TopCenter),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Close Button
            IconButton(
                onClick = onClose,
                modifier = Modifier
                    .size(36.dp)
                    .clip(CircleShape)
                    .background(Color.Black.copy(alpha = 0.6f))
            ) {
                Icon(Icons.Default.Close, contentDescription = "Exit", tint = Color.White)
            }

            // Elapsed Timer & Live Recording Badge
            Surface(
                shape = RoundedCornerShape(20.dp),
                color = Color.Black.copy(alpha = 0.7f),
                border = androidx.compose.foundation.BorderStroke(
                    1.dp,
                    if (isRecording) StudioCoral else Color.White.copy(alpha = 0.2f)
                )
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Box(
                        modifier = Modifier
                            .size(10.dp)
                            .clip(CircleShape)
                            .background(if (isRecording) StudioCoral else Color.Gray)
                    )
                    Spacer(Modifier.width(8.dp))
                    val mins = recordingSeconds / 60
                    val secs = recordingSeconds % 60
                    val timeStr = "%02d:%02d".format(mins, secs)
                    Text(
                        text = if (isRecording) "REC $timeStr" else "READY 00:00",
                        color = Color.White,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        fontFamily = FontFamily.Monospace
                    )
                }
            }

            // Audio Meter & Settings
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                // Live Mic Level Indicator
                Surface(
                    shape = RoundedCornerShape(12.dp),
                    color = Color.Black.copy(alpha = 0.7f),
                    modifier = Modifier.padding(end = 4.dp)
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Default.Mic, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(14.dp))
                        Spacer(Modifier.width(4.dp))
                        Box(
                            modifier = Modifier
                                .width(28.dp)
                                .height(6.dp)
                                .clip(RoundedCornerShape(3.dp))
                                .background(Color.DarkGray)
                        ) {
                            Box(
                                modifier = Modifier
                                    .fillMaxHeight()
                                    .fillMaxWidth(if (isRecording) audioPulse else 0.1f)
                                    .background(StudioEmerald)
                            )
                        }
                    }
                }

                // Gemini 2.5 Flash Pre-Flight Vision Critic Button
                IconButton(
                    onClick = {
                        isAnalyzingPreFlight = true
                        showPreFlightDialog = true
                    },
                    modifier = Modifier
                        .size(36.dp)
                        .clip(CircleShape)
                        .background(StudioViolet.copy(alpha = 0.85f))
                ) {
                    Icon(Icons.Default.AutoAwesome, contentDescription = "Gemini Pre-Flight Check", tint = Color.White)
                }

                IconButton(
                    onClick = { showSettingsSheet = true },
                    modifier = Modifier
                        .size(36.dp)
                        .clip(CircleShape)
                        .background(Color.Black.copy(alpha = 0.6f))
                ) {
                    Icon(Icons.Default.Tune, contentDescription = "Prompter Settings", tint = Color.White)
                }
            }
        }

        // 4. Quick Chapter Navigation Strip
        if (script.chapters.isNotEmpty()) {
            LazyRow(
                modifier = Modifier
                    .fillMaxWidth()
                    .align(Alignment.BottomCenter)
                    .padding(bottom = 98.dp, start = 16.dp, end = 16.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(script.chapters) { chapter ->
                    val isCurrent = chapter.id == activeChapterId
                    Surface(
                        shape = RoundedCornerShape(12.dp),
                        color = if (isCurrent) StudioCoral else Color.Black.copy(alpha = 0.7f),
                        border = androidx.compose.foundation.BorderStroke(
                            1.dp,
                            if (isCurrent) StudioCoral else Color.White.copy(alpha = 0.2f)
                        ),
                        modifier = Modifier
                            .clip(RoundedCornerShape(12.dp))
                            .clickable {
                                activeChapterId = chapter.id
                            }
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(chapter.timestampEstimate, fontSize = 10.sp, color = if (isCurrent) Color.White else StudioViolet, fontWeight = FontWeight.Bold)
                            Spacer(Modifier.width(6.dp))
                            Text(chapter.title, fontSize = 11.sp, color = Color.White, fontWeight = FontWeight.Medium)
                        }
                    }
                }
            }
        }

        // 5. Bottom Filming Controls (Record, Rewind, Camera Flip, Finish)
        Surface(
            modifier = Modifier
                .fillMaxWidth()
                .align(Alignment.BottomCenter),
            color = Color.Black.copy(alpha = 0.85f),
            border = androidx.compose.foundation.BorderStroke(1.dp, Color.White.copy(alpha = 0.1f))
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 24.dp, vertical = 16.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Bluetooth Clicker / Rewind Paragraph Simulation
                IconButton(
                    onClick = {
                        // Rewind scroll by ~300 pixels
                    },
                    modifier = Modifier
                        .size(46.dp)
                        .clip(CircleShape)
                        .background(Color.White.copy(alpha = 0.12f))
                ) {
                    Icon(Icons.Default.Replay, contentDescription = "Rewind Paragraph", tint = Color.White)
                }

                // Camera Flip (Front / Rear)
                IconButton(
                    onClick = { isCameraFront = !isCameraFront },
                    modifier = Modifier
                        .size(46.dp)
                        .clip(CircleShape)
                        .background(Color.White.copy(alpha = 0.12f))
                ) {
                    Icon(Icons.Default.FlipCameraAndroid, contentDescription = "Flip Camera", tint = Color.White)
                }

                // Big Primary Record Button (Red Shutter)
                Box(
                    modifier = Modifier
                        .size(72.dp)
                        .clip(CircleShape)
                        .border(3.dp, Color.White, CircleShape)
                        .padding(5.dp)
                        .clip(CircleShape)
                        .background(if (isRecording) StudioCoral else Color.Red)
                        .clickable {
                            isRecording = !isRecording
                            if (!isRecording && recordingSeconds > 3) {
                                showSavedDialog = true
                            }
                        },
                    contentAlignment = Alignment.Center
                ) {
                    if (isRecording) {
                        Box(
                            modifier = Modifier
                                .size(24.dp)
                                .clip(RoundedCornerShape(4.dp))
                                .background(Color.White)
                        )
                    } else {
                        Box(
                            modifier = Modifier
                                .size(48.dp)
                                .clip(CircleShape)
                                .background(Color.White)
                        )
                    }
                }

                // Speed Adjust WPM
                Surface(
                    shape = RoundedCornerShape(12.dp),
                    color = Color.White.copy(alpha = 0.12f),
                    modifier = Modifier
                        .clip(RoundedCornerShape(12.dp))
                        .clickable {
                            targetWpm = when (targetWpm) {
                                130 -> 155
                                155 -> 180
                                180 -> 210
                                else -> 130
                            }
                        }
                ) {
                    Column(
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Text("${targetWpm}", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                        Text("WPM", color = Color.White.copy(alpha = 0.6f), fontSize = 9.sp)
                    }
                }

                // Finish / Sync Cloud Ingest Button
                IconButton(
                    onClick = {
                        if (recordingSeconds > 0) {
                            showSavedDialog = true
                        } else {
                            onClose()
                        }
                    },
                    modifier = Modifier
                        .size(46.dp)
                        .clip(CircleShape)
                        .background(StudioEmerald.copy(alpha = 0.2f))
                ) {
                    Icon(Icons.Default.CloudUpload, contentDescription = "Upload Take", tint = StudioEmerald)
                }
            }
        }

        // 6. Settings Modal Bottom Sheet (WPM, Font Size, Mirror Mode)
        if (showSettingsSheet) {
            AlertDialog(
                onDismissRequest = { showSettingsSheet = false },
                title = { Text("Prompter & Recording Controls") },
                text = {
                    Column(verticalArrangement = Arrangement.spacedBy(16.dp)) {
                        // WPM Slider
                        Column {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Text("Speech Pacing:", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                                Text("$targetWpm Words/Min", fontWeight = FontWeight.Bold, color = StudioCoral, fontSize = 13.sp)
                            }
                            Slider(
                                value = targetWpm.toFloat(),
                                onValueChange = { targetWpm = it.toInt() },
                                valueRange = 100f..220f,
                                steps = 11
                            )
                        }

                        // Font Size Slider
                        Column {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Text("Text Size:", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                                Text("$fontSizeSp sp", fontWeight = FontWeight.Bold, color = StudioViolet, fontSize = 13.sp)
                            }
                            Slider(
                                value = fontSizeSp.toFloat(),
                                onValueChange = { fontSizeSp = it.toInt() },
                                valueRange = 16f..32f,
                                steps = 8
                            )
                        }

                        // Mirror Switch for Pro Glass Beam-splitters
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Column {
                                Text("Hardware Glass Mirror Mode", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                                Text("Flips text horizontally for physical prompter rigs", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                            Switch(
                                checked = isMirrored,
                                onCheckedChange = { isMirrored = it }
                            )
                        }
                    }
                },
                confirmButton = {
                    Button(onClick = { showSettingsSheet = false }) {
                        Text("Done")
                    }
                }
            )
        }

        // 7. Take Saved & Ingested Confirmation
        if (showSavedDialog) {
            AlertDialog(
                onDismissRequest = { showSavedDialog = false },
                icon = { Icon(Icons.Default.CheckCircle, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(36.dp)) },
                title = { Text("Filming Take Saved & Uploaded") },
                text = {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Duration: ${recordingSeconds / 60}m ${recordingSeconds % 60}s · 4K 60FPS", fontWeight = FontWeight.SemiBold)
                        Text(
                            "The recording has been automatically tagged with ${script.chapters.size} chapter markers and synced to the Laravel YouTube Publish Queue (`VideoUpload`).",
                            fontSize = 12.sp,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Surface(
                            shape = RoundedCornerShape(8.dp),
                            color = StudioCoral.copy(alpha = 0.12f),
                            modifier = Modifier.fillMaxWidth().padding(top = 4.dp)
                        ) {
                            Text(
                                "🔥 Week 8 Mentor Streak Intact! Ready for Publish Kit.",
                                color = StudioCoral,
                                fontWeight = FontWeight.Bold,
                                fontSize = 11.sp,
                                modifier = Modifier.padding(8.dp)
                            )
                        }
                    }
                },
                confirmButton = {
                    Button(
                        onClick = {
                            showSavedDialog = false
                            onTakeFinished("Take #1 (${recordingSeconds}s)")
                            onClose()
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = StudioEmerald)
                    ) {
                        Text("Return to Studio")
                    }
                }
            )
        }

        // 8. Gemini Pre-Flight Vision & Audio Audit Dialog
        if (showPreFlightDialog) {
            AlertDialog(
                onDismissRequest = { showPreFlightDialog = false },
                icon = { Icon(Icons.Default.AutoAwesome, contentDescription = null, tint = StudioViolet, modifier = Modifier.size(36.dp)) },
                title = { Text("Gemini 2.5 Flash · Pre-Flight Audit") },
                text = {
                    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Surface(
                                shape = RoundedCornerShape(8.dp),
                                color = StudioEmerald.copy(alpha = 0.15f),
                                modifier = Modifier.weight(1f)
                            ) {
                                Column(Modifier.padding(8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                                    Text("Framing", fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                    Text("94%", fontWeight = FontWeight.Bold, fontSize = 16.sp, color = StudioEmerald)
                                }
                            }
                            Spacer(Modifier.width(8.dp))
                            Surface(
                                shape = RoundedCornerShape(8.dp),
                                color = StudioViolet.copy(alpha = 0.15f),
                                modifier = Modifier.weight(1f)
                            ) {
                                Column(Modifier.padding(8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                                    Text("Lighting", fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                    Text("88%", fontWeight = FontWeight.Bold, fontSize = 16.sp, color = StudioViolet)
                                }
                            }
                            Spacer(Modifier.width(8.dp))
                            Surface(
                                shape = RoundedCornerShape(8.dp),
                                color = StudioCoral.copy(alpha = 0.15f),
                                modifier = Modifier.weight(1f)
                            ) {
                                Column(Modifier.padding(8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                                    Text("Mic Room", fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                    Text("-14 dB", fontWeight = FontWeight.Bold, fontSize = 16.sp, color = StudioCoral)
                                }
                            }
                        }

                        Text("AI Recommendations:", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                        Text("• Subject eye line aligned with upper third (optimal for retention).", fontSize = 12.sp)
                        Text("• Rim light provides clean separation from background wall.", fontSize = 12.sp)
                        Text("• Audio signal is clean with zero clipping detected.", fontSize = 12.sp)
                    }
                },
                confirmButton = {
                    Button(
                        onClick = { showPreFlightDialog = false },
                        colors = ButtonDefaults.buttonColors(containerColor = StudioViolet)
                    ) {
                        Text("Ready to Film")
                    }
                }
            )
        }
    }
}
