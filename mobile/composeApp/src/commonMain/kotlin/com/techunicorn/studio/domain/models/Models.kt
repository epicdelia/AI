package com.techunicorn.studio.domain.models

import kotlinx.serialization.Serializable

enum class UserRole {
    STUDIO,   // Delia's full creator studio
    CLIENT,   // Agency / Client portal view
    PUBLIC    // Public landing page & visitor view
}

@Serializable
data class User(
    val id: Long = 1,
    val name: String = "Delia Lazarescu",
    val email: String = "delia@example.com",
    val role: String = "studio",
    val abilities: List<String> = listOf("studio", "portal.requests", "clients.manage")
)

@Serializable
data class StudioDashboardStats(
    val weeklyGoalStreak: Int = 8,
    val videosFilmedThisMonth: Int = 3,
    val audienceReach: Long = 472500,
    val activeBrandDeals: Int = 4,
    val dealsPipelineValue: Double = 34500.0,
    val pendingAgentTasks: Int = 2
)

@Serializable
data class Breakout(
    val id: Long,
    val channelTitle: String,
    val videoTitle: String,
    val views: Long,
    val channelMedianViews: Long,
    val multiplier: Double,
    val publishedAt: String,
    val videoUrl: String,
    val thumbnailUrl: String = ""
)

@Serializable
data class Idea(
    val id: Long,
    val title: String,
    val angle: String,
    val hook: String,
    val targetAudience: String = "",
    val status: String = "inbox",
    val estimatedViews: Long = 0,
    val notes: String = ""
)

@Serializable
data class ScriptChapter(
    val id: String,
    val title: String,
    val timestampEstimate: String,
    val keyPoints: List<String> = emptyList()
)

@Serializable
data class Script(
    val id: Long,
    val title: String,
    val hook: String,
    val body: String,
    val cta: String = "",
    val wordCount: Int = 0,
    val estimatedDurationMinutes: Double = 0.0,
    val chapters: List<ScriptChapter> = emptyList(),
    val retentionWarnings: List<String> = emptyList(),
    val updatedAt: String = ""
)

@Serializable
data class SocialPost(
    val id: Long,
    val platform: String,
    val content: String,
    val scheduledFor: String? = null,
    val status: String = "draft",
    val characterCount: Int = 0,
    val mediaUrls: List<String> = emptyList()
)

@Serializable
data class Deal(
    val id: Long,
    val brandName: String,
    val contactPerson: String,
    val stage: String,
    val value: Double,
    val currency: String = "USD",
    val deliverables: List<String> = emptyList(),
    val dueDate: String? = null
)

@Serializable
data class CopilotAction(
    val id: String,
    val title: String,
    val description: String,
    val type: String,
    val payload: String = ""
)

@Serializable
data class CopilotMessage(
    val id: String,
    val sender: String,
    val text: String,
    val timestamp: String,
    val actions: List<CopilotAction> = emptyList()
)

@Serializable
data class AgentSwarmMission(
    val id: Long,
    val name: String,
    val agentName: String,
    val goal: String,
    val status: String = "running",
    val stepsCompleted: Int = 0,
    val totalSteps: Int = 5,
    val latestLog: String = ""
)

@Serializable
data class LandingService(
    val category: String,
    val badge: String,
    val iconName: String,
    val title: String,
    val desc: String,
    val deliverables: List<String>,
    val tags: List<String>,
    val ctaService: String
)

@Serializable
data class Talk(
    val id: Long,
    val title: String,
    val event: String,
    val year: String,
    val reach: String,
    val description: String
)

@Serializable
data class DeliverableAnnotation(
    val id: Long,
    val timestamp: String,
    val author: String,
    val text: String,
    val isResolved: Boolean = false
)

@Serializable
data class ClientRequest(
    val id: Long,
    val title: String,
    val company: String,
    val status: String, // in_progress, review, paid, completed
    val unreadCount: Int = 0,
    val revisionsCount: Int = 0,
    val amount: Double = 0.0,
    val currency: String = "USD",
    val paymentStatus: String = "unpaid",
    val meetingAt: String? = null,
    val needsYou: String? = null,
    val deliverables: List<String> = emptyList(),
    val annotations: List<DeliverableAnnotation> = emptyList()
)

@Serializable
data class BrandVault(
    val brandName: String = "Acme Corp",
    val primaryHex: String = "#FF5757",
    val secondaryHex: String = "#8B5CF6",
    val logoUrl: String = "",
    val guidelinesUrl: String = "",
    val viesVatNumber: String = "DE123456789",
    val isViesVerified: Boolean = true
)

@Serializable
data class DealTier(
    val key: String,
    val name: String,
    val amount: Double,
    val summary: String,
    val features: List<String>,
    val badge: String? = null
)

@Serializable
data class DealRoomData(
    val token: String,
    val brandName: String,
    val creator: String = "Delia Lazarescu",
    val amount: Double,
    val currency: String = "USD",
    val selectedTier: String = "growth",
    val tiers: List<DealTier> = emptyList(),
    val acceptedAt: String? = null,
    val paidAt: String? = null,
    val isDpaSigned: Boolean = false
)

@Serializable
data class LoveLetter(
    val id: Long,
    val title: String,
    val date: String,
    val excerpt: String,
    val body: String,
    val isOpened: Boolean = false
)

@Serializable
data class MemoryJarPrompt(
    val id: Long,
    val question: String,
    val answer: String? = null,
    val dateAnswered: String? = null
)

@Serializable
data class ToolkitTemplate(
    val id: Long,
    val title: String,
    val category: String,
    val description: String,
    val promptTemplate: String
)
