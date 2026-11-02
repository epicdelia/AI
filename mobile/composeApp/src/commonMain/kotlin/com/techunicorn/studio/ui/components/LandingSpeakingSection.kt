package com.techunicorn.studio.ui.components

import androidx.compose.animation.*
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
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.ui.theme.*

/**
 * Representation of one stage appearance or talk matching Talk.php & landing_page_source.html.
 */
data class StageTalk(
    val num: String,
    val event: String,
    val description: String,
    val kind: String,
    val cityOrVenue: String,
    val dateOrYear: String = "2025/2026"
)

/**
 * Mobile Implementation of the Official "06: Speaking & Stage" Section + Final Closing CTA
 * (<section class="speaking" id="speaking"> and <section class="cta">)
 *
 * Directives:
 * - Eyebrow: "06: Speaking"
 * - Headline: "Recently on stage" (with "on stage" in italics + StudioCoral)
 * - Stage talks matching official HTML:
 *    01: Nomad Cruise (Keynote)
 *    02: Vently Panel (Panel)
 *    03: Digital Garage Tokyo (Workshop)
 * - Curated speaking topics from Talk.php:
 *    "AI agents in production", "Enterprise AI & knowledge management", "Building in public", "Remote & nomad engineering", "Big Tech to indie"
 * - Closing CTA:
 *    "Have an idea that needs shipping?"
 *    "App, campaign, keynote, or something weirder — if it makes tech less scary, I'm interested."
 * - Strict flat design: 0dp elevation, crisp 1dp outlines, solid colors, zero gradients/glows.
 */
