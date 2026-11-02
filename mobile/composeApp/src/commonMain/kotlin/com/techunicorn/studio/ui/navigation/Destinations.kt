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
    Landing("landing", "Website", Icons.Filled.Public, Icons.Outlined.Public),
    Dashboard("dashboard", "Today", Icons.Filled.Home, Icons.Outlined.Home),
    Flow("flow", "Flow", Icons.Filled.Mic, Icons.Outlined.Mic),
    YouTube("youtube", "YouTube", Icons.Filled.PlayArrow, Icons.Outlined.PlayArrow),
    Social("social", "Social", Icons.Filled.Share, Icons.Outlined.Share),
    Snippets("snippets", "Cards", Icons.Filled.Code, Icons.Outlined.Code),
    Deals("deals", "Deals", Icons.Filled.Work, Icons.Outlined.WorkOutline),
    ClientPortal("portal", "Portal", Icons.Filled.Business, Icons.Outlined.Business),
    Toolkit("toolkit", "Toolkit", Icons.Filled.AutoAwesome, Icons.Outlined.AutoAwesome),
    Love("love", "Love", Icons.Filled.Favorite, Icons.Outlined.FavoriteBorder),
    Agents("agents", "Agents", Icons.Filled.SmartToy, Icons.Outlined.SmartToy)
}
