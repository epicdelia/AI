package com.techunicorn.studio.data.live

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

// What the Creator Studio server sends (field names as in the Laravel API). Unknown fields
// are ignored, so the app keeps working as the server adds more.

@Serializable
data class SessionUser(
    val id: Long = 0,
    val name: String = "",
    val email: String = "",
    val role: String = "client",
    val abilities: List<String> = emptyList(),
)

@Serializable
data class TokenResponse(
    val token: String? = null,
    @SerialName("two_factor") val twoFactor: Boolean = false,
    val user: SessionUser? = null,
    val error: String? = null,
    val message: String? = null,
)

@Serializable
data class StatusResponse(val user: SessionUser? = null)

// ---- Needs you (her) -----------------------------------------------------------------------

@Serializable
data class NeedsYou(val total: Int = 0, val groups: List<NeedsGroup> = emptyList())

@Serializable
data class NeedsGroup(val id: String = "", val title: String = "", val count: Int = 0, val items: List<NeedsItem> = emptyList())

@Serializable
data class NeedsItem(val title: String = "", val meta: String? = null, val url: String = "", val at: String? = null)

// ---- Client requests ----------------------------------------------------------------------

@Serializable
data class RequestStatus(val key: String = "", val label: String = "", val step: Int? = null)

@Serializable
data class RequestSummary(
    val id: Long = 0,
    val title: String = "",
    val company: String? = null,
    val status: RequestStatus = RequestStatus(),
    val amount: Double? = null,
    val currency: String = "USD",
    @SerialName("needs_you") val needsYou: String? = null,
    val unread: Int = 0,
)

@Serializable
data class RequestList(val requests: List<RequestSummary> = emptyList())

@Serializable
data class OnboardingStep(val key: String = "", val label: String = "", val type: String = "custom", val done: Boolean = false, val auto: Boolean = false)

@Serializable
data class Onboarding(val active: Boolean = false, val done: Int = 0, val total: Int = 0, val steps: List<OnboardingStep> = emptyList())

@Serializable
data class BillingTerms(
    @SerialName("payment_plan") val paymentPlan: String? = null,
    @SerialName("deposit_percent") val depositPercent: Int = 0,
    @SerialName("next_amount") val nextAmount: Double = 0.0,
    @SerialName("next_part") val nextPart: String = "full",
    @SerialName("invoice_due_on") val invoiceDueOn: String? = null,
    val overdue: Boolean = false,
    @SerialName("days_overdue") val daysOverdue: Int = 0,
)

@Serializable
data class UsageRight(
    val id: Long = 0,
    val label: String = "",
    val scope: String? = null,
    val days: Int = 0,
    val price: Double = 0.0,
    @SerialName("ends_on") val endsOn: String? = null,
    val status: String = "active",
    @SerialName("ends_soon") val endsSoon: Boolean = false,
)

@Serializable
data class SponsoredCopy(
    val text: String = "",
    val status: String = "pending",
    val note: String? = null,
    @SerialName("due_on") val dueOn: String? = null,
)

// ---- Sales (her) --------------------------------------------------------------------------

@Serializable
data class ShopTotals(
    @SerialName("revenue_30d") val revenue30d: Double = 0.0,
    @SerialName("orders_30d") val orders30d: Int = 0,
    @SerialName("revenue_all") val revenueAll: Double = 0.0,
)

@Serializable
data class ShopAdmin(val totals: ShopTotals = ShopTotals())

@Serializable
data class AffiliateTotals(
    @SerialName("earned_30d") val earned30d: Double = 0.0,
    @SerialName("clicks_30d") val clicks30d: Int = 0,
    val broken: Int = 0,
)

@Serializable
data class AffiliateOverview(val totals: AffiliateTotals = AffiliateTotals())

@Serializable
data class RequestDetail(
    val id: Long = 0,
    val title: String = "",
    val company: String? = null,
    val status: RequestStatus = RequestStatus(),
    val amount: Double? = null,
    val currency: String = "USD",
    @SerialName("needs_you") val needsYou: String? = null,
    @SerialName("can_review_work") val canReviewWork: Boolean = false,
    @SerialName("can_pay") val canPay: Boolean = false,
    @SerialName("review_due_on") val reviewDueOn: String? = null,
    @SerialName("review_overdue") val reviewOverdue: Boolean = false,
    val onboarding: Onboarding? = null,
    val billing: BillingTerms? = null,
    val rights: List<UsageRight> = emptyList(),
    @SerialName("sponsored_copy") val sponsoredCopy: SponsoredCopy? = null,
)

@Serializable
data class RequestEnvelope(val request: RequestDetail = RequestDetail())

@Serializable
data class OnboardingEnvelope(val onboarding: Onboarding = Onboarding())

@Serializable
data class CheckoutResponse(@SerialName("checkout_url") val checkoutUrl: String? = null, val message: String? = null)

// ---- Team ---------------------------------------------------------------------------------

@Serializable
data class TeamTaskLive(
    val id: Long = 0,
    val title: String = "",
    val stage: String = "",
    @SerialName("assignee_name") val assigneeName: String = "",
    @SerialName("due_on") val dueOn: String? = null,
    val overdue: Boolean = false,
    val mine: Boolean = false,
    val completed: Boolean = false,
    @SerialName("minutes_logged") val minutesLogged: Int = 0,
    @SerialName("timer_started_at") val timerStartedAt: String? = null,
    val brief: String? = null,
    @SerialName("payout_amount") val payoutAmount: Double? = null,
)

@Serializable
data class TeamMetrics(val mine: Int = 0, val overdue: Int = 0, @SerialName("total_tasks") val totalTasks: Int = 0)

@Serializable
data class TeamMe(val id: Long = 0, val admin: Boolean = false)

@Serializable
data class TeamOverview(val me: TeamMe = TeamMe(), val tasks: List<TeamTaskLive> = emptyList(), val metrics: TeamMetrics = TeamMetrics())

// ---- Community ----------------------------------------------------------------------------

@Serializable
data class LiveEvent(
    val id: Long = 0,
    val title: String = "",
    val description: String? = null,
    @SerialName("starts_at") val startsAt: String = "",
    val minutes: Int = 60,
    val tier: String = "free",
    val locked: Boolean = false,
    @SerialName("join_url") val joinUrl: String? = null,
    @SerialName("replay_url") val replayUrl: String? = null,
    val going: Boolean = false,
    val count: Int = 0,
    val past: Boolean = false,
    val live: Boolean = false,
)

@Serializable
data class EventList(val upcoming: List<LiveEvent> = emptyList(), val past: List<LiveEvent> = emptyList())

@Serializable
data class RsvpResponse(val going: Boolean = false, val count: Int = 0)

@Serializable
data class CourseCard(
    val id: Long = 0,
    val title: String = "",
    val description: String? = null,
    val tier: String = "free",
    val locked: Boolean = false,
    val lessons: Int = 0,
    val completed: Int = 0,
)

@Serializable
data class CourseList(val courses: List<CourseCard> = emptyList())

@Serializable
data class Lesson(
    val id: Long = 0,
    val title: String = "",
    val minutes: Int? = null,
    val locked: Boolean = false,
    val body: String? = null,
    @SerialName("video_url") val videoUrl: String? = null,
    val done: Boolean = false,
)

@Serializable
data class CourseInfo(val id: Long = 0, val title: String = "", val description: String? = null)

@Serializable
data class CourseDetail(val course: CourseInfo = CourseInfo(), val lessons: List<Lesson> = emptyList())

@Serializable
data class ErrorBody(val error: String? = null, val message: String? = null)
