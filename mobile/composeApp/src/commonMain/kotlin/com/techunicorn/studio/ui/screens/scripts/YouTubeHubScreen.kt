package com.techunicorn.studio.ui.screens.scripts

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.scrollBy
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.lint.AiTellsLinter
import com.techunicorn.studio.domain.lint.LintSeverity
import com.techunicorn.studio.domain.models.Breakout
import com.techunicorn.studio.domain.models.Idea
import com.techunicorn.studio.domain.models.Script
import com.techunicorn.studio.ui.theme.*
import kotlinx.coroutines.delay

@Composable
fun YouTubeHubScreen(
    breakouts: List<Breakout>,
    ideas: List<Idea>,
    scripts: List<Script>,
    onOpenCopilot: () -> Unit
) {
    var selectedTab by remember { mutableStateOf(0) } // 0: Radar, 1: Ideas, 2: Scripts
    var activeTeleprompterScript by remember { mutableStateOf<Script?>(null) }
    
    // Mutable lists so actions immediately reflect in the UI
    var localIdeas by remember { mutableStateOf(ideas) }
    var localScripts by remember { mutableStateOf(scripts) }
    var selectedBreakoutForAngle by remember { mutableStateOf<Breakout?>(null) }

    // If Teleprompter is active, render Camera Prompter
    if (activeTeleprompterScript != null) {
        CameraPrompterScreen(
            script = activeTeleprompterScript!!,
            onClose = { activeTeleprompterScript = null },
            onTakeFinished = {
                // Advance script status to filmed
                localScripts = localScripts.map { s ->
                    if (s.id == activeTeleprompterScript!!.id) s.copy(updatedAt = "Filmed just now") else s
                }
                activeTeleprompterScript = null
            }
        )
        return
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
    ) {
        // Tab Row
        TabRow(
            selectedTabIndex = selectedTab,
            containerColor = MaterialTheme.colorScheme.surface,
            contentColor = StudioCoral
        ) {
            Tab(selected = selectedTab == 0, onClick = { selectedTab = 0 }, text = { Text("Radar") })
            Tab(selected = selectedTab == 1, onClick = { selectedTab = 1 }, text = { Text("Ideas (${localIdeas.size})") })
            Tab(selected = selectedTab == 2, onClick = { selectedTab = 2 }, text = { Text("Scripts (${localScripts.size})") })
        }

        when (selectedTab) {
            0 -> BreakoutsTab(
                breakouts = breakouts,
                onSpinAngle = { selectedBreakoutForAngle = it }
            )
            1 -> IdeasTab(
                ideas = localIdeas,
                onGreenlight = { ideaId ->
                    localIdeas = localIdeas.map { if (it.id == ideaId) it.copy(status = "greenlit") else it }
                },
                onArchive = { ideaId ->
                    localIdeas = localIdeas.filter { it.id != ideaId }
                },
                onCreateScript = { idea ->
                    val newScript = Script(
                        id = kotlinx.datetime.Clock.System.now().toEpochMilliseconds(),
                        title = idea.title,
                        hook = idea.hook,
                        body = "## Cold open (00:00 - 00:30)\n[VISUAL: Direct to camera, high energy]\n${idea.hook}\n\n## The Breakdown (00:30 - 04:00)\n[B-ROLL: Terminal screen recording]\nHere is the exact production architecture...\n\n## The Reality Check (04:00 - 07:30)\n[VISUAL: Code diff on monitor]\nWhere typical advice breaks down in production.\n\n## Summary & Call To Action\n[VISUAL: GitHub repository link]\nStar the repo and subscribe for more real engineering deep-dives.",
                        wordCount = 920,
                        estimatedDurationMinutes = 6.2,
                        updatedAt = "Just drafted"
                    )
                    localScripts = listOf(newScript) + localScripts
                    selectedTab = 2
                }
            )
            2 -> ScriptsTab(
                scripts = localScripts,
                onLaunchTeleprompter = { activeTeleprompterScript = it },
                onHumanizeScript = { scriptId ->
                    localScripts = localScripts.map { s ->
                        if (s.id == scriptId) {
                            val humanizedBody = AiTellsLinter.humanize(s.body)
                            s.copy(body = humanizedBody)
                        } else s
                    }
                }
            )
        }
    }

    // Contrarian Angle Spinner Dialog
    if (selectedBreakoutForAngle != null) {
        val breakout = selectedBreakoutForAngle!!
        ContrarianAngleDialog(
            breakout = breakout,
            onDismiss = { selectedBreakoutForAngle = null },
            onSelectAngle = { title, angle, hook ->
                val newIdea = Idea(
                    id = kotlinx.datetime.Clock.System.now().toEpochMilliseconds(),
                    title = title,
                    angle = angle,
                    hook = hook,
                    status = "greenlit",
                    estimatedViews = (breakout.channelMedianViews * 2.5).toLong()
                )
                localIdeas = listOf(newIdea) + localIdeas
                selectedBreakoutForAngle = null
                selectedTab = 1
            }
        )
    }
}

