package com.techunicorn.studio.ui.screens.settings

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
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
import com.techunicorn.studio.ui.theme.StudioEmerald
import com.techunicorn.studio.ui.theme.ThemeMode
import com.techunicorn.studio.ui.theme.ThemeSwitch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(
    currentThemeMode: ThemeMode,
    onThemeChange: (ThemeMode) -> Unit
) {
    var motto by remember { mutableStateOf("High-leverage engineering & vibecoding in production.") }
    var openaiKey by remember { mutableStateOf("sk-proj-••••••••••••••••") }
    var geminiKey by remember { mutableStateOf("AIzaSy••••••••••••••••") }
    var youtubeKey by remember { mutableStateOf("AIzaSyYT••••••••••••") }
    var assemblyKey by remember { mutableStateOf("aai_••••••••••••••••") }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .padding(16.dp)
    ) {
        Text(
            "Studio Settings & Keys",
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onBackground
        )
        Text(
            "AI engines, keys, appearance and make it yours",
            fontSize = 12.sp,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )

        Spacer(Modifier.height(14.dp))

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            // Theme & Appearance
            item {
                Surface(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(14.dp),
                    color = MaterialTheme.colorScheme.surface,
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline)
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        Text(
                            "Appearance & Theme",
                            fontWeight = FontWeight.Bold,
                            fontSize = 14.sp,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                        Text(
                            "Light, Dark or follow the system color scheme",
                            fontSize = 11.sp,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )

                        Spacer(Modifier.height(12.dp))

                        ThemeSwitch(
                            currentMode = currentThemeMode,
                            onModeSelected = onThemeChange
                        )
                    }
                }
            }

            // Studio Identity & Motto
            item {
                Surface(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(14.dp),
                    color = MaterialTheme.colorScheme.surface,
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline)
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        Text(
                            "Studio Identity & Motto",
                            fontWeight = FontWeight.Bold,
                            fontSize = 14.sp,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                        Spacer(Modifier.height(8.dp))

                        OutlinedTextField(
                            value = motto,
                            onValueChange = { motto = it },
                            label = { Text("Dashboard Hero Motto") },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(10.dp)
                        )
                    }
                }
            }

            // API Keys & Engines
            item {
                Surface(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(14.dp),
                    color = MaterialTheme.colorScheme.surface,
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline)
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        Text(
                            "API Keys & Providers",
                            fontWeight = FontWeight.Bold,
                            fontSize = 14.sp,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                        Text(
                            "Keys are encrypted and stored in local secure keychain",
                            fontSize = 11.sp,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )

                        Spacer(Modifier.height(12.dp))

                        OutlinedTextField(
                            value = openaiKey,
                            onValueChange = { openaiKey = it },
                            label = { Text("Anthropic & OpenAI (LLM Engine)", fontSize = 11.sp) },
                            modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                            shape = RoundedCornerShape(10.dp),
                            trailingIcon = {
                                Icon(Icons.Default.CheckCircle, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(18.dp))
                            }
                        )

                        OutlinedTextField(
                            value = geminiKey,
                            onValueChange = { geminiKey = it },
                            label = { Text("Google Gemini 1.5 Pro / Flash", fontSize = 11.sp) },
                            modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                            shape = RoundedCornerShape(10.dp),
                            trailingIcon = {
                                Icon(Icons.Default.CheckCircle, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(18.dp))
                            }
                        )

                        OutlinedTextField(
                            value = youtubeKey,
                            onValueChange = { youtubeKey = it },
                            label = { Text("YouTube Data API v3", fontSize = 11.sp) },
                            modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                            shape = RoundedCornerShape(10.dp),
                            trailingIcon = {
                                Icon(Icons.Default.CheckCircle, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(18.dp))
                            }
                        )

                        OutlinedTextField(
                            value = assemblyKey,
                            onValueChange = { assemblyKey = it },
                            label = { Text("AssemblyAI (Audio Transcription)", fontSize = 11.sp) },
                            modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                            shape = RoundedCornerShape(10.dp),
                            trailingIcon = {
                                Icon(Icons.Default.CheckCircle, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(18.dp))
                            }
                        )
                    }
                }
            }

            // Connected Networks
            item {
                Surface(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(14.dp),
                    color = MaterialTheme.colorScheme.surface,
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline)
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        Text(
                            "Connected Social Networks",
                            fontWeight = FontWeight.Bold,
                            fontSize = 14.sp,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                        Spacer(Modifier.height(10.dp))

                        listOf(
                            Triple("YouTube", "@TechUnicorn", true),
                            Triple("LinkedIn", "Delia Lazarescu", true),
                            Triple("X / Twitter", "@delia_tech", true),
                            Triple("Stripe Connect", "Studio Verified", true)
                        ).forEach { (network, handle, connected) ->
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(vertical = 6.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column {
                                    Text(network, fontWeight = FontWeight.Bold, fontSize = 13.sp, color = MaterialTheme.colorScheme.onSurface)
                                    Text(handle, fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                                Surface(
                                    shape = RoundedCornerShape(6.dp),
                                    color = StudioEmerald.copy(alpha = 0.15f)
                                ) {
                                    Text(
                                        "Connected",
                                        fontSize = 10.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = StudioEmerald,
                                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
