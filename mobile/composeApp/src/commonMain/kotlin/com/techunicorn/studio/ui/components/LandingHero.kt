package com.techunicorn.studio.ui.components

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
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
import androidx.compose.material.icons.automirrored.filled.MenuBook
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
 * Work Options available under the "Work with me" menu.
 */
data class WorkOptionItem(
    val title: String,
    val subtitle: String,
    val icon: ImageVector,
    val key: String
)

/**
 * Brand or Stage partner data representation.
 */
data class BrandStage(
    val name: String,
    val mark: String? = null,
    val isStage: Boolean = false
)

/**
 * Video item for the Landing Hero Mosaic.
 */
data class HeroMosaicVideo(
    val id: String,
    val title: String,
    val views: String,
    val duration: String,
    val isFeatured: Boolean = false
)

/**
 * Comprehensive Mobile Translation of the Official Landing Hero (<section class="landing-hero tu-hero-stage">).
 *
 * Directives:
 * - Exact typography: "Making tech less scary: built from beach cafés in 69 countries."
 * - Custom SVG flourish wave underline for "less scary".
 * - Interactive "Work with me" eyebrow with flat dropdown options.
 * - Exact lede paragraph: "I am a software engineer who left Big Tech to build apps..."
 * - Action buttons: "Book an Intro Call" + "Watch the latest video".
 * - Brands & stages she's worked with (IBM, Adobe, Airalo, Temporal, KM & AI Summit, etc.).
 * - Full Media & Numbers Mosaic:
 *    - Featured video tile ("10 Brutal Truths I Learned as a Google Software Engineer")
 *    - Social network side tiles (Instagram: 384K / 12.7%, TikTok: 45K / 13.1%)
 *    - Video showcase row (3 video cards)
 *    - Results quadrant (519K+ audience, 69 countries, 2-4 wks MVP, ex-G)
 * - Strict flat design: 0dp elevation, crisp 1dp borders, solid accent fills, zero futuristic/sci-fi glows.
 */
