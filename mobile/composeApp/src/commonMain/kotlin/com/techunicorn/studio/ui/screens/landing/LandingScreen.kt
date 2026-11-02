package com.techunicorn.studio.ui.screens.landing

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.models.LandingService
import com.techunicorn.studio.domain.models.Talk
import com.techunicorn.studio.domain.models.UserRole
import com.techunicorn.studio.ui.theme.*

@Composable
fun LandingScreen(
    services: List<LandingService>,
    talks: List<Talk>,
    onSwitchRole: (UserRole) -> Unit,
    onBookService: (String) -> Unit
) {
    var showBookingDialog by remember { mutableStateOf(false) }
    var selectedServiceForBooking by remember { mutableStateOf("Vibecoded MVPs") }

    LazyColumn(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(20.dp)
    ) {
        // Top Bar & Role Selector Pill
        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(36.dp)
                            .clip(CircleShape)
                            .background(StudioCoral),
                        contentAlignment = Alignment.Center
                    ) {
                        Text("🦄", fontSize = 18.sp)
                    }
                    Spacer(Modifier.width(10.dp))
                    Column {
                        Text("Tech Unicorn", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                        Text("Delia Lazarescu · Ex-Google", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }

                // Switch to Studio / Client Login Button
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    OutlinedButton(
                        onClick = { onSwitchRole(UserRole.CLIENT) },
                        contentPadding = PaddingValues(horizontal = 10.dp, vertical = 4.dp)
                    ) {
                        Text("Client Portal", fontSize = 11.sp)
                    }
                    Button(
                        onClick = { onSwitchRole(UserRole.STUDIO) },
                        colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                        contentPadding = PaddingValues(horizontal = 10.dp, vertical = 4.dp)
                    ) {
                        Text("Studio", fontSize = 11.sp)
                    }
                }
            }
        }

        // Hero Section
        item {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(20.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)
            ) {
                Column(Modifier.padding(20.dp)) {
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(8.dp))
                            .background(StudioEmerald.copy(alpha = 0.15f))
                            .padding(horizontal = 10.dp, vertical = 4.dp)
                    ) {
                        Text("● Available for Q4 MVP Builds & Keynotes", color = StudioEmerald, fontWeight = FontWeight.Bold, fontSize = 11.sp)
                    }

                    Spacer(Modifier.height(14.dp))
                    Text(
                        text = "Making Tech Less Scary",
                        style = MaterialTheme.typography.headlineLarge,
                        fontWeight = FontWeight.Bold
                    )
                    Spacer(Modifier.height(6.dp))
                    Text(
                        text = "Ex-Google engineer, creator for 472K+ developers, and founder building AI-powered apps. Vibecoded MVPs shipped in 2 to 4 weeks, practical AI integrations, keynote speaking and verified brand partnerships.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )

                    Spacer(Modifier.height(16.dp))
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Button(
                            onClick = {
                                selectedServiceForBooking = "Sprint MVP Build"
                                showBookingDialog = true
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                            modifier = Modifier.weight(1f)
                        ) {
                            Icon(Icons.Default.CalendarToday, contentDescription = null, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Book an Intro Call", fontSize = 13.sp)
                        }
                    }

                    Spacer(Modifier.height(16.dp))
                    // Metrics Row
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Column {
                            Text("472K+", fontWeight = FontWeight.Bold, fontSize = 18.sp, color = StudioCoral)
                            Text("Developer Reach", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                        Column {
                            Text("2-4 Wks", fontWeight = FontWeight.Bold, fontSize = 18.sp, color = StudioViolet)
                            Text("MVP Delivery", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                        Column {
                            Text("12+ Keynotes", fontWeight = FontWeight.Bold, fontSize = 18.sp, color = StudioEmerald)
                            Text("Global Stages", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                }
            }
        }

        // Section: Services
        item {
            Text("02: Services", color = StudioCoral, fontWeight = FontWeight.Bold, fontSize = 12.sp)
            Text("Six Ways We Can Work Together", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
            Text("Transparent deliverables, high technical rigor, and zero corporate fluff.", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }

        items(services) { service ->
            var isExpanded by remember { mutableStateOf(false) }

            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(14.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(Modifier.padding(16.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(service.category.uppercase(), fontWeight = FontWeight.Bold, fontSize = 11.sp, color = StudioViolet)
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(6.dp))
                                .background(MaterialTheme.colorScheme.surfaceVariant)
                                .padding(horizontal = 8.dp, vertical = 2.dp)
                        ) {
                            Text(service.badge, fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }

                    Spacer(Modifier.height(6.dp))
                    Text(service.title, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(4.dp))
                    Text(service.desc, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)

                    Spacer(Modifier.height(10.dp))
                    Text("KEY DELIVERABLES:", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    val shownDeliverables = if (isExpanded) service.deliverables else service.deliverables.take(1)
                    for (deliv in shownDeliverables) {
                        Row(Modifier.padding(vertical = 2.dp)) {
                            Icon(Icons.Default.Check, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text(deliv, fontSize = 12.sp)
                        }
                    }

                    if (service.deliverables.size > 1) {
                        TextButton(
                            onClick = { isExpanded = !isExpanded },
                            contentPadding = PaddingValues(0.dp)
                        ) {
                            Text(if (isExpanded) "Show Less" else "Show All Deliverables (+${service.deliverables.size - 1})", fontSize = 12.sp, color = StudioCoral)
                        }
                    }

                    Spacer(Modifier.height(8.dp))
                    Button(
                        onClick = {
                            selectedServiceForBooking = service.title
                            showBookingDialog = true
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text("Get Started · ${service.title}", color = MaterialTheme.colorScheme.onSurface, fontSize = 12.sp)
                    }
                }
            }
        }

        // Section: On Stage Talks
        item {
            Text("03: On Stage", color = StudioViolet, fontWeight = FontWeight.Bold, fontSize = 12.sp)
            Text("Keynotes & Tech Summits", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
        }

        items(talks) { talk ->
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(14.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(Modifier.padding(16.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(talk.event, fontWeight = FontWeight.Bold, fontSize = 12.sp, color = StudioCoral)
                        Text(talk.reach, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = StudioEmerald)
                    }
                    Spacer(Modifier.height(4.dp))
                    Text(talk.title, fontWeight = FontWeight.Bold, fontSize = 15.sp)
                    Spacer(Modifier.height(4.dp))
                    Text(talk.description, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
    }

    // Instant Booking Sheet Dialog
    if (showBookingDialog) {
        AlertDialog(
            onDismissRequest = { showBookingDialog = false },
            title = { Text("Book an Intro Call") },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("Selected: $selectedServiceForBooking", fontWeight = FontWeight.Bold, color = StudioCoral)
                    Text("We'll discuss your MVP scope, tech stack, and timeline directly with Delia.", fontSize = 13.sp)
                    OutlinedTextField(
                        value = "Your Name & Company",
                        onValueChange = {},
                        label = { Text("Full Name") },
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = "you@company.com",
                        onValueChange = {},
                        label = { Text("Email Address") },
                        modifier = Modifier.fillMaxWidth()
                    )
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        showBookingDialog = false
                        onBookService(selectedServiceForBooking)
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = StudioCoral)
                ) {
                    Text("Confirm Booking")
                }
            },
            dismissButton = {
                TextButton(onClick = { showBookingDialog = false }) {
                    Text("Cancel")
                }
            }
        )
    }
}
