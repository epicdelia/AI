package com.techunicorn.studio

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AutoAwesome
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import com.techunicorn.studio.data.api.StudioApiClient
import com.techunicorn.studio.domain.models.*
import com.techunicorn.studio.ui.components.CopilotDrawerContent
import com.techunicorn.studio.ui.navigation.StudioDestination
import com.techunicorn.studio.ui.screens.agents.AgentsScreen
import com.techunicorn.studio.ui.screens.dashboard.DashboardScreen
import com.techunicorn.studio.ui.screens.deals.DealsScreen
import com.techunicorn.studio.ui.screens.flow.FlowScreen
import com.techunicorn.studio.ui.screens.scripts.YouTubeHubScreen
import com.techunicorn.studio.ui.screens.social.SocialScreen
import com.techunicorn.studio.ui.theme.StudioCoral
import com.techunicorn.studio.ui.theme.StudioTheme
import com.techunicorn.studio.ui.theme.StudioViolet
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun App() {
    val api = remember { StudioApiClient() }
    val scope = rememberCoroutineScope()

    var currentDestination by remember { mutableStateOf(StudioDestination.Dashboard) }
    var isCopilotOpen by remember { mutableStateOf(false) }

    // Live App State loaded from API / Mock cache
    var stats by remember { mutableStateOf(StudioDashboardStats()) }
    var breakouts by remember { mutableStateOf<List<Breakout>>(emptyList()) }
    var ideas by remember { mutableStateOf<List<Idea>>(emptyList()) }
    var scripts by remember { mutableStateOf<List<Script>>(emptyList()) }
    var posts by remember { mutableStateOf<List<SocialPost>>(emptyList()) }
    var deals by remember { mutableStateOf<List<Deal>>(emptyList()) }

    var copilotMessages by remember {
        mutableStateOf(
            listOf(
                CopilotMessage(
                    id = "1",
                    sender = "assistant",
                    text = "Welcome to Creator Studio. I'm monitoring your retention stats, YouTube breakouts, and brand deals. How can I assist your workflow today?",
                    timestamp = "Now"
                )
            )
        )
    }

    // Load initial data
    LaunchedEffect(Unit) {
        stats = api.getDashboardStats()
        breakouts = api.getBreakouts()
        ideas = api.getIdeas()
        scripts = api.getScripts()
        posts = api.getSocialPosts()
        deals = api.getDeals()
    }

    StudioTheme {
        Scaffold(
            bottomBar = {
                NavigationBar(
                    containerColor = MaterialTheme.colorScheme.surface,
                    tonalElevation = 8.dp
                ) {
                    StudioDestination.entries.forEach { destination ->
                        val isSelected = currentDestination == destination
                        NavigationBarItem(
                            selected = isSelected,
                            onClick = { currentDestination = destination },
                            icon = {
                                Icon(
                                    imageVector = if (isSelected) destination.selectedIcon else destination.unselectedIcon,
                                    contentDescription = destination.title
                                )
                            },
                            label = { Text(destination.title) },
                            colors = NavigationBarItemDefaults.colors(
                                selectedIconColor = StudioCoral,
                                selectedTextColor = StudioCoral,
                                indicatorColor = StudioCoral.copy(alpha = 0.12f)
                            )
                        )
                    }
                }
            },
            floatingActionButton = {
                if (!isCopilotOpen) {
                    FloatingActionButton(
                        onClick = { isCopilotOpen = true },
                        containerColor = StudioViolet,
                        contentColor = Color.White
                    ) {
                        Icon(Icons.Default.AutoAwesome, contentDescription = "Copilot")
                    }
                }
            }
        ) { paddingValues ->
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(paddingValues)
            ) {
                // Active Screen
                when (currentDestination) {
                    StudioDestination.Dashboard -> DashboardScreen(
                        stats = stats,
                        onNavigateToFlow = { currentDestination = StudioDestination.Flow },
                        onNavigateToScripts = { currentDestination = StudioDestination.YouTube },
                        onNavigateToDeals = { currentDestination = StudioDestination.Deals },
                        onOpenCopilot = { isCopilotOpen = true }
                    )
                    StudioDestination.Flow -> FlowScreen(
                        onSaveToIdeas = { text ->
                            ideas = listOf(
                                Idea(
                                    id = ideas.size + 1L,
                                    title = "Extracted from Flow Dictation",
                                    angle = text.take(60),
                                    hook = text.lines().firstOrNull() ?: "",
                                    status = "inbox"
                                )
                            ) + ideas
                            currentDestination = StudioDestination.YouTube
                        }
                    )
                    StudioDestination.YouTube -> YouTubeHubScreen(
                        breakouts = breakouts,
                        ideas = ideas,
                        scripts = scripts,
                        onOpenCopilot = { isCopilotOpen = true }
                    )
                    StudioDestination.Social -> SocialScreen(posts = posts)
                    StudioDestination.Deals -> DealsScreen(deals = deals)
                    StudioDestination.Agents -> AgentsScreen()
                }

                // Modal Bottom Sheet for Copilot
                if (isCopilotOpen) {
                    ModalBottomSheet(
                        onDismissRequest = { isCopilotOpen = false },
                        containerColor = MaterialTheme.colorScheme.surface,
                        dragHandle = { BottomSheetDefaults.DragHandle() }
                    ) {
                        CopilotDrawerContent(
                            messages = copilotMessages,
                            onSendMessage = { prompt ->
                                copilotMessages = copilotMessages + CopilotMessage(
                                    id = (copilotMessages.size + 1).toString(),
                                    sender = "user",
                                    text = prompt,
                                    timestamp = "Now"
                                )
                                scope.launch {
                                    val streamMsgId = (copilotMessages.size + 1).toString()
                                    var streamedText = ""
                                    api.sendCopilotMessage("1", prompt).collect { chunk ->
                                        streamedText += chunk
                                        val existing = copilotMessages.filterNot { it.id == streamMsgId }
                                        copilotMessages = existing + CopilotMessage(
                                            id = streamMsgId,
                                            sender = "assistant",
                                            text = streamedText,
                                            timestamp = "Now",
                                            actions = listOf(
                                                CopilotAction(
                                                    id = "act1",
                                                    title = "Update Hook in Script #101",
                                                    description = "Replaces opening line with tested high-retention metric.",
                                                    type = "update_script"
                                                )
                                            )
                                        )
                                    }
                                }
                            },
                            onApplyAction = { action ->
                                // Apply update to scripts
                                if (scripts.isNotEmpty()) {
                                    val s = scripts.first()
                                    scripts = listOf(s.copy(hook = "I tested 48h of continuous AI vibecoding. Here is where the code broke.")) + scripts.drop(1)
                                }
                                isCopilotOpen = false
                            },
                            onClose = { isCopilotOpen = false }
                        )
                    }
                }
            }
        }
    }
}