@Composable
fun LandingHero(
    onBookIntroCall: () -> Unit,
    onWatchVideo: (HeroMosaicVideo) -> Unit,
    onSelectWorkOption: (WorkOptionItem) -> Unit,
    onOpenSocial: (String) -> Unit,
    onNavigateSection: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    var isWorkMenuOpen by remember { mutableStateOf(false) }

    val workOptions = remember {
        listOf(
            WorkOptionItem(
                title = "MVP build",
                subtitle = "Your idea, live in 2 to 4 weeks",
                icon = Icons.Default.Bolt,
                key = "mvp"
            ),
            WorkOptionItem(
                title = "AI & architecture consulting",
                subtitle = "Agents, LLM features, code reviews",
                icon = Icons.Default.AutoAwesome,
                key = "consulting"
            ),
            WorkOptionItem(
                title = "Advisory retainer",
                subtitle = "An engineer on call every month",
                icon = Icons.Default.Sync,
                key = "retainer"
            ),
            WorkOptionItem(
                title = "Brand campaign",
                subtitle = "Videos, reels and posts for your product",
                icon = Icons.Default.Work,
                key = "campaign"
            ),
            WorkOptionItem(
                title = "Keynote or workshop",
                subtitle = "In person or virtual",
                icon = Icons.Default.Mic,
                key = "speaking"
            ),
            WorkOptionItem(
                title = "See packages & pricing",
                subtitle = "Transparent scopes, no surprise invoices",
                icon = Icons.Default.Sell,
                key = "pricing"
            )
        )
    }

    val brandsAndStages = remember {
        listOf(
            BrandStage(name = "IBM", mark = "I"),
            BrandStage(name = "Adobe", mark = "A"),
            BrandStage(name = "Airalo", mark = "A"),
            BrandStage(name = "Temporal", mark = "T"),
            BrandStage(name = "KM & AI Summit 2025", isStage = true),
            BrandStage(name = "Head Start", isStage = true),
            BrandStage(name = "Nomad Cruise", isStage = true),
            BrandStage(name = "Vently Panel", isStage = true),
            BrandStage(name = "Digital Garage Tokyo", isStage = true)
        )
    }

    val featuredVideo = remember {
        HeroMosaicVideo(
            id = "5p3Mg4VEkbI",
            title = "10 Brutal Truths I Learned as a Google Software Engineer",
            views = "482K views",
            duration = "14:02",
            isFeatured = true
        )
    }

    val mosaicVideos = remember {
        listOf(
            HeroMosaicVideo(
                id = "DSXRUnBCYtQ",
                title = "I am back at Google! (after I quit... here is why)",
                views = "182K views",
                duration = "11:45"
            ),
            HeroMosaicVideo(
                id = "YJlJ12Txhkg",
                title = "Is OpenClaw Still Worth It in 2026? (Ex-Google Engineer)",
                views = "98K views",
                duration = "09:18"
            ),
            HeroMosaicVideo(
                id = "5jYD7ZRMJ5I",
                title = "WTF Is Happening To The Job Market (Ex-Googler Perspective)",
                views = "342K views",
                duration = "16:30"
            )
        )
    }

    Surface(
        modifier = modifier.fillMaxWidth(),
        color = MaterialTheme.colorScheme.surface,
        shape = RoundedCornerShape(20.dp),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.35f))
    ) {
        Box(modifier = Modifier.fillMaxWidth()) {
            // Subtle Nomad Trail Vector Background
            HeroNomadTrailCanvas(
                modifier = Modifier
                    .matchParentSize()
                    .padding(16.dp)
            )

            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 20.dp),
                verticalArrangement = Arrangement.spacedBy(18.dp)
            ) {
                // ================= PART 1: "WORK WITH ME" EYEBROW MENU =================
                Box {
                    Surface(
                        shape = RoundedCornerShape(24.dp),
                        color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.6f),
                        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.4f)),
                        modifier = Modifier
                            .clip(RoundedCornerShape(24.dp))
                            .clickable { isWorkMenuOpen = !isWorkMenuOpen }
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Icon(
                                Icons.Default.AutoAwesome,
                                contentDescription = null,
                                modifier = Modifier.size(13.dp),
                                tint = StudioCoral
                            )
                            Text(
                                text = "Work with me",
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Bold,
                                color = MaterialTheme.colorScheme.onSurface
                            )
                            Icon(
                                if (isWorkMenuOpen) Icons.Default.KeyboardArrowUp else Icons.Default.KeyboardArrowDown,
                                contentDescription = null,
                                modifier = Modifier.size(14.dp),
                                tint = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }

                    // Interactive Flat Work Dropdown Menu
                    DropdownMenu(
                        expanded = isWorkMenuOpen,
                        onDismissRequest = { isWorkMenuOpen = false },
                        modifier = Modifier
                            .width(300.dp)
                            .background(MaterialTheme.colorScheme.surface)
                            .border(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.4f), RoundedCornerShape(8.dp))
                    ) {
                        Column(modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp)) {
                            Text(
                                text = "Work with Delia",
                                fontWeight = FontWeight.Bold,
                                fontSize = 13.sp,
                                color = MaterialTheme.colorScheme.onSurface
                            )
                            Text(
                                text = "Every project starts with a free 30-minute call",
                                fontSize = 10.sp,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }

                        HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.3f))

                        workOptions.forEach { opt ->
                            DropdownMenuItem(
                                text = {
                                    Column {
                                        Text(opt.title, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                        Text(opt.subtitle, fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                    }
                                },
                                leadingIcon = {
                                    Box(
                                        modifier = Modifier
                                            .size(28.dp)
                                            .clip(RoundedCornerShape(6.dp))
                                            .background(StudioCoral.copy(alpha = 0.12f)),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(
                                            opt.icon,
                                            contentDescription = null,
                                            modifier = Modifier.size(15.dp),
                                            tint = StudioCoral
                                        )
                                    }
                                },
                                onClick = {
                                    isWorkMenuOpen = false
                                    onSelectWorkOption(opt)
                                }
                            )
                        }
                    }
                }

                // ================= PART 2: HERO TITLE & WAVED UNDERLINE =================
                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(
                        text = "Making tech",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )

                    Row(verticalAlignment = Alignment.Bottom) {
                        Column {
                            Text(
                                text = "less scary",
                                style = MaterialTheme.typography.headlineMedium,
                                fontWeight = FontWeight.ExtraBold,
                                fontStyle = FontStyle.Italic,
                                color = StudioCoral
                            )
                            // SVG Flourish Wave Underline (d="M2 8 Q 20 2, 40 6 T 78 5")
                            HeroTextWave(
                                color = StudioCoral,
                                modifier = Modifier
                                    .width(110.dp)
                                    .height(10.dp)
                            )
                        }
                        Text(
                            text = ": built from beach",
                            style = MaterialTheme.typography.headlineMedium,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                    }

                    Text(
                        text = "cafés in 69 countries.",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                }

                // ================= PART 3: EXACT LEDE PARAGRAPH =================
                Text(
                    text = "I am a software engineer who left Big Tech to build apps, create content for 519K+ people, and prove you do not need a hoodie and a standing desk to ship great software.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    lineHeight = 21.sp
                )

                // ================= PART 4: DUAL CALLS TO ACTION =================
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    // Primary CTA: Book an Intro Call
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
                            "Book an Intro Call",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White,
                            maxLines = 1
                        )
                        Spacer(Modifier.width(4.dp))
                        Icon(
                            Icons.AutoMirrored.Filled.ArrowForward,
                            contentDescription = null,
                            modifier = Modifier.size(13.dp),
                            tint = Color.White
                        )
                    }

                    Text(
                        text = "or",
                        fontSize = 11.sp,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )

                    // Secondary CTA: Watch the latest video
                    OutlinedButton(
                        onClick = { onWatchVideo(featuredVideo) },
                        shape = RoundedCornerShape(10.dp),
                        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.4f)),
                        modifier = Modifier.weight(1f),
                        contentPadding = PaddingValues(horizontal = 10.dp, vertical = 11.dp)
                    ) {
                        Box(
                            modifier = Modifier
                                .size(18.dp)
                                .clip(RoundedCornerShape(4.dp))
                                .background(StudioViolet.copy(alpha = 0.15f)),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                Icons.Default.PlayArrow,
                                contentDescription = null,
                                modifier = Modifier.size(12.dp),
                                tint = StudioViolet
                            )
                        }
                        Spacer(Modifier.width(6.dp))
                        Text(
                            "Watch latest video",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = MaterialTheme.colorScheme.onSurface,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                }

                // ================= PART 5: BRANDS & STAGES TRACK =================
                Column(
                    modifier = Modifier.fillMaxWidth(),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Text(
                            text = "Brands and stages she’s worked with",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Surface(
                            shape = CircleShape,
                            color = MaterialTheme.colorScheme.surfaceVariant,
                            border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.3f))
                        ) {
                            Text(
                                text = "9",
                                fontSize = 9.sp,
                                fontWeight = FontWeight.Bold,
                                color = StudioCoral,
                                modifier = Modifier.padding(horizontal = 6.dp, vertical = 1.dp)
                            )
                        }
                    }

                    // Flat horizontal scrolling marquee track
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .horizontalScroll(rememberScrollState()),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        brandsAndStages.forEach { brand ->
                            Surface(
                                shape = RoundedCornerShape(6.dp),
                                color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.4f),
                                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.25f))
                            ) {
                                Row(
                                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 5.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                ) {
                                    if (brand.isStage) {
                                        Icon(
                                            Icons.Default.Mic,
                                            contentDescription = null,
                                            modifier = Modifier.size(12.dp),
                                            tint = StudioViolet
                                        )
                                    } else {
                                        Box(
                                            modifier = Modifier
                                                .size(16.dp)
                                                .clip(RoundedCornerShape(3.dp))
                                                .background(StudioCoral.copy(alpha = 0.15f)),
                                            contentAlignment = Alignment.Center
                                        ) {
                                            Text(
                                                text = brand.mark ?: brand.name.take(1),
                                                fontSize = 9.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = StudioCoral
                                            )
                                        }
                                    }
                                    Text(
                                        text = brand.name,
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.Medium,
                                        color = MaterialTheme.colorScheme.onSurface
                                    )
                                }
                            }
                        }
                    }
                }

                // ================= PART 6: QUICK READ LINKS =================
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    Text(
                        text = "Prefer to read first?",
                        fontSize = 10.sp,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    Text(
                        text = "Packages",
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        color = StudioCoral,
                        modifier = Modifier.clickable { onNavigateSection("pricing") }
                    )
                    Text("·", fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(
                        text = "How it works",
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        color = StudioOcean,
                        modifier = Modifier.clickable { onNavigateSection("process") }
                    )
                    Text("·", fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(
                        text = "About Delia",
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        color = StudioViolet,
                        modifier = Modifier.clickable { onNavigateSection("about") }
                    )
                }

                HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.25f))

                // ================= PART 7: MEDIA & NUMBERS MOSAIC (tu-mosaic) =================
                Column(
                    modifier = Modifier.fillMaxWidth(),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Text(
                        text = "HER LATEST VIDEOS AND NUMBERS",
                        fontSize = 9.sp,
                        fontWeight = FontWeight.Bold,
                        fontFamily = FontFamily.Monospace,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )

                    // 1. Featured Video Card
                    FlatFeaturedVideoCard(
                        video = featuredVideo,
                        onClick = { onWatchVideo(featuredVideo) }
                    )

                    // 2. Social Networks Side-by-Side (Instagram + TikTok)
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        FlatSocialAudienceCard(
                            network = "Instagram",
                            handle = "@tech.unicorn",
                            followers = "384K",
                            metric = "12.7% engaged",
                            badgeColor = StudioCoral,
                            icon = Icons.Default.CameraAlt,
                            onClick = { onOpenSocial("instagram") },
                            modifier = Modifier.weight(1f)
                        )
                        FlatSocialAudienceCard(
                            network = "TikTok",
                            handle = "@tech.unicorn",
                            followers = "45K",
                            metric = "13.1% engaged",
                            badgeColor = StudioViolet,
                            icon = Icons.Default.PlayCircle,
                            onClick = { onOpenSocial("tiktok") },
                            modifier = Modifier.weight(1f)
                        )
                    }

                    // 3. Video Showcase Row (3 Horizontal Cards)
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .horizontalScroll(rememberScrollState()),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        mosaicVideos.forEach { video ->
                            FlatMiniVideoCard(
                                video = video,
                                onClick = { onWatchVideo(video) }
                            )
                        }
                    }

                    // 4. Results Stats Quadrant (519K+, 69 Countries, 2-4 wks, ex-G)
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        FlatMosaicStat(
                            value = "519K+",
                            label = "Combined audience",
                            accentColor = StudioCoral,
                            modifier = Modifier.weight(1f)
                        )
                        FlatMosaicStat(
                            value = "69",
                            label = "Countries traveled",
                            accentColor = StudioOcean,
                            modifier = Modifier.weight(1f)
                        )
                        FlatMosaicStat(
                            value = "2–4 wks",
                            label = "MVP turnaround",
                            accentColor = StudioEmerald,
                            modifier = Modifier.weight(1f)
                        )
                        FlatMosaicStat(
                            value = "ex-G",
                            label = "Google engineer",
                            accentColor = StudioViolet,
                            modifier = Modifier.weight(1f)
                        )
                    }
                }
            }
        }
    }
}

