package com.techunicorn.studio.domain.models

import kotlinx.serialization.Serializable

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
    val multiplier: Double, // e.g. 4.2x
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
    val status: String = "inbox", // inbox, greenlit, archived
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
    val platform: String, // linkedin, instagram, x, tiktok, threads
    val content: String,
    val scheduledFor: String? = null,
    val status: String = "draft", // draft, scheduled, published
    val characterCount: Int = 0,
    val mediaUrls: List<String> = emptyList()
)

@Serializable
data class Deal(
    val id: Long,
    val brandName: String,
    val contactPerson: String,
    val stage: String, // prospect, pitched, negotiating, signed, paid
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
    val type: String, // insert_hook, update_script, schedule_post
    val payload: String = ""
)

@Serializable
data class CopilotMessage(
    val id: String,
    val sender: String, // user, assistant, system
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
    val status: String = "running", // running, completed, paused
    val stepsCompleted: Int = 0,
    val totalSteps: Int = 5,
    val latestLog: String = ""
)

@Serializable
data class DictationNote(
    val id: Long,
    val rawTranscript: String,
    val polishedMarkdown: String,
    val createdAt: String
)

