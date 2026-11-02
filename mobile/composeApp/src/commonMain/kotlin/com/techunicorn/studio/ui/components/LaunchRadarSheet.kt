package com.techunicorn.studio.ui.components

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.ui.theme.*

/**
 * Pillar 4: 120-Minute Launch Velocity Radar Sheet
 *
 * The first 120 minutes of a YouTube upload determine 80% of its lifetime reach.
 * This radar monitors real-time CTR and view velocity against Delia's 10.1k baseline,
 * offering 1-tap bandit title & thumbnail swaps if early performance dips.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LaunchRadarSheet(
    onDismiss: () -> Unit,
    onTitleSwapped: (String) -> Unit
) {
    var selectedVariant by remember { mutableStateOf(1) } // 0: Variant A, 1: Variant B
    var isSwappedSuccess by remember { mutableStateOf(false) }

    val variants = listOf(
        Pair("Stop Using LangChain: Native Kotlin Agent Swarms", 7.8),
        Pair("I Tested AI Coding for 48 Hours: The Brutal Truth", 9.4)
    )

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = MaterialTheme.colorScheme.surface
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp, vertical = 10.dp)
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Header
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(36.dp)
                            .clip(CircleShape)
                            .background(StudioAmber.copy(alpha = 0.2f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(Icons.Default.Speed, contentDescription = null, tint = StudioAmber, modifier = Modifier.size(20.dp))
                    }
                    Spacer(Modifier.width(10.dp))
                    Column {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text("120-Min Launch Radar", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                            Spacer(Modifier.width(6.dp))
                            Surface(
                                shape = RoundedCornerShape(4.dp),
                                color = StudioCoral.copy(alpha = 0.15f)
                            ) {
                                Text("CTR ALERT", color = StudioCoral, fontWeight = FontWeight.Bold, fontSize = 9.sp, modifier = Modifier.padding(horizontal = 4.dp, vertical = 2.dp))
                            }
                        }
                        Text("Published 74m ago · Week 8 Video Check-in", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
                IconButton(onClick = onDismiss) {
                    Icon(Icons.Default.Close, contentDescription = "Close", modifier = Modifier.size(20.dp))
                }
            }

            // Real-Time Pacing Metrics vs Baseline
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)
            ) {
                Column(Modifier.padding(14.dp)) {
                    Text("LIVE METRICS VS 10.1K BASELINE", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = StudioViolet)
                    Spacer(Modifier.height(10.dp))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Column {
                            Text("Click-Through Rate", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            Text("4.7%", fontWeight = FontWeight.Bold, fontSize = 20.sp, color = StudioCoral)
                            Text("Baseline: 8.1%", fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }

                        Column {
                            Text("Velocity", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            Text("185 vph", fontWeight = FontWeight.Bold, fontSize = 20.sp, color = StudioAmber)
                            Text("Target: 420 vph", fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }

                        Column {
                            Text("Avg Duration", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            Text("4m 18s", fontWeight = FontWeight.Bold, fontSize = 20.sp, color = StudioEmerald)
                            Text("Retention: 45%", fontSize = 10.sp, color = StudioEmerald)
                        }
                    }
                }
            }

            // Diagnostic Diagnosis
            Surface(
                shape = RoundedCornerShape(10.dp),
                color = StudioCoral.copy(alpha = 0.1f),
                modifier = Modifier.fillMaxWidth()
            ) {
                Row(
                    modifier = Modifier.padding(12.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(Icons.Default.Warning, contentDescription = null, tint = StudioCoral, modifier = Modifier.size(20.dp))
                    Spacer(Modifier.width(10.dp))
                    Text(
                        "Diagnosis: High retention (45%) but weak initial CTR (4.7%). Viewers who click stay, but the title hook lacks curiosity tension. Swapping recommended immediately.",
                        fontSize = 11.sp,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                }
            }

            // Bandit Optimizer Candidates
            Text("Multi-Armed Bandit Title Candidates", fontWeight = FontWeight.Bold, fontSize = 13.sp)

            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                variants.forEachIndexed { index, (title, predictedCtr) ->
                    val isSelected = selectedVariant == index
                    Surface(
                        shape = RoundedCornerShape(10.dp),
                        color = if (isSelected) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surface,
                        border = androidx.compose.foundation.BorderStroke(
                            1.dp,
                            if (isSelected) StudioCoral else MaterialTheme.colorScheme.outline.copy(alpha = 0.5f)
                        ),
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .clickable { selectedVariant = index }
                    ) {
                        Row(
                            modifier = Modifier.padding(12.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Column(Modifier.weight(1f)) {
                                Text("Variant ${if (index == 0) "A" else "B"}", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = StudioViolet)
                                Text(title, fontWeight = FontWeight.SemiBold, fontSize = 12.sp)
                            }
                            Spacer(Modifier.width(8.dp))
                            Surface(
                                shape = RoundedCornerShape(6.dp),
                                color = StudioEmerald.copy(alpha = 0.15f)
                            ) {
                                Text(
                                    text = "Est. ${predictedCtr}%",
                                    color = StudioEmerald,
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 11.sp,
                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                )
                            }
                        }
                    }
                }
            }

            // Confirmation Alert
            if (isSwappedSuccess) {
                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = StudioEmerald.copy(alpha = 0.15f),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Row(
                        modifier = Modifier.padding(10.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Default.CheckCircle, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(8.dp))
                        Text(
                            "Live title updated on YouTube via API! Snapshot pacing resets in 15 minutes.",
                            color = StudioEmerald,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                    }
                }
            }

            // 1-Tap Emergency Swap Button
            Button(
                onClick = {
                    isSwappedSuccess = true
                    onTitleSwapped(variants[selectedVariant].first)
                },
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(10.dp),
                colors = ButtonDefaults.buttonColors(containerColor = StudioCoral)
            ) {
                Icon(Icons.Default.PublishedWithChanges, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(8.dp))
                Text(if (isSwappedSuccess) "Swapped on YouTube!" else "1-Tap Title Swap to YouTube API", fontWeight = FontWeight.Bold)
            }

            Spacer(Modifier.height(16.dp))
        }
    }
}

