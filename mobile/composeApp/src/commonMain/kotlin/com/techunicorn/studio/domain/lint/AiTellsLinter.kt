package com.techunicorn.studio.domain.lint

import kotlinx.serialization.Serializable

enum class LintSeverity {
    HARD,
    WARN
}

@Serializable
data class LintFinding(
    val severity: LintSeverity,
    val line: Int,
    val rule: String,
    val match: String
)

@Serializable
data class LintReport(
    val findings: List<LintFinding>,
    val hardCount: Int,
    val warnCount: Int,
    val isClean: Boolean,
    val spokenMinutes: Double,
    val wordCount: Int
)

/**
 * Deterministic AI-tell linter for scripts and posts.
 * Kotlin Multiplatform port of review/ai_tells.py
 */
object AiTellsLinter {

    private val BANNED = listOf(
        "in today's fast-paced world", "game-changer", "game changer", "revolutionizing",
        "revolutionize", "delve", "navigate the landscape", "unprecedented", "harness the power",
        "it's no secret", "in the world of", "buckle up", "let's dive in", "dive into",
        "in conclusion", "tapestry", "testament to", "seamlessly", "elevate your",
        "unlock the power", "unleash", "a deep dive", "ever-evolving", "landscape of"
    )

    private val BANNED_REGEX = Regex(
        "\\b(" + BANNED.joinToString("|") { Regex.escape(it) } + ")\\b",
        RegexOption.IGNORE_CASE
    )

    private val LEVERAGE_VERB_REGEX = Regex(
        "\\bleverag(e|es|ing|ed)\\s+(the|your|our|this|these|AI|it)\\b",
        RegexOption.IGNORE_CASE
    )

    private val NOT_X_ITS_Y_REGEX = Regex(
        "\\b(isn't|is not|it's not|not) (just |only |about )?[^.,;!?]{1,40}[,;] (it's|it is|but)\\b",
        RegexOption.IGNORE_CASE
    )

    private val STOCK_SETUP_REGEX = Regex(
        "\\b(here's the (thing|kicker|catch)|let that sink in|the best part\\?|spoiler( alert)?:|plot twist:|but here's where it gets)",
        RegexOption.IGNORE_CASE
    )

    private val FAKE_SUSPENSE_REGEX = Regex(
        "\\bThe (result|answer|catch|kicker|twist|verdict|secret)\\?",
        RegexOption.IGNORE_CASE
    )

    private val ESSAY_TRANSITIONS_REGEX = Regex(
        "(^|[.!?]\\s+)(Moreover|Furthermore|Additionally|Ultimately|Notably|Importantly),",
        RegexOption.IGNORE_CASE
    )

    private val MISSING_CONTRACTIONS_REGEX = Regex(
        "\\b(I am|you are|we are|they are|do not|does not|did not|is not|are not|was not|it is|that is|there is|cannot|will not|would not|should not|could not|have not|has not)\\b",
        RegexOption.IGNORE_CASE
    )

    private val SIGNATURE_PHRASES = listOf(
        "absolutely insane", "absolutely crazy", "absolutely amazing", "obsessed with",
        "highly recommend", "don't judge me", "life-changing", "it's for science",
        "sketchy", "hello world, hello friends", "the world is your oyster"
    )

