package com.techunicorn.studio.data.live

import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.plugins.HttpTimeout
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.client.request.HttpRequestBuilder
import io.ktor.client.request.accept
import io.ktor.client.request.bearerAuth
import io.ktor.client.request.delete
import io.ktor.client.request.get
import io.ktor.client.request.post
import io.ktor.client.request.patch
import io.ktor.client.request.setBody
import io.ktor.client.statement.HttpResponse
import io.ktor.client.statement.bodyAsText
import io.ktor.http.ContentType
import io.ktor.http.contentType
import io.ktor.http.isSuccess
import io.ktor.serialization.kotlinx.json.json
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

/**
 * The real Creator Studio API, signed in with a bearer token from POST /api/auth/mobile-token.
 * Every call returns a [LiveResult]: the server's own error message on failure, never sample data.
 */
sealed class LiveResult<out T> {
    data class Ok<T>(val value: T) : LiveResult<T>()
    data class Err(val message: String, val status: Int = 0) : LiveResult<Nothing>()
}

class LiveApi {
    private val json = Json { ignoreUnknownKeys = true; isLenient = true; explicitNulls = false }

    private val client = HttpClient {
        install(ContentNegotiation) { json(json) }
        install(HttpTimeout) {
            requestTimeoutMillis = 20_000
            connectTimeoutMillis = 8_000
        }
        expectSuccess = false
    }

    private val _user = MutableStateFlow<SessionUser?>(null)
    val user: StateFlow<SessionUser?> = _user.asStateFlow()

    var serverUrl: String = TokenStore.read("server") ?: defaultServerUrl()
        private set
    private var token: String? = TokenStore.read("token")

    val isSignedIn: Boolean get() = token != null

    fun setServer(url: String) {
        serverUrl = url.trim().trimEnd('/')
        TokenStore.write("server", serverUrl)
    }

    private fun url(path: String) = "$serverUrl/api/${path.trimStart('/')}"

    private fun HttpRequestBuilder.auth() {
        accept(ContentType.Application.Json)
        token?.let { bearerAuth(it) }
    }

    private suspend inline fun <reified T> read(response: HttpResponse): LiveResult<T> {
        val text = response.bodyAsText()
        if (!response.status.isSuccess()) {
            if (response.status.value == 401) signOutLocally()
            val err = runCatching { json.decodeFromString<ErrorBody>(text) }.getOrNull()
            return LiveResult.Err(err?.error ?: err?.message ?: "Something went wrong (${response.status.value}).", response.status.value)
        }
        return runCatching { LiveResult.Ok(json.decodeFromString<T>(text)) }
            .getOrElse { LiveResult.Err("Unexpected answer from the server.") }
    }

    private suspend inline fun <reified T> call(block: () -> HttpResponse): LiveResult<T> =
        try { read(block()) } catch (e: Exception) { LiveResult.Err("Can’t reach $serverUrl. Check the address and your connection.") }

    // ---- session ------------------------------------------------------------------------

    /** Email + password; when two-factor is on, call again with [code]. */
    suspend fun signIn(email: String, password: String, code: String? = null): LiveResult<TokenResponse> {
        val body = buildJsonObject {
            put("email", email)
            put("password", password)
            put("device_name", "Creator Studio app")
            if (!code.isNullOrBlank()) put("code", code)
        }
        val r = call<TokenResponse> {
            client.post(url("auth/mobile-token")) { accept(ContentType.Application.Json); contentType(ContentType.Application.Json); setBody(body) }
        }
        if (r is LiveResult.Ok && r.value.token != null) {
            token = r.value.token
            TokenStore.write("token", token)
            _user.value = r.value.user
        }
        return r
    }

    /** Who's signed in (after an app restart with a saved token). */
    suspend fun refreshUser(): LiveResult<StatusResponse> {
        val r = call<StatusResponse> { client.get(url("auth/status")) { auth() } }
        if (r is LiveResult.Ok) _user.value = r.value.user
        return r
    }

    suspend fun signOut() {
        runCatching { client.delete(url("auth/mobile-token")) { auth() } }
        signOutLocally()
    }

