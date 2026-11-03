package com.techunicorn.studio.ui.screens.live

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Tab
import androidx.compose.material3.TabRow
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import com.techunicorn.studio.data.live.CourseCard
import com.techunicorn.studio.data.live.CourseDetail
import com.techunicorn.studio.data.live.EventList
import com.techunicorn.studio.data.live.LiveApi
import com.techunicorn.studio.data.live.LiveResult
import com.techunicorn.studio.data.live.NeedsYou
import com.techunicorn.studio.data.live.RequestDetail
import com.techunicorn.studio.data.live.RequestSummary
import com.techunicorn.studio.data.live.TeamOverview
import kotlinx.coroutines.launch

// Screens on real Creator Studio data for each role (her, team, clients, members). Each one
// asks to sign in to the server first; nothing here is sample data.

@Composable
private fun Message(text: String, isError: Boolean = false) {
    Text(
        text,
        modifier = Modifier.padding(16.dp),
        color = if (isError) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
        style = MaterialTheme.typography.bodyMedium,
    )
}

@Composable
private fun SectionCard(content: @Composable () -> Unit) {
    Card(
        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 6.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
    ) { Column(Modifier.padding(14.dp)) { content() } }
}

/** Shows [content] once signed in to the server, else the sign-in form. */
@Composable
fun SignedIn(api: LiveApi, content: @Composable () -> Unit) {
    var signedIn by remember { mutableStateOf(api.isSignedIn) }
    if (signedIn) content() else LiveSignIn(api) { signedIn = true }
}

@Composable
fun LiveSignIn(api: LiveApi, onSignedIn: () -> Unit) {
    val scope = rememberCoroutineScope()
    var server by remember { mutableStateOf(api.serverUrl) }
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var code by remember { mutableStateOf("") }
    var needsCode by remember { mutableStateOf(false) }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    Column(Modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text("Sign in to your studio", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
        Text("Use the same email and password as on the website.", style = MaterialTheme.typography.bodyMedium)
        OutlinedTextField(server, { server = it }, label = { Text("Server address") }, singleLine = true, modifier = Modifier.fillMaxWidth(),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri))
        OutlinedTextField(email, { email = it }, label = { Text("Email") }, singleLine = true, modifier = Modifier.fillMaxWidth(),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email))
        OutlinedTextField(password, { password = it }, label = { Text("Password") }, singleLine = true, modifier = Modifier.fillMaxWidth(),
            visualTransformation = PasswordVisualTransformation(), keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password))
        if (needsCode) {
            OutlinedTextField(code, { code = it }, label = { Text("6-digit code from your authenticator") }, singleLine = true, modifier = Modifier.fillMaxWidth(),
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number))
        }
        error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
        Button(
            enabled = !busy && email.isNotBlank() && password.isNotBlank(),
            modifier = Modifier.fillMaxWidth(),
            onClick = {
                busy = true
                error = null
                scope.launch {
                    api.setServer(server)
                    when (val r = api.signIn(email.trim(), password, code.takeIf { needsCode })) {
                        is LiveResult.Ok -> if (r.value.token != null) onSignedIn() else if (r.value.twoFactor) needsCode = true
                        is LiveResult.Err -> error = r.message
                    }
                    busy = false
                }
            },
        ) { Text(if (busy) "Signing in…" else "Sign in") }
    }
}

// =============================================================================================
// Her: Needs you
// =============================================================================================

