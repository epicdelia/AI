package com.techunicorn.studio.ui.viewmodels

import com.techunicorn.studio.data.repository.StudioRepository
import com.techunicorn.studio.domain.models.Breakout
import com.techunicorn.studio.domain.models.Idea
import com.techunicorn.studio.platform.haptics.HapticEngine
import kotlinx.coroutines.FlowPreview
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch

data class YouTubeHubUiState(
    val selectedTab: Int = 0, // 0 = Outliers, 1 = Idea Bank
    val searchQuery: String = "",
    val minMultiplier: Double = 0.0, // 0.0 = All, 2.0 = 2x+, 4.0 = 4x+
    val allBreakouts: List<Breakout> = emptyList(),
    val filteredBreakouts: List<Breakout> = emptyList(),
    val ideas: List<Idea> = emptyList(),
    val isLoading: Boolean = false
)

sealed interface YouTubeHubEvent {
    data class ShowToast(val message: String) : YouTubeHubEvent
    data class UndoAvailable(val ideaId: Long, val previousStatus: String) : YouTubeHubEvent
}

@OptIn(FlowPreview::class)
class YouTubeHubViewModel(
    private val repository: StudioRepository,
    private val haptics: HapticEngine
) : BaseViewModel<YouTubeHubUiState, YouTubeHubEvent>(YouTubeHubUiState()) {

    private val searchQueryFlow = MutableStateFlow("")
    private val multiplierFilterFlow = MutableStateFlow(0.0)

    init {
        // Observe repository breakouts
        repository.breakouts
            .onEach { list ->
                updateState { it.copy(allBreakouts = list) }
                applyFilters(searchQueryFlow.value, multiplierFilterFlow.value, list)
            }
            .launchIn(viewModelScope)

        // Observe repository ideas
        repository.ideas
            .onEach { list -> updateState { it.copy(ideas = list) } }
            .launchIn(viewModelScope)

        // Debounced search query flow
        searchQueryFlow
            .debounce(250)
            .distinctUntilChanged()
            .onEach { query ->
                applyFilters(query, multiplierFilterFlow.value, uiState.value.allBreakouts)
            }
            .launchIn(viewModelScope)

        multiplierFilterFlow
            .onEach { multiplier ->
                applyFilters(searchQueryFlow.value, multiplier, uiState.value.allBreakouts)
            }
            .launchIn(viewModelScope)
    }

    fun selectTab(tabIndex: Int) {
        haptics.lightTick()
        updateState { it.copy(selectedTab = tabIndex) }
    }

    fun updateSearchQuery(query: String) {
        updateState { it.copy(searchQuery = query) }
        searchQueryFlow.value = query
    }

    fun setMultiplierFilter(minMultiplier: Double) {
        haptics.click()
        updateState { it.copy(minMultiplier = minMultiplier) }
        multiplierFilterFlow.value = minMultiplier
    }

    private fun applyFilters(query: String, minMultiplier: Double, source: List<Breakout>) {
        val q = query.trim().lowercase()
        val filtered = source.filter { item ->
            val matchesMultiplier = item.multiplier >= minMultiplier
            val matchesQuery = q.isEmpty() ||
                    item.videoTitle.lowercase().contains(q) ||
                    item.channelTitle.lowercase().contains(q)
            matchesMultiplier && matchesQuery
        }.sortedByDescending { it.multiplier }

        updateState { it.copy(filteredBreakouts = filtered) }
    }

    fun greenlightIdea(idea: Idea) {
        haptics.success()
        viewModelScope.launch {
            repository.greenlightIdea(idea.id)
            sendEvent(YouTubeHubEvent.ShowToast("Idea '${idea.title}' greenlit for production!"))
        }
    }

    fun archiveIdea(idea: Idea) {
        haptics.click()
        viewModelScope.launch {
            repository.archiveIdea(idea.id)
            sendEvent(YouTubeHubEvent.ShowToast("Idea archived."))
        }
    }

    fun quickAddIdea(title: String, angle: String, hook: String) {
        haptics.success()
        viewModelScope.launch {
            repository.createIdea(title, angle, hook)
            sendEvent(YouTubeHubEvent.ShowToast("New idea drafted!"))
        }
    }
}

