package com.techunicorn.studio.ui.components

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.*
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.models.UserRole
import com.techunicorn.studio.ui.navigation.StudioDestination
import com.techunicorn.studio.ui.theme.*

/**
 * Advanced Kotlin Sealed Interface representing all Header Action Dispatches.
 * Enforces compile-time exhaustiveness with zero unchecked string routing.
 */
sealed interface HeaderAction {
    data object ToggleDrawer : HeaderAction
    data object OpenOmnibar : HeaderAction
    data object OpenNotifications : HeaderAction
    data object OpenCopilot : HeaderAction
    data object OpenSpeedToLead : HeaderAction
    data object OpenLaunchRadar : HeaderAction
    data object LaunchPrompter : HeaderAction
    data object OpenDictate : HeaderAction
    data class SearchQueryChanged(val query: String) : HeaderAction
    data class Navigate(val destination: StudioDestination) : HeaderAction
    data class ChangeTheme(val mode: ThemeMode) : HeaderAction
    data object SignOut : HeaderAction
    data object OpenAuth : HeaderAction
    data object ToggleTelemetry : HeaderAction
    data object ToggleQuickActions : HeaderAction
    data class SelectSearchScope(val scope: SearchScope) : HeaderAction
    data class SwitchWorkspace(val workspaceId: String) : HeaderAction
    data object TriggerSyncFlush : HeaderAction
}

/**
 * Search Scope filter categories for the enhanced inline mobile search bar.
 */
sealed interface SearchScope {
    val label: String
    val icon: ImageVector

    data object All : SearchScope {
        override val label: String = "All"
        override val icon: ImageVector = Icons.Default.AllInclusive
    }
    data object Scripts : SearchScope {
        override val label: String = "Scripts"
        override val icon: ImageVector = Icons.AutoMirrored.Filled.Article
    }
    data object Deals : SearchScope {
        override val label: String = "Deals"
        override val icon: ImageVector = Icons.Default.AttachMoney
    }
    data object VIP : SearchScope {
        override val label: String = "VIP"
        override val icon: ImageVector = Icons.Default.Star
    }
    data object Ideas : SearchScope {
        override val label: String = "Ideas"
        override val icon: ImageVector = Icons.Default.Lightbulb
    }

    companion object {
        val entries: List<SearchScope> = listOf(All, Scripts, Deals, VIP, Ideas)
    }
}

/**
 * Advanced Inline Value Class for Network Latency with custom boundary thresholds.
 */
@JvmInline
value class LatencyMs(val ms: Long) : Comparable<LatencyMs> {
    val isSub30ms: Boolean get() = ms in 1..35
    val isAcceptable: Boolean get() = ms in 36..120
    val isNomadWarning: Boolean get() = ms > 120

    override fun compareTo(other: LatencyMs): Int = ms.compareTo(other.ms)

    fun formatted(): String = "${ms}ms"

    fun statusColor(): Color = when {
        isSub30ms -> StudioGoodLight
        isAcceptable -> StudioEmerald
        else -> StudioAmber
    }
}

/**
 * Advanced Inline Value Class for Header Notification Badges with arithmetic operators.
 */
@JvmInline
value class NotificationBadgeCount(val count: Int) {
    val isVisible: Boolean get() = count > 0

    operator fun plus(additional: Int): NotificationBadgeCount = NotificationBadgeCount((count + additional).coerceAtLeast(0))
    operator fun minus(removed: Int): NotificationBadgeCount = NotificationBadgeCount((count - removed).coerceAtLeast(0))
    operator fun compareTo(other: NotificationBadgeCount): Int = count.compareTo(other.count)

    fun formatDisplay(): String = when {
        count <= 0 -> ""
        count > 9 -> "9+"
        else -> count.toString()
    }
}

/**
 * Bitmask Flags for High-Performance Header State Engineering.
 */
@JvmInline
value class HeaderBitmask(val raw: Int) {
    operator fun contains(flag: Int): Boolean = (raw and flag) != 0
    operator fun plus(flag: Int): HeaderBitmask = HeaderBitmask(raw or flag)
    operator fun minus(flag: Int): HeaderBitmask = HeaderBitmask(raw and flag.inv())
    fun toggle(flag: Int): HeaderBitmask = HeaderBitmask(raw xor flag)

    companion object {
        const val FLAG_ONLINE = 1 shl 0
        const val FLAG_URGENT_LEAD = 1 shl 1
        const val FLAG_RADAR_ALERT = 1 shl 2
        const val FLAG_SEARCH_EXPANDED = 1 shl 3
        const val FLAG_TELEMETRY_EXPANDED = 1 shl 4
        const val FLAG_QUICK_TRAY_EXPANDED = 1 shl 5
        const val FLAG_FILMING_ACTIVE = 1 shl 6

        val DEFAULT = HeaderBitmask(FLAG_ONLINE or FLAG_URGENT_LEAD or FLAG_RADAR_ALERT)
    }
}

