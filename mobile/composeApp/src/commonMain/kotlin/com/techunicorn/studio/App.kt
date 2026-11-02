package com.techunicorn.studio

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
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
import com.techunicorn.studio.data.api.StudioApiClient
import com.techunicorn.studio.domain.models.*
import com.techunicorn.studio.ui.components.AuthModal
import com.techunicorn.studio.ui.components.CopilotDrawerContent
import com.techunicorn.studio.ui.navigation.StudioDestination
import com.techunicorn.studio.ui.screens.agents.AgentsScreen
import com.techunicorn.studio.ui.screens.client.ClientPortalScreen
import com.techunicorn.studio.ui.screens.dashboard.DashboardScreen
import com.techunicorn.studio.ui.screens.deals.DealRoomScreen
import com.techunicorn.studio.ui.screens.deals.DealsScreen
import com.techunicorn.studio.ui.screens.flow.FlowScreen
import com.techunicorn.studio.ui.screens.landing.LandingScreen
import com.techunicorn.studio.ui.screens.love.LoveScreen
import com.techunicorn.studio.ui.screens.scripts.YouTubeHubScreen
import com.techunicorn.studio.ui.screens.snippets.SnippetsScreen
import com.techunicorn.studio.ui.screens.social.SocialScreen
import com.techunicorn.studio.ui.screens.toolkit.ToolkitScreen
import com.techunicorn.studio.ui.theme.StudioCoral
import com.techunicorn.studio.ui.theme.StudioEmerald
import com.techunicorn.studio.ui.theme.StudioTheme
import com.techunicorn.studio.ui.theme.StudioViolet
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun App() {
    val api = remember { StudioApiClient() }
    val scope = rememberCoroutineScope()

    // Default to Public Landing page as requested
    var userRole by remember { mutableStateOf(UserRole.PUBLIC) }
    var currentDestination by remember { mutableStateOf(StudioDestination.Landing) }
    var loggedInUserName by remember { mutableStateOf<String?>(null) }
    var showAuthModal by remember { mutableStateOf(false) }

    var isCopilotOpen by remember { mutableStateOf(false) }
    var isDealRoomActive by remember { mutableStateOf(false) }

    // Live App State loaded from API / Mock cache
    var stats by remember { mutableStateOf(StudioDashboardStats()) }
    var breakouts by remember { mutableStateOf<List<Breakout>>(emptyList()) }
    var ideas by remember { mutableStateOf<List<Idea>>(emptyList()) }
    var scripts by remember { mutableStateOf<List<Script>>(emptyList()) }
    var posts by remember { mutableStateOf<List<SocialPost>>(emptyList()) }
    var deals by remember { mutableStateOf<List<Deal>>(emptyList()) }
    var landingServices by remember { mutableStateOf<List<LandingService>>(emptyList()) }
    var talks by remember { mutableStateOf<List<Talk>>(emptyList()) }
    var shippedBuilds by remember { mutableStateOf<List<ShippedBuild>>(emptyList()) }
    var pricingPackages by remember { mutableStateOf<List<PricingPackage>>(emptyList()) }
    var faqItems by remember { mutableStateOf<List<FaqItem>>(emptyList()) }
    var clientRequests by remember { mutableStateOf<List<ClientRequest>>(emptyList()) }
    var brandVault by remember { mutableStateOf(BrandVault()) }
    var dealRoomData by remember { mutableStateOf<DealRoomData?>(null) }
    var loveLetters by remember { mutableStateOf<List<LoveLetter>>(emptyList()) }
    var memoryJar by remember { mutableStateOf<List<MemoryJarPrompt>>(emptyList()) }
    var toolkitTemplates by remember { mutableStateOf<List<ToolkitTemplate>>(emptyList()) }

    var copilotMessages by remember {
        mutableStateOf(
            listOf(
                CopilotMessage(
                    id = "1",
                    sender = "assistant",
                    text = "Welcome to Creator Studio. I'm monitoring your retention stats, YouTube breakouts, client deliverables, and brand deals. How can I assist you today?",
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
        landingServices = api.getLandingServices()
        talks = api.getTalks()
        shippedBuilds = api.getShippedBuilds()
        pricingPackages = api.getPricingPackages()
        faqItems = api.getFaqItems()
        clientRequests = api.getClientRequests()
        brandVault = api.getBrandVault()
        dealRoomData = api.getDealRoom()
        loveLetters = api.getLoveLetters()
        memoryJar = api.getMemoryJarPrompts()
        toolkitTemplates = api.getToolkitTemplates()
    }

    StudioTheme {
        // If Deal Room is active full-screen
        if (isDealRoomActive && dealRoomData != null) {
            DealRoomScreen(
                initialData = dealRoomData!!,
                onBack = { isDealRoomActive = false }
            )
            return@StudioTheme
        }

        // Available destinations based on active role
        val availableDestinations = when (userRole) {
            UserRole.PUBLIC -> listOf(
                StudioDestination.Landing,
                StudioDestination.Snippets,
                StudioDestination.ClientPortal
            )
            UserRole.CLIENT -> listOf(
                StudioDestination.ClientPortal,
                StudioDestination.Deals,
                StudioDestination.Snippets,
                StudioDestination.Landing
            )
            UserRole.STUDIO -> listOf(
                StudioDestination.Dashboard,
                StudioDestination.Flow,
                StudioDestination.YouTube,
                StudioDestination.Social,
                StudioDestination.Snippets,
                StudioDestination.Deals,
                StudioDestination.ClientPortal,
                StudioDestination.Toolkit,
                StudioDestination.Love,
                StudioDestination.Agents
            )
        }

        Scaffold(
            topBar = {
                // Header shown when logged in or when navigating outside Landing
                if (userRole != UserRole.PUBLIC || currentDestination != StudioDestination.Landing) {
                    TopAppBar(
                        title = {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Box(
                                    modifier = Modifier
                                        .size(32.dp)
                                        .clip(CircleShape)
                                        .background(StudioCoral),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Text("🦄", fontSize = 16.sp)
                                }
                                Spacer(Modifier.width(10.dp))
                                Column {
                                    Text(
                                        when (userRole) {
                                            UserRole.STUDIO -> "Studio Admin · Delia"
                                            UserRole.CLIENT -> "Client Portal · ${loggedInUserName ?: "ScaleWorks"}"
                                            UserRole.PUBLIC -> "Tech Unicorn Studio"
                                        },
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 15.sp
                                    )
                                    Text(
                                        when (userRole) {
                                            UserRole.STUDIO -> "Full Admin Control · 472K Reach"
                                            UserRole.CLIENT -> "Active Retainer · Private Staging"
                                            UserRole.PUBLIC -> "Ex-Google · Valencia, Spain"
                                        },
                                        fontSize = 11.sp,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                }
                            }
                        },
                        actions = {
                            if (userRole != UserRole.PUBLIC) {
                                Box(
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(
                                            if (userRole == UserRole.STUDIO) StudioCoral.copy(alpha = 0.15f)
                                            else StudioViolet.copy(alpha = 0.15f)
                                        )
                                        .padding(horizontal = 8.dp, vertical = 4.dp)
                                ) {
                                    Text(
                                        if (userRole == UserRole.STUDIO) "ADMIN" else "CLIENT",
                                        fontSize = 10.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = if (userRole == UserRole.STUDIO) StudioCoral else StudioViolet
                                    )
                                }
                                Spacer(Modifier.width(6.dp))
                                IconButton(
                                    onClick = {
                                        userRole = UserRole.PUBLIC
                                        loggedInUserName = null
                                        currentDestination = StudioDestination.Landing
                                    }
                                ) {
                                    Icon(Icons.Default.Logout, contentDescription = "Sign Out", tint = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                            } else {
                                Button(
                                    onClick = { showAuthModal = true },
                                    colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                                    contentPadding = PaddingValues(horizontal = 12.dp, vertical = 4.dp)
                                ) {
                                    Text("Sign In", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                }
                            }
                        },
                        colors = TopAppBarDefaults.topAppBarColors(
                            containerColor = MaterialTheme.colorScheme.surface
                        )
                    )
                }
            },
            bottomBar = {
                NavigationBar(
                    containerColor = MaterialTheme.colorScheme.surface,
                    tonalElevation = 8.dp
                ) {
                    availableDestinations.take(5).forEach { destination ->
                        val isSelected = currentDestination == destination
                        NavigationBarItem(
                            selected = isSelected,
                            onClick = {
                                if (destination == StudioDestination.ClientPortal && userRole == UserRole.PUBLIC) {
                                    showAuthModal = true
                                } else {
                                    currentDestination = destination
                                }
                            },
                            icon = {
                                Icon(
                                    imageVector = if (isSelected) destination.selectedIcon else destination.unselectedIcon,
                                    contentDescription = destination.title
                                )
                            },
                            label = { Text(destination.title, fontSize = 11.sp) },
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
                if (!isCopilotOpen && userRole == UserRole.STUDIO) {
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
                    StudioDestination.Landing -> LandingScreen(
                        services = landingServices,
                        talks = talks,
                        shippedBuilds = shippedBuilds,
                        pricingPackages = pricingPackages,
                        faqItems = faqItems,
                        onOpenAuth = { showAuthModal = true },
                        onSwitchRole = { role ->
                            userRole = role
                            loggedInUserName = if (role == UserRole.STUDIO) "Delia Lazarescu" else "ScaleWorks Inc."
                            currentDestination = if (role == UserRole.STUDIO) StudioDestination.Dashboard else StudioDestination.ClientPortal
                        },
                        onBookService = {
                            if (userRole == UserRole.PUBLIC) {
                                showAuthModal = true
                            } else {
                                currentDestination = StudioDestination.ClientPortal
                            }
                        },
                        onExploreSnippets = {
                            currentDestination = StudioDestination.Snippets
                        }
                    )
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
                    StudioDestination.Snippets -> SnippetsScreen()
                    StudioDestination.Deals -> DealsScreen(deals = deals)
                    StudioDestination.ClientPortal -> ClientPortalScreen(
                        requests = clientRequests,
                        brandVault = brandVault,
                        onSwitchRole = { role ->
                            userRole = role
                            loggedInUserName = if (role == UserRole.STUDIO) "Delia Lazarescu" else "ScaleWorks Inc."
                            currentDestination = if (role == UserRole.STUDIO) StudioDestination.Dashboard else StudioDestination.Landing
                        },
                        onOpenDealRoom = { isDealRoomActive = true }
                    )
                    StudioDestination.Toolkit -> ToolkitScreen(templates = toolkitTemplates)
                    StudioDestination.Love -> LoveScreen(letters = loveLetters, memoryJar = memoryJar)
                    StudioDestination.Agents -> AgentsScreen()
                }

                // Auth Modal
                if (showAuthModal) {
                    AuthModal(
                        onDismiss = { showAuthModal = false },
                        onLoginSuccess = { role, name ->
                            userRole = role
                            loggedInUserName = name
                            currentDestination = if (role == UserRole.STUDIO) StudioDestination.Dashboard else StudioDestination.ClientPortal
                            showAuthModal = false
                        }
                    )
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
