package com.techunicorn.studio.ui.screens.deals

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.models.DealRoomData
import com.techunicorn.studio.domain.models.DealTier
import com.techunicorn.studio.ui.theme.*

@Composable
fun DealRoomScreen(
    initialData: DealRoomData,
    onBack: () -> Unit
) {
    var dealData by remember { mutableStateOf(initialData) }
    var selectedTierKey by remember { mutableStateOf(initialData.selectedTier) }
    var legalSignName by remember { mutableStateOf("") }
    var hasAgreedToDpa by remember { mutableStateOf(false) }
    var isAccepted by remember { mutableStateOf(dealData.acceptedAt != null) }
    var isPaid by remember { mutableStateOf(dealData.paidAt != null) }

    LazyColumn(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Header
        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                IconButton(onClick = onBack) {
                    Icon(Icons.Default.ArrowBack, contentDescription = "Back")
                }
                Text("Deal Room", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(6.dp))
                        .background(StudioEmerald.copy(alpha = 0.15f))
                        .padding(horizontal = 8.dp, vertical = 4.dp)
                ) {
                    Text(if (isPaid) "PAID" else if (isAccepted) "ACCEPTED" else "PROPOSAL", color = StudioEmerald, fontWeight = FontWeight.Bold, fontSize = 11.sp)
                }
            }
        }

        // Deal Summary Card
        item {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)
            ) {
                Column(Modifier.padding(18.dp)) {
                    Text("PARTNERSHIP PROPOSAL", color = StudioCoral, fontWeight = FontWeight.Bold, fontSize = 11.sp)
                    Spacer(Modifier.height(4.dp))
                    Text(dealData.brandName, style = MaterialTheme.typography.headlineLarge, fontWeight = FontWeight.Bold)
                    Text("Prepared by ${dealData.creator} · Ex-Google Engineer", fontSize = 13.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

                    Spacer(Modifier.height(14.dp))
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text("Contract Rate:", fontSize = 14.sp)
                        val currentRate = dealData.tiers.find { it.key == selectedTierKey }?.amount ?: dealData.amount
                        Text("\$${currentRate.toInt()}", fontWeight = FontWeight.Bold, fontSize = 24.sp, color = StudioEmerald)
                    }
                }
            }
        }

        // Scope Switcher
        item {
            Text("Select Partnership Scope", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            Text("Tap to compare deliverable tiers", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }

        items(dealData.tiers) { tier ->
            val isSelected = tier.key == selectedTierKey

            Card(
                onClick = {
                    if (!isAccepted) {
                        selectedTierKey = tier.key
                    }
                },
                modifier = Modifier
                    .fillMaxWidth()
                    .then(
                        if (isSelected) Modifier.border(2.dp, StudioCoral, RoundedCornerShape(14.dp))
                        else Modifier
                    ),
                shape = RoundedCornerShape(14.dp),
                colors = CardDefaults.cardColors(
                    containerColor = if (isSelected) StudioCoral.copy(alpha = 0.08f) else MaterialTheme.colorScheme.surface
                )
            ) {
                Column(Modifier.padding(16.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(tier.name, fontWeight = FontWeight.Bold, fontSize = 16.sp)
                        Text("\$${tier.amount.toInt()}", fontWeight = FontWeight.Bold, fontSize = 16.sp, color = StudioEmerald)
                    }

                    if (tier.badge != null) {
                        Spacer(Modifier.height(4.dp))
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(4.dp))
                                .background(StudioViolet)
                                .padding(horizontal = 6.dp, vertical = 2.dp)
                        ) {
                            Text(tier.badge, color = Color.White, fontWeight = FontWeight.Bold, fontSize = 9.sp)
                        }
                    }

                    Spacer(Modifier.height(6.dp))
                    Text(tier.summary, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

                    Spacer(Modifier.height(8.dp))
                    for (feat in tier.features) {
                        Row(Modifier.padding(vertical = 2.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Check, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(14.dp))
                            Spacer(Modifier.width(6.dp))
                            Text(feat, fontSize = 12.sp)
                        }
                    }
                }
            }
        }

        // Legal Agreement & E-Signature
        item {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(14.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("Legal Agreement & E-Signature", fontWeight = FontWeight.Bold, fontSize = 15.sp)
                    Text("By signing, you agree to the Creator Partnership Agreement and EU DPA terms.", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

                    OutlinedTextField(
                        value = legalSignName,
                        onValueChange = { legalSignName = it },
                        label = { Text("Signee Full Legal Name") },
                        modifier = Modifier.fillMaxWidth(),
                        enabled = !isAccepted
                    )

                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Checkbox(
                            checked = hasAgreedToDpa,
                            onCheckedChange = { hasAgreedToDpa = it },
                            enabled = !isAccepted
                        )
                        Text("I accept the terms and GDPR Data Processing Addendum", fontSize = 12.sp)
                    }

                    Button(
                        onClick = { isAccepted = true },
                        enabled = !isAccepted && legalSignName.isNotBlank() && hasAgreedToDpa,
                        colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Icon(Icons.Default.Draw, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(6.dp))
                        Text(if (isAccepted) "Agreement Cryptographically Signed" else "Sign & Accept Agreement")
                    }

                    if (isAccepted && !isPaid) {
                        Spacer(Modifier.height(8.dp))
                        Button(
                            onClick = { isPaid = true },
                            colors = ButtonDefaults.buttonColors(containerColor = StudioEmerald),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Icon(Icons.Default.Lock, contentDescription = null, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            val currentRate = dealData.tiers.find { it.key == selectedTierKey }?.amount ?: dealData.amount
                            Text("Pay \$${currentRate.toInt()} via Stripe")
                        }
                    }
                }
            }
        }
    }
}
