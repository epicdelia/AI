package com.techunicorn.studio.ui.components

import androidx.compose.animation.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.models.UserRole
import com.techunicorn.studio.ui.navigation.StudioDestination
import com.techunicorn.studio.ui.theme.*

/**
 * Advanced Kotlin Sealed Interface representing Structured Drawer Content.
 * Enforces compile-time exhaustiveness across section headers, navigable items, and separators.
 */
sealed interface DrawerEntry {
    data class SectionHeader(val title: String, val count: Int = 0) : DrawerEntry
    data class DestinationItem(
        val destination: StudioDestination,
        val badge: DrawerBadge = DrawerBadge.NONE,
        val highRoiTag: String? = null
    ) : DrawerEntry
    data object SectionDivider : DrawerEntry
}

/**
 * Kotlin Inline Value Class for High-ROI Navigation Badges.
 */
@JvmInline
value class DrawerBadge(val label: String) {
    val isVisible: Boolean get() = label.isNotBlank()

    companion object {
        val NONE = DrawerBadge("")
    }
}

/**
 * Kotlin Type-Safe Builder for Drawer Entries.
 * Builds structured entries dynamically based on UserRole and search queries.
 */
class DrawerEntriesBuilder(private val userRole: UserRole) {
    fun build(query: String = ""): List<DrawerEntry> {
        val rawSections: List<Pair<String, List<Pair<StudioDestination, DrawerBadge>>>> = when (userRole) {
            UserRole.CLIENT -> listOf(
                "Client Workspace" to listOf(
                    StudioDestination.ClientPortal to DrawerBadge.NONE,
                    StudioDestination.Deals to DrawerBadge("Active"),
                    StudioDestination.Snippets to DrawerBadge.NONE,
                    StudioDestination.Landing to DrawerBadge.NONE
                )
            )
            UserRole.PUBLIC -> listOf(
                "Explore" to listOf(
                    StudioDestination.Landing to DrawerBadge.NONE,
                    StudioDestination.Snippets to DrawerBadge("Free"),
                    StudioDestination.ClientPortal to DrawerBadge("Sign In")
                )
            )
            UserRole.STUDIO -> listOf(
                "Today & Pacing" to listOf(
                    StudioDestination.Dashboard to DrawerBadge.NONE,
                    StudioDestination.Flow to DrawerBadge("Wispr"),
                    StudioDestination.Love to DrawerBadge("💌")
                ),
                "YouTube Engine" to listOf(
                    StudioDestination.Breakouts to DrawerBadge("3.8x"),
                    StudioDestination.Ideas to DrawerBadge("AI"),
                    StudioDestination.Scripts to DrawerBadge("4K"),
                    StudioDestination.Goals to DrawerBadge("W8 Streak"),
                    StudioDestination.Channel to DrawerBadge.NONE,
                    StudioDestination.YouTube to DrawerBadge("Test")
                ),
                "Social & Deals" to listOf(
                    StudioDestination.Calendar to DrawerBadge.NONE,
                    StudioDestination.Analytics to DrawerBadge.NONE,
                    StudioDestination.Deals to DrawerBadge("\$34.5k"),
                    StudioDestination.MediaKit to DrawerBadge.NONE,
                    StudioDestination.Agents to DrawerBadge("Swarm"),
                    StudioDestination.Workflows to DrawerBadge.NONE,
                    StudioDestination.Inbox to DrawerBadge("3 VIP"),
                    StudioDestination.Repurpose to DrawerBadge.NONE,
                    StudioDestination.Bio to DrawerBadge.NONE
                ),
                "Creative Toolkit" to listOf(
                    StudioDestination.Website to DrawerBadge.NONE,
                    StudioDestination.Landing to DrawerBadge.NONE,
                    StudioDestination.Snippets to DrawerBadge("Carbon"),
                    StudioDestination.Toolkit to DrawerBadge.NONE,
                    StudioDestination.Chat to DrawerBadge.NONE,
                    StudioDestination.Library to DrawerBadge.NONE,
                    StudioDestination.Media to DrawerBadge.NONE
                ),
                "Operations & Admin" to listOf(
                    StudioDestination.Clients to DrawerBadge.NONE,
                    StudioDestination.Account to DrawerBadge.NONE,
                    StudioDestination.Connections to DrawerBadge("OAuth"),
                    StudioDestination.Brand to DrawerBadge.NONE,
                    StudioDestination.Settings to DrawerBadge.NONE
                )
            )
        }

        // If search filter query is present, flatten and match destinations
        if (query.isNotBlank()) {
            val trimmed = query.trim().lowercase()
            val matchedItems = rawSections.flatMap { it.second }.filter { (dest, _) ->
                dest.title.lowercase().contains(trimmed) || dest.section.lowercase().contains(trimmed)
            }
            return if (matchedItems.isEmpty()) {
                listOf(DrawerEntry.SectionHeader("No Matching Destinations"))
            } else {
                listOf(DrawerEntry.SectionHeader("Search Results (${matchedItems.size})")) +
                        matchedItems.map { (dest, badge) -> DrawerEntry.DestinationItem(dest, badge) }
            }
        }

        // Build formatted structured entries
        val result = mutableListOf<DrawerEntry>()
        rawSections.forEachIndexed { index, (headerTitle, items) ->
            if (index > 0) result.add(DrawerEntry.SectionDivider)
            result.add(DrawerEntry.SectionHeader(headerTitle, items.size))
            items.forEach { (dest, badge) ->
                result.add(DrawerEntry.DestinationItem(dest, badge))
            }
        }
        return result
    }
}