/**
 * Featured Video Card with Flat Poster Design.
 */
@Composable
private fun FlatFeaturedVideoCard(
    video: HeroMosaicVideo,
    onClick: () -> Unit
) {
    Surface(
        shape = RoundedCornerShape(12.dp),
        color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.35f)),
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .clickable(onClick = onClick)
    ) {
        Column {
            // Video Thumbnail Poster Placeholder
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(140.dp)
                    .background(Color(0xFF1E212B)),
                contentAlignment = Alignment.Center
            ) {
                // Background Tech Grid Lines
                Canvas(modifier = Modifier.fillMaxSize()) {
                    val lineColor = Color.White.copy(alpha = 0.05f)
                    for (x in 0..(size.width.toInt()) step 40) {
                        drawLine(lineColor, Offset(x.toFloat(), 0f), Offset(x.toFloat(), size.height))
                    }
                    for (y in 0..(size.height.toInt()) step 40) {
                        drawLine(lineColor, Offset(0f, y.toFloat()), Offset(size.width, y.toFloat()))
                    }
                }

                // Centered Play Button
                Surface(
                    shape = CircleShape,
                    color = StudioCoral,
                    border = BorderStroke(2.dp, Color.White),
                    modifier = Modifier.size(46.dp)
                ) {
                    Box(contentAlignment = Alignment.Center) {
                        Icon(
                            Icons.Default.PlayArrow,
                            contentDescription = "Play Video",
                            modifier = Modifier.size(24.dp),
                            tint = Color.White
                        )
                    }
                }

                // Top Left: Featured Badge
                Box(
                    modifier = Modifier
                        .align(Alignment.TopStart)
                        .padding(8.dp)
                        .clip(RoundedCornerShape(4.dp))
                        .background(StudioCoral)
                        .padding(horizontal = 6.dp, vertical = 2.dp)
                ) {
                    Text(
                        "LATEST YOUTUBE",
                        fontSize = 8.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White
                    )
                }

                // Bottom Right: Duration Chip
                Box(
                    modifier = Modifier
                        .align(Alignment.BottomEnd)
                        .padding(8.dp)
                        .clip(RoundedCornerShape(4.dp))
                        .background(Color.Black.copy(alpha = 0.75f))
                        .padding(horizontal = 6.dp, vertical = 2.dp)
                ) {
                    Text(
                        video.duration,
                        fontSize = 9.sp,
                        fontWeight = FontWeight.Bold,
                        fontFamily = FontFamily.Monospace,
                        color = Color.White
                    )
                }
            }

            // Title & Meta
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(12.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = video.title,
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis
                    )
                    Spacer(Modifier.height(3.dp))
                    Text(
                        text = "${video.views} · Ex-Google Perspective",
                        fontSize = 10.sp,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
                Spacer(Modifier.width(8.dp))
                Icon(
                    Icons.AutoMirrored.Filled.ArrowForward,
                    contentDescription = null,
                    modifier = Modifier.size(16.dp),
                    tint = StudioCoral
                )
            }
        }
    }
}

