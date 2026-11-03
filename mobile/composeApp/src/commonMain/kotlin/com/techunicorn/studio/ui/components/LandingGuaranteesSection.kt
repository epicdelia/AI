package com.techunicorn.studio.ui.components

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.ui.theme.*

/**
 * Data Model for Client Guarantees and Engineering Commitments.
 */
data class ClientGuaranteeItem(
    val num: String,
    val title: String,
    val badge: String,
    val description: String,
    val icon: ImageVector,
    val accentColor: Color
)

/**
 * Mobile Implementation of the Official Client Commitments & Guarantee Manifesto:
 * (<section class="landing-section tinted" id="guarantees">)
 *
 * Directives:
 * - Eyebrow: "COMMITMENTS & STANDARDS"
 * - Headline: "The client guarantee manifesto" with "guarantee manifesto" in italics + StudioCoral
 * - Subtitle: "Engineering contracts built on mutual trust, transparency, and accountability. Here are the 6 ironclad commitments behind every build."
 * - 6 Complete Guarantee Cards:
 *    1. 100% IP & Asset Custody (01 · 100% Yours)
 *    2. Zero Junior Delegation (02 · Direct Principal)
 *    3. Weekly Friday Loom Demos (03 · Weekly Proof)
 *    4. 100% Fixed-Price Contract (04 · Fixed Guarantee)
 *    5. 30-Day Post-Launch Warranty (05 · 30-Day Shield)
 *    6. Complete Ops Runbooks & Video Handover (06 · Zero Debt)
 * - Bottom High-Trust Banner with 1-tap "Book 30-Min Intro Call →"
 * - Strict flat design: 0dp elevation, crisp 1dp borders, solid accent fills, zero futuristic or sci-fi glows
 */
