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
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.ui.theme.*
import kotlinx.datetime.Clock
import kotlinx.datetime.DateTimeUnit
import kotlinx.datetime.TimeZone
import kotlinx.datetime.plus
import kotlinx.datetime.toLocalDateTime
import kotlinx.serialization.Serializable
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json

/**
 * Value class for high-precision USD currency with operator overloading and clean formatting.
 */
@JvmInline
value class UsdCents(val cents: Long) : Comparable<UsdCents> {
    val dollars: Long get() = cents / 100

    fun toFormattedString(): String {
        val s = dollars.toString()
        val sb = StringBuilder()
        for (i in s.indices) {
            if (i > 0 && (s.length - i) % 3 == 0) sb.append(',')
            sb.append(s[i])
        }
        return "\$$sb"
    }

    operator fun plus(other: UsdCents): UsdCents = UsdCents(cents + other.cents)
    operator fun minus(other: UsdCents): UsdCents = UsdCents((cents - other.cents).coerceAtLeast(0L))
    operator fun times(multiplier: Double): UsdCents = UsdCents((cents * multiplier).toLong())
    override fun compareTo(other: UsdCents): Int = cents.compareTo(other.cents)

    companion object {
        val ZERO = UsdCents(0L)
        fun fromDollars(dollars: Long): UsdCents = UsdCents(dollars * 100L)
    }
}

/**
 * Benefit inclusion state for matrix comparisons.
 */
enum class BenefitState {
    INCLUDED,
    ADDED,
    ABSENT
}

data class PlanBenefit(
    val title: String,
    val state: BenefitState
)

data class PlanMetric(
    val label: String,
    val value: String,
    val sub: String
)

/**
 * Sealed hierarchy for official pricing packages matching Pricing.php & landing_page_source.html.
 */
sealed interface PricingPlan {
    val id: String
    val name: String
    val price: UsdCents
    val pricePrefix: String
    val interval: String
    val clarify: String
    val desc: String
    val ctaText: String
    val trustNote: String
    val badgeLabel: String
    val isPopular: Boolean
    val metrics: List<PlanMetric>
    val perks: List<String>
    val benefits: List<PlanBenefit>

    data class StrategyCall(
        override val id: String = "strategy",
        override val name: String = "Strategy Call",
        override val price: UsdCents = UsdCents.fromDollars(1500L),
        override val pricePrefix: String = "",
        override val interval: String = "/session",
        override val clarify: String = "one-time private call",
        override val desc: String = "A private 90-minute architecture review, code audit and AI product roadmap.",
        override val ctaText: String = "Book Strategy Call",
        override val trustNote: String = "100% satisfaction guarantee",
        override val badgeLabel: String = "Google Meet 1:1",
        override val isPopular: Boolean = false,
        override val metrics: List<PlanMetric> = listOf(
            PlanMetric("Duration", "90 min", "private session"),
            PlanMetric("Seats", "1:1", "your core team"),
            PlanMetric("Turnaround", "24h", "to book"),
            PlanMetric("Support", "7 days", "async follow-up")
        ),
        override val perks: List<String> = listOf(
            "Full recording & searchable transcript",
            "Executive architecture summary report",
            "Follow-up async Q&A for 7 days"
        ),
        override val benefits: List<PlanBenefit> = listOf(
            PlanBenefit("90-minute private strategy call", BenefitState.INCLUDED),
            PlanBenefit("Full code & architecture audit", BenefitState.INCLUDED),
            PlanBenefit("AI model selection & roadmap", BenefitState.INCLUDED),
            PlanBenefit("7-day async follow-up via Slack", BenefitState.INCLUDED),
            PlanBenefit("Ongoing weekly on-call access", BenefitState.ABSENT),
            PlanBenefit("Dedicated MVP production build", BenefitState.ABSENT)
        )
    ) : PricingPlan

    data class Retainer(
        override val id: String = "retainer",
        override val name: String = "Advisory Retainer",
        override val price: UsdCents = UsdCents.fromDollars(3500L),
        override val pricePrefix: String = "",
        override val interval: String = "/month",
        override val clarify: String = "billed monthly, cancel anytime",
        override val desc: String = "An ex-Google engineer on call for your product and engineering team.",
        override val ctaText: String = "Start Retainer",
        override val trustNote: String = "Cancel anytime, no lock-in",
        override val badgeLabel: String = "Cloud & AI Advisory",
        override val isPopular: Boolean = false,
        override val metrics: List<PlanMetric> = listOf(
            PlanMetric("Strategy calls", "2 / mo", "every month"),
            PlanMetric("Async reviews", "Unlimited", "code & PRs"),
            PlanMetric("Response", "24h", "guaranteed"),
            PlanMetric("Direct line", "Slack / TG", "priority line")
        ),
        override val perks: List<String> = listOf(
            "2 deep-dive strategy calls every month",
            "Unlimited async PR & architecture reviews",
            "Engineering hiring & vetting evaluations"
        ),
        override val benefits: List<PlanBenefit> = listOf(
            PlanBenefit("2 deep-dive strategy calls per month", BenefitState.ADDED),
            PlanBenefit("Unlimited async PR & code reviews", BenefitState.ADDED),
            PlanBenefit("Direct Slack/Telegram access, 24h SLA", BenefitState.ADDED),
            PlanBenefit("Build-vs-buy and AI model evaluations", BenefitState.ADDED),
            PlanBenefit("Cancel anytime, no lock-in term", BenefitState.INCLUDED),
            PlanBenefit("Dedicated full MVP production builds", BenefitState.ABSENT)
        )
    ) : PricingPlan

