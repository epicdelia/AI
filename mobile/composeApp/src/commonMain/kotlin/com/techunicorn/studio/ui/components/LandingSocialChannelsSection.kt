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
import androidx.compose.material.icons.automirrored.filled.OpenInNew
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.ui.theme.*

/**
 * Advanced Kotlin Sealed Interface representing all supported Social Platforms.
 * Guarantees type-safety with zero unchecked platform strings.
 */
sealed interface SocialPlatform {
    val id: String
    val label: String
    val followersDisplay: String
    val handle: String
    val brandColor: Color
    val iconVector: ImageVector
    val channelUrl: String
    val summary: String

    data object YouTube : SocialPlatform {
        override val id: String = "youtube"
        override val label: String = "YouTube"
        override val followersDisplay: String = "22K"
        override val handle: String = "@Tech.Unicorn"
        override val brandColor: Color = StudioCoral
        override val iconVector: ImageVector = Icons.Default.PlayArrow
        override val channelUrl: String = "https://youtube.com/@Tech.Unicorn"
        override val summary: String = "Deep dives on engineering, AI and tech careers."
    }

    data object Instagram : SocialPlatform {
        override val id: String = "instagram"
        override val label: String = "Instagram"
        override val followersDisplay: String = "384K"
        override val handle: String = "@tech.unicorn"
        override val brandColor: Color = StudioViolet
        override val iconVector: ImageVector = Icons.Default.CameraAlt
        override val channelUrl: String = "https://instagram.com/tech.unicorn"
        override val summary: String = "60-second reels, nomadic setup tours & story updates."
    }

    data object TikTok : SocialPlatform {
        override val id: String = "tiktok"
        override val label: String = "TikTok"
        override val followersDisplay: String = "45K"
        override val handle: String = "@tech.unicorn"
        override val brandColor: Color = StudioOcean
        override val iconVector: ImageVector = Icons.Default.PlayCircle
        override val channelUrl: String = "https://tiktok.com/@tech.unicorn"
        override val summary: String = "Bite-sized AI tools, code breakdowns & career reality."
    }

    data object X : SocialPlatform {
        override val id: String = "twitter"
        override val label: String = "X"
        override val followersDisplay: String = "28K"
        override val handle: String = "@Tech_Unicorn"
        override val brandColor: Color = Color(0xFF64748B)
        override val iconVector: ImageVector = Icons.Default.AlternateEmail
        override val channelUrl: String = "https://x.com/tech_unicorn"
        override val summary: String = "Raw architecture thoughts, build-in-public logs & LLM evals."
    }

    data object Threads : SocialPlatform {
        override val id: String = "threads"
        override val label: String = "Threads"
        override val followersDisplay: String = "15K"
        override val handle: String = "@tech.unicorn"
        override val brandColor: Color = StudioAmber
        override val iconVector: ImageVector = Icons.Default.ChatBubble
        override val channelUrl: String = "https://threads.net/@tech.unicorn"
        override val summary: String = "Nomadic engineering reflections from 69 countries."
    }

    companion object {
        val all: List<SocialPlatform> = listOf(YouTube, Instagram, TikTok, X, Threads)
    }
}

/**
 * Social Content Item displayed inside the horizontal multi-platform track.
 */
data class SocialContentItem(
    val id: String,
    val platform: SocialPlatform,
    val tag: String,
    val title: String,
    val durationOrMetric: String,
    val views: String,
    val videoUrl: String
)

