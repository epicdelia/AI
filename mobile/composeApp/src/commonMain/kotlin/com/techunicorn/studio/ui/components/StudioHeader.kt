package com.techunicorn.studio.ui.components

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.models.UserRole
import com.techunicorn.studio.ui.navigation.StudioDestination
import com.techunicorn.studio.ui.theme.*

/**
 * Advanced Kotlin sealed interface representing all discrete user actions dispatched by the header.
 * Encapsulates UI events in a type-safe, exhaustive structure.
 */
sealed interface HeaderAction {
    data object ToggleDrawer : HeaderAction
    data object OpenOmnibar : HeaderAction
    data object OpenNotifications : HeaderAction
    data object OpenCopilot : HeaderAction
    data object OpenSpeedToLead : HeaderAction
    data object OpenLaunchRadar : HeaderAction
    data class Navigate(val destination: StudioDestination) : HeaderAction
    data class ChangeTheme(val mode: ThemeMode) : HeaderAction
    data object SignOut : HeaderAction
    data object OpenAuth : HeaderAction
}

/**
 * Kotlin Inline Value Class for unread notification count badge rendering.
 * Provides zero-allocation formatting helpers.
 */
@JvmInline
value class NotificationBadgeCount(val count: Int) {
    val isVisible: Boolean get() = count > 0
    fun formatDisplay(): String = if (count > 9) "9+" else count.toString()
}

/**
 * Ultra Mobile-Friendly Header with Advanced Kotlin Features:
 * - Single-row responsive layout eliminating mobile horizontal crowding
 * - Official isometric SVG StudioLogo (replacing legacy emojis)
 * - Quick-action mobile search pill & compact touch targets (minimum 44dp)
 * - Live Speed-to-Lead and 120-Minute Radar indicator chips
 * - Compact horizontal sub-rail with smooth animated indicator
 */
