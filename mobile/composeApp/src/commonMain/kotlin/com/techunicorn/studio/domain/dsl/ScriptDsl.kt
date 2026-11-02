package com.techunicorn.studio.domain.dsl

import com.techunicorn.studio.domain.models.Script
import com.techunicorn.studio.domain.models.ScriptChapter

@DslMarker
annotation class ScriptDslMarker

@ScriptDslMarker
class ScriptBuilder {
    var id: Long = 0
    var title: String = ""
    var hook: String = ""
    var body: String = ""
    var cta: String = ""
    var wordCount: Int = 0
    var estimatedDurationMinutes: Double = 0.0
    var updatedAt: String = "Just now"

    private val chapters = mutableListOf<ScriptChapter>()
    private val retentionWarnings = mutableListOf<String>()

    fun chapter(title: String, timestampEstimate: String = "", block: ChapterBuilder.() -> Unit = {}) {
        val builder = ChapterBuilder(title, timestampEstimate).apply(block)
        chapters.add(builder.build())
    }

    fun warning(warningText: String) {
        retentionWarnings.add(warningText)
    }

    fun build(): Script {
        val computedWordCount = if (wordCount > 0) wordCount else {
            val fullText = "$hook $body $cta " + chapters.flatMap { it.keyPoints }.joinToString(" ")
            fullText.split("\\s+".toRegex()).filter { it.isNotBlank() }.size
        }
        val computedDuration = if (estimatedDurationMinutes > 0.0) estimatedDurationMinutes else {
            // Average reading/speaking rate is ~140 words per minute
            (computedWordCount / 140.0 * 10).toInt() / 10.0
        }

        return Script(
            id = if (id != 0L) id else ClockUtils.currentTimestampId(),
            title = title,
            hook = hook,
            body = body,
            cta = cta,
            wordCount = computedWordCount,
            estimatedDurationMinutes = computedDuration,
            chapters = chapters.toList(),
            retentionWarnings = retentionWarnings.toList(),
            updatedAt = updatedAt
        )
    }
}

@ScriptDslMarker
class ChapterBuilder(
    private val title: String,
    private val timestampEstimate: String
) {
    private val keyPoints = mutableListOf<String>()
    private var customId: String? = null

    fun id(custom: String) {
        customId = custom
    }

    fun point(pointText: String) {
        keyPoints.add(pointText)
    }

    fun build(): ScriptChapter {
        return ScriptChapter(
            id = customId ?: "ch_${title.lowercase().replace("[^a-z0-9]".toRegex(), "_")}",
            title = title,
            timestampEstimate = timestampEstimate,
            keyPoints = keyPoints.toList()
        )
    }
}

internal object ClockUtils {
    fun currentTimestampId(): Long = kotlinx.datetime.Clock.System.now().toEpochMilliseconds()
}

/**
 * Top-level idiomatic Kotlin DSL entry point for creating Creator Studio scripts
 */
fun creatorScript(block: ScriptBuilder.() -> Unit): Script {
    return ScriptBuilder().apply(block).build()
}

