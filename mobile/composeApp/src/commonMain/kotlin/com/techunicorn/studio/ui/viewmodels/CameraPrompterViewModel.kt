package com.techunicorn.studio.ui.viewmodels

import com.techunicorn.studio.data.api.PreFlightAuditResult
import com.techunicorn.studio.data.api.StudioAiClient
import com.techunicorn.studio.domain.models.Script
import com.techunicorn.studio.domain.models.ScriptChapter
import com.techunicorn.studio.platform.haptics.HapticEngine
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

data class CameraPrompterUiState(
    val script: Script? = null,
    val isPlaying: Boolean = false,
    val isMirrored: Boolean = false,
    val scrollSpeedMultiplier: Float = 1.0f, // 0.5x to 4.0x
    val fontSizeSp: Int = 26,
    val scrollProgress: Float = 0.0f, // 0.0 to 1.0
    val elapsedSeconds: Int = 0,
    val wordsReadEstimate: Int = 0,
    val currentWpm: Int = 145,
    val activeChapterIndex: Int = 0,
    val isAuditingPreFlight: Boolean = false,
    val preFlightAudit: PreFlightAuditResult? = null
)

sealed interface CameraPrompterEvent {
    data object FinishReading : CameraPrompterEvent
    data class ChapterAnnounced(val chapterTitle: String) : CameraPrompterEvent
    data class ShowToast(val message: String) : CameraPrompterEvent
}

class CameraPrompterViewModel(
    private val aiClient: StudioAiClient,
    private val haptics: HapticEngine
) : BaseViewModel<CameraPrompterUiState, CameraPrompterEvent>(CameraPrompterUiState()) {

    private var tickerJob: Job? = null

    fun setScript(script: Script) {
        updateState {
            it.copy(
                script = script,
                scrollProgress = 0f,
                elapsedSeconds = 0,
                isPlaying = false
            )
        }
    }

    fun togglePlayPause() {
        haptics.click()
        val newState = !uiState.value.isPlaying
        updateState { it.copy(isPlaying = newState) }
        if (newState) {
            startTicker()
        } else {
            tickerJob?.cancel()
        }
    }

    fun setSpeed(speed: Float) {
        haptics.lightTick()
        updateState { it.copy(scrollSpeedMultiplier = speed.coerceIn(0.5f, 4.0f)) }
    }

    fun toggleMirror() {
        haptics.click()
        updateState { it.copy(isMirrored = !it.isMirrored) }
    }

    fun setFontSize(sizeSp: Int) {
        updateState { it.copy(fontSizeSp = sizeSp.coerceIn(18, 52)) }
    }

    fun updateScrollProgress(progress: Float) {
        updateState {
            val totalWords = it.script?.wordCount ?: 0
            val wordsRead = (totalWords * progress).toInt()
            it.copy(
                scrollProgress = progress.coerceIn(0f, 1f),
                wordsReadEstimate = wordsRead
            )
        }
    }

    fun selectChapter(index: Int, chapter: ScriptChapter) {
        haptics.lightTick()
        updateState { it.copy(activeChapterIndex = index) }
        sendEvent(CameraPrompterEvent.ChapterAnnounced(chapter.title))
    }

    private fun startTicker() {
        tickerJob?.cancel()
        tickerJob = viewModelScope.launch {
            while (isActive && uiState.value.isPlaying) {
                delay(1000)
                updateState {
                    val newElapsed = it.elapsedSeconds + 1
                    val wpm = if (newElapsed > 0) ((it.wordsReadEstimate.toFloat() / newElapsed) * 60).toInt() else 145
                    it.copy(
                        elapsedSeconds = newElapsed,
                        currentWpm = wpm.coerceIn(80, 250)
                    )
                }
            }
        }
    }

    fun runPreFlightAudit(imageBase64: String? = null) {
        haptics.click()
        updateState { it.copy(isAuditingPreFlight = true) }
        viewModelScope.launch {
            val result = aiClient.auditCameraFraming(imageBase64)
            updateState {
                it.copy(
                    isAuditingPreFlight = false,
                    preFlightAudit = result
                )
            }
            haptics.success()
            sendEvent(CameraPrompterEvent.ShowToast("Pre-Flight Check: ${result.framingScore}% Framing Score"))
        }
    }

    override fun onCleared() {
        tickerJob?.cancel()
        super.onCleared()
    }
}