/**
 * Mobile Implementation of the Official Content & Social Channels Section:
 * (<section class="landing-section landing-yt-section" id="youtube">)
 *
 * Directives:
 * - Eyebrow: "02b: Content & Social Channels"
 * - Headline: "Watch, listen, and follow across platforms" with "platforms" in italics + StudioCoral
 * - Subtitle: "From deep dives on YouTube to 60-second reels on Instagram and raw engineering notes on X and Threads."
 * - Direct external channel link: "Visit YouTube ↗"
 * - 5 Platform Tabs: YouTube (22K), Instagram (384K), TikTok (45K), X (28K), Threads (15K)
 * - Horizontal Content Track with:
 *    - Official YouTube video cards (10 Brutal Truths, I am back at Google, Is OpenClaw Still Worth It, etc.)
 *    - Meta tags (Google & Career, Career, AI, Tech Market, Keynote, Women in Tech)
 *    - Follow card with subscriber count & channel visit trigger
 * - Live Feed Status Bar: "● 6 latest from YouTube · updated 39 min ago"
 * - Strict flat design: 0dp elevation, crisp 1dp borders, solid accent fills, zero futuristic or sci-fi glows
 */
@Composable
fun LandingSocialChannelsSection(
    onPlayContent: (SocialContentItem) -> Unit,
    onVisitChannel: (SocialPlatform) -> Unit,
    modifier: Modifier = Modifier
) {
    var selectedPlatform by remember { mutableStateOf<SocialPlatform>(SocialPlatform.YouTube) }
    val horizontalScrollState = rememberScrollState()

    // Full YouTube Content Items matching the official HTML
    val youtubeItems = remember {
        listOf(
            SocialContentItem(
                id = "5p3Mg4VEkbI",
                platform = SocialPlatform.YouTube,
                tag = "Google & Career",
                title = "10 Brutal Truths I Learned as a Google Software Engineer",
                durationOrMetric = "14:02",
                views = "482K views",
                videoUrl = "https://www.youtube.com/watch?v=5p3Mg4VEkbI"
            ),
            SocialContentItem(
                id = "DSXRUnBCYtQ",
                platform = SocialPlatform.YouTube,
                tag = "Career",
                title = "I am back at Google! (after I quit... here is why)",
                durationOrMetric = "11:45",
                views = "182K views",
                videoUrl = "https://www.youtube.com/watch?v=DSXRUnBCYtQ"
            ),
            SocialContentItem(
                id = "YJlJ12Txhkg",
                platform = SocialPlatform.YouTube,
                tag = "AI",
                title = "Is OpenClaw Still Worth It in 2026? (Ex-Google Engineer)",
                durationOrMetric = "09:18",
                views = "98K views",
                videoUrl = "https://www.youtube.com/watch?v=YJlJ12Txhkg"
            ),
            SocialContentItem(
                id = "5jYD7ZRMJ5I",
                platform = SocialPlatform.YouTube,
                tag = "Tech Market",
                title = "WTF Is Happening To The Job Market (Ex-Googler Perspective)",
                durationOrMetric = "16:30",
                views = "342K views",
                videoUrl = "https://www.youtube.com/watch?v=5jYD7ZRMJ5I"
            ),
            SocialContentItem(
                id = "iT0iE6GGuQk",
                platform = SocialPlatform.YouTube,
                tag = "Keynote",
                title = "How A.I. crosses my way as a Digital Nomad (Keynote Talk)",
                durationOrMetric = "28:10",
                views = "124K views",
                videoUrl = "https://www.youtube.com/watch?v=iT0iE6GGuQk"
            ),
            SocialContentItem(
                id = "z5lKZX9dB-4",
                platform = SocialPlatform.YouTube,
                tag = "Women in Tech",
                title = "On the Importance of Big Tech Clout, Especially as a Woman in Tech",
                durationOrMetric = "18:40",
                views = "215K views",
                videoUrl = "https://www.youtube.com/watch?v=z5lKZX9dB-4"
            )
        )
    }

    // Instagram Content Items
    val instagramItems = remember {
        listOf(
            SocialContentItem(
                id = "ig-1",
                platform = SocialPlatform.Instagram,
                tag = "Nomad Life",
                title = "How I prompt Claude for full-stack architectures in beach cafés",
                durationOrMetric = "0:58",
                views = "1.2M plays",
                videoUrl = "https://instagram.com/tech.unicorn"
            ),
            SocialContentItem(
                id = "ig-2",
                platform = SocialPlatform.Instagram,
                tag = "Coding Tips",
                title = "The difference between junior and senior AI developers in 2026",
                durationOrMetric = "0:45",
                views = "890K plays",
                videoUrl = "https://instagram.com/tech.unicorn"
            ),
            SocialContentItem(
                id = "ig-3",
                platform = SocialPlatform.Instagram,
                tag = "Vlog",
                title = "Day in the life: Coding and shipping client apps from Valencia",
                durationOrMetric = "1:00",
                views = "640K plays",
                videoUrl = "https://instagram.com/tech.unicorn"
            )
        )
    }

    // TikTok Content Items
    val tiktokItems = remember {
        listOf(
            SocialContentItem(
                id = "tt-1",
                platform = SocialPlatform.TikTok,
                tag = "Tech Reality",
                title = "Stop building wrapper apps that OpenAI can destroy next week",
                durationOrMetric = "0:35",
                views = "450K views",
                videoUrl = "https://tiktok.com/@tech.unicorn"
            ),
            SocialContentItem(
                id = "tt-2",
                platform = SocialPlatform.TikTok,
                tag = "Career",
                title = "How to get hired as a remote systems engineer without a CS degree",
                durationOrMetric = "0:52",
                views = "380K views",
                videoUrl = "https://tiktok.com/@tech.unicorn"
            )
        )
    }

    // X (Twitter) Content Items
    val xItems = remember {
        listOf(
            SocialContentItem(
                id = "x-1",
                platform = SocialPlatform.X,
                tag = "Thread",
                title = "Ship your MVP in 2 weeks. Private GitHub commits. No agency bloat.",
                durationOrMetric = "12 tweets",
                views = "145K views",
                videoUrl = "https://x.com/tech_unicorn"
            ),
            SocialContentItem(
                id = "x-2",
                platform = SocialPlatform.X,
                tag = "AI Architecture",
                title = "Why deterministic guardrails matter more than model parameter size.",
                durationOrMetric = "8 tweets",
                views = "88K views",
                videoUrl = "https://x.com/tech_unicorn"
            )
        )
    }

    // Threads Content Items
    val threadsItems = remember {
        listOf(
            SocialContentItem(
                id = "th-1",
                platform = SocialPlatform.Threads,
                tag = "Nomad Log",
                title = "69 countries. 1 laptop. Zero standing desks. Proof of work over aesthetics.",
                durationOrMetric = "Post",
                views = "42K reads",
                videoUrl = "https://threads.net/@tech.unicorn"
            )
        )
    }

    // Resolve current active items based on selected tab
    val currentItems = when (selectedPlatform) {
        SocialPlatform.YouTube -> youtubeItems
        SocialPlatform.Instagram -> instagramItems
        SocialPlatform.TikTok -> tiktokItems
        SocialPlatform.X -> xItems
        SocialPlatform.Threads -> threadsItems
    }

    Column(
        modifier = modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // ================= 1. SECTION HEADER =================
        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(
                text = "02b: CONTENT & SOCIAL CHANNELS",
                color = StudioCoral,
                fontWeight = FontWeight.Bold,
                fontSize = 11.sp,
                fontFamily = FontFamily.Monospace,
                letterSpacing = 1.sp
            )

            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = "Watch, listen, and follow across ",
                    style = MaterialTheme.typography.headlineMedium,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface
                )
            }
            Text(
                text = "platforms",
                style = MaterialTheme.typography.headlineMedium,
                fontWeight = FontWeight.ExtraBold,
                fontStyle = FontStyle.Italic,
                color = StudioCoral
            )

            Spacer(Modifier.height(2.dp))

            Text(
                text = "From deep dives on YouTube to 60-second reels on Instagram and raw engineering notes on X and Threads.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                lineHeight = 20.sp
            )

            Spacer(Modifier.height(4.dp))

            // Quick Visit Channel Trigger Pill
            Surface(
                shape = RoundedCornerShape(8.dp),
                color = selectedPlatform.brandColor.copy(alpha = 0.12f),
                border = BorderStroke(1.dp, selectedPlatform.brandColor.copy(alpha = 0.35f)),
                modifier = Modifier
                    .clip(RoundedCornerShape(8.dp))
                    .clickable { onVisitChannel(selectedPlatform) }
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Text(
                        text = "Visit ${selectedPlatform.label} (${selectedPlatform.handle})",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = selectedPlatform.brandColor
                    )
                    Icon(
                        Icons.AutoMirrored.Filled.OpenInNew,
                        contentDescription = null,
                        modifier = Modifier.size(13.dp),
                        tint = selectedPlatform.brandColor
                    )
                }
            }
        }

        // ================= 2. PLATFORM TABS ROW =================
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .horizontalScroll(rememberScrollState()),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            SocialPlatform.all.forEach { platform ->
                val isSelected = selectedPlatform == platform
                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = if (isSelected) platform.brandColor else MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f),
                    border = BorderStroke(
                        1.dp,
                        if (isSelected) platform.brandColor else MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)
                    ),
                    modifier = Modifier
                        .clip(RoundedCornerShape(8.dp))
                        .clickable { selectedPlatform = platform }
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Icon(
                            imageVector = platform.iconVector,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = if (isSelected) Color.White else platform.brandColor
                        )
                        Text(
                            text = platform.label,
                            fontSize = 12.sp,
                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                            color = if (isSelected) Color.White else MaterialTheme.colorScheme.onSurface
                        )
                        // Followers Badge
                        Surface(
                            shape = RoundedCornerShape(4.dp),
                            color = if (isSelected) Color.White.copy(alpha = 0.25f) else platform.brandColor.copy(alpha = 0.15f)
                        ) {
                            Text(
                                text = platform.followersDisplay,
                                fontSize = 9.sp,
                                fontWeight = FontWeight.Bold,
                                fontFamily = FontFamily.Monospace,
                                color = if (isSelected) Color.White else platform.brandColor,
                                modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                            )
                        }
                    }
                }
            }
        }

        // ================= 3. HORIZONTAL CONTENT TRACK =================
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .horizontalScroll(horizontalScrollState),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            // Content Cards for Active Platform
            currentItems.forEach { item ->
                FlatSocialContentCard(
                    item = item,
                    onClick = { onPlayContent(item) }
                )
            }

            // Trailing "Follow on Platform" Card
            FlatSocialFollowChannelCard(
                platform = selectedPlatform,
                onVisitChannel = { onVisitChannel(selectedPlatform) }
            )
        }

        // ================= 4. LIVE FEED STATUS BAR =================
        Surface(
            shape = RoundedCornerShape(6.dp),
            color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.35f),
            border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.2f)),
            modifier = Modifier.fillMaxWidth()
        ) {
            Row(
                modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
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
                            .background(StudioGoodLight)
                    )
                    Text(
                        text = "${currentItems.size} latest from ${selectedPlatform.label}",
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Medium,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                }
                Text(
                    text = "updated 39 min ago",
                    fontSize = 10.sp,
                    fontFamily = FontFamily.Monospace,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
    }
}

