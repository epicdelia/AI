package com.techunicorn.studio.ui.screens.snippets

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.ui.theme.*

enum class SnippetTheme(val label: String, val gradient: Brush, val windowBg: Color, val textColor: Color) {
    UNICORN(
        "Unicorn Neon",
        Brush.linearGradient(listOf(Color(0xFFF43F5E), Color(0xFFA855F7), Color(0xFF6366F1))),
        Color(0xFF1E1E2E),
        Color(0xFFCDD6F4)
    ),
    CYBERPUNK(
        "Cyberpunk Dark",
        Brush.linearGradient(listOf(Color(0xFF09090B), Color(0xFF1E1B4B), Color(0xFF4C1D95))),
        Color(0xFF0F172A),
        Color(0xFF38BDF8)
    ),
    SUNSET(
        "Warm Sunset",
        Brush.linearGradient(listOf(Color(0xFFEA580C), Color(0xFFDB2777), Color(0xFF7C3AED))),
        Color(0xFF18181B),
        Color(0xFFFAFAFA)
    ),
    SLATE(
        "Deep Slate",
        Brush.linearGradient(listOf(Color(0xFF334155), Color(0xFF1E293B))),
        Color(0xFF0F172A),
        Color(0xFFE2E8F0)
    ),
    EMERALD(
        "Matrix Emerald",
        Brush.linearGradient(listOf(Color(0xFF064E3B), Color(0xFF022C22))),
        Color(0xFF022C22),
        Color(0xFF34D399)
    )
}

@Composable
fun SnippetsScreen() {
    var activeTheme by remember { mutableStateOf(SnippetTheme.UNICORN) }
    var windowTitle by remember { mutableStateOf("agent.kt") }
    var showLineNumbers by remember { mutableStateOf(true) }
    var watermarkText by remember { mutableStateOf("@Tech.Unicorn") }

    var codeText by remember {
        mutableStateOf(
            """// Build an autonomous AI agent swarm in Kotlin
val swarm = AgentSwarm(
    model = "claude-3-7-sonnet",
    agents = listOf(ScriptAuditor, SponsorNegotiator),
    guardrails = PlatformShield.Strict
)

val verdict = swarm.runMission("Audit retention on 48h MVP video")
println("Verdict: ${'$'}{verdict.score}/100")"""
        )
    }

    LazyColumn(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text("Code Card Studio", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
                    Text("Generate viral Carbon / Ray.so graphics for social media", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                Button(
                    onClick = {},
                    colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                    contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp)
                ) {
                    Icon(Icons.Default.Share, contentDescription = null, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("Export", fontSize = 12.sp)
                }
            }
        }

        // Live Gradient Code Card Preview
        item {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(16.dp))
                    .background(activeTheme.gradient)
                    .padding(20.dp)
            ) {
                // Code Window
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp))
                        .background(activeTheme.windowBg)
                        .padding(14.dp)
                ) {
                    // Window Top Bar
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        // macOS Traffic Lights
                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            Box(Modifier.size(10.dp).clip(CircleShape).background(Color(0xFFFF5F56)))
                            Box(Modifier.size(10.dp).clip(CircleShape).background(Color(0xFFFFBD2E)))
                            Box(Modifier.size(10.dp).clip(CircleShape).background(Color(0xFF27C93F)))
                        }

                        Text(windowTitle, color = Color.White.copy(alpha = 0.7f), fontSize = 11.sp, fontFamily = FontFamily.Monospace)

                        Text(watermarkText, color = StudioCoral, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    }

                    Spacer(Modifier.height(14.dp))

                    // Code lines
                    val lines = codeText.split("\n")
                    for ((index, line) in lines.withIndex()) {
                        Row(modifier = Modifier.fillMaxWidth()) {
                            if (showLineNumbers) {
                                Text(
                                    text = "${index + 1}".padStart(2, ' '),
                                    color = Color.White.copy(alpha = 0.3f),
                                    fontSize = 12.sp,
                                    fontFamily = FontFamily.Monospace,
                                    modifier = Modifier.width(28.dp)
                                )
                            }
                            Text(
                                text = line,
                                color = activeTheme.textColor,
                                fontSize = 12.sp,
                                fontFamily = FontFamily.Monospace,
                                modifier = Modifier.weight(1f)
                            )
                        }
                    }
                }
            }
        }

        // Theme Selector Chips
        item {
            Text("Gradient Themes", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(8.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                for (th in SnippetTheme.entries) {
                    FilterChip(
                        selected = activeTheme == th,
                        onClick = { activeTheme = th },
                        label = { Text(th.label, fontSize = 11.sp) }
                    )
                }
            }
        }

        // Code Editor Box
        item {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(14.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text("Card Settings", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Checkbox(checked = showLineNumbers, onCheckedChange = { showLineNumbers = it })
                            Text("Line numbers", fontSize = 12.sp)
                        }
                    }

                    OutlinedTextField(
                        value = windowTitle,
                        onValueChange = { windowTitle = it },
                        label = { Text("File Name (e.g. index.kt)") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true
                    )

                    OutlinedTextField(
                        value = codeText,
                        onValueChange = { codeText = it },
                        label = { Text("Code Snippet") },
                        modifier = Modifier.fillMaxWidth(),
                        minLines = 6,
                        maxLines = 12,
                        textStyle = androidx.compose.ui.text.TextStyle(fontFamily = FontFamily.Monospace, fontSize = 12.sp)
                    )
                }
            }
        }
    }
}
