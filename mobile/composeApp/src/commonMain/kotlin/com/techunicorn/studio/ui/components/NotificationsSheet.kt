package com.techunicorn.studio.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
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
import com.techunicorn.studio.ui.navigation.StudioDestination

data class StudioNotification(
    val id: String,
    val title: String,
    val message: String,
    val timeAgo: String,
    val icon: ImageVector,
    val unread: Boolean = true,
    val targetDestination: StudioDestination? = null
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NotificationsSheet(
    onDismiss: () -> Unit,
    onNavigate: (StudioDestination) -> Unit
) {
    var notifications by remember {
        mutableStateOf(
            listOf(
                StudioNotification(
                    id = "1",
                    title = "New Client Inquiry",
                    message = "ScaleWorks Inc requested an MVP Consultation for AI Copilot build (\$12,000 retainer).",
                    timeAgo = "12m ago",
                    icon = Icons.Default.Work,
                    unread = true,
                    targetDestination = StudioDestination.Deals
                ),
                StudioNotification(
                    id = "2",
                    title = "Agent Approval Needed",
                    message = "EvergreenRecycler generated 3 LinkedIn carousel drafts ready for review.",
                    timeAgo = "1h ago",
                    icon = Icons.Default.SmartToy,
                    unread = true,
                    targetDestination = StudioDestination.Agents
                ),
                StudioNotification(
                    id = "3",
                    title = "Outlier Breakout Alert",
                    message = "\"Why Next.js 15 Changed Everything\" is pacing 4.37x above channel median views.",
                    timeAgo = "3h ago",
                    icon = Icons.Default.TrendingUp,
                    unread = false,
                    targetDestination = StudioDestination.Breakouts
                ),
                StudioNotification(
                    id = "4",
                    title = "Script Review Completed",
                    message = "AI Retention Linter flagged hook pacing at 0:14. Retention score: 88/100.",
                    timeAgo = "5h ago",
                    icon = Icons.Default.Description,
                    unread = false,
                    targetDestination = StudioDestination.Scripts
                ),
                StudioNotification(
                    id = "5",
                    title = "Stripe Payout Confirmed",
                    message = "\$8,500 retainer payout from NeoLogix processed successfully to studio bank.",
                    timeAgo = "1d ago",
                    icon = Icons.Default.Payments,
                    unread = false,
                    targetDestination = StudioDestination.Deals
                )
            )
        )
    }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        containerColor = MaterialTheme.colorScheme.surface,
        dragHandle = { BottomSheetDefaults.DragHandle() }
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp, vertical = 8.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        "Notifications",
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Spacer(Modifier.width(8.dp))
                    val unreadCount = notifications.count { it.unread }
                    if (unreadCount > 0) {
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(12.dp))
                                .background(MaterialTheme.colorScheme.primary)
                                .padding(horizontal = 8.dp, vertical = 2.dp)
                        ) {
                            Text(
                                "$unreadCount new",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color.White
                            )
                        }
                    }
                }

                TextButton(
                    onClick = {
                        notifications = notifications.map { it.copy(unread = false) }
                    }
                ) {
                    Text("Mark all read", fontSize = 12.sp, color = MaterialTheme.colorScheme.primary)
                }
            }

            Spacer(Modifier.height(12.dp))

            LazyColumn(
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(max = 420.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(notifications) { notif ->
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(12.dp))
                            .background(
                                if (notif.unread) MaterialTheme.colorScheme.surfaceVariant
                                else MaterialTheme.colorScheme.surface
                            )
                            .border(
                                1.dp,
                                if (notif.unread) MaterialTheme.colorScheme.primary.copy(alpha = 0.3f)
                                else MaterialTheme.colorScheme.outline,
                                RoundedCornerShape(12.dp)
                            )
                            .clickable {
                                notifications = notifications.map { if (it.id == notif.id) it.copy(unread = false) else it }
                                if (notif.targetDestination != null) {
                                    onDismiss()
                                    onNavigate(notif.targetDestination)
                                }
                            }
                            .padding(12.dp),
                        verticalAlignment = Alignment.Top
                    ) {
                        Box(
                            modifier = Modifier
                                .size(36.dp)
                                .clip(RoundedCornerShape(10.dp))
                                .background(
                                    if (notif.unread) MaterialTheme.colorScheme.primaryContainer
                                    else MaterialTheme.colorScheme.surfaceVariant
                                ),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = notif.icon,
                                contentDescription = null,
                                modifier = Modifier.size(18.dp),
                                tint = if (notif.unread) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }

                        Spacer(Modifier.width(12.dp))

                        Column(modifier = Modifier.weight(1f)) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text(
                                    notif.title,
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 13.sp,
                                    color = MaterialTheme.colorScheme.onSurface
                                )
                                Text(
                                    notif.timeAgo,
                                    fontSize = 11.sp,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                            Spacer(Modifier.height(3.dp))
                            Text(
                                notif.message,
                                fontSize = 12.sp,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                lineHeight = 16.sp
                            )
                        }

                        if (notif.unread) {
                            Spacer(Modifier.width(8.dp))
                            Box(
                                modifier = Modifier
                                    .padding(top = 4.dp)
                                    .size(8.dp)
                                    .clip(CircleShape)
                                    .background(MaterialTheme.colorScheme.primary)
                            )
                        }
                    }
                }
            }

            Spacer(Modifier.height(16.dp))
        }
    }
}