@Composable
fun LandingGuaranteesSection(
    onBookIntroCall: () -> Unit,
    modifier: Modifier = Modifier
) {
    val guarantees = remember {
        listOf(
            ClientGuaranteeItem(
                num = "01",
                title = "100% IP & Asset Custody",
                badge = "100% Yours",
                description = "From Day 1, all code is pushed to your private GitHub repository under your organization. Cloud accounts (GCP, AWS, Vercel) and API keys are set up in your name. Zero hostage fees or proprietary lock-ins.",
                icon = Icons.Default.Lock,
                accentColor = StudioCoral
            ),
            ClientGuaranteeItem(
                num = "02",
                title = "Zero Junior Delegation",
                badge = "Direct Principal",
                description = "No bait-and-switch. You hire an Ex-Google Systems Engineer, and Delia personally architects, writes, tests, and deploys every single line of production code. No junior contractors or outsourced teams.",
                icon = Icons.Default.Code,
                accentColor = StudioViolet
            ),
            ClientGuaranteeItem(
                num = "03",
                title = "Weekly Friday Loom Demos",
                badge = "Weekly Proof",
                description = "Complete engineering transparency. Every Friday afternoon, you receive a detailed recorded video walk-through demonstrating working software running on your password-protected live staging environment.",
                icon = Icons.Default.PlayArrow,
                accentColor = StudioOcean
            ),
            ClientGuaranteeItem(
                num = "04",
                title = "100% Fixed-Price Contract",
                badge = "Fixed Guarantee",
                description = "The quote agreed in Phase 02 Blueprint is final and binding. No hourly meter running, no surprise invoices, and no unexpected change orders. Complete budget certainty from start to finish.",
                icon = Icons.Default.Balance,
                accentColor = StudioAmber
            ),
            ClientGuaranteeItem(
                num = "05",
                title = "30-Day Post-Launch Warranty",
                badge = "30-Day Shield",
                description = "Every shipped MVP includes 30 days of comprehensive post-launch warranty at zero extra cost. If any bug or unintended behavior arises within the agreed scope, it is patched immediately with priority.",
                icon = Icons.Default.Security,
                accentColor = StudioEmerald
            ),
            ClientGuaranteeItem(
                num = "06",
                title = "Complete Ops Runbooks & Video Handover",
                badge = "Zero Debt",
                description = "You receive thorough documentation, API schema contracts, environment secrets setup guides, and recorded video onboarding for your future developers or maintenance team.",
                icon = Icons.Default.Description,
                accentColor = StudioCoral
            )
        )
    }

    Surface(
        modifier = modifier.fillMaxWidth(),
        color = MaterialTheme.colorScheme.surface,
        shape = RoundedCornerShape(20.dp),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.35f))
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(20.dp),
            verticalArrangement = Arrangement.spacedBy(18.dp)
        ) {
            // ================= 1. SECTION HEADER =================
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(
                    text = "COMMITMENTS & STANDARDS",
                    color = StudioCoral,
                    fontWeight = FontWeight.Bold,
                    fontSize = 11.sp,
                    fontFamily = FontFamily.Monospace,
                    letterSpacing = 1.sp
                )

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        text = "The client ",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Text(
                        text = "guarantee manifesto",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.ExtraBold,
                        fontStyle = FontStyle.Italic,
                        color = StudioCoral
                    )
                }

                Spacer(Modifier.height(2.dp))

                Text(
                    text = "Engineering contracts built on mutual trust, transparency, and accountability. Here are the 6 ironclad commitments behind every build.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    lineHeight = 20.sp
                )
            }

            // ================= 2. SIX GUARANTEE CARDS =================
            Column(
                modifier = Modifier.fillMaxWidth(),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                guarantees.forEach { item ->
                    Surface(
                        shape = RoundedCornerShape(12.dp),
                        color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.45f),
                        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.25f)),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(14.dp),
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            // Top Row: Icon, Number, Badge
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(30.dp)
                                            .clip(RoundedCornerShape(6.dp))
                                            .background(item.accentColor.copy(alpha = 0.15f)),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(
                                            imageVector = item.icon,
                                            contentDescription = null,
                                            modifier = Modifier.size(15.dp),
                                            tint = item.accentColor
                                        )
                                    }

                                    Text(
                                        text = item.num,
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.Bold,
                                        fontFamily = FontFamily.Monospace,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                }

                                // Badge
                                Surface(
                                    shape = RoundedCornerShape(4.dp),
                                    color = item.accentColor.copy(alpha = 0.12f),
                                    border = BorderStroke(1.dp, item.accentColor.copy(alpha = 0.35f))
                                ) {
                                    Text(
                                        text = item.badge,
                                        fontSize = 9.sp,
                                        fontWeight = FontWeight.Bold,
                                        fontFamily = FontFamily.Monospace,
                                        color = item.accentColor,
                                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                    )
                                }
                            }

                            // Title
                            Text(
                                text = item.title,
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold,
                                color = MaterialTheme.colorScheme.onSurface
                            )

                            // Description
                            Text(
                                text = item.description,
                                fontSize = 12.sp,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                lineHeight = 18.sp
                            )
                        }
                    }
                }
            }

            // ================= 3. BOTTOM BANNER CARD =================
            Surface(
                shape = RoundedCornerShape(12.dp),
                color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.6f),
                border = BorderStroke(1.dp, StudioCoral.copy(alpha = 0.35f)),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(
                    modifier = Modifier.padding(14.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                        Text(
                            text = "Ready for a high-trust engineering partnership?",
                            fontWeight = FontWeight.Bold,
                            fontSize = 13.sp,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                        Text(
                            text = "Book a complimentary 30-minute intro call to review your build scope.",
                            fontSize = 11.sp,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }

                    Button(
                        onClick = onBookIntroCall,
                        colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                        shape = RoundedCornerShape(8.dp),
                        modifier = Modifier.fillMaxWidth(),
                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 10.dp)
                    ) {
                        Icon(
                            Icons.Default.CalendarMonth,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = Color.White
                        )
                        Spacer(Modifier.width(6.dp))
                        Text(
                            text = "Book 30-Min Intro Call",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White
                        )
                        Spacer(Modifier.width(4.dp))
                        Icon(
                            Icons.AutoMirrored.Filled.ArrowForward,
                            contentDescription = null,
                            modifier = Modifier.size(13.dp),
                            tint = Color.White
                        )
                    }
                }
            }
        }
    }
}