/**
 * Individual Flat Social Card for Video or Post.
 */
@Composable
private fun FlatSocialContentCard(
    item: SocialContentItem,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Surface(
        shape = RoundedCornerShape(12.dp),
        color = MaterialTheme.colorScheme.surface,
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.35f)),
        modifier = modifier
            .width(220.dp)
            .clip(RoundedCornerShape(12.dp))
            .clickable(onClick = onClick)
    ) {
        Column {
            // Thumbnail / Media Placeholder
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(115.dp)
                    .background(Color(0xFF1E212B)),
                contentAlignment = Alignment.Center
            ) {
                // Background subtle matrix lines
                Canvas(modifier = Modifier.fillMaxSize()) {
                    val lineColor = Color.White.copy(alpha = 0.04f)
                    for (x in 0..(size.width.toInt()) step 35) {
                        drawLine(lineColor, Offset(x.toFloat(), 0f), Offset(x.toFloat(), size.height))
                    }
                    for (y in 0..(size.height.toInt()) step 35) {
                        drawLine(lineColor, Offset(0f, y.toFloat()), Offset(size.width, y.toFloat()))
                    }
                }

                // Centered Play Button
                Surface(
                    shape = CircleShape,
                    color = item.platform.brandColor,
                    modifier = Modifier.size(38.dp)
                ) {
                    Box(contentAlignment = Alignment.Center) {
                        Icon(
                            imageVector = Icons.Default.PlayArrow,
                            contentDescription = "Play",
                            tint = Color.White,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                }

                // Top Left: Topic Meta Tag
                Box(
                    modifier = Modifier
                        .align(Alignment.TopStart)
                        .padding(8.dp)
                        .clip(RoundedCornerShape(4.dp))
                        .background(Color.Black.copy(alpha = 0.8f))
                        .padding(horizontal = 6.dp, vertical = 2.dp)
                ) {
                    Text(
                        text = item.tag,
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
                        .background(Color.Black.copy(alpha = 0.8f))
                        .padding(horizontal = 6.dp, vertical = 2.dp)
                ) {
                    Text(
                        text = item.durationOrMetric,
                        fontSize = 8.sp,
                        fontFamily = FontFamily.Monospace,
                        color = Color.White
                    )
                }
            }

            // Card Body: Title & Views
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(10.dp),
                verticalArrangement = Arrangement.spacedBy(4.dp)
            ) {
                Text(
                    text = item.title,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    text = item.views,
                    fontSize = 10.sp,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
    }
}

/**
 * Trailing Follow Card on the right of the social track.
 */
@Composable
private fun FlatSocialFollowChannelCard(
    platform: SocialPlatform,
    onVisitChannel: () -> Unit,
    modifier: Modifier = Modifier
) {
    Surface(
        shape = RoundedCornerShape(12.dp),
        color = platform.brandColor.copy(alpha = 0.08f),
        border = BorderStroke(1.dp, platform.brandColor.copy(alpha = 0.35f)),
        modifier = modifier
            .width(200.dp)
            .height(185.dp)
            .clip(RoundedCornerShape(12.dp))
            .clickable(onClick = onVisitChannel)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(14.dp),
            verticalArrangement = Arrangement.SpaceBetween
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Box(
                    modifier = Modifier
                        .size(36.dp)
                        .clip(RoundedCornerShape(8.dp))
                        .background(platform.brandColor.copy(alpha = 0.18f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = platform.iconVector,
                        contentDescription = null,
                        modifier = Modifier.size(20.dp),
                        tint = platform.brandColor
                    )
                }

                Text(
                    text = "More on ${platform.label}",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface
                )

                Text(
                    text = platform.summary,
                    fontSize = 10.sp,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    lineHeight = 14.sp,
                    maxLines = 3,
                    overflow = TextOverflow.Ellipsis
                )
            }

            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(
                    text = "${platform.followersDisplay} community",
                    fontSize = 10.sp,
                    fontFamily = FontFamily.Monospace,
                    fontWeight = FontWeight.Bold,
                    color = platform.brandColor
                )
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    Text(
                        text = "Visit ${platform.label}",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = platform.brandColor
                    )
                    Icon(
                        imageVector = Icons.AutoMirrored.Filled.ArrowForward,
                        contentDescription = null,
                        modifier = Modifier.size(12.dp),
                        tint = platform.brandColor
                    )
                }
            }
        }
    }
}