/**
 * Social Audience Side Card (Instagram / TikTok).
 */
@Composable
private fun FlatSocialAudienceCard(
    network: String,
    handle: String,
    followers: String,
    metric: String,
    badgeColor: Color,
    icon: ImageVector,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Surface(
        shape = RoundedCornerShape(10.dp),
        color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.4f),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)),
        modifier = modifier
            .clip(RoundedCornerShape(10.dp))
            .clickable(onClick = onClick)
    ) {
        Column(modifier = Modifier.padding(10.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Box(
                    modifier = Modifier
                        .size(26.dp)
                        .clip(RoundedCornerShape(6.dp))
                        .background(badgeColor.copy(alpha = 0.15f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(icon, contentDescription = null, modifier = Modifier.size(14.dp), tint = badgeColor)
                }
                Text(
                    text = metric,
                    fontSize = 9.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = StudioEmerald
                )
            }

            Spacer(Modifier.height(8.dp))

            Text(
                text = followers,
                fontSize = 18.sp,
                fontWeight = FontWeight.ExtraBold,
                color = MaterialTheme.colorScheme.onSurface
            )

            Text(
                text = "$network $handle",
                fontSize = 9.sp,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}

/**
 * Mini Video Card for Horizontal Showcase Row.
 */
@Composable
private fun FlatMiniVideoCard(
    video: HeroMosaicVideo,
    onClick: () -> Unit
) {
    Surface(
        shape = RoundedCornerShape(10.dp),
        color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.4f),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)),
        modifier = Modifier
            .width(180.dp)
            .clip(RoundedCornerShape(10.dp))
            .clickable(onClick = onClick)
    ) {
        Column {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(80.dp)
                    .background(Color(0xFF1E212B)),
                contentAlignment = Alignment.Center
            ) {
                Surface(
                    shape = CircleShape,
                    color = Color.Black.copy(alpha = 0.6f),
                    modifier = Modifier.size(28.dp)
                ) {
                    Box(contentAlignment = Alignment.Center) {
                        Icon(
                            Icons.Default.PlayArrow,
                            contentDescription = null,
                            modifier = Modifier.size(16.dp),
                            tint = Color.White
                        )
                    }
                }
                Box(
                    modifier = Modifier
                        .align(Alignment.BottomEnd)
                        .padding(4.dp)
                        .clip(RoundedCornerShape(3.dp))
                        .background(Color.Black.copy(alpha = 0.8f))
                        .padding(horizontal = 4.dp, vertical = 1.dp)
                ) {
                    Text(
                        video.duration,
                        fontSize = 8.sp,
                        fontFamily = FontFamily.Monospace,
                        color = Color.White
                    )
                }
            }
            Column(modifier = Modifier.padding(8.dp)) {
                Text(
                    text = video.title,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = MaterialTheme.colorScheme.onSurface,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
                Spacer(Modifier.height(3.dp))
                Text(
                    text = video.views,
                    fontSize = 9.sp,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
    }
}

/**
 * Flat Mosaic Stat Quadrant Item.
 */
@Composable
private fun FlatMosaicStat(
    value: String,
    label: String,
    accentColor: Color,
    modifier: Modifier = Modifier
) {
    Surface(
        shape = RoundedCornerShape(6.dp),
        color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.35f),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.25f)),
        modifier = modifier
    ) {
        Column(modifier = Modifier.padding(6.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    Icons.Default.ArrowDropUp,
                    contentDescription = null,
                    modifier = Modifier.size(12.dp),
                    tint = accentColor
                )
                Text(
                    text = value,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.ExtraBold,
                    fontFamily = FontFamily.Monospace,
                    color = accentColor,
                    maxLines = 1
                )
            }
            Text(
                text = label,
                fontSize = 8.sp,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}

/**
 * Hand-drawn vector wave underline for the playful title emphasis.
 * Matches `d="M2 8 Q 20 2, 40 6 T 78 5"`.
 */
@Composable
fun HeroTextWave(
    color: Color = StudioCoral,
    modifier: Modifier = Modifier
) {
    Canvas(modifier = modifier) {
        val path = Path().apply {
            moveTo(2f, size.height * 0.75f)
            quadraticTo(
                size.width * 0.25f, size.height * 0.15f,
                size.width * 0.50f, size.height * 0.50f
            )
            quadraticTo(
                size.width * 0.75f, size.height * 0.85f,
                size.width * 0.98f, size.height * 0.40f
            )
        }
        drawPath(
            path = path,
            color = color,
            style = Stroke(width = 2.5.dp.toPx(), cap = StrokeCap.Round)
        )
    }
}

/**
 * Decorative Flat Nomad Trail Vector Canvas (representing 69 countries traveled).
 */
@Composable
fun HeroNomadTrailCanvas(modifier: Modifier = Modifier) {
    val strokeColor = MaterialTheme.colorScheme.outline.copy(alpha = 0.15f)
    val dotColor = StudioCoral.copy(alpha = 0.4f)
    Canvas(modifier = modifier) {
        val trail = Path().apply {
            moveTo(0f, size.height * 0.85f)
            cubicTo(
                size.width * 0.35f, size.height * 0.80f,
                size.width * 0.65f, size.height * 0.35f,
                size.width, size.height * 0.60f
            )
        }
        drawPath(
            path = trail,
            color = strokeColor,
            style = Stroke(width = 1.dp.toPx(), cap = StrokeCap.Round)
        )
        drawCircle(color = dotColor, radius = 3.dp.toPx(), center = Offset(size.width * 0.65f, size.height * 0.35f))
        drawCircle(color = StudioViolet.copy(alpha = 0.35f), radius = 2.5.dp.toPx(), center = Offset(size.width * 0.35f, size.height * 0.80f))
    }
}

