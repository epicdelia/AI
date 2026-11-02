package com.techunicorn.studio.ui.viewmodels

import androidx.compose.ui.geometry.Offset
import com.techunicorn.studio.data.repository.StudioRepository
import com.techunicorn.studio.domain.models.DealRoomData
import com.techunicorn.studio.domain.models.DealTier
import com.techunicorn.studio.platform.haptics.HapticEngine
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.launchIn
import kotlinx.coroutines.flow.onEach
import kotlinx.coroutines.launch

data class StrokePath(
    val points: List<Offset>
)

data class DealRoomUiState(
    val dealRoomData: DealRoomData? = null,
    val selectedTierIndex: Int = 1, // Default Growth ($12,000)
    val customDeliverables: Set<String> = emptySet(),
    val computedTotalAmount: Double = 12000.0,
    val signerFullName: String = "",
    val signerTitle: String = "",
    val strokes: List<StrokePath> = emptyList(),
    val isDpaAccepted: Boolean = false,
    val isSigningInProgress: Boolean = false,
    val isDealLockedAndSigned: Boolean = false,
    val stripeCheckoutUrl: String? = null
)

sealed interface DealRoomEvent {
    data class ShowToast(val message: String) : DealRoomEvent
    data object SignatureCleared : DealRoomEvent
    data object DealSignedSuccessfully : DealRoomEvent
}

class DealRoomViewModel(
    private val repository: StudioRepository,
    private val haptics: HapticEngine
) : BaseViewModel<DealRoomUiState, DealRoomEvent>(DealRoomUiState()) {

    init {
        repository.dealRoomData
            .onEach { data ->
                if (data != null) {
                    val defaultTier = data.tiers.getOrNull(1) ?: data.tiers.firstOrNull()
                    updateState {
                        it.copy(
                            dealRoomData = data,
                            computedTotalAmount = defaultTier?.amount ?: 12000.0
                        )
                    }
                }
            }
            .launchIn(viewModelScope)
    }

    fun selectTier(index: Int, tier: DealTier) {
        haptics.click()
        updateState {
            it.copy(
                selectedTierIndex = index,
                computedTotalAmount = tier.amount
            )
        }
    }

    fun addStroke(stroke: StrokePath) {
        updateState { it.copy(strokes = it.strokes + stroke) }
    }

    fun clearSignature() {
        haptics.lightTick()
        updateState { it.copy(strokes = emptyList()) }
        sendEvent(DealRoomEvent.SignatureCleared)
    }

    fun setSignerInfo(name: String, title: String) {
        updateState { it.copy(signerFullName = name, signerTitle = title) }
    }

    fun toggleDpa(accepted: Boolean) {
        haptics.lightTick()
        updateState { it.copy(isDpaAccepted = accepted) }
    }

    fun completeContractSigning() {
        val state = uiState.value
        if (state.signerFullName.isBlank()) {
            sendEvent(DealRoomEvent.ShowToast("Please enter your legal signatory full name."))
            haptics.error()
            return
        }
        if (state.strokes.isEmpty()) {
            sendEvent(DealRoomEvent.ShowToast("Please draw your signature in the signature canvas."))
            haptics.error()
            return
        }
        if (!state.isDpaAccepted) {
            sendEvent(DealRoomEvent.ShowToast("Please accept the GDPR Data Processing Agreement."))
            haptics.error()
            return
        }

        viewModelScope.launch {
            haptics.heavy()
            updateState { it.copy(isSigningInProgress = true) }
            delay(1500) // Cryptographic signature stamping simulation
            updateState {
                it.copy(
                    isSigningInProgress = false,
                    isDealLockedAndSigned = true,
                    stripeCheckoutUrl = "https://checkout.stripe.com/c/pay/cs_live_studio_retainer"
                )
            }
            haptics.success()
            sendEvent(DealRoomEvent.DealSignedSuccessfully)
            sendEvent(DealRoomEvent.ShowToast("Deal legally signed and encrypted. Stripe payment ready!"))
        }
    }
}