/**
 * Contextual Header Slot resolved dynamically from the active screen.
 * Morphs the central header tools using Kotlin pattern matching and rich metadata.
 */
sealed interface HeaderContextSlot {
    data class YouTubeContext(
        val streakWeeks: Int = 8,
        val queuedScriptsCount: Int = 3,
        val filmingReady: Boolean = true
    ) : HeaderContextSlot

    data class DealsContext(
        val pipelineAmount: Double = 34500.0,
        val urgentLeadsCount: Int = 1,
        val closingTargetAmount: Double = 50000.0
    ) : HeaderContextSlot

    data class SocialContext(
        val unreadVipCount: Int = 3,
        val pendingScheduledPosts: Int = 4
    ) : HeaderContextSlot

    data class VoiceContext(
        val isStreaming: Boolean = true,
        val latencyMs: Long = 280L
    ) : HeaderContextSlot

    data class ClientContext(
        val activeSprint: String = "Sprint 3",
        val completionPercentage: Int = 85
    ) : HeaderContextSlot

    data object Standard : HeaderContextSlot

    companion object {
        fun resolve(destination: StudioDestination, userRole: UserRole): HeaderContextSlot = when {
            userRole == UserRole.CLIENT -> ClientContext(activeSprint = "Sprint 3", completionPercentage = 85)
            destination == StudioDestination.Dashboard -> YouTubeContext(streakWeeks = 8, queuedScriptsCount = 3)
            destination in listOf(
                StudioDestination.Breakouts,
                StudioDestination.Ideas,
                StudioDestination.Scripts,
                StudioDestination.Goals,
                StudioDestination.YouTube,
                StudioDestination.Channel
            ) -> YouTubeContext(streakWeeks = 8, queuedScriptsCount = 3)

            destination in listOf(
                StudioDestination.Deals,
                StudioDestination.ClientPortal,
                StudioDestination.MediaKit,
                StudioDestination.Clients
            ) -> DealsContext(pipelineAmount = 34500.0, urgentLeadsCount = 1)

            destination in listOf(
                StudioDestination.Calendar,
                StudioDestination.Analytics,
                StudioDestination.Inbox,
                StudioDestination.Social,
                StudioDestination.Repurpose
            ) -> SocialContext(unreadVipCount = 3, pendingScheduledPosts = 4)

            destination == StudioDestination.Flow -> VoiceContext(isStreaming = true, latencyMs = 280L)
            else -> Standard
        }
    }
}

/**
 * Ultra-Polished Flat Design Mobile Header with Multi-Tier Contextual Telemetry.
 *
 * Strict Design Directives:
 * - Pure icon-driven ergonomic layout: Hamburger, Brand Logo, Contextual Micro-Pills, Alerts, Copilot, Avatar
 * - Zero "Creator Studio" title or text labels in the primary bar
 * - Pure flat design: solid colors, crisp 1dp bottom and inner borders, zero gradients, zero sci-fi glows
 * - Dynamic Contextual Morphing: Center slot morphs dynamically based on current screen
 * - Expandable Telemetry Ribbon: Real-time Ex-Google nomad sync, 4K bitrate, SLA metrics
 * - Expandable Hardware Quick-Actions Tray: Instant trigger for teleprompter, Wispr voice, Stripe deal link
 * - Interactive Flat Search Bar with quick scope filtering (All, Scripts, Deals, VIP, Ideas)
 * - Workspace / Identity Popover: Fast switching between Studio, Client Mode, and Public Guest
 */