@Composable
fun StudioHeader(
    currentDestination: StudioDestination,
    userRole: UserRole,
    themeMode: ThemeMode,
    unreadNotifications: Int = 3,
    hasUrgentLead: Boolean = true,
    hasLaunchWatchdogAlert: Boolean = true,
    onAction: (HeaderAction) -> Unit
) {
    var isProfileMenuOpen by remember { mutableStateOf(false) }
    val badge = remember(unreadNotifications) { NotificationBadgeCount(unreadNotifications) }

    Surface(
        modifier = Modifier.fillMaxWidth(),
        color = MaterialTheme.colorScheme.surface,
        tonalElevation = 3.dp,
        shadowElevation = 2.dp
    ) {
        Column(modifier = Modifier.fillMaxWidth()) {
            // Main Top Bar: Single, balanced mobile row
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(56.dp)
                    .padding(horizontal = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                // Left: Navigation Drawer + Official SVG Logo + Role Chip
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.weight(1f, fill = false)
                ) {
                    IconButton(
                        onClick = { onAction(HeaderAction.ToggleDrawer) },
                        modifier = Modifier.size(40.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Menu,
                            contentDescription = "Open Navigation Menu",
                            tint = MaterialTheme.colorScheme.onSurface
                        )
                    }

                    Spacer(Modifier.width(2.dp))

                    // Brand Mark with Official SVG Logo
                    Box(
                        modifier = Modifier
                            .size(36.dp)
                            .clip(RoundedCornerShape(10.dp))
                            .background(
                                Brush.linearGradient(
                                    listOf(StudioAccentDark, StudioVioletDark)
                                )
                            )
                            .clickable { onAction(HeaderAction.Navigate(StudioDestination.Dashboard)) },
                        contentAlignment = Alignment.Center
                    ) {
                        StudioLogo(size = 22.dp, useGradient = true)
                    }

                    Spacer(Modifier.width(8.dp))

                    Column(
                        modifier = Modifier.clickable { onAction(HeaderAction.Navigate(StudioDestination.Dashboard)) }
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(
                                text = "Creator Studio",
                                fontWeight = FontWeight.Bold,
                                fontSize = 14.sp,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                                color = MaterialTheme.colorScheme.onSurface
                            )
                            Spacer(Modifier.width(5.dp))
                            Surface(
                                shape = RoundedCornerShape(4.dp),
                                color = when (userRole) {
                                    UserRole.STUDIO -> MaterialTheme.colorScheme.primaryContainer
                                    UserRole.CLIENT -> MaterialTheme.colorScheme.secondaryContainer
                                    UserRole.PUBLIC -> MaterialTheme.colorScheme.surfaceVariant
                                }
                            ) {
                                Text(
                                    text = when (userRole) {
                                        UserRole.STUDIO -> "PRO"
                                        UserRole.CLIENT -> "CLIENT"
                                        UserRole.PUBLIC -> "PUBLIC"
                                    },
                                    fontSize = 8.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = when (userRole) {
                                        UserRole.STUDIO -> MaterialTheme.colorScheme.primary
                                        UserRole.CLIENT -> MaterialTheme.colorScheme.secondary
                                        UserRole.PUBLIC -> MaterialTheme.colorScheme.onSurfaceVariant
                                    },
                                    modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                                )
                            }
                        }
                        Text(
                            text = if (userRole == UserRole.STUDIO) "@Tech.Unicorn" else "Client Portal",
                            fontSize = 10.sp,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }

                // Right: Touch-Optimized Action Bar
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    // Mobile Search Action Pill
                    IconButton(
                        onClick = { onAction(HeaderAction.OpenOmnibar) },
                        modifier = Modifier
                            .size(36.dp)
                            .clip(CircleShape)
                            .background(MaterialTheme.colorScheme.surfaceVariant)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Search,
                            contentDescription = "Search",
                            modifier = Modifier.size(18.dp),
                            tint = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }

                    // Urgent Lead Radar Alert Button (Speed-to-Lead)
                    if (userRole == UserRole.STUDIO && hasUrgentLead) {
                        Surface(
                            modifier = Modifier
                                .clip(RoundedCornerShape(16.dp))
                                .border(1.dp, StudioEmerald.copy(alpha = 0.5f), RoundedCornerShape(16.dp))
                                .clickable { onAction(HeaderAction.OpenSpeedToLead) },
                            color = StudioEmerald.copy(alpha = 0.15f)
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 7.dp, vertical = 4.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(7.dp)
                                        .clip(CircleShape)
                                        .background(StudioEmerald)
                                )
                                Spacer(Modifier.width(4.dp))
                                Text(
                                    text = "\$12.5k Lead",
                                    fontSize = 10.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = StudioEmerald
                                )
                            }
                        }
                    }

                    // Notifications Bell with Badge
                    IconButton(
                        onClick = { onAction(HeaderAction.OpenNotifications) },
                        modifier = Modifier.size(36.dp)
                    ) {
                        BadgedBox(
                            badge = {
                                if (badge.isVisible) {
                                    Badge(
                                        containerColor = StudioCoral,
                                        contentColor = Color.White
                                    ) {
                                        Text(badge.formatDisplay(), fontSize = 8.sp, fontWeight = FontWeight.Bold)
                                    }
                                }
                            }
                        ) {
                            Icon(
                                imageVector = Icons.Default.NotificationsNone,
                                contentDescription = "Notifications",
                                modifier = Modifier.size(20.dp),
                                tint = MaterialTheme.colorScheme.onSurface
                            )
                        }
                    }

                    // Copilot AI Sparkles
                    if (userRole == UserRole.STUDIO) {
                        IconButton(
                            onClick = { onAction(HeaderAction.OpenCopilot) },
                            modifier = Modifier
                                .size(36.dp)
                                .clip(CircleShape)
                                .background(StudioViolet.copy(alpha = 0.15f))
                        ) {
                            Icon(
                                imageVector = Icons.Default.AutoAwesome,
                                contentDescription = "Copilot",
                                modifier = Modifier.size(18.dp),
                                tint = StudioViolet
                            )
                        }
                    }

                    // User Profile Menu / Avatar
                    Box {
                        Box(
                            modifier = Modifier
                                .size(34.dp)
                                .clip(CircleShape)
                                .background(
                                    when (userRole) {
                                        UserRole.STUDIO -> Brush.linearGradient(listOf(StudioCoral, StudioViolet))
                                        UserRole.CLIENT -> Brush.linearGradient(listOf(StudioOcean, StudioViolet))
                                        UserRole.PUBLIC -> Brush.linearGradient(listOf(MaterialTheme.colorScheme.surfaceVariant, MaterialTheme.colorScheme.outline))
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

                        // Profile Dropdown
                        DropdownMenu(
                            expanded = isProfileMenuOpen,
                            onDismissRequest = { isProfileMenuOpen = false },
                            modifier = Modifier
                                .width(260.dp)
                                .background(MaterialTheme.colorScheme.surface)
                                .border(1.dp, MaterialTheme.colorScheme.outline, RoundedCornerShape(12.dp))
                        ) {
                            Column(modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp)) {
                                Text(
                                    text = if (userRole == UserRole.STUDIO) "Delia Lazarescu" else "ScaleWorks Inc.",
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 14.sp,
                                    color = MaterialTheme.colorScheme.onSurface
                                )
                                Text(
                                    text = if (userRole == UserRole.STUDIO) "delia@techunicorn.ai" else "client@scaleworks.io",
                                    fontSize = 11.sp,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }

                            Divider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.4f))

                            // Theme Selector
                            Box(modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp)) {
                                ThemeSwitch(
                                    currentMode = themeMode,
                                    onModeSelected = { mode -> onAction(HeaderAction.ChangeTheme(mode)) }
                                )
                            }

                            Divider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.4f))

                            DropdownMenuItem(
                                text = { Text("Account & Security", fontSize = 13.sp) },
                                leadingIcon = { Icon(Icons.Default.Lock, contentDescription = null, modifier = Modifier.size(16.dp)) },
                                onClick = {
                                    isProfileMenuOpen = false
                                    onAction(HeaderAction.Navigate(StudioDestination.Account))
                                }
                            )

                            if (userRole == UserRole.STUDIO) {
                                DropdownMenuItem(
                                    text = { Text("For You (Love Letters)", fontSize = 13.sp) },
                                    leadingIcon = { Icon(Icons.Default.Favorite, contentDescription = null, modifier = Modifier.size(16.dp), tint = StudioCoral) },
                                    onClick = {
                                        isProfileMenuOpen = false
                                        onAction(HeaderAction.Navigate(StudioDestination.Love))
                                    }
                                )

                                DropdownMenuItem(
                                    text = { Text("Launch Velocity Radar", fontSize = 13.sp) },
                                    leadingIcon = { Icon(Icons.Default.Speed, contentDescription = null, modifier = Modifier.size(16.dp), tint = StudioAmber) },
                                    onClick = {
                                        isProfileMenuOpen = false
                                        onAction(HeaderAction.OpenLaunchRadar)
                                    }
                                )

                                DropdownMenuItem(
                                    text = { Text("Studio Settings", fontSize = 13.sp) },
                                    leadingIcon = { Icon(Icons.Default.Settings, contentDescription = null, modifier = Modifier.size(16.dp)) },
                                    onClick = {
                                        isProfileMenuOpen = false
                                        onAction(HeaderAction.Navigate(StudioDestination.Settings))
                                    }
                                )
                            }

                            Divider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.4f))

                            DropdownMenuItem(
                                text = { Text("Sign Out", fontSize = 13.sp, color = MaterialTheme.colorScheme.error) },
                                leadingIcon = { Icon(Icons.Default.Logout, contentDescription = null, modifier = Modifier.size(16.dp), tint = MaterialTheme.colorScheme.error) },
                                onClick = {
                                    isProfileMenuOpen = false
                                    onAction(HeaderAction.SignOut)
                                }
                            )
                        }
                    }
                }
            }

            // Compact Navigation Strip (Horizontally Scrollable Chips)
            if (userRole != UserRole.PUBLIC) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState())
                        .padding(horizontal = 10.dp, vertical = 4.dp),
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    val quickSections = listOf(
                        "Today" to StudioDestination.Dashboard,
                        "YouTube" to StudioDestination.Breakouts,
                        "Filming Prompter" to StudioDestination.Breakouts, // Triggers camera prompter
                        "Social Queue" to StudioDestination.Calendar,
                        "Deals ($34.5k)" to StudioDestination.Deals,
                        "Dictate" to StudioDestination.Flow,
                        "Snippets" to StudioDestination.Snippets,
                        "Agents" to StudioDestination.Agents
                    )

                    quickSections.forEach { (label, target) ->
                        val isSelected = currentDestination == target
                        Surface(
                            shape = RoundedCornerShape(12.dp),
                            color = if (isSelected) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.6f),
                            modifier = Modifier
                                .clip(RoundedCornerShape(12.dp))
                                .clickable { onAction(HeaderAction.Navigate(target)) }
                        ) {
                            Text(
                                text = label,
                                fontSize = 11.sp,
                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                color = if (isSelected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.padding(horizontal = 9.dp, vertical = 4.dp)
                            )
                        }
                    }
                }
            }

            Divider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.35f))
        }
    }
}
