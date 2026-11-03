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
import com.techunicorn.studio.ui.theme.StudioCoral
import com.techunicorn.studio.ui.theme.StudioEmerald
import com.techunicorn.studio.ui.theme.StudioViolet

data class CalendarEvent(
    val id: String,
    val dayOfWeek: String,
    val dateStr: String,
    val platform: String,
    val title: String,
    val format: String,
    val status: String // "scheduled", "draft", "published"
)

@Composable
fun CalendarScreen() {
    var selectedFilter by remember { mutableStateOf("All") }

    val events = remember {
        listOf(
            CalendarEvent("1", "Mon", "Nov 3 · 09:00", "LinkedIn", "Why Autonomous AI Swarms Beat Monolithic Agents", "Carousel", "scheduled"),
            CalendarEvent("2", "Mon", "Nov 3 · 14:30", "X", "Thread: 5 lessons after 48h of continuous vibecoding", "Thread (7 tweets)", "scheduled"),
            CalendarEvent("3", "Tue", "Nov 4 · 18:00", "YouTube", "I Replaced My Entire Engineering Team With AI Agents", "Full Video (14m)", "draft"),
            CalendarEvent("4", "Wed", "Nov 5 · 11:00", "Instagram", "Behind-the-scenes: Ex-Google creator workspace setup in Valencia", "Reel / 60s", "scheduled"),
            CalendarEvent("5", "Thu", "Nov 6 · 15:00", "TikTok", "How much money does a 470K tech channel actually make?", "Short", "scheduled"),
            CalendarEvent("6", "Fri", "Nov 7 · 10:00", "Threads", "Unpopular opinion: Most AI startups will be dead by 2027", "Post", "published"),
            CalendarEvent("7", "Sat", "Nov 8 · 12:00", "LinkedIn", "Weekend Reflection: Work-life balance as an ex-Googler nomad", "Text Post", "draft")
        )
    }

    val platforms = listOf("All", "YouTube", "LinkedIn", "X", "Instagram", "TikTok", "Threads")

    val filteredEvents = remember(selectedFilter) {
        if (selectedFilter == "All") events else events.filter { it.platform == selectedFilter }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .padding(16.dp)
    ) {
        // Page Title & Header
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text(
                    "Content Calendar",
                    fontSize = 20.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onBackground
                )
                Text(
                    "Every post scheduled across all networks",
                    fontSize = 12.sp,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }

            Button(
                onClick = {},
                colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.primary),
                contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp)
            ) {
                Icon(Icons.Default.Add, contentDescription = null, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(4.dp))
                Text("Schedule Post", fontSize = 12.sp, fontWeight = FontWeight.Bold)
            }
        }

        Spacer(Modifier.height(14.dp))

        // Platform Filter Chips
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            platforms.take(4).forEach { plat ->
                val isSelected = selectedFilter == plat
                Surface(
                    shape = RoundedCornerShape(20.dp),
                    color = if (isSelected) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surface,
                    modifier = Modifier
                        .clip(RoundedCornerShape(20.dp))
                        .border(
                            1.dp,
                            if (isSelected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outline,
                            RoundedCornerShape(20.dp)
                        )
                        .clickable { selectedFilter = plat }
                ) {
                    Text(
                        plat,
                        fontSize = 11.sp,
                        fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                        color = if (isSelected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                    )
                }
            }
        }

        Spacer(Modifier.height(16.dp))

        // Week Overview Schedule List
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            items(filteredEvents) { event ->
                val statusColor = when (event.status) {
                    "published" -> StudioEmerald
                    "scheduled" -> StudioViolet
                    else -> StudioCoral
                }

                Surface(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    color = MaterialTheme.colorScheme.surface,
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
                    tonalElevation = 2.dp
                ) {
                    Row(
                        modifier = Modifier.padding(14.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        // Date column
                        Column(
                            horizontalAlignment = Alignment.CenterHorizontally,
                            modifier = Modifier
                                .width(56.dp)
                                .clip(RoundedCornerShape(8.dp))
                                .background(MaterialTheme.colorScheme.surfaceVariant)
                                .padding(vertical = 8.dp)
                        ) {
                            Text(
                                event.dayOfWeek.uppercase(),
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold,
                                color = MaterialTheme.colorScheme.primary
                            )
                            Text(
                                event.dateStr.split(" ")[1],
                                fontSize = 14.sp,
                                fontWeight = FontWeight.ExtraBold,
                                color = MaterialTheme.colorScheme.onSurface
                            )
                        }

                        Spacer(Modifier.width(14.dp))

                        Column(modifier = Modifier.weight(1f)) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text(
                                    event.platform,
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = MaterialTheme.colorScheme.primary
                                )

                                Surface(
                                    shape = RoundedCornerShape(6.dp),
                                    color = statusColor.copy(alpha = 0.15f)
                                ) {
                                    Text(
                                        event.status.uppercase(),
                                        fontSize = 9.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = statusColor,
                                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                    )
                                }
                            }

                            Spacer(Modifier.height(4.dp))

                            Text(
                                event.title,
                                fontSize = 13.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = MaterialTheme.colorScheme.onSurface,
                                maxLines = 2
                            )

                            Spacer(Modifier.height(4.dp))

                            Text(
                                "${event.format} · ${event.dateStr}",
                                fontSize = 11.sp,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                }
            }
        }
    }
}
