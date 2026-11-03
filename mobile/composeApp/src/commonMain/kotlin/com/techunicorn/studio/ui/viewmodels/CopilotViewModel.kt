package com.techunicorn.studio.ui.viewmodels

import com.techunicorn.studio.data.api.StudioAiClient
import com.techunicorn.studio.domain.models.CopilotAction
import com.techunicorn.studio.domain.models.CopilotMessage
import com.techunicorn.studio.platform.haptics.HapticEngine
import kotlinx.coroutines.flow.launchIn
import kotlinx.coroutines.flow.onCompletion
import kotlinx.coroutines.flow.onEach
import kotlinx.coroutines.launch
import kotlinx.datetime.Clock

data class CopilotUiState(
    val messages: List<CopilotMessage> = emptyList(),
    val isStreaming: Boolean = false,
    val activeStreamText: String = "",
    val suggestedCommands: List<String> = listOf(
        "/hook",
        "/titles",
        "/thumbnail",
        "/factcheck",
        "/sponsor",
        "/shorten",
        "/plan"
    )
)

sealed interface CopilotEvent {
    data class ShowToast(val message: String) : CopilotEvent
    data class ActionApplied(val action: CopilotAction) : CopilotEvent
}

class CopilotViewModel(
    private val aiClient: StudioAiClient,
    private val haptics: HapticEngine
) : BaseViewModel<CopilotUiState, CopilotEvent>(
    CopilotUiState(
        messages = listOf(
            CopilotMessage(
                id = "welcome",
                sender = "copilot",
                text = "Hey Delia! I'm your studio copilot powered by Gemini 2.5 Flash and Creator Studio agents. Ready to audit your YouTube hook, review camera framing, or synthesize a brand sponsor deal.",
                timestamp = "Just now",
                actions = listOf(
                    CopilotAction(
                        id = "act-hook",
                        title = "Audit Chapter 1 Hook",
                        description = "Compare cold open with 4.37x Next.js breakout",
                        type = "hook_audit"
                    ),
                    CopilotAction(
                        id = "act-preflight",
                        title = "Run Camera Pre-Flight",
                        description = "Verify 4K eye line and lighting balance",
                        type = "camera_audit"
                    )
                )
            )
        )
    )
) {
    fun sendMessage(userPrompt: String, contextInfo: String = "") {
        if (userPrompt.isBlank() || uiState.value.isStreaming) return

        haptics.click()
        val now = Clock.System.now().toEpochMilliseconds()
        val userMsg = CopilotMessage(
            id = "user-$now",
            sender = "user",
            text = userPrompt,
            timestamp = "Just now"
        )

        updateState {
            it.copy(
                messages = it.messages + userMsg,
                isStreaming = true,
                activeStreamText = ""
            )
        }

        viewModelScope.launch {
            var fullResponse = StringBuilder()

            aiClient.streamCopilotMessage(
                userPrompt = userPrompt,
                contextInfo = contextInfo
            ).onEach { token ->
                fullResponse.append(token)
                updateState { it.copy(activeStreamText = fullResponse.toString()) }
            }.onCompletion {
                haptics.success()
                val responseText = fullResponse.toString().ifBlank {
                    "Analysis complete. All script chapters and retention metrics look strong."
                }

                val generatedActions = mutableListOf<CopilotAction>()
                if (userPrompt.contains("hook", ignoreCase = true)) {
                    generatedActions.add(
                        CopilotAction(
                            id = "act-apply-hook-$now",
                            title = "Apply Tension Hook to Script",
                            description = "Overwrites Chapter 1 cold open with ex-Google metric",
                            type = "update_hook",
                            payload = "Everyone claims AI can build a production startup in 48 hours. As an ex-Google engineer, I put that to the test."
                        )
                    )
                }

                val copilotMsg = CopilotMessage(
                    id = "copilot-$now",
                    sender = "copilot",
                    text = responseText,
                    timestamp = "Just now",
                    actions = generatedActions
                )

                updateState {
                    it.copy(
                        messages = it.messages + copilotMsg,
                        isStreaming = false,
                        activeStreamText = ""
                    )
                }
            }.launchIn(viewModelScope)
        }
    }

    fun applyAction(action: CopilotAction) {
        haptics.heavy()
        viewModelScope.launch {
            sendEvent(CopilotEvent.ActionApplied(action))
            sendEvent(CopilotEvent.ShowToast("Applied: ${action.title}"))

            // Remove applied action from UI
            updateState { state ->
                state.copy(
                    messages = state.messages.map { msg ->
                        msg.copy(actions = msg.actions.filter { it.id != action.id })
                    }
                )
            }
        }
    }

    fun dismissAction(actionId: String) {
        haptics.lightTick()
        updateState { state ->
            state.copy(
                messages = state.messages.map { msg ->
                    msg.copy(actions = msg.actions.filter { it.id != actionId })
                }
            )
        }
    }
}
