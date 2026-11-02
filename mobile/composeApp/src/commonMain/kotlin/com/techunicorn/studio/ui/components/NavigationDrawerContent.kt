package com.techunicorn.studio.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
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
import com.techunicorn.studio.ui.theme.StudioAccentDark
import com.techunicorn.studio.ui.theme.StudioVioletDark

@Composable
fun NavigationDrawerContent(
    currentDestination: StudioDestination,
    userRole: UserRole,
    onSelectDestination: (StudioDestination) -> Unit,
    onCloseDrawer: () -> Unit
) {
    val sections = if (userRole == UserRole.CLIENT) {
        listOf(
            "Projects" to listOf(
                StudioDestination.ClientPortal,
                StudioDestination.Deals,
                StudioDestination.Snippets,
                StudioDestination.Landing
            )
        )
    } else {
        listOf(
            "Today" to listOf(
                StudioDestination.Dashboard,
                StudioDestination.Flow,
                StudioDestination.Love
            ),
            "YouTube" to listOf(
                StudioDestination.Breakouts,
                StudioDestination.Ideas,
                StudioDestination.Scripts,
                StudioDestination.Goals,
                StudioDestination.Channel,
                StudioDestination.YouTube
            ),
            "Social" to listOf(
                StudioDestination.Calendar,
                StudioDestination.Analytics,
                StudioDestination.Deals,
                StudioDestination.MediaKit,
                StudioDestination.Agents,
                StudioDestination.Workflows,
                StudioDestination.Inbox,
                StudioDestination.Repurpose,
                StudioDestination.Bio
            ),
            "Create" to listOf(
                StudioDestination.Website,
                StudioDestination.Landing,
                StudioDestination.Snippets,
                StudioDestination.Toolkit,
                StudioDestination.Chat,
                StudioDestination.Library,
                StudioDestination.Media
            ),
            "Studio" to listOf(
                StudioDestination.Clients,
                StudioDestination.Account,
                StudioDestination.Connections,
                StudioDestination.Brand,
                StudioDestination.Settings
            )
        )
    }

    Surface(
        modifier = Modifier
            .fillMaxHeight()
            .width(310.dp),
        color = MaterialTheme.colorScheme.surface,
        tonalElevation = 6.dp
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = 16.dp, vertical = 20.dp)
        ) {
            // Brand Header
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(38.dp)
                            .clip(RoundedCornerShape(10.dp))
                            .background(
                                Brush.linearGradient(
                                    listOf(StudioAccentDark, StudioVioletDark)
                                )
                            ),
                        contentAlignment = Alignment.Center
                    ) {
                        StudioLogo(size = 22.dp, useGradient = true)
                    }
                    Spacer(Modifier.width(12.dp))
                    Column {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(
                                "Creator Studio",
                                fontWeight = FontWeight.Bold,
                                fontSize = 16.sp,
                                color = MaterialTheme.colorScheme.onSurface
                            )
                            Spacer(Modifier.width(6.dp))
                            Surface(
                                shape = RoundedCornerShape(6.dp),
                                color = if (userRole == UserRole.STUDIO) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.secondaryContainer
                            ) {
                                Text(
                                    if (userRole == UserRole.STUDIO) "PRO" else "CLIENT",
                                    fontSize = 9.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = if (userRole == UserRole.STUDIO) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.secondary,
                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                )
                            }
                        }
                        Text(
                            "Delia · Studio Suite",
                            fontSize = 11.sp,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }

                IconButton(onClick = onCloseDrawer) {
                    Icon(Icons.Default.Close, contentDescription = "Close Drawer", tint = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }

            Spacer(Modifier.height(18.dp))
            Divider(color = MaterialTheme.colorScheme.outline)
            Spacer(Modifier.height(10.dp))

            // Navigation Sections
            LazyColumn(
                modifier = Modifier.weight(1f),
                verticalArrangement = Arrangement.spacedBy(14.dp)
            ) {
                sections.forEach { (sectionTitle, destinations) ->
                    item {
                        Text(
                            text = sectionTitle.uppercase(),
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            letterSpacing = 1.2.sp,
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                        )

                        Spacer(Modifier.height(4.dp))

                        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                            destinations.forEach { dest ->
                                val selected = currentDestination == dest
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(
                                            if (selected) MaterialTheme.colorScheme.primaryContainer
                                            else Color.Transparent
                                        )
                                        .clickable {
                                            onSelectDestination(dest)
                                            onCloseDrawer()
                                        }
                                        .padding(horizontal = 10.dp, vertical = 8.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Icon(
                                        imageVector = if (selected) dest.selectedIcon else dest.unselectedIcon,
                                        contentDescription = dest.title,
                                        modifier = Modifier.size(18.dp),
                                        tint = if (selected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                    Spacer(Modifier.width(12.dp))
                                    Text(
                                        dest.title,
                                        fontSize = 13.sp,
                                        fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
                                        color = if (selected) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurface
                                    )
                                }
                            }
                        }
                    }
                }
            }

            Spacer(Modifier.height(8.dp))
            Divider(color = MaterialTheme.colorScheme.outline)
            Spacer(Modifier.height(10.dp))

            // Bottom Profile Quick Bar
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column {
                    Text(
                        if (userRole == UserRole.STUDIO) "Delia Lazarescu" else "ScaleWorks Inc.",
                        fontWeight = FontWeight.Bold,
                        fontSize = 13.sp,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Text(
                        if (userRole == UserRole.STUDIO) "delia@techunicorn.ai" else "client@scaleworks.io",
                        fontSize = 11.sp,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }

                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = MaterialTheme.colorScheme.surfaceVariant,
                    modifier = Modifier.clip(RoundedCornerShape(8.dp))
                ) {
                    Text(
                        "v2.4",
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 3.dp)
                    )
                }
            }
        }
    }
}