    data class MvpBuild(
        override val id: String = "mvp",
        override val name: String = "MVP Build",
        override val price: UsdCents = UsdCents.fromDollars(12000L),
        override val pricePrefix: String = "from ",
        override val interval: String = "fixed",
        override val clarify: String = "fixed scope delivery",
        override val desc: String = "Your idea, live in 2 to 4 weeks: UX design, AI build and production cloud deploy.",
        override val ctaText: String = "Get a Fixed Quote",
        override val trustNote: String = "30-day post-launch warranty included",
        override val badgeLabel: String = "Production Cloud Deploy",
        override val isPopular: Boolean = true,
        val depositPrice: UsdCents = UsdCents.fromDollars(6000L),
        override val metrics: List<PlanMetric> = listOf(
            PlanMetric("Timeline", "2 to 4 wks", "production ready"),
            PlanMetric("Live demos", "Weekly", "Friday reviews"),
            PlanMetric("IP ownership", "100%", "all source & code"),
            PlanMetric("Warranty", "30 days", "after launch")
        ),
        override val perks: List<String> = listOf(
            "Full UX design, database & responsive app",
            "Cloud deployment with your domain & SSL",
            "30 days of post-launch engineering support",
            "100% code custody & IP ownership"
        ),
        override val benefits: List<PlanBenefit> = listOf(
            PlanBenefit("Complete product: UX, AI build & deploy", BenefitState.ADDED),
            PlanBenefit("Weekly Friday demos of working software", BenefitState.ADDED),
            PlanBenefit("Production codebase with clean documentation", BenefitState.ADDED),
            PlanBenefit("100% ownership of code, IP & accounts", BenefitState.ADDED),
            PlanBenefit("30-day post-launch bug warranty", BenefitState.ADDED),
            PlanBenefit("100% fixed price, zero surprise invoices", BenefitState.INCLUDED)
        )
    ) : PricingPlan

    data class BrandPartnership(
        override val id: String = "brand",
        override val name: String = "Brand Partnership",
        override val price: UsdCents = UsdCents.fromDollars(5000L),
        override val pricePrefix: String = "from ",
        override val interval: String = "/campaign",
        override val clarify: String = "multi-platform campaign",
        override val desc: String = "Your product explained to an engaged audience of 519K+ tech professionals.",
        override val ctaText: String = "Request Media Kit",
        override val trustNote: String = "Full digital commercial rights included",
        override val badgeLabel: String = "519K+ Audience Reach",
        override val isPopular: Boolean = false,
        override val metrics: List<PlanMetric> = listOf(
            PlanMetric("Reach", "519K+", "Instagram + YT + X"),
            PlanMetric("Retention", "82%", "above industry avg"),
            PlanMetric("Production", "4K HDR", "custom script"),
            PlanMetric("Rights", "Full", "commercial usage")
        ),
        override val perks: List<String> = listOf(
            "Concept, script, high-retention video production",
            "Native 384K Instagram + YouTube distribution",
            "Digital advertising usage rights included"
        ),
        override val benefits: List<PlanBenefit> = listOf(
            PlanBenefit("High-retention technical integration video", BenefitState.ADDED),
            PlanBenefit("384K Instagram + 22K YouTube distribution", BenefitState.ADDED),
            PlanBenefit("Full digital ad commercial usage rights", BenefitState.ADDED),
            PlanBenefit("Detailed post-campaign performance analytics", BenefitState.ADDED),
            PlanBenefit("Tracked links in bio & descriptions", BenefitState.INCLUDED),
            PlanBenefit("Personal endorsement from ex-Google engineer", BenefitState.INCLUDED)
        )
    ) : PricingPlan
}

/**
 * Project Archetype for the Real-Time Scope Estimator.
 */
enum class ProjectArchetype(
    val title: String,
    val baseWeeks: Int,
    val baseDollars: Long,
    val summary: String
) {
    VIBECODED_MVP(
        title = "Vibecoded App (MVP)",
        baseWeeks = 3,
        baseDollars = 12000L,
        summary = "AI-powered web application built, tested and deployed to production in 2-4 weeks."
    ),
    AGENTIC_WORKFLOW(
        title = "Autonomous AI Agent Workflow",
        baseWeeks = 3,
        baseDollars = 14500L,
        summary = "Autonomous tool-calling agents, vector memory routing, and human-in-the-loop triggers."
    ),
    CROSS_PLATFORM(
        title = "Web Console + Mobile Companion",
        baseWeeks = 4,
        baseDollars = 16500L,
        summary = "Web management dashboard + Kotlin Compose Multiplatform app (Android & Desktop/iOS)."
    ),
    ENTERPRISE_RAG(
        title = "Private Enterprise RAG Knowledgebase",
        baseWeeks = 3,
        baseDollars = 13500L,
        summary = "Document indexing pipeline, semantic chunking, and grounded LLM answers with citation audit."
    )
}

