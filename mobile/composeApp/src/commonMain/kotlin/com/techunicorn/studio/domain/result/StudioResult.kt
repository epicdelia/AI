package com.techunicorn.studio.domain.result

sealed interface StudioResult<out T> {
    data class Success<out T>(
        val data: T,
        val isFromCache: Boolean = false,
        val timestamp: Long = currentEpochMillis()
    ) : StudioResult<T>

    data class Error(
        val error: StudioError,
        val fallbackData: Any? = null
    ) : StudioResult<Nothing>

    data object Loading : StudioResult<Nothing>
}

sealed interface StudioError {
    data class Network(val message: String, val statusCode: Int? = null) : StudioError
    data class Serialization(val message: String) : StudioError
    data class Server(val message: String, val code: String? = null) : StudioError
    data class Validation(val field: String, val message: String) : StudioError
    data class Cache(val message: String) : StudioError
    data class Unknown(val message: String, val cause: Throwable? = null) : StudioError
}

fun currentEpochMillis(): Long = kotlinx.datetime.Clock.System.now().toEpochMilliseconds()

inline fun <T, R> StudioResult<T>.map(transform: (T) -> R): StudioResult<R> {
    return when (this) {
        is StudioResult.Success -> StudioResult.Success(transform(data), isFromCache, timestamp)
        is StudioResult.Error -> this
        is StudioResult.Loading -> StudioResult.Loading
    }
}

inline fun <T> StudioResult<T>.onSuccess(action: (data: T) -> Unit): StudioResult<T> {
    if (this is StudioResult.Success) action(data)
    return this
}

inline fun <T> StudioResult<T>.onError(action: (error: StudioError) -> Unit): StudioResult<T> {
    if (this is StudioResult.Error) action(error)
    return this
}

fun <T> StudioResult<T>.getOrNull(): T? {
    return when (this) {
        is StudioResult.Success -> data
        else -> null
    }
}

fun <T> StudioResult<T>.getOrDefault(default: T): T {
    return when (this) {
        is StudioResult.Success -> data
        else -> default
    }
}

