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
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.models.LandingService
import com.techunicorn.studio.ui.theme.*

/**
 * Mobile Implementation of the Official Landing Services Section:
 * (<section class="landing-section tinted" id="services">)
 *
 * Directives:
 * - Eyebrow: "02: Services"
 * - Headline: "Six ways we can work together" with "work together" in italics + StudioCoral
 * - Subtitle: "Every engagement comes with direct access to Delia, transparent deliverables, and zero corporate fluff."
 * - 6 Complete Service Cards:
 *    1. Vibecoded Apps (MVP Engineering · 2 to 4 wks)
 *    2. AI Integration (Production AI · Practical LLMs)
 *    3. Technical Consulting (Ex-Google Advisory · Weekly Syncs)
 *    4. Content Creation (Audience & Growth · 519K+ Audience)
 *    5. Speaking & Workshops (Keynotes & Stages · Global Stages)
 *    6. Brand Partnerships (Creator Partnerships · Verified Fit)
 * - Per-card expandable deliverables with "Show more (+2)" / "Show less"
 * - Per-card focus tags (React/Vite, Claude Code, Gemini API, Docker, etc.)
 * - Per-card direct "Book a call →" action button
 * - Section toggle: "Show all 6 services" / "Show fewer services"
 * - Strict flat design: 0dp elevation, crisp 1dp borders, solid accent fills, zero futuristic or sci-fi glows
 */