    private fun signOutLocally() {
        token = null
        TokenStore.write("token", null)
        _user.value = null
    }

    // ---- her ------------------------------------------------------------------------------

    suspend fun needsYou(): LiveResult<NeedsYou> = call { client.get(url("needs-you")) { auth() } }

    suspend fun shopTotals(): LiveResult<ShopAdmin> = call { client.get(url("shop/admin")) { auth() } }

    suspend fun affiliateTotals(): LiveResult<AffiliateOverview> = call { client.get(url("affiliates")) { auth() } }

    // ---- clients ----------------------------------------------------------------------------

    suspend fun approveCopy(id: Long): LiveResult<JsonObject> = call { client.post(url("portal/requests/$id/copy/approve")) { auth() } }

    suspend fun copyChanges(id: Long, note: String): LiveResult<JsonObject> = call {
        client.post(url("portal/requests/$id/copy/changes")) { auth(); contentType(ContentType.Application.Json); setBody(buildJsonObject { put("note", note) }) }
    }

    suspend fun requests(): LiveResult<RequestList> = call { client.get(url("portal/requests")) { auth() } }

    suspend fun request(id: Long): LiveResult<RequestEnvelope> = call { client.get(url("portal/requests/$id")) { auth() } }

    suspend fun approve(id: Long): LiveResult<RequestEnvelope> = call { client.post(url("portal/requests/$id/approve")) { auth() } }

    suspend fun requestChanges(id: Long, note: String): LiveResult<RequestEnvelope> = call {
        client.post(url("portal/requests/$id/changes")) { auth(); contentType(ContentType.Application.Json); setBody(buildJsonObject { put("note", note) }) }
    }

    suspend fun tickOnboarding(id: Long, key: String, done: Boolean): LiveResult<OnboardingEnvelope> = call {
        client.post(url("portal/requests/$id/onboarding/$key")) { auth(); contentType(ContentType.Application.Json); setBody(buildJsonObject { put("done", done) }) }
    }

    suspend fun pay(id: Long): LiveResult<CheckoutResponse> = call { client.post(url("portal/requests/$id/pay")) { auth() } }

    suspend fun extendRight(id: Long, right: Long): LiveResult<CheckoutResponse> = call { client.post(url("portal/requests/$id/rights/$right/extend")) { auth() } }

    // ---- team -------------------------------------------------------------------------------

    suspend fun team(): LiveResult<TeamOverview> = call { client.get(url("team")) { auth() } }

    suspend fun toggleTimer(taskId: Long): LiveResult<JsonObject> = call { client.post(url("team/tasks/$taskId/timer")) { auth() } }

    suspend fun logMinutes(taskId: Long, minutes: Int): LiveResult<JsonObject> = call {
        client.post(url("team/tasks/$taskId/time")) { auth(); contentType(ContentType.Application.Json); setBody(buildJsonObject { put("minutes", minutes) }) }
    }

    suspend fun updateTask(taskId: Long, stage: String? = null, completed: Boolean? = null): LiveResult<JsonObject> = call {
        client.patch(url("team/tasks/$taskId")) {
            auth()
            contentType(ContentType.Application.Json)
            setBody(buildJsonObject {
                stage?.let { put("stage", it) }
                completed?.let { put("completed", JsonPrimitive(it)) }
            })
        }
    }

    // ---- community --------------------------------------------------------------------------

    suspend fun events(): LiveResult<EventList> = call { client.get(url("portal/community/events")) { auth() } }

    suspend fun rsvp(id: Long): LiveResult<RsvpResponse> = call { client.post(url("portal/community/events/$id/rsvp")) { auth() } }

    suspend fun courses(): LiveResult<CourseList> = call { client.get(url("portal/community/courses")) { auth() } }

    suspend fun course(id: Long): LiveResult<CourseDetail> = call { client.get(url("portal/community/courses/$id")) { auth() } }

    suspend fun completeLesson(id: Long, done: Boolean): LiveResult<JsonObject> = call {
        client.post(url("portal/community/lessons/$id/complete")) { auth(); contentType(ContentType.Application.Json); setBody(buildJsonObject { put("done", done) }) }
    }
}
