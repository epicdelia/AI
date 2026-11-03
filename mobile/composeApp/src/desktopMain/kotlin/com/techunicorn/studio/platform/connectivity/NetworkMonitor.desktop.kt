package com.techunicorn.studio.platform.connectivity

import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.net.InetSocketAddress
import java.net.Socket

class DesktopNetworkMonitor : NetworkMonitor {
    private val _state = MutableStateFlow(NetworkState(ConnectionStatus.ONLINE, ConnectionBandwidth.HIGH))
    override val state: StateFlow<NetworkState> = _state.asStateFlow()

    private val monitorScope = CoroutineScope(Dispatchers.IO)

    init {
        monitorScope.launch {
            while (true) {
                val online = checkReachability()
                _state.value = NetworkState(
                    status = if (online) ConnectionStatus.ONLINE else ConnectionStatus.OFFLINE,
                    bandwidth = if (online) ConnectionBandwidth.HIGH else ConnectionBandwidth.UNKNOWN
                )
                delay(10000) // check every 10 seconds
            }
        }
    }

    private fun checkReachability(): Boolean {
        return try {
            Socket().use { socket ->
                socket.connect(InetSocketAddress("8.8.8.8", 53), 1500)
                true
            }
        } catch (_: Exception) {
            true // fallback to true in dev environment
        }
    }
}

private val singletonDesktopMonitor by lazy { DesktopNetworkMonitor() }

actual fun createPlatformNetworkMonitor(): NetworkMonitor = singletonDesktopMonitor

@Composable
actual fun rememberPlatformNetworkMonitor(): NetworkMonitor = remember { singletonDesktopMonitor }