@Composable
fun LandingServicesSection(
    services: List<LandingService>,
    onBookService: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    var showAllServices by remember { mutableStateOf(true) }

    // Fallback full list of 6 services matching the HTML if the list passed is empty
    val effectiveServices = remember(services) {
        if (services.isNotEmpty()) services else listOf(
            LandingService(
                category = "MVP Engineering",
                badge = "2 to 4 wks",
                iconName = "zap",
                title = "Vibecoded Apps",
                desc = "AI-powered MVPs designed, built, and shipped in 2 to 4 weeks. From napkin sketch to live cloud deployment.",
                deliverables = listOf(
                    "Interactive prototype and design review within 5 days",
                    "Full production codebase transferred to your GitHub repo",
                    "Integrated Stripe billing, auth, and automated database backups"
                ),
                tags = listOf("React / Vite", "Python", "Stripe", "Docker"),
                ctaService = "mvp_build"
            ),
            LandingService(
                category = "Production AI",
                badge = "Practical LLMs",
                iconName = "sparkle",
                title = "AI Integration",
                desc = "Practical AI features for your existing product: shipped, not slide-decked. Autonomous agents that earn their keep.",
                deliverables = listOf(
                    "Custom LLM subagent pipelines and tool-routing workflows",
                    "Deterministic evaluation benchmarks and safety guardrails",
                    "Optimized inference latency with streaming responses"
                ),
                tags = listOf("Claude Code", "Gemini API", "Agents", "Vector DB"),
                ctaService = "consulting"
            ),
            LandingService(
                category = "Ex-Google Advisory",
                badge = "Weekly Syncs",
                iconName = "code",
                title = "Technical Consulting",
                desc = "An ex-Google engineer in your corner for architecture reviews, build-vs-buy decisions, and scaling strategy.",
                deliverables = listOf(
                    "Unbiased build versus buy infrastructure assessments",
                    "Scalability and cloud security audits before launch",
                    "Async code reviews and emergency escalation coverage"
                ),
                tags = listOf("Architecture", "Cloud Security", "Scalability"),
                ctaService = "consulting"
            ),
            LandingService(
                category = "Audience & Growth",
                badge = "519K+ Audience",
                iconName = "play",
                title = "Content Creation",
                desc = "Short-form video, long-form YouTube deep-dives, and LinkedIn content that makes complex tech feel obvious.",
                deliverables = listOf(
                    "Full video scripting, hands-on demos, and production editing",
                    "Multi-platform syndication across YouTube, Instagram & LinkedIn",
                    "Native integrations that build lasting developer goodwill"
                ),
                tags = listOf("YouTube 4K", "Shorts", "LinkedIn Pulse"),
                ctaService = "brand_deal"
            ),
            LandingService(
                category = "Keynotes & Stages",
                badge = "Global Stages",
                iconName = "mic",
                title = "Speaking & Workshops",
                desc = "Keynotes and hands-on workshops on AI agents, building in public, and the future of remote engineering.",
                deliverables = listOf(
                    "In-person and virtual keynote talks tailored to your conference",
                    "Live coding demos showing autonomous agents in real time",
                    "Interactive Q&A and VIP speaker session engagement"
                ),
                tags = listOf("Nomad Cruise", "Tech Summits", "Live Coding"),
                ctaService = "speaking"
            ),
            LandingService(
                category = "Creator Partnerships",
                badge = "Verified Fit",
                iconName = "briefcase",
                title = "Brand Partnerships",
                desc = "Authentic integrations with an engaged audience of 519K+ developers, founders, and tech-curious professionals.",
                deliverables = listOf(
                    "Dedicated feature video or authentic 60-second integration",
                    "Custom tracking links, social promotion, and analytics reporting",
                    "Strict policy: I only endorse developer tools I genuinely use"
                ),
                tags = listOf("Dev Tools", "SaaS", "High Retention"),
                ctaService = "brand_deal"
            )
        )
    }

    val displayedServices = if (showAllServices) effectiveServices else effectiveServices.take(3)

    Column(
        modifier = modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // ================= 1. SECTION HEADER =================
        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(
                text = "02: SERVICES",
                color = StudioCoral,
                fontWeight = FontWeight.Bold,
                fontSize = 11.sp,
                fontFamily = FontFamily.Monospace,
                letterSpacing = 1.sp
            )

            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = "Six ways we can ",
                    style = MaterialTheme.typography.headlineMedium,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface
                )
                Text(
                    text = "work together",
                    style = MaterialTheme.typography.headlineMedium,
                    fontWeight = FontWeight.ExtraBold,
                    fontStyle = FontStyle.Italic,
                    color = StudioCoral
                )
            }

            Spacer(Modifier.height(2.dp))

            Text(
                text = "Every engagement comes with direct access to Delia, transparent deliverables, and zero corporate fluff.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                lineHeight = 20.sp
            )
        }

        // ================= 2. SERVICE CARDS LIST =================
        Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
            displayedServices.forEach { service ->
                FlatServiceCard(
                    service = service,
                    onBookCall = { onBookService(service.title) }
                )
            }
        }

        // ================= 3. TOGGLE ALL / FEWER BUTTON =================
        if (effectiveServices.size > 3) {
            Surface(
                shape = RoundedCornerShape(10.dp),
                color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f),
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)),
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(10.dp))
                    .clickable { showAllServices = !showAllServices }
            ) {
                Row(
                    modifier = Modifier.padding(vertical = 12.dp),
                    horizontalArrangement = Arrangement.Center,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = if (showAllServices) "Show fewer services" else "Show all ${effectiveServices.size} services",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Spacer(Modifier.width(6.dp))
                    Icon(
                        if (showAllServices) Icons.Default.KeyboardArrowUp else Icons.Default.KeyboardArrowDown,
                        contentDescription = null,
                        modifier = Modifier.size(16.dp),
                        tint = StudioCoral
                    )
                }
            }
        }
    }
}

/**
 * Individual Flat Service Card matching `<article class="landing-service-card">`.
 */
