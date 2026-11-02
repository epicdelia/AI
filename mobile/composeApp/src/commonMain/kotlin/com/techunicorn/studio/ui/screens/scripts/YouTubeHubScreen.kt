package com.techunicorn.studio.ui.screens.scripts

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.gestures.scrollBy
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.models.Breakout
import com.techunicorn.studio.domain.models.Idea
import com.techunicorn.studio.domain.models.Script
import com.techunicorn.studio.ui.theme.*
import kotlinx.coroutines.delay

@Composable
fun YouTubeHubScreen(
    breakouts: List<Breakout>,
    ideas: List<Idea>,
    scripts: List<Script>,
    onOpenCopilot: () -> Unit
) {
    var selectedTab by remember { mutableStateOf(0) } // 0: Breakouts, 1: Ideas, 2: Scripts
    var activeTeleprompterScript by remember { mutableStateOf<Script?>(null) }

    // If Teleprompter is active, render fullscreen
    if (activeTeleprompterScript != null) {
        TeleprompterFullscreen(
            script = activeTeleprompterScript!!,
            onClose = { activeTeleprompterScript = null }
        )
        return
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
    ) {
        // Tab Row
        TabRow(
            selectedTabIndex = selectedTab,
            containerColor = MaterialTheme.colorScheme.surface,
            contentColor = StudioCoral
        ) {
            Tab(selected = selectedTab == 0, onClick = { selectedTab = 0 }, text = { Text("Radar") })
            Tab(selected = selectedTab == 1, onClick = { selectedTab = 1 }, text = { Text("Ideas (${ideas.size})") })
            Tab(selected = selectedTab == 2, onClick = { selectedTab = 2 }, text = { Text("Scripts") })
        }

        when (selectedTab) {
            0 -> BreakoutsTab(breakouts)
            1 -> IdeasTab(ideas)
            2 -> ScriptsTab(scripts, onLaunchTeleprompter = { activeTeleprompterScript = it })
        }
    }
}

