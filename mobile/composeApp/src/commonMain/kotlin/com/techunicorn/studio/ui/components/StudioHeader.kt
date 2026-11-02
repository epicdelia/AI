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
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
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
    data class Navigate(val destination: StudioDestination) : HeaderAction
    data class ChangeTheme(val mode: ThemeMode) : HeaderAction
    data object SignOut : HeaderAction
    data object OpenAuth : HeaderAction
}

/**
 * Kotlin Inline Value Class for Header Badges with arithmetic operator overloading.
 */
@JvmInline
value class NotificationBadgeCount(val count: Int) {
    val isVisible: Boolean get() = count > 0

    operator fun plus(additional: Int): NotificationBadgeCount = NotificationBadgeCount(count + additional)
    operator fun compareTo(other: NotificationBadgeCount): Int = count.compareTo(other.count)

    fun formatDisplay(): String = when {
        count <= 0 -> ""
        count > 9 -> "9+"
        else -> count.toString()
    }
}

/**
 * Keyed Identifier for Header Icon Slots.
 */
@JvmInline
value class HeaderIconSlot(val key: String)

/**
 * Pure Flat Design Icon-Only Mobile Header.
 *
 * Strict Design Directives:
 * - Completely removed all "Creator Studio" text and title labels
 * - Strict flat design: solid colors, crisp 1dp bottom border, zero gradients, zero sci-fi glowing effects
 * - Pure icon-driven ergonomic layout: Hamburger, Brand Logo, Quick Tools, Alerts, Copilot, and Avatar
 * - Full accessibility with semantic touch targets and tooltips
 */
@Composable
fun StudioHeader(
    currentDestination: StudioDestination,
    userRole: UserRole,
    themeMode: ThemeMode,
    unreadNotifications: Int = 3,
    hasUrgentLead: Boolean = true,
    hasLaunchWatchdogAlert: Boolean = true,
    onAction: (HeaderAction) -> Unit,
    modifier: Modifier = Modifier
) {
    var isProfileMenuOpen by remember { mutableStateOf(false) }
    val badge = remember(unreadNotifications) { NotificationBadgeCount(unreadNotifications) }

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
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .height(54.dp)
                .padding(horizontal = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            // ================= LEFT: DRAWER MENU & BRAND MARK =================
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

                // Flat SVG Brand Mark Icon Button
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
            }

            // ================= CENTER: QUICK ACTION TOOLS =================
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(4.dp)
            ) {
                // Search Omnibar Icon
                FlatHeaderIconButton(
                    icon = Icons.Default.Search,
                    contentDescription = "Quick Search (Omnibar)",
                    onClick = { onAction(HeaderAction.OpenOmnibar) }
                )

                if (userRole == UserRole.STUDIO) {
                    // Quick Filming Prompter Trigger
                    FlatHeaderIconButton(
                        icon = Icons.Default.Videocam,
                        contentDescription = "Camera Teleprompter",
                        onClick = { onAction(HeaderAction.LaunchPrompter) },
                        activeTint = StudioCoral
                    )

                    // Quick Voice Dictate Trigger
                    FlatHeaderIconButton(
                        icon = Icons.Default.Mic,
                        contentDescription = "Voice Dictate",
                        onClick = { onAction(HeaderAction.OpenDictate) },
                        activeTint = StudioViolet
                    )
                }
            }

            // ================= RIGHT: ALERTS, COPILOT & AVATAR =================
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(4.dp)
            ) {
                // Speed-to-Lead Alert Icon (Urgent Lead)
                if (userRole == UserRole.STUDIO && hasUrgentLead) {
                    FlatHeaderAlertButton(
                        icon = Icons.Default.Bolt,
                        contentDescription = "Urgent $12.5k Inbound Lead",
                        alertColor = StudioEmerald,
                        onClick = { onAction(HeaderAction.OpenSpeedToLead) }
                    )
                }

                // 120-Minute Launch Radar Alert Icon
                if (userRole == UserRole.STUDIO && hasLaunchWatchdogAlert) {
                    FlatHeaderAlertButton(
                        icon = Icons.Default.Speed,
                        contentDescription = "120-Min Launch Velocity Alert",
                        alertColor = StudioAmber,
                        onClick = { onAction(HeaderAction.OpenLaunchRadar) }
                    )
                }

                // Notifications Bell with Flat Badge
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

                // Copilot AI Sparkles (Studio Mode)
                if (userRole == UserRole.STUDIO) {
                    FlatHeaderIconButton(
                        icon = Icons.Default.AutoAwesome,
                        contentDescription = "AI Copilot",
                        onClick = { onAction(HeaderAction.OpenCopilot) },
                        activeTint = StudioViolet
                    )
                }

                // Flat User Avatar with Role Letter & Profile Dropdown
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

                    // Profile Dropdown
                    DropdownMenu(
                        expanded = isProfileMenuOpen,
                        onDismissRequest = { isProfileMenuOpen = false },
                        modifier = Modifier
                            .width(260.dp)
                            .background(MaterialTheme.colorScheme.surface)
                            .border(1.dp, MaterialTheme.colorScheme.outline, RoundedCornerShape(8.dp))
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

                        HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.4f))

                        Box(modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp)) {
                            ThemeSwitch(
                                currentMode = themeMode,
                                onModeSelected = { mode -> onAction(HeaderAction.ChangeTheme(mode)) }
                            )
                        }

                        HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.4f))

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
                                text = { Text("Studio Settings", fontSize = 13.sp) },
                                leadingIcon = { Icon(Icons.Default.Settings, contentDescription = null, modifier = Modifier.size(16.dp)) },
                                onClick = {
                                    isProfileMenuOpen = false
                                    onAction(HeaderAction.Navigate(StudioDestination.Settings))
                                }
                            )
                        }

                        HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.4f))

                        DropdownMenuItem(
                            text = { Text("Sign Out", fontSize = 13.sp, color = MaterialTheme.colorScheme.error) },
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
    }
}

/**
 * Standard Flat Icon Button for Header Actions (No Drop Shadows, Pure Flat).
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
            modifier = Modifier.size(20.dp),
            tint = activeTint ?: MaterialTheme.colorScheme.onSurface
        )
    }
}

/**
 * Flat Header Alert Button with Colored Notification Dot.
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
            modifier = Modifier.size(20.dp),
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
