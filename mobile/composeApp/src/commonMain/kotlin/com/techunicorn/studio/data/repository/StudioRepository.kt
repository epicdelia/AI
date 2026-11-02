package com.techunicorn.studio.data.repository

import com.techunicorn.studio.data.api.StudioApiClient
import com.techunicorn.studio.data.cache.StudioLocalCache
import com.techunicorn.studio.domain.models.*
import com.techunicorn.studio.domain.result.StudioError
import com.techunicorn.studio.domain.result.StudioResult
import com.techunicorn.studio.platform.connectivity.NetworkMonitor
import kotlinx.coroutines.flow.*

class StudioRepository(
    private val api: StudioApiClient,
    private val cache: StudioLocalCache,
    private val networkMonitor: NetworkMonitor
) {
    // Direct Reactive State from Cache
    val dashboardStats: StateFlow<StudioDashboardStats> = cache.dashboardStats
    val breakouts: StateFlow<List<Breakout>> = cache.breakouts
    val ideas: StateFlow<List<Idea>> = cache.ideas
    val scripts: StateFlow<List<Script>> = cache.scripts
    val posts: StateFlow<List<SocialPost>> = cache.posts
    val deals: StateFlow<List<Deal>> = cache.deals
    val clientRequests: StateFlow<List<ClientRequest>> = cache.clientRequests
    val brandVault: StateFlow<BrandVault> = cache.brandVault
    val dealRoomData: StateFlow<DealRoomData?> = cache.dealRoomData

    /**
     * Offline-first sync flow:
     * 1. Emits cache immediately if available.
     * 2. If online, fetches fresh data from network.
     * 3. Updates cache and emits fresh data.
     * 4. If offline or error occurs, preserves cached data and emits fallback.
     */
    fun getDashboardStatsFlow(forceRefresh: Boolean = false): Flow<StudioResult<StudioDashboardStats>> = flow {
        emit(StudioResult.Loading)
        val cached = cache.dashboardStats.value
        emit(StudioResult.Success(cached, isFromCache = true))

        if (networkMonitor.isOnline || forceRefresh) {
            try {
                val fresh = api.getDashboardStats()
                cache.saveDashboardStats(fresh)
                emit(StudioResult.Success(fresh, isFromCache = false))
            } catch (e: Exception) {
                emit(StudioResult.Error(StudioError.Network(e.message ?: "Failed to refresh dashboard stats"), cached))
            }
        }
    }

    fun getBreakoutsFlow(forceRefresh: Boolean = false): Flow<StudioResult<List<Breakout>>> = flow {
        emit(StudioResult.Loading)
        val cached = cache.breakouts.value
        if (cached.isNotEmpty()) {
            emit(StudioResult.Success(cached, isFromCache = true))
        }

        try {
            val fresh = api.getBreakouts()
            cache.saveBreakouts(fresh)
            emit(StudioResult.Success(fresh, isFromCache = false))
        } catch (e: Exception) {
            if (cached.isNotEmpty()) {
                emit(StudioResult.Success(cached, isFromCache = true))
            } else {
                emit(StudioResult.Error(StudioError.Network(e.message ?: "Failed to load breakouts")))
            }
        }
    }

    fun getIdeasFlow(forceRefresh: Boolean = false): Flow<StudioResult<List<Idea>>> = flow {
        emit(StudioResult.Loading)
        val cached = cache.ideas.value
        if (cached.isNotEmpty()) {
            emit(StudioResult.Success(cached, isFromCache = true))
        }

        try {
            val fresh = api.getIdeas()
            cache.saveIdeas(fresh)
            emit(StudioResult.Success(fresh, isFromCache = false))
        } catch (e: Exception) {
            if (cached.isNotEmpty()) {
                emit(StudioResult.Success(cached, isFromCache = true))
            } else {
                emit(StudioResult.Error(StudioError.Network(e.message ?: "Failed to load ideas")))
            }
        }
    }

    fun getScriptsFlow(forceRefresh: Boolean = false): Flow<StudioResult<List<Script>>> = flow {
        emit(StudioResult.Loading)
        val cached = cache.scripts.value
        if (cached.isNotEmpty()) {
            emit(StudioResult.Success(cached, isFromCache = true))
        }

        try {
            val fresh = api.getScripts()
            cache.saveScripts(fresh)
            emit(StudioResult.Success(fresh, isFromCache = false))
        } catch (e: Exception) {
            if (cached.isNotEmpty()) {
                emit(StudioResult.Success(cached, isFromCache = true))
            } else {
                emit(StudioResult.Error(StudioError.Network(e.message ?: "Failed to load scripts")))
            }
        }
    }

    // Optimistic Mutations
    suspend fun greenlightIdea(ideaId: Long) {
        cache.updateIdeaStatus(ideaId, "greenlit")
        try {
            api.greenlightIdea(ideaId)
        } catch (_: Exception) {
            // Keep local state
        }
    }

    suspend fun archiveIdea(ideaId: Long) {
        cache.updateIdeaStatus(ideaId, "archived")
        try {
            api.archiveIdea(ideaId)
        } catch (_: Exception) {
            // Keep local state
        }
    }

    suspend fun createIdea(title: String, angle: String, hook: String): Idea {
        val newIdea = Idea(
            id = kotlinx.datetime.Clock.System.now().toEpochMilliseconds(),
            title = title,
            angle = angle,
            hook = hook,
            status = "inbox"
        )
        cache.addIdea(newIdea)
        return newIdea
    }

    suspend fun saveScript(script: Script) {
        cache.saveOrUpdateScript(script)
    }

    suspend fun initialWarmup() {
        // Hydrate from network or defaults
        try {
            val stats = api.getDashboardStats()
            cache.saveDashboardStats(stats)
            val breakoutsList = api.getBreakouts()
            cache.saveBreakouts(breakoutsList)
            val ideasList = api.getIdeas()
            cache.saveIdeas(ideasList)
            val scriptsList = api.getScripts()
            cache.saveScripts(scriptsList)
            val postsList = api.getSocialPosts()
            cache.savePosts(postsList)
            val dealsList = api.getDeals()
            cache.saveDeals(dealsList)
            val requests = api.getClientRequests()
            cache.saveClientRequests(requests)
            val vault = api.getBrandVault()
            cache.saveBrandVault(vault)
            val dealRoom = api.getDealRoom()
            cache.saveDealRoomData(dealRoom)
            val letters = api.getLoveLetters()
            cache.saveLoveLetters(letters)
            val memory = api.getMemoryJarPrompts()
            cache.saveMemoryJar(memory)
        } catch (_: Exception) {
            // Fallback handled in StudioApiClient
        }
    }
}
