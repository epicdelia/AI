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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.ui.theme.*

/**
 * Data Model for a Tech Tool in an Engineering Pillar.
 */
data class StackTechItem(
    val name: String,
    val role: String
)

/**
 * Data Model for one of the 4 Engineering Pillars.
 */
data class EngineeringPillar(
    val id: String,
    val title: String,
    val subtitle: String,
    val description: String,
    val guarantee: String,
    val tools: List<StackTechItem>,
    val icon: ImageVector,
    val accentColor: Color
)

/**
 * Mobile Implementation of the Official Engineering Ecosystem Section:
 * (<section class="landing-section" id="stack">)
 *
 * Directives:
 * - Eyebrow: "04: Engineering Ecosystem"
 * - Headline: "Modern tech stack, zero bloat" with "zero bloat" in italics + StudioCoral
 * - Subtitle: "Production systems engineering honed at Google and proven across 60+ shipped client projects. We build on open, maintainable standards that you control."
 * - 4 Engineering Pillars:
 *    1. AI & Autonomous Agents (Gemini 1.5/2.0, Claude 3.5, Vector DBs, Guardrails)
 *    2. Full-Stack & High-Scale APIs (TypeScript/Node, Laravel 11/PHP 8.3, Python, PostgreSQL/Redis)
 *    3. Frontend & Reactive Interfaces (Reactive Vanilla JS, Vue/React, Tailwind Tokens, Accessible ARIA)
 *    4. Cloud, DevOps & Reliability (GCP Cloud Run, Docker, GitHub Actions, Stripe API)
 * - 4 Engineering Standards cards (100% IP Ownership, Day-1 Velocity, Scale to 100K+, 30-Day Bug Warranty)
 * - Stack Review CTA: "Review Your Tech Stack With Delia →"
 * - Strict flat design: 0dp elevation, crisp 1dp borders, solid accent fills, zero futuristic or sci-fi glows
 */
