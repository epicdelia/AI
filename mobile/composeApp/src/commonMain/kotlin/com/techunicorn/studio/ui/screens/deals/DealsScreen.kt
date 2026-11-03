package com.techunicorn.studio.ui.screens.deals

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.models.Deal
import com.techunicorn.studio.ui.theme.*

@Composable
fun DealsScreen(
    deals: List<Deal>
) {
    var localDeals by remember { mutableStateOf(deals) }
    var showNewPitchDialog by remember { mutableStateOf(false) }
    var activeSponsorScriptDeal by remember { mutableStateOf<Deal?>(null) }
    val clipboard = LocalClipboardManager.current
    var copiedNotice by remember { mutableStateOf<String?>(null) }

    val totalPipeline = localDeals.sumOf { it.value }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .padding(16.dp)
    ) {
        // Pipeline Header Card
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(14.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)
        ) {
            Row(
                modifier = Modifier.padding(16.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text("ACTIVE BRAND PIPELINE", color = StudioEmerald, fontWeight = FontWeight.Bold, fontSize = 11.sp)
                    Spacer(Modifier.height(4.dp))
                    Text("\$${totalPipeline.toInt()}", style = MaterialTheme.typography.headlineLarge, fontWeight = FontWeight.Bold)
                    Text("${localDeals.size} brand deals in flight", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                Button(
                    onClick = { showNewPitchDialog = true },
                    colors = ButtonDefaults.buttonColors(containerColor = StudioEmerald),
                    contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp)
                ) {
                    Icon(Icons.Default.Add, contentDescription = null, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("New Pitch", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            }
        }

        Spacer(Modifier.height(16.dp))
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text("Sponsorship Deals", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
            if (copiedNotice != null) {
                Text(copiedNotice!!, color = StudioEmerald, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            }
        }
        Spacer(Modifier.height(8.dp))

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            items(localDeals) { deal ->
                val stageColor = when (deal.stage) {
                    "signed" -> StudioEmerald
                    "negotiating" -> StudioViolet
                    "pitched" -> StudioOcean
                    else -> StudioAmber
                }

                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
                ) {
                    Column(Modifier.padding(14.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(deal.brandName, fontWeight = FontWeight.Bold, fontSize = 16.sp)
                            Text(
                                text = "\$${deal.value.toInt()}",
                                fontWeight = FontWeight.Bold,
                                fontSize = 16.sp,
                                color = StudioEmerald
                            )
                        }
                        Text("Contact: ${deal.contactPerson}", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

                        Spacer(Modifier.height(8.dp))
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Box(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(6.dp))
                                    .background(stageColor.copy(alpha = 0.15f))
                                    .padding(horizontal = 8.dp, vertical = 4.dp)
                            ) {
                                Text(deal.stage.uppercase(), color = stageColor, fontWeight = FontWeight.Bold, fontSize = 10.sp)
                            }
                            if (deal.dueDate != null) {
                                Text("Due: ${deal.dueDate}", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                        }

                        if (deal.deliverables.isNotEmpty()) {
                            Spacer(Modifier.height(8.dp))
                            Text(
                                text = "Deliverables: ${deal.deliverables.joinToString(", ")}",
                                fontSize = 12.sp,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }

                        Spacer(Modifier.height(12.dp))

                        // Pillar 6: High-ROI Quick Actions
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            OutlinedButton(
                                onClick = {
                                    val token = deal.brandName.lowercase().replace(" ", "-")
                                    clipboard.setText(AnnotatedString("https://thetechunicorn.com/room/$token"))
                                    copiedNotice = "Copied Deal Room link!"
                                },
                                modifier = Modifier.weight(1f),
                                contentPadding = PaddingValues(horizontal = 6.dp, vertical = 4.dp)
                            ) {
                                Icon(Icons.Default.Link, contentDescription = null, modifier = Modifier.size(13.dp))
                                Spacer(Modifier.width(3.dp))
                                Text("Deal Room", fontSize = 11.sp)
                            }

                            OutlinedButton(
                                onClick = { activeSponsorScriptDeal = deal },
                                modifier = Modifier.weight(1f),
                                contentPadding = PaddingValues(horizontal = 6.dp, vertical = 4.dp)
                            ) {
                                Icon(Icons.Default.Mic, contentDescription = null, modifier = Modifier.size(13.dp))
                                Spacer(Modifier.width(3.dp))
                                Text("Script Read", fontSize = 11.sp)
                            }

                            Button(
                                onClick = {
                                    clipboard.setText(AnnotatedString("https://buy.stripe.com/deal_${deal.id}"))
                                    copiedNotice = "Copied Stripe checkout link!"
                                },
                                colors = ButtonDefaults.buttonColors(containerColor = StudioEmerald),
                                modifier = Modifier.weight(1f),
                                contentPadding = PaddingValues(horizontal = 6.dp, vertical = 4.dp)
                            ) {
                                Icon(Icons.Default.CreditCard, contentDescription = null, modifier = Modifier.size(13.dp))
                                Spacer(Modifier.width(3.dp))
                                Text("Stripe Pay", fontSize = 11.sp)
                            }
                        }
                    }
                }
            }
        }
    }

    // New Pitch Dialog
    if (showNewPitchDialog) {
        var brandName by remember { mutableStateOf("") }
        var contactPerson by remember { mutableStateOf("") }
        var selectedTier by remember { mutableStateOf(12000.0) }

        AlertDialog(
            onDismissRequest = { showNewPitchDialog = false },
            icon = { Icon(Icons.Default.Handshake, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(36.dp)) },
            title = { Text("Generate Brand Pitch", fontWeight = FontWeight.Bold) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = brandName,
                        onValueChange = { brandName = it },
                        label = { Text("Brand / Company Name") },
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = contactPerson,
                        onValueChange = { contactPerson = it },
                        label = { Text("Key Contact / Marketing Lead") },
                        modifier = Modifier.fillMaxWidth()
                    )
                    Text("Select Rate Card Tier:", fontWeight = FontWeight.SemiBold, fontSize = 12.sp)
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        FilterChip(
                            selected = selectedTier == 8500.0,
                            onClick = { selectedTier = 8500.0 },
                            label = { Text("Essential (\$8.5K)", fontSize = 10.sp) }
                        )
                        FilterChip(
                            selected = selectedTier == 12000.0,
                            onClick = { selectedTier = 12000.0 },
                            label = { Text("Growth (\$12K)", fontSize = 10.sp) }
                        )
                        FilterChip(
                            selected = selectedTier == 18500.0,
                            onClick = { selectedTier = 18500.0 },
                            label = { Text("Enterprise (\$18.5K)", fontSize = 10.sp) }
                        )
                    }
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        if (brandName.isNotBlank()) {
                            val newDeal = Deal(
                                id = kotlinx.datetime.Clock.System.now().toEpochMilliseconds(),
                                brandName = brandName.trim(),
                                contactPerson = contactPerson.ifBlank { "Marketing Partner" },
                                stage = "pitched",
                                value = selectedTier,
                                deliverables = listOf("60s Dedicated Mid-Roll", "X Thread", "LinkedIn Carousel"),
                                dueDate = "Nov 28, 2026"
                            )
                            localDeals = listOf(newDeal) + localDeals
                            showNewPitchDialog = false
                        }
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = StudioEmerald)
                ) {
                    Text("Add to Pipeline")
                }
            },
            dismissButton = {
                TextButton(onClick = { showNewPitchDialog = false }) { Text("Cancel") }
            }
        )
    }

    // Sponsor Script Segment Modal
    if (activeSponsorScriptDeal != null) {
        val deal = activeSponsorScriptDeal!!
        val sponsorScriptText = """
## Sponsor Segment: ${deal.brandName} (02:30 - 03:30)
[VISUAL: Direct to camera, holding phone showing ${deal.brandName} UI]

"Before we dive into the concurrency architecture, quick shoutout to today's sponsor, ${deal.brandName}.

If you've ever tried building production systems with fragmented APIs, you know how painful state reconciliation can be. ${deal.brandName} solves this by giving you instant, type-safe infrastructure that deploys in seconds.

I personally use their engine for our creator studio backend. Check out the link in the top description to get started with \$25 free platform credits."

[VISUAL: Code diff graphic on screen · Text: thetechunicorn.com/${deal.brandName.lowercase().replace(" ", "-")}]
        """.trimIndent()

        AlertDialog(
            onDismissRequest = { activeSponsorScriptDeal = null },
            icon = { Icon(Icons.Default.Mic, contentDescription = null, tint = StudioViolet, modifier = Modifier.size(36.dp)) },
            title = { Text("60s Sponsor Read · ${deal.brandName}", fontWeight = FontWeight.Bold) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("Tailored mid-roll integration formatted per editing/workflow.md:", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = MaterialTheme.colorScheme.surfaceVariant,
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text(
                            text = sponsorScriptText,
                            fontSize = 11.sp,
                            modifier = Modifier.padding(10.dp)
                        )
                    }
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        clipboard.setText(AnnotatedString(sponsorScriptText))
                        activeSponsorScriptDeal = null
                        copiedNotice = "Sponsor segment copied!"
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = StudioViolet)
                ) {
                    Icon(Icons.Default.ContentCopy, contentDescription = null, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(6.dp))
                    Text("Copy Sponsor Segment")
                }
            },
            dismissButton = {
                TextButton(onClick = { activeSponsorScriptDeal = null }) { Text("Close") }
            }
        )
    }
}
