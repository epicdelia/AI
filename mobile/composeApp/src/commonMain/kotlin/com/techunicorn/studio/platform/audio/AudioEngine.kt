package com.techunicorn.studio.platform.audio

import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlin.random.Random

enum class RecordingState {
    IDLE,
    RECORDING,
    PAUSED,
    PROCESSING_STT,
    POLISHING_AI
}

data class AudioSample(
    val amplitude: Float, // Normalized 0.0f to 1.0f
    val frequencyBands: List<Float> = emptyList(), // 8-16 bar visualizer spectrum
    val timestampMillis: Long = 0L
)

interface AudioEngine {
    val recordingState: StateFlow<RecordingState>
    val currentSample: StateFlow<AudioSample>
    val recordedDurationMillis: StateFlow<Long>
    val audioWaveformHistory: StateFlow<List<Float>>

    fun startRecording()
    fun pauseRecording()
    fun resumeRecording()
    fun stopRecording(onComplete: (durationMillis: Long) -> Unit)
    fun reset()
}

/**
 * High-fidelity Audio Sampler for mobile voice dictation and studio audio visualization.
 * Produces smooth natural audio dynamics with harmonic oscillation and simulated voice pulses.
 */
class StudioAudioEngine(
    private val scope: CoroutineScope = CoroutineScope(Dispatchers.Default)
) : AudioEngine {
    private val _recordingState = MutableStateFlow(RecordingState.IDLE)
    override val recordingState: StateFlow<RecordingState> = _recordingState.asStateFlow()

    private val _currentSample = MutableStateFlow(AudioSample(0f, List(16) { 0.1f }))
    override val currentSample: StateFlow<AudioSample> = _currentSample.asStateFlow()

    private val _recordedDurationMillis = MutableStateFlow(0L)
    override val recordedDurationMillis: StateFlow<Long> = _recordedDurationMillis.asStateFlow()

    private val _audioWaveformHistory = MutableStateFlow<List<Float>>(emptyList())
    override val audioWaveformHistory: StateFlow<List<Float>> = _audioWaveformHistory.asStateFlow()

    private var samplingJob: Job? = null
    private var startTimeMillis: Long = 0L
    private var accumulatedDuration: Long = 0L

    override fun startRecording() {
        if (_recordingState.value == RecordingState.RECORDING) return
        _recordingState.value = RecordingState.RECORDING
        startTimeMillis = kotlinx.datetime.Clock.System.now().toEpochMilliseconds()
        _audioWaveformHistory.value = emptyList()

        startSamplingLoop()
    }

    override fun pauseRecording() {
        if (_recordingState.value != RecordingState.RECORDING) return
        _recordingState.value = RecordingState.PAUSED
        val now = kotlinx.datetime.Clock.System.now().toEpochMilliseconds()
        accumulatedDuration += (now - startTimeMillis)
        samplingJob?.cancel()
    }

    override fun resumeRecording() {
        if (_recordingState.value != RecordingState.PAUSED) return
        _recordingState.value = RecordingState.RECORDING
        startTimeMillis = kotlinx.datetime.Clock.System.now().toEpochMilliseconds()
        startSamplingLoop()
    }

    override fun stopRecording(onComplete: (durationMillis: Long) -> Unit) {
        val now = kotlinx.datetime.Clock.System.now().toEpochMilliseconds()
        if (_recordingState.value == RecordingState.RECORDING) {
            accumulatedDuration += (now - startTimeMillis)
        }
        samplingJob?.cancel()
        val total = accumulatedDuration
        _recordingState.value = RecordingState.PROCESSING_STT
        onComplete(total)
    }

    override fun reset() {
        samplingJob?.cancel()
        _recordingState.value = RecordingState.IDLE
        accumulatedDuration = 0L
        _recordedDurationMillis.value = 0L
        _currentSample.value = AudioSample(0f, List(16) { 0.1f })
        _audioWaveformHistory.value = emptyList()
    }

    private fun startSamplingLoop() {
        samplingJob?.cancel()
        samplingJob = scope.launch {
            var step = 0
            while (isActive && _recordingState.value == RecordingState.RECORDING) {
                step++
                val now = kotlinx.datetime.Clock.System.now().toEpochMilliseconds()
                val currentDuration = accumulatedDuration + (now - startTimeMillis)
                _recordedDurationMillis.value = currentDuration

                // Natural human voice speech dynamics simulation
                val baseCadence = kotlin.math.sin(step * 0.18).toFloat().coerceAtLeast(0f)
                val jitter = Random.nextFloat() * 0.25f
                val voiceAmplitude = ((baseCadence * 0.65f) + jitter).coerceIn(0.08f, 0.95f)

                // Generate 16 frequency bands for the spectrum analyzer
                val bands = List(16) { idx ->
                    val freqFactor = kotlin.math.sin((step * 0.25) + (idx * 0.4)).toFloat()
                    ((voiceAmplitude * (0.6f + (freqFactor * 0.4f)))).coerceIn(0.05f, 1.0f)
                }

                _currentSample.value = AudioSample(
                    amplitude = voiceAmplitude,
                    frequencyBands = bands,
                    timestampMillis = now
                )

                // Append to waveform history (keep last 40 samples for scrolling waveform)
                val currentHistory = _audioWaveformHistory.value
                val updatedHistory = if (currentHistory.size >= 45) {
                    currentHistory.drop(1) + voiceAmplitude
                } else {
                    currentHistory + voiceAmplitude
                }
                _audioWaveformHistory.value = updatedHistory

                delay(50) // 20 updates per second (50ms interval) for silky smooth 60fps animations
            }
        }
    }
}

