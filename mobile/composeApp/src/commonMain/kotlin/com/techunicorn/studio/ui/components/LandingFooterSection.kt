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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.ClipboardManager
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.models.UserRole
import com.techunicorn.studio.ui.theme.*
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

/**
 * Value class tracking user interactions with footer link nodes.
 */
@JvmInline
value class LinkInteractionCount(val value: Int) : Comparable<LinkInteractionCount> {
    operator fun plus(other: LinkInteractionCount): LinkInteractionCount =
        LinkInteractionCount(value + other.value)
    operator fun plus(delta: Int): LinkInteractionCount =
        LinkInteractionCount(value + delta)
    override fun compareTo(other: LinkInteractionCount): Int =
        value.compareTo(other.value)
}

/**
 * Typesafe representations of footer links matching landing_page_source.html.
 */
sealed interface FooterLink {
    val title: String

    data class SocialLink(
        override val title: String,
        val handle: String,
        val reach: String,
        val url: String,
        val icon: ImageVector
    ) : FooterLink

    data class SectionJump(
        override val title: String,
        val sectionId: String,
        val number: String
    ) : FooterLink

    data class LegalDocument(
        override val title: String,
        val docType: LegalDocType
    ) : FooterLink
}

/**
 * European-compliant legal documents matching the official Spanish legal entity.
 */
enum class LegalDocType(
    val title: String,
    val identifier: String,
    val lastUpdated: String,
    val summary: String,
    val sections: List<Pair<String, String>>
) {
    PRIVACY(
        title = "Privacy Policy",
        identifier = "EU GDPR Regulation 2016/679",
        lastUpdated = "January 2026",
        summary = "Delia Lazarescu / Tech Unicorn Studio S.L. processes strictly necessary data for engineering consulting, project estimations, and direct communication. Zero third-party ad tracking.",
        sections = listOf(
            "Data Controller" to "Tech Unicorn Studio S.L., Carrer de Colón 14, 46004 València, Spain. Contact: privacy@thetechunicorn.com",
            "Collected Information" to "Work email, contact name, company name, and project scope details provided explicitly through booking or scope estimation forms.",
            "Legal Basis" to "Processing is carried out under Art. 6(1)(b) GDPR (pre-contractual measures and contract performance) and Art. 6(1)(f) GDPR (legitimate interest).",
            "Data Retention" to "Project inquiries are retained for 12 months. Paid project records are retained for 5 years in compliance with Spanish tax law."
        )
    ),
    TERMS(
        title = "Terms of Service",
        identifier = "Commercial Contracting Terms",
        lastUpdated = "January 2026",
        summary = "Standard engineering engagement terms for fixed-scope MVP builds, retainer advisory, and sponsored media integrations.",
        sections = listOf(
            "Fixed-Price Guarantee" to "All MVP builds proceed on a strict 100% fixed-price proposal. No scope creep, hourly billing overruns, or surprise invoices.",
            "50/50 Milestone Schedule" to "50% upfront project deposit secures dedicated engineering sprint slot; remaining 50% is due only upon staging sign-off and cloud deployment.",
            "100% Code & IP Custody" to "Upon final milestone payment, client owns 100% of all code, git repositories, architectural assets, documentation, and cloud infrastructure.",
            "30-Day Defect Warranty" to "Includes 30 calendar days of post-launch engineering bug fixes at zero additional charge."
        )
    ),
    GDPR_DPA(
        title = "GDPR Data Processing Addendum (DPA)",
        identifier = "Article 28 GDPR Compliance",
        lastUpdated = "January 2026",
        summary = "Ensures full compliance when Delia Lazarescu or subagent automation processes personal data on behalf of enterprise clients.",
        sections = listOf(
            "Security Measures" to "Enforces AES-256 encryption at rest, TLS 1.3 in transit, MFA on all code repositories, and zero unauthorized LLM model training on client data.",
            "Sub-processors" to "GCP (Cloud Run & Firestore in EU-West), Supabase (Frankfurt), and Stripe Payments (PCI-DSS Level 1).",
            "Audit & Erasure" to "Clients retain the right to complete data erasure within 48 hours of project sign-off or contract termination."
        )
    ),
    IMPRESSUM(
        title = "Legal Notice / Impressum",
        identifier = "Spanish LSSI-CE 34/2002",
        lastUpdated = "January 2026",
        summary = "Official corporate disclosure for Tech Unicorn Studio S.L. in compliance with Spanish and European electronic commerce regulations.",
        sections = listOf(
            "Corporate Entity" to "Tech Unicorn Studio S.L.",
            "Registration" to "Registro Mercantil de Valencia, Tomo 10928, Folio 144, Hoja V-198273.",
            "Tax ID / NIF" to "CIF: ESB-72910482",
            "Registered Address" to "Carrer de Colón 14, 46004 València, Spain",
            "Representative" to "Delia Lazarescu, Managing Director & Lead Systems Engineer"
        )
    )
}

