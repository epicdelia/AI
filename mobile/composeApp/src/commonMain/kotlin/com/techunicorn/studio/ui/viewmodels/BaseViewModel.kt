package com.techunicorn.studio.ui.viewmodels

import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed interface UiStatus {
    data object Idle : UiStatus
    data object Loading : UiStatus
    data object Success : UiStatus
    data class Error(val message: String) : UiStatus
}

abstract class BaseViewModel<STATE, EVENT>(initialState: STATE) {
    protected val viewModelJob = SupervisorJob()
    protected val viewModelScope = CoroutineScope(Dispatchers.Default + viewModelJob)

    private val _uiState = MutableStateFlow(initialState)
    val uiState: StateFlow<STATE> = _uiState.asStateFlow()

    private val _uiEvents = MutableSharedFlow<EVENT>(extraBufferCapacity = 64)
    val uiEvents: SharedFlow<EVENT> = _uiEvents.asSharedFlow()

    protected fun updateState(reducer: (STATE) -> STATE) {
        _uiState.value = reducer(_uiState.value)
    }

    protected fun sendEvent(event: EVENT) {
        viewModelScope.launch {
            _uiEvents.emit(event)
        }
    }

    open fun onCleared() {
        viewModelJob.cancel()
    }
}

