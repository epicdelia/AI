package com.techunicorn.studio.domain.models

import kotlin.jvm.JvmInline

@JvmInline
value class CurrencyAmount(val cents: Long) {
    val dollars: Double get() = cents / 100.0

    fun format(currencyCode: String = "USD"): String {
        val symbol = when (currencyCode.uppercase()) {
            "USD" -> "$"
            "EUR" -> "€"
            "GBP" -> "£"
            else -> "$currencyCode "
        }
        val formattedDollars = if (cents % 100 == 0L) {
            "${cents / 100}"
        } else {
            val d = dollars
            val intPart = d.toLong()
            val decPart = (cents % 100).toString().padStart(2, '0')
            "$intPart.$decPart"
        }
        return "$symbol$formattedDollars"
    }

    companion object {
        fun fromDollars(dollars: Double): CurrencyAmount = CurrencyAmount((dollars * 100).toLong())
    }
}

@JvmInline
value class ReadingSpeed(val wordsPerMinute: Int = 140) {
    fun calculateDurationMinutes(wordCount: Int): Double {
        if (wordsPerMinute <= 0 || wordCount <= 0) return 0.0
        return (wordCount.toDouble() / wordsPerMinute * 10).toInt() / 10.0
    }

    fun formatTimeEstimate(wordCount: Int): String {
        val totalSec = ((wordCount.toDouble() / wordsPerMinute) * 60).toInt()
        val minutes = totalSec / 60
        val seconds = totalSec % 60
        return if (minutes > 0) "${minutes}m ${seconds}s" else "${seconds}s"
    }
}

// Extension functions for domain models
fun Long.formatMetricCompact(): String {
    return when {
        this >= 1_000_000 -> "${(this / 100_000).toDouble() / 10}M"
        this >= 1_000 -> "${(this / 100).toDouble() / 10}K"
        else -> this.toString()
    }
}

fun Double.formatMultiplier(): String = "${((this * 100).toInt()) / 100.0}x"

fun Script.readingPaceEstimate(wpm: Int = 140): String {
    return ReadingSpeed(wpm).formatTimeEstimate(this.wordCount)
}

fun List<Breakout>.filterHighOutliers(thresholdMultiplier: Double = 2.0): List<Breakout> {
    return this.filter { it.multiplier >= thresholdMultiplier }
        .sortedByDescending { it.multiplier }
}

