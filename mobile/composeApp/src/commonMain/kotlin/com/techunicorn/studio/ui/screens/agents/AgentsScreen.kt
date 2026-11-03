package com.techunicorn.studio.ui.screens.agents

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
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
import com.techunicorn.studio.domain.models.AgentSwarmMission
import com.techunicorn.studio.ui.theme.*

@Composable
fun AgentsScreen() {
    var missions by remember {
        mutableStateOf(
            listOf(
                AgentSwarmMission(
                    id = 1,
                    name = "Retention & Pacing Audit",
                    agentName = "ScriptRetentionAuditor",
                    goal = "Scan last 5 video drafts for lull points, passive sentences and pacing dips.",
                    status = "running",
                    stepsCompleted = 3,
                    totalSteps = 5,
                    latestLog = "Auditing 04:30 dip in 'Building Production App in 48h'..."
                ),
                AgentSwarmMission(
                    id = 2,
                    name = "Sponsor Value Maximizer",
                    agentName = "SponsorNegotiator",
                    goal = "Synthesize Q4 rate card vs market CPMs for Supabase & Cursor deals.",
                    status = "completed",
                    stepsCompleted = 4,
                    totalSteps = 4,
                    latestLog = "Generated 3 tiered pricing proposals with +18% CPM uplift."
                ),
                AgentSwarmMission(
                    id = 3,
                    name = "Evergreen Content Recycler",
                    agentName = "EvergreenRecycler",
                    goal = "Repurpose top performing 2025 AI engineering video into modern carousel.",
                    status = "running",
                    stepsCompleted = 1,
                    totalSteps = 3,
                    latestLog = "Extracting key diagrams from video transcript..."
                )
            )
        )
    }

    var showNewMissionDialog by remember { mutableStateOf(false) }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .padding(16.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text("Autonomous Swarms", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
                Text("Background multi-agent orchestration & evals", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            Button(
                onClick = { showNewMissionDialog = true },
                colors = ButtonDefaults.buttonColors(containerColor = StudioViolet),
                contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp)
            ) {
                Icon(Icons.Default.PlayArrow, contentDescription = null, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(4.dp))
                Text("New Mission", fontSize = 12.sp, fontWeight = FontWeight.Bold)
            }
        }

        Spacer(Modifier.height(16.dp))

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            items(missions) { mission ->
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
                            Text(mission.name, fontWeight = FontWeight.Bold, fontSize = 15.sp)
                            Box(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(6.dp))
                                    .background(
                                        if (mission.status == "running") StudioOcean.copy(alpha = 0.15f)
                                        else StudioEmerald.copy(alpha = 0.15f)
                                    )
                                    .padding(horizontal = 8.dp, vertical = 4.dp)
                            ) {
                                Text(
                                    text = mission.status.uppercase(),
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 10.sp,
                                    color = if (mission.status == "running") StudioOcean else StudioEmerald
                                )
                            }
                        }

                        Text("Agent: ${mission.agentName}", fontSize = 12.sp, color = StudioViolet, fontWeight = FontWeight.SemiBold)
                        Spacer(Modifier.height(6.dp))
                        Text(mission.goal, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

                        Spacer(Modifier.height(10.dp))
                        LinearProgressIndicator(
                            progress = { mission.stepsCompleted.toFloat() / mission.totalSteps.toFloat() },
                            modifier = Modifier.fillMaxWidth().height(6.dp).clip(RoundedCornerShape(3.dp)),
                            color = StudioViolet
                        )

                        Spacer(Modifier.height(8.dp))
                        Text(
                            text = "Log: ${mission.latestLog}",
                            fontSize = 11.sp,
                            fontStyle = androidx.compose.ui.text.font.FontStyle.Italic,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }
        }
    }

    if (showNewMissionDialog) {
        val agentOptions = listOf(
            Triple("ScriptRetentionAuditor", "Hook & Lull Analyzer", "Deep scan of cold open and visual cadence to protect APV >50%."),
            Triple("SponsorNegotiator", "Q4 Brand Rate Optimizer", "Cross-references CPMs across technical creators to maximize deal room retainers."),
            Triple("AlgorithmShield", "Baseline Velocity Sentinel", "Simulates first-48-hour velocity vs Delia's 10.1k median baseline.")
        )

        AlertDialog(
            onDismissRequest = { showNewMissionDialog = false },
            icon = { Icon(Icons.Default.SmartToy, contentDescription = null, tint = StudioViolet, modifier = Modifier.size(36.dp)) },
            title = { Text("Launch Agent Mission", fontWeight = FontWeight.Bold) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("Select an autonomous agent routine from the studio library:", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    for (item in agentOptions) {
                        val (agent, name, desc) = item
                        Surface(
                            shape = RoundedCornerShape(8.dp),
                            color = MaterialTheme.colorScheme.surfaceVariant,
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Column(Modifier.padding(10.dp)) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text(name, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                                    Button(
                                        onClick = {
                                            val newMission = AgentSwarmMission(
                                                id = kotlinx.datetime.Clock.System.now().toEpochMilliseconds(),
                                                name = name,
                                                agentName = agent,
                                                goal = desc,
                                                status = "running",
                                                stepsCompleted = 1,
                                                totalSteps = 4,
                                                latestLog = "Initializing $agent context and loading historical metrics..."
                                            )
                                            missions = listOf(newMission) + missions
                                            showNewMissionDialog = false
                                        },
                                        colors = ButtonDefaults.buttonColors(containerColor = StudioViolet),
                                        contentPadding = PaddingValues(horizontal = 10.dp, vertical = 4.dp)
                                    ) {
                                        Text("Run", fontSize = 11.sp)
                                    }
                                }
                                Text("Agent: $agent", fontSize = 10.sp, color = StudioViolet)
                                Spacer(Modifier.height(2.dp))
                                Text(desc, fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                        }
                    }
                }
            },
            confirmButton = {},
            dismissButton = {
                TextButton(onClick = { showNewMissionDialog = false }) { Text("Cancel") }
            }
        )
    }
}
