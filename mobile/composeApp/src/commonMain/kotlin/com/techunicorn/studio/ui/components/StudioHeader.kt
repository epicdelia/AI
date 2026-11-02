package com.techunicorn.studio.ui.components

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
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.models.UserRole
import com.techunicorn.studio.ui.navigation.StudioDestination
import com.techunicorn.studio.ui.theme.*

@Composable
fun StudioHeader(
    currentDestination: StudioDestination,
    userRole: UserRole,
    themeMode: ThemeMode,
    onOpenDrawer: () -> Unit,
    onOpenOmnibar: () -> Unit,
    onOpenNotifications: () -> Unit,
    onOpenCopilot: () -> Unit,
    onThemeChange: (ThemeMode) -> Unit,
    onNavigate: (StudioDestination) -> Unit,
    onSignOut: () -> Unit,
    onOpenAuth: () -> Unit
) {
    var isProfileMenuOpen by remember { mutableStateOf(false) }

    Surface(
        modifier = Modifier.fillMaxWidth(),
        color = MaterialTheme.colorScheme.surface,
        tonalElevation = 4.dp
    ) {
        Column(modifier = Modifier.fillMaxWidth()) {
            // Main Top Bar Row
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 12.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                // Left: Drawer Hamburger & Brand Mark
                Row(verticalAlignment = Alignment.CenterVertically) {
                    IconButton(onClick = onOpenDrawer) {
                        Icon(Icons.Default.Menu, contentDescription = "Open Navigation Menu", tint = MaterialTheme.colorScheme.onSurface)
                    }

                    Box(
                        modifier = Modifier
                            .size(32.dp)
                            .clip(RoundedCornerShape(8.dp))
                            .background(
                                Brush.linearGradient(
                                    listOf(StudioAccentDark, StudioVioletDark)
                                )
                            )
                            .clickable { onNavigate(StudioDestination.Dashboard) },
                        contentAlignment = Alignment.Center
                    ) {
                        Text("🦄", fontSize = 16.sp)
                    }

                    Spacer(Modifier.width(8.dp))

                    Column {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(
                                "Creator Studio",
                                fontWeight = FontWeight.Bold,
                                fontSize = 14.sp,
                                color = MaterialTheme.colorScheme.onSurface
                            )
                            Spacer(Modifier.width(4.dp))
                            Surface(
                                shape = RoundedCornerShape(4.dp),
                                color = if (userRole == UserRole.STUDIO) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.secondaryContainer
                            ) {
                                Text(
                                    if (userRole == UserRole.STUDIO) "PRO" else if (userRole == UserRole.CLIENT) "CLIENT" else "PUBLIC",
                                    fontSize = 8.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = if (userRole == UserRole.STUDIO) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.secondary,
                                    modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                                )
                            }
                        }
                    }
                }

                // Center / Action Pill: Omnibar Search Bar ("Ctrl K")
                Surface(
                    modifier = Modifier
                        .weight(1f, fill = false)
                        .padding(horizontal = 8.dp)
                        .clip(RoundedCornerShape(20.dp))
                        .border(1.dp, MaterialTheme.colorScheme.outline, RoundedCornerShape(20.dp))
                        .clickable { onOpenOmnibar() },
                    color = MaterialTheme.colorScheme.surfaceVariant
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            Icons.Default.Search,
                            contentDescription = "Search",
                            modifier = Modifier.size(14.dp),
                            tint = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Spacer(Modifier.width(6.dp))
                        Text(
                            "Search scripts, ideas…",
                            fontSize = 11.sp,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Spacer(Modifier.width(6.dp))
                        Surface(
                            shape = RoundedCornerShape(4.dp),
                            color = MaterialTheme.colorScheme.surface,
                            modifier = Modifier.padding(start = 2.dp)
                        ) {
                            Text(
                                "Ctrl K",
                                fontSize = 9.sp,
                                fontWeight = FontWeight.Bold,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                            )
                        }
                    }
                }

                // Right: Notifications, Copilot, User Avatar
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    // Notifications Bell
                    IconButton(
                        onClick = onOpenNotifications,
                        modifier = Modifier.size(34.dp)
                    ) {
                        BadgedBox(
                            badge = {
                                Badge(
                                    containerColor = MaterialTheme.colorScheme.primary,
                                    contentColor = Color.White
                                ) {
                                    Text("2", fontSize = 9.sp)
                                }
                            }
                        ) {
                            Icon(Icons.Default.NotificationsNone, contentDescription = "Notifications", modifier = Modifier.size(19.dp))
                        }
                    }

                    // Copilot Trigger
                    if (userRole == UserRole.STUDIO) {
                        Surface(
                            modifier = Modifier
                                .clip(RoundedCornerShape(8.dp))
                                .border(1.dp, MaterialTheme.colorScheme.secondary.copy(alpha = 0.4f), RoundedCornerShape(8.dp))
                                .clickable { onOpenCopilot() },
                            color = MaterialTheme.colorScheme.secondaryContainer
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 8.dp, vertical = 5.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(
                                    Icons.Default.AutoAwesome,
                                    contentDescription = "Copilot",
                                    modifier = Modifier.size(14.dp),
                                    tint = MaterialTheme.colorScheme.secondary
                                )
                                Spacer(Modifier.width(4.dp))
                                Text(
                                    "Copilot",
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = MaterialTheme.colorScheme.secondary
                                )
                            }
                        }
                    }

                    // User Profile Avatar
                    Box {
                        Box(
                            modifier = Modifier
                                .size(32.dp)
                                .clip(CircleShape)
                                .background(
                                    if (userRole == UserRole.STUDIO) MaterialTheme.colorScheme.primary
                                    else if (userRole == UserRole.CLIENT) MaterialTheme.colorScheme.secondary
                                    else MaterialTheme.colorScheme.surfaceVariant
                                )
                                .clickable {
                                    if (userRole == UserRole.PUBLIC) onOpenAuth()
                                    else isProfileMenuOpen = true
                                },
                            contentAlignment = Alignment.Center
                        ) {
                            Text(
                                if (userRole == UserRole.STUDIO) "D" else if (userRole == UserRole.CLIENT) "S" else "?",
                                fontWeight = FontWeight.Bold,
                                fontSize = 13.sp,
                                color = if (userRole == UserRole.PUBLIC) MaterialTheme.colorScheme.onSurfaceVariant else Color.White
                            )
                        }

                        // Profile Dropdown Menu matching app.js
                        DropdownMenu(
                            expanded = isProfileMenuOpen,
                            onDismissRequest = { isProfileMenuOpen = false },
                            modifier = Modifier
                                .width(270.dp)
                                .background(MaterialTheme.colorScheme.surface)
                                .border(1.dp, MaterialTheme.colorScheme.outline, RoundedCornerShape(8.dp))
                        ) {
                            // User Info Header
                            Column(modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp)) {
                                Text(
                                    if (userRole == UserRole.STUDIO) "Delia Lazarescu" else "ScaleWorks Inc.",
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 14.sp,
                                    color = MaterialTheme.colorScheme.onSurface
                                )
                                Text(
                                    if (userRole == UserRole.STUDIO) "delia@techunicorn.ai" else "client@scaleworks.io",
                                    fontSize = 11.sp,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }

                            Divider(color = MaterialTheme.colorScheme.outline)

                            // Segmented Theme Switch (Light / Dark / System)
                            Box(modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp)) {
                                ThemeSwitch(
                                    currentMode = themeMode,
                                    onModeSelected = { mode ->
                                        onThemeChange(mode)
                                    }
                                )
                            }

                            Divider(color = MaterialTheme.colorScheme.outline)

                            DropdownMenuItem(
                                text = { Text("Account & Security", fontSize = 13.sp) },
                                leadingIcon = { Icon(Icons.Default.Lock, contentDescription = null, modifier = Modifier.size(16.dp)) },
                                onClick = {
                                    isProfileMenuOpen = false
                                    onNavigate(StudioDestination.Account)
                                }
                            )

                            if (userRole == UserRole.STUDIO) {
                                DropdownMenuItem(
                                    text = { Text("For You (Love Suite)", fontSize = 13.sp) },
                                    leadingIcon = { Icon(Icons.Default.Favorite, contentDescription = null, modifier = Modifier.size(16.dp), tint = MaterialTheme.colorScheme.primary) },
                                    onClick = {
                                        isProfileMenuOpen = false
                                        onNavigate(StudioDestination.Love)
                                    }
                                )

                                DropdownMenuItem(
                                    text = { Text("Studio Settings", fontSize = 13.sp) },
                                    leadingIcon = { Icon(Icons.Default.Settings, contentDescription = null, modifier = Modifier.size(16.dp)) },
                                    onClick = {
                                        isProfileMenuOpen = false
                                        onNavigate(StudioDestination.Settings)
                                    }
                                )
                            }

                            Divider(color = MaterialTheme.colorScheme.outline)

                            DropdownMenuItem(
                                text = { Text("Sign Out", fontSize = 13.sp, color = MaterialTheme.colorScheme.error) },
                                leadingIcon = { Icon(Icons.Default.Logout, contentDescription = null, modifier = Modifier.size(16.dp), tint = MaterialTheme.colorScheme.error) },
                                onClick = {
                                    isProfileMenuOpen = false
                                    onSignOut()
                                }
                            )
                        }
                    }
                }
            }

            // Mega Menus Section Tabs Bar (scrollable horizontal rail)
            if (userRole != UserRole.PUBLIC) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState())
                        .padding(horizontal = 12.dp, vertical = 4.dp),
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    val sections = listOf(
                        "Today" to StudioDestination.Dashboard,
                        "YouTube" to StudioDestination.Breakouts,
                        "Social" to StudioDestination.Calendar,
                        "Create" to StudioDestination.Snippets,
                        "Studio" to StudioDestination.Settings
                    )

                    sections.forEach { (secName, targetDest) ->
                        val isCurrentSection = currentDestination.section == secName
                        Surface(
                            shape = RoundedCornerShape(16.dp),
                            color = if (isCurrentSection) MaterialTheme.colorScheme.primaryContainer else Color.Transparent,
                            modifier = Modifier
                                .clip(RoundedCornerShape(16.dp))
                                .clickable { onNavigate(targetDest) }
                        ) {
                            Text(
                                secName,
                                fontSize = 11.sp,
                                fontWeight = if (isCurrentSection) FontWeight.Bold else FontWeight.Medium,
                                color = if (isCurrentSection) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp)
                            )
                        }
                    }
                }
            }

            Divider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.5f))
        }
    }
}