@Composable
fun BreakoutsTab(
    breakouts: List<Breakout>,
    onSpinAngle: (Breakout) -> Unit
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        item {
            Text("Breakout Radar", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            Text("Videos beating channel medians by ≥3x multiplier", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Spacer(Modifier.height(4.dp))
        }

        items(breakouts) { breakout ->
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(Modifier.padding(14.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(breakout.channelTitle, fontWeight = FontWeight.SemiBold, fontSize = 13.sp, color = StudioViolet)
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(6.dp))
                                .background(MultiplierBadgeBg)
                                .padding(horizontal = 8.dp, vertical = 4.dp)
                        ) {
                            Text("${breakout.multiplier}x Median", color = MultiplierGreen, fontWeight = FontWeight.Bold, fontSize = 11.sp)
                        }
                    }
                    Spacer(Modifier.height(6.dp))
                    Text(breakout.videoTitle, fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
                    Spacer(Modifier.height(8.dp))
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text("${breakout.views / 1000}K views (median: ${breakout.channelMedianViews / 1000}K)", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text(breakout.publishedAt, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }

                    Spacer(Modifier.height(12.dp))

                    // High-ROI Fast Track Button
                    Button(
                        onClick = { onSpinAngle(breakout) },
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.buttonColors(containerColor = StudioViolet),
                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp)
                    ) {
                        Icon(Icons.Default.Bolt, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(6.dp))
                        Text("⚡ Spin Contrarian Angle (15m Draft)", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
    }
}

@Composable
fun ContrarianAngleDialog(
    breakout: Breakout,
    onDismiss: () -> Unit,
    onSelectAngle: (title: String, angle: String, hook: String) -> Unit
) {
    val angles = remember(breakout) {
        listOf(
            Triple(
                "Why Everyone is Wrong About ${breakout.videoTitle.take(28)}...",
                "The Contrarian Reality Check: Ex-Google engineering teardown of popular misconceptions.",
                "Everyone is following the same tutorial advice for ${breakout.videoTitle.take(24)}. But in production at Google, doing this caused our biggest outage."
            ),
            Triple(
                "I Built It in 48h with Zero Frameworks",
                "Pure Systems Rigor: Replacing heavy frameworks with clean, native Kotlin and SQLite.",
                "Framework bloat is slowing teams down. Here is how I built the entire ${breakout.channelTitle} architecture with zero external wrappers."
            ),
            Triple(
                "What the ${breakout.channelTitle} Tutorial Didn't Show You",
                "Production Stress Test: Live telemetry, latency benchmarks, and hidden API costs.",
                "The tutorial looked flawless, but what happens when you hit 10,000 requests/sec? I ran the real stress benchmarks."
            )
        )
    }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Default.AutoFixHigh, contentDescription = null, tint = StudioViolet, modifier = Modifier.size(24.dp))
                Spacer(Modifier.width(8.dp))
                Text("Contrarian Angle Matrix", fontWeight = FontWeight.Bold, fontSize = 18.sp)
            }
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(
                    "Breakout: \"${breakout.videoTitle}\" (${breakout.multiplier}x views)",
                    fontSize = 12.sp,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                Text("Pick a high-retention angle tailored to your ex-Google engineering voice:", fontSize = 12.sp, fontWeight = FontWeight.SemiBold)

                for ((idx, item) in angles.withIndex()) {
                    val (title, angle, hook) = item
                    Surface(
                        shape = RoundedCornerShape(10.dp),
                        color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.6f),
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { onSelectAngle(title, angle, hook) }
                    ) {
                        Column(Modifier.padding(12.dp)) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Box(
                                    modifier = Modifier
                                        .size(20.dp)
                                        .clip(CircleShape)
                                        .background(StudioViolet),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Text("${idx + 1}", color = Color.White, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                }
                                Spacer(Modifier.width(8.dp))
                                Text(title, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                            }
                            Spacer(Modifier.height(4.dp))
                            Text(angle, fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            Spacer(Modifier.height(4.dp))
                            Text("\"$hook\"", fontSize = 11.sp, fontStyle = androidx.compose.ui.text.font.FontStyle.Italic)
                        }
                    }
                }
            }
        },
        confirmButton = {},
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Cancel") }
        }
    )
}