/**
 * Pure Flat Design Mobile Side Navigation Drawer.
 *
 * Strict Design Directives:
 * - Pure flat design: solid colors, 1dp dividing lines, zero sci-fi neon or blur shadows
 * - Real-time in-drawer search filter bar to eliminate navigation fatigue across 25+ features
 * - Flat left-indicator accent line for active selection state
 * - Flat footer dock with instant theme toggle and sign-out action
 */
@Composable
fun NavigationDrawerContent(
    currentDestination: StudioDestination,
    userRole: UserRole,
    onSelectDestination: (StudioDestination) -> Unit,
    onCloseDrawer: () -> Unit,
    themeMode: ThemeMode = ThemeMode.SYSTEM,
    onThemeChange: ((ThemeMode) -> Unit)? = null,
    onSignOut: (() -> Unit)? = null
) {
    var searchQuery by remember { mutableStateOf("") }
    val focusManager = LocalFocusManager.current
    val builder = remember(userRole) { DrawerEntriesBuilder(userRole) }
    val drawerEntries = remember(userRole, searchQuery) { builder.build(searchQuery) }

    Surface(
        modifier = Modifier
            .fillMaxHeight()
            .width(320.dp),
        color = MaterialTheme.colorScheme.surface,
        tonalElevation = 0.dp,
        shadowElevation = 0.dp,
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.35f))
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = 14.dp, vertical = 16.dp)
        ) {
            // ================= BRAND HEADER =================
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(36.dp)
                            .clip(RoundedCornerShape(8.dp))
                            .background(StudioCoral),
                        contentAlignment = Alignment.Center
                    ) {
                        StudioLogo(size = 20.dp, tint = Color.White)
                    }

                    Spacer(Modifier.width(10.dp))

                    Column {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(
                                text = "Creator Studio",
                                fontWeight = FontWeight.Bold,
                                fontSize = 15.sp,
                                color = MaterialTheme.colorScheme.onSurface
                            )
                            Spacer(Modifier.width(6.dp))
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
                            text = if (userRole == UserRole.STUDIO) "@Tech.Unicorn · Delia" else "Client Portal Access",
                            fontSize = 11.sp,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }

                IconButton(
                    onClick = onCloseDrawer,
                    modifier = Modifier.size(32.dp)
                ) {
                    Icon(
                        Icons.Default.Close,
                        contentDescription = "Close Drawer",
                        tint = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.size(18.dp)
                    )
                }
            }

            Spacer(Modifier.height(14.dp))

            // ================= REAL-TIME IN-DRAWER FILTER =================
            Surface(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(8.dp),
                color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f),
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.4f))
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 2.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        Icons.Default.Search,
                        contentDescription = null,
                        modifier = Modifier.size(16.dp),
                        tint = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    Spacer(Modifier.width(8.dp))
                    OutlinedTextField(
                        value = searchQuery,
                        onValueChange = { searchQuery = it },
                        placeholder = { Text("Filter 25+ studio pages...", fontSize = 12.sp) },
                        modifier = Modifier.weight(1f),
                        singleLine = true,
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Color.Transparent,
                            unfocusedBorderColor = Color.Transparent
                        ),
                        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Done),
                        keyboardActions = KeyboardActions(onDone = { focusManager.clearFocus() })
                    )
                    if (searchQuery.isNotEmpty()) {
                        IconButton(
                            onClick = { searchQuery = "" },
                            modifier = Modifier.size(24.dp)
                        ) {
                            Icon(
                                Icons.Default.Clear,
                                contentDescription = "Clear search",
                                modifier = Modifier.size(14.dp),
                                tint = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                }
            }

            Spacer(Modifier.height(12.dp))
            HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.35f))
            Spacer(Modifier.height(8.dp))

            // ================= STRUCTURED DRAWER LIST =================
            LazyColumn(
                modifier = Modifier.weight(1f),
                verticalArrangement = Arrangement.spacedBy(2.dp)
            ) {
                items(drawerEntries) { entry ->
                    when (entry) {
                        is DrawerEntry.SectionHeader -> {
                            Spacer(Modifier.height(6.dp))
                            Text(
                                text = entry.title.uppercase(),
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                letterSpacing = 1.1.sp,
                                modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                            )
                        }

                        is DrawerEntry.SectionDivider -> {
                            Spacer(Modifier.height(4.dp))
                            HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.25f))
                            Spacer(Modifier.height(4.dp))
                        }

                        is DrawerEntry.DestinationItem -> {
                            val isSelected = currentDestination == entry.destination ||
                                    (entry.destination == StudioDestination.YouTube &&
                                            currentDestination in listOf(
                                                StudioDestination.Breakouts,
                                                StudioDestination.Ideas,
                                                StudioDestination.Scripts,
                                                StudioDestination.Goals
                                            ))

                            FlatDrawerItemRow(
                                entry = entry,
                                isSelected = isSelected,
                                onClick = {
                                    onSelectDestination(entry.destination)
                                    onCloseDrawer()
                                }
                            )
                        }
                    }
                }
            }

            Spacer(Modifier.height(8.dp))
            HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.35f))
            Spacer(Modifier.height(10.dp))

            // ================= BOTTOM PROFILE & THEME DOCK =================
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.weight(1f)
                ) {
                    Box(
                        modifier = Modifier
                            .size(32.dp)
                            .clip(RoundedCornerShape(6.dp))
                            .background(if (userRole == UserRole.STUDIO) StudioCoral else StudioOcean),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = if (userRole == UserRole.STUDIO) "D" else "S",
                            fontWeight = FontWeight.Bold,
                            fontSize = 13.sp,
                            color = Color.White
                        )
                    }
                    Spacer(Modifier.width(8.dp))
                    Column {
                        Text(
                            text = if (userRole == UserRole.STUDIO) "Delia Lazarescu" else "ScaleWorks Inc.",
                            fontWeight = FontWeight.Bold,
                            fontSize = 12.sp,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                        Text(
                            text = "SQLite · Sync Active",
                            fontSize = 10.sp,
                            color = StudioGoodLight
                        )
                    }
                }

                // Theme Mode Switch (Light / Dark / System)
                if (onThemeChange != null) {
                    Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                        FlatThemeModeChip(
                            icon = Icons.Default.LightMode,
                            isSelected = themeMode == ThemeMode.LIGHT,
                            onClick = { onThemeChange(ThemeMode.LIGHT) }
                        )
                        FlatThemeModeChip(
                            icon = Icons.Default.DarkMode,
                            isSelected = themeMode == ThemeMode.DARK,
                            onClick = { onThemeChange(ThemeMode.DARK) }
                        )
                    }
                }

                // Sign Out Action Button
                if (onSignOut != null && userRole != UserRole.PUBLIC) {
                    IconButton(
                        onClick = onSignOut,
                        modifier = Modifier.size(30.dp)
                    ) {
                        Icon(
                            Icons.AutoMirrored.Filled.Logout,
                            contentDescription = "Sign Out",
                            tint = MaterialTheme.colorScheme.error,
                            modifier = Modifier.size(16.dp)
                        )
                    }
                }
            }
        }
    }
}

