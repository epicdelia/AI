package com.techunicorn.studio.ui.screens.social

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
import com.techunicorn.studio.domain.models.SocialPost
import com.techunicorn.studio.ui.theme.*

@Composable
fun SocialScreen(
    posts: List<SocialPost>
) {
    var selectedPlatform by remember { mutableStateOf("all") }

    val filteredPosts = if (selectedPlatform == "all") posts else posts.filter { it.platform == selectedPlatform }

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
                label = { Text("All", fontSize = 11.sp) }
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
                selected = selectedPlatform == "instagram",
                onClick = { selectedPlatform = "instagram" },
                label = { Text("Instagram", fontSize = 11.sp) }
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
                        onClick = {},
                        colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp)
                    ) {
                        Icon(Icons.Default.AutoFixHigh, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(4.dp))
                        Text("Repurpose", fontSize = 12.sp)
                    }
                }
            }

            items(filteredPosts) { post ->
                val platformColor = when (post.platform) {
                    "linkedin" -> StudioOcean
                    "x" -> StudioTextPrimaryLight
                    "instagram" -> StudioCoral
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
                        var isCopied by remember { mutableStateOf(false) }
                        Button(
                            onClick = { isCopied = true },
                            modifier = Modifier.fillMaxWidth(),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = if (isCopied) StudioEmerald else platformColor
                            ),
                            contentPadding = PaddingValues(vertical = 8.dp),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Icon(
                                if (isCopied) Icons.Default.Check else Icons.Default.Send,
                                contentDescription = null,
                                modifier = Modifier.size(15.dp)
                            )
                            Spacer(Modifier.width(6.dp))
                            Text(
                                if (isCopied) "Copied! Opening ${post.platform.uppercase()}..."
                                else "1-Tap Copy & Open ${post.platform.uppercase()}",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold
                            )
                        }
                    }
                }
            }
        }
    }
}

