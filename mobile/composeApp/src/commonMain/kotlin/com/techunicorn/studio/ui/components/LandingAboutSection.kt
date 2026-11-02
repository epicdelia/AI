package com.techunicorn.studio.ui.components

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material.icons.automirrored.filled.OpenInNew
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.ui.theme.*

/**
 * Journey Milestone data class for the interactive "The path so far" timeline.
 */
data class JourneyMilestone(
    val stepLabel: String,
    val title: String,
    val detail: String,
    val isCurrent: Boolean = false
)

/**
 * Key fact data representation for the About section.
 */
data class AboutFactMetric(
    val number: String,
    val unit: String,
    val subtitle: String,
    val icon: ImageVector,
    val accentColor: Color
)

/**
 * Mobile Implementation of the Official Landing About Section:
 * (<section class="landing-section landing-about" id="about">)
 *
 * Directives:
 * - Eyebrow: "01: About"
 * - Headline: "Engineer first. Storyteller always." with "Storyteller" in italics + StudioCoral
 * - Bio paragraph: "I spent years writing production code at Google before trading the campus for a carry-on..."
 * - Thesis blockquote: "Tech feels scary because it is explained badly."
 * - Facts row: 69 countries, 519K+ people, 5 talks
 * - Interactive expandable journey accordion ("The path so far" -> 4 milestones)
 * - Dual action buttons: "Book an intro call" + "Read my story"
 * - Founder showcase card: Ex-Google Engineer & Founder badge, "Now: Building AI-powered apps...", "1 build slot open"
 * - Strict flat design: 0dp elevation, crisp 1dp borders, solid accent fills, zero sci-fi neon or blur gradients
 */