/**
 * Individual Flat Drawer Item Row with Flat Left Accent Line.
 */
@Composable
private fun FlatDrawerItemRow(
    entry: DrawerEntry.DestinationItem,
    isSelected: Boolean,
    onClick: () -> Unit
) {
    val interactionSource = remember { MutableInteractionSource() }

    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(6.dp))
            .clickable(
                interactionSource = interactionSource,
                indication = null,
                onClick = onClick
            ),
        shape = RoundedCornerShape(6.dp),
        color = if (isSelected) StudioCoral.copy(alpha = 0.12f) else Color.Transparent
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = 7.dp, horizontal = 8.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Flat Left Indicator Bar
            Box(
                modifier = Modifier
                    .width(3.dp)
                    .height(18.dp)
                    .clip(RoundedCornerShape(1.5.dp))
                    .background(if (isSelected) StudioCoral else Color.Transparent)
            )

            Spacer(Modifier.width(8.dp))

            // Navigation Icon
            Icon(
                imageVector = if (isSelected) entry.destination.selectedIcon else entry.destination.unselectedIcon,
                contentDescription = entry.destination.title,
                modifier = Modifier.size(18.dp),
                tint = if (isSelected) StudioCoral else MaterialTheme.colorScheme.onSurfaceVariant
            )

            Spacer(Modifier.width(10.dp))

            // Destination Title
            Text(
                text = entry.destination.title,
                fontSize = 12.sp,
                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                color = if (isSelected) StudioCoral else MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.weight(1f)
            )

            // High-ROI Badge
            if (entry.badge.isVisible) {
                Surface(
                    shape = RoundedCornerShape(4.dp),
                    color = if (isSelected) StudioCoral else MaterialTheme.colorScheme.surfaceVariant
                ) {
                    Text(
                        text = entry.badge.label,
                        fontSize = 9.sp,
                        fontWeight = FontWeight.Bold,
                        color = if (isSelected) Color.White else MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(horizontal = 5.dp, vertical = 2.dp)
                    )
                }
            }
        }
    }
}

/**
 * Compact Flat Theme Switch Chip.
 */
@Composable
private fun FlatThemeModeChip(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    isSelected: Boolean,
    onClick: () -> Unit
) {
    Box(
        modifier = Modifier
            .size(28.dp)
            .clip(RoundedCornerShape(6.dp))
            .background(if (isSelected) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f))
            .border(
                1.dp,
                if (isSelected) StudioCoral else MaterialTheme.colorScheme.outline.copy(alpha = 0.3f),
                RoundedCornerShape(6.dp)
            )
            .clickable(onClick = onClick),
        contentAlignment = Alignment.Center
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            modifier = Modifier.size(14.dp),
            tint = if (isSelected) StudioCoral else MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}