/**
 * Add-on modules for scope calculation.
 */
enum class ScopeAddon(
    val label: String,
    val days: Int,
    val dollars: Long,
    val tag: String
) {
    AUTH_RBAC("Multi-tenant Auth & RBAC", 2, 1500L, "Security"),
    STRIPE_PAYMENTS("Stripe Subscriptions & Webhooks", 3, 1800L, "Billing"),
    VECTOR_DB("Vector Embeddings & Semantic Search", 4, 2400L, "AI Memory"),
    MOBILE_COMPANION("Compose Multiplatform Mobile App", 7, 4500L, "Mobile"),
    OBSERVABILITY("Structured Logging & Telemetry", 2, 1200L, "DevOps"),
    DOCKER_DEPLOY("Docker Container & Cloud Run Setup", 0, 0L, "Included ($0)")
}

/**
 * Delivery velocity SLA multiplier.
 */
enum class DeliveryVelocity(
    val label: String,
    val multiplier: Double,
    val speedTag: String,
    val weekDelta: Int
) {
    STANDARD("Standard Pace (3-4 wks)", 1.0, "100% Rate", 0),
    FAST_TRACK("Fast-Track (3 wks)", 1.15, "+15% Express SLA", -1),
    HYPER_SPRINT("Hyper-Sprint (2 wks)", 1.30, "+30% Rush SLA", -2)
}

/**
 * Serializable snapshot of the calculated scope quotation.
 */
@Serializable
data class ScopeQuotationSnapshot(
    val archetype: String,
    val addons: List<String>,
    val velocity: String,
    val totalInvestment: Long,
    val deposit50Percent: Long,
    val estimatedWeeks: Int,
    val estimatedDeliveryDate: String,
    val timestampUtc: String
)

private val ScopeJson = Json { prettyPrint = true }

/**
 * Custom add-on engagements (listed below cards in the official site).
 */
data class CustomEngagement(
    val title: String,
    val startingPrice: String,
    val type: String
)

/**
 * Mobile Implementation of the Official "Packages & Pricing" Section:
 * (<section class="landing-section" id="pricing">)
 *
 * Directives:
 * - Eyebrow: "04 — Pricing"
 * - Headline: "Packages that pay for themselves" (with "pay for themselves" in italics + StudioCoral)
 * - Subtitle: "Every engagement starts with a free intro call and a fixed quote. Prices below are starting points for typical scope."
 * - Strict flat design: 0dp elevation, 1dp crisp outlines, high-contrast solid tokens.
 * - Interactive tab/segmented switcher between:
 *    [01: Packages (4 tiers)] and [02: Instant MVP Estimator & Scope Calculator]
 * - Includes 50% deposit option, live milestone calculation, and exportable JSON scope.
 */