@Composable
private fun FlatServiceCard(
    service: LandingService,
    onBookCall: () -> Unit,
    modifier: Modifier = Modifier
) {
    var isExpanded by remember { mutableStateOf(false) }

    val iconVector = when (service.iconName.lowercase()) {
        "zap", "mvp" -> Icons.Default.Bolt
        "sparkle", "ai" -> Icons.Default.AutoAwesome
        "code", "terminal" -> Icons.Default.Code
        "play", "video" -> Icons.Default.PlayArrow
        "mic", "speaker" -> Icons.Default.Mic
        "briefcase", "work" -> Icons.Default.Work
        else -> Icons.Default.Bolt
    }

    val categoryColor = when (service.category.lowercase()) {
        "mvp engineering" -> StudioCoral
        "production ai" -> StudioViolet
        "ex-google advisory" -> StudioOcean
        "audience & growth" -> StudioCoral
        "keynotes & stages" -> StudioViolet
        "creator partnerships" -> StudioEmerald
        else -> StudioCoral
    }

    Surface(
        shape = RoundedCornerShape(14.dp),
        color = MaterialTheme.colorScheme.surface,
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.35f)),
        modifier = modifier.fillMaxWidth()
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Card Head: Category Badge + Duration/Audience Badge
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Category Pill with Icon
                Surface(
                    shape = RoundedCornerShape(6.dp),
                    color = categoryColor.copy(alpha = 0.12f),
                    border = BorderStroke(1.dp, categoryColor.copy(alpha = 0.35f))
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Icon(
                            imageVector = iconVector,
                            contentDescription = null,
                            modifier = Modifier.size(12.dp),
                            tint = categoryColor
                        )
                        Text(
                            text = service.category,
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold,
                            color = categoryColor
                        )
                    }
                }

                // Badge Pill
                Surface(
                    shape = RoundedCornerShape(6.dp),
                    color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.6f),
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.25f))
                ) {
                    Text(
                        text = service.badge,
                        fontSize = 10.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(horizontal = 7.dp, vertical = 3.dp)
                    )
                }
            }

            // Service Title
            Text(
                text = service.title,
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface
            )

            // Description
            Text(
                text = service.desc,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                lineHeight = 20.sp
            )

            // Deliverables List
            Column(
                modifier = Modifier.fillMaxWidth(),
                verticalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                val deliverablesToShow = if (isExpanded) service.deliverables else service.deliverables.take(1)

                deliverablesToShow.forEach { deliverable ->
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalAlignment = Alignment.Top
                    ) {
                        Box(
                            modifier = Modifier
                                .padding(top = 2.dp)
                                .size(16.dp)
                                .clip(RoundedCornerShape(4.dp))
                                .background(StudioEmerald.copy(alpha = 0.15f)),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                Icons.Default.Check,
                                contentDescription = null,
                                modifier = Modifier.size(11.dp),
                                tint = StudioEmerald
                            )
                        }
                        Text(
                            text = deliverable,
                            fontSize = 11.sp,
                            color = MaterialTheme.colorScheme.onSurface,
                            lineHeight = 16.sp
                        )
                    }
                }
            }

            // Focus Tags
            if (service.tags.isNotEmpty()) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    service.tags.forEach { tag ->
                        Surface(
                            shape = RoundedCornerShape(4.dp),
                            color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.45f),
                            border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.2f))
                        ) {
                            Text(
                                text = tag,
                                fontSize = 10.sp,
                                fontFamily = FontFamily.Monospace,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.padding(horizontal = 6.dp, vertical = 3.dp)
                            )
                        }
                    }
                }
            }

            HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.2f))

            // Footer Actions: Show more (+2) toggle & Book a call button
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Show more (+2) / Show less
                if (service.deliverables.size > 1) {
                    Row(
                        modifier = Modifier
                            .clip(RoundedCornerShape(4.dp))
                            .clickable { isExpanded = !isExpanded }
                            .padding(vertical = 4.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Text(
                            text = if (isExpanded) "Show less" else "Show more (+${service.deliverables.size - 1})",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = StudioCoral
                        )
                        Icon(
                            if (isExpanded) Icons.Default.KeyboardArrowUp else Icons.Default.KeyboardArrowDown,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = StudioCoral
                        )
                    }
                } else {
                    Spacer(Modifier.width(1.dp))
                }

                // Primary "Book a call →" Button
                Button(
                    onClick = onBookCall,
                    colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                    shape = RoundedCornerShape(8.dp),
                    contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp)
                ) {
                    Icon(
                        Icons.Default.CalendarMonth,
                        contentDescription = null,
                        modifier = Modifier.size(13.dp),
                        tint = Color.White
                    )
                    Spacer(Modifier.width(6.dp))
                    Text(
                        text = "Book a call",
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

