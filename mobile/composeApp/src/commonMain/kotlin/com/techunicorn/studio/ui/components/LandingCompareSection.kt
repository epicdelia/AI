package com.techunicorn.studio.ui.components

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
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
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.ui.theme.*

/**
 * Data Model for one of the 6 comparison dimensions between Delia, Agencies, and Freelancers.
 */
data class CompareCriterion(
    val title: String,
    val deliaPill: String,
    val deliaAdvantage: String,
    val agencyDisadvantage: String,
    val freelancerDisadvantage: String
)

/**
 * Comparison View Mode for the Mobile Comparison Segmented Switcher.
 */
enum class CompareViewMode(val label: String) {
    DELIA_VS_AGENCY("vs Agency"),
    DELIA_VS_FREELANCER("vs Freelancer"),
    FULL_MATRIX("All 3")
}

/**
 * Mobile Implementation of the Official "Why Delia Lazarescu" Comparison Section:
 * (<section class="landing-section" id="why-delia">)
 *
 * Directives:
 * - Eyebrow: "05: Why Delia Lazarescu"
 * - Headline: "Why Delia Lazarescu" with "Lazarescu" in italics + StudioCoral
 * - Subtitle: "No junior handoffs, no bureaucratic agency layers, and no surprise invoices. Here is how working with Delia compares to traditional options."
 * - Comparison across 6 dimensions:
 *    1. Who writes your code (solo principal)
 *    2. Shipping velocity (2–4 weeks)
 *    3. Code quality & standards (ex-Google quality)
 *    4. Pricing predictability (zero surprise fees)
 *    5. Communication (full transparency)
 *    6. Launch & distribution (built-in distribution)
 * - Official isometric brand mark on Delia's card header (StudioLogo)
 * - Clean flat checkmarks for Delia vs flat cross marks for Traditional Agencies and Freelance Marketplaces
 * - Segmented comparison filter (vs Agency, vs Freelancer, All 3)
 * - Footer CTA card with 1-tap "Book Your 30-Min Intro Call →"
 * - Strict flat design: 0dp elevation, crisp 1dp borders, solid accent fills, zero futuristic or sci-fi glows
 */
