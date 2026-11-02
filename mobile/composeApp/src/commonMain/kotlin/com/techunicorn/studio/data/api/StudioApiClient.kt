package com.techunicorn.studio.data.api

import com.techunicorn.studio.domain.models.*
import io.ktor.client.*
import io.ktor.client.call.*
import io.ktor.client.plugins.*
import io.ktor.client.plugins.contentnegotiation.*
import io.ktor.client.plugins.logging.*
import io.ktor.client.request.*
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

    // Public Landing Page Services
    suspend fun getLandingServices(): List<LandingService> {
        return listOf(
            LandingService(
                category = "MVP Engineering",
                badge = "2 to 4 wks",
                iconName = "zap",
                title = "Vibecoded Apps",
                desc = "AI-powered MVPs designed, built, and shipped in 2 to 4 weeks. From napkin sketch to live cloud deployment.",
                deliverables = listOf(
                    "Interactive prototype & design review in 5 days",
                    "Full production codebase transferred to your GitHub",
                    "Integrated Stripe billing, auth & automated backups"
                ),
                tags = listOf("React / Vite", "Python", "Stripe", "Docker"),
                ctaService = "mvp_build"
            ),
            LandingService(
                category = "Production AI",
                badge = "Practical LLMs",
                iconName = "sparkle",
                title = "AI Integration",
                desc = "Practical AI features for your existing product: shipped, not slide-decked. Autonomous agents that earn their keep.",
                deliverables = listOf(
                    "Custom LLM subagent pipelines & tool-routing",
                    "Deterministic evaluation benchmarks & safety guardrails",
                    "Streaming responses with low latency"
                ),
                tags = listOf("Claude Code", "Gemini API", "Agents", "Vector DB"),
                ctaService = "consulting"
            ),
            LandingService(
                category = "Ex-Google Advisory",
                badge = "Weekly Syncs",
                iconName = "code",
                title = "Technical Consulting",
                desc = "An ex-Google engineer in your corner for architecture reviews, build-vs-buy decisions, and scaling strategy.",
                deliverables = listOf(
                    "Unbiased infrastructure assessments",
                    "Scalability and cloud security audits before launch",
                    "Async code reviews and emergency escalation"
                ),
                tags = listOf("Architecture", "Cloud Security", "Scalability"),
                ctaService = "consulting"
            ),
            LandingService(
                category = "Audience & Growth",
                badge = "519K+ Audience",
                iconName = "play",
                title = "Content Creation",
                desc = "Short-form video, long-form YouTube deep-dives, and LinkedIn content that makes complex tech feel obvious.",
                deliverables = listOf(
                    "Full video scripting, hands-on demos & editing",
                    "Multi-platform syndication (YouTube, IG, LinkedIn)",
                    "Native integrations that build developer goodwill"
                ),
                tags = listOf("YouTube 4K", "Shorts", "LinkedIn Pulse"),
                ctaService = "brand_deal"
            ),
            LandingService(
                category = "Keynotes & Stages",
                badge = "Global Stages",
                iconName = "mic",
                title = "Speaking & Workshops",
                desc = "Keynotes and hands-on workshops on AI agents, building in public, and the future of remote engineering.",
                deliverables = listOf(
                    "In-person and virtual keynote talks tailored to your conference",
                    "Live coding demos showing autonomous agents in real time",
                    "Interactive Q&A and VIP speaker session engagement"
                ),
                tags = listOf("Nomad Cruise", "Tech Summits", "Live Coding"),
                ctaService = "speaking"
            ),
            LandingService(
                category = "Creator Partnerships",
                badge = "Verified Fit",
                iconName = "briefcase",
                title = "Brand Partnerships",
                desc = "Authentic integrations with an engaged audience of 519K+ developers, founders, and tech professionals.",
                deliverables = listOf(
                    "Dedicated feature video or 60-second integration",
                    "Custom tracking links, social promotion & reporting",
                    "Strict policy: I only endorse tools I genuinely use"
                ),
                tags = listOf("Dev Tools", "SaaS", "High Retention"),
                ctaService = "brand_deal"
            )
        )
    }

    // Talks / On Stage
    suspend fun getTalks(): List<Talk> {
        return listOf(
            Talk(
                id = 1,
                title = "Vibecoding Production Apps with AI Agents",
                event = "Nomad Cruise Keynote",
                year = "2026",
                reach = "1,800+ Attendees",
                description = "How autonomous coding agents changed software architecture and allowed 1-person teams to ship at scale."
            ),
            Talk(
                id = 2,
                title = "Ex-Google to 470K Audience: The Creator Engineer Playbook",
                event = "Tech Global Summit",
                year = "2025",
                reach = "3,200+ Attendees",
                description = "Bridging deep technical engineering with storytelling to build high-trust developer communities."
            )
        )
    }

    // Client Portal Requests
    suspend fun getClientRequests(): List<ClientRequest> {
        return listOf(
            ClientRequest(
                id = 201,
                title = "AI Code Review Copilot MVP",
                company = "ScaleWorks Inc.",
                status = "review",
                unreadCount = 2,
                revisionsCount = 1,
                amount = 12500.0,
                paymentStatus = "paid",
                deliverables = listOf("Core LLM pipeline", "GitHub webhook listener", "Stripe billing integration"),
                annotations = listOf(
                    DeliverableAnnotation(
                        1,
                        "02:15",
                        "Sarah (CTO)",
                        "Can we add support for GitLab PRs as well as GitHub?",
                        false
                    ),
                    DeliverableAnnotation(2, "04:30", "Delia", "Added GitLab OAuth in the latest build commit.", true)
                )
            ),
            ClientRequest(
                id = 202,
                title = "Monthly AI Architecture Advisory",
                company = "CloudFlow AI",
                status = "in_progress",
                unreadCount = 0,
                revisionsCount = 0,
                amount = 4500.0,
                paymentStatus = "paid",
                meetingAt = "Thursday at 16:00 CET",
                deliverables = listOf("Weekly architecture sync", "Emergency Slack access", "PR review audit")
            )
        )
    }

    // Deal Room
    suspend fun getDealRoom(token: String = "supabase-q4"): DealRoomData {
        return DealRoomData(
            token = token,
            brandName = "Supabase",
            amount = 12000.0,
            selectedTier = "growth",
            tiers = listOf(
                DealTier(
                    key = "essential",
                    name = "Essential Integration",
                    amount = 8500.0,
                    summary = "Single 60-second video integration with permanent link placement.",
                    features = listOf("60s Dedicated Segment", "Top comment link", "Performance report after 30 days")
                ),
                DealTier(
                    key = "growth",
                    name = "Growth Cascade",
                    amount = 12000.0,
                    summary = "60-second video + multi-platform syndication across X, LinkedIn, and newsletter.",
                    features = listOf(
                        "60s Dedicated Segment",
                        "Dedicated X thread (47K views est.)",
                        "LinkedIn Carousel post",
                        "Newsletter banner"
                    ),
                    badge = "MOST POPULAR"
                ),
                DealTier(
                    key = "enterprise",
                    name = "Full Ecosystem Takeover",
                    amount = 18500.0,
                    summary = "Full dedicated 10-minute deep dive video + complete social campaign.",
                    features = listOf(
                        "10-minute Dedicated Video",
                        "Full GitHub open-source sample",
                        "Keynote mention",
                        "30-day exclusive category sponsorship"
                    )
                )
            ),
            acceptedAt = null,
            paidAt = null,
            isDpaSigned = false
        )
    }

    // Brand Vault
    suspend fun getBrandVault(): BrandVault {
        return BrandVault(
            brandName = "Tech Unicorn",
            primaryHex = "#FF5757",
            secondaryHex = "#8B5CF6",
            logoUrl = "https://thetechunicorn.com/logo.svg",
            guidelinesUrl = "https://thetechunicorn.com/brand-kit",
            viesVatNumber = "DE382910482",
            isViesVerified = true
        )
    }

    // Love Suite
    suspend fun getLoveLetters(): List<LoveLetter> {
        return listOf(
            LoveLetter(
                id = 1,
                title = "To My Favorite Person in the World",
                date = "Oct 28, 2026",
                excerpt = "Just a little reminder of how proud I am of everything you build...",
                body = "Just a reminder: you've built an entire company, audience, and system from scratch. Take a breath and enjoy how far you've come. I love you endlessly.",
                isOpened = true
            ),
            LoveLetter(
                id = 2,
                title = "Open on a Stressful Launch Day 💌",
                date = "Nov 02, 2026",
                excerpt = "For when code breaks or deadlines feel too heavy...",
                body = "Remember: No bug is worth losing sleep over. You're brilliant, you're capable, and I'm right here beside you through every launch.",
                isOpened = false
            )
        )
    }

    // Memory Jar
    suspend fun getMemoryJarPrompts(): List<MemoryJarPrompt> {
        return listOf(
            MemoryJarPrompt(
                id = 1,
                question = "What was our best moment from the Nomad Cruise?",
                answer = "Watching the sunset over the Atlantic after delivering the vibecoding keynote.",
                dateAnswered = "Sep 15, 2026"
            ),
            MemoryJarPrompt(
                id = 2,
                question = "What is a small thing that made you smile today?",
                answer = null,
                dateAnswered = null
            )
        )
    }

    // Creative Toolkit
    suspend fun getToolkitTemplates(): List<ToolkitTemplate> {
        return listOf(
            ToolkitTemplate(
                id = 1,
                title = "Viral Technical Hook Generator",
                category = "YouTube",
                description = "Craft 5 tension-building opening hooks based on controversial tech trade-offs.",
                promptTemplate = "Generate 5 high-retention video hooks for the following topic: {topic}. Avoid corporate jargon."
            ),
            ToolkitTemplate(
                id = 2,
                title = "X Thread Cascader",
                category = "Social",
                description = "Turn any script or technical note into an 8-tweet narrative thread.",
                promptTemplate = "Repurpose this script into an 8-tweet thread with punchy single-line takeaways: {script}"
            ),
            ToolkitTemplate(
                id = 3,
                title = "Sponsor Value Proposal Pitch",
                category = "Deals",
                description = "Synthesize audience demographic fit and conversion metrics for inbound brands.",
                promptTemplate = "Write a tailored sponsor pitch for {brand} matching our 472K software engineer audience."
            )
        )
    }

    // Shipped Products & Case Studies
    suspend fun getShippedBuilds(): List<ShippedBuild> {
        return listOf(
            ShippedBuild(
                id = "build-agents",
                title = "Autonomous Multi-Agent AI Pipeline",
                tagline = "Production subagent tool-routing & semantic vector search",
                turnaround = "Shipped in 18 Days",
                location = "Lisbon & Tokyo",
                desc = "An enterprise research tool integrating Gemini Pro and Claude 3.5 Sonnet for multi-step reasoning, external API retrieval, and deterministic evaluation benchmarks.",
                metrics = listOf("Uptime" to "99.98%", "RAG Latency" to "<75ms", "Tool Success" to "99.4%"),
                stack = listOf("Gemini 2.0", "Claude 3.5", "pgvector", "Docker", "GCP")
            ),
            ShippedBuild(
                id = "build-fintech",
                title = "High-Concurrency Multi-Tenant API",
                tagline = "Zero-downtime transactional gateway with audit logging",
                turnaround = "Shipped in 21 Days",
                location = "Bali & Singapore",
                desc = "Scalable subscription billing engine with Stripe webhook synchronization, strict schema validation, and automated zero-downtime deployments on Google Cloud Run.",
                metrics = listOf("Daily Events" to "250K+", "P99 Latency" to "42ms", "Deployment" to "Zero Downtime"),
                stack = listOf("Laravel 11", "PHP 8.3", "PostgreSQL", "Redis", "Cloud Run")
            ),
            ShippedBuild(
                id = "build-studio",
                title = "Real-Time Creator Studio Suite",
                tagline = "Zero-build-step reactive dashboard with local analytics",
                turnaround = "Shipped in 14 Days",
                location = "Bangkok & London",
                desc = "A lightning-fast creator platform featuring privacy-first analytics, social feed synchronization, audio prompter, and dynamic dark/light design token engine.",
                metrics = listOf("Build Time" to "0ms", "Lighthouse" to "100/100", "State Sync" to "Real-Time"),
                stack = listOf("Vanilla JS", "SSE Streams", "WebSockets", "CSS Tokens")
            )
        )
    }

    // Packages & Transparent Pricing
    suspend fun getPricingPackages(): List<PricingPackage> {
        return listOf(
            PricingPackage(
                key = "mvp",
                name = "Sprint MVP Build",
                price = 12500.0,
                per = "flat / fixed",
                desc = "From napkin sketch to live cloud deployment in 2 to 4 weeks. Ideal for founders needing a production-ready product fast.",
                clarify = "Includes 30-day bug warranty & code handover",
                popular = true,
                benefits = listOf(
                    "Interactive prototype within 5 days",
                    "Full production codebase transferred to your GitHub",
                    "Integrated Stripe payments, auth, and automated DB backups",
                    "Friday recorded Loom demos with Delia",
                    "30-day post-launch bug warranty included"
                )
            ),
            PricingPackage(
                key = "retainer",
                name = "Monthly AI Advisory",
                price = 3500.0,
                per = "per month",
                desc = "An ex-Google systems engineer on call. Architecture reviews, AI build-vs-buy guidance, and async PR evaluations.",
                clarify = "Pause or cancel anytime · No lock-in",
                popular = false,
                benefits = listOf(
                    "Two 60-min 1-on-1 strategic architecture calls per month",
                    "Unlimited async code & PR reviews via Slack or GitHub",
                    "Guaranteed 24h turnaround on business days",
                    "AI subagent tool-routing and safety audits",
                    "No long-term commitment · Stripe monthly billing"
                )
            ),
            PricingPackage(
                key = "brand",
                name = "Dedicated Video Sponsor",
                price = 6500.0,
                per = "per integration",
                desc = "Authentic video feature reaching 472K+ developers, founders, and technical professionals across YouTube, X, and LinkedIn.",
                clarify = "High retention · Strict tool endorsement policy",
                popular = false,
                benefits = listOf(
                    "60s dedicated integration or custom deep-dive segment",
                    "Syndication across YouTube 4K, LinkedIn, and Instagram",
                    "Custom tracking links and 30-day campaign ROI report",
                    "Permanent video placement with top pinned comment"
                )
            )
        )
    }

    // FAQ Items
    suspend fun getFaqItems(): List<FaqItem> {
        return listOf(
            FaqItem(
                id = "faq-vibecoded",
                category = "mvp",
                question = "What does 'Vibecoded' mean and how can an MVP ship in 2 to 4 weeks?",
                answer = "Vibecoding combines deep systems engineering foundations (from years writing production code at Google) with autonomous AI tools and rapid iteration loops. Instead of a 10-person agency spending a month in committee meetings, I work directly with you against concrete acceptance criteria. You get private GitHub commits from Day 1 and working software deployed to staging in days.",
                tags = listOf("2–4 Week Turnaround", "Friday Loom Demos", "Ex-Google Standards")
            ),
            FaqItem(
                id = "faq-ownership",
                category = "mvp",
                question = "Do I own 100% of the code, intellectual property, and infrastructure?",
                answer = "Yes, 100% without exception. Code is pushed to your private GitHub organization from Day 1. All cloud infrastructure (GCP, AWS, Supabase, Vercel) and third-party APIs are created in your own company accounts. There are zero licensing traps and zero proprietary lock-in.",
                tags = listOf("100% IP Ownership", "Zero Lock-In", "Full Handover")
            ),
            FaqItem(
                id = "faq-warranty",
                category = "mvp",
                question = "What happens after the MVP is launched? Is there a warranty?",
                answer = "Every MVP engagement includes a comprehensive 30-day post-launch bug warranty at zero additional cost. If anything in the agreed specification breaks, I patch it immediately. You also receive complete ops runbooks and developer documentation.",
                tags = listOf("30-Day Warranty", "Ops Runbook", "Full Handover")
            ),
            FaqItem(
                id = "faq-retainer",
                category = "advisory",
                question = "How does the $3,500/month Advisory Retainer work?",
                answer = "It places an ex-Google engineer on your team without executive hire overhead. Includes two 60-min architecture calls per month, unlimited async PR reviews via private Slack or GitHub (24h turnaround), and AI evaluations. Billed monthly via Stripe, pause or cancel anytime.",
                tags = listOf("Ex-Google on Call", "24h Turnaround", "Cancel Anytime")
            ),
            FaqItem(
                id = "faq-timezones",
                category = "logistics",
                question = "You travel across 60+ countries. How do time zones work?",
                answer = "Async-first discipline is my superpower. Having worked across global teams at Google and traveled across 69 countries as a digital nomad, collaboration runs seamlessly via private Slack, daily commit logs, and weekly Loom demos. Real-time calls convert automatically to your local timezone.",
                tags = listOf("69 Countries Nomad", "Async-First Flow", "Local Timezones")
            )
        )
    }
}