/**
 * Mobile Implementation of the Official Tech Unicorn Landing Page Footer:
 * (<footer class="footer"> and <div class="footer-word">)
 *
 * Directives:
 * - Huge wordmark banner: "TECH UNICORN" with subtle outline opacity.
 * - Isometric vector brand mark (StudioLogo) + official entity name & headline.
 * - Comprehensive social links track with live follower metrics:
 *    Instagram (384K), YouTube (22K), LinkedIn (25K), TikTok (45K), Email.
 * - Quick jump section anchors (#about, #services, #process, #guarantees, #stack, #why-delia, #pricing, #speaking).
 * - Interactive role mode switcher (Client Portal / Studio Admin).
 * - Interactive European Legal Modals (Privacy Policy, Terms of Service, GDPR DPA, Impressum) with full GDPR dialog.
 * - Click-to-copy email with toast notification.
 * - Real-time Valencia HQ / Nomad status indicator: "Made somewhere with good wifi 🌴".
 * - Strict flat design: 0dp elevation, crisp 1dp outlines, solid dark tokens.
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun LandingFooterSection(
    onNavigateSection: (sectionId: String) -> Unit,
    onOpenSocial: (url: String) -> Unit,
    onSwitchRole: (UserRole) -> Unit,
    onScrollToTop: () -> Unit,
    modifier: Modifier = Modifier
) {
    val clipboardManager: ClipboardManager = LocalClipboardManager.current
    val coroutineScope = rememberCoroutineScope()
    var copyNotification by remember { mutableStateOf<String?>(null) }
    var activeLegalDoc by remember { mutableStateOf<LegalDocType?>(null) }

    val socialLinks = remember {
        listOf(
            FooterLink.SocialLink("Instagram", "@tech.unicorn", "384K followers", "https://www.instagram.com/tech.unicorn/", Icons.Default.CameraAlt),
            FooterLink.SocialLink("YouTube", "@Tech.Unicorn", "22K subscribers", "https://www.youtube.com/@Tech.Unicorn", Icons.Default.SmartDisplay),
            FooterLink.SocialLink("LinkedIn", "Delia Lazarescu", "25K network", "https://www.linkedin.com/in/delialazarescu/", Icons.Default.Work),
            FooterLink.SocialLink("TikTok", "@tech.unicorn", "45K audience", "https://www.tiktok.com/@tech.unicorn", Icons.Default.MusicNote),
            FooterLink.SocialLink("Email", "hello@thetechunicorn.com", "24h SLA", "mailto:hello@thetechunicorn.com", Icons.Default.Email)
        )
    }

    val sectionAnchors = remember {
        listOf(
            FooterLink.SectionJump("01: About", "about", "01"),
            FooterLink.SectionJump("02: Services", "services", "02"),
            FooterLink.SectionJump("02b: Socials", "content", "02b"),
            FooterLink.SectionJump("03: How It Works", "process", "03"),
            FooterLink.SectionJump("Commitments", "guarantees", "✦"),
            FooterLink.SectionJump("04: Tech Stack", "stack", "04"),
            FooterLink.SectionJump("05: Why Delia", "why-delia", "05"),
            FooterLink.SectionJump("04: Pricing", "pricing", "$"),
            FooterLink.SectionJump("06: Speaking", "speaking", "06"),
            FooterLink.SectionJump("FAQ", "faq", "?")
        )
    }

    Surface(
        modifier = modifier.fillMaxWidth(),
        color = Color(0xFF0F0F14),
        shape = RoundedCornerShape(20.dp),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.5f))
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(20.dp)
        ) {
            // HUGE BRAND WORDMARK (from web line 731: <div class="footer-word">TECH UNICORN</div>)
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 12.dp),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = "TECH UNICORN",
                    fontSize = 28.sp,
                    fontWeight = FontWeight.ExtraBold,
                    fontFamily = FontFamily.Monospace,
                    letterSpacing = 4.sp,
                    color = Color.White.copy(alpha = 0.12f),
                    textAlign = TextAlign.Center
                )
            }

            Spacer(Modifier.height(8.dp))

            // BRAND IDENTITY ROW
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .size(36.dp)
                            .clip(RoundedCornerShape(8.dp))
                            .background(StudioCoral),
                        contentAlignment = Alignment.Center
                    ) {
                        StudioLogo(modifier = Modifier.size(20.dp), tint = Color.White)
                    }

                    Column {
                        Text(
                            text = "Tech Unicorn Studio",
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White
                        )
                        Text(
                            text = "Delia Lazarescu · Ex-Google Engineer",
                            fontSize = 11.sp,
                            color = Color.White.copy(alpha = 0.65f)
                        )
                    }
                }

                // Scroll to top button
                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = Color.White.copy(alpha = 0.08f),
                    border = BorderStroke(1.dp, Color.White.copy(alpha = 0.15f)),
                    modifier = Modifier.clickable { onScrollToTop() }
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Icon(
                            Icons.Default.ArrowUpward,
                            contentDescription = "Scroll to top",
                            tint = Color.White,
                            modifier = Modifier.size(13.dp)
                        )
                        Text(
                            text = "Top",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White
                        )
                    }
                }
            }

            Spacer(Modifier.height(12.dp))

            Text(
                text = "Building and deploying production AI applications, technical video content, and autonomous workflows for founders worldwide.",
                fontSize = 12.sp,
                lineHeight = 18.sp,
                color = Color.White.copy(alpha = 0.7f)
            )

            Spacer(Modifier.height(14.dp))

            // NOMAD WI-FI & LOCATION TELEMETRY PILL
            Surface(
                shape = RoundedCornerShape(8.dp),
                color = Color.White.copy(alpha = 0.04f),
                border = BorderStroke(1.dp, Color.White.copy(alpha = 0.08f)),
                modifier = Modifier.fillMaxWidth()
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Box(
                            modifier = Modifier
                                .size(6.dp)
                                .clip(CircleShape)
                                .background(StudioEmerald)
                        )
                        Text(
                            text = "HQ: València, Spain (CET · UTC+1)",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = Color.White
                        )
                    }

                    Text(
                        text = "69 countries · 24ms wifi 🌴",
                        fontSize = 10.sp,
                        fontFamily = FontFamily.Monospace,
                        color = StudioEmerald
                    )
                }
            }

            Spacer(Modifier.height(20.dp))
            HorizontalDivider(color = Color.White.copy(alpha = 0.1f))
            Spacer(Modifier.height(16.dp))

            // SECTION JUMP ANCHORS
            Text(
                text = "PAGE NAVIGATION",
                fontSize = 10.sp,
                fontWeight = FontWeight.Bold,
                fontFamily = FontFamily.Monospace,
                color = StudioCoral,
                letterSpacing = 1.sp
            )

            Spacer(Modifier.height(10.dp))

            FlowRow(
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                sectionAnchors.forEach { anchor ->
                    Surface(
                        shape = RoundedCornerShape(6.dp),
                        color = Color.White.copy(alpha = 0.05f),
                        border = BorderStroke(1.dp, Color.White.copy(alpha = 0.12f)),
                        modifier = Modifier.clickable { onNavigateSection(anchor.sectionId) }
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Text(
                                text = anchor.number,
                                fontSize = 9.sp,
                                fontWeight = FontWeight.Bold,
                                fontFamily = FontFamily.Monospace,
                                color = StudioCoral
                            )
                            Text(
                                text = anchor.title.replace(Regex("^\\d+[a-z]*:\\s*"), ""),
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Medium,
                                color = Color.White.copy(alpha = 0.9f)
                            )
                        }
                    }
                }
            }

            Spacer(Modifier.height(20.dp))
            HorizontalDivider(color = Color.White.copy(alpha = 0.1f))
            Spacer(Modifier.height(16.dp))

            // SOCIAL & CONTACT CHANNELS (5 Channels from landing_page_source.html)
            Text(
                text = "SOCIAL & DIRECT CONTACT",
                fontSize = 10.sp,
                fontWeight = FontWeight.Bold,
                fontFamily = FontFamily.Monospace,
                color = StudioViolet,
                letterSpacing = 1.sp
            )

            Spacer(Modifier.height(10.dp))

            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                socialLinks.forEach { link ->
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = Color.White.copy(alpha = 0.04f),
                        border = BorderStroke(1.dp, Color.White.copy(alpha = 0.08f)),
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable {
                                if (link.title == "Email") {
                                    clipboardManager.setText(AnnotatedString("hello@thetechunicorn.com"))
                                    copyNotification = "Email copied to clipboard (hello@thetechunicorn.com)"
                                    coroutineScope.launch {
                                        delay(2600)
                                        copyNotification = null
                                    }
                                } else {
                                    onOpenSocial(link.url)
                                }
                            }
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 9.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(10.dp)
                            ) {
                                Icon(
                                    link.icon,
                                    contentDescription = null,
                                    tint = StudioCoral,
                                    modifier = Modifier.size(16.dp)
                                )
                                Column {
                                    Text(
                                        text = link.title,
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.SemiBold,
                                        color = Color.White
                                    )
                                    Text(
                                        text = link.handle,
                                        fontSize = 10.sp,
                                        fontFamily = FontFamily.Monospace,
                                        color = Color.White.copy(alpha = 0.6f)
                                    )
                                }
                            }

                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(4.dp))
                                        .background(Color.White.copy(alpha = 0.08f))
                                        .padding(horizontal = 6.dp, vertical = 2.dp)
                                ) {
                                    Text(
                                        text = link.reach,
                                        fontSize = 10.sp,
                                        fontFamily = FontFamily.Monospace,
                                        color = Color.White.copy(alpha = 0.85f)
                                    )
                                }

                                Icon(
                                    Icons.Default.ArrowOutward,
                                    contentDescription = null,
                                    tint = Color.White.copy(alpha = 0.4f),
                                    modifier = Modifier.size(13.dp)
                                )
                            }
                        }
                    }
                }
            }

            // COPY TOAST FEEDBACK
            if (copyNotification != null) {
                Spacer(Modifier.height(8.dp))
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(6.dp))
                        .background(StudioEmerald.copy(alpha = 0.15f))
                        .border(1.dp, StudioEmerald.copy(alpha = 0.4f), RoundedCornerShape(6.dp))
                        .padding(horizontal = 10.dp, vertical = 6.dp)
                ) {
                    Text(
                        text = "✓ $copyNotification",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = StudioEmerald
                    )
                }
            }

            Spacer(Modifier.height(20.dp))
            HorizontalDivider(color = Color.White.copy(alpha = 0.1f))
            Spacer(Modifier.height(16.dp))

            // STUDIO / CLIENT PORTAL QUICK SWITCHER
            Text(
                text = "PORTAL ENVIRONMENT ACCESS",
                fontSize = 10.sp,
                fontWeight = FontWeight.Bold,
                fontFamily = FontFamily.Monospace,
                color = Color.White.copy(alpha = 0.6f),
                letterSpacing = 1.sp
            )

            Spacer(Modifier.height(8.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                OutlinedButton(
                    onClick = { onSwitchRole(UserRole.CLIENT) },
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(8.dp),
                    border = BorderStroke(1.dp, Color.White.copy(alpha = 0.2f)),
                    contentPadding = PaddingValues(vertical = 10.dp)
                ) {
                    Text(
                        text = "Client Portal",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = Color.White
                    )
                }

                Button(
                    onClick = { onSwitchRole(UserRole.STUDIO) },
                    modifier = Modifier.weight(1f),
                    colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                    shape = RoundedCornerShape(8.dp),
                    contentPadding = PaddingValues(vertical = 10.dp)
                ) {
                    Text(
                        text = "Studio Admin",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White
                    )
                }
            }

            Spacer(Modifier.height(20.dp))
            HorizontalDivider(color = Color.White.copy(alpha = 0.1f))
            Spacer(Modifier.height(16.dp))

            // EU LEGAL & COMPLIANCE
            Column {
                Text(
                    text = "EU LEGAL & ENTITY DISCLOSURE",
                    fontSize = 10.sp,
                    fontWeight = FontWeight.Bold,
                    fontFamily = FontFamily.Monospace,
                    color = Color.White.copy(alpha = 0.5f),
                    letterSpacing = 0.8.sp
                )

                Spacer(Modifier.height(6.dp))

                Text(
                    text = "Tech Unicorn Studio S.L. · CIF/VAT: ESB-72910482\nCarrer de Colón 14, 46004 València, Spain",
                    fontSize = 10.sp,
                    lineHeight = 15.sp,
                    color = Color.White.copy(alpha = 0.6f)
                )

                Spacer(Modifier.height(10.dp))

                // Interactive Legal Modal Triggers
                FlowRow(
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                    verticalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    LegalDocType.entries.forEach { doc ->
                        Text(
                            text = doc.title,
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Medium,
                            color = StudioCoral,
                            textDecoration = TextDecoration.Underline,
                            modifier = Modifier.clickable { activeLegalDoc = doc }
                        )
                    }
                }

                Spacer(Modifier.height(14.dp))

                // Official web copyright note from line 744 of landing_page_source.html
                Text(
                    text = "© 2026 Tech Unicorn — software development, technical consulting & media services · hello@thetechunicorn.com · Made somewhere with good wifi 🌴",
                    fontSize = 10.sp,
                    lineHeight = 14.sp,
                    color = Color.White.copy(alpha = 0.45f),
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth()
                )
            }
        }
    }

    // INTERACTIVE LEGAL POPUP MODAL
    if (activeLegalDoc != null) {
        val doc = activeLegalDoc!!
        AlertDialog(
            onDismissRequest = { activeLegalDoc = null },
            confirmButton = {
                Button(
                    onClick = { activeLegalDoc = null },
                    colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                    shape = RoundedCornerShape(8.dp)
                ) {
                    Text("Close", fontWeight = FontWeight.Bold)
                }
            },
            title = {
                Column {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        StudioLogo(modifier = Modifier.size(16.dp), tint = StudioCoral)
                        Text(
                            text = doc.title,
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                    }
                    Spacer(Modifier.height(2.dp))
                    Text(
                        text = "${doc.identifier} · Updated ${doc.lastUpdated}",
                        fontSize = 10.sp,
                        fontFamily = FontFamily.Monospace,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            },
            text = {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .heightIn(max = 380.dp)
                        .padding(vertical = 4.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    // Summary Box
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(8.dp))
                            .background(MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.6f))
                            .padding(10.dp)
                    ) {
                        Text(
                            text = doc.summary,
                            fontSize = 11.sp,
                            lineHeight = 16.sp,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                    }

                    // Sections
                    doc.sections.forEach { (heading, body) ->
                        Column {
                            Text(
                                text = heading,
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = StudioCoral
                            )
                            Spacer(Modifier.height(2.dp))
                            Text(
                                text = body,
                                fontSize = 11.sp,
                                lineHeight = 16.sp,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                }
            },
            shape = RoundedCornerShape(16.dp),
            containerColor = MaterialTheme.colorScheme.surface
        )
    }
}