@Composable
fun IdeasTab(
    ideas: List<Idea>,
    onGreenlight: (Long) -> Unit,
    onArchive: (Long) -> Unit,
    onCreateScript: (Idea) -> Unit
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text("Idea Deck", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                    Text("Greenlight to draft a high-retention script", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }

        items(ideas) { idea ->
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(Modifier.padding(14.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(idea.title, fontWeight = FontWeight.Bold, fontSize = 15.sp, modifier = Modifier.weight(1f))
                        if (idea.status == "greenlit") {
                            Box(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(6.dp))
                                    .background(MultiplierBadgeBg)
                                    .padding(horizontal = 6.dp, vertical = 2.dp)
                            ) {
                                Text("GREENLIT", color = MultiplierGreen, fontWeight = FontWeight.Bold, fontSize = 10.sp)
                            }
                        }
                    }
                    Spacer(Modifier.height(6.dp))
                    Text("Hook: \"${idea.hook}\"", fontSize = 13.sp, fontStyle = androidx.compose.ui.text.font.FontStyle.Italic)
                    Spacer(Modifier.height(6.dp))
                    Text("Angle: ${idea.angle}", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)

                    Spacer(Modifier.height(10.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        if (idea.status != "greenlit") {
                            Button(
                                onClick = { onGreenlight(idea.id) },
                                colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                                modifier = Modifier.weight(1f),
                                contentPadding = PaddingValues(vertical = 6.dp)
                            ) {
                                Text("Greenlight", fontSize = 12.sp)
                            }
                        } else {
                            Button(
                                onClick = { onCreateScript(idea) },
                                colors = ButtonDefaults.buttonColors(containerColor = StudioEmerald),
                                modifier = Modifier.weight(1f),
                                contentPadding = PaddingValues(vertical = 6.dp)
                            ) {
                                Icon(Icons.Default.Edit, contentDescription = null, modifier = Modifier.size(14.dp))
                                Spacer(Modifier.width(4.dp))
                                Text("Draft Script", fontSize = 12.sp)
                            }
                        }
                        OutlinedButton(
                            onClick = { onArchive(idea.id) },
                            modifier = Modifier.weight(0.7f),
                            contentPadding = PaddingValues(vertical = 6.dp)
                        ) {
                            Text("Archive", fontSize = 12.sp)
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun ScriptsTab(
    scripts: List<Script>,
    onLaunchTeleprompter: (Script) -> Unit,
    onHumanizeScript: (Long) -> Unit
) {
    var selectedScriptForHandoff by remember { mutableStateOf<Script?>(null) }
    var expandedLintScriptId by remember { mutableStateOf<Long?>(null) }

    LazyColumn(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        items(scripts) { script ->
            val lintReport = remember(script.body) { AiTellsLinter.lint(script.body) }
            val isLintExpanded = expandedLintScriptId == script.id

            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(14.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Column(Modifier.padding(16.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            script.title,
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.Bold,
                            modifier = Modifier.weight(1f)
                        )
                        // Status badge
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(6.dp))
                                .background(StudioCoral.copy(alpha = 0.15f))
                                .padding(horizontal = 8.dp, vertical = 4.dp)
                        ) {
                            Text(script.updatedAt.ifBlank { "Draft" }, color = StudioCoral, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        }
                    }

                    Text("Spoken pacing: ${lintReport.spokenMinutes.let { (it * 10).toInt() / 10.0 }} mins · ${lintReport.wordCount} words", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp)

                    Spacer(Modifier.height(10.dp))

                    // Hook Banner
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(8.dp))
                            .background(StudioViolet.copy(alpha = 0.1f))
                            .padding(10.dp)
                    ) {
                        Text("Hook: ${script.hook}", fontSize = 13.sp)
                    }

                    Spacer(Modifier.height(12.dp))

                    // Pillar 2: Native Deterministic AI-Tell Linter Bar
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = when {
                            lintReport.hardCount > 0 -> StudioCoral.copy(alpha = 0.12f)
                            lintReport.warnCount > 0 -> StudioAmber.copy(alpha = 0.12f)
                            else -> StudioEmerald.copy(alpha = 0.12f)
                        },
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { expandedLintScriptId = if (isLintExpanded) null else script.id }
                    ) {
                        Row(
                            modifier = Modifier.padding(10.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(
                                    imageVector = when {
                                        lintReport.hardCount > 0 -> Icons.Default.Cancel
                                        lintReport.warnCount > 0 -> Icons.Default.Warning
                                        else -> Icons.Default.CheckCircle
                                    },
                                    contentDescription = null,
                                    tint = when {
                                        lintReport.hardCount > 0 -> StudioCoral
                                        lintReport.warnCount > 0 -> StudioAmber
                                        else -> StudioEmerald
                                    },
                                    modifier = Modifier.size(18.dp)
                                )
                                Spacer(Modifier.width(8.dp))
                                Text(
                                    text = when {
                                        lintReport.hardCount > 0 -> "AI-TELL LINTER: ${lintReport.hardCount} Hard Tells, ${lintReport.warnCount} Warnings"
                                        lintReport.warnCount > 0 -> "AI-TELL LINTER: ${lintReport.warnCount} Warnings"
                                        else -> "AI-TELL LINTER: Clean (0 synthetic tells)"
                                    },
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 11.sp,
                                    color = when {
                                        lintReport.hardCount > 0 -> StudioCoral
                                        lintReport.warnCount > 0 -> StudioAmber
                                        else -> StudioEmerald
                                    }
                                )
                            }
                            Icon(
                                imageVector = if (isLintExpanded) Icons.Default.ExpandLess else Icons.Default.ExpandMore,
                                contentDescription = null,
                                tint = MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.size(16.dp)
                            )
                        }
                    }

                    // Expanded Findings Drawer & Auto-Humanize Button
                    if (isLintExpanded && lintReport.findings.isNotEmpty()) {
                        Spacer(Modifier.height(8.dp))
                        Column(
                            verticalArrangement = Arrangement.spacedBy(4.dp),
                            modifier = Modifier
                                .fillMaxWidth()
                                .background(MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.4f), RoundedCornerShape(8.dp))
                                .padding(10.dp)
                        ) {
                            lintReport.findings.take(8).forEach { finding ->
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    Text(
                                        "${if (finding.line > 0) "Line ${finding.line}: " else ""}${finding.rule} ('${finding.match}')",
                                        fontSize = 11.sp,
                                        color = if (finding.severity == LintSeverity.HARD) StudioCoral else StudioAmber
                                    )
                                }
                            }
                            Spacer(Modifier.height(6.dp))
                            Button(
                                onClick = { onHumanizeScript(script.id) },
                                colors = ButtonDefaults.buttonColors(containerColor = StudioViolet),
                                modifier = Modifier.fillMaxWidth(),
                                contentPadding = PaddingValues(vertical = 6.dp)
                            ) {
                                Icon(Icons.Default.AutoFixHigh, contentDescription = null, modifier = Modifier.size(14.dp))
                                Spacer(Modifier.width(6.dp))
                                Text("🪄 Auto-Humanize Script (Purge AI Tells)", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                            }
                        }
                    }

                    Spacer(Modifier.height(14.dp))

                    // Action Buttons Row: Teleprompter & Handoff Pack
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Button(
                            onClick = { onLaunchTeleprompter(script) },
                            modifier = Modifier.weight(1.2f),
                            colors = ButtonDefaults.buttonColors(containerColor = StudioCoral)
                        ) {
                            Icon(Icons.Default.Videocam, contentDescription = null, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Film 4K Prompter", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                        }

                        OutlinedButton(
                            onClick = { selectedScriptForHandoff = script },
                            modifier = Modifier.weight(1f)
                        ) {
                            Icon(Icons.Default.FolderZip, contentDescription = null, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(4.dp))
                            Text("Handoff Pack", fontSize = 12.sp)
                        }
                    }
                }
            }
        }
    }

    // Pillar 3: 1-Click Video Editor Handoff Pack Dialog
    if (selectedScriptForHandoff != null) {
        val script = selectedScriptForHandoff!!
        EditorHandoffDialog(
            script = script,
            onDismiss = { selectedScriptForHandoff = null }
        )
    }
}

@Composable
fun EditorHandoffDialog(
    script: Script,
    onDismiss: () -> Unit
) {
    val clipboard = LocalClipboardManager.current
    var copiedNotice by remember { mutableStateOf(false) }

    val slug = remember(script.title) {
        script.title.lowercase().replace(Regex("[^a-z0-9]+"), "-").trim('-')
    }
    val folderPath = "YouTube/2026-11-08_$slug/"
    val roughDue = "Nov 03, 2026 (48h)"
    val finalDue = "Nov 06, 2026"

    val briefMarkdown = remember(script) {
        """
# Edit brief: ${script.title}

**Publish date:** Sunday, Nov 08, 2026
**Folder:** $folderPath
**Target length:** ${(script.wordCount / 150.0).let { (it * 10).toInt() / 10.0 }} min (hard ceiling 12 min)
**Rough cut due:** $roughDue  **Final due:** $finalDue

## The one-sentence promise
${script.hook}

## Shot list
The `[VISUAL]` and `[B-ROLL]` notes extracted from `01_script/final_script.md`:
- [00:00]: A-Roll talking head direct to camera (4K). Cut silence >1.2s.
- [00:30]: Zoom-in cut on punchline. Screen recording of live terminal.
- [02:15]: B-Roll overlay with syntax code card.

## Deliverables & SLA (Strict 2-round review limit)
- [ ] Rough cut v1 (structure and pacing only, zero polish) due $roughDue
- [ ] Polished v2 (graphics, zooms, sound, captions) due $finalDue
- [ ] Final 4K export in `05_final/`
- [ ] Captions .srt
- [ ] 3 vertical Shorts (9:16) with burned-in dynamic captions
        """.trimIndent()
    }

    AlertDialog(
        onDismissRequest = onDismiss,
        icon = { Icon(Icons.Default.DriveFolderUpload, contentDescription = null, tint = StudioCoral, modifier = Modifier.size(36.dp)) },
        title = { Text("1-Click Editor Handoff Pack", fontWeight = FontWeight.Bold) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = MaterialTheme.colorScheme.surfaceVariant,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(Modifier.padding(10.dp)) {
                        Text("Google Drive Scaffolding:", fontWeight = FontWeight.Bold, fontSize = 11.sp, color = StudioViolet)
                        Text(folderPath, fontWeight = FontWeight.Bold, fontSize = 12.sp)
                        Text("📁 01_script/  📁 02_raw/  📁 03_assets/", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text("📁 04_edits/   📁 05_final/ 📁 06_thumbnail/", fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }

                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = StudioCoral.copy(alpha = 0.1f),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text(
                        "⚡ Turnaround SLA: Rough cut in 48h. Max 2 revision rounds per editing/workflow.md.",
                        fontSize = 11.sp,
                        color = StudioCoral,
                        fontWeight = FontWeight.SemiBold,
                        modifier = Modifier.padding(8.dp)
                    )
                }

                Text("Edit Brief Preview (`edit_brief.md`):", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(140.dp)
                        .background(MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f), RoundedCornerShape(6.dp))
                        .padding(8.dp)
                        .verticalScroll(rememberScrollState())
                ) {
                    Text(briefMarkdown, fontSize = 10.sp, fontFamily = FontFamily.Monospace)
                }
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    clipboard.setText(AnnotatedString(briefMarkdown))
                    copiedNotice = true
                },
                colors = ButtonDefaults.buttonColors(containerColor = StudioCoral)
            ) {
                Icon(Icons.Default.ContentCopy, contentDescription = null, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(6.dp))
                Text(if (copiedNotice) "Copied Brief!" else "Copy Edit Brief")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Close") }
        }
    )
}