@Composable
fun StudioHeader(
    currentDestination: StudioDestination,
    userRole: UserRole,
    themeMode: ThemeMode,
    unreadNotifications: Int = 3,
    hasUrgentLead: Boolean = true,
    hasLaunchWatchdogAlert: Boolean = true,
    networkLatencyMs: Long = 24L,
    onAction: (HeaderAction) -> Unit,
    modifier: Modifier = Modifier
) {
    var isProfileMenuOpen by remember { mutableStateOf(false) }
    var isInlineSearchOpen by remember { mutableStateOf(false) }
    var isTelemetryRibbonOpen by remember { mutableStateOf(false) }
    var isQuickActionsTrayOpen by remember { mutableStateOf(false) }
    var selectedSearchScope by remember { mutableStateOf<SearchScope>(SearchScope.All) }
    var inlineSearchQuery by remember { mutableStateOf("") }
    val focusManager = LocalFocusManager.current

    val badge = remember(unreadNotifications) { NotificationBadgeCount(unreadNotifications) }
    val latency = remember(networkLatencyMs) { LatencyMs(networkLatencyMs) }
    val contextSlot = remember(currentDestination, userRole) {
        HeaderContextSlot.resolve(currentDestination, userRole)
    }

    Surface(
        modifier = modifier.fillMaxWidth(),
        color = MaterialTheme.colorScheme.surface,
        tonalElevation = 0.dp,
        shadowElevation = 0.dp,
        border = BorderStroke(
            width = 1.dp,
            color = MaterialTheme.colorScheme.outline.copy(alpha = 0.35f)
        )
    ) {
        Column(modifier = Modifier.fillMaxWidth()) {

            // ================= TIER 1: PRIMARY 52DP ICON-ONLY ERGONOMIC ROW =================
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(52.dp)
                    .padding(horizontal = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                // ---------- LEFT CLUSTER: DRAWER, BRAND LOGO, TELEMETRY DOT ----------
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    // Navigation Drawer Hamburger
                    FlatHeaderIconButton(
                        icon = Icons.Default.Menu,
                        contentDescription = "Open Navigation Menu",
                        onClick = { onAction(HeaderAction.ToggleDrawer) }
                    )

                    // Flat SVG Brand Mark Icon Button (No Text)
                    Box(
                        modifier = Modifier
                            .size(36.dp)
                            .clip(RoundedCornerShape(8.dp))
                            .background(StudioCoral)
                            .clickable { onAction(HeaderAction.Navigate(StudioDestination.Dashboard)) },
                        contentAlignment = Alignment.Center
                    ) {
                        StudioLogo(size = 20.dp, tint = Color.White)
                    }

                    // Interactive Nomad Sync Dot / Pill (Tapping toggles Telemetry Ribbon)
                    Surface(
                        shape = RoundedCornerShape(12.dp),
                        color = latency.statusColor().copy(alpha = 0.12f),
                        border = BorderStroke(1.dp, latency.statusColor().copy(alpha = 0.3f)),
                        modifier = Modifier
                            .clip(RoundedCornerShape(12.dp))
                            .clickable {
                                isTelemetryRibbonOpen = !isTelemetryRibbonOpen
                                isQuickActionsTrayOpen = false
                                onAction(HeaderAction.ToggleTelemetry)
                            }
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 5.dp, vertical = 3.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(6.dp)
                                    .clip(CircleShape)
                                    .background(latency.statusColor())
                            )
                            Text(
                                text = latency.formatted(),
                                fontSize = 9.sp,
                                fontFamily = FontFamily.Monospace,
                                fontWeight = FontWeight.Bold,
                                color = latency.statusColor()
                            )
                        }
                    }
                }

                // ---------- CENTER CLUSTER: CONTEXTUAL SLOTS OR INLINE SEARCH ----------
                AnimatedContent(
                    targetState = isInlineSearchOpen,
                    transitionSpec = { fadeIn(tween(140)) togetherWith fadeOut(tween(90)) },
                    modifier = Modifier.weight(1f, fill = false).padding(horizontal = 4.dp)
                ) { searchOpen ->
                    if (searchOpen) {
                        // High-Efficiency Flat Search Bar
                        Surface(
                            shape = RoundedCornerShape(8.dp),
                            color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.65f),
                            border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.45f)),
                            modifier = Modifier
                                .widthIn(min = 160.dp, max = 240.dp)
                                .height(36.dp)
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 8.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(
                                    Icons.Default.Search,
                                    contentDescription = null,
                                    modifier = Modifier.size(15.dp),
                                    tint = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                                Spacer(Modifier.width(6.dp))
                                OutlinedTextField(
                                    value = inlineSearchQuery,
                                    onValueChange = {
                                        inlineSearchQuery = it
                                        onAction(HeaderAction.SearchQueryChanged(it))
                                    },
                                    placeholder = {
                                        Text(
                                            "Filter ${selectedSearchScope.label}...",
                                            fontSize = 11.sp,
                                            maxLines = 1,
                                            overflow = TextOverflow.Ellipsis
                                        )
                                    },
                                    modifier = Modifier.weight(1f),
                                    singleLine = true,
                                    colors = OutlinedTextFieldDefaults.colors(
                                        focusedBorderColor = Color.Transparent,
                                        unfocusedBorderColor = Color.Transparent
                                    ),
                                    keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
                                    keyboardActions = KeyboardActions(onSearch = { focusManager.clearFocus() })
                                )
                                IconButton(
                                    onClick = {
                                        isInlineSearchOpen = false
                                        inlineSearchQuery = ""
                                        onAction(HeaderAction.SearchQueryChanged(""))
                                    },
                                    modifier = Modifier.size(24.dp)
                                ) {
                                    Icon(
                                        Icons.Default.Close,
                                        contentDescription = "Close Search",
                                        modifier = Modifier.size(14.dp),
                                        tint = MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                }
                            }
                        }
                    } else {
                        // Contextual Morphing Center Micro-Pills
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            // Search Trigger Icon
                            FlatHeaderIconButton(
                                icon = Icons.Default.Search,
                                contentDescription = "Quick Search",
                                onClick = { isInlineSearchOpen = true },
                                size = 32.dp
                            )

                            // Screen Context Micro-Tools
                            when (contextSlot) {
                                is HeaderContextSlot.YouTubeContext -> {
                                    // 4K Prompter Trigger Pill
                                    FlatContextPill(
                                        icon = Icons.Default.Videocam,
                                        label = "4K Film",
                                        tint = StudioCoral,
                                        onClick = { onAction(HeaderAction.LaunchPrompter) }
                                    )
                                    // Streak Counter Pill
                                    FlatContextPill(
                                        icon = Icons.Default.LocalFireDepartment,
                                        label = "W${contextSlot.streakWeeks}",
                                        tint = StudioAmber,
                                        onClick = { onAction(HeaderAction.Navigate(StudioDestination.Goals)) }
                                    )
                                }

                                is HeaderContextSlot.DealsContext -> {
                                    // Pipeline Deal Total Pill
                                    FlatContextPill(
                                        icon = Icons.Default.AttachMoney,
                                        label = "\$34.5k",
                                        tint = StudioEmerald,
                                        badgeText = if (contextSlot.urgentLeadsCount > 0) "1 Hot" else null,
                                        onClick = { onAction(HeaderAction.OpenSpeedToLead) }
                                    )
                                }

                                is HeaderContextSlot.SocialContext -> {
                                    // VIP Inbox Pill
                                    FlatContextPill(
                                        icon = Icons.Default.Inbox,
                                        label = "${contextSlot.unreadVipCount} VIP",
                                        tint = StudioViolet,
                                        badgeText = "${contextSlot.pendingScheduledPosts} Q'd",
                                        onClick = { onAction(HeaderAction.Navigate(StudioDestination.Inbox)) }
                                    )
                                }

                                is HeaderContextSlot.VoiceContext -> {
                                    // Wispr Live Streaming Mic Pill
                                    FlatContextPill(
                                        icon = Icons.Default.GraphicEq,
                                        label = "Wispr 280ms",
                                        tint = StudioCoral,
                                        onClick = { onAction(HeaderAction.OpenDictate) }
                                    )
                                }

                                is HeaderContextSlot.ClientContext -> {
                                    // Active Sprint Pill
                                    FlatContextPill(
                                        icon = Icons.Default.TaskAlt,
                                        label = "${contextSlot.activeSprint}: ${contextSlot.completionPercentage}%",
                                        tint = StudioOcean,
                                        onClick = { onAction(HeaderAction.Navigate(StudioDestination.ClientPortal)) }
                                    )
                                }

                                HeaderContextSlot.Standard -> {
                                    FlatContextPill(
                                        icon = Icons.Default.Videocam,
                                        label = "4K Rec",
                                        tint = StudioCoral,
                                        onClick = { onAction(HeaderAction.LaunchPrompter) }
                                    )
                                }
                            }
                        }
                    }
                }

                // ---------- RIGHT CLUSTER: ALERTS, HARDWARE TRAY, COPILOT, AVATAR ----------
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(3.dp)
                ) {
                    // Urgent Lead Alert Icon (Studio Role Only)
                    if (userRole == UserRole.STUDIO && hasUrgentLead) {
                        FlatHeaderAlertButton(
                            icon = Icons.Default.Bolt,
                            contentDescription = "Urgent Inbound Lead",
                            alertColor = StudioEmerald,
                            onClick = { onAction(HeaderAction.OpenSpeedToLead) }
                        )
                    }

                    // 120-Min Launch Radar Alert (Studio Role Only)
                    if (userRole == UserRole.STUDIO && hasLaunchWatchdogAlert) {
                        FlatHeaderAlertButton(
                            icon = Icons.Default.Speed,
                            contentDescription = "120-Min Launch Radar",
                            alertColor = StudioAmber,
                            onClick = { onAction(HeaderAction.OpenLaunchRadar) }
                        )
                    }

                    // Quick-Actions Hardware Drawer Toggle
                    FlatHeaderIconButton(
                        icon = if (isQuickActionsTrayOpen) Icons.Default.KeyboardArrowUp else Icons.Default.Widgets,
                        contentDescription = "Toggle Quick Actions",
                        onClick = {
                            isQuickActionsTrayOpen = !isQuickActionsTrayOpen
                            isTelemetryRibbonOpen = false
                            onAction(HeaderAction.ToggleQuickActions)
                        },
                        activeTint = if (isQuickActionsTrayOpen) StudioCoral else null
                    )

                    // Notifications Bell with Numerical Badge
                    Box {
                        FlatHeaderIconButton(
                            icon = Icons.Default.NotificationsNone,
                            contentDescription = "Notifications",
                            onClick = { onAction(HeaderAction.OpenNotifications) }
                        )
                        if (badge.isVisible) {
                            Surface(
                                shape = CircleShape,
                                color = StudioCoral,
                                modifier = Modifier
                                    .align(Alignment.TopEnd)
                                    .padding(top = 2.dp, end = 2.dp)
                            ) {
                                Text(
                                    text = badge.formatDisplay(),
                                    fontSize = 8.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Color.White,
                                    modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                                )
                            }
                        }
                    }

                    // Copilot AI Sparkles (Studio Role Only)
                    if (userRole == UserRole.STUDIO) {
                        FlatHeaderIconButton(
                            icon = Icons.Default.AutoAwesome,
                            contentDescription = "AI Copilot",
                            onClick = { onAction(HeaderAction.OpenCopilot) },
                            activeTint = StudioViolet
                        )
                    }

                    // User Avatar with Workspace & Profile Popover
                    Box {
                        Box(
                            modifier = Modifier
                                .size(34.dp)
                                .clip(RoundedCornerShape(8.dp))
                                .background(
                                    when (userRole) {
                                        UserRole.STUDIO -> StudioCoral
                                        UserRole.CLIENT -> StudioOcean
                                        UserRole.PUBLIC -> MaterialTheme.colorScheme.surfaceVariant
                                    }
                                )
                                .clickable {
                                    if (userRole == UserRole.PUBLIC) onAction(HeaderAction.OpenAuth)
                                    else isProfileMenuOpen = true
                                },
                            contentAlignment = Alignment.Center
                        ) {
                            Text(
                                text = when (userRole) {
                                    UserRole.STUDIO -> "D"
                                    UserRole.CLIENT -> "S"
                                    UserRole.PUBLIC -> "?"
                                },
                                fontWeight = FontWeight.Bold,
                                fontSize = 13.sp,
                                color = Color.White
                            )
                        }

                        // Profile & Workspace Popover Menu
                        DropdownMenu(
                            expanded = isProfileMenuOpen,
                            onDismissRequest = { isProfileMenuOpen = false },
                            modifier = Modifier
                                .width(280.dp)
                                .background(MaterialTheme.colorScheme.surface)
                                .border(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.4f), RoundedCornerShape(8.dp))
                        ) {
                            // User Info Header Card
                            Column(modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp)) {
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(28.dp)
                                            .clip(RoundedCornerShape(6.dp))
                                            .background(
                                                if (userRole == UserRole.STUDIO) StudioCoral else StudioOcean
                                            ),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Text(
                                            text = if (userRole == UserRole.STUDIO) "D" else "S",
                                            fontWeight = FontWeight.Bold,
                                            fontSize = 12.sp,
                                            color = Color.White
                                        )
                                    }
                                    Column {
                                        Text(
                                            text = if (userRole == UserRole.STUDIO) "Delia Lazarescu" else "ScaleWorks Inc.",
                                            fontWeight = FontWeight.Bold,
                                            fontSize = 13.sp,
                                            color = MaterialTheme.colorScheme.onSurface
                                        )
                                        Text(
                                            text = if (userRole == UserRole.STUDIO) "@Tech.Unicorn • Ex-Google" else "Client Portal Member",
                                            fontSize = 10.sp,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant
                                        )
                                    }
                                }

                                Spacer(Modifier.height(8.dp))

                                // Nomad Mode Status Pill
                                Surface(
                                    shape = RoundedCornerShape(4.dp),
                                    color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f),
                                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)),
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    Row(
                                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.SpaceBetween
                                    ) {
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Box(modifier = Modifier.size(6.dp).clip(CircleShape).background(StudioGoodLight))
                                            Spacer(Modifier.width(6.dp))
                                            Text(
                                                text = "HTTP/3 • ${latency.formatted()}",
                                                fontSize = 10.sp,
                                                fontFamily = FontFamily.Monospace,
                                                color = MaterialTheme.colorScheme.onSurface
                                            )
                                        }
                                        Text(
                                            text = "69 Countries Nomad",
                                            fontSize = 9.sp,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant
                                        )
                                    }
                                }
                            }

                            HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.3f))

                            // Theme Switcher Dock
                            Box(modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp)) {
                                ThemeSwitch(
                                    currentMode = themeMode,
                                    onModeSelected = { mode -> onAction(HeaderAction.ChangeTheme(mode)) }
                                )
                            }

                            HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.3f))

                            // Destination Shortcuts
                            DropdownMenuItem(
                                text = { Text("Account & Security", fontSize = 12.sp) },
                                leadingIcon = { Icon(Icons.Default.Lock, contentDescription = null, modifier = Modifier.size(16.dp)) },
                                onClick = {
                                    isProfileMenuOpen = false
                                    onAction(HeaderAction.Navigate(StudioDestination.Account))
                                }
                            )

                            if (userRole == UserRole.STUDIO) {
                                DropdownMenuItem(
                                    text = { Text("For You (Love Letters)", fontSize = 12.sp) },
                                    leadingIcon = { Icon(Icons.Default.Favorite, contentDescription = null, modifier = Modifier.size(16.dp), tint = StudioCoral) },
                                    onClick = {
                                        isProfileMenuOpen = false
                                        onAction(HeaderAction.Navigate(StudioDestination.Love))
                                    }
                                )

                                DropdownMenuItem(
                                    text = { Text("Studio Settings", fontSize = 12.sp) },
                                    leadingIcon = { Icon(Icons.Default.Settings, contentDescription = null, modifier = Modifier.size(16.dp)) },
                                    onClick = {
                                        isProfileMenuOpen = false
                                        onAction(HeaderAction.Navigate(StudioDestination.Settings))
                                    }
                                )
                            }

                            HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.3f))

                            DropdownMenuItem(
                                text = { Text("Sign Out", fontSize = 12.sp, color = MaterialTheme.colorScheme.error) },
                                leadingIcon = { Icon(Icons.AutoMirrored.Filled.Logout, contentDescription = null, modifier = Modifier.size(16.dp), tint = MaterialTheme.colorScheme.error) },
                                onClick = {
                                    isProfileMenuOpen = false
                                    onAction(HeaderAction.SignOut)
                                }
                            )
                        }
                    }
                }
            }

            // ================= TIER 2: SEARCH SCOPES BAR (WHEN SEARCH ACTIVE) =================
            AnimatedVisibility(
                visible = isInlineSearchOpen,
                enter = expandVertically(tween(160)) + fadeIn(tween(140)),
                exit = shrinkVertically(tween(120)) + fadeOut(tween(90))
            ) {
                Surface(
                    color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.35f),
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.2f)),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 8.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Text(
                            text = "SCOPE:",
                            fontSize = 9.sp,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        SearchScope.entries.forEach { scope ->
                            val isSelected = selectedSearchScope == scope
                            Surface(
                                shape = RoundedCornerShape(4.dp),
                                color = if (isSelected) StudioCoral else Color.Transparent,
                                border = BorderStroke(
                                    1.dp,
                                    if (isSelected) StudioCoral else MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)
                                ),
                                modifier = Modifier
                                    .clip(RoundedCornerShape(4.dp))
                                    .clickable {
                                        selectedSearchScope = scope
                                        onAction(HeaderAction.SelectSearchScope(scope))
                                    }
                            ) {
                                Row(
                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(3.dp)
                                ) {
                                    Icon(
                                        imageVector = scope.icon,
                                        contentDescription = null,
                                        modifier = Modifier.size(10.dp),
                                        tint = if (isSelected) Color.White else MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                    Text(
                                        text = scope.label,
                                        fontSize = 10.sp,
                                        fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal,
                                        color = if (isSelected) Color.White else MaterialTheme.colorScheme.onSurface
                                    )
                                }
                            }
                        }
                    }
                }
            }

            // ================= TIER 3: COLLAPSIBLE TELEMETRY RIBBON =================
            AnimatedVisibility(
                visible = isTelemetryRibbonOpen,
                enter = expandVertically(tween(180)) + fadeIn(tween(150)),
                exit = shrinkVertically(tween(140)) + fadeOut(tween(100))
            ) {
                Surface(
                    color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.45f),
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.25f)),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 10.dp, vertical = 8.dp),
                        verticalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Text(
                                text = "STUDIO HARDWARE & SYNC TELEMETRY",
                                fontSize = 9.sp,
                                fontWeight = FontWeight.Bold,
                                fontFamily = FontFamily.Monospace,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(4.dp),
                                modifier = Modifier
                                    .clip(RoundedCornerShape(4.dp))
                                    .clickable { onAction(HeaderAction.TriggerSyncFlush) }
                                    .padding(horizontal = 4.dp, vertical = 2.dp)
                            ) {
                                Icon(
                                    Icons.Default.Refresh,
                                    contentDescription = "Flush Cache",
                                    modifier = Modifier.size(11.dp),
                                    tint = StudioOcean
                                )
                                Text(
                                    text = "Flush Cache",
                                    fontSize = 9.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = StudioOcean
                                )
                            }
                        }

                        // 4 Flat Telemetry Cards
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            FlatTelemetryCard(
                                title = "Ex-Google Sync",
                                value = latency.formatted(),
                                subtitle = "HTTP/3 Nomad",
                                accentColor = latency.statusColor(),
                                modifier = Modifier.weight(1f)
                            )
                            FlatTelemetryCard(
                                title = "4K Bitrate",
                                value = "150 Mbps",
                                subtitle = "Rec.709 Ready",
                                accentColor = StudioCoral,
                                modifier = Modifier.weight(1f)
                            )
                            FlatTelemetryCard(
                                title = "Speed-to-Lead",
                                value = "< 5 min",
                                subtitle = "1 Pending Lead",
                                accentColor = StudioEmerald,
                                modifier = Modifier.weight(1f)
                            )
                            FlatTelemetryCard(
                                title = "Launch CTR",
                                value = "7.8%",
                                subtitle = "+1.4% Bandit",
                                accentColor = StudioAmber,
                                modifier = Modifier.weight(1f)
                            )
                        }
                    }
                }
            }

            // ================= TIER 4: COLLAPSIBLE HARDWARE QUICK-ACTIONS TRAY =================
            AnimatedVisibility(
                visible = isQuickActionsTrayOpen,
                enter = expandVertically(tween(180)) + fadeIn(tween(150)),
                exit = shrinkVertically(tween(140)) + fadeOut(tween(100))
            ) {
                Surface(
                    color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f),
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.25f)),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 8.dp, vertical = 6.dp),
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        FlatQuickActionButton(
                            icon = Icons.Default.Videocam,
                            label = "4K Prompter",
                            tint = StudioCoral,
                            onClick = {
                                isQuickActionsTrayOpen = false
                                onAction(HeaderAction.LaunchPrompter)
                            },
                            modifier = Modifier.weight(1f)
                        )
                        FlatQuickActionButton(
                            icon = Icons.Default.GraphicEq,
                            label = "Wispr Dictate",
                            tint = StudioOcean,
                            onClick = {
                                isQuickActionsTrayOpen = false
                                onAction(HeaderAction.OpenDictate)
                            },
                            modifier = Modifier.weight(1f)
                        )
                        FlatQuickActionButton(
                            icon = Icons.Default.Bolt,
                            label = "$12.5k Deal",
                            tint = StudioEmerald,
                            onClick = {
                                isQuickActionsTrayOpen = false
                                onAction(HeaderAction.OpenSpeedToLead)
                            },
                            modifier = Modifier.weight(1f)
                        )
                        FlatQuickActionButton(
                            icon = Icons.Default.Speed,
                            label = "Radar Watch",
                            tint = StudioAmber,
                            onClick = {
                                isQuickActionsTrayOpen = false
                                onAction(HeaderAction.OpenLaunchRadar)
                            },
                            modifier = Modifier.weight(1f)
                        )
                    }
                }
            }
        }
    }
}