@Composable
fun BreakoutsTab(breakouts: List<Breakout>) {
    LazyColumn(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        item {
            Text("Breakout Radar", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            Text("Videos significantly outperforming their channel median", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Spacer(Modifier.height(4.dp))
        }

        items(breakouts) { breakout ->
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(Modifier.padding(14.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(breakout.channelTitle, fontWeight = FontWeight.SemiBold, fontSize = 13.sp, color = StudioViolet)
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(6.dp))
                                .background(MultiplierBadgeBg)
                                .padding(horizontal = 8.dp, vertical = 4.dp)
                        ) {
                            Text("${breakout.multiplier}x Median", color = MultiplierGreen, fontWeight = FontWeight.Bold, fontSize = 11.sp)
                        }
                    }
                    Spacer(Modifier.height(6.dp))
                    Text(breakout.videoTitle, fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
                    Spacer(Modifier.height(8.dp))
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text("${breakout.views / 1000}K views (median: ${breakout.channelMedianViews / 1000}K)", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text(breakout.publishedAt, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }
        }
    }
}

@Composable
fun IdeasTab(ideas: List<Idea>) {
    LazyColumn(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text("Idea Deck", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                    Text("Swipe or greenlight to generate a script", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                Button(
                    onClick = {},
                    colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                    contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp)
                ) {
                    Icon(Icons.Default.Add, contentDescription = null, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("New Idea", fontSize = 12.sp)
                }
            }
        }

        items(ideas) { idea ->
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(Modifier.padding(14.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(idea.title, fontWeight = FontWeight.Bold, fontSize = 15.sp, modifier = Modifier.weight(1f))
                        if (idea.status == "greenlit") {
                            Box(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(6.dp))
                                    .background(MultiplierBadgeBg)
                                    .padding(horizontal = 6.dp, vertical = 2.dp)
                            ) {
                                Text("GREENLIT", color = MultiplierGreen, fontWeight = FontWeight.Bold, fontSize = 10.sp)
                            }
                        }
                    }
                    Spacer(Modifier.height(6.dp))
                    Text("Hook: \"${idea.hook}\"", fontSize = 13.sp, fontStyle = androidx.compose.ui.text.font.FontStyle.Italic)
                    Spacer(Modifier.height(6.dp))
                    Text("Angle: ${idea.angle}", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

                    Spacer(Modifier.height(10.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Button(
                            onClick = {},
                            colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                            modifier = Modifier.weight(1f)
                        ) {
                            Text("Greenlight")
                        }
                        OutlinedButton(
                            onClick = {},
                            modifier = Modifier.weight(1f)
                        ) {
                            Text("Archive")
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun ScriptsTab(
    scripts: List<Script>,
    onLaunchTeleprompter: (Script) -> Unit
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        items(scripts) { script ->
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(14.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(Modifier.padding(16.dp)) {
                    Text(script.title, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                    Text("Estimated: ${script.estimatedDurationMinutes} mins · ${script.wordCount} words", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp)

                    Spacer(Modifier.height(12.dp))

                    // Hook Banner
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(8.dp))
                            .background(StudioViolet.copy(alpha = 0.1f))
                            .padding(10.dp)
                    ) {
                        Text("Hook: ${script.hook}", fontSize = 13.sp)
                    }

                    Spacer(Modifier.height(12.dp))

                    // Retention Warnings
                    if (script.retentionWarnings.isNotEmpty()) {
                        Text("AI RETENTION LINTER", fontWeight = FontWeight.Bold, fontSize = 11.sp, color = StudioCoral)
                        Spacer(Modifier.height(4.dp))
                        for (warning in script.retentionWarnings) {
                            Row(Modifier.padding(vertical = 2.dp)) {
                                Icon(Icons.Default.Warning, contentDescription = null, tint = StudioAmber, modifier = Modifier.size(16.dp))
                                Spacer(Modifier.width(6.dp))
                                Text(warning, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                        }
                        Spacer(Modifier.height(12.dp))
                    }

                    // Teleprompter button
                    Button(
                        onClick = { onLaunchTeleprompter(script) },
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.buttonColors(containerColor = StudioCoral)
                    ) {
                        Icon(Icons.Default.Speed, contentDescription = null, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("Open Teleprompter Mode")
                    }
                }
            }
        }
    }
}

@Composable
fun TeleprompterFullscreen(
    script: Script,
    onClose: () -> Unit
) {
    var scrollSpeed by remember { mutableStateOf(2f) }
    var isMirrored by remember { mutableStateOf(false) }
    val scrollState = rememberScrollState()

    LaunchedEffect(scrollSpeed) {
        while (scrollSpeed > 0f) {
            scrollState.scrollBy(scrollSpeed)
            delay(16L) // 60 FPS
        }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Color.Black)
    ) {
        // Mirrored or Normal Text View
        Box(
            modifier = Modifier
                .fillMaxSize()
                .graphicsLayer { if (isMirrored) scaleX = -1f }
                .padding(horizontal = 24.dp, vertical = 72.dp)
                .verticalScroll(scrollState)
        ) {
            Column {
                Text(
                    text = script.title,
                    color = StudioCoral,
                    fontSize = 32.sp,
                    fontWeight = FontWeight.Bold
                )
                Spacer(Modifier.height(24.dp))
                Text(
                    text = script.body,
                    color = Color.White,
                    fontSize = 28.sp,
                    lineHeight = 40.sp,
                    fontWeight = FontWeight.Medium
                )
                Spacer(Modifier.height(300.dp)) // Extra padding at bottom
            }
        }

        // Floating Control Bar
        Card(
            modifier = Modifier
                .align(Alignment.BottomCenter)
                .padding(16.dp)
                .fillMaxWidth(),
            shape = RoundedCornerShape(24.dp),
            colors = CardDefaults.cardColors(containerColor = Color(0xDD1E293B))
        ) {
            Row(
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                IconButton(onClick = onClose) {
                    Icon(Icons.Default.Close, contentDescription = "Exit", tint = Color.White)
                }

                // Speed controls
                Row(verticalAlignment = Alignment.CenterVertically) {
                    IconButton(onClick = { if (scrollSpeed > 0.5f) scrollSpeed -= 0.5f }) {
                        Icon(Icons.Default.Remove, contentDescription = "Slower", tint = Color.White)
                    }
                    Text("${scrollSpeed}x", color = Color.White, fontWeight = FontWeight.Bold)
                    IconButton(onClick = { if (scrollSpeed < 5f) scrollSpeed += 0.5f }) {
                        Icon(Icons.Default.Add, contentDescription = "Faster", tint = Color.White)
                    }
                }

                // Mirror toggle
                IconButton(onClick = { isMirrored = !isMirrored }) {
                    Icon(
                        Icons.Default.Flip,
                        contentDescription = "Mirror",
                        tint = if (isMirrored) StudioCoral else Color.White
                    )
                }
            }
        }
    }
}

