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
import com.techunicorn.studio.ui.components.*
import com.techunicorn.studio.ui.navigation.StudioDestination
import com.techunicorn.studio.ui.screens.agents.AgentsScreen
import com.techunicorn.studio.ui.screens.chat.ProducerChatScreen
import com.techunicorn.studio.ui.screens.client.ClientPortalScreen
import com.techunicorn.studio.ui.screens.dashboard.DashboardScreen
import com.techunicorn.studio.ui.screens.deals.DealRoomScreen
import com.techunicorn.studio.ui.screens.deals.DealsScreen
import com.techunicorn.studio.ui.screens.deals.MediaKitScreen
import com.techunicorn.studio.ui.screens.flow.FlowScreen
import com.techunicorn.studio.ui.screens.landing.LandingScreen
import com.techunicorn.studio.ui.screens.love.LoveScreen
import com.techunicorn.studio.ui.screens.scripts.CameraPrompterScreen
import com.techunicorn.studio.ui.screens.scripts.YouTubeHubScreen
import com.techunicorn.studio.ui.screens.settings.AccountScreen
import com.techunicorn.studio.ui.screens.settings.SettingsScreen
import com.techunicorn.studio.ui.screens.snippets.SnippetsScreen
import com.techunicorn.studio.ui.screens.social.AnalyticsScreen
import com.techunicorn.studio.ui.screens.social.CalendarScreen
import com.techunicorn.studio.ui.screens.social.InboxScreen
import com.techunicorn.studio.ui.screens.social.SocialScreen
import com.techunicorn.studio.ui.screens.toolkit.ToolkitScreen
import com.techunicorn.studio.ui.screens.website.WebsiteScreen
import com.techunicorn.studio.ui.theme.*
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun App() {
    val api = remember { StudioApiClient() }
    val scope = rememberCoroutineScope()
    val drawerState = rememberDrawerState(DrawerValue.Closed)

    // Tri-State Theme Mode: LIGHT, DARK, SYSTEM (creator-studio/public/js/theme.js)
    var themeMode by remember { mutableStateOf(ThemeMode.SYSTEM) }

    // User Role & Destination
    var userRole by remember { mutableStateOf(UserRole.PUBLIC) }
    var currentDestination by remember { mutableStateOf(StudioDestination.Landing) }
    var loggedInUserName by remember { mutableStateOf<String?>(null) }

    // Modals & Panels State
    var showAuthModal by remember { mutableStateOf(false) }
    var isOmnibarOpen by remember { mutableStateOf(false) }
    var isNotificationsOpen by remember { mutableStateOf(false) }
    var isCopilotOpen by remember { mutableStateOf(false) }
    var isDealRoomActive by remember { mutableStateOf(false) }
    var isSpeedToLeadOpen by remember { mutableStateOf(false) }
    var isLaunchRadarOpen by remember { mutableStateOf(false) }
    var activePrompterScript by remember { mutableStateOf<Script?>(null) }

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

    StudioTheme(
        themeMode = themeMode,
        onThemeChange = { themeMode = it }
    ) {
        // If Deal Room is active full-screen
        if (isDealRoomActive && dealRoomData != null) {
            DealRoomScreen(
                initialData = dealRoomData!!,
                onBack = { isDealRoomActive = false }
            )
            return@StudioTheme
        }

        ModalNavigationDrawer(
            drawerState = drawerState,
            gesturesEnabled = userRole != UserRole.PUBLIC,
            drawerContent = {
                NavigationDrawerContent(
                    currentDestination = currentDestination,
                    userRole = userRole,
                    onSelectDestination = { dest ->
                        currentDestination = dest
                        scope.launch { drawerState.close() }
                    },
                    onCloseDrawer = {
                        scope.launch { drawerState.close() }
                    }
                )
            }
        ) {
            Scaffold(
                topBar = {
                    StudioHeader(
                        currentDestination = currentDestination,
                        userRole = userRole,
                        themeMode = themeMode,
                        unreadNotifications = 3,
                        hasUrgentLead = userRole == UserRole.STUDIO,
                        hasLaunchWatchdogAlert = userRole == UserRole.STUDIO,
                        onAction = { action ->
                            when (action) {
                                HeaderAction.ToggleDrawer -> scope.launch { drawerState.open() }
                                HeaderAction.OpenOmnibar -> isOmnibarOpen = true
                                HeaderAction.OpenNotifications -> isNotificationsOpen = true
                                HeaderAction.OpenCopilot -> isCopilotOpen = true
                                HeaderAction.OpenSpeedToLead -> isSpeedToLeadOpen = true
                                HeaderAction.OpenLaunchRadar -> isLaunchRadarOpen = true
                                is HeaderAction.Navigate -> currentDestination = action.destination
                                is HeaderAction.ChangeTheme -> themeMode = action.mode
                                HeaderAction.SignOut -> {
                                    userRole = UserRole.PUBLIC
                                    loggedInUserName = null
                                    currentDestination = StudioDestination.Landing
                                }
                                HeaderAction.OpenAuth -> showAuthModal = true
                            }
                        }
                    )
                },
                bottomBar = {
                    val bottomDestinations = when (userRole) {
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
                            StudioDestination.YouTube,
                            StudioDestination.Calendar,
                            StudioDestination.Inbox,
                            StudioDestination.Deals
                        )
                    }

                    NavigationBar(
                        containerColor = MaterialTheme.colorScheme.surface,
                        tonalElevation = 6.dp
                    ) {
                        bottomDestinations.forEach { destination ->
                            val isSelected = currentDestination == destination || (destination == StudioDestination.YouTube && currentDestination in listOf(StudioDestination.Breakouts, StudioDestination.Ideas, StudioDestination.Scripts, StudioDestination.Goals))
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
                                label = { Text(destination.title, fontSize = 10.sp) },
                                colors = NavigationBarItemDefaults.colors(
                                    selectedIconColor = MaterialTheme.colorScheme.primary,
                                    selectedTextColor = MaterialTheme.colorScheme.primary,
                                    indicatorColor = MaterialTheme.colorScheme.primaryContainer
                                )
                            )
                        }
                    }
                },
                floatingActionButton = {
                    if (!isCopilotOpen && userRole == UserRole.STUDIO) {
                        FloatingActionButton(
                            onClick = { isCopilotOpen = true },
                            containerColor = MaterialTheme.colorScheme.secondary,
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
                    // Active Destination Screen
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
                                if (userRole == UserRole.PUBLIC) showAuthModal = true
                                else currentDestination = StudioDestination.ClientPortal
                            },
                            onExploreSnippets = { currentDestination = StudioDestination.Snippets }
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
                        StudioDestination.YouTube,
                        StudioDestination.Breakouts,
                        StudioDestination.Ideas,
                        StudioDestination.Scripts,
                        StudioDestination.Goals,
                        StudioDestination.Channel -> YouTubeHubScreen(
                            breakouts = breakouts,
                            ideas = ideas,
                            scripts = scripts,
                            onOpenCopilot = { isCopilotOpen = true }
                        )
                        StudioDestination.Social,
                        StudioDestination.Repurpose,
                        StudioDestination.Bio -> SocialScreen(posts = posts)
                        StudioDestination.Calendar -> CalendarScreen()
                        StudioDestination.Analytics -> AnalyticsScreen()
                        StudioDestination.Inbox -> InboxScreen()
                        StudioDestination.Snippets -> SnippetsScreen()
                        StudioDestination.Deals -> DealsScreen(deals = deals)
                        StudioDestination.MediaKit -> MediaKitScreen()
                        StudioDestination.Website -> WebsiteScreen()
                        StudioDestination.ClientPortal,
                        StudioDestination.Clients -> ClientPortalScreen(
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
                        StudioDestination.Chat -> ProducerChatScreen()
                        StudioDestination.Love -> LoveScreen(letters = loveLetters, memoryJar = memoryJar)
                        StudioDestination.Agents,
                        StudioDestination.Workflows -> AgentsScreen()
                        StudioDestination.Settings,
                        StudioDestination.Connections,
                        StudioDestination.Brand,
                        StudioDestination.Library,
                        StudioDestination.Media -> SettingsScreen(
                            currentThemeMode = themeMode,
                            onThemeChange = { themeMode = it }
                        )
                        StudioDestination.Account -> AccountScreen(
                            userRole = userRole,
                            onSignOut = {
                                userRole = UserRole.PUBLIC
                                loggedInUserName = null
                                currentDestination = StudioDestination.Landing
                            }
                        )
                    }

                    // Omnibar Dialog
                    if (isOmnibarOpen) {
                        OmnibarDialog(
                            onDismiss = { isOmnibarOpen = false },
                            onNavigate = { dest -> currentDestination = dest },
                            onToggleTheme = {
                                themeMode = if (themeMode == ThemeMode.DARK) ThemeMode.LIGHT else ThemeMode.DARK
                            },
                            onOpenCopilot = { isCopilotOpen = true }
                        )
                    }

                    // Notifications Sheet
                    if (isNotificationsOpen) {
                        NotificationsSheet(
                            onDismiss = { isNotificationsOpen = false },
                            onNavigate = { dest -> currentDestination = dest }
                        )
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

                    // Copilot Modal Bottom Sheet
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

                    // Speed-to-Lead Instant Closer Sheet (Pillar 2)
                    if (isSpeedToLeadOpen) {
                        SpeedToLeadSheet(
                            onDismiss = { isSpeedToLeadOpen = false },
                            onProposalDispatched = { link, amount ->
                                // Dispatched proposal feedback
                            }
                        )
                    }

                    // 120-Minute Launch Velocity Radar Sheet (Pillar 4)
                    if (isLaunchRadarOpen) {
                        LaunchRadarSheet(
                            onDismiss = { isLaunchRadarOpen = false },
                            onTitleSwapped = { newTitle ->
                                if (scripts.isNotEmpty()) {
                                    val s = scripts.first()
                                    scripts = listOf(s.copy(title = newTitle)) + scripts.drop(1)
                                }
                            }
                        )
                    }
                }
            }
        }
    }
}
