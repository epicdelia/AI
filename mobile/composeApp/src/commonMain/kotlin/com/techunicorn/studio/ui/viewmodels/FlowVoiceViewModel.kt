package com.techunicorn.studio.ui.viewmodels

import com.techunicorn.studio.data.api.StudioAiClient
import com.techunicorn.studio.data.api.VoicePolishResult
import com.techunicorn.studio.data.repository.StudioRepository
import com.techunicorn.studio.domain.models.ScriptChapter
import com.techunicorn.studio.platform.audio.AudioEngine
import com.techunicorn.studio.platform.audio.AudioSample
import com.techunicorn.studio.platform.audio.RecordingState
import com.techunicorn.studio.platform.haptics.HapticEngine
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.launchIn
import kotlinx.coroutines.flow.onEach
import kotlinx.coroutines.launch

data class FlowVoiceUiState(
    val recordingState: RecordingState = RecordingState.IDLE,
    val currentSample: AudioSample = AudioSample(0f),
    val recordedDurationMillis: Long = 0L,
    val waveformHistory: List<Float> = emptyList(),
    val rawTranscription: String = "",
    val polishedHook: String = "",
    val polishedAngle: String = "",
    val chapters: List<ScriptChapter> = emptyList(),
    val xThreadOpener: String = "",
    val linkedInTakeaway: String = "",
    val isPolishing: Boolean = false
)

sealed interface FlowVoiceEvent {
    data class ShowToast(val message: String) : FlowVoiceEvent
    data object VoiceSavedAsIdea : FlowVoiceEvent
}

class FlowVoiceViewModel(
    private val audioEngine: AudioEngine,
    private val repository: StudioRepository,
    private val aiClient: StudioAiClient,
    private val haptics: HapticEngine
) : BaseViewModel<FlowVoiceUiState, FlowVoiceEvent>(FlowVoiceUiState()) {

    init {
        audioEngine.recordingState
            .onEach { state -> updateState { it.copy(recordingState = state) } }
            .launchIn(viewModelScope)

        audioEngine.currentSample
            .onEach { sample -> updateState { it.copy(currentSample = sample) } }
            .launchIn(viewModelScope)

        audioEngine.recordedDurationMillis
            .onEach { dur -> updateState { it.copy(recordedDurationMillis = dur) } }
            .launchIn(viewModelScope)

        audioEngine.audioWaveformHistory
            .onEach { hist -> updateState { it.copy(waveformHistory = hist) } }
            .launchIn(viewModelScope)
    }

    fun startRecording() {
        haptics.heavy()
        audioEngine.startRecording()
        updateState {
            it.copy(
                rawTranscription = "",
                polishedHook = "",
                polishedAngle = ""
            )
        }
    }

    fun pauseOrResume() {
        haptics.click()
        if (uiState.value.recordingState == RecordingState.RECORDING) {
            audioEngine.pauseRecording()
        } else if (uiState.value.recordingState == RecordingState.PAUSED) {
            audioEngine.resumeRecording()
        }
    }

    fun stopAndPolish() {
        haptics.success()
        audioEngine.stopRecording { duration ->
            simulateTranscriptionAndAiPolish(duration)
        }
    }

    private fun simulateTranscriptionAndAiPolish(durationMillis: Long) {
        viewModelScope.launch {
            val transcript = "I was thinking about how developer tools are changing with AI swarms... like, instead of building another copilot, what if the entire creator pipeline from script to teleprompter to brand contract was handled natively in Kotlin?"
            updateState {
                it.copy(
                    recordingState = RecordingState.PROCESSING_STT,
                    rawTranscription = transcript
                )
            }
            delay(500)

            updateState {
                it.copy(
                    recordingState = RecordingState.POLISHING_AI,
                    isPolishing = true
                )
            }

            // Real Gemini / Creator Studio Polish Call
            val polishResult = aiClient.polishVoiceTranscript(transcript)

            updateState {
                it.copy(
                    recordingState = RecordingState.IDLE,
                    isPolishing = false,
                    polishedHook = polishResult.title,
                    polishedAngle = polishResult.angle,
                    chapters = polishResult.chapters,
                    xThreadOpener = polishResult.xThreadOpener,
                    linkedInTakeaway = polishResult.linkedInTakeaway
                )
            }
            haptics.success()
            sendEvent(FlowVoiceEvent.ShowToast("Gemini Voice Polish & Multi-Platform Cascade ready!"))
        }
    }

    fun saveAsYouTubeIdea() {
        val state = uiState.value
        val title = state.polishedHook.ifBlank { "Untitled Voice Idea" }
        val angle = state.polishedAngle.ifBlank { state.rawTranscription }
        val hook = state.polishedHook.ifBlank { "Voice Memo: ${(state.recordedDurationMillis / 1000)}s" }

        viewModelScope.launch {
            repository.createIdea(title, angle, hook)
            haptics.success()
            sendEvent(FlowVoiceEvent.ShowToast("Idea saved to Studio Idea Bank!"))
            sendEvent(FlowVoiceEvent.VoiceSavedAsIdea)
            audioEngine.reset()
            updateState { FlowVoiceUiState() }
        }
    }

    fun discard() {
        haptics.lightTick()
        audioEngine.reset()
        updateState { FlowVoiceUiState() }
    }
}

