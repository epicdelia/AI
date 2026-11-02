package com.techunicorn.studio.ui.navigation

import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.ui.graphics.vector.ImageVector

enum class StudioDestination(
    val route: String,
    val title: String,
    val section: String,
    val selectedIcon: ImageVector,
    val unselectedIcon: ImageVector
) {
    // Today
    Dashboard("dashboard", "Dashboard", "Today", Icons.Filled.Home, Icons.Outlined.Home),
    Flow("flow", "Dictate", "Today", Icons.Filled.Mic, Icons.Outlined.Mic),
    Love("love", "For You", "Today", Icons.Filled.Favorite, Icons.Outlined.FavoriteBorder),

    // YouTube
    Breakouts("breakouts", "Breakouts", "YouTube", Icons.Filled.TrendingUp, Icons.Outlined.TrendingUp),
    Ideas("ideas", "Ideas", "YouTube", Icons.Filled.Lightbulb, Icons.Outlined.Lightbulb),
    Scripts("scripts", "Scripts", "YouTube", Icons.Filled.Description, Icons.Outlined.Description),
    Goals("goals", "Weekly Goals", "YouTube", Icons.Filled.Flag, Icons.Outlined.Flag),
    Channel("channel", "My Channel", "YouTube", Icons.Filled.Group, Icons.Outlined.Group),
    YouTube("youtube", "Upload & Test", "YouTube", Icons.Filled.PlayArrow, Icons.Outlined.PlayArrow),

    // Social
    Social("social", "Social", "Social", Icons.Filled.Share, Icons.Outlined.Share),
    Calendar("calendar", "Calendar", "Social", Icons.Filled.CalendarMonth, Icons.Outlined.CalendarMonth),
    Analytics("analytics", "Analytics", "Social", Icons.Filled.BarChart, Icons.Outlined.BarChart),
    Deals("deals", "Brand Deals", "Social", Icons.Filled.Work, Icons.Outlined.WorkOutline),
    MediaKit("mediakit", "Media Kit", "Social", Icons.Filled.Article, Icons.Outlined.Article),
    Agents("agents", "Agents", "Social", Icons.Filled.SmartToy, Icons.Outlined.SmartToy),
    Workflows("workflows", "Workflows", "Social", Icons.Filled.AccountTree, Icons.Outlined.AccountTree),
    Inbox("inbox", "Inbox", "Social", Icons.Filled.Inbox, Icons.Outlined.Inbox),
    Repurpose("repurpose", "Repurpose", "Social", Icons.Filled.Repeat, Icons.Outlined.Repeat),
    Bio("bio", "Link in Bio", "Social", Icons.Filled.Link, Icons.Outlined.Link),

    // Create
    Website("website", "Website Funnel", "Create", Icons.Filled.Language, Icons.Outlined.Language),
    Landing("landing", "Landing Page", "Create", Icons.Filled.Public, Icons.Outlined.Public),
    Snippets("snippets", "Code Cards", "Create", Icons.Filled.Code, Icons.Outlined.Code),
    Toolkit("toolkit", "Toolkit", "Create", Icons.Filled.AutoAwesome, Icons.Outlined.AutoAwesome),
    Chat("chat", "Producer Chat", "Create", Icons.Filled.Chat, Icons.Outlined.ChatBubbleOutline),
    Library("library", "Library", "Create", Icons.Filled.MenuBook, Icons.Outlined.MenuBook),
    Media("media", "Media Assets", "Create", Icons.Filled.Image, Icons.Outlined.Image),

    // Studio & Operations
    Clients("clients", "Clients", "Studio", Icons.Filled.People, Icons.Outlined.People),
    Account("account", "Account & Security", "Studio", Icons.Filled.Lock, Icons.Outlined.Lock),
    Connections("connections", "Connections", "Studio", Icons.Filled.Power, Icons.Outlined.Power),
    Brand("brand", "Brand Kit", "Studio", Icons.Filled.Palette, Icons.Outlined.Palette),
    Settings("settings", "Settings", "Studio", Icons.Filled.Settings, Icons.Outlined.Settings),

    // Client Portal
    ClientPortal("portal", "Client Portal", "Projects", Icons.Filled.Business, Icons.Outlined.Business)
}