/**
 * Micro Flat Telemetry Card for Expandable Sub-bar.
 */
@Composable
private fun FlatTelemetryCard(
    title: String,
    value: String,
    subtitle: String,
    accentColor: Color,
    modifier: Modifier = Modifier
) {
    Surface(
        shape = RoundedCornerShape(4.dp),
        color = MaterialTheme.colorScheme.surface,
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.25f)),
        modifier = modifier
    ) {
        Column(modifier = Modifier.padding(6.dp)) {
            Text(
                text = title,
                fontSize = 8.sp,
                fontWeight = FontWeight.Medium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
            Text(
                text = value,
                fontSize = 11.sp,
                fontWeight = FontWeight.Bold,
                fontFamily = FontFamily.Monospace,
                color = accentColor,
                maxLines = 1
            )
            Text(
                text = subtitle,
                fontSize = 8.sp,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}

/**
 * Flat Quick-Action Trigger Button for Hardware Tray.
 */
@Composable
private fun FlatQuickActionButton(
    icon: ImageVector,
    label: String,
    tint: Color,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Surface(
        shape = RoundedCornerShape(6.dp),
        color = tint.copy(alpha = 0.10f),
        border = BorderStroke(1.dp, tint.copy(alpha = 0.35f)),
        modifier = modifier
            .clip(RoundedCornerShape(6.dp))
            .clickable(onClick = onClick)
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 6.dp, vertical = 6.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.Center
        ) {
            Icon(icon, contentDescription = null, tint = tint, modifier = Modifier.size(13.dp))
            Spacer(Modifier.width(4.dp))
            Text(
                text = label,
                fontSize = 9.sp,
                fontWeight = FontWeight.Bold,
                color = tint,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}

/**
 * Clean Flat Contextual Chip for Center Header Slot.
 */
@Composable
private fun FlatContextPill(
    icon: ImageVector,
    label: String,
    tint: Color,
    badgeText: String? = null,
    onClick: () -> Unit
) {
    Surface(
        shape = RoundedCornerShape(6.dp),
        color = tint.copy(alpha = 0.12f),
        border = BorderStroke(1.dp, tint.copy(alpha = 0.35f)),
        modifier = Modifier
            .clip(RoundedCornerShape(6.dp))
            .clickable(onClick = onClick)
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 6.dp, vertical = 4.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(icon, contentDescription = null, tint = tint, modifier = Modifier.size(13.dp))
            Spacer(Modifier.width(4.dp))
            Text(
                text = label,
                fontSize = 10.sp,
                fontWeight = FontWeight.Bold,
                color = tint
            )
            if (badgeText != null) {
                Spacer(Modifier.width(4.dp))
                Surface(
                    shape = RoundedCornerShape(3.dp),
                    color = tint,
                    modifier = Modifier.padding(0.dp)
                ) {
                    Text(
                        text = badgeText,
                        fontSize = 7.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White,
                        modifier = Modifier.padding(horizontal = 3.dp, vertical = 1.dp)
                    )
                }
            }
        }
    }
}

/**
 * Standard Flat Icon Button for Header Actions (Pure Flat, 0dp Elevation).
 */
@Composable
private fun FlatHeaderIconButton(
    icon: ImageVector,
    contentDescription: String,
    onClick: () -> Unit,
    activeTint: Color? = null,
    size: Dp = 36.dp
) {
    Box(
        modifier = Modifier
            .size(size)
            .clip(RoundedCornerShape(8.dp))
            .clickable(onClick = onClick),
        contentAlignment = Alignment.Center
    ) {
        Icon(
            imageVector = icon,
            contentDescription = contentDescription,
            modifier = Modifier.size(19.dp),
            tint = activeTint ?: MaterialTheme.colorScheme.onSurface
        )
    }
}

/**
 * Flat Header Alert Button with Colored Alert Dot Indicator.
 */
@Composable
private fun FlatHeaderAlertButton(
    icon: ImageVector,
    contentDescription: String,
    alertColor: Color,
    onClick: () -> Unit,
    size: Dp = 36.dp
) {
    Box(
        modifier = Modifier
            .size(size)
            .clip(RoundedCornerShape(8.dp))
            .clickable(onClick = onClick),
        contentAlignment = Alignment.Center
    ) {
        Icon(
            imageVector = icon,
            contentDescription = contentDescription,
            modifier = Modifier.size(19.dp),
            tint = alertColor
        )
        Box(
            modifier = Modifier
                .size(6.dp)
                .clip(CircleShape)
                .background(alertColor)
                .align(Alignment.TopEnd)
                .padding(top = 2.dp, end = 2.dp)
        )
    }
}
