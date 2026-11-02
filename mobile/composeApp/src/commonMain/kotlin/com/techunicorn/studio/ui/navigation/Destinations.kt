package com.techunicorn.studio.ui.navigation

import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.ui.graphics.vector.ImageVector

enum class StudioDestination(
    val route: String,
    val title: String,
    val selectedIcon: ImageVector,
    val unselectedIcon: ImageVector
) {
    Dashboard("dashboard", "Today", Icons.Filled.Home, Icons.Outlined.Home),
    Flow("flow", "Flow", Icons.Filled.Mic, Icons.Outlined.Mic),
    YouTube("youtube", "YouTube", Icons.Filled.PlayArrow, Icons.Outlined.PlayArrow),
    Social("social", "Social", Icons.Filled.Share, Icons.Outlined.Share),
    Deals("deals", "Deals", Icons.Filled.Work, Icons.Outlined.WorkOutline),
    Agents("agents", "Agents", Icons.Filled.SmartToy, Icons.Outlined.SmartToy)
}

