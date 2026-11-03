package com.techunicorn.studio.ui.screens.social

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.models.SocialPost
import com.techunicorn.studio.ui.theme.*

@Composable
fun SocialScreen(
    posts: List<SocialPost>
) {
    var selectedPlatform by remember { mutableStateOf("all") }
    var localPosts by remember { mutableStateOf(posts) }
    var showRepurposeDialog by remember { mutableStateOf(false) }

    val filteredPosts = if (selectedPlatform == "all") localPosts else localPosts.filter { it.platform == selectedPlatform }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
    ) {
        // Platform Filter Chips
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 10.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            FilterChip(
                selected = selectedPlatform == "all",
                onClick = { selectedPlatform = "all" },
                label = { Text("All (${localPosts.size})", fontSize = 11.sp) }
            )
            FilterChip(
                selected = selectedPlatform == "linkedin",
                onClick = { selectedPlatform = "linkedin" },
                label = { Text("LinkedIn", fontSize = 11.sp) }
            )
            FilterChip(
                selected = selectedPlatform == "x",
                onClick = { selectedPlatform = "x" },
                label = { Text("X / Twitter", fontSize = 11.sp) }
            )
            FilterChip(
                selected = selectedPlatform == "substack",
                onClick = { selectedPlatform = "substack" },
                label = { Text("Substack", fontSize = 11.sp) }
            )
            FilterChip(
                selected = selectedPlatform == "instagram",
                onClick = { selectedPlatform = "instagram" },
                label = { Text("Shorts / IG", fontSize = 11.sp) }
            )
        }

        LazyColumn(
            modifier = Modifier.fillMaxSize().padding(horizontal = 16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            item {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text("Repurposing Hub", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                        Text("One YouTube video -> native posts everywhere", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    Button(
                        onClick = { showRepurposeDialog = true },
                        colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp)
                    ) {
                        Icon(Icons.Default.AutoFixHigh, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(4.dp))
                        Text("Repurpose", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }

            items(filteredPosts) { post ->
                val platformColor = when (post.platform) {
                    "linkedin" -> StudioOcean
                    "x" -> StudioTextPrimaryLight
                    "instagram" -> StudioCoral
                    "substack" -> StudioAmber
                    else -> StudioViolet
                }

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
                            Text(
                                text = post.platform.uppercase(),
                                fontWeight = FontWeight.Bold,
                                fontSize = 11.sp,
                                color = platformColor
                            )
                            Box(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(6.dp))
                                    .background(MaterialTheme.colorScheme.surfaceVariant)
                                    .padding(horizontal = 6.dp, vertical = 2.dp)
                            ) {
                                Text(
                                    text = post.status.uppercase(),
                                    fontSize = 10.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = if (post.status == "scheduled") StudioEmerald else MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        }

                        Spacer(Modifier.height(8.dp))
                        Text(post.content, style = MaterialTheme.typography.bodyMedium)

                        Spacer(Modifier.height(10.dp))
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = "${post.characterCount} chars",
                                fontSize = 11.sp,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                            if (post.scheduledFor != null) {
                                Text(
                                    text = "Scheduled: ${post.scheduledFor}",
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = StudioEmerald
                                )
                            }
                        }

                        // 1-Tap Mobile Dispatch Action
                        Spacer(Modifier.height(10.dp))
                        val clipboard = LocalClipboardManager.current
                        var isCopied by remember { mutableStateOf(false) }
                        Button(
                            onClick = {
                                clipboard.setText(AnnotatedString(post.content))
                                isCopied = true
                            },
                            modifier = Modifier.fillMaxWidth(),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = if (isCopied) StudioEmerald else platformColor
                            ),
                            contentPadding = PaddingValues(vertical = 8.dp),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Icon(
                                if (isCopied) Icons.Default.Check else Icons.AutoMirrored.Filled.Send,
                                contentDescription = null,
                                modifier = Modifier.size(15.dp)
                            )
                            Spacer(Modifier.width(6.dp))
                            Text(
                                if (isCopied) "Copied to Clipboard!"
                                else "1-Tap Copy & Schedule for ${post.platform.uppercase()}",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold
                            )
                        }
                    }
                }
            }
        }
    }

    // Repurposing Arbitrage Modal
    if (showRepurposeDialog) {
        RepurposeArbitrageDialog(
            onDismiss = { showRepurposeDialog = false },
            onApplyRepurpose = { newPosts ->
                localPosts = newPosts + localPosts
                showRepurposeDialog = false
            }
        )
    }
}

