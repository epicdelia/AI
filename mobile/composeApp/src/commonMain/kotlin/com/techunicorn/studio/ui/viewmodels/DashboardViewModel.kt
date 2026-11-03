package com.techunicorn.studio.ui.viewmodels

import com.techunicorn.studio.data.repository.StudioRepository
import com.techunicorn.studio.domain.models.Breakout
import com.techunicorn.studio.domain.models.StudioDashboardStats
import com.techunicorn.studio.domain.result.StudioResult
import com.techunicorn.studio.platform.connectivity.NetworkMonitor
import com.techunicorn.studio.platform.haptics.HapticEngine
import kotlinx.coroutines.flow.launchIn
import kotlinx.coroutines.flow.onEach
import kotlinx.coroutines.launch

data class DashboardUiState(
    val stats: StudioDashboardStats = StudioDashboardStats(),
    val topBreakouts: List<Breakout> = emptyList(),
    val isRefreshing: Boolean = false,
    val isOffline: Boolean = false,
    val isStreakCelebrated: Boolean = false,
    val errorMessage: String? = null
)

sealed interface DashboardEvent {
    data class ShowMessage(val message: String) : DashboardEvent
    data object HapticSuccess : DashboardEvent
}

class DashboardViewModel(
    private val repository: StudioRepository,
    private val networkMonitor: NetworkMonitor,
    private val haptics: HapticEngine
) : BaseViewModel<DashboardUiState, DashboardEvent>(DashboardUiState()) {

    init {
        // Observe repository stats and breakouts reactive streams
        repository.dashboardStats
            .onEach { stats -> updateState { it.copy(stats = stats) } }
            .launchIn(viewModelScope)

        repository.breakouts
            .onEach { breakouts ->
                val top = breakouts.sortedByDescending { it.multiplier }.take(3)
                updateState { it.copy(topBreakouts = top) }
            }
            .launchIn(viewModelScope)

        // Observe network state
        networkMonitor.state
            .onEach { net -> updateState { it.copy(isOffline = !net.isOnline) } }
            .launchIn(viewModelScope)

        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            updateState { it.copy(isRefreshing = true, errorMessage = null) }
            repository.getDashboardStatsFlow(forceRefresh = true).collect { res ->
                when (res) {
                    is StudioResult.Success -> {
                        updateState { it.copy(stats = res.data, isRefreshing = false) }
                    }
                    is StudioResult.Error -> {
                        updateState { it.copy(isRefreshing = false, errorMessage = res.error.toString()) }
                    }
                    is StudioResult.Loading -> Unit
                }
            }
        }
    }

    fun celebrateStreak() {
        updateState { it.copy(isStreakCelebrated = true) }
        haptics.success()
        sendEvent(DashboardEvent.ShowMessage("🔥 8-Week Creator Streak: Accountability verified!"))
        sendEvent(DashboardEvent.HapticSuccess)
    }
}

