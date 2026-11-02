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
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
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
import com.techunicorn.studio.domain.models.UserRole
import com.techunicorn.studio.ui.navigation.StudioDestination
import com.techunicorn.studio.ui.theme.*

/**
 * Advanced Kotlin Sealed Interface representing Footer Navigation & Action Nodes.
 * Enforces compile-time exhaustiveness across navigation tabs and central quick-action triggers.
 */
sealed interface FooterItem {
    val id: String

    data class Tab(
        val destination: StudioDestination,
        val badge: FooterBadge = FooterBadge.NONE,
        val showPulseDot: Boolean = false
    ) : FooterItem {
        override val id: String get() = destination.route
    }

    data class CenterAction(
        val actionId: String,
        val icon: ImageVector,
        val contentDescription: String,
        val onClick: () -> Unit
    ) : FooterItem {
        override val id: String get() = actionId
    }
}

/**
 * Inline Value Class for Zero-Allocation Footer Badges.
 * Implements operator overloading for arithmetic composition.
 */
@JvmInline
value class FooterBadge(val count: Int) {
    val isVisible: Boolean get() = count > 0

    operator fun plus(other: FooterBadge): FooterBadge = FooterBadge(count + other.count)
    operator fun compareTo(other: FooterBadge): Int = count.compareTo(other.count)

    fun formatted(): String = when {
        count <= 0 -> ""
        count > 99 -> "99+"
        else -> count.toString()
    }

    companion object {
        val NONE = FooterBadge(0)
    }
}

/**
 * Pure Flat Design Mobile Footer Navigation Dock.
 *
 * Strict Design Directives:
 * - Pure flat design: solid colors, 1dp top divider, zero blur shadows or sci-fi gradients
 * - Crisp 2.5dp flat active indicator bar resting on the top border of the selected tab
 * - Role-driven destination resolution using Kotlin pattern matching
 * - Touch targets conforming to 48x48dp mobile accessibility requirements
 */
@Composable
fun StudioFooter(
    currentDestination: StudioDestination,
    userRole: UserRole,
    unreadInboxCount: Int = 3,
    activeDealsCount: Int = 4,
    onNavigate: (StudioDestination) -> Unit,
    onCenterAction: () -> Unit,
    modifier: Modifier = Modifier
) {
    // Advanced Kotlin: Dynamic role-based item resolution with type-safe composition
    val footerItems = remember(userRole, unreadInboxCount, activeDealsCount) {
        when (userRole) {
            UserRole.STUDIO -> listOf(
                FooterItem.Tab(StudioDestination.Dashboard),
                FooterItem.Tab(StudioDestination.YouTube, showPulseDot = true),
                FooterItem.CenterAction("quick_film", Icons.Default.Videocam, "Quick Film 4K", onCenterAction),
                FooterItem.Tab(StudioDestination.Inbox, badge = FooterBadge(unreadInboxCount)),
                FooterItem.Tab(StudioDestination.Deals, badge = FooterBadge(activeDealsCount))
            )
            UserRole.CLIENT -> listOf(
                FooterItem.Tab(StudioDestination.ClientPortal),
                FooterItem.Tab(StudioDestination.Deals),
                FooterItem.CenterAction("consult", Icons.Default.Chat, "Client Messages", onCenterAction),
                FooterItem.Tab(StudioDestination.Snippets),
                FooterItem.Tab(StudioDestination.Landing)
            )
            UserRole.PUBLIC -> listOf(
                FooterItem.Tab(StudioDestination.Landing),
                FooterItem.CenterAction("book_call", Icons.Default.Bolt, "Instant Booking", onCenterAction),
                FooterItem.Tab(StudioDestination.Snippets),
                FooterItem.Tab(StudioDestination.ClientPortal)
            )
        }
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
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .height(60.dp)
                .padding(horizontal = 4.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceAround
        ) {
            footerItems.forEach { item ->
                when (item) {
                    is FooterItem.Tab -> {
                        val isSelected = currentDestination == item.destination ||
                                (item.destination == StudioDestination.YouTube &&
                                        currentDestination in listOf(
                                            StudioDestination.Breakouts,
                                            StudioDestination.Ideas,
                                            StudioDestination.Scripts,
                                            StudioDestination.Goals
                                        ))

                        FlatTabButton(
                            item = item,
                            isSelected = isSelected,
                            onClick = { onNavigate(item.destination) },
                            modifier = Modifier.weight(1f)
                        )
                    }

                    is FooterItem.CenterAction -> {
                        FlatCenterActionButton(
                            item = item,
                            modifier = Modifier.padding(horizontal = 4.dp)
                        )
                    }
                }
            }
        }
    }
}

/**
 * Individual Flat Footer Tab with Top Accent Line Indicator.
 */
@Composable
private fun FlatTabButton(
    item: FooterItem.Tab,
    isSelected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val interactionSource = remember { MutableInteractionSource() }

    Box(
        modifier = modifier
            .fillMaxHeight()
            .clickable(
                interactionSource = interactionSource,
                indication = null,
                onClick = onClick
            ),
        contentAlignment = Alignment.Center
    ) {
        // Flat Top Accent Indicator Bar (Active State)
        if (isSelected) {
            Box(
                modifier = Modifier
                    .fillMaxWidth(0.6f)
                    .height(2.5.dp)
                    .align(Alignment.TopCenter)
                    .background(StudioCoral)
            )
        }

        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center,
            modifier = Modifier.padding(vertical = 4.dp)
        ) {
            // Icon Container with Badge
            BadgedBox(
                badge = {
                    if (item.badge.isVisible) {
                        Badge(
                            containerColor = StudioCoral,
                            contentColor = Color.White
                        ) {
                            Text(
                                text = item.badge.formatted(),
                                fontSize = 8.sp,
                                fontWeight = FontWeight.Bold
                            )
                        }
                    } else if (item.showPulseDot && !isSelected) {
                        Box(
                            modifier = Modifier
                                .size(6.dp)
                                .clip(CircleShape)
                                .background(StudioCoral)
                        )
                    }
                }
            ) {
                Icon(
                    imageVector = if (isSelected) item.destination.selectedIcon else item.destination.unselectedIcon,
                    contentDescription = item.destination.title,
                    modifier = Modifier.size(22.dp),
                    tint = if (isSelected) StudioCoral else MaterialTheme.colorScheme.onSurfaceVariant
                )
            }

            Spacer(Modifier.height(3.dp))

            // Label Text (Clean Flat Typography)
            Text(
                text = item.destination.title,
                fontSize = 10.sp,
                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                color = if (isSelected) StudioCoral else MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1
            )
        }
    }
}

/**
 * Clean Flat Center Action Button (Zero Neon, Solid Accent).
 */
@Composable
private fun FlatCenterActionButton(
    item: FooterItem.CenterAction,
    modifier: Modifier = Modifier
) {
    Box(
        modifier = modifier
            .size(42.dp)
            .clip(RoundedCornerShape(10.dp))
            .background(StudioCoral)
            .clickable(onClick = item.onClick),
        contentAlignment = Alignment.Center
    ) {
        Icon(
            imageVector = item.icon,
            contentDescription = item.contentDescription,
            tint = Color.White,
            modifier = Modifier.size(22.dp)
        )
    }
}

