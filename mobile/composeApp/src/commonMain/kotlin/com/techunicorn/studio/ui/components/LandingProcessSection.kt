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
 * Data model for a Process Step in the "How it works" section.
 */
data class ProcessStepItem(
    val stepNumber: Int,
    val title: String,
    val timelineBadge: String,
    val description: String,
    val bulletPoints: List<String>,
    val icon: ImageVector,
    val accentColor: Color
)

/**
 * Mobile Implementation of the Official "How it works" Section:
 * (<section class="process" id="process">)
 *
 * Directives:
 * - Eyebrow: "03 — How it works"
 * - Headline: "From first call to shipped in weeks" with "shipped" in italics + StudioCoral
 * - 3 Sequential Steps:
 *    1. Intro call (Free 30-minute call, honest advice on feasibility)
 *    2. Fixed-scope proposal (Within 48 hours, fixed price, no surprise invoices)
 *    3. Build, demo, ship (Weekly Friday demos, live deployment, 30 days support)
 * - Interactive step expansion & vertical progress connector line
 * - 3 Guarantees micro-cards (100% IP ownership, Zero scope creep, 30-day warranty)
 * - Primary CTA: "Book your 30-min intro call →"
 * - Strict flat design: 0dp elevation, crisp 1dp borders, solid accent fills, zero futuristic or sci-fi glows
 */
