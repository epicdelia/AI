package com.techunicorn.studio.platform.connectivity

import android.content.Context
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import android.net.NetworkRequest
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.platform.LocalContext
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

class AndroidNetworkMonitor(context: Context) : NetworkMonitor {
    private val connectivityManager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as? ConnectivityManager
    private val _state = MutableStateFlow(computeInitialState())
    override val state: StateFlow<NetworkState> = _state.asStateFlow()

    init {
        val request = NetworkRequest.Builder()
            .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
            .build()

        try {
            connectivityManager?.registerNetworkCallback(request, object : ConnectivityManager.NetworkCallback() {
                override fun onAvailable(network: Network) {
                    updateState()
                }

                override fun onLost(network: Network) {
                    updateState()
                }

                override fun onCapabilitiesChanged(network: Network, networkCapabilities: NetworkCapabilities) {
                    updateState()
                }
            })
        } catch (_: Exception) {
            // Permission or system failure fallback
        }
    }

    private fun computeInitialState(): NetworkState {
        val cm = connectivityManager ?: return NetworkState()
        val activeNetwork = cm.activeNetwork ?: return NetworkState(status = ConnectionStatus.OFFLINE)
        val caps = cm.getNetworkCapabilities(activeNetwork) ?: return NetworkState(status = ConnectionStatus.OFFLINE)

        val isOnline = caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
        val bandwidth = when {
            caps.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) || caps.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET) ->
                ConnectionBandwidth.HIGH
            caps.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) ->
                ConnectionBandwidth.MEDIUM
            else -> ConnectionBandwidth.LOW
        }
        val isMetered = !caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_NOT_METERED)

        return NetworkState(
            status = if (isOnline) ConnectionStatus.ONLINE else ConnectionStatus.OFFLINE,
            bandwidth = bandwidth,
            isMetered = isMetered
        )
    }

    private fun updateState() {
        _state.value = computeInitialState()
    }
}

private var appContext: Context? = null

fun initAndroidNetworkMonitor(context: Context) {
    appContext = context.applicationContext
}

actual fun createPlatformNetworkMonitor(): NetworkMonitor {
    val ctx = appContext
    return if (ctx != null) AndroidNetworkMonitor(ctx) else object : NetworkMonitor {
        override val state: StateFlow<NetworkState> = MutableStateFlow(NetworkState())
    }
}

@Composable
actual fun rememberPlatformNetworkMonitor(): NetworkMonitor {
    val context = LocalContext.current
    return remember(context) { AndroidNetworkMonitor(context) }
}

