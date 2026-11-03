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
import kotlinx.coroutines.launch

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
    var showPaymentSheet by remember { mutableStateOf(false) }
    var paymentPackageId by remember { mutableStateOf("retainer") }
    var paymentPackageName by remember { mutableStateOf("Monthly AI Advisory") }
    var paymentAmount by remember { mutableStateOf(3500.0) }

    // FAQ filter
    var selectedFaqCategory by remember { mutableStateOf("all") }

    // Lead magnet newsletter
    var newsletterEmail by remember { mutableStateOf("") }
    var newsletterSubscribed by remember { mutableStateOf(false) }

    val listState = androidx.compose.foundation.lazy.rememberLazyListState()
    val coroutineScope = rememberCoroutineScope()

    LazyColumn(
        state = listState,
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
                    // Navigate to section
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
                    // Fact click
                }
            )
        }

        // ==========================================
        // 4. SERVICES SECTION (<section class="landing-section tinted" id="services">)
        // ==========================================
        item {
            LandingServicesSection(
                services = services,
                onBookService = { serviceName ->
                    selectedServiceForBooking = serviceName
                    showBookingDialog = true
                }
            )
        }

        // ==========================================
        // 5. CONTENT & SOCIAL CHANNELS (<section class="landing-section landing-yt-section" id="youtube">)
        // ==========================================
        item {
            LandingSocialChannelsSection(
                onPlayContent = { item ->
                    activeVideoModal = HeroMosaicVideo(
                        id = item.id,
                        title = item.title,
                        views = item.views,
                        duration = item.durationOrMetric
                    )
                },
                onVisitChannel = { /* Visit external platform channel */ }
            )
        }

        // ==========================================
        // 6. HOW IT WORKS / PROCESS (<section class="process" id="process">)
        // ==========================================
        item {
            LandingProcessSection(
                onBookIntroCall = {
                    selectedServiceForBooking = "Sprint MVP Build"
                    showBookingDialog = true
                }
            )
        }

        // ==========================================
        // 7. COMMITMENTS & GUARANTEES (<section class="landing-section tinted" id="guarantees">)
        // ==========================================
        item {
            LandingGuaranteesSection(
                onBookIntroCall = {
                    selectedServiceForBooking = "Sprint MVP Build"
                    showBookingDialog = true
                }
            )
        }

        // ==========================================
        // 8. ENGINEERING ECOSYSTEM (<section class="landing-section" id="stack">)
        // ==========================================
        item {
            LandingStackSection(
                onReviewStack = {
                    selectedServiceForBooking = "Technical Consulting"
                    showBookingDialog = true
                }
            )
        }

        // ==========================================
        // 9. WHY DELIA LAZARESCU (<section class="landing-section" id="why-delia">)
        // ==========================================
        item {
            LandingCompareSection(
                onBookIntroCall = {
                    selectedServiceForBooking = "Sprint MVP Build"
                    showBookingDialog = true
                }
            )
        }

        // ==========================================
        // 10. PACKAGES & PRICING (<section class="pricing" id="pricing">)
        // ==========================================
        item {
            LandingPricingSection(
                onSelectPlan = { planId, planName, priceStr ->
                    selectedServiceForBooking = "$planName ($priceStr)"
                    showBookingDialog = true
                },
                onBookIntroCall = { serviceName ->
                    selectedServiceForBooking = serviceName
                    showBookingDialog = true
                }
            )
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
        // 11. SPEAKING & STAGE (<section class="speaking" id="speaking">) + FINAL CTA
        // ==========================================
        item {
            LandingSpeakingSection(
                onBookSpeaking = { topic ->
                    selectedServiceForBooking = "Speaking & Workshops: $topic"
                    showBookingDialog = true
                },
                onBookCall = {
                    selectedServiceForBooking = "Sprint MVP Build"
                    showBookingDialog = true
                }
            )
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
        // 13. COMPREHENSIVE NATIVE FOOTER (<footer class="footer">)
        // ==========================================
        item {
            LandingFooterSection(
                onNavigateSection = { sectionId ->
                    // Anchor navigation
                },
                onOpenSocial = { url ->
                    // External social url
                },
                onSwitchRole = onSwitchRole,
                onScrollToTop = {
                    coroutineScope.launch {
                        listState.animateScrollToItem(0)
                    }
                }
            )
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
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedButton(
                            onClick = {
                                val isMvp = selectedServiceForBooking.contains("MVP", ignoreCase = true)
                                paymentPackageId = if (isMvp) "mvp_full" else "retainer"
                                paymentPackageName = if (isMvp) "Sprint MVP Build Package" else "Monthly AI Advisory Retainer"
                                paymentAmount = if (isMvp) 12000.0 else 3500.0
                                showPaymentSheet = true
                                showBookingDialog = false
                            }
                        ) {
                            Text("Instant Pay")
                        }
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

    if (showPaymentSheet) {
        MobilePaymentSheet(
            packageId = paymentPackageId,
            packageName = paymentPackageName,
            amount = paymentAmount,
            clientNameDefault = bookingName,
            clientEmailDefault = bookingEmail,
            onDismiss = { showPaymentSheet = false },
            onPaymentSuccess = {
                showPaymentSheet = false
                bookingConfirmed = true
            }
        )
    }
}
