package com.techunicorn.studio.ui.screens.chat

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AutoAwesome
import androidx.compose.material.icons.filled.Send
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.ui.theme.StudioViolet

data class ProducerChatMessage(
    val id: String,
    val sender: String, // "producer" or "delia"
    val text: String,
    val timeAgo: String
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProducerChatScreen() {
    var inputQuery by remember { mutableStateOf("") }

    var chatHistory by remember {
        mutableStateOf(
            listOf(
                ProducerChatMessage(
                    id = "1",
                    sender = "producer",
                    text = "Hey Delia! I've analyzed your recent breakout on Next.js 15 (4.37x median). For this week's video, what topic do you want to bounce around?",
                    timeAgo = "10:14 AM"
                ),
                ProducerChatMessage(
                    id = "2",
                    sender = "delia",
                    text = "I'm torn between 'Building a Full App with AI Agents in 48 Hours' versus 'Why 90% of AI Startups Will Die'. What does the retention curve predict?",
                    timeAgo = "10:16 AM"
                ),
                ProducerChatMessage(
                    id = "3",
                    sender = "producer",
                    text = "The '48h AI Agent Swarm' has 3x higher CTR potential based on recent dev trends. If we anchor the hook on: 'I tested 48 hours of autonomous coding—here is where the code broke', that creates an immediate high-stakes curiosity gap without sounding clickbaity.",
                    timeAgo = "10:17 AM"
                )
            )
        )
    }

    val quickTopics = listOf(
        "Critique hook pacing",
        "Brainstorm thumbnail ideas",
        "Title A/B test ideas",
        "Audit sponsor segment"
    )

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
    ) {
        // Chat Header
        Surface(
            modifier = Modifier.fillMaxWidth(),
            color = MaterialTheme.colorScheme.surface,
            tonalElevation = 2.dp,
            border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline)
        ) {
            Row(
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Box(
                    modifier = Modifier
                        .size(36.dp)
                        .clip(CircleShape)
                        .background(StudioViolet),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        Icons.Default.AutoAwesome,
                        contentDescription = null,
                        tint = Color.White,
                        modifier = Modifier.size(18.dp)
                    )
                }
                Spacer(Modifier.width(12.dp))
                Column {
                    Text(
                        "Executive Producer AI",
                        fontWeight = FontWeight.Bold,
                        fontSize = 14.sp,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Text(
                        "Thinking partner · Retention, packaging & strategy",
                        fontSize = 11.sp,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
        }

        // Quick Topic Chips
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .horizontalScroll(rememberScrollState())
                .padding(horizontal = 14.dp, vertical = 10.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            quickTopics.forEach { topic ->
                Surface(
                    shape = RoundedCornerShape(16.dp),
                    color = MaterialTheme.colorScheme.surfaceVariant,
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
                    modifier = Modifier
                        .clip(RoundedCornerShape(16.dp))
                        .clickable {
                            inputQuery = topic
                        }
                ) {
                    Text(
                        topic,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Medium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                    )
                }
            }
        }

        // Messages List
        LazyColumn(
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .padding(horizontal = 16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            items(chatHistory) { msg ->
                val isProducer = msg.sender == "producer"
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = if (isProducer) Arrangement.Start else Arrangement.End
                ) {
                    Surface(
                        shape = RoundedCornerShape(
                            topStart = 14.dp,
                            topEnd = 14.dp,
                            bottomStart = if (isProducer) 2.dp else 14.dp,
                            bottomEnd = if (isProducer) 14.dp else 2.dp
                        ),
                        color = if (isProducer) MaterialTheme.colorScheme.surfaceVariant else MaterialTheme.colorScheme.primary,
                        modifier = Modifier.widthIn(max = 300.dp)
                    ) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Text(
                                msg.text,
                                fontSize = 13.sp,
                                color = if (isProducer) MaterialTheme.colorScheme.onSurface else Color.White,
                                lineHeight = 18.sp
                            )
                            Spacer(Modifier.height(4.dp))
                            Text(
                                msg.timeAgo,
                                fontSize = 10.sp,
                                color = if (isProducer) MaterialTheme.colorScheme.onSurfaceVariant else Color.White.copy(
                                    alpha = 0.7f
                                ),
                                modifier = Modifier.align(Alignment.End)
                            )
                        }
                    }
                }
            }
        }

        // Input Field
        Surface(
            modifier = Modifier.fillMaxWidth(),
            color = MaterialTheme.colorScheme.surface,
            tonalElevation = 6.dp,
            border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline)
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 12.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                OutlinedTextField(
                    value = inputQuery,
                    onValueChange = { inputQuery = it },
                    placeholder = { Text("Bounce an idea with your producer…", fontSize = 13.sp) },
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(20.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedContainerColor = MaterialTheme.colorScheme.surfaceVariant,
                        unfocusedContainerColor = MaterialTheme.colorScheme.surfaceVariant
                    ),
                    maxLines = 3
                )
                Spacer(Modifier.width(8.dp))
                IconButton(
                    onClick = {
                        if (inputQuery.isNotBlank()) {
                            val newText = inputQuery
                            inputQuery = ""
                            chatHistory = chatHistory + ProducerChatMessage(
                                id = (chatHistory.size + 1).toString(),
                                sender = "delia",
                                text = newText,
                                timeAgo = "Just now"
                            ) + ProducerChatMessage(
                                id = (chatHistory.size + 2).toString(),
                                sender = "producer",
                                text = "Evaluating \"$newText\" against YouTube viewer median benchmarks... That's a great angle! Let's weave in the visual demo early around the 2-minute mark.",
                                timeAgo = "Just now"
                            )
                        }
                    },
                    modifier = Modifier
                        .size(42.dp)
                        .clip(CircleShape)
                        .background(MaterialTheme.colorScheme.primary)
                ) {
                    Icon(
                        Icons.Default.Send,
                        contentDescription = "Send",
                        tint = Color.White,
                        modifier = Modifier.size(18.dp)
                    )
                }
            }
        }
    }
}