@Composable
fun RepurposeArbitrageDialog(
    onDismiss: () -> Unit,
    onApplyRepurpose: (List<SocialPost>) -> Unit
) {
    var includeLinkedIn by remember { mutableStateOf(true) }
    var includeX by remember { mutableStateOf(true) }
    var includeSubstack by remember { mutableStateOf(true) }
    var includeShorts by remember { mutableStateOf(true) }

    AlertDialog(
        onDismissRequest = onDismiss,
        icon = { Icon(Icons.Default.Share, contentDescription = null, tint = StudioCoral, modifier = Modifier.size(36.dp)) },
        title = { Text("Omnichannel Repurposing Arbitrage", fontWeight = FontWeight.Bold, fontSize = 17.sp) },
        text = {
            Column(
                verticalArrangement = Arrangement.spacedBy(10.dp),
                modifier = Modifier.verticalScroll(rememberScrollState())
            ) {
                Text(
                    "Source Video: \"Building a Production App in 48h with AI\"",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = StudioViolet
                )
                Text(
                    "Select networks to automatically cascade into native platform formats:",
                    fontSize = 12.sp,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )

                // Network options
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Checkbox(checked = includeLinkedIn, onCheckedChange = { includeLinkedIn = it })
                    Spacer(Modifier.width(4.dp))
                    Column {
                        Text("LinkedIn Post (Professional)", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                        Text("3 architecture takeaways + ex-Google metrics", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Checkbox(checked = includeX, onCheckedChange = { includeX = it })
                    Spacer(Modifier.width(4.dp))
                    Column {
                        Text("X / Twitter Thread (8 Tweets)", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                        Text("Fast narrative hook with terminal code card embeds", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Checkbox(checked = includeSubstack, onCheckedChange = { includeSubstack = it })
                    Spacer(Modifier.width(4.dp))
                    Column {
                        Text("Substack Newsletter (Long-Form)", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                        Text("Deep-dive engineering essay from prompts/04_substack.md", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Checkbox(checked = includeShorts, onCheckedChange = { includeShorts = it })
                    Spacer(Modifier.width(4.dp))
                    Column {
                        Text("3x Vertical Shorts (9:16)", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                        Text("Extracted high-retention 60-second viral hooks", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    val generated = mutableListOf<SocialPost>()
                    val nowMs = kotlinx.datetime.Clock.System.now().toEpochMilliseconds()

                    if (includeLinkedIn) {
                        generated.add(
                            SocialPost(
                                id = nowMs + 1,
                                platform = "linkedin",
                                content = "Everyone says AI can build a production startup in 48 hours.\n\nAs an ex-Google systems engineer, I actually tested the limits on live traffic.\n\nHere are 3 production realities most tutorials skip:\n1. Prompt versioning matters more than model size.\n2. Without deterministic guardrails, multi-step agents fail on edge-case state reconciliation.\n3. Native type safety (Kotlin + SQLite) eliminated 80% of our production bugs.\n\nFull architecture breakdown in the video linked in comments 👇",
                                scheduledFor = "Monday at 09:15 CET",
                                status = "scheduled",
                                characterCount = 492
                            )
                        )
                    }

                    if (includeX) {
                        generated.add(
                            SocialPost(
                                id = nowMs + 2,
                                platform = "x",
                                content = "I spent 48 hours testing whether AI agents can write production software without human intervention.\n\nTL;DR: Scaffolding is 10x faster. Production edge cases are 2x harder.\n\nHere is what broke (and the exact architecture that survived): 🧵👇",
                                scheduledFor = "Monday at 17:30 CET",
                                status = "scheduled",
                                characterCount = 248
                            )
                        )
                    }

                    if (includeSubstack) {
                        generated.add(
                            SocialPost(
                                id = nowMs + 3,
                                platform = "substack",
                                content = "# Tech Unicorn #42: The 48-Hour Production AI Experiment\n\nMost tutorials demonstrate toy demos with 3 files. When you introduce database migrations, Stripe webhooks, and background queues, AI begins to hallucinate.\n\nIn this issue, we examine the exact benchmark data from our live deployment.",
                                scheduledFor = "Wednesday at 10:00 CET",
                                status = "scheduled",
                                characterCount = 338
                            )
                        )
                    }

                    if (includeShorts) {
                        generated.add(
                            SocialPost(
                                id = nowMs + 4,
                                platform = "instagram",
                                content = "The #1 mistake engineers make when building AI agent swarms ⚠️ (Clip from Sunday's deep-dive)",
                                scheduledFor = "Tuesday at 18:00 CET",
                                status = "scheduled",
                                characterCount = 104
                            )
                        )
                    }

                    onApplyRepurpose(generated)
                },
                colors = ButtonDefaults.buttonColors(containerColor = StudioCoral)
            ) {
                Icon(Icons.Default.Bolt, contentDescription = null, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(6.dp))
                Text("Cascade Omnichannel Schedule")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Cancel") }
        }
    )
}
