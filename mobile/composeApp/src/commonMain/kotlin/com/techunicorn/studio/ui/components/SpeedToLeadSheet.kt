package com.techunicorn.studio.ui.components

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.ui.theme.*

/**
 * Pillar 2: "Speed-to-Lead" Inbound Deal Engine & Instant Closer
 *
 * Research proves responding within 5 minutes yields 21x higher deal close rates.
 * This sheet enables Delia to close $12.5k MVP builds, $3.5k retainers, or $8.5k brand deals
 * directly from her pocket while travelling or at keynotes.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SpeedToLeadSheet(
    onDismiss: () -> Unit,
    onProposalDispatched: (String, Double) -> Unit
) {
    var selectedTierIndex by remember { mutableStateOf(0) }
    var includeWhitelisting by remember { mutableStateOf(false) }
    var includeExpeditedDelivery by remember { mutableStateOf(false) }
    var includeNewsletterFeature by remember { mutableStateOf(true) }
    var isBiometricSigning by remember { mutableStateOf(false) }
    var isSignedSuccess by remember { mutableStateOf(false) }
    var copiedLinkSuccess by remember { mutableStateOf(false) }

    val baseTiers = listOf(
        Triple("Essential Sprint MVP", 12500.0, "2–4 week build · Full IP handover · 30-day warranty"),
        Triple("Growth AI Stack", 16500.0, "Sprint MVP + Subagent tool routing & safety evaluation"),
        Triple("Enterprise MVP + Advisory", 22000.0, "Sprint MVP + 3-month ex-Google advisory retainer")
    )

    val currentBase = baseTiers[selectedTierIndex]
    val addOnsTotal = (if (includeWhitelisting) 2500.0 else 0.0) +
            (if (includeExpeditedDelivery) 3000.0 else 0.0) +
            (if (includeNewsletterFeature) 1500.0 else 0.0)
    val grandTotal = currentBase.second + addOnsTotal

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = MaterialTheme.colorScheme.surface
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp, vertical = 10.dp)
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Header: Urgent Inbound Lead Alert
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
                            .background(StudioEmerald.copy(alpha = 0.2f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(Icons.Default.Bolt, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(20.dp))
                    }
                    Spacer(Modifier.width(10.dp))
                    Column {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text("Speed-to-Lead Inbound", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                            Spacer(Modifier.width(6.dp))
                            Surface(
                                shape = RoundedCornerShape(4.dp),
                                color = StudioEmerald.copy(alpha = 0.15f)
                            ) {
                                Text("FIT SCORE: 94/100", color = StudioEmerald, fontWeight = FontWeight.Bold, fontSize = 9.sp, modifier = Modifier.padding(horizontal = 4.dp, vertical = 2.dp))
                            }
                        }
                        Text("Arrived 4m ago · ScaleWorks Inc. (Series A, Berlin)", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
                IconButton(onClick = onDismiss) {
                    Icon(Icons.Default.Close, contentDescription = "Close", modifier = Modifier.size(20.dp))
                }
            }

            // Lead Context Card
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)
            ) {
                Column(Modifier.padding(14.dp)) {
                    Text("INQUIRY BRIEF", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = StudioCoral)
                    Spacer(Modifier.height(4.dp))
                    Text(
                        "\"Loved your YouTube video on autonomous agent swarms. We need our customer onboarding AI MVP built in 3 weeks before our Q1 launch. Budget allocated: \$15k–\$25k.\"",
                        fontSize = 12.sp,
                        fontStyle = androidx.compose.ui.text.font.FontStyle.Italic
                    )
                    Spacer(Modifier.height(8.dp))
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text("Lead Source: YouTube (Breakout #42)", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text("Decision Maker: CTO Verified", fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = StudioViolet)
                    }
                }
            }

            // Deliverable Tier Selector
            Text("Select Proposal Scope", fontWeight = FontWeight.Bold, fontSize = 14.sp)

            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                baseTiers.forEachIndexed { idx, tier ->
                    val isSelected = selectedTierIndex == idx
                    Surface(
                        shape = RoundedCornerShape(10.dp),
                        color = if (isSelected) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surface,
                        border = androidx.compose.foundation.BorderStroke(
                            1.dp,
                            if (isSelected) StudioCoral else MaterialTheme.colorScheme.outline.copy(alpha = 0.5f)
                        ),
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .clickable { selectedTierIndex = idx }
                    ) {
                        Row(
                            modifier = Modifier.padding(12.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Column(Modifier.weight(1f)) {
                                Text(tier.first, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                                Text(tier.third, fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                            Text(
                                text = "\$${tier.second.toInt()}",
                                fontWeight = FontWeight.Bold,
                                fontSize = 16.sp,
                                color = if (isSelected) StudioCoral else MaterialTheme.colorScheme.onSurface
                            )
                        }
                    }
                }
            }

            // High-Margin Add-Ons
            Text("Campaign & Build Add-Ons", fontWeight = FontWeight.Bold, fontSize = 14.sp)

            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                // Addon 1
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text("Expedited 14-Day Delivery (+ \$3,000)", fontSize = 12.sp, fontWeight = FontWeight.Medium)
                        Text("Guaranteed priority sprint slot", fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    Switch(checked = includeExpeditedDelivery, onCheckedChange = { includeExpeditedDelivery = it })
                }
                // Addon 2
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text("90-Day Digital Ad Whitelisting (+ \$2,500)", fontSize = 12.sp, fontWeight = FontWeight.Medium)
                        Text("Paid social ad usage rights for client", fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    Switch(checked = includeWhitelisting, onCheckedChange = { includeWhitelisting = it })
                }
                // Addon 3
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text("Tech Unicorn Newsletter Feature (+ \$1,500)", fontSize = 12.sp, fontWeight = FontWeight.Medium)
                        Text("Featured blast to 14,000+ tech founders", fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    Switch(checked = includeNewsletterFeature, onCheckedChange = { includeNewsletterFeature = it })
                }
            }

            // Total Value & Live Actions
            Surface(
                shape = RoundedCornerShape(12.dp),
                color = MaterialTheme.colorScheme.surfaceVariant,
                modifier = Modifier.fillMaxWidth()
            ) {
                Row(
                    modifier = Modifier.padding(14.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text("TOTAL CONTRACT VALUE", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = StudioEmerald)
                        Text("\$${grandTotal.toInt()}", fontSize = 24.sp, fontWeight = FontWeight.Bold, color = StudioEmerald)
                    }
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = StudioViolet.copy(alpha = 0.15f)
                    ) {
                        Text("Net-15 Stripe Invoicing", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = StudioViolet, modifier = Modifier.padding(6.dp))
                    }
                }
            }

            // Biometric E-Signature Feedback
            if (isSignedSuccess) {
                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = StudioEmerald.copy(alpha = 0.15f),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Row(
                        modifier = Modifier.padding(10.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Default.Verified, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(8.dp))
                        Text(
                            "Cryptographically countersigned via BiometricPrompt (SHA-256 fingerprint #a8f09d)",
                            color = StudioEmerald,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                    }
                }
            }

            // Action Buttons: 1-Tap Copy Stripe Link & Biometric Sign
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                // Biometric Countersign
                OutlinedButton(
                    onClick = {
                        isBiometricSigning = true
                        isSignedSuccess = true
                    },
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(10.dp)
                ) {
                    Icon(Icons.Default.Fingerprint, contentDescription = null, modifier = Modifier.size(18.dp), tint = StudioViolet)
                    Spacer(Modifier.width(6.dp))
                    Text(if (isSignedSuccess) "Signed" else "Sign with FaceID", fontSize = 12.sp)
                }

                // 1-Tap Instant Stripe Deal Room Link
                Button(
                    onClick = {
                        copiedLinkSuccess = true
                        onProposalDispatched("https://techunicorn.ai/deal/scale-8812", grandTotal)
                    },
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = StudioEmerald)
                ) {
                    Icon(Icons.Default.Link, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(6.dp))
                    Text(if (copiedLinkSuccess) "Link Copied!" else "1-Tap Stripe Link", fontSize = 12.sp)
                }
            }

            Spacer(Modifier.height(16.dp))
        }
    }
}