@Composable
fun LandingSpeakingSection(
    onBookSpeaking: (topic: String) -> Unit,
    onBookCall: () -> Unit,
    modifier: Modifier = Modifier
) {
    var selectedTopic by remember { mutableStateOf<String?>("AI agents in production") }

    val topics = remember {
        listOf(
            "AI agents in production",
            "Enterprise AI & knowledge",
            "Building in public",
            "Remote & nomad engineering",
            "Big Tech to indie"
        )
    }

    val talks = remember {
        listOf(
            StageTalk(
                num = "01",
                event = "Nomad Cruise",
                description = "Keynote on building a tech career without an address — engineering, content, and income on the move.",
                kind = "Keynote",
                cityOrVenue = "Atlantic Crossing · 450+ Attendees"
            ),
            StageTalk(
                num = "02",
                event = "Vently Panel",
                description = "Panel on creator-led businesses and what happens when engineers learn distribution.",
                kind = "Panel",
                cityOrVenue = "London · Tech & Media Track"
            ),
            StageTalk(
                num = "03",
                event = "Digital Garage Tokyo",
                description = "Hands-on workshop on practical AI for non-technical teams — making the intimidating feel obvious.",
                kind = "Workshop",
                cityOrVenue = "Tokyo · Shibuya Sky Center"
            ),
            StageTalk(
                num = "04",
                event = "AI Engineer Summit",
                description = "Deterministic prompt workflows and tool-calling subagent pipelines in real-world client architectures.",
                kind = "Fireside Chat",
                cityOrVenue = "San Francisco · Builder Stage"
            )
        )
    }

    Column(
        modifier = modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(20.dp)
    ) {
        // SPEAKING SECTION CONTAINER
        Surface(
            modifier = Modifier.fillMaxWidth(),
            color = MaterialTheme.colorScheme.surface,
            shape = RoundedCornerShape(20.dp),
            border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(20.dp)
            ) {
                // EYEBROW
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .size(6.dp)
                            .clip(CircleShape)
                            .background(StudioViolet)
                    )
                    Text(
                        text = "06: SPEAKING",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        fontFamily = FontFamily.Monospace,
                        color = StudioViolet,
                        letterSpacing = 1.2.sp
                    )
                }

                Spacer(Modifier.height(8.dp))

                // HEADLINE: "Recently on stage"
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text(
                        text = "Recently",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Text(
                        text = "on stage",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                        fontStyle = FontStyle.Italic,
                        color = StudioCoral
                    )
                }

                Spacer(Modifier.height(6.dp))

                Text(
                    text = "Keynotes, technical panels, and interactive AI workshops for engineering teams, conferences, and startup communities worldwide.",
                    fontSize = 13.sp,
                    lineHeight = 19.sp,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )

                Spacer(Modifier.height(16.dp))

                // SPEAKING TOPIC CHIPS
                Text(
                    text = "CURATED TALK TOPICS",
                    fontSize = 10.sp,
                    fontWeight = FontWeight.Bold,
                    fontFamily = FontFamily.Monospace,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    letterSpacing = 0.8.sp
                )

                Spacer(Modifier.height(8.dp))

                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    topics.forEach { topic ->
                        val isSelected = selectedTopic == topic
                        Surface(
                            shape = RoundedCornerShape(8.dp),
                            color = if (isSelected) StudioViolet.copy(alpha = 0.15f) else MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.4f),
                            border = BorderStroke(
                                1.dp,
                                if (isSelected) StudioViolet else MaterialTheme.colorScheme.outlineVariant
                            ),
                            modifier = Modifier.clickable {
                                selectedTopic = if (isSelected) null else topic
                            }
                        ) {
                            Text(
                                text = topic,
                                fontSize = 11.sp,
                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                color = if (isSelected) StudioViolet else MaterialTheme.colorScheme.onSurface,
                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                            )
                        }
                    }
                }

                Spacer(Modifier.height(18.dp))

                // TALKS LIST
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    talks.forEach { talk ->
                        Surface(
                            shape = RoundedCornerShape(12.dp),
                            color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.35f),
                            border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Column(Modifier.padding(14.dp)) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                    ) {
                                        Text(
                                            text = talk.num,
                                            fontSize = 12.sp,
                                            fontWeight = FontWeight.Bold,
                                            fontFamily = FontFamily.Monospace,
                                            color = StudioCoral
                                        )
                                        Text(
                                            text = talk.event,
                                            fontSize = 14.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = MaterialTheme.colorScheme.onSurface
                                        )
                                    }

                                    Box(
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(6.dp))
                                            .background(StudioViolet.copy(alpha = 0.15f))
                                            .padding(horizontal = 8.dp, vertical = 3.dp)
                                    ) {
                                        Text(
                                            text = talk.kind,
                                            fontSize = 10.sp,
                                            fontWeight = FontWeight.Bold,
                                            fontFamily = FontFamily.Monospace,
                                            color = StudioViolet
                                        )
                                    }
                                }

                                Spacer(Modifier.height(6.dp))

                                Text(
                                    text = talk.description,
                                    fontSize = 12.sp,
                                    lineHeight = 18.sp,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )

                                Spacer(Modifier.height(8.dp))

                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text(
                                        text = "📍 ${talk.cityOrVenue}",
                                        fontSize = 10.sp,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                    Text(
                                        text = talk.dateOrYear,
                                        fontSize = 10.sp,
                                        fontFamily = FontFamily.Monospace,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                }
                            }
                        }
                    }
                }

                Spacer(Modifier.height(18.dp))

                // SPEAKING INQUIRY CTA BUTTON
                Button(
                    onClick = { onBookSpeaking(selectedTopic ?: "Keynotes & Workshops") },
                    colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant),
                    shape = RoundedCornerShape(8.dp),
                    modifier = Modifier.fillMaxWidth(),
                    contentPadding = PaddingValues(vertical = 12.dp)
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Text(
                            text = "Book Delia to Speak for 2026",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                        Icon(
                            Icons.AutoMirrored.Filled.ArrowForward,
                            contentDescription = null,
                            modifier = Modifier.size(13.dp),
                            tint = MaterialTheme.colorScheme.onSurface
                        )
                    }
                }
            }
        }

        // ==========================================
        // CLOSING HERO CALL-TO-ACTION SECTION (<section class="cta">)
        // ==========================================
        Surface(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(20.dp),
            color = Color(0xFF141419),
            border = BorderStroke(1.dp, StudioCoral.copy(alpha = 0.4f))
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(24.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                // Eyebrow
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    StudioLogo(modifier = Modifier.size(14.dp), tint = StudioCoral)
                    Text(
                        text = "LET'S BUILD SOMETHING",
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        fontFamily = FontFamily.Monospace,
                        color = Color.White.copy(alpha = 0.7f),
                        letterSpacing = 1.2.sp
                    )
                }

                Spacer(Modifier.height(12.dp))

                // Title: Have an idea that needs shipping?
                Text(
                    text = "Have an idea that needs shipping?",
                    style = MaterialTheme.typography.headlineMedium,
                    fontWeight = FontWeight.Bold,
                    color = Color.White,
                    textAlign = TextAlign.Center,
                    lineHeight = 32.sp
                )

                Spacer(Modifier.height(10.dp))

                // Subtitle
                Text(
                    text = "App, campaign, keynote, or something weirder — if it makes tech less scary, I'm interested.",
                    fontSize = 13.sp,
                    lineHeight = 20.sp,
                    color = Color.White.copy(alpha = 0.75f),
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth(0.95f)
                )

                Spacer(Modifier.height(20.dp))

                // Primary Action Button
                Button(
                    onClick = onBookCall,
                    colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                    shape = RoundedCornerShape(8.dp),
                    modifier = Modifier.fillMaxWidth(),
                    contentPadding = PaddingValues(vertical = 13.dp)
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Text(
                            text = "Book a 30-Minute Intro Call",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White
                        )
                        Icon(
                            Icons.AutoMirrored.Filled.ArrowForward,
                            contentDescription = null,
                            modifier = Modifier.size(15.dp),
                            tint = Color.White
                        )
                    }
                }

                Spacer(Modifier.height(8.dp))

                // Direct email option
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    Text(
                        text = "Or email directly:",
                        fontSize = 11.sp,
                        color = Color.White.copy(alpha = 0.5f)
                    )
                    Text(
                        text = "hello@thetechunicorn.com",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        fontFamily = FontFamily.Monospace,
                        color = StudioCoral
                    )
                }

                Spacer(Modifier.height(16.dp))

                // Trust reassurance note
                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = Color.White.copy(alpha = 0.05f),
                    border = BorderStroke(1.dp, Color.White.copy(alpha = 0.08f)),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text(
                        text = "Free 30-minute call · fixed-scope proposal within 48 hours · zero surprise invoices",
                        fontSize = 10.sp,
                        color = Color.White.copy(alpha = 0.65f),
                        textAlign = TextAlign.Center,
                        modifier = Modifier.padding(vertical = 8.dp, horizontal = 10.dp)
                    )
                }
            }
        }
    }
}

