package com.techunicorn.studio.ui.screens.social

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowUpward
import androidx.compose.material.icons.filled.BarChart
import androidx.compose.material.icons.filled.TrendingUp
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.ui.theme.StudioEmerald
import com.techunicorn.studio.ui.theme.StudioViolet

@Composable
fun AnalyticsScreen() {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .padding(16.dp)
    ) {
        // Title
        Text(
            "Audience Analytics",
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onBackground
        )
        Text(
            "Unified reach, cross-platform velocity & posting heatmaps",
            fontSize = 12.sp,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )

        Spacer(Modifier.height(14.dp))

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            // Hero Scoreboard
            item {
                Surface(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    color = MaterialTheme.colorScheme.surface,
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
                    tonalElevation = 2.dp
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text(
                            "TOTAL COMBINED REACH",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            letterSpacing = 1.sp
                        )
                        Spacer(Modifier.height(6.dp))
                        Row(verticalAlignment = Alignment.Bottom) {
                            Text(
                                "472,800",
                                fontSize = 32.sp,
                                fontWeight = FontWeight.ExtraBold,
                                color = MaterialTheme.colorScheme.onSurface
                            )
                            Spacer(Modifier.width(8.dp))
                            Row(
                                modifier = Modifier
                                    .padding(bottom = 6.dp)
                                    .clip(RoundedCornerShape(6.dp))
                                    .background(StudioEmerald.copy(alpha = 0.15f))
                                    .padding(horizontal = 6.dp, vertical = 2.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(Icons.Default.ArrowUpward, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(12.dp))
                                Spacer(Modifier.width(2.dp))
                                Text("+8.4% 30d", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = StudioEmerald)
                            }
                        }

                        Spacer(Modifier.height(14.dp))
                        Divider(color = MaterialTheme.colorScheme.outline)
                        Spacer(Modifier.height(14.dp))

                        // Grid Breakdown
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Column {
                                Text("YouTube", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                Text("210,000", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = MaterialTheme.colorScheme.onSurface)
                                Text("4.6% avg ctr", fontSize = 10.sp, color = StudioEmerald)
                            }
                            Column {
                                Text("LinkedIn", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                Text("145,200", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = MaterialTheme.colorScheme.onSurface)
                                Text("8.2% eng", fontSize = 10.sp, color = StudioEmerald)
                            }
                            Column {
                                Text("X / Twitter", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                Text("78,100", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = MaterialTheme.colorScheme.onSurface)
                                Text("1.2M imp", fontSize = 10.sp, color = StudioViolet)
                            }
                            Column {
                                Text("Instagram", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                Text("39,500", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = MaterialTheme.colorScheme.onSurface)
                                Text("+1.1K new", fontSize = 10.sp, color = StudioEmerald)
                            }
                        }
                    }
                }
            }

            // Best Posting Windows Heatmap Card
            item {
                Surface(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(14.dp),
                    color = MaterialTheme.colorScheme.surface,
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline)
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                "Best Posting Windows",
                                fontWeight = FontWeight.Bold,
                                fontSize = 14.sp,
                                color = MaterialTheme.colorScheme.onSurface
                            )
                            Text(
                                "Madrid/Europe (CET)",
                                fontSize = 10.sp,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }

                        Spacer(Modifier.height(10.dp))

                        listOf(
                            Triple("LinkedIn", "Tuesday & Thursday · 08:30 – 10:00", "94% peak engagement"),
                            Triple("YouTube", "Thursday · 17:00 – 19:00", "4.2x view velocity first 3h"),
                            Triple("X / Twitter", "Monday & Wednesday · 14:00 – 16:00", "880 avg retweets"),
                            Triple("TikTok", "Sunday · 20:00 – 22:00", "High algorithm multiplier")
                        ).forEach { (plat, window, metric) ->
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(vertical = 6.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column {
                                    Text(plat, fontWeight = FontWeight.Bold, fontSize = 12.sp, color = MaterialTheme.colorScheme.primary)
                                    Text(window, fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                                Surface(
                                    shape = RoundedCornerShape(6.dp),
                                    color = MaterialTheme.colorScheme.primaryContainer
                                ) {
                                    Text(
                                        metric,
                                        fontSize = 10.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = MaterialTheme.colorScheme.primary,
                                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                    )
                                }
                            }
                        }
                    }
                }
            }

            // Top Performing Content Leaderboard
            item {
                Surface(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(14.dp),
                    color = MaterialTheme.colorScheme.surface,
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline)
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        Text(
                            "Top Performing Content (30 Days)",
                            fontWeight = FontWeight.Bold,
                            fontSize = 14.sp,
                            color = MaterialTheme.colorScheme.onSurface
                        )

                        Spacer(Modifier.height(10.dp))

                        listOf(
                            Triple("Why Next.js 15 Changed Everything", "YouTube · 284K views", "4.37x median"),
                            Triple("Autonomous AI swarms in Kotlin Multiplatform", "LinkedIn · 68K impressions", "1,240 reactions"),
                            Triple("48 Hours of continuous vibecoding breakdown", "X Thread · 340K views", "3,100 bookmarks")
                        ).forEachIndexed { i, (title, meta, badge) ->
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(vertical = 6.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text(
                                    "#${i + 1}",
                                    fontWeight = FontWeight.ExtraBold,
                                    fontSize = 14.sp,
                                    color = MaterialTheme.colorScheme.primary,
                                    modifier = Modifier.width(28.dp)
                                )
                                Column(modifier = Modifier.weight(1f)) {
                                    Text(title, fontWeight = FontWeight.SemiBold, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurface)
                                    Text(meta, fontSize = 10.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                                Surface(
                                    shape = RoundedCornerShape(6.dp),
                                    color = StudioEmerald.copy(alpha = 0.15f)
                                ) {
                                    Text(
                                        badge,
                                        fontSize = 10.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = StudioEmerald,
                                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
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
