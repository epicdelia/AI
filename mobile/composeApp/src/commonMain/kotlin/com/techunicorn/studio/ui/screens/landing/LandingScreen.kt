package com.techunicorn.studio.ui.screens.landing

import androidx.compose.animation.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.models.*
import com.techunicorn.studio.ui.components.*
import com.techunicorn.studio.ui.theme.*

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun LandingScreen(
    services: List<LandingService>,
    talks: List<Talk>,
    shippedBuilds: List<ShippedBuild>,
    pricingPackages: List<PricingPackage>,
    faqItems: List<FaqItem>,
    onOpenAuth: () -> Unit,
    onSwitchRole: (UserRole) -> Unit,
    onBookService: (String) -> Unit,
    onExploreSnippets: () -> Unit
) {
    var showBookingDialog by remember { mutableStateOf(false) }
    var selectedServiceForBooking by remember { mutableStateOf("Sprint MVP Build") }
    var bookingName by remember { mutableStateOf("") }
    var bookingEmail by remember { mutableStateOf("") }
    var bookingNotes by remember { mutableStateOf("") }
    var bookingConfirmed by remember { mutableStateOf(false) }
    var activeVideoModal by remember { mutableStateOf<HeroMosaicVideo?>(null) }

    // Estimator state
    var selectedProjectType by remember { mutableStateOf(0) } // 0: MVP ($12,500), 1: AI Agent Pipeline ($8,500), 2: Backend Architecture ($9,500), 3: Advisory ($3,500)
    var addStripeBilling by remember { mutableStateOf(true) } // +$1,200
    var addMobileCompanion by remember { mutableStateOf(false) } // +$4,500
    var addVectorRag by remember { mutableStateOf(true) } // +$2,000

    // Pricing deposit split toggle
    var isDepositView by remember { mutableStateOf(false) }

    // FAQ filter
    var selectedFaqCategory by remember { mutableStateOf("all") }

    // Lead magnet newsletter
    var newsletterEmail by remember { mutableStateOf("") }
    var newsletterSubscribed by remember { mutableStateOf(false) }

    LazyColumn(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(24.dp)
    ) {
        item { Spacer(Modifier.height(8.dp)) }

        // ==========================================
        // 1. BRAND HEADER & STICKY ACTION BAR
        // ==========================================
        item {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(18.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
            ) {
                Column(Modifier.padding(16.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(
                                modifier = Modifier
                                    .size(42.dp)
                                    .clip(CircleShape)
                                    .background(StudioCoral),
                                contentAlignment = Alignment.Center
                            ) {
                                StudioLogo(size = 24.dp, tint = Color.White)
                            }
                            Spacer(Modifier.width(12.dp))
                            Column {
                                Text(
                                    "Tech Unicorn",
                                    style = MaterialTheme.typography.titleLarge,
                                    fontWeight = FontWeight.Bold
                                )
                                Text(
                                    "Delia Lazarescu · Ex-Google",
                                    fontSize = 12.sp,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        }

                        // Auth & Portal Access Button
                        Button(
                            onClick = onOpenAuth,
                            colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                            shape = RoundedCornerShape(20.dp),
                            contentPadding = PaddingValues(horizontal = 14.dp, vertical = 6.dp)
                        ) {
                            Icon(Icons.Default.AccountCircle, contentDescription = null, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Sign In / Portal", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                    }

                    Spacer(Modifier.height(12.dp))
                    HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.5f))
                    Spacer(Modifier.height(10.dp))

                    // Location & Live Status Pill
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(
                                modifier = Modifier
                                    .size(8.dp)
                                    .clip(CircleShape)
                                    .background(StudioEmerald)
                            )
                            Spacer(Modifier.width(6.dp))
                            Text(
                                "Available for Q2 MVP Sprints",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = StudioEmerald
                            )
                        }

                        Text(
                            "📍 Valencia, Spain (CET)",
                            fontSize = 11.sp,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }
        }

        // ==========================================
        // 2. HERO SECTION (<section class="landing-hero tu-hero-stage">)
        // ==========================================
        item {
            LandingHero(
                onBookIntroCall = {
                    selectedServiceForBooking = "Sprint MVP Build"
                    showBookingDialog = true
                },
                onWatchVideo = { video ->
                    activeVideoModal = video
                },
                onSelectWorkOption = { opt ->
                    selectedServiceForBooking = opt.title
                    showBookingDialog = true
                },
                onOpenSocial = { /* Social deep-link */ },
                onNavigateSection = { section ->
                    if (section == "pricing") isDepositView = false
                }
            )
        }

        // ==========================================
        // 3. ABOUT SECTION (<section class="landing-section landing-about" id="about">)
        // ==========================================
        item {
            LandingAboutSection(
                onBookIntroCall = {
                    selectedServiceForBooking = "Sprint MVP Build"
                    showBookingDialog = true
                },
                onReadStory = {
                    // Open LinkedIn / Story
                },
                onFactClick = { factKey ->
                    if (factKey == "countries") isDepositView = false
                }
            )
        }

        // ==========================================
        // 4. 6 CORE SERVICES
        // ==========================================
        item {
            Column {
                Text("02: SERVICES", color = StudioCoral, fontWeight = FontWeight.Bold, fontSize = 11.sp, letterSpacing = 1.sp)
                Spacer(Modifier.height(4.dp))
                Text("Six Ways We Can Work Together", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(4.dp))
                Text("Fixed pricing, transparent deliverables, and zero corporate fluff.", fontSize = 13.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }

        items(services) { service ->
            var isExpanded by remember { mutableStateOf(false) }

            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
            ) {
                Column(Modifier.padding(18.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(service.category.uppercase(), fontWeight = FontWeight.Bold, fontSize = 11.sp, color = StudioViolet)
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(8.dp))
                                .background(MaterialTheme.colorScheme.surfaceVariant)
                                .padding(horizontal = 10.dp, vertical = 4.dp)
                        ) {
                            Text(service.badge, fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }

                    Spacer(Modifier.height(8.dp))
                    Text(service.title, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(6.dp))
                    Text(service.desc, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)

                    Spacer(Modifier.height(14.dp))
                    Text("KEY DELIVERABLES:", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    val shownDeliverables = if (isExpanded) service.deliverables else service.deliverables.take(2)
                    for (deliv in shownDeliverables) {
                        Row(Modifier.padding(vertical = 3.dp)) {
                            Icon(Icons.Default.CheckCircle, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(8.dp))
                            Text(deliv, fontSize = 12.sp)
                        }
                    }

                    if (service.deliverables.size > 2) {
                        TextButton(
                            onClick = { isExpanded = !isExpanded },
                            contentPadding = PaddingValues(0.dp)
                        ) {
                            Text(
                                if (isExpanded) "Show Less" else "Show All Deliverables (+${service.deliverables.size - 2})",
                                fontSize = 12.sp,
                                color = StudioCoral,
                                fontWeight = FontWeight.SemiBold
                            )
                        }
                    }

                    Spacer(Modifier.height(10.dp))
                    Button(
                        onClick = {
                            selectedServiceForBooking = service.title
                            showBookingDialog = true
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text("Book ${service.title}", color = MaterialTheme.colorScheme.onSurface, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
        }

        // ==========================================
        // 6. SHIPPED BUILDS & CASE STUDIES
        // ==========================================
        item {
            Column {
                Text("03: PROVEN TRACK RECORD", color = StudioViolet, fontWeight = FontWeight.Bold, fontSize = 11.sp, letterSpacing = 1.sp)
                Spacer(Modifier.height(4.dp))
                Text("Shipped Products & Case Studies", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(4.dp))
                Text("Real-world production systems deployed globally from coffee shops and coworking hubs.", fontSize = 13.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }

        items(shippedBuilds) { build ->
            var showSpecs by remember { mutableStateOf(false) }

            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(Modifier.padding(18.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(8.dp))
                                .background(StudioEmerald.copy(alpha = 0.15f))
                                .padding(horizontal = 8.dp, vertical = 4.dp)
                        ) {
                            Text(build.turnaround, color = StudioEmerald, fontWeight = FontWeight.Bold, fontSize = 11.sp)
                        }
                        Text("📍 ${build.location}", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }

                    Spacer(Modifier.height(8.dp))
                    Text(build.title, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                    Text(build.tagline, fontSize = 12.sp, color = StudioCoral, fontWeight = FontWeight.SemiBold)

                    Spacer(Modifier.height(8.dp))
                    Text(build.desc, fontSize = 13.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

                    Spacer(Modifier.height(14.dp))
                    // Metrics Table
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        build.metrics.forEach { (key, value) ->
                            Column {
                                Text(value, fontWeight = FontWeight.Bold, fontSize = 14.sp, color = MaterialTheme.colorScheme.onSurface)
                                Text(key, fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                        }
                    }

                    Spacer(Modifier.height(12.dp))
                    // Stack Tags
                    FlowRow(
                        horizontalArrangement = Arrangement.spacedBy(6.dp),
                        verticalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        build.stack.forEach { tech ->
                            Box(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(6.dp))
                                    .background(MaterialTheme.colorScheme.surfaceVariant)
                                    .padding(horizontal = 8.dp, vertical = 3.dp)
                            ) {
                                Text(tech, fontSize = 10.sp, fontWeight = FontWeight.Medium)
                            }
                        }
                    }

                    Spacer(Modifier.height(10.dp))
                    TextButton(
                        onClick = { showSpecs = !showSpecs },
                        contentPadding = PaddingValues(0.dp)
                    ) {
                        Text(if (showSpecs) "Hide Architecture Specs ▲" else "Inspect Architecture Specs ▼", fontSize = 11.sp, color = StudioViolet)
                    }

                    if (showSpecs) {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(8.dp))
                                .background(MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f))
                                .padding(10.dp)
                        ) {
                            Text(
                                "Production Spec: Fully containerized with Docker, automated GitHub Actions CI/CD pipeline, Redis event streaming, and automated SSL termination on GCP Cloud Run.",
                                fontSize = 11.sp,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                }
            }
        }

        // ==========================================
        // 7. INTERACTIVE PROJECT ESTIMATOR
        // ==========================================
        item {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(20.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.8f))
            ) {
                Column(Modifier.padding(20.dp)) {
                    Text("04: INSTANT ESTIMATOR", color = StudioCoral, fontWeight = FontWeight.Bold, fontSize = 11.sp, letterSpacing = 1.sp)
                    Spacer(Modifier.height(4.dp))
                    Text("Interactive Project Scope Calculator", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(4.dp))
                    Text("Configure your build scope and see estimated investment and delivery timeline instantly.", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

                    Spacer(Modifier.height(16.dp))
                    Text("STEP 1: SELECT CORE ENGAGEMENT", fontSize = 10.sp, fontWeight = FontWeight.Bold)
                    val projectTypes = listOf(
                        "Sprint MVP ($12,500 · 3 wks)",
                        "AI Agent Pipeline ($8,500 · 2 wks)",
                        "Backend Architecture ($9,500 · 2 wks)",
                        "AI Advisory ($3,500 / mo)"
                    )
                    val basePrices = listOf(12500, 8500, 9500, 3500)

                    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        projectTypes.forEachIndexed { index, title ->
                            val isSelected = selectedProjectType == index
                            Card(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clickable { selectedProjectType = index },
                                shape = RoundedCornerShape(10.dp),
                                colors = CardDefaults.cardColors(
                                    containerColor = if (isSelected) StudioCoral.copy(alpha = 0.15f) else MaterialTheme.colorScheme.surface
                                ),
                                border = if (isSelected) CardDefaults.outlinedCardBorder().copy(brush = Brush.linearGradient(listOf(StudioCoral, StudioCoral))) else null
                            ) {
                                Row(
                                    Modifier.padding(12.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    RadioButton(selected = isSelected, onClick = { selectedProjectType = index })
                                    Spacer(Modifier.width(8.dp))
                                    Text(title, fontSize = 12.sp, fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal)
                                }
                            }
                        }
                    }

                    Spacer(Modifier.height(16.dp))
                    Text("STEP 2: OPTIONAL ADD-ONS", fontSize = 10.sp, fontWeight = FontWeight.Bold)

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text("Stripe Webhook Billing (+$1,200)", fontSize = 12.sp)
                        Switch(checked = addStripeBilling, onCheckedChange = { addStripeBilling = it })
                    }

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text("Mobile App Companion (+$4,500)", fontSize = 12.sp)
                        Switch(checked = addMobileCompanion, onCheckedChange = { addMobileCompanion = it })
                    }

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text("Vector DB RAG Search (+$2,000)", fontSize = 12.sp)
                        Switch(checked = addVectorRag, onCheckedChange = { addVectorRag = it })
                    }

                    // Calculation
                    val totalEstimate = basePrices[selectedProjectType] +
                            (if (addStripeBilling) 1200 else 0) +
                            (if (addMobileCompanion) 4500 else 0) +
                            (if (addVectorRag) 2000 else 0)

                    Spacer(Modifier.height(16.dp))
                    HorizontalDivider()
                    Spacer(Modifier.height(12.dp))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column {
                            Text("ESTIMATED INVESTMENT", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            Text("$$totalEstimate", fontSize = 24.sp, fontWeight = FontWeight.ExtraBold, color = StudioCoral)
                        }
                        Button(
                            onClick = {
                                selectedServiceForBooking = "${projectTypes[selectedProjectType]} (Total: $$totalEstimate)"
                                showBookingDialog = true
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                            shape = RoundedCornerShape(10.dp)
                        ) {
                            Text("Request Review", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
        }

        // ==========================================
        // 8. PACKAGES & TRANSPARENT PRICING
        // ==========================================
        item {
            Column {
                Text("05: TRANSPARENT PRICING", color = StudioCoral, fontWeight = FontWeight.Bold, fontSize = 11.sp, letterSpacing = 1.sp)
                Spacer(Modifier.height(4.dp))
                Text("Simple Packages, Zero Upsells", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(4.dp))
                Text("Transparent pricing models with fixed deliverables and 100% IP ownership.", fontSize = 13.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

                Spacer(Modifier.height(12.dp))
                // Deposit Toggle
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Text("Full Investment", fontSize = 12.sp, fontWeight = if (!isDepositView) FontWeight.Bold else FontWeight.Normal)
                    Switch(checked = isDepositView, onCheckedChange = { isDepositView = it })
                    Text("50% Split Deposit", fontSize = 12.sp, fontWeight = if (isDepositView) FontWeight.Bold else FontWeight.Normal, color = StudioEmerald)
                }
            }
        }

        items(pricingPackages) { pkg ->
            val displayPrice = if (isDepositView && pkg.key == "mvp") pkg.price / 2 else pkg.price
            val priceSuffix = if (isDepositView && pkg.key == "mvp") "deposit upfront (50%)" else pkg.per

            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                border = if (pkg.popular) CardDefaults.outlinedCardBorder().copy(brush = Brush.linearGradient(listOf(StudioCoral, StudioViolet))) else null
            ) {
                Column(Modifier.padding(18.dp)) {
                    if (pkg.popular) {
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(6.dp))
                                .background(StudioCoral)
                                .padding(horizontal = 8.dp, vertical = 2.dp)
                        ) {
                            Text("MOST POPULAR", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 9.sp)
                        }
                        Spacer(Modifier.height(6.dp))
                    }

                    Text(pkg.name, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(4.dp))
                    Text(pkg.desc, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

                    Spacer(Modifier.height(12.dp))
                    Row(verticalAlignment = Alignment.Bottom) {
                        Text("$${displayPrice.toInt()}", fontSize = 28.sp, fontWeight = FontWeight.ExtraBold, color = StudioCoral)
                        Spacer(Modifier.width(6.dp))
                        Text("/ $priceSuffix", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.padding(bottom = 4.dp))
                    }

                    Text(pkg.clarify, fontSize = 11.sp, color = StudioEmerald, fontWeight = FontWeight.SemiBold)

                    Spacer(Modifier.height(14.dp))
                    Text("INCLUDED IN THIS ENGAGEMENT:", fontSize = 10.sp, fontWeight = FontWeight.Bold)
                    for (benefit in pkg.benefits) {
                        Row(Modifier.padding(vertical = 3.dp)) {
                            Icon(Icons.Default.Check, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(8.dp))
                            Text(benefit, fontSize = 12.sp)
                        }
                    }

                    Spacer(Modifier.height(14.dp))
                    Button(
                        onClick = {
                            selectedServiceForBooking = pkg.name
                            showBookingDialog = true
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = if (pkg.popular) StudioCoral else MaterialTheme.colorScheme.surfaceVariant),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text(
                            "Select ${pkg.name}",
                            color = if (pkg.popular) Color.White else MaterialTheme.colorScheme.onSurface,
                            fontWeight = FontWeight.Bold,
                            fontSize = 12.sp
                        )
                    }
                }
            }
        }

        // ==========================================
        // 9. ON STAGE & KEYNOTES
        // ==========================================
        item {
            Column {
                Text("06: ON STAGE", color = StudioViolet, fontWeight = FontWeight.Bold, fontSize = 11.sp, letterSpacing = 1.sp)
                Spacer(Modifier.height(4.dp))
                Text("Keynotes & Tech Summits", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
            }
        }

        items(talks) { talk ->
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(14.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(Modifier.padding(16.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(talk.event, fontWeight = FontWeight.Bold, fontSize = 12.sp, color = StudioCoral)
                        Text(talk.reach, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = StudioEmerald)
                    }
                    Spacer(Modifier.height(4.dp))
                    Text(talk.title, fontWeight = FontWeight.Bold, fontSize = 15.sp)
                    Spacer(Modifier.height(4.dp))
                    Text(talk.description, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }

        // ==========================================
        // 10. GUARANTEES & POLICIES
        // ==========================================
        item {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(18.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)
            ) {
                Column(Modifier.padding(18.dp)) {
                    Text("07: IRONCLAD GUARANTEES", color = StudioCoral, fontWeight = FontWeight.Bold, fontSize = 11.sp, letterSpacing = 1.sp)
                    Spacer(Modifier.height(4.dp))
                    Text("Complete Peace of Mind", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)

                    Spacer(Modifier.height(12.dp))
                    val guarantees = listOf(
                        "100% IP & Code Ownership" to "Every line of code and commit belongs entirely to your entity. No proprietary frameworks, no licensing royalties.",
                        "30-Day Post-Launch Bug Warranty" to "Any defect or regression discovered within 30 days of launch is patched immediately at zero cost.",
                        "Direct Access to Delia" to "No account managers, junior proxies, or offshore handoffs. You collaborate directly with an ex-Google senior engineer.",
                        "Weekly Loom Demos & Daily Commits" to "Zero black box surprises. You receive recorded Friday Loom walkthroughs and active GitHub progress."
                    )

                    guarantees.forEach { (title, desc) ->
                        Row(Modifier.padding(vertical = 4.dp)) {
                            Icon(Icons.Default.VerifiedUser, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Column {
                                Text(title, fontWeight = FontWeight.Bold, fontSize = 12.sp)
                                Text(desc, fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                        }
                    }
                }
            }
        }

        // ==========================================
        // 11. COMPREHENSIVE FAQ
        // ==========================================
        item {
            Column {
                Text("08: FREQUENTLY ASKED QUESTIONS", color = StudioCoral, fontWeight = FontWeight.Bold, fontSize = 11.sp, letterSpacing = 1.sp)
                Spacer(Modifier.height(4.dp))
                Text("Everything You Need to Know", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)

                Spacer(Modifier.height(10.dp))
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    val categories = listOf("all" to "All Questions", "mvp" to "MVP Builds", "advisory" to "Advisory Retainer", "logistics" to "Logistics & Timezones")
                    categories.forEach { (catKey, catLabel) ->
                        val isSelected = selectedFaqCategory == catKey
                        FilterChip(
                            selected = isSelected,
                            onClick = { selectedFaqCategory = catKey },
                            label = { Text(catLabel, fontSize = 11.sp) }
                        )
                    }
                }
            }
        }

        val filteredFaqs = faqItems.filter { selectedFaqCategory == "all" || it.category == selectedFaqCategory }
        items(filteredFaqs) { faq ->
            var isFaqExpanded by remember { mutableStateOf(false) }

            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable { isFaqExpanded = !isFaqExpanded },
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(Modifier.padding(16.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(faq.question, fontWeight = FontWeight.Bold, fontSize = 13.sp, modifier = Modifier.weight(1f))
                        Icon(
                            imageVector = if (isFaqExpanded) Icons.Default.ExpandLess else Icons.Default.ExpandMore,
                            contentDescription = null,
                            tint = StudioCoral
                        )
                    }

                    if (isFaqExpanded) {
                        Spacer(Modifier.height(8.dp))
                        Text(faq.answer, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant, lineHeight = 18.sp)

                        Spacer(Modifier.height(10.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            faq.tags.forEach { tag ->
                                Box(
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(6.dp))
                                        .background(MaterialTheme.colorScheme.surfaceVariant)
                                        .padding(horizontal = 6.dp, vertical = 2.dp)
                                ) {
                                    Text(tag, fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                            }
                        }
                    }
                }
            }
        }

        // ==========================================
        // 12. LEAD MAGNET / NEWSLETTER
        // ==========================================
        item {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(20.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(Modifier.padding(20.dp)) {
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(8.dp))
                            .background(StudioEmerald.copy(alpha = 0.15f))
                            .padding(horizontal = 8.dp, vertical = 4.dp)
                    ) {
                        Text("FREE DOWNLOAD", color = StudioEmerald, fontWeight = FontWeight.Bold, fontSize = 10.sp)
                    }
                    Spacer(Modifier.height(8.dp))
                    Text("The 2026 AI Vibecoding Playbook", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(4.dp))
                    Text("My exact systems prompt pack, deterministic subagent architecture, and CI/CD setup for shipping apps in 14 days.", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

                    Spacer(Modifier.height(14.dp))
                    if (newsletterSubscribed) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(8.dp))
                                .background(StudioEmerald.copy(alpha = 0.15f))
                                .padding(12.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(Icons.Default.CheckCircle, contentDescription = null, tint = StudioEmerald)
                            Spacer(Modifier.width(8.dp))
                            Text("Playbook link sent! Check your inbox.", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = StudioEmerald)
                        }
                    } else {
                        OutlinedTextField(
                            value = newsletterEmail,
                            onValueChange = { newsletterEmail = it },
                            placeholder = { Text("you@company.com") },
                            label = { Text("Work Email") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )
                        Spacer(Modifier.height(8.dp))
                        Button(
                            onClick = {
                                if (newsletterEmail.contains("@")) {
                                    newsletterSubscribed = true
                                }
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Text("Download Free Playbook", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                        }
                    }
                }
            }
        }

        // ==========================================
        // 13. COMPREHENSIVE FOOTER
        // ==========================================
        item {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(20.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f))
            ) {
                Column(Modifier.padding(20.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier
                                .size(32.dp)
                                .clip(CircleShape)
                                .background(StudioCoral),
                            contentAlignment = Alignment.Center
                        ) {
                            StudioLogo(size = 18.dp, tint = Color.White)
                        }
                        Spacer(Modifier.width(10.dp))
                        Column {
                            Text("Tech Unicorn Studio", fontWeight = FontWeight.Bold, fontSize = 16.sp)
                            Text("Delia Lazarescu · Ex-Google Engineer", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }

                    Spacer(Modifier.height(12.dp))
                    Text(
                        "Building & shipping production software with autonomous AI systems for founders worldwide.",
                        fontSize = 11.sp,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )

                    Spacer(Modifier.height(16.dp))
                    HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.3f))
                    Spacer(Modifier.height(12.dp))

                    Text("PORTAL ACCESS & MODES", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Spacer(Modifier.height(6.dp))
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedButton(
                            onClick = { onSwitchRole(UserRole.CLIENT) },
                            modifier = Modifier.weight(1f),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Text("Client Portal", fontSize = 11.sp)
                        }
                        Button(
                            onClick = { onSwitchRole(UserRole.STUDIO) },
                            modifier = Modifier.weight(1f),
                            colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Text("Studio Admin", fontSize = 11.sp)
                        }
                    }

                    Spacer(Modifier.height(16.dp))
                    HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.3f))
                    Spacer(Modifier.height(12.dp))

                    Text("EU LEGAL & COMPLIANCE", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Spacer(Modifier.height(4.dp))
                    Text("Tech Unicorn Studio S.L. · CIF/VAT: ESX9999999Z\nCarrer de Colón 14, 46004 València, Spain", fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

                    Spacer(Modifier.height(8.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        Text("Privacy Policy", fontSize = 10.sp, color = StudioCoral)
                        Text("Terms of Service", fontSize = 10.sp, color = StudioCoral)
                        Text("GDPR DPA", fontSize = 10.sp, color = StudioCoral)
                        Text("Impressum", fontSize = 10.sp, color = StudioCoral)
                    }

                    Spacer(Modifier.height(14.dp))
                    Text(
                        "© 2026 Tech Unicorn Studio S.L. · Built with Compose Multiplatform & Kotlin",
                        fontSize = 10.sp,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        textAlign = TextAlign.Center,
                        modifier = Modifier.fillMaxWidth()
                    )
                }
            }
        }

        item { Spacer(Modifier.height(24.dp)) }
    }

    // ==========================================
    // INSTANT BOOKING DIALOG
    // ==========================================
    if (showBookingDialog) {
        AlertDialog(
            onDismissRequest = {
                showBookingDialog = false
                bookingConfirmed = false
            },
            title = {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("📅 ", fontSize = 18.sp)
                    Text(if (bookingConfirmed) "Call Request Received" else "Book an Intro Call", fontWeight = FontWeight.Bold, fontSize = 18.sp)
                }
            },
            text = {
                if (bookingConfirmed) {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Thank you, $bookingName!", fontWeight = FontWeight.Bold, color = StudioEmerald)
                        Text("Your request for \"$selectedServiceForBooking\" has been logged.", fontSize = 13.sp)
                        Text("Delia will review your details and send a direct Google Meet calendar invite within 24 hours.", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                } else {
                    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(8.dp))
                                .background(StudioCoral.copy(alpha = 0.12f))
                                .padding(8.dp)
                        ) {
                            Text("Selected: $selectedServiceForBooking", fontWeight = FontWeight.Bold, color = StudioCoral, fontSize = 12.sp)
                        }
                        Text("We'll discuss your MVP scope, tech architecture, and timeline directly with Delia.", fontSize = 12.sp)

                        OutlinedTextField(
                            value = bookingName,
                            onValueChange = { bookingName = it },
                            label = { Text("Your Name & Company") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )
                        OutlinedTextField(
                            value = bookingEmail,
                            onValueChange = { bookingEmail = it },
                            label = { Text("Work Email") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )
                        OutlinedTextField(
                            value = bookingNotes,
                            onValueChange = { bookingNotes = it },
                            label = { Text("Brief Project Summary (Optional)") },
                            modifier = Modifier.fillMaxWidth(),
                            maxLines = 3
                        )
                    }
                }
            },
            confirmButton = {
                if (bookingConfirmed) {
                    Button(
                        onClick = {
                            showBookingDialog = false
                            bookingConfirmed = false
                            onBookService(selectedServiceForBooking)
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = StudioEmerald)
                    ) {
                        Text("Done")
                    }
                } else {
                    Button(
                        onClick = {
                            if (bookingName.isNotBlank() && bookingEmail.contains("@")) {
                                bookingConfirmed = true
                            } else {
                                bookingConfirmed = true
                            }
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = StudioCoral)
                    ) {
                        Text("Send Call Request")
                    }
                }
            },
            dismissButton = {
                if (!bookingConfirmed) {
                    TextButton(onClick = { showBookingDialog = false }) {
                        Text("Cancel")
                    }
                }
            }
        )
    }

    // FEATURED / MOSAIC VIDEO PREVIEW DIALOG
    if (activeVideoModal != null) {
        val video = activeVideoModal!!
        AlertDialog(
            onDismissRequest = { activeVideoModal = null },
            confirmButton = {
                Button(
                    onClick = { activeVideoModal = null },
                    colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                    shape = RoundedCornerShape(8.dp)
                ) {
                    Text("Close", fontWeight = FontWeight.Bold)
                }
            },
            title = {
                Text(
                    text = video.title,
                    fontWeight = FontWeight.Bold,
                    fontSize = 14.sp
                )
            },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Surface(
                        shape = RoundedCornerShape(10.dp),
                        color = Color(0xFF1E212B),
                        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)),
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(160.dp)
                    ) {
                        Box(contentAlignment = Alignment.Center) {
                            Surface(
                                shape = CircleShape,
                                color = StudioCoral,
                                modifier = Modifier.size(46.dp)
                            ) {
                                Box(contentAlignment = Alignment.Center) {
                                    Icon(
                                        Icons.Default.PlayArrow,
                                        contentDescription = "Playing",
                                        tint = Color.White,
                                        modifier = Modifier.size(24.dp)
                                    )
                                }
                            }
                            Box(
                                modifier = Modifier
                                    .align(Alignment.BottomEnd)
                                    .padding(8.dp)
                                    .clip(RoundedCornerShape(4.dp))
                                    .background(Color.Black.copy(alpha = 0.8f))
                                    .padding(horizontal = 6.dp, vertical = 2.dp)
                            ) {
                                Text(
                                    video.duration,
                                    fontSize = 9.sp,
                                    fontFamily = FontFamily.Monospace,
                                    color = Color.White
                                )
                            }
                        }
                    }

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = video.views,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = StudioCoral
                        )
                        Text(
                            text = "Ex-Google Software Engineer",
                            fontSize = 10.sp,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }

                    Text(
                        text = "YouTube ID: ${video.id} • Delia Lazarescu (@Tech.Unicorn)",
                        fontSize = 10.sp,
                        fontFamily = FontFamily.Monospace,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            },
            shape = RoundedCornerShape(14.dp),
            containerColor = MaterialTheme.colorScheme.surface
        )
    }
}
