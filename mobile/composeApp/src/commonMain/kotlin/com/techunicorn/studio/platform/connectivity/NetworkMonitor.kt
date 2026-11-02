package com.techunicorn.studio.platform.connectivity

import androidx.compose.runtime.Composable
import kotlinx.coroutines.flow.StateFlow

enum class ConnectionStatus {
    ONLINE,
    OFFLINE
}

enum class ConnectionBandwidth {
    HIGH,    // Wi-Fi or fast broadband
    MEDIUM,  // 4G/5G Cellular
    LOW,     // 2G/3G or degraded
    UNKNOWN
}

data class NetworkState(
    val status: ConnectionStatus = ConnectionStatus.ONLINE,
    val bandwidth: ConnectionBandwidth = ConnectionBandwidth.HIGH,
    val isMetered: Boolean = false
) {
    val isOnline: Boolean get() = status == ConnectionStatus.ONLINE
}

interface NetworkMonitor {
    val state: StateFlow<NetworkState>
    val isOnline: Boolean get() = state.value.isOnline
}

expect fun createPlatformNetworkMonitor(): NetworkMonitor

@Composable
expect fun rememberPlatformNetworkMonitor(): NetworkMonitor