@Composable
fun NeedsYouLiveScreen(api: LiveApi) = SignedIn(api) {
    var data by remember { mutableStateOf<NeedsYou?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var reload by remember { mutableIntStateOf(0) }
    var sales by remember { mutableStateOf<Pair<com.techunicorn.studio.data.live.ShopTotals, com.techunicorn.studio.data.live.AffiliateTotals>?>(null) }
    LaunchedEffect(reload) {
        when (val r = api.needsYou()) {
            is LiveResult.Ok -> { data = r.value; error = null }
            is LiveResult.Err -> error = if (r.status == 403) "Only the studio owner sees this." else r.message
        }
        val shop = (api.shopTotals() as? LiveResult.Ok)?.value?.totals
        val aff = (api.affiliateTotals() as? LiveResult.Ok)?.value?.totals
        if (shop != null && aff != null) sales = shop to aff
    }
    LazyColumn(Modifier.fillMaxSize()) {
        item {
            Row(Modifier.fillMaxWidth().padding(16.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text("Needs you" + (data?.let { " (${it.total})" } ?: ""), style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
                TextButton(onClick = { reload++ }) { Text("Refresh") }
            }
        }
        error?.let { item { Message(it, true) } }
        sales?.let { (shop, aff) ->
            item {
                SectionCard {
                    Text("Sales (30 days)", fontWeight = FontWeight.SemiBold)
                    Text("Shop: ${shop.orders30d} order(s), ${shop.revenue30d}", style = MaterialTheme.typography.bodyMedium)
                    Text("Affiliate: ${aff.earned30d} earned, ${aff.clicks30d} clicks" + if (aff.broken > 0) " · ${aff.broken} broken link(s)" else "",
                        style = MaterialTheme.typography.bodyMedium, color = if (aff.broken > 0) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurface)
                }
            }
        }
        val d = data
        if (d == null && error == null) item { Box(Modifier.fillMaxWidth().padding(32.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator() } }
        if (d != null && d.groups.isEmpty()) item { Message("All caught up: nothing is waiting on you.") }
        d?.groups?.forEach { g ->
            item {
                SectionCard {
                    Text("${g.title} (${g.count})", fontWeight = FontWeight.SemiBold)
                    g.items.forEach { i ->
                        Spacer(Modifier.height(6.dp))
                        Text(i.title, style = MaterialTheme.typography.bodyMedium)
                        i.meta?.let { Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
                    }
                }
            }
        }
    }
}

// =============================================================================================
// Team: my tasks with a timer
// =============================================================================================

@Composable
fun MyTasksLiveScreen(api: LiveApi) = SignedIn(api) {
    val scope = rememberCoroutineScope()
    var data by remember { mutableStateOf<TeamOverview?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var reload by remember { mutableIntStateOf(0) }
    LaunchedEffect(reload) {
        when (val r = api.team()) {
            is LiveResult.Ok -> { data = r.value; error = null }
            is LiveResult.Err -> error = if (r.status == 403) "Tasks are for the studio team." else r.message
        }
    }
    val mine = data?.tasks?.filter { it.mine && !it.completed }.orEmpty()
    LazyColumn(Modifier.fillMaxSize()) {
        item {
            Row(Modifier.fillMaxWidth().padding(16.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text("My tasks (${mine.size})", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
                TextButton(onClick = { reload++ }) { Text("Refresh") }
            }
        }
        error?.let { item { Message(it, true) } }
        if (data != null && mine.isEmpty()) item { Message("Nothing assigned to you right now.") }
        items(mine, key = { it.id }) { t ->
            SectionCard {
                Text(t.title, fontWeight = FontWeight.SemiBold)
                Text(
                    listOfNotNull(t.stage, t.dueOn?.let { "due $it" }, if (t.overdue) "OVERDUE" else null, "${t.minutesLogged / 60}h ${t.minutesLogged % 60}m logged").joinToString(" · "),
                    style = MaterialTheme.typography.bodySmall,
                    color = if (t.overdue) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
                )
                t.brief?.takeIf { it.isNotBlank() }?.let { Text(it.take(400), style = MaterialTheme.typography.bodySmall, modifier = Modifier.padding(top = 6.dp)) }
                Row(Modifier.padding(top = 8.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Button(onClick = { scope.launch { api.toggleTimer(t.id); reload++ } }) { Text(if (t.timerStartedAt != null) "Stop timer" else "Start timer") }
                    OutlinedButton(onClick = { scope.launch { api.logMinutes(t.id, 15); reload++ } }) { Text("+15 min") }
                    OutlinedButton(onClick = { scope.launch { api.updateTask(t.id, completed = true); reload++ } }) { Text("Done") }
                }
            }
        }
    }
}

// =============================================================================================
// Clients: my requests (review, onboarding, payment, rights)
// =============================================================================================

@Composable
fun MyRequestsLiveScreen(api: LiveApi) = SignedIn(api) {
    var list by remember { mutableStateOf<List<RequestSummary>?>(null) }
    var open by remember { mutableStateOf<Long?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    LaunchedEffect(open) {
        if (open == null) {
            when (val r = api.requests()) {
                is LiveResult.Ok -> { list = r.value.requests; error = null }
                is LiveResult.Err -> error = if (r.status == 403) "Requests are for client accounts (with a confirmed email)." else r.message
            }
        }
    }
    val id = open
    if (id != null) {
        RequestDetailView(api, id) { open = null }
        return@SignedIn
    }
    LazyColumn(Modifier.fillMaxSize()) {
        item { Text("My requests", modifier = Modifier.padding(16.dp), style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold) }
        error?.let { item { Message(it, true) } }
        if (list?.isEmpty() == true) item { Message("No requests yet. Start one on the website.") }
        items(list.orEmpty(), key = { it.id }) { r ->
            Card(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 6.dp).clickable { open = r.id }) {
                Column(Modifier.padding(14.dp)) {
                    Text(r.title, fontWeight = FontWeight.SemiBold)
                    Text(r.status.label + (if (r.unread > 0) " · ${r.unread} new message(s)" else ""), style = MaterialTheme.typography.bodySmall)
                    r.needsYou?.let { Text(it, color = MaterialTheme.colorScheme.primary, style = MaterialTheme.typography.bodySmall) }
                }
            }
        }
    }
}

@Composable
private fun RequestDetailView(api: LiveApi, id: Long, onBack: () -> Unit) {
    val scope = rememberCoroutineScope()
    val uri = LocalUriHandler.current
    var r by remember { mutableStateOf<RequestDetail?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var note by remember { mutableStateOf("") }
    var reload by remember { mutableIntStateOf(0) }
    LaunchedEffect(reload) {
        when (val res = api.request(id)) {
            is LiveResult.Ok -> r = res.value.request
            is LiveResult.Err -> error = res.message
        }
    }
    LazyColumn(Modifier.fillMaxSize()) {
        item { TextButton(onClick = onBack, modifier = Modifier.padding(8.dp)) { Text("← All requests") } }
        error?.let { item { Message(it, true) } }
        val d = r ?: return@LazyColumn
        item {
            Column(Modifier.padding(horizontal = 16.dp)) {
                Text(d.title, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
                Text(d.status.label, style = MaterialTheme.typography.bodyMedium)
                d.needsYou?.let { Text(it, color = MaterialTheme.colorScheme.primary) }
            }
        }
        d.sponsoredCopy?.let { c ->
            item {
                var copyNote by remember { mutableStateOf("") }
                SectionCard {
                    Text("Sponsored copy · " + when (c.status) { "approved" -> "approved"; "changes" -> "changes asked"; else -> "waiting for you" + (c.dueOn?.let { " (by $it)" } ?: "") },
                        fontWeight = FontWeight.SemiBold)
                    Text(c.text, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.padding(vertical = 6.dp))
                    if (c.status == "pending") {
                        Button(onClick = { scope.launch { api.approveCopy(id); reload++ } }) { Text("Approve this wording") }
                        OutlinedTextField(copyNote, { copyNote = it }, label = { Text("Or what should change?") }, modifier = Modifier.fillMaxWidth().padding(top = 8.dp))
                        OutlinedButton(enabled = copyNote.trim().length >= 5, onClick = { scope.launch { api.copyChanges(id, copyNote.trim()); copyNote = ""; reload++ } }) { Text("Ask for changes") }
                    }
                }
            }
        }
        if (d.canReviewWork) item {
            SectionCard {
                Text("Review the work" + (d.reviewDueOn?.let { " · due $it" } ?: ""), fontWeight = FontWeight.SemiBold,
                    color = if (d.reviewOverdue) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurface)
                Button(onClick = { scope.launch { api.approve(id); reload++ } }, modifier = Modifier.padding(top = 8.dp)) { Text("Approve") }
                OutlinedTextField(note, { note = it }, label = { Text("Or describe the changes") }, modifier = Modifier.fillMaxWidth().padding(top = 8.dp))
                OutlinedButton(enabled = note.trim().length >= 5, onClick = { scope.launch { api.requestChanges(id, note.trim()); note = ""; reload++ } }) { Text("Ask for changes") }
            }
        }
        d.onboarding?.takeIf { it.active && it.steps.isNotEmpty() }?.let { o ->
            item {
                SectionCard {
                    Text("Getting started · ${o.done}/${o.total}", fontWeight = FontWeight.SemiBold)
                    LinearProgressIndicator(progress = { if (o.total == 0) 0f else o.done.toFloat() / o.total }, modifier = Modifier.fillMaxWidth().padding(vertical = 6.dp))
                    o.steps.forEach { s ->
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Checkbox(checked = s.done, enabled = !s.auto, onCheckedChange = { v -> scope.launch { api.tickOnboarding(id, s.key, v); reload++ } })
                            Text(s.label + if (s.auto && !s.done) " (ticks itself on the website)" else "", style = MaterialTheme.typography.bodyMedium)
                        }
                    }
                }
            }
        }
        d.billing?.let { b ->
            if (b.paymentPlan != null || b.depositPercent > 0 || b.invoiceDueOn != null || d.canPay) item {
                SectionCard {
                    Text("Payment", fontWeight = FontWeight.SemiBold)
                    if (b.depositPercent > 0) Text("${b.depositPercent}% deposit up front; the balance once the work is live.", style = MaterialTheme.typography.bodySmall)
                    b.invoiceDueOn?.let { Text("Invoice due $it" + if (b.overdue) " · ${b.daysOverdue} days overdue" else "", style = MaterialTheme.typography.bodySmall,
                        color = if (b.overdue) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant) }
                    if (d.canPay) Button(onClick = { scope.launch { (api.pay(id) as? LiveResult.Ok)?.value?.checkoutUrl?.let { uri.openUri(it) } } }, modifier = Modifier.padding(top = 8.dp)) {
                        Text("Pay ${b.nextAmount} ${d.currency}" + if (b.nextPart == "deposit") " deposit" else "")
                    }
                }
            }
        }
        if (d.rights.isNotEmpty()) item {
            SectionCard {
                Text("Usage rights", fontWeight = FontWeight.SemiBold)
                d.rights.forEach { x ->
                    Spacer(Modifier.height(6.dp))
                    Text("${x.label}${x.scope?.let { " · $it" } ?: ""}", style = MaterialTheme.typography.bodyMedium)
                    Text(x.endsOn?.let { "Until $it" + if (x.endsSoon) " · ends soon" else "" } ?: "${x.days} days from publish", style = MaterialTheme.typography.bodySmall)
                    if ((x.endsSoon || x.status == "ended") && x.price > 0) OutlinedButton(onClick = {
                        scope.launch { (api.extendRight(id, x.id) as? LiveResult.Ok)?.value?.checkoutUrl?.let { uri.openUri(it) } }
                    }) { Text("Extend ${x.days} days · ${x.price} ${d.currency}") }
                }
            }
        }
    }
}

// =============================================================================================
// Members: live sessions and courses
// =============================================================================================

@Composable
fun CommunityLiveScreen(api: LiveApi) = SignedIn(api) {
    var tab by remember { mutableIntStateOf(0) }
    Column(Modifier.fillMaxSize()) {
        TabRow(selectedTabIndex = tab) {
            Tab(selected = tab == 0, onClick = { tab = 0 }, text = { Text("Live sessions") })
            Tab(selected = tab == 1, onClick = { tab = 1 }, text = { Text("Courses") })
        }
        if (tab == 0) EventsView(api) else CoursesView(api)
    }
}

@Composable
private fun EventsView(api: LiveApi) {
    val scope = rememberCoroutineScope()
    val uri = LocalUriHandler.current
    var data by remember { mutableStateOf<EventList?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var reload by remember { mutableIntStateOf(0) }
    LaunchedEffect(reload) {
        when (val r = api.events()) {
            is LiveResult.Ok -> data = r.value
            is LiveResult.Err -> error = r.message
        }
    }
    LazyColumn(Modifier.fillMaxSize()) {
        error?.let { item { Message(it, true) } }
        val d = data ?: return@LazyColumn
        if (d.upcoming.isEmpty()) item { Message("No live sessions planned right now.") }
        items(d.upcoming, key = { it.id }) { e ->
            SectionCard {
                Text(e.title, fontWeight = FontWeight.SemiBold)
                Text("${e.startsAt.replace('T', ' ').take(16)} · ${e.minutes} min · ${e.count} going" + if (e.live) " · LIVE NOW" else "", style = MaterialTheme.typography.bodySmall)
                e.description?.let { Text(it, style = MaterialTheme.typography.bodySmall, modifier = Modifier.padding(top = 4.dp)) }
                Row(Modifier.padding(top = 8.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    if (e.locked) Text("For Pro members", style = MaterialTheme.typography.bodySmall)
                    else {
                        Button(onClick = { scope.launch { api.rsvp(e.id); reload++ } }) { Text(if (e.going) "✓ Going" else "RSVP") }
                        if (e.live && e.joinUrl != null) OutlinedButton(onClick = { uri.openUri(e.joinUrl) }) { Text("Join") }
                    }
                }
            }
        }
        if (d.past.isNotEmpty()) item { Text("Past sessions", modifier = Modifier.padding(16.dp), fontWeight = FontWeight.SemiBold) }
        items(d.past, key = { "p" + it.id }) { e ->
            SectionCard {
                Text(e.title)
                e.replayUrl?.let { url -> TextButton(onClick = { uri.openUri(url) }) { Text("Watch the replay") } } ?: Text("Replay coming", style = MaterialTheme.typography.bodySmall)
            }
        }
    }
}

@Composable
private fun CoursesView(api: LiveApi) {
    val scope = rememberCoroutineScope()
    val uri = LocalUriHandler.current
    var list by remember { mutableStateOf<List<CourseCard>?>(null) }
    var open by remember { mutableStateOf<CourseDetail?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var reload by remember { mutableIntStateOf(0) }
    LaunchedEffect(reload) {
        when (val r = api.courses()) {
            is LiveResult.Ok -> list = r.value.courses
            is LiveResult.Err -> error = r.message
        }
    }
    val course = open
    if (course != null) {
        LazyColumn(Modifier.fillMaxSize()) {
            item { TextButton(onClick = { open = null; reload++ }, modifier = Modifier.padding(8.dp)) { Text("← All courses") } }
            item { Text(course.course.title, modifier = Modifier.padding(horizontal = 16.dp), style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold) }
            items(course.lessons, key = { it.id }) { l ->
                SectionCard {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Checkbox(checked = l.done, enabled = !l.locked, onCheckedChange = { v ->
                            scope.launch {
                                api.completeLesson(l.id, v)
                                (api.course(course.course.id) as? LiveResult.Ok)?.let { open = it.value }
                            }
                        })
                        Text(l.title + (l.minutes?.let { " · $it min" } ?: ""), fontWeight = FontWeight.SemiBold)
                    }
                    if (l.locked) Text("For Pro members", style = MaterialTheme.typography.bodySmall)
                    l.videoUrl?.let { url -> TextButton(onClick = { uri.openUri(url) }) { Text("Watch the video") } }
                    l.body?.let { Text(it.take(1200), style = MaterialTheme.typography.bodySmall) }
                }
            }
        }
        return
    }
    LazyColumn(Modifier.fillMaxSize()) {
        error?.let { item { Message(it, true) } }
        if (list?.isEmpty() == true) item { Message("No courses yet.") }
        items(list.orEmpty(), key = { it.id }) { c ->
            Card(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 6.dp).clickable {
                scope.launch { (api.course(c.id) as? LiveResult.Ok)?.let { open = it.value } }
            }) {
                Column(Modifier.padding(14.dp)) {
                    Text(c.title + if (c.tier == "pro") " · Pro" else "", fontWeight = FontWeight.SemiBold)
                    c.description?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                    LinearProgressIndicator(progress = { if (c.lessons == 0) 0f else c.completed.toFloat() / c.lessons }, modifier = Modifier.fillMaxWidth().padding(top = 8.dp))
                    Text("${c.completed}/${c.lessons} lessons done", style = MaterialTheme.typography.bodySmall)
                }
            }
        }
    }
}
