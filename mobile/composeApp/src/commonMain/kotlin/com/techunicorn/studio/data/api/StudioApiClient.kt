package com.techunicorn.studio.data.api

import com.techunicorn.studio.domain.models.*
import io.ktor.client.*
import io.ktor.client.call.*
import io.ktor.client.plugins.*
import io.ktor.client.plugins.contentnegotiation.*
import io.ktor.client.plugins.logging.*
import io.ktor.client.request.*
import io.ktor.client.statement.*
import io.ktor.http.*
import io.ktor.serialization.kotlinx.json.*
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow
import kotlinx.serialization.json.Json

class StudioApiClient(
    val baseUrl: String = "http://localhost:8000/api"
) {
    val client = HttpClient {
        install(ContentNegotiation) {
            json(Json {
                ignoreUnknownKeys = true
                prettyPrint = true
                isLenient = true
            })
        }
        install(Logging) {
            level = LogLevel.INFO
        }
        install(HttpTimeout) {
            requestTimeoutMillis = 15000
            connectTimeoutMillis = 5000
        }
    }

    // Dashboard
    suspend fun getDashboardStats(): StudioDashboardStats {
        return try {
            client.get("$baseUrl/dashboard").body()
        } catch (e: Exception) {
            // High fidelity fallback when local backend is offline
            StudioDashboardStats()
        }
    }

    // Breakouts
    suspend fun getBreakouts(): List<Breakout> {
        return try {
            client.get("$baseUrl/channels").body()
        } catch (e: Exception) {
            listOf(
                Breakout(
                    id = 1,
                    channelTitle = "Theo - t3.gg",
                    videoTitle = "Why Next.js 15 Changed Everything",
                    views = 284000,
                    channelMedianViews = 65000,
                    multiplier = 4.37,
                    publishedAt = "2 days ago",
                    videoUrl = "https://youtube.com"
                ),
                Breakout(
                    id = 2,
                    channelTitle = "Alberta Tech",
                    videoTitle = "I Built a Local AI Agent Swarm with Kotlin",
                    views = 152000,
                    channelMedianViews = 38000,
                    multiplier = 4.0,
                    publishedAt = "3 days ago",
                    videoUrl = "https://youtube.com"
                ),
                Breakout(
                    id = 3,
                    channelTitle = "Fireship",
                    videoTitle = "10 AI Tools That Feel Illegal to Use",
                    views = 980000,
                    channelMedianViews = 320000,
                    multiplier = 3.06,
                    publishedAt = "5 days ago",
                    videoUrl = "https://youtube.com"
                )
            )
        }
    }

    // Ideas
    suspend fun getIdeas(): List<Idea> {
        return try {
            client.get("$baseUrl/ideas").body()
        } catch (e: Exception) {
            listOf(
                Idea(
                    id = 1,
                    title = "Building a Full Production App in 48 Hours with AI",
                    angle = "Vibecoding vs traditional engineering: what actually works in production.",
                    hook = "Everyone says AI can build an entire startup in a weekend. As an ex-Google engineer, I tested it.",
                    status = "greenlit",
                    estimatedViews = 180000
                ),
                Idea(
                    id = 2,
                    title = "Stop Using LangChain: Write Autonomous Agent Swarms with Native Kotlin",
                    angle = "Simplicity, type safety, and zero framework overhead.",
                    hook = "Python is the AI standard, but Kotlin might actually be the best language for agent loops.",
                    status = "inbox",
                    estimatedViews = 120000
                ),
                Idea(
                    id = 3,
                    title = "How I Replaced 3 Full-time Agency Roles with Background Routines",
                    angle = "Autonomous scheduling, social repurposing, and contract generation.",
                    hook = "My agency runs 24/7 without a team. Here is the exact system architecture.",
                    status = "inbox",
                    estimatedViews = 95000
                )
            )
        }
    }

    // Scripts
    suspend fun getScripts(): List<Script> {
        return try {
            client.get("$baseUrl/scripts").body()
        } catch (e: Exception) {
            listOf(
                Script(
                    id = 101,
                    title = "Building a Production App in 48h with AI",
                    hook = "Everyone says AI can build an entire startup in a weekend. As an ex-Google engineer, I spent 48 hours testing the limits.",
                    body = """
# Intro (00:00 - 01:15)
Most tutorials show you a toy demo with 3 files. When you build authentication, payments, database migrations, and background jobs, AI starts forgetting context.

# The Architecture (01:15 - 04:30)
Here is the stack we chose:
1. Backend with clean Domain Separation.
2. Event-driven agents with strict guardrails.
3. Native mobile interface using Kotlin Compose.

# The Reality Check (04:30 - 08:00)
Where AI succeeded: rapid scaffolding, boilerplate, and UI components.
Where AI struggled: edge cases in state reconciliation and rate limits.
                    """.trimIndent(),
                    cta = "Check out the open source template linked below and subscribe for more real engineering breakdowns.",
                    wordCount = 1420,
                    estimatedDurationMinutes = 9.5,
                    chapters = listOf(
                        ScriptChapter("ch1", "The 48h Experiment Hook", "00:00"),
                        ScriptChapter("ch2", "Real Architecture vs Toy Demos", "01:15"),
                        ScriptChapter("ch3", "Where AI Breaks Down", "04:30"),
                        ScriptChapter("ch4", "Final Production Wrap", "08:15")
                    ),
                    retentionWarnings = listOf(
                        "Pacing in Chapter 2 is text-dense; consider B-roll graphic at 02:45.",
                        "Remove passive sentence: 'It was decided that...' -> 'We chose...'"
                    ),
                    updatedAt = "Today, 14:20"
                )
            )
        }
    }

    // Social Posts
    suspend fun getSocialPosts(): List<SocialPost> {
        return try {
            client.get("$baseUrl/social/posts").body()
        } catch (e: Exception) {
            listOf(
                SocialPost(
                    id = 1,
                    platform = "linkedin",
                    content = "Stop over-engineering your AI wrappers.\n\n3 lessons from building 25+ autonomous agent workflows:\n1. Prompt versioning > Model size\n2. Guardrails beat fine-tuning\n3. Type safety keeps agents deterministic",
                    scheduledFor = "Tomorrow, 09:00",
                    status = "scheduled",
                    characterCount = 215
                ),
                SocialPost(
                    id = 2,
                    platform = "x",
                    content = "Kotlin Multiplatform + Compose is the most underrated stack for creators building their own tools.\n\nNative mobile app + instant desktop preview from one single codebase.",
                    scheduledFor = "Today, 18:30",
                    status = "scheduled",
                    characterCount = 178
                ),
                SocialPost(
                    id = 3,
                    platform = "instagram",
                    content = "Swipe to see the 5 tools that automate 80% of our YouTube studio workflow 👉",
                    scheduledFor = null,
                    status = "draft",
                    characterCount = 82
                )
            )
        }
    }

    // Brand Deals
    suspend fun getDeals(): List<Deal> {
        return try {
            client.get("$baseUrl/deals").body()
        } catch (e: Exception) {
            listOf(
                Deal(
                    id = 1,
                    brandName = "Supabase",
                    contactPerson = "Alex Rivera",
                    stage = "negotiating",
                    value = 12000.0,
                    deliverables = listOf("60s Dedicated Mid-roll", "X Thread", "Newsletter Inclusion"),
                    dueDate = "Nov 18, 2026"
                ),
                Deal(
                    id = 2,
                    brandName = "Cursor",
                    contactPerson = "Sarah Chen",
                    stage = "signed",
                    value = 15000.0,
                    deliverables = listOf("Integrated Video Sponsor", "LinkedIn Carousel"),
                    dueDate = "Nov 25, 2026"
                ),
                Deal(
                    id = 3,
                    brandName = "JetBrains",
                    contactPerson = "Pavel Novak",
                    stage = "prospect",
                    value = 8500.0,
                    deliverables = listOf("KMP Ecosystem Deep Dive"),
                    dueDate = "Dec 05, 2026"
                )
            )
        }
    }

    // Copilot Assistant & SSE Streaming
    suspend fun sendCopilotMessage(threadId: String, userPrompt: String): Flow<String> = flow {
        // Simulates streaming tokens with markdown and action cards
        val tokens = listOf(
            "I've analyzed ", "your latest YouTube script ", "**Building a Production App in 48h**.\n\n",
            "Here are **2 retention optimizations**:\n",
            "1. **Strengthen the 00:30 hook**: Swap the passive opener with your real Google metric.\n",
            "2. **Pacing alert**: Chapter 2 runs 3 minutes without a visual change. Add a terminal B-roll prompt.\n\n",
            "Would you like me to update the script now?"
        )
        for (token in tokens) {
            emit(token)
            delay(120)
        }
    }

    // Flow Dictation Polishing
    suspend fun polishDictation(rawText: String): String {
        delay(600)
        return """
### Polished Studio Note
**Core Premise:**
$rawText

**Action Items:**
- Turn into 60s YouTube Short hook.
- Cascade into X thread and LinkedIn carousel.
- Link to Studio Deal Room deliverable.
        """.trimIndent()
    }
}