@Composable
fun LandingAboutSection(
    onBookIntroCall: () -> Unit,
    onReadStory: () -> Unit,
    onFactClick: (String) -> Unit = {},
    modifier: Modifier = Modifier
) {
    var isJourneyExpanded by remember { mutableStateOf(false) }

    val milestones = remember {
        listOf(
            JourneyMilestone(
                stepLabel = "1",
                title = "Self-taught engineer",
                detail = "Learned to code on her own and got hired as a software engineer."
            ),
            JourneyMilestone(
                stepLabel = "2",
                title = "Google",
                detail = "Wrote production code at Google."
            ),
            JourneyMilestone(
                stepLabel = "3",
                title = "Independent founder",
                detail = "Left Big Tech to build AI-powered products for clients, from anywhere."
            ),
            JourneyMilestone(
                stepLabel = "✨",
                title = "Creator & speaker",
                detail = "Explains tech to a large community and on stage.",
                isCurrent = true
            )
        )
    }

    val facts = remember {
        listOf(
            AboutFactMetric(
                number = "69",
                unit = "countries",
                subtitle = "worked and shipped from",
                icon = Icons.Default.Public,
                accentColor = StudioOcean
            ),
            AboutFactMetric(
                number = "519K+",
                unit = "people",
                subtitle = "follow along across 6 networks",
                icon = Icons.Default.People,
                accentColor = StudioCoral
            ),
            AboutFactMetric(
                number = "5",
                unit = "talks",
                subtitle = "Latest: KM & AI Summit 2025",
                icon = Icons.Default.Mic,
                accentColor = StudioViolet
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
            // ================= 1. EYEBROW & MAIN TITLE =================
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(
                    text = "01: ABOUT",
                    color = StudioCoral,
                    fontWeight = FontWeight.Bold,
                    fontSize = 11.sp,
                    fontFamily = FontFamily.Monospace,
                    letterSpacing = 1.sp
                )

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        text = "Engineer first. ",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Text(
                        text = "Storyteller",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.ExtraBold,
                        fontStyle = FontStyle.Italic,
                        color = StudioCoral
                    )
                    Text(
                        text = " always.",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                }
            }

            // ================= 2. BIO PARAGRAPH =================
            Text(
                text = "I spent years writing production code at Google before trading the campus for a carry-on. Now I split my time between shipping AI-powered products for clients and explaining technology to a few hundred thousand people who were told it was not for them.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                lineHeight = 21.sp
            )

            // ================= 3. THESIS BLOCKQUOTE =================
            Surface(
                shape = RoundedCornerShape(10.dp),
                color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.45f),
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)),
                modifier = Modifier.fillMaxWidth()
            ) {
                Row(
                    modifier = Modifier.padding(14.dp),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    // Left vertical accent bar
                    Box(
                        modifier = Modifier
                            .width(3.dp)
                            .height(64.dp)
                            .clip(RoundedCornerShape(2.dp))
                            .background(StudioCoral)
                    )

                    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text(
                            text = "Tech feels scary because it is explained badly.",
                            fontWeight = FontWeight.Bold,
                            fontSize = 13.sp,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                        Text(
                            text = "Whether it is a 30-second reel, a conference keynote, or an MVP built in three weeks, my job is the same: take something intimidating and make it feel obvious.",
                            fontSize = 11.sp,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            lineHeight = 16.sp
                        )
                    }
                }
            }

            // ================= 4. 3 KEY FACTS ROW =================
            Column(
                modifier = Modifier.fillMaxWidth(),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                facts.forEach { fact ->
                    Surface(
                        shape = RoundedCornerShape(10.dp),
                        color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.35f),
                        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.25f)),
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .clickable { onFactClick(fact.unit) }
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 10.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(10.dp)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(32.dp)
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(fact.accentColor.copy(alpha = 0.15f)),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(
                                        imageVector = fact.icon,
                                        contentDescription = null,
                                        modifier = Modifier.size(16.dp),
                                        tint = fact.accentColor
                                    )
                                }
                                Column {
                                    Row(verticalAlignment = Alignment.Bottom) {
                                        Text(
                                            text = fact.number,
                                            fontWeight = FontWeight.ExtraBold,
                                            fontSize = 16.sp,
                                            fontFamily = FontFamily.Monospace,
                                            color = MaterialTheme.colorScheme.onSurface
                                        )
                                        Spacer(Modifier.width(4.dp))
                                        Text(
                                            text = fact.unit,
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.Medium,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant
                                        )
                                    }
                                    Text(
                                        text = fact.subtitle,
                                        fontSize = 10.sp,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                }
                            }
                            Icon(
                                imageVector = Icons.AutoMirrored.Filled.ArrowForward,
                                contentDescription = null,
                                modifier = Modifier.size(14.dp),
                                tint = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                }
            }

            // ================= 5. EXPANDABLE JOURNEY ACCORDION =================
            Surface(
                shape = RoundedCornerShape(12.dp),
                color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.35f),
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.25f)),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(modifier = Modifier.padding(14.dp)) {
                    // Accordion Header
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { isJourneyExpanded = !isJourneyExpanded },
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "The path so far",
                            fontWeight = FontWeight.Bold,
                            fontSize = 13.sp,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            Text(
                                text = if (isJourneyExpanded) "Hide story" else "Show the story",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = StudioCoral
                            )
                            Icon(
                                if (isJourneyExpanded) Icons.Default.KeyboardArrowUp else Icons.Default.KeyboardArrowDown,
                                contentDescription = null,
                                modifier = Modifier.size(16.dp),
                                tint = StudioCoral
                            )
                        }
                    }

                    // Expandable Milestones Timeline
                    AnimatedVisibility(
                        visible = isJourneyExpanded,
                        enter = expandVertically(tween(180)) + fadeIn(tween(150)),
                        exit = shrinkVertically(tween(140)) + fadeOut(tween(100))
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(top = 12.dp),
                            verticalArrangement = Arrangement.spacedBy(10.dp)
                        ) {
                            HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.2f))

                            milestones.forEachIndexed { index, step ->
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(10.dp),
                                    verticalAlignment = Alignment.Top
                                ) {
                                    // Step Indicator Dot
                                    Box(
                                        modifier = Modifier
                                            .size(24.dp)
                                            .clip(CircleShape)
                                            .background(
                                                if (step.isCurrent) StudioCoral else MaterialTheme.colorScheme.surfaceVariant
                                            )
                                            .border(
                                                1.dp,
                                                if (step.isCurrent) StudioCoral else MaterialTheme.colorScheme.outline.copy(alpha = 0.4f),
                                                CircleShape
                                            ),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Text(
                                            text = step.stepLabel,
                                            fontSize = 10.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = if (step.isCurrent) Color.White else MaterialTheme.colorScheme.onSurface
                                        )
                                    }

                                    Column(modifier = Modifier.weight(1f)) {
                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                                        ) {
                                            Text(
                                                text = step.title,
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 12.sp,
                                                color = MaterialTheme.colorScheme.onSurface
                                            )
                                            if (step.isCurrent) {
                                                Surface(
                                                    shape = RoundedCornerShape(4.dp),
                                                    color = StudioCoral.copy(alpha = 0.15f)
                                                ) {
                                                    Text(
                                                        text = "NOW",
                                                        fontSize = 8.sp,
                                                        fontWeight = FontWeight.ExtraBold,
                                                        color = StudioCoral,
                                                        modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                                                    )
                                                }
                                            }
                                        }
                                        Spacer(Modifier.height(2.dp))
                                        Text(
                                            text = step.detail,
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

            // ================= 6. FOUNDER SHOWCASE PHOTO CARD =================
            Surface(
                shape = RoundedCornerShape(14.dp),
                color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f),
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.35f)),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column {
                    // Founder Portrait Card Header / Graphic
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(130.dp)
                            .background(Color(0xFF1E212B)),
                        contentAlignment = Alignment.Center
                    ) {
                        // Subtle Tech Blueprint Grid
                        Canvas(modifier = Modifier.fillMaxSize()) {
                            val lineColor = Color.White.copy(alpha = 0.04f)
                            for (x in 0..(size.width.toInt()) step 30) {
                                drawLine(lineColor, Offset(x.toFloat(), 0f), Offset(x.toFloat(), size.height))
                            }
                            for (y in 0..(size.height.toInt()) step 30) {
                                drawLine(lineColor, Offset(0f, y.toFloat()), Offset(size.width, y.toFloat()))
                            }
                        }

                        // Founder Center Initials Mark
                        Surface(
                            shape = CircleShape,
                            color = StudioCoral,
                            border = BorderStroke(2.dp, Color.White),
                            modifier = Modifier.size(54.dp)
                        ) {
                            Box(contentAlignment = Alignment.Center) {
                                Text(
                                    text = "DL",
                                    fontSize = 18.sp,
                                    fontWeight = FontWeight.Bold,
                                    fontFamily = FontFamily.Monospace,
                                    color = Color.White
                                )
                            }
                        }

                        // Top Left: Ex-Google Engineer & Founder Tag
                        Surface(
                            shape = RoundedCornerShape(4.dp),
                            color = Color.Black.copy(alpha = 0.75f),
                            border = BorderStroke(1.dp, Color.White.copy(alpha = 0.2f)),
                            modifier = Modifier
                                .align(Alignment.TopStart)
                                .padding(8.dp)
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 6.dp, vertical = 3.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                Icon(
                                    Icons.Default.Code,
                                    contentDescription = null,
                                    modifier = Modifier.size(11.dp),
                                    tint = StudioOcean
                                )
                                Text(
                                    text = "Ex-Google Engineer & Founder",
                                    fontSize = 9.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = Color.White
                                )
                            }
                        }
                    }

                    // Figcaption: "Now: Building AI-powered apps..." & "1 build slot open"
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(12.dp),
                        verticalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            // "Now" Pill with Pulse Dot
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(5.dp)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(7.dp)
                                        .clip(CircleShape)
                                        .background(StudioGoodLight)
                                )
                                Text(
                                    text = "Now",
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 10.sp,
                                    color = StudioGoodLight
                                )
                            }

                            // 1 build slot open badge
                            Surface(
                                shape = RoundedCornerShape(4.dp),
                                color = StudioEmerald.copy(alpha = 0.15f),
                                border = BorderStroke(1.dp, StudioEmerald.copy(alpha = 0.35f))
                            ) {
                                Text(
                                    text = "1 build slot open",
                                    fontSize = 9.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = StudioEmerald,
                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                )
                            }
                        }

                        Text(
                            text = "Building AI-powered apps and explaining them on camera",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                    }
                }
            }

            // ================= 7. SECTION ACTION BUTTONS =================
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(10.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Button(
                    onClick = onBookIntroCall,
                    colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                    shape = RoundedCornerShape(10.dp),
                    modifier = Modifier.weight(1f),
                    contentPadding = PaddingValues(horizontal = 14.dp, vertical = 11.dp)
                ) {
                    Icon(
                        Icons.Default.CalendarMonth,
                        contentDescription = null,
                        modifier = Modifier.size(15.dp),
                        tint = Color.White
                    )
                    Spacer(Modifier.width(6.dp))
                    Text(
                        text = "Book an intro call",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White
                    )
                }

                OutlinedButton(
                    onClick = onReadStory,
                    shape = RoundedCornerShape(10.dp),
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.4f)),
                    modifier = Modifier.weight(1f),
                    contentPadding = PaddingValues(horizontal = 12.dp, vertical = 11.dp)
                ) {
                    Text(
                        text = "Read my story",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Spacer(Modifier.width(6.dp))
                    Icon(
                        Icons.AutoMirrored.Filled.OpenInNew,
                        contentDescription = null,
                        modifier = Modifier.size(13.dp),
                        tint = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
        }
    }
}
