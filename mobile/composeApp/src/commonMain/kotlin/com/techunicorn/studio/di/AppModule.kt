package com.techunicorn.studio.di

import com.techunicorn.studio.data.api.StudioApiClient
import com.techunicorn.studio.data.cache.StudioLocalCache
import com.techunicorn.studio.data.repository.StudioRepository
import com.techunicorn.studio.platform.audio.AudioEngine
import com.techunicorn.studio.platform.audio.StudioAudioEngine
import com.techunicorn.studio.platform.connectivity.NetworkMonitor
import com.techunicorn.studio.platform.connectivity.createPlatformNetworkMonitor
import com.techunicorn.studio.platform.haptics.HapticEngine
import com.techunicorn.studio.platform.haptics.getPlatformHapticEngine
import com.techunicorn.studio.ui.viewmodels.*
import org.koin.core.context.startKoin
import org.koin.core.module.Module
import org.koin.dsl.module

val appModule: Module = module {
    // Singletons - Data & Platform Infrastructure
    single<StudioApiClient> { StudioApiClient() }
    single<StudioLocalCache> { StudioLocalCache() }
    single<NetworkMonitor> { createPlatformNetworkMonitor() }
    single<HapticEngine> { getPlatformHapticEngine() }
    single<AudioEngine> { StudioAudioEngine() }

    // Repository
    single<StudioRepository> {
        StudioRepository(
            api = get(),
            cache = get(),
            networkMonitor = get()
        )
    }

    // ViewModels / Presenters
    factory { DashboardViewModel(repository = get(), networkMonitor = get(), haptics = get()) }
    factory { YouTubeHubViewModel(repository = get(), haptics = get()) }
    factory { CameraPrompterViewModel(haptics = get()) }
    factory { FlowVoiceViewModel(audioEngine = get(), repository = get(), haptics = get()) }
    factory { DealRoomViewModel(repository = get(), haptics = get()) }
}

private var isKoinInitialized = false

fun initKoin() {
    if (!isKoinInitialized) {
        startKoin {
            modules(appModule)
        }
        isKoinInitialized = true
    }
}