@Composable
fun LandingStackSection(
    onReviewStack: () -> Unit,
    modifier: Modifier = Modifier
) {
    var selectedPillarIndex by remember { mutableStateOf(0) }

    val pillars = remember {
        listOf(
            EngineeringPillar(
                id = "ai",
                title = "AI & Autonomous Agents",
                subtitle = "Multimodal & RAG",
                description = "Autonomous tool-calling, custom subagent routing, streaming inference, and deterministic benchmarks.",
                guarantee = "Sub-second streaming & evaluated safety bounds.",
                tools = listOf(
                    StackTechItem("Gemini 1.5 & 2.0 API", "Multimodal reasoning"),
                    StackTechItem("Claude 3.5 Sonnet", "Tool execution & coding"),
                    StackTechItem("Vector DBs (pgvector)", "Low-latency RAG"),
                    StackTechItem("Guardrail Harnesses", "Deterministic outputs")
                ),
                icon = Icons.Default.AutoAwesome,
                accentColor = StudioViolet
            ),
            EngineeringPillar(
                id = "fullstack",
                title = "Full-Stack & High-Scale APIs",
                subtitle = "ACID & Microservices",
                description = "Modular, maintainable application backends built for high concurrency, clean migrations, and security.",
                guarantee = "Strict schema validations & 100% test-backed contracts.",
                tools = listOf(
                    StackTechItem("TypeScript & Node.js", "Real-time WebSocket layers"),
                    StackTechItem("Laravel 11 / PHP 8.3", "Queues, auth & robust ORM"),
                    StackTechItem("Python Microservices", "Data transformations & ML"),
                    StackTechItem("PostgreSQL & Redis", "Sub-ms caching & ACID data")
                ),
                icon = Icons.Default.Code,
                accentColor = StudioOcean
            ),
            EngineeringPillar(
                id = "frontend",
                title = "Frontend & Reactive Interfaces",
                subtitle = "Mobile-First & 60fps",
                description = "Snappy, accessible, mobile-first interfaces with zero npm bloat. Instant 60fps micro-interactions.",
                guarantee = "Instant page transitions & 60fps responsive fidelity.",
                tools = listOf(
                    StackTechItem("Reactive Vanilla JS", "Zero-build-step speed"),
                    StackTechItem("Vue 3 & React", "Component-driven web apps"),
                    StackTechItem("Tailwind CSS Tokens", "Fluid responsive dark/light"),
                    StackTechItem("Accessible ARIA", "Keyboard & screen-reader UX")
                ),
                icon = Icons.Default.Bolt,
                accentColor = StudioCoral
            ),
            EngineeringPillar(
                id = "cloud",
                title = "Cloud, DevOps & Reliability",
                subtitle = "Serverless & CI/CD",
                description = "Battle-tested cloud infrastructure in your own company accounts with automated CI/CD and secrets isolation.",
                guarantee = "100% infrastructure-as-code handover in your name.",
                tools = listOf(
                    StackTechItem("Google Cloud (GCP)", "Serverless Cloud Run & Tasks"),
                    StackTechItem("Docker Containers", "Local-to-production parity"),
                    StackTechItem("GitHub Actions CI/CD", "Automated test pipelines"),
                    StackTechItem("Stripe Checkout API", "PCI billing & subscriptions")
                ),
                icon = Icons.Default.Public,
                accentColor = StudioEmerald
            )
        )
    }

    val activePillar = pillars[selectedPillarIndex]

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
                    text = "04: ENGINEERING ECOSYSTEM",
                    color = StudioCoral,
                    fontWeight = FontWeight.Bold,
                    fontSize = 11.sp,
                    fontFamily = FontFamily.Monospace,
                    letterSpacing = 1.sp
                )

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        text = "Modern tech stack, ",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Text(
                        text = "zero bloat",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.ExtraBold,
                        fontStyle = FontStyle.Italic,
                        color = StudioCoral
                    )
                }

                Spacer(Modifier.height(2.dp))

                Text(
                    text = "Production systems engineering honed at Google and proven across 60+ shipped client projects. We build on open, maintainable standards that you control.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    lineHeight = 20.sp
                )
            }

            // ================= 2. PILLAR TABS SELECTOR =================
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                pillars.forEachIndexed { index, pillar ->
                    val isSelected = selectedPillarIndex == index
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = if (isSelected) pillar.accentColor else MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f),
                        border = BorderStroke(
                            1.dp,
                            if (isSelected) pillar.accentColor else MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)
                        ),
                        modifier = Modifier
                            .clip(RoundedCornerShape(8.dp))
                            .clickable { selectedPillarIndex = index }
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Icon(
                                imageVector = pillar.icon,
                                contentDescription = null,
                                modifier = Modifier.size(13.dp),
                                tint = if (isSelected) Color.White else pillar.accentColor
                            )
                            Text(
                                text = pillar.title,
                                fontSize = 11.sp,
                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                color = if (isSelected) Color.White else MaterialTheme.colorScheme.onSurface
                            )
                        }
                    }
                }
            }

            // ================= 3. ACTIVE PILLAR CARD =================
            Surface(
                shape = RoundedCornerShape(14.dp),
                color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.45f),
                border = BorderStroke(1.dp, activePillar.accentColor.copy(alpha = 0.4f)),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    // Pillar Header
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(32.dp)
                                    .clip(RoundedCornerShape(8.dp))
                                    .background(activePillar.accentColor.copy(alpha = 0.15f)),
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(
                                    imageVector = activePillar.icon,
                                    contentDescription = null,
                                    modifier = Modifier.size(16.dp),
                                    tint = activePillar.accentColor
                                )
                            }
                            Text(
                                text = activePillar.title,
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold,
                                color = MaterialTheme.colorScheme.onSurface
                            )
                        }

                        Surface(
                            shape = RoundedCornerShape(4.dp),
                            color = activePillar.accentColor.copy(alpha = 0.12f),
                            border = BorderStroke(1.dp, activePillar.accentColor.copy(alpha = 0.3f))
                        ) {
                            Text(
                                text = activePillar.subtitle,
                                fontSize = 9.sp,
                                fontWeight = FontWeight.Bold,
                                fontFamily = FontFamily.Monospace,
                                color = activePillar.accentColor,
                                modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                            )
                        }
                    }

                    // Description
                    Text(
                        text = activePillar.description,
                        fontSize = 12.sp,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        lineHeight = 18.sp
                    )

                    HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.2f))

                    // Tech Tools Rows
                    Column(
                        modifier = Modifier.fillMaxWidth(),
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        activePillar.tools.forEach { tool ->
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clip(RoundedCornerShape(6.dp))
                                    .background(MaterialTheme.colorScheme.surface.copy(alpha = 0.6f))
                                    .border(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.15f), RoundedCornerShape(6.dp))
                                    .padding(horizontal = 10.dp, vertical = 7.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(6.dp)
                                            .clip(CircleShape)
                                            .background(activePillar.accentColor)
                                    )
                                    Text(
                                        text = tool.name,
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 11.sp,
                                        color = MaterialTheme.colorScheme.onSurface
                                    )
                                }
                                Text(
                                    text = tool.role,
                                    fontSize = 10.sp,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        }
                    }

                    // Pillar Guarantee
                    Surface(
                        shape = RoundedCornerShape(6.dp),
                        color = activePillar.accentColor.copy(alpha = 0.10f),
                        border = BorderStroke(1.dp, activePillar.accentColor.copy(alpha = 0.25f)),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 7.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Icon(
                                Icons.Default.Check,
                                contentDescription = null,
                                modifier = Modifier.size(13.dp),
                                tint = activePillar.accentColor
                            )
                            Text(
                                text = activePillar.guarantee,
                                fontSize = 10.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = activePillar.accentColor
                            )
                        }
                    }
                }
            }

            // ================= 4. FOUR ENGINEERING STANDARDS =================
            Column(
                modifier = Modifier.fillMaxWidth(),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    FlatStackStandardCard(
                        title = "100% IP Ownership",
                        description = "Private GitHub repos and cloud accounts created directly in your name.",
                        icon = Icons.Default.Lock,
                        accentColor = StudioCoral,
                        modifier = Modifier.weight(1f)
                    )
                    FlatStackStandardCard(
                        title = "Day-1 Launch Velocity",
                        description = "Pre-tested architecture scaffolds cut weeks off setup without compromising quality.",
                        icon = Icons.Default.RocketLaunch,
                        accentColor = StudioViolet,
                        modifier = Modifier.weight(1f)
                    )
                }

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    FlatStackStandardCard(
                        title = "Built to Scale to 100K+",
                        description = "Stateless backend architecture ready for high concurrency spikes.",
                        icon = Icons.Default.Balance,
                        accentColor = StudioOcean,
                        modifier = Modifier.weight(1f)
                    )
                    FlatStackStandardCard(
                        title = "30-Day Bug Warranty",
                        description = "Every build is backed by a 30-day post-launch zero-cost bug fix guarantee.",
                        icon = Icons.Default.Security,
                        accentColor = StudioEmerald,
                        modifier = Modifier.weight(1f)
                    )
                }
            }

            // ================= 5. STACK REVIEW CTA CARD =================
            Surface(
                shape = RoundedCornerShape(12.dp),
                color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f),
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(
                    modifier = Modifier.padding(14.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Text(
                        text = "Have a specific stack or proprietary API integration in mind?",
                        fontSize = 11.sp,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )

                    Button(
                        onClick = onReviewStack,
                        colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                        shape = RoundedCornerShape(8.dp),
                        modifier = Modifier.fillMaxWidth(),
                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 10.dp)
                    ) {
                        Icon(
                            Icons.Default.Code,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = Color.White
                        )
                        Spacer(Modifier.width(6.dp))
                        Text(
                            text = "Review Your Tech Stack With Delia",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White
                        )
                        Spacer(Modifier.width(4.dp))
                        Icon(
                            Icons.AutoMirrored.Filled.ArrowForward,
                            contentDescription = null,
                            modifier = Modifier.size(12.dp),
                            tint = Color.White
                        )
                    }
                }
            }
        }
    }
}

/**
 * Standard Flat Micro-Card for the 4 Engineering Standards.
 */
@Composable
private fun FlatStackStandardCard(
    title: String,
    description: String,
    icon: ImageVector,
    accentColor: Color,
    modifier: Modifier = Modifier
) {
    Surface(
        shape = RoundedCornerShape(10.dp),
        color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.35f),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.25f)),
        modifier = modifier
    ) {
        Column(
            modifier = Modifier.padding(10.dp),
            verticalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(24.dp)
                        .clip(RoundedCornerShape(5.dp))
                        .background(accentColor.copy(alpha = 0.15f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = icon,
                        contentDescription = null,
                        modifier = Modifier.size(13.dp),
                        tint = accentColor
                    )
                }
                Text(
                    text = title,
                    fontWeight = FontWeight.Bold,
                    fontSize = 11.sp,
                    color = MaterialTheme.colorScheme.onSurface,
                    maxLines = 1
                )
            }
            Text(
                text = description,
                fontSize = 9.sp,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                lineHeight = 13.sp
            )
        }
    }
}
