package com.techunicorn.studio.ui.screens.client

import androidx.compose.animation.*
import androidx.compose.foundation.background
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
import com.techunicorn.studio.domain.models.*
import com.techunicorn.studio.ui.theme.*

@Composable
fun ClientPortalScreen(
    requests: List<ClientRequest>,
    brandVault: BrandVault,
    onSwitchRole: (UserRole) -> Unit,
    onOpenDealRoom: () -> Unit
) {
    var selectedTab by remember { mutableStateOf(0) } // 0: Overview, 1: Brand Vault, 2: Billing
    var selectedRequest by remember { mutableStateOf<ClientRequest?>(null) }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
    ) {
        // Top Portal Bar
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Client Portal", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.width(8.dp))
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(6.dp))
                            .background(StudioViolet.copy(alpha = 0.15f))
                            .padding(horizontal = 6.dp, vertical = 2.dp)
                    ) {
                        Text("PORTAL ACCESS", color = StudioViolet, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                    }
                }
                Text("ScaleWorks Inc. · Signed in as client", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }

            // Role Return Switcher
            OutlinedButton(
                onClick = { onSwitchRole(UserRole.STUDIO) },
                contentPadding = PaddingValues(horizontal = 10.dp, vertical = 4.dp)
            ) {
                Text("Studio View", fontSize = 11.sp)
            }
        }

        // Tab Navigation
        TabRow(
            selectedTabIndex = selectedTab,
            containerColor = MaterialTheme.colorScheme.surface,
            contentColor = StudioCoral
        ) {
            Tab(selected = selectedTab == 0, onClick = { selectedTab = 0 }, text = { Text("Projects") })
            Tab(selected = selectedTab == 1, onClick = { selectedTab = 1 }, text = { Text("Brand Vault") })
            Tab(selected = selectedTab == 2, onClick = { selectedTab = 2 }, text = { Text("Deal Room & DPA") })
        }

        when (selectedTab) {
            0 -> ClientProjectsTab(requests, onSelectRequest = { selectedRequest = it })
            1 -> BrandVaultTab(brandVault)
            2 -> ClientDealRoomTab(onOpenDealRoom)
        }
    }

    // Deliverable Revision Modal Sheet
    if (selectedRequest != null) {
        val req = selectedRequest!!
        AlertDialog(
            onDismissRequest = { selectedRequest = null },
            title = { Text(req.title) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("Company: ${req.company}", fontWeight = FontWeight.SemiBold)
                    Text("Contract: \$${req.amount.toInt()} · ${req.paymentStatus.uppercase()}", color = StudioEmerald, fontWeight = FontWeight.Bold)

                    Text("Deliverables In Review:", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                    for (deliv in req.deliverables) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.CheckCircle, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text(deliv, fontSize = 12.sp)
                        }
                    }

                    Spacer(Modifier.height(6.dp))
                    Text("Revision Annotations:", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                    for (ann in req.annotations) {
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)
                        ) {
                            Column(Modifier.padding(8.dp)) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    Text(ann.author, fontWeight = FontWeight.Bold, fontSize = 11.sp)
                                    Text(if (ann.isResolved) "RESOLVED" else "PENDING", color = if (ann.isResolved) StudioEmerald else StudioAmber, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                                }
                                Text(ann.text, fontSize = 12.sp)
                            }
                        }
                    }
                }
            },
            confirmButton = {
                Button(
                    onClick = { selectedRequest = null },
                    colors = ButtonDefaults.buttonColors(containerColor = StudioEmerald)
                ) {
                    Text("Approve Deliverables")
                }
            },
            dismissButton = {
                TextButton(onClick = { selectedRequest = null }) {
                    Text("Close")
                }
            }
        )
    }
}

@Composable
fun ClientProjectsTab(
    requests: List<ClientRequest>,
    onSelectRequest: (ClientRequest) -> Unit
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        items(requests) { req ->
            Card(
                onClick = { onSelectRequest(req) },
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
                        Text(req.title, fontWeight = FontWeight.Bold, fontSize = 15.sp)
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(6.dp))
                                .background(StudioViolet.copy(alpha = 0.15f))
                                .padding(horizontal = 8.dp, vertical = 4.dp)
                        ) {
                            Text(req.status.uppercase(), color = StudioViolet, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                        }
                    }

                    Spacer(Modifier.height(4.dp))
                    Text(req.company, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

                    if (req.meetingAt != null) {
                        Spacer(Modifier.height(6.dp))
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.CalendarMonth, contentDescription = null, tint = StudioCoral, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Next Call: ${req.meetingAt}", fontSize = 12.sp, fontWeight = FontWeight.Medium)
                        }
                    }

                    Spacer(Modifier.height(10.dp))
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text("\$${req.amount.toInt()} · ${req.paymentStatus.uppercase()}", color = StudioEmerald, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                        Text("${req.annotations.size} revision notes", fontSize = 12.sp, color = StudioCoral)
                    }
                }
            }
        }
    }
}

@Composable
fun BrandVaultTab(vault: BrandVault) {
    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        Text("Brand Vault & Compliance", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
        Text("Centralized assets, guidelines, and tax verification", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(14.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
        ) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text("Brand Assets", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                Text("Brand Name: ${vault.brandName}", fontSize = 13.sp)
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Palette: ", fontSize = 13.sp)
                    Box(Modifier.size(20.dp).clip(CircleShape).background(StudioCoral))
                    Spacer(Modifier.width(6.dp))
                    Box(Modifier.size(20.dp).clip(CircleShape).background(StudioViolet))
                }
                Text("Guidelines: ${vault.guidelinesUrl}", fontSize = 12.sp, color = StudioViolet)
            }
        }

        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(14.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
        ) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text("EU VAT & VIES Tax Verification", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("VAT ID: ${vault.viesVatNumber}", fontSize = 13.sp)
                    Spacer(Modifier.width(8.dp))
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(6.dp))
                            .background(StudioEmerald.copy(alpha = 0.15f))
                            .padding(horizontal = 6.dp, vertical = 2.dp)
                    ) {
                        Text("VIES VERIFIED", color = StudioEmerald, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                    }
                }
                Text("Zero reverse-charge tax applied on cross-border EU invoices.", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

@Composable
fun ClientDealRoomTab(onOpenDealRoom: () -> Unit) {
    Column(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(14.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)
        ) {
            Column(Modifier.padding(16.dp)) {
                Text("Partnership Deal Room", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(4.dp))
                Text("Review scope tiers, sign cryptographic DPA, and pay via Stripe.", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

                Spacer(Modifier.height(14.dp))
                Button(
                    onClick = onOpenDealRoom,
                    colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Icon(Icons.Default.OpenInNew, contentDescription = null, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(6.dp))
                    Text("Launch Deal Room Interface")
                }
            }
        }
    }
}

