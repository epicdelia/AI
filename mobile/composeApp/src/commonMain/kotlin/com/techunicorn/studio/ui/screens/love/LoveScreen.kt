package com.techunicorn.studio.ui.screens.love

import androidx.compose.animation.*
import androidx.compose.foundation.background
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
import com.techunicorn.studio.domain.models.LoveLetter
import com.techunicorn.studio.domain.models.MemoryJarPrompt
import com.techunicorn.studio.ui.theme.*

@Composable
fun LoveScreen(
    letters: List<LoveLetter>,
    memoryJar: List<MemoryJarPrompt>
) {
    var selectedTab by remember { mutableStateOf(0) } // 0: Letters, 1: Memory Jar
    var selectedLetter by remember { mutableStateOf<LoveLetter?>(null) }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
    ) {
        // Header
        Row(
            modifier = Modifier.fillMaxWidth().padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(40.dp)
                    .clip(CircleShape)
                    .background(StudioCoral.copy(alpha = 0.2f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(Icons.Default.Favorite, contentDescription = null, tint = StudioCoral)
            }
            Spacer(Modifier.width(12.dp))
            Column {
                Text("For You", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                Text("Letters, memories, and little moments", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }

        TabRow(
            selectedTabIndex = selectedTab,
            containerColor = MaterialTheme.colorScheme.surface,
            contentColor = StudioCoral
        ) {
            Tab(selected = selectedTab == 0, onClick = { selectedTab = 0 }, text = { Text("Love Letters (${letters.size})") })
            Tab(selected = selectedTab == 1, onClick = { selectedTab = 1 }, text = { Text("Memory Jar") })
        }

        when (selectedTab) {
            0 -> LettersTab(letters, onOpenLetter = { selectedLetter = it })
            1 -> MemoryJarTab(memoryJar)
        }
    }

    if (selectedLetter != null) {
        val letter = selectedLetter!!
        AlertDialog(
            onDismissRequest = { selectedLetter = null },
            icon = { Icon(Icons.Default.Favorite, contentDescription = null, tint = StudioCoral) },
            title = { Text(letter.title, fontWeight = FontWeight.Bold) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(letter.date, fontSize = 11.sp, color = StudioViolet, fontWeight = FontWeight.Bold)
                    Text(letter.body, style = MaterialTheme.typography.bodyLarge)
                }
            },
            confirmButton = {
                Button(
                    onClick = { selectedLetter = null },
                    colors = ButtonDefaults.buttonColors(containerColor = StudioCoral)
                ) {
                    Text("Close")
                }
            }
        )
    }
}

@Composable
fun LettersTab(
    letters: List<LoveLetter>,
    onOpenLetter: (LoveLetter) -> Unit
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        items(letters) { letter ->
            Card(
                onClick = { onOpenLetter(letter) },
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(14.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(Modifier.padding(16.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(letter.date, fontSize = 11.sp, color = StudioCoral, fontWeight = FontWeight.Bold)
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(6.dp))
                                .background(if (letter.isOpened) StudioEmerald.copy(alpha = 0.15f) else StudioViolet.copy(alpha = 0.15f))
                                .padding(horizontal = 6.dp, vertical = 2.dp)
                        ) {
                            Text(if (letter.isOpened) "READ" else "SEALED 💌", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = if (letter.isOpened) StudioEmerald else StudioViolet)
                        }
                    }
                    Spacer(Modifier.height(6.dp))
                    Text(letter.title, fontWeight = FontWeight.Bold, fontSize = 15.sp)
                    Spacer(Modifier.height(4.dp))
                    Text(letter.excerpt, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
    }
}

@Composable
fun MemoryJarTab(prompts: List<MemoryJarPrompt>) {
    LazyColumn(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        item {
            Text("The Memory Jar", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            Text("Questions to remember our favorite moments", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }

        items(prompts) { prompt ->
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(14.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(Modifier.padding(16.dp)) {
                    Text(prompt.question, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                    Spacer(Modifier.height(8.dp))
                    if (prompt.answer != null) {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(8.dp))
                                .background(MaterialTheme.colorScheme.surfaceVariant)
                                .padding(10.dp)
                        ) {
                            Column {
                                Text(prompt.answer, fontSize = 13.sp)
                                if (prompt.dateAnswered != null) {
                                    Spacer(Modifier.height(4.dp))
                                    Text("Answered on ${prompt.dateAnswered}", fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                            }
                        }
                    } else {
                        Button(
                            onClick = {},
                            colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                            contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp)
                        ) {
                            Text("Answer this prompt", fontSize = 12.sp)
                        }
                    }
                }
            }
        }
    }
}