    fun lint(text: String): LintReport {
        val findings = mutableListOf<LintFinding>()
        val lines = text.lines()

        for ((index, line) in lines.withIndex()) {
            val lineNum = index + 1

            // 1. HARD: Em dash
            if (line.contains("—")) {
                findings.add(LintFinding(LintSeverity.HARD, lineNum, "em dash", "—"))
            }

            // 2. HARD: Spaced en dash used as em dash
            if (line.contains(" – ")) {
                findings.add(LintFinding(LintSeverity.HARD, lineNum, "spaced en dash", " – "))
            }

            // 3. HARD: Banned phrases
            BANNED_REGEX.findAll(line).forEach { m ->
                findings.add(LintFinding(LintSeverity.HARD, lineNum, "banned phrase", m.value.trim()))
            }

            // 4. HARD: Leverage as a verb
            LEVERAGE_VERB_REGEX.findAll(line).forEach { m ->
                findings.add(LintFinding(LintSeverity.HARD, lineNum, "'leverage' as verb", m.value.trim()))
            }

            // 5. WARN: Reversal pattern
            NOT_X_ITS_Y_REGEX.findAll(line).forEach { m ->
                findings.add(LintFinding(LintSeverity.WARN, lineNum, "'not X, it's Y' reversal", m.value.trim()))
            }

            // 6. WARN: Stock setups
            STOCK_SETUP_REGEX.findAll(line).forEach { m ->
                findings.add(LintFinding(LintSeverity.WARN, lineNum, "stock setup phrase", m.value.trim()))
            }

            // 7. WARN: Fake suspense
            FAKE_SUSPENSE_REGEX.findAll(line).forEach { m ->
                findings.add(LintFinding(LintSeverity.WARN, lineNum, "fake-suspense question", m.value.trim()))
            }

            // 8. WARN: Essay transitions
            ESSAY_TRANSITIONS_REGEX.findAll(line).forEach { m ->
                findings.add(LintFinding(LintSeverity.WARN, lineNum, "essay transition", m.value.trim()))
            }

            // 9. WARN: Missing contractions
            MISSING_CONTRACTIONS_REGEX.findAll(line).forEach { m ->
                findings.add(LintFinding(LintSeverity.WARN, lineNum, "missing contraction", m.value.trim()))
            }
        }

        // Global warnings
        val lowerText = text.lowercase()
        val signatureCount = SIGNATURE_PHRASES.sumOf { phrase ->
            var count = 0
            var idx = 0
            while (lowerText.indexOf(phrase, idx).also { idx = it } != -1) {
                count++
                idx += phrase.length
            }
            count
        }
        if (signatureCount > 3) {
            findings.add(
                LintFinding(
                    LintSeverity.WARN,
                    0,
                    "signature phrases overused",
                    "$signatureCount signature phrases found (cap is 3)"
                )
            )
        }

        val words = text.split(Regex("\\s+")).filter { it.isNotBlank() }.size
        val spokenMinutes = words / 150.0
        val hardCount = findings.count { it.severity == LintSeverity.HARD }
        val warnCount = findings.count { it.severity == LintSeverity.WARN }

        return LintReport(
            findings = findings,
            hardCount = hardCount,
            warnCount = warnCount,
            isClean = findings.isEmpty(),
            spokenMinutes = spokenMinutes,
            wordCount = words
        )
    }

    /**
     * Auto-correct common AI tells:
     * - Replaces em dashes with appropriate punctuation (commas, dashes)
     * - Fixes uncontracted words to sound conversational
     * - Replaces banned words with authentic equivalents
     */
    fun humanize(text: String): String {
        var clean = text
            .replace("—", ", ")
            .replace(" – ", ", ")
            .replace(Regex("\\bdelve into\\b", RegexOption.IGNORE_CASE), "explore")
            .replace(Regex("\\bdelve\\b", RegexOption.IGNORE_CASE), "dig")
            .replace(Regex("\\bgame-changer\\b", RegexOption.IGNORE_CASE), "big shift")
            .replace(Regex("\\bgame changer\\b", RegexOption.IGNORE_CASE), "big shift")
            .replace(Regex("\\btestament to\\b", RegexOption.IGNORE_CASE), "proof of")
            .replace(Regex("\\btapestry\\b", RegexOption.IGNORE_CASE), "collection")
            .replace(Regex("\\bseamlessly\\b", RegexOption.IGNORE_CASE), "smoothly")
            .replace(Regex("\\bIt is\\b"), "It's")
            .replace(Regex("\\bit is\\b"), "it's")
            .replace(Regex("\\bDo not\\b"), "Don't")
            .replace(Regex("\\bdo not\\b"), "don't")
            .replace(Regex("\\bDoes not\\b"), "Doesn't")
            .replace(Regex("\\bdoes not\\b"), "doesn't")
            .replace(Regex("\\bCannot\\b"), "Can't")
            .replace(Regex("\\bcannot\\b"), "can't")
            .replace(Regex("\\bYou are\\b"), "You're")
            .replace(Regex("\\byou are\\b"), "you're")
            .replace(Regex("\\bWe are\\b"), "We're")
            .replace(Regex("\\bwe are\\b"), "we're")

        return clean
    }
}

