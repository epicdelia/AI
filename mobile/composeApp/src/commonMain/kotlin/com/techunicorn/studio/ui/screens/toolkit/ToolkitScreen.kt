package com.techunicorn.studio.ui.screens.toolkit

import androidx.compose.animation.*
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.models.ToolkitTemplate
import com.techunicorn.studio.ui.theme.*

@Composable
fun ToolkitScreen(
    templates: List<ToolkitTemplate>
) {
    var selectedTemplate by remember { mutableStateOf<ToolkitTemplate?>(null) }
    var isPlayingAudio by remember { mutableStateOf(false) }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .padding(16.dp)
    ) {
        Text("Creative Toolkit", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
        Text("AI templates, TTS narration, and thumbnail reviewers", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

        Spacer(Modifier.height(16.dp))

        // TTS Read-Aloud Card
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(14.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)
        ) {
            Row(
                modifier = Modifier.padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column(Modifier.weight(1f)) {
                    Text("TTS Script Read-Aloud", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                    Text("Listen to your script pacing in Delia's cloned voice", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                IconButton(
                    onClick = { isPlayingAudio = !isPlayingAudio },
                    colors = IconButtonDefaults.iconButtonColors(containerColor = StudioCoral)
                ) {
                    Icon(
                        imageVector = if (isPlayingAudio) Icons.Default.Pause else Icons.Default.PlayArrow,
                        contentDescription = "Play Narration",
                        tint = androidx.compose.ui.graphics.Color.White
                    )
                }
            }
        }

        Spacer(Modifier.height(16.dp))
        Text("AI Writing Templates", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
        Spacer(Modifier.height(8.dp))

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            items(templates) { template ->
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
                            Text(template.title, fontWeight = FontWeight.Bold, fontSize = 15.sp)
                            Box(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(6.dp))
                                    .background(StudioViolet.copy(alpha = 0.15f))
                                    .padding(horizontal = 6.dp, vertical = 2.dp)
                            ) {
                                Text(template.category.uppercase(), color = StudioViolet, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                            }
                        }
                        Spacer(Modifier.height(4.dp))
                        Text(template.description, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

                        Spacer(Modifier.height(10.dp))
                        Button(
                            onClick = { selectedTemplate = template },
                            colors = ButtonDefaults.buttonColors(containerColor = StudioViolet),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Icon(Icons.Default.AutoAwesome, contentDescription = null, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Run Template")
                        }
                    }
                }
            }
        }
    }

    if (selectedTemplate != null) {
        val tmpl = selectedTemplate!!
        AlertDialog(
            onDismissRequest = { selectedTemplate = null },
            title = { Text(tmpl.title) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("Prompt Template:", fontWeight = FontWeight.SemiBold, fontSize = 12.sp)
                    Text(tmpl.promptTemplate, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    OutlinedTextField(
                        value = "Building a Production App in 48 Hours with AI",
                        onValueChange = {},
                        label = { Text("Topic / Input Context") },
                        modifier = Modifier.fillMaxWidth()
                    )
                }
            },
            confirmButton = {
                Button(
                    onClick = { selectedTemplate = null },
                    colors = ButtonDefaults.buttonColors(containerColor = StudioViolet)
                ) {
                    Text("Execute Prompt")
                }
            },
            dismissButton = {
                TextButton(onClick = { selectedTemplate = null }) {
                    Text("Close")
                }
            }
        )
    }
}

