package com.techunicorn.studio.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import com.techunicorn.studio.ui.navigation.StudioDestination
import com.techunicorn.studio.ui.theme.ThemeMode

data class OmnibarAction(
    val title: String,
    val subtitle: String,
    val icon: ImageVector,
    val destination: StudioDestination? = null,
    val execute: (() -> Unit)? = null
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OmnibarDialog(
    onDismiss: () -> Unit,
    onNavigate: (StudioDestination) -> Unit,
    onToggleTheme: () -> Unit,
    onOpenCopilot: () -> Unit
) {
    var query by remember { mutableStateOf("") }

    val defaultActions = remember {
        listOf(
            OmnibarAction("New script draft", "Create a script with AI linting", Icons.Default.Description, StudioDestination.Scripts),
            OmnibarAction("New social post", "Schedule post for LinkedIn, X, IG, TikTok", Icons.Default.CalendarMonth, StudioDestination.Calendar),
            OmnibarAction("Add brand deal", "Sponsorship pipeline & deliverables", Icons.Default.Work, StudioDestination.Deals),
            OmnibarAction("Sponsor campaign report", "Verified metrics & wrap-up report for sponsors", Icons.Default.Article, StudioDestination.MediaKit),
            OmnibarAction("Omnichannel repurpose", "Turn scripts into LinkedIn, X, TikTok & newsletters", Icons.Default.Repeat, StudioDestination.Repurpose),
            OmnibarAction("Code cards & terminal graphics", "Ray.so visual code snippet studio", Icons.Default.Code, StudioDestination.Snippets),
            OmnibarAction("Media kit press deck", "Audience reach, demographics & rate card", Icons.Default.Article, StudioDestination.MediaKit),
            OmnibarAction("Tech Unicorn website", "Live portfolio, services, packages & booking", Icons.Default.Language, StudioDestination.Website),
            OmnibarAction("Dictate an idea", "Voice recording with live streaming transcript", Icons.Default.Mic, StudioDestination.Flow),
            OmnibarAction("Producer chat", "Think out loud with your producer", Icons.Default.Chat, StudioDestination.Chat),
            OmnibarAction("YouTube breakouts", "Videos beating their channel median", Icons.Default.TrendingUp, StudioDestination.Breakouts),
            OmnibarAction("Autonomous AI agents", "Multi-agent missions & approval queue", Icons.Default.SmartToy, StudioDestination.Agents),
            OmnibarAction("Toggle dark / light mode", "Switch studio appearance instantly", Icons.Default.Brightness4, execute = onToggleTheme),
            OmnibarAction("Launch Copilot", "Agentic assistant panel with tool use", Icons.Default.AutoAwesome, execute = onOpenCopilot)
        )
    }

    val filteredActions = remember(query) {
        val clean = query.trim().removePrefix(">").trim().lowercase()
        if (clean.isEmpty()) {
            defaultActions
        } else {
            defaultActions.filter {
                it.title.lowercase().contains(clean) || it.subtitle.lowercase().contains(clean)
            }
        }
    }

    Dialog(onDismissRequest = onDismiss) {
        Surface(
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(max = 520.dp)
                .clip(RoundedCornerShape(16.dp))
                .border(1.dp, MaterialTheme.colorScheme.outline, RoundedCornerShape(16.dp)),
            color = MaterialTheme.colorScheme.surface,
            tonalElevation = 8.dp
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                // Search Input Bar
                OutlinedTextField(
                    value = query,
                    onValueChange = { query = it },
                    placeholder = { Text("Type to search or \">\" for actions…", fontSize = 14.sp) },
                    leadingIcon = { Icon(Icons.Default.Search, contentDescription = "Search", tint = MaterialTheme.colorScheme.primary) },
                    trailingIcon = {
                        Surface(
                            shape = RoundedCornerShape(6.dp),
                            color = MaterialTheme.colorScheme.surfaceVariant,
                            modifier = Modifier.padding(end = 4.dp)
                        ) {
                            Text(
                                "Ctrl K",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp),
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedContainerColor = MaterialTheme.colorScheme.surfaceVariant,
                        unfocusedContainerColor = MaterialTheme.colorScheme.surfaceVariant
                    ),
                    singleLine = true
                )

                Spacer(Modifier.height(12.dp))

                Text(
                    if (query.isNotEmpty()) "MATCHING COMMANDS" else "QUICK ACTIONS",
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    letterSpacing = 1.sp,
                    modifier = Modifier.padding(horizontal = 4.dp, vertical = 6.dp)
                )

                LazyColumn(
                    modifier = Modifier.weight(1f, fill = false),
                    verticalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    items(filteredActions) { action ->
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(10.dp))
                                .clickable {
                                    onDismiss()
                                    if (action.execute != null) {
                                        action.execute.invoke()
                                    } else if (action.destination != null) {
                                        onNavigate(action.destination)
                                    }
                                }
                                .padding(horizontal = 12.dp, vertical = 10.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(34.dp)
                                    .clip(RoundedCornerShape(8.dp))
                                    .background(MaterialTheme.colorScheme.primaryContainer),
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(
                                    imageVector = action.icon,
                                    contentDescription = null,
                                    modifier = Modifier.size(18.dp),
                                    tint = MaterialTheme.colorScheme.primary
                                )
                            }
                            Spacer(Modifier.width(12.dp))
                            Column(modifier = Modifier.weight(1f)) {
                                Text(
                                    action.title,
                                    fontWeight = FontWeight.SemiBold,
                                    fontSize = 13.sp,
                                    color = MaterialTheme.colorScheme.onSurface
                                )
                                Text(
                                    action.subtitle,
                                    fontSize = 11.sp,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                            Icon(
                                Icons.Default.ArrowForward,
                                contentDescription = null,
                                modifier = Modifier.size(14.dp),
                                tint = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                }
            }
        }
    }
}