@Composable
fun LandingPricingSection(
    onSelectPlan: (planId: String, planName: String, priceStr: String) -> Unit,
    onBookIntroCall: (serviceName: String) -> Unit,
    modifier: Modifier = Modifier
) {
    var activeTab by remember { mutableStateOf(0) } // 0 = Packages, 1 = Real-Time Scope Estimator
    var selectedPlanId by remember { mutableStateOf("mvp") }
    var showDepositCalculation by remember { mutableStateOf(true) }

    // Estimator State
    var selectedArchetype by remember { mutableStateOf(ProjectArchetype.VIBECODED_MVP) }
    var selectedAddons by remember { mutableStateOf(setOf(ScopeAddon.AUTH_RBAC, ScopeAddon.STRIPE_PAYMENTS, ScopeAddon.DOCKER_DEPLOY)) }
    var selectedVelocity by remember { mutableStateOf(DeliveryVelocity.STANDARD) }
    var exportedJsonText by remember { mutableStateOf<String?>(null) }

    val plans = remember {
        listOf(
            PricingPlan.MvpBuild(),
            PricingPlan.Retainer(),
            PricingPlan.BrandPartnership(),
            PricingPlan.StrategyCall()
        )
    }

    val customEngagements = remember {
        listOf(
            CustomEngagement("AI Integration Sprint", "from \$8,000", "2-week sprint"),
            CustomEngagement("Keynotes & Workshops", "from \$7,500", "conference / corporate"),
            CustomEngagement("B2B Content Engine", "from \$6,000/mo", "monthly retainer")
        )
    }

    // Estimator calculations using UsdCents
    val baseCost = remember(selectedArchetype) { UsdCents.fromDollars(selectedArchetype.baseDollars) }
    val addonsCost = remember(selectedAddons) {
        selectedAddons.fold(UsdCents.ZERO) { acc, addon -> acc + UsdCents.fromDollars(addon.dollars) }
    }
    val rawSubtotal = baseCost + addonsCost
    val totalCost = remember(rawSubtotal, selectedVelocity) { rawSubtotal * selectedVelocity.multiplier }
    val depositCost = remember(totalCost) { totalCost * 0.5 }
    val completionCost = remember(totalCost, depositCost) { totalCost - depositCost }

    val estimatedWeeks = remember(selectedArchetype, selectedAddons, selectedVelocity) {
        val extraDays = selectedAddons.sumOf { it.days }
        val rawWeeks = selectedArchetype.baseWeeks + (extraDays / 7)
        (rawWeeks + selectedVelocity.weekDelta).coerceAtLeast(2)
    }

    // Calculate delivery date via kotlinx.datetime
    val deliveryDateStr = remember(estimatedWeeks) {
        try {
            val now = Clock.System.now()
            val local = now.toLocalDateTime(TimeZone.currentSystemDefault())
            val target = local.date.plus(estimatedWeeks * 7, DateTimeUnit.DAY)
            "${target.month.name.take(3)} ${target.dayOfMonth}, ${target.year}"
        } catch (_: Exception) {
            "In $estimatedWeeks weeks"
        }
    }

    Surface(
        modifier = modifier.fillMaxWidth(),
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
                        .background(StudioCoral)
                )
                Text(
                    text = "04: PACKAGES & PRICING",
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    fontFamily = FontFamily.Monospace,
                    color = StudioCoral,
                    letterSpacing = 1.2.sp
                )
            }

            Spacer(Modifier.height(8.dp))

            // HEADLINE: "Packages that pay for themselves"
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                Text(
                    text = "Packages that",
                    style = MaterialTheme.typography.headlineMedium,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface
                )
                Text(
                    text = "pay for themselves",
                    style = MaterialTheme.typography.headlineMedium,
                    fontWeight = FontWeight.Bold,
                    fontStyle = FontStyle.Italic,
                    color = StudioCoral
                )
            }

            Spacer(Modifier.height(8.dp))

            // SUBTITLE
            Text(
                text = "Every engagement starts with a free intro call and a fixed quote. Prices below are starting points for typical scope.",
                fontSize = 13.sp,
                lineHeight = 19.sp,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )

            Spacer(Modifier.height(18.dp))

            // FLAT VIEW TOGGLE: [Packages] vs [Instant MVP Estimator]
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(10.dp))
                    .background(MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f))
                    .padding(3.dp),
                horizontalArrangement = Arrangement.spacedBy(4.dp)
            ) {
                listOf("Standard Packages", "Interactive Estimator").forEachIndexed { index, label ->
                    val isSelected = activeTab == index
                    Surface(
                        modifier = Modifier
                            .weight(1f)
                            .clip(RoundedCornerShape(8.dp))
                            .clickable { activeTab = index },
                        color = if (isSelected) MaterialTheme.colorScheme.surface else Color.Transparent,
                        border = if (isSelected) BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant) else null
                    ) {
                        Box(
                            modifier = Modifier.padding(vertical = 9.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                if (index == 0) {
                                    Icon(
                                        Icons.Default.CardGiftcard,
                                        contentDescription = null,
                                        modifier = Modifier.size(13.dp),
                                        tint = if (isSelected) StudioCoral else MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                } else {
                                    Icon(
                                        Icons.Default.Calculate,
                                        contentDescription = null,
                                        modifier = Modifier.size(13.dp),
                                        tint = if (isSelected) StudioCoral else MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                }
                                Text(
                                    text = label,
                                    fontSize = 12.sp,
                                    fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                    color = if (isSelected) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        }
                    }
                }
            }

            Spacer(Modifier.height(20.dp))

            // ==========================================
            // TAB 0: STANDARD PACKAGES
            // ==========================================
            if (activeTab == 0) {
                // Horizontal Plan Selector Tabs (Chips)
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    plans.forEach { plan ->
                        val isSelected = selectedPlanId == plan.id
                        Surface(
                            shape = RoundedCornerShape(8.dp),
                            color = if (isSelected) MaterialTheme.colorScheme.surfaceVariant else MaterialTheme.colorScheme.surface,
                            border = BorderStroke(
                                1.dp,
                                if (isSelected) StudioCoral else MaterialTheme.colorScheme.outlineVariant
                            ),
                            modifier = Modifier.clickable { selectedPlanId = plan.id }
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 7.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                if (plan.isPopular) {
                                    Box(
                                        modifier = Modifier
                                            .size(6.dp)
                                            .clip(CircleShape)
                                            .background(StudioCoral)
                                    )
                                }
                                Text(
                                    text = plan.name,
                                    fontSize = 12.sp,
                                    fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                    color = if (isSelected) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        }
                    }
                }

                Spacer(Modifier.height(16.dp))

                // The Selected Plan Detail Card (Featured presentation)
                val currentPlan = plans.firstOrNull { it.id == selectedPlanId } ?: plans.first()
                PlanCard(
                    plan = currentPlan,
                    showDeposit = showDepositCalculation,
                    onToggleDeposit = { showDepositCalculation = !showDepositCalculation },
                    onSelect = { onSelectPlan(currentPlan.id, currentPlan.name, currentPlan.price.toFormattedString()) },
                    onBookCall = { onBookIntroCall(currentPlan.name) }
                )

                Spacer(Modifier.height(20.dp))

                // CUSTOM ENGAGEMENTS BAR
                Surface(
                    shape = RoundedCornerShape(12.dp),
                    color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.4f),
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(Modifier.padding(14.dp)) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Text("✦", fontSize = 12.sp, color = StudioViolet)
                            Text(
                                text = "Also available for bespoke engagements:",
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Bold,
                                color = MaterialTheme.colorScheme.onSurface
                            )
                        }

                        Spacer(Modifier.height(10.dp))

                        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            customEngagements.forEach { custom ->
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column {
                                        Text(
                                            text = custom.title,
                                            fontSize = 12.sp,
                                            fontWeight = FontWeight.SemiBold,
                                            color = MaterialTheme.colorScheme.onSurface
                                        )
                                        Text(
                                            text = custom.type,
                                            fontSize = 10.sp,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant
                                        )
                                    }
                                    Box(
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(6.dp))
                                            .background(MaterialTheme.colorScheme.surface)
                                            .border(1.dp, MaterialTheme.colorScheme.outlineVariant, RoundedCornerShape(6.dp))
                                            .padding(horizontal = 8.dp, vertical = 3.dp)
                                    ) {
                                        Text(
                                            text = custom.startingPrice,
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.Bold,
                                            fontFamily = FontFamily.Monospace,
                                            color = StudioViolet
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                // ==========================================
                // TAB 1: INTERACTIVE REAL-TIME SCOPE ESTIMATOR
                // ==========================================
                Column(verticalArrangement = Arrangement.spacedBy(16.dp)) {
                    // STEP 1: Core Archetype Selection
                    Column {
                        Text(
                            text = "STEP 1: SELECT CORE BUILD ARCHETYPE",
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold,
                            fontFamily = FontFamily.Monospace,
                            color = StudioCoral,
                            letterSpacing = 0.8.sp
                        )
                        Spacer(Modifier.height(8.dp))

                        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            ProjectArchetype.entries.forEach { archetype ->
                                val isSelected = selectedArchetype == archetype
                                Surface(
                                    shape = RoundedCornerShape(10.dp),
                                    color = if (isSelected) MaterialTheme.colorScheme.surfaceVariant else MaterialTheme.colorScheme.surface,
                                    border = BorderStroke(
                                        1.dp,
                                        if (isSelected) StudioCoral else MaterialTheme.colorScheme.outlineVariant
                                    ),
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clickable { selectedArchetype = archetype }
                                ) {
                                    Row(
                                        modifier = Modifier.padding(12.dp),
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        RadioButton(
                                            selected = isSelected,
                                            onClick = { selectedArchetype = archetype },
                                            colors = RadioButtonDefaults.colors(selectedColor = StudioCoral)
                                        )
                                        Spacer(Modifier.width(10.dp))
                                        Column(Modifier.weight(1f)) {
                                            Row(
                                                modifier = Modifier.fillMaxWidth(),
                                                horizontalArrangement = Arrangement.SpaceBetween,
                                                verticalAlignment = Alignment.CenterVertically
                                            ) {
                                                Text(
                                                    text = archetype.title,
                                                    fontSize = 13.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = MaterialTheme.colorScheme.onSurface
                                                )
                                                Text(
                                                    text = "\$${archetype.baseDollars / 1000}k",
                                                    fontSize = 12.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    fontFamily = FontFamily.Monospace,
                                                    color = StudioCoral
                                                )
                                            }
                                            Spacer(Modifier.height(2.dp))
                                            Text(
                                                text = archetype.summary,
                                                fontSize = 11.sp,
                                                color = MaterialTheme.colorScheme.onSurfaceVariant
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }

                    HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)

                    // STEP 2: Add-on Modules (Checkboxes)
                    Column {
                        Text(
                            text = "STEP 2: SELECT MODULAR CAPABILITIES",
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold,
                            fontFamily = FontFamily.Monospace,
                            color = StudioViolet,
                            letterSpacing = 0.8.sp
                        )
                        Spacer(Modifier.height(8.dp))

                        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                            ScopeAddon.entries.forEach { addon ->
                                val isChecked = selectedAddons.contains(addon)
                                Surface(
                                    shape = RoundedCornerShape(8.dp),
                                    color = if (isChecked) MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.6f) else MaterialTheme.colorScheme.surface,
                                    border = BorderStroke(
                                        1.dp,
                                        if (isChecked) StudioViolet.copy(alpha = 0.6f) else MaterialTheme.colorScheme.outlineVariant
                                    ),
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clickable {
                                            selectedAddons = if (isChecked) selectedAddons - addon else selectedAddons + addon
                                        }
                                ) {
                                    Row(
                                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 8.dp),
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Checkbox(
                                            checked = isChecked,
                                            onCheckedChange = {
                                                selectedAddons = if (it) selectedAddons + addon else selectedAddons - addon
                                            },
                                            colors = CheckboxDefaults.colors(checkedColor = StudioViolet)
                                        )
                                        Spacer(Modifier.width(8.dp))
                                        Column(Modifier.weight(1f)) {
                                            Text(
                                                text = addon.label,
                                                fontSize = 12.sp,
                                                fontWeight = FontWeight.Medium,
                                                color = MaterialTheme.colorScheme.onSurface
                                            )
                                            if (addon.days > 0) {
                                                Text(
                                                    text = "+${addon.days} days engineering delivery",
                                                    fontSize = 10.sp,
                                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                                )
                                            }
                                        }
                                        Box(
                                            modifier = Modifier
                                                .clip(RoundedCornerShape(4.dp))
                                                .background(if (addon.dollars > 0) StudioViolet.copy(alpha = 0.12f) else StudioEmerald.copy(alpha = 0.12f))
                                                .padding(horizontal = 6.dp, vertical = 2.dp)
                                        ) {
                                            Text(
                                                text = if (addon.dollars > 0) "+\$${addon.dollars}" else "Included",
                                                fontSize = 10.sp,
                                                fontWeight = FontWeight.Bold,
                                                fontFamily = FontFamily.Monospace,
                                                color = if (addon.dollars > 0) StudioViolet else StudioEmerald
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }

                    HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)

                    // STEP 3: Delivery Velocity SLA
                    Column {
                        Text(
                            text = "STEP 3: TURNAROUND & VELOCITY SLA",
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold,
                            fontFamily = FontFamily.Monospace,
                            color = StudioEmerald,
                            letterSpacing = 0.8.sp
                        )
                        Spacer(Modifier.height(8.dp))

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            DeliveryVelocity.entries.forEach { velocity ->
                                val isSelected = selectedVelocity == velocity
                                Surface(
                                    modifier = Modifier
                                        .weight(1f)
                                        .clip(RoundedCornerShape(8.dp))
                                        .clickable { selectedVelocity = velocity },
                                    shape = RoundedCornerShape(8.dp),
                                    color = if (isSelected) MaterialTheme.colorScheme.surfaceVariant else MaterialTheme.colorScheme.surface,
                                    border = BorderStroke(
                                        1.dp,
                                        if (isSelected) StudioEmerald else MaterialTheme.colorScheme.outlineVariant
                                    )
                                ) {
                                    Column(
                                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 10.dp),
                                        horizontalAlignment = Alignment.CenterHorizontally
                                    ) {
                                        Text(
                                            text = velocity.label.split(" (")[0],
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.Bold,
                                            textAlign = TextAlign.Center,
                                            color = if (isSelected) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant
                                        )
                                        Spacer(Modifier.height(3.dp))
                                        Text(
                                            text = velocity.speedTag,
                                            fontSize = 9.sp,
                                            fontWeight = FontWeight.SemiBold,
                                            color = StudioEmerald,
                                            fontFamily = FontFamily.Monospace
                                        )
                                    }
                                }
                            }
                        }
                    }

                    // REAL-TIME INVESTMENT SUMMARY CARD
                    Surface(
                        shape = RoundedCornerShape(14.dp),
                        color = Color(0xFF141419),
                        border = BorderStroke(1.dp, StudioCoral.copy(alpha = 0.4f)),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Column(Modifier.padding(16.dp)) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column {
                                    Text(
                                        text = "ESTIMATED INVESTMENT",
                                        fontSize = 10.sp,
                                        fontWeight = FontWeight.Bold,
                                        fontFamily = FontFamily.Monospace,
                                        color = Color.White.copy(alpha = 0.6f)
                                    )
                                    Text(
                                        text = totalCost.toFormattedString(),
                                        fontSize = 26.sp,
                                        fontWeight = FontWeight.Bold,
                                        fontFamily = FontFamily.Monospace,
                                        color = StudioCoral
                                    )
                                }
                                Column(horizontalAlignment = Alignment.End) {
                                    Text(
                                        text = "ESTIMATED SHIP DATE",
                                        fontSize = 10.sp,
                                        fontWeight = FontWeight.Bold,
                                        fontFamily = FontFamily.Monospace,
                                        color = Color.White.copy(alpha = 0.6f)
                                    )
                                    Text(
                                        text = deliveryDateStr,
                                        fontSize = 13.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = StudioEmerald
                                    )
                                    Text(
                                        text = "$estimatedWeeks weeks fixed turnaround",
                                        fontSize = 10.sp,
                                        color = Color.White.copy(alpha = 0.7f)
                                    )
                                }
                            }

                            Spacer(Modifier.height(14.dp))
                            HorizontalDivider(color = Color.White.copy(alpha = 0.12f))
                            Spacer(Modifier.height(14.dp))

                            // 50% Kick-Off Deposit Schedule
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column {
                                    Text(
                                        text = "50% Kick-Off Deposit",
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.SemiBold,
                                        color = Color.White
                                    )
                                    Text(
                                        text = "Locks in Delia's dedicated sprint slot",
                                        fontSize = 10.sp,
                                        color = Color.White.copy(alpha = 0.6f)
                                    )
                                }
                                Text(
                                    text = depositCost.toFormattedString(),
                                    fontSize = 14.sp,
                                    fontWeight = FontWeight.Bold,
                                    fontFamily = FontFamily.Monospace,
                                    color = Color.White
                                )
                            }

                            Spacer(Modifier.height(8.dp))

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column {
                                    Text(
                                        text = "50% Upon Production Launch",
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.SemiBold,
                                        color = Color.White
                                    )
                                    Text(
                                        text = "Due only after live demo & deployment",
                                        fontSize = 10.sp,
                                        color = Color.White.copy(alpha = 0.6f)
                                    )
                                }
                                Text(
                                    text = completionCost.toFormattedString(),
                                    fontSize = 14.sp,
                                    fontWeight = FontWeight.Bold,
                                    fontFamily = FontFamily.Monospace,
                                    color = Color.White.copy(alpha = 0.8f)
                                )
                            }

                            Spacer(Modifier.height(16.dp))

                            // Action Buttons
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                Button(
                                    onClick = {
                                        onSelectPlan(
                                            "mvp_custom",
                                            "${selectedArchetype.title} (Custom)",
                                            depositCost.toFormattedString()
                                        )
                                    },
                                    modifier = Modifier.weight(1f),
                                    colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                                    shape = RoundedCornerShape(8.dp),
                                    contentPadding = PaddingValues(vertical = 12.dp)
                                ) {
                                    Text(
                                        text = "Lock Build Slot (${depositCost.toFormattedString()})",
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold
                                    )
                                }

                                OutlinedButton(
                                    onClick = {
                                        val snapshot = ScopeQuotationSnapshot(
                                            archetype = selectedArchetype.title,
                                            addons = selectedAddons.map { it.label },
                                            velocity = selectedVelocity.label,
                                            totalInvestment = totalCost.dollars,
                                            deposit50Percent = depositCost.dollars,
                                            estimatedWeeks = estimatedWeeks,
                                            estimatedDeliveryDate = deliveryDateStr,
                                            timestampUtc = Clock.System.now().toString()
                                        )
                                        exportedJsonText = ScopeJson.encodeToString(snapshot)
                                    },
                                    modifier = Modifier.weight(0.7f),
                                    shape = RoundedCornerShape(8.dp),
                                    border = BorderStroke(1.dp, Color.White.copy(alpha = 0.3f)),
                                    contentPadding = PaddingValues(vertical = 12.dp)
                                ) {
                                    Text(
                                        text = "Export Spec",
                                        fontSize = 12.sp,
                                        color = Color.White,
                                        fontWeight = FontWeight.SemiBold
                                    )
                                }
                            }

                            // Show Exported Spec Dialog / Snip
                            if (exportedJsonText != null) {
                                Spacer(Modifier.height(12.dp))
                                Box(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(Color.Black.copy(alpha = 0.6f))
                                        .border(1.dp, StudioCoral.copy(alpha = 0.3f), RoundedCornerShape(8.dp))
                                        .padding(10.dp)
                                ) {
                                    Column {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text(
                                                text = "PROJECT SPEC EXPORT (JSON)",
                                                fontSize = 9.sp,
                                                fontFamily = FontFamily.Monospace,
                                                fontWeight = FontWeight.Bold,
                                                color = StudioCoral
                                            )
                                            Text(
                                                text = "Dismiss",
                                                fontSize = 10.sp,
                                                color = Color.White.copy(alpha = 0.7f),
                                                modifier = Modifier.clickable { exportedJsonText = null }
                                            )
                                        }
                                        Spacer(Modifier.height(6.dp))
                                        Text(
                                            text = exportedJsonText!!,
                                            fontSize = 9.sp,
                                            fontFamily = FontFamily.Monospace,
                                            color = Color.White.copy(alpha = 0.85f),
                                            lineHeight = 13.sp
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

/**
 * Reusable Plan Card for standard package tiers.
 */
@Composable
private fun PlanCard(
    plan: PricingPlan,
    showDeposit: Boolean,
    onToggleDeposit: () -> Unit,
    onSelect: () -> Unit,
    onBookCall: () -> Unit,
    modifier: Modifier = Modifier
) {
    Surface(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        color = if (plan.isPopular) Color(0xFF141419) else MaterialTheme.colorScheme.surface,
        border = BorderStroke(
            if (plan.isPopular) 1.5.dp else 1.dp,
            if (plan.isPopular) StudioViolet else MaterialTheme.colorScheme.outlineVariant
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(20.dp)
        ) {
            // TOP BADGES ROW
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Category Pill
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(6.dp))
                        .background(if (plan.isPopular) StudioViolet.copy(alpha = 0.25f) else MaterialTheme.colorScheme.surfaceVariant)
                        .padding(horizontal = 8.dp, vertical = 4.dp)
                ) {
                    Text(
                        text = plan.badgeLabel,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = if (plan.isPopular) StudioViolet else MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }

                if (plan.isPopular) {
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(6.dp))
                            .background(StudioCoral)
                            .padding(horizontal = 8.dp, vertical = 3.dp)
                    ) {
                        Text(
                            text = "MOST POPULAR",
                            fontSize = 9.sp,
                            fontWeight = FontWeight.Bold,
                            fontFamily = FontFamily.Monospace,
                            color = Color.White
                        )
                    }
                }
            }

            Spacer(Modifier.height(12.dp))

            // PLAN NAME
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                if (plan is PricingPlan.MvpBuild) {
                    StudioLogo(modifier = Modifier.size(20.dp), tint = StudioCoral)
                }
                Text(
                    text = plan.name,
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = if (plan.isPopular) Color.White else MaterialTheme.colorScheme.onSurface
                )
            }

            Spacer(Modifier.height(4.dp))

            // DESCRIPTION
            Text(
                text = plan.desc,
                fontSize = 13.sp,
                lineHeight = 18.sp,
                color = if (plan.isPopular) Color.White.copy(alpha = 0.75f) else MaterialTheme.colorScheme.onSurfaceVariant
            )

            Spacer(Modifier.height(14.dp))

            // PRICE
            Row(
                verticalAlignment = Alignment.Bottom,
                horizontalArrangement = Arrangement.spacedBy(4.dp)
            ) {
                Text(
                    text = "${plan.pricePrefix}${plan.price.toFormattedString()}",
                    fontSize = 28.sp,
                    fontWeight = FontWeight.Bold,
                    fontFamily = FontFamily.Monospace,
                    color = if (plan.isPopular) StudioCoral else MaterialTheme.colorScheme.onSurface
                )
                Text(
                    text = plan.interval,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Medium,
                    color = if (plan.isPopular) Color.White.copy(alpha = 0.6f) else MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(bottom = 4.dp)
                )
            }

            Text(
                text = plan.clarify,
                fontSize = 11.sp,
                color = if (plan.isPopular) Color.White.copy(alpha = 0.5f) else MaterialTheme.colorScheme.onSurfaceVariant
            )

            // DEPOSIT TOGGLE (FOR MVP BUILD)
            if (plan is PricingPlan.MvpBuild) {
                Spacer(Modifier.height(12.dp))
                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = Color.White.copy(alpha = 0.06f),
                    border = BorderStroke(1.dp, Color.White.copy(alpha = 0.12f)),
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable { onToggleDeposit() }
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 8.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column {
                            Text(
                                text = "50% Upfront Deposit Option",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = StudioEmerald
                            )
                            Text(
                                text = "Remaining 50% upon deployment sign-off",
                                fontSize = 10.sp,
                                color = Color.White.copy(alpha = 0.6f)
                            )
                        }
                        Text(
                            text = plan.depositPrice.toFormattedString(),
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold,
                            fontFamily = FontFamily.Monospace,
                            color = StudioEmerald
                        )
                    }
                }
            }

            Spacer(Modifier.height(16.dp))

            // 4 METRICS QUADRANT
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(8.dp))
                    .background(if (plan.isPopular) Color.White.copy(alpha = 0.05f) else MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.4f))
                    .padding(vertical = 10.dp, horizontal = 6.dp),
                horizontalArrangement = Arrangement.SpaceEvenly
            ) {
                plan.metrics.forEach { metric ->
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(
                            text = metric.value,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            fontFamily = FontFamily.Monospace,
                            color = if (plan.isPopular) Color.White else MaterialTheme.colorScheme.onSurface
                        )
                        Text(
                            text = metric.label,
                            fontSize = 9.sp,
                            color = if (plan.isPopular) Color.White.copy(alpha = 0.6f) else MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }

            Spacer(Modifier.height(16.dp))

            // BENEFITS LIST
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                plan.benefits.forEach { benefit ->
                    Row(
                        verticalAlignment = Alignment.Top,
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        when (benefit.state) {
                            BenefitState.INCLUDED, BenefitState.ADDED -> {
                                Text(
                                    text = "✦",
                                    fontSize = 11.sp,
                                    color = if (plan.isPopular) StudioCoral else StudioViolet,
                                    modifier = Modifier.padding(top = 1.dp)
                                )
                                Text(
                                    text = benefit.title,
                                    fontSize = 12.sp,
                                    color = if (plan.isPopular) Color.White.copy(alpha = 0.9f) else MaterialTheme.colorScheme.onSurface
                                )
                            }
                            BenefitState.ABSENT -> {
                                Text(
                                    text = "—",
                                    fontSize = 11.sp,
                                    color = if (plan.isPopular) Color.White.copy(alpha = 0.25f) else MaterialTheme.colorScheme.outlineVariant,
                                    modifier = Modifier.padding(top = 1.dp)
                                )
                                Text(
                                    text = benefit.title,
                                    fontSize = 12.sp,
                                    color = if (plan.isPopular) Color.White.copy(alpha = 0.35f) else MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f)
                                )
                            }
                        }
                    }
                }
            }

            Spacer(Modifier.height(20.dp))

            // CTA BUTTONS
            Button(
                onClick = onSelect,
                colors = ButtonDefaults.buttonColors(
                    containerColor = if (plan.isPopular) StudioCoral else MaterialTheme.colorScheme.surfaceVariant
                ),
                shape = RoundedCornerShape(8.dp),
                modifier = Modifier.fillMaxWidth(),
                contentPadding = PaddingValues(vertical = 12.dp)
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Text(
                        text = plan.ctaText,
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        color = if (plan.isPopular) Color.White else MaterialTheme.colorScheme.onSurface
                    )
                    Icon(
                        Icons.AutoMirrored.Filled.ArrowForward,
                        contentDescription = null,
                        modifier = Modifier.size(14.dp),
                        tint = if (plan.isPopular) Color.White else MaterialTheme.colorScheme.onSurface
                    )
                }
            }

            Spacer(Modifier.height(8.dp))

            // Trust note
            Text(
                text = "✓ ${plan.trustNote}",
                fontSize = 11.sp,
                textAlign = TextAlign.Center,
                color = if (plan.isPopular) StudioEmerald else StudioEmerald,
                modifier = Modifier.fillMaxWidth()
            )
        }
    }
}