@Composable
fun LandingCompareSection(
    onBookIntroCall: () -> Unit,
    modifier: Modifier = Modifier
) {
    var viewMode by remember { mutableStateOf(CompareViewMode.FULL_MATRIX) }
    var selectedCriterionIndex by remember { mutableStateOf(0) }

    val criteria = remember {
        listOf(
            CompareCriterion(
                title = "Who writes your code",
                deliaPill = "solo principal",
                deliaAdvantage = "Delia directly (Ex-Google systems engineer; partnered with dedicated tech firm for specialized scale)",
                agencyDisadvantage = "Junior devs rotated behind layers of account managers",
                freelancerDisadvantage = "Unvetted contractors with unpredictable seniority"
            ),
            CompareCriterion(
                title = "Shipping velocity",
                deliaPill = "2–4 weeks",
                deliaAdvantage = "2 to 4 weeks fixed sprint with weekly Friday recorded Loom demos",
                agencyDisadvantage = "3 to 6 months bogged down in committee meetings",
                freelancerDisadvantage = "Variable timelines & frequent milestone ghosting"
            ),
            CompareCriterion(
                title = "Code quality & standards",
                deliaPill = "ex-Google quality",
                deliaAdvantage = "Production-grade architecture, automated tests & 30-day warranty",
                agencyDisadvantage = "Over-engineered codebases or cookie-cutter templates",
                freelancerDisadvantage = "Patchwork scripts with high hidden technical debt"
            ),
            CompareCriterion(
                title = "Pricing predictability",
                deliaPill = "zero surprise fees",
                deliaAdvantage = "100% fixed-price scope or transparent monthly retainer",
                agencyDisadvantage = "Billable hours, change orders & ballooning invoices",
                freelancerDisadvantage = "Low initial bids that escalate with billable extras"
            ),
            CompareCriterion(
                title = "Communication",
                deliaPill = "full transparency",
                deliaAdvantage = "Async Slack/WhatsApp + weekly recorded Loom walkthroughs",
                agencyDisadvantage = "Bureaucratic weekly status decks & endless sync calls",
                freelancerDisadvantage = "Sporadic messages and vanishing progress updates"
            ),
            CompareCriterion(
                title = "Launch & distribution",
                deliaPill = "built-in distribution",
                deliaAdvantage = "Built-in launch amplification to 519K+ developer community",
                agencyDisadvantage = "Zero distribution (they hand over code and walk away)",
                freelancerDisadvantage = "Zero audience reach or post-launch visibility"
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
                    text = "05: WHY DELIA LAZARESCU",
                    color = StudioCoral,
                    fontWeight = FontWeight.Bold,
                    fontSize = 11.sp,
                    fontFamily = FontFamily.Monospace,
                    letterSpacing = 1.sp
                )

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        text = "Why Delia ",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Text(
                        text = "Lazarescu",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.ExtraBold,
                        fontStyle = FontStyle.Italic,
                        color = StudioCoral
                    )
                }

                Spacer(Modifier.height(2.dp))

                Text(
                    text = "No junior handoffs, no bureaucratic agency layers, and no surprise invoices. Here is how working with Delia compares to traditional options.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    lineHeight = 20.sp
                )
            }

            // ================= 2. VIEW MODE SEGMENTED TOGGLE =================
            Surface(
                shape = RoundedCornerShape(8.dp),
                color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.45f),
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.25f)),
                modifier = Modifier.fillMaxWidth()
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(3.dp),
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    CompareViewMode.entries.forEach { mode ->
                        val isSelected = viewMode == mode
                        Surface(
                            shape = RoundedCornerShape(6.dp),
                            color = if (isSelected) StudioCoral else Color.Transparent,
                            modifier = Modifier
                                .weight(1f)
                                .clip(RoundedCornerShape(6.dp))
                                .clickable { viewMode = mode }
                        ) {
                            Text(
                                text = mode.label,
                                fontSize = 11.sp,
                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal,
                                color = if (isSelected) Color.White else MaterialTheme.colorScheme.onSurface,
                                modifier = Modifier
                                    .padding(vertical = 7.dp)
                                    .wrapContentWidth(Alignment.CenterHorizontally)
                            )
                        }
                    }
                }
            }

            // ================= 3. SIX COMPARISON DIMENSION CARDS =================
            Column(
                modifier = Modifier.fillMaxWidth(),
                verticalArrangement = Arrangement.spacedBy(14.dp)
            ) {
                criteria.forEachIndexed { index, criterion ->
                    Surface(
                        shape = RoundedCornerShape(12.dp),
                        color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.35f),
                        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.25f)),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Column(
                            modifier = Modifier.padding(14.dp),
                            verticalArrangement = Arrangement.spacedBy(10.dp)
                        ) {
                            // Dimension Title & Delia Badge
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Text(
                                    text = criterion.title,
                                    style = MaterialTheme.typography.titleMedium,
                                    fontWeight = FontWeight.Bold,
                                    color = MaterialTheme.colorScheme.onSurface
                                )
                                Surface(
                                    shape = RoundedCornerShape(4.dp),
                                    color = StudioCoral.copy(alpha = 0.12f),
                                    border = BorderStroke(1.dp, StudioCoral.copy(alpha = 0.35f))
                                ) {
                                    Text(
                                        text = criterion.deliaPill,
                                        fontSize = 9.sp,
                                        fontWeight = FontWeight.Bold,
                                        fontFamily = FontFamily.Monospace,
                                        color = StudioCoral,
                                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                    )
                                }
                            }

                            // 1. Delia Advantage Card (Highlighted in Theme)
                            Surface(
                                shape = RoundedCornerShape(8.dp),
                                color = StudioCoral.copy(alpha = 0.08f),
                                border = BorderStroke(1.dp, StudioCoral.copy(alpha = 0.3f)),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Row(
                                    modifier = Modifier.padding(10.dp),
                                    verticalAlignment = Alignment.Top,
                                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .padding(top = 1.dp)
                                            .size(18.dp)
                                            .clip(RoundedCornerShape(4.dp))
                                            .background(StudioGoodLight),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(
                                            Icons.Default.Check,
                                            contentDescription = null,
                                            modifier = Modifier.size(12.dp),
                                            tint = Color.White
                                        )
                                    }
                                    Column {
                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                                        ) {
                                            StudioLogo(size = 12.dp, tint = StudioCoral)
                                            Text(
                                                text = "Delia Lazarescu",
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 11.sp,
                                                color = StudioCoral
                                            )
                                        }
                                        Spacer(Modifier.height(2.dp))
                                        Text(
                                            text = criterion.deliaAdvantage,
                                            fontSize = 11.sp,
                                            color = MaterialTheme.colorScheme.onSurface,
                                            lineHeight = 16.sp
                                        )
                                    }
                                }
                            }

                            // 2. Traditional Dev Agency Comparison
                            if (viewMode == CompareViewMode.DELIA_VS_AGENCY || viewMode == CompareViewMode.FULL_MATRIX) {
                                Surface(
                                    shape = RoundedCornerShape(8.dp),
                                    color = MaterialTheme.colorScheme.surface,
                                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.2f)),
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    Row(
                                        modifier = Modifier.padding(10.dp),
                                        verticalAlignment = Alignment.Top,
                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                    ) {
                                        Box(
                                            modifier = Modifier
                                                .padding(top = 1.dp)
                                                .size(18.dp)
                                                .clip(RoundedCornerShape(4.dp))
                                                .background(MaterialTheme.colorScheme.error.copy(alpha = 0.15f)),
                                            contentAlignment = Alignment.Center
                                        ) {
                                            Icon(
                                                Icons.Default.Close,
                                                contentDescription = null,
                                                modifier = Modifier.size(12.dp),
                                                tint = MaterialTheme.colorScheme.error
                                            )
                                        }
                                        Column {
                                            Text(
                                                text = "Traditional Dev Agency (5–15 person firm)",
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 11.sp,
                                                color = MaterialTheme.colorScheme.onSurfaceVariant
                                            )
                                            Spacer(Modifier.height(2.dp))
                                            Text(
                                                text = criterion.agencyDisadvantage,
                                                fontSize = 11.sp,
                                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                                lineHeight = 16.sp
                                            )
                                        }
                                    }
                                }
                            }

                            // 3. Freelance Marketplace Comparison
                            if (viewMode == CompareViewMode.DELIA_VS_FREELANCER || viewMode == CompareViewMode.FULL_MATRIX) {
                                Surface(
                                    shape = RoundedCornerShape(8.dp),
                                    color = MaterialTheme.colorScheme.surface,
                                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.2f)),
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    Row(
                                        modifier = Modifier.padding(10.dp),
                                        verticalAlignment = Alignment.Top,
                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                    ) {
                                        Box(
                                            modifier = Modifier
                                                .padding(top = 1.dp)
                                                .size(18.dp)
                                                .clip(RoundedCornerShape(4.dp))
                                                .background(MaterialTheme.colorScheme.error.copy(alpha = 0.15f)),
                                            contentAlignment = Alignment.Center
                                        ) {
                                            Icon(
                                                Icons.Default.Close,
                                                contentDescription = null,
                                                modifier = Modifier.size(12.dp),
                                                tint = MaterialTheme.colorScheme.error
                                            )
                                        }
                                        Column {
                                            Text(
                                                text = "Freelance Marketplace (Upwork / Fiverr)",
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 11.sp,
                                                color = MaterialTheme.colorScheme.onSurfaceVariant
                                            )
                                            Spacer(Modifier.height(2.dp))
                                            Text(
                                                text = criterion.freelancerDisadvantage,
                                                fontSize = 11.sp,
                                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                                lineHeight = 16.sp
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // ================= 4. FOOTER CALL-TO-ACTION CARD =================
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
                    Text(
                        text = "Ready to skip agency overhead and ship your MVP in weeks?",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )

                    Button(
                        onClick = onBookIntroCall,
                        colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                        shape = RoundedCornerShape(8.dp),
                        modifier = Modifier.fillMaxWidth(),
                        contentPadding = PaddingValues(horizontal = 14.dp, vertical = 11.dp)
                    ) {
                        Icon(
                            Icons.Default.Bolt,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = Color.White
                        )
                        Spacer(Modifier.width(6.dp))
                        Text(
                            text = "Book Your 30-Min Intro Call",
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