@Composable
fun LandingProcessSection(
    onBookIntroCall: () -> Unit,
    modifier: Modifier = Modifier
) {
    var selectedStepIndex by remember { mutableStateOf(0) }

    val steps = remember {
        listOf(
            ProcessStepItem(
                stepNumber = 1,
                title = "Intro call",
                timelineBadge = "30 min free",
                description = "A free 30-minute call. You bring the idea or the problem; I'll tell you honestly whether it's a 2-week build, a retainer, or not worth your money.",
                bulletPoints = listOf(
                    "Direct engineering review (zero sales reps)",
                    "Stack & architecture recommendation",
                    "Honest build-vs-buy feasibility assessment"
                ),
                icon = Icons.Default.CalendarMonth,
                accentColor = StudioCoral
            ),
            ProcessStepItem(
                stepNumber = 2,
                title = "Fixed-scope proposal",
                timelineBadge = "Within 48 hours",
                description = "Within 48 hours you get a one-page proposal: deliverables, timeline, and a fixed price. No hourly billing, no scope creep, no surprise invoices.",
                bulletPoints = listOf(
                    "One-page crystal clear scope & deliverables",
                    "Guaranteed fixed price in writing",
                    "Private GitHub repository setup & access"
                ),
                icon = Icons.Default.Description,
                accentColor = StudioViolet
            ),
            ProcessStepItem(
                stepNumber = 3,
                title = "Build, demo, ship",
                timelineBadge = "2 to 4 weeks",
                description = "Weekly demos while I build, a live product at the end, and 30 days of post-launch support included on every build.",
                bulletPoints = listOf(
                    "Weekly Friday interactive demos & progress",
                    "Live production deployment with automated CI/CD",
                    "30 days post-launch warranty & bugfix support"
                ),
                icon = Icons.Default.RocketLaunch,
                accentColor = StudioEmerald
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
                    text = "03 — HOW IT WORKS",
                    color = StudioCoral,
                    fontWeight = FontWeight.Bold,
                    fontSize = 11.sp,
                    fontFamily = FontFamily.Monospace,
                    letterSpacing = 1.sp
                )

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        text = "From first call to ",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Text(
                        text = "shipped",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.ExtraBold,
                        fontStyle = FontStyle.Italic,
                        color = StudioCoral
                    )
                    Text(
                        text = " in weeks",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                }

                Spacer(Modifier.height(2.dp))

                Text(
                    text = "A transparent, milestone-driven process designed for speed and zero bureaucracy.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    lineHeight = 20.sp
                )
            }

            // ================= 2. SEQUENTIAL STEPS VERTICAL TIMELINE =================
            Column(
                modifier = Modifier.fillMaxWidth(),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                steps.forEachIndexed { index, step ->
                    val isSelected = selectedStepIndex == index

                    Surface(
                        shape = RoundedCornerShape(14.dp),
                        color = if (isSelected) MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.6f)
                        else MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.25f),
                        border = BorderStroke(
                            1.dp,
                            if (isSelected) step.accentColor else MaterialTheme.colorScheme.outline.copy(alpha = 0.25f)
                        ),
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(14.dp))
                            .clickable { selectedStepIndex = index }
                    ) {
                        Column(
                            modifier = Modifier.padding(14.dp),
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            // Step Header: Step Number, Title, Timeline Badge
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                                ) {
                                    // Number Pill
                                    Box(
                                        modifier = Modifier
                                            .size(28.dp)
                                            .clip(CircleShape)
                                            .background(if (isSelected) step.accentColor else MaterialTheme.colorScheme.surfaceVariant)
                                            .border(
                                                1.dp,
                                                if (isSelected) step.accentColor else MaterialTheme.colorScheme.outline.copy(
                                                    alpha = 0.4f
                                                ),
                                                CircleShape
                                            ),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Text(
                                            text = step.stepNumber.toString(),
                                            fontSize = 12.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = if (isSelected) Color.White else MaterialTheme.colorScheme.onSurface
                                        )
                                    }

                                    Text(
                                        text = step.title,
                                        style = MaterialTheme.typography.titleMedium,
                                        fontWeight = FontWeight.Bold,
                                        color = MaterialTheme.colorScheme.onSurface
                                    )
                                }

                                // Timeline Badge
                                Surface(
                                    shape = RoundedCornerShape(4.dp),
                                    color = step.accentColor.copy(alpha = 0.12f),
                                    border = BorderStroke(1.dp, step.accentColor.copy(alpha = 0.3f))
                                ) {
                                    Text(
                                        text = step.timelineBadge,
                                        fontSize = 9.sp,
                                        fontWeight = FontWeight.Bold,
                                        fontFamily = FontFamily.Monospace,
                                        color = step.accentColor,
                                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                    )
                                }
                            }

                            // Description
                            Text(
                                text = step.description,
                                fontSize = 12.sp,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                lineHeight = 18.sp
                            )

                            // Expandable Bullet Points when active/selected
                            AnimatedVisibility(
                                visible = isSelected,
                                enter = expandVertically() + fadeIn(),
                                exit = shrinkVertically() + fadeOut()
                            ) {
                                Column(
                                    modifier = Modifier.padding(top = 4.dp),
                                    verticalArrangement = Arrangement.spacedBy(4.dp)
                                ) {
                                    HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.2f))
                                    Spacer(Modifier.height(2.dp))

                                    step.bulletPoints.forEach { point ->
                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                                        ) {
                                            Icon(
                                                Icons.Default.Check,
                                                contentDescription = null,
                                                modifier = Modifier.size(13.dp),
                                                tint = step.accentColor
                                            )
                                            Text(
                                                text = point,
                                                fontSize = 11.sp,
                                                fontWeight = FontWeight.Medium,
                                                color = MaterialTheme.colorScheme.onSurface
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // ================= 3. THREE CORE GUARANTEES =================
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                FlatProcessGuaranteeCard(
                    title = "100% IP Ownership",
                    subtitle = "Private GitHub commits",
                    accentColor = StudioCoral,
                    modifier = Modifier.weight(1f)
                )
                FlatProcessGuaranteeCard(
                    title = "Zero Scope Creep",
                    subtitle = "Fixed price in writing",
                    accentColor = StudioViolet,
                    modifier = Modifier.weight(1f)
                )
                FlatProcessGuaranteeCard(
                    title = "30-Day Warranty",
                    subtitle = "Post-launch support",
                    accentColor = StudioEmerald,
                    modifier = Modifier.weight(1f)
                )
            }

            // ================= 4. PRIMARY ACTION BUTTON =================
            Button(
                onClick = onBookIntroCall,
                colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                shape = RoundedCornerShape(10.dp),
                modifier = Modifier.fillMaxWidth(),
                contentPadding = PaddingValues(horizontal = 14.dp, vertical = 12.dp)
            ) {
                Icon(
                    Icons.Default.CalendarMonth,
                    contentDescription = null,
                    modifier = Modifier.size(15.dp),
                    tint = Color.White
                )
                Spacer(Modifier.width(6.dp))
                Text(
                    text = "Book your 30-min intro call",
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

/**
 * Micro Flat Guarantee Card for Process Section.
 */
@Composable
private fun FlatProcessGuaranteeCard(
    title: String,
    subtitle: String,
    accentColor: Color,
    modifier: Modifier = Modifier
) {
    Surface(
        shape = RoundedCornerShape(8.dp),
        color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.35f),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.25f)),
        modifier = modifier
    ) {
        Column(modifier = Modifier.padding(8.dp)) {
            Text(
                text = title,
                fontSize = 10.sp,
                fontWeight = FontWeight.Bold,
                color = accentColor,
                maxLines = 1
            )
            Spacer(Modifier.height(2.dp))
            Text(
                text = subtitle,
                fontSize = 9.sp,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1
            )
        }
    }
}

