package com.techunicorn.studio.data.cache

import com.techunicorn.studio.domain.models.*
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock

class StudioLocalCache {
    private val mutex = Mutex()

    private val _dashboardStats = MutableStateFlow(StudioDashboardStats())
    val dashboardStats: StateFlow<StudioDashboardStats> = _dashboardStats.asStateFlow()

    private val _breakouts = MutableStateFlow<List<Breakout>>(emptyList())
    val breakouts: StateFlow<List<Breakout>> = _breakouts.asStateFlow()

    private val _ideas = MutableStateFlow<List<Idea>>(emptyList())
    val ideas: StateFlow<List<Idea>> = _ideas.asStateFlow()

    private val _scripts = MutableStateFlow<List<Script>>(emptyList())
    val scripts: StateFlow<List<Script>> = _scripts.asStateFlow()

    private val _posts = MutableStateFlow<List<SocialPost>>(emptyList())
    val posts: StateFlow<List<SocialPost>> = _posts.asStateFlow()

    private val _deals = MutableStateFlow<List<Deal>>(emptyList())
    val deals: StateFlow<List<Deal>> = _deals.asStateFlow()

    private val _clientRequests = MutableStateFlow<List<ClientRequest>>(emptyList())
    val clientRequests: StateFlow<List<ClientRequest>> = _clientRequests.asStateFlow()

    private val _brandVault = MutableStateFlow(BrandVault())
    val brandVault: StateFlow<BrandVault> = _brandVault.asStateFlow()

    private val _dealRoomData = MutableStateFlow<DealRoomData?>(null)
    val dealRoomData: StateFlow<DealRoomData?> = _dealRoomData.asStateFlow()

    private val _loveLetters = MutableStateFlow<List<LoveLetter>>(emptyList())
    val loveLetters: StateFlow<List<LoveLetter>> = _loveLetters.asStateFlow()

    private val _memoryJar = MutableStateFlow<List<MemoryJarPrompt>>(emptyList())
    val memoryJar: StateFlow<List<MemoryJarPrompt>> = _memoryJar.asStateFlow()

    suspend fun saveDashboardStats(stats: StudioDashboardStats) = mutex.withLock {
        _dashboardStats.value = stats
    }

    suspend fun saveBreakouts(list: List<Breakout>) = mutex.withLock {
        _breakouts.value = list
    }

    suspend fun saveIdeas(list: List<Idea>) = mutex.withLock {
        _ideas.value = list
    }

    suspend fun updateIdeaStatus(ideaId: Long, newStatus: String) = mutex.withLock {
        val current = _ideas.value.toMutableList()
        val index = current.indexOfFirst { it.id == ideaId }
        if (index != -1) {
            val updated = current[index].copy(status = newStatus)
            current[index] = updated
            _ideas.value = current
        }
    }

    suspend fun addIdea(idea: Idea) = mutex.withLock {
        _ideas.value = listOf(idea) + _ideas.value
    }

    suspend fun saveScripts(list: List<Script>) = mutex.withLock {
        _scripts.value = list
    }

    suspend fun saveOrUpdateScript(script: Script) = mutex.withLock {
        val current = _scripts.value.toMutableList()
        val index = current.indexOfFirst { it.id == script.id }
        if (index != -1) {
            current[index] = script
        } else {
            current.add(0, script)
        }
        _scripts.value = current
    }

    suspend fun savePosts(list: List<SocialPost>) = mutex.withLock {
        _posts.value = list
    }

    suspend fun saveDeals(list: List<Deal>) = mutex.withLock {
        _deals.value = list
    }

    suspend fun saveClientRequests(list: List<ClientRequest>) = mutex.withLock {
        _clientRequests.value = list
    }

    suspend fun saveBrandVault(vault: BrandVault) = mutex.withLock {
        _brandVault.value = vault
    }

    suspend fun saveDealRoomData(data: DealRoomData?) = mutex.withLock {
        _dealRoomData.value = data
    }

    suspend fun saveLoveLetters(list: List<LoveLetter>) = mutex.withLock {
        _loveLetters.value = list
    }

    suspend fun saveMemoryJar(list: List<MemoryJarPrompt>) = mutex.withLock {
        _memoryJar.value = list
    }

    fun hasCachedData(): Boolean {
        return _breakouts.value.isNotEmpty() || _ideas.value.isNotEmpty() || _scripts.value.isNotEmpty()
    }
}

