package com.techunicorn.studio.ui.screens.social

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AutoAwesome
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Send
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

data class InboxMessage(
    val id: String,
    val author: String,
    val handle: String,
    val platform: String,
    val timeAgo: String,
    val content: String,
    val suggestedReply: String,
    var repliedText: String? = null
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun InboxScreen() {
    var messages by remember {
        mutableStateOf(
            listOf(
                InboxMessage(
                    id = "1",
                    author = "Marc Lou",
                    handle = "@marclou",
                    platform = "X / Twitter",
                    timeAgo = "18m ago",
                    content = "How are you handling offline state sync between Kotlin Multiplatform and the Laravel backend on mobile? Loving the architecture so far.",
                    suggestedReply = "We use SQLDelight locally on mobile with transactional mutation logs that re-sync via Ktor WebSockets whenever connection restores!"
                ),
                InboxMessage(
                    id = "2",
                    author = "Elena Vance",
                    handle = "@elenav",
                    platform = "YouTube",
                    timeAgo = "1h ago",
                    content = "Video is insane Delia! At 04:20 when you showed the multi-agent deliberation log, what LLM were you running for the auditor agent?",
                    suggestedReply = "We use Claude 3.5 Sonnet for the script auditor and Gemini 1.5 Pro for video pacing analysis. Works like magic!"
                ),
                InboxMessage(
                    id = "3",
                    author = "David Sacks",
                    handle = "Partner @ Craft Ventures",
                    platform = "LinkedIn",
                    timeAgo = "3h ago",
                    content = "Saw your keynote on AI MVP velocity in Berlin. Are you taking on board advisory or speaking roles in Q1 2027?",
                    suggestedReply = "Hi David! Yes, I have two advisory slots open for Q1. Let's connect over email or book directly via techunicorn.ai/website."
                )
            )
        )
    }

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
                Text(
                    "Unified Inbox",
                    fontSize = 20.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onBackground
                )
                Text(
                    "Comments and DMs across all networks with AI replies",
                    fontSize = 12.sp,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }

            Surface(
                shape = RoundedCornerShape(12.dp),
                color = MaterialTheme.colorScheme.primaryContainer
            ) {
                Text(
                    "${messages.count { it.repliedText == null }} to answer",
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp)
                )
            }
        }

        Spacer(Modifier.height(14.dp))

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            items(messages) { msg ->
                val isAnswered = msg.repliedText != null

                Surface(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(14.dp),
                    color = MaterialTheme.colorScheme.surface,
                    border = BorderStroke(
                        1.dp,
                        if (isAnswered) MaterialTheme.colorScheme.outline
                        else MaterialTheme.colorScheme.primary.copy(alpha = 0.4f)
                    ),
                    tonalElevation = 2.dp
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Box(
                                    modifier = Modifier
                                        .size(32.dp)
                                        .clip(CircleShape)
                                        .background(MaterialTheme.colorScheme.primaryContainer),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Text(
                                        msg.author.first().toString(),
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 13.sp,
                                        color = MaterialTheme.colorScheme.primary
                                    )
                                }
                                Spacer(Modifier.width(10.dp))
                                Column {
                                    Text(msg.author, fontWeight = FontWeight.Bold, fontSize = 13.sp, color = MaterialTheme.colorScheme.onSurface)
                                    Text("${msg.handle} · ${msg.platform}", fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                            }

                            Text(msg.timeAgo, fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }

                        Spacer(Modifier.height(10.dp))

                        Text(
                            msg.content,
                            fontSize = 13.sp,
                            color = MaterialTheme.colorScheme.onSurface,
                            lineHeight = 18.sp
                        )

                        Spacer(Modifier.height(12.dp))

                        if (isAnswered) {
                            Surface(
                                shape = RoundedCornerShape(10.dp),
                                color = MaterialTheme.colorScheme.surfaceVariant,
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Row(
                                    modifier = Modifier.padding(10.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Icon(Icons.Default.Check, contentDescription = null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(16.dp))
                                    Spacer(Modifier.width(8.dp))
                                    Text(
                                        "Replied: \"${msg.repliedText}\"",
                                        fontSize = 11.sp,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                }
                            }
                        } else {
                            // AI Suggested Reply Pill
                            Surface(
                                shape = RoundedCornerShape(10.dp),
                                color = MaterialTheme.colorScheme.secondaryContainer.copy(alpha = 0.5f),
                                border = BorderStroke(1.dp, MaterialTheme.colorScheme.secondary.copy(alpha = 0.3f)),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(modifier = Modifier.padding(10.dp)) {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Icon(Icons.Default.AutoAwesome, contentDescription = null, tint = MaterialTheme.colorScheme.secondary, modifier = Modifier.size(14.dp))
                                        Spacer(Modifier.width(6.dp))
                                        Text(
                                            "AI Suggested Reply",
                                            fontSize = 10.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = MaterialTheme.colorScheme.secondary
                                        )
                                    }
                                    Spacer(Modifier.height(4.dp))
                                    Text(
                                        msg.suggestedReply,
                                        fontSize = 12.sp,
                                        color = MaterialTheme.colorScheme.onSurface,
                                        lineHeight = 16.sp
                                    )
                                    Spacer(Modifier.height(8.dp))
                                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                        Button(
                                            onClick = {
                                                messages = messages.map { if (it.id == msg.id) it.copy(repliedText = msg.suggestedReply) else it }
                                            },
                                            colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.primary),
                                            contentPadding = PaddingValues(horizontal = 12.dp, vertical = 4.dp),
                                            modifier = Modifier.height(30.dp)
                                        ) {
                                            Icon(Icons.Default.Send, contentDescription = null, modifier = Modifier.size(12.dp))
                                            Spacer(Modifier.width(4.dp))
                                            Text("Send Reply", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
