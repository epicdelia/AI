<?php

declare(strict_types=1);

namespace App\Domains\Entity\Concerns;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Enums\MagicResponse;
use App\Helpers\Classes\Helper;
use App\Helpers\Classes\MarketplaceHelper;
use App\Helpers\Classes\RateLimiter\RateLimiter;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\SettingTwo;
use App\Models\SharedCreditTransaction;
use App\Models\Team\Team;
use App\Models\User;
use App\Models\UserUsageCredit;
use App\Services\SharedCredit\SharedCreditService;
use Closure;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

trait HasCreditLimit
{
    protected ?float $calculatedInputCredit = null;

    private static ?array $aiImageProSelectedModelsCache = null;

    public function isSharedCreditUser(): bool
    {
        $service = app(SharedCreditService::class);
        if (! $service->isEnabled()) {
            return false;
        }

        $user = $this->getUser();

        return $user instanceof User && $user->isSharedCreditUser();
    }

    public function creditEnum(): EntityEnum
    {
        return $this->enum()->creditBy();
    }

    protected function getPlanWithCredit(): ?Plan
    {
        $this->ensurePlanProvided();

        return $this->plan;
    }

    protected function getTeamWithCredit(): ?Team
    {
        $this->ensureTeamProvided();

        return $this->team;
    }

    protected function getUserWithCredit(): null|User|Authenticatable
    {
        $this->ensureUserProvided();

        return $this->getUser();
    }

    public function getCredit(): array
    {
        if ($this->isSharedCreditUser()) {
            $user = $this->getUserWithCredit();
            $balance = (float) $user->shared_credits;

            $team = $this->team ?? $user->myTeam;
            if ($team?->exists && $team->isSharedCreditTeam()) {
                $memberStat = $user->teamMember;
                if (isset($memberStat) && $memberStat->status === 'active') {
                    $balance += (float) $team->shared_credits;
                }
            }

            return [
                'credit'      => $balance,
                'isUnlimited' => false,
            ];
        }

        if ($this->plan?->exists) {
            return $this->getPlanWithCredit()?->getCredit($this->engine()->slug(), $this->creditKey());
        }
        $user = $this->getUserWithCredit();
        if ($this->team?->exists || $user?->myTeam?->exists) {
            if (! $this->team?->exists) {
                $this->team = $user?->myTeam;
            }

            $memberStat = $user?->teamMember;
            if (isset($memberStat) && $memberStat->status !== 'active') {
                // If the user is not an active member of the team, return the user's credit
                return $user?->getCredit($this->engine()->slug(), $this->creditKey()) ?? [];
            }

            $userCredits = $this->getUserWithCredit()?->getCredit($this->engine()->slug(), $this->creditKey());
            $teamCredits = $this->getTeamWithCredit()?->getCredit($this->engine()->slug(), $this->creditKey());

            $mergedCredits = [
                'credit'      => 0,
                'isUnlimited' => false,
            ];

            if (($userCredits['isUnlimited'] ?? false) || ($teamCredits['isUnlimited'] ?? false)) {
                $mergedCredits['isUnlimited'] = true;
                $mergedCredits['credit'] = 0;
            } else {
                $mergedCredits['credit'] = ($userCredits['credit'] ?? 0) + ($teamCredits['credit'] ?? 0);
            }

            return $mergedCredits;
        }

        return $user?->getCredit($this->engine()->slug(), $this->creditKey());
    }

    /**
     * @throws Exception
     */
    public function creditBalance(): float
    {
        return match (config('octane.enabled') || isset($this->team)) {
            true    => $this->getCreditBalance(),
            default => once(function () {
                return $this->getCreditBalance();
            }),
        };
    }

    /**
     * @throws Exception
     */
    public function isUnlimitedCredit(): bool
    {
        return match (config('octane.enabled')) {
            true    => $this->getIsUnlimitedCredit(),
            default => once(function () {
                return $this->getIsUnlimitedCredit();
            }),
        };
    }

    /**
     * Check if the current entity is a web search model.
     */
    protected function isWebSearchModel(): bool
    {
        return in_array($this->enum(), [
            EntityEnum::GPT_4_O_SEARCH_PREVIEW,
            EntityEnum::GPT_4_O_MINI_SEARCH_PREVIEW,
        ], true);
    }

    /**
     * Get credit data from the default OpenAI chat model for web search model fallback.
     *
     * @return array{credit: float, isUnlimited: bool}|null
     */
    protected function getDefaultChatModelCreditData(): ?array
    {
        $defaultModelSlug = Setting::getCache()?->openai_default_model ?? EntityEnum::GPT_5_MINI->slug();
        $defaultEnum = EntityEnum::fromSlug($defaultModelSlug);

        if (! $defaultEnum || $defaultEnum === $this->enum()) {
            return null;
        }

        $driver = Entity::driver($defaultEnum);

        $user = $this->getUser();
        if ($user) {
            $driver = $driver->forUser($user);
        }

        if ($this->plan?->exists) {
            $driver = $driver->forPlan($this->plan);
        }

        if ($this->team?->exists) {
            $driver = $driver->forTeam($this->team);
        }

        return $driver->getCredit();
    }

    public function getCreditBalance(): float
    {
        $credit = $this->getCredit()['credit'];

        if (is_string($credit)) {
            $credit = (float) $credit;
        }

        $aiFinances = app('ai_chat_model_plan');

        $engineDefaultModels = $this->engine()->getDefaultModels(Setting::getCache(), SettingTwo::getCache());
        $model = $this->model(config('octane.enabled'));
        if (
            $model && ! $model->is_selected &&
            ! in_array($model->key, $engineDefaultModels, true) &&
            ! in_array($model->id, $aiFinances, true) &&
            ! $this->isInAiImageProSelectedModels($model->key->value) &&
            ! $this->isWebSearchModel()
        ) {
            return 0;
        }

        if ($credit == 0 && $this->isWebSearchModel()) {
            $defaultCredit = $this->getDefaultChatModelCreditData();
            if ($defaultCredit) {
                $fallback = $defaultCredit['credit'];

                return is_string($fallback) ? (float) $fallback : $fallback;
            }
        }

        return $credit;
    }

    /**
     * @throws Exception
     */
    public function getIsUnlimitedCredit(): bool
    {
        if ($this->isSharedCreditUser()) {
            return false;
        }

        $aiFinances = app('ai_chat_model_plan');

        $engineDefaultModels = $this->engine()->getDefaultModels(Setting::getCache(), SettingTwo::getCache());

        $model = $this->model(config('octane.enabled'));

        if (
            $model && ! $model->is_selected &&
            ! in_array($model->key, $engineDefaultModels, true) &&
            ! in_array($model->id, $aiFinances, true) &&
            ! $this->isInAiImageProSelectedModels($model->key->value) &&
            ! $this->isWebSearchModel()
        ) {
            return false;
        }

        $isUnlimited = $this->getCredit()['isUnlimited'];

        if (! $isUnlimited && $this->isWebSearchModel()) {
            $defaultCredit = $this->getDefaultChatModelCreditData();
            if ($defaultCredit) {
                return $defaultCredit['isUnlimited'];
            }
        }

        return $isUnlimited;
    }

    /**
     * Check if the model key is in AI Image Pro or AI Chat Pro Image Chat selected models.
     */
    protected function isInAiImageProSelectedModels(string $modelKey): bool
    {
        return in_array($modelKey, $this->getAiImageProSelectedModels(), true);
    }

    /**
     * Get all selected models from AI Image Pro extensions (cached per request).
     */
    protected function getAiImageProSelectedModels(): array
    {
        if (self::$aiImageProSelectedModelsCache !== null) {
            return self::$aiImageProSelectedModelsCache;
        }

        $models = [];

        // Check AI Image Pro selected models (only if extension is registered)
        if (MarketplaceHelper::isRegistered('ai-image-pro')) {
            $aiImageProModels = setting('ai_image_selected_models', null);
            if (! empty($aiImageProModels)) {
                $slugs = is_string($aiImageProModels)
                    ? (json_decode($aiImageProModels, true) ?? explode(',', $aiImageProModels))
                    : (array) $aiImageProModels;
                $models = array_merge($models, $slugs);
            }
        }

        // Check AI Chat Pro Image Chat selected models (only if extension is registered)
        if (MarketplaceHelper::isRegistered('ai-chat-pro-image-chat')) {
            $aiChatImageModels = setting('ai_chat_pro_image_chat_selected_models', null);
            if (! empty($aiChatImageModels)) {
                $slugs = is_string($aiChatImageModels)
                    ? (json_decode($aiChatImageModels, true) ?? explode(',', $aiChatImageModels))
                    : (array) $aiChatImageModels;
                $models = array_merge($models, $slugs);
            }
        }

        self::$aiImageProSelectedModelsCache = array_unique($models);

        return self::$aiImageProSelectedModelsCache;
    }

    /**
     * @throws Exception
     */
    public function hasCreditBalance(): bool
    {
        if ($this->guest) {
            return $this->guestHasAttempts();
        }

        return $this->creditBalance() > 0 || $this->isUnlimitedCredit();
    }

    public function guestHasAttempts(): bool
    {
        $clientIp = Helper::getRequestIp();
        $rateLimiter = new RateLimiter('guest-chat-attempt', (int) setting('guest_user_daily_message_limit', '10'));
        if ($rateLimiter->attempt($clientIp)) {
            return true;
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function redirectIfNoCreditBalance(): void
    {
        if ($this->hasCreditBalance()) {
            return;
        }

        MagicResponse::NO_CREDITS_LEFT->exception();
    }

    /**
     * @throws Exception
     */
    public function setCredit(float $value = 1.00): bool
    {
        if ($this->isSharedCreditUser()) {
            $user = $this->getUserWithCredit();
            $user->shared_credits = $value;

            return $user->save();
        }

        return $this->updateUserCredit($value, function ($creditBalance, $credit) {
            return $credit;
        }, skipCalculatedCredit: true);
    }

    /**
     * @throws Exception
     */
    public function setDefaultCreditForDemo(): bool
    {
        if ($this->getUserWithCredit()?->isAdmin()) {
            $this->setAsUnlimited();
        }

        return $this->setCredit($this->creditEnum()->defaultCreditForDemo());
    }

    public function setAsUnlimited(bool $unlimited = true): bool
    {
        if ($this->isSharedCreditUser()) {
            return true;
        }

        $user = $this->getUserWithCredit();
        $creditKey = $this->creditKey();
        $engineKey = $this->engine()->slug();

        $creditsArr = $user?->entity_credits;

        $creditsArr[$engineKey][$creditKey] = [
            'credit'              => $creditsArr[$engineKey][$creditKey]['credit'] ?? 0.0,
            'isUnlimited'         => $unlimited,
        ];

        return $user?->update([
            'entity_credits' => $creditsArr,
        ]);
    }

    /**
     * @throws Exception
     */
    public function increaseCredit(float $value = 1.00): bool
    {
        if ($this->isSharedCreditUser()) {
            $user = $this->getUserWithCredit();
            $service = app(SharedCreditService::class);
            $entity = EntityEnum::fromSlug($this->enum()->slug());
            $service->topUp($user, $value, 'Credit increase', $entity, 'increase');

            return true;
        }

        return $this->updateUserCredit($value, function ($creditBalance, $credit) {
            return $creditBalance + $credit;
        });
    }

    /**
     * @throws Exception
     */
    public function decreaseCredit(float $value = 1.00): bool
    {
        if ($this->guest || $this->isUnlimitedCredit()) {
            return true;
        }

        $entity = EntityEnum::fromSlug($this->enum()->slug());
        $unitPrice = $entity->unitPrice();
        $currentSpend = $value * $unitPrice;
        setting(['total_spend' => number_format((setting('total_spend', 0) + $currentSpend), 2)])->save();

        UserUsageCredit::create([
            'user_id'     => $this->getUser()->id,
            'model_key'   => $this->enum()->slug(),
            'credit'      => $value,
            'unit_price'  => $unitPrice,
            'total'       => $value * $unitPrice,
        ]);

        if ($this->isSharedCreditUser()) {
            $user = $this->getUserWithCredit();
            $service = app(SharedCreditService::class);

            // Feature limit check
            $featureLimits = $user->activePlan()?->shared_credit_feature_limits;
            if ($featureLimits && $this->isFeatureLimitExceeded($user, $entity, $featureLimits)) {
                return false;
            }

            // Derive raw unit count from calculatedInputCredit so deduction scales with output length.
            // calculatedInputCredit = count × getCreditIndex(); deduct() multiplies quantity × unitCost,
            // so we divide back to avoid double-multiplication.
            $creditIndex = $this->getCreditIndex();
            $quantity = ($this->calculatedInputCredit !== null && $creditIndex > 0)
                ? $this->calculatedInputCredit / $creditIndex
                : $value;

            $team = $this->team ?? $user->myTeam;
            if ($team?->exists && $team->isSharedCreditTeam()) {
                $memberStat = $user->teamMember;
                if (isset($memberStat) && $memberStat->status === 'active') {
                    $transaction = $service->deductWithTeam($user, $team, $entity, $quantity);

                    return $transaction !== null;
                }
            }

            $transaction = $service->deduct($user, $entity, $quantity);

            return $transaction !== null;
        }

        return $this->updateUserCredit($value, function ($creditBalance, $credit) {
            return max(0, $creditBalance - $credit);
        });
    }

    /**
     * Decrease credit from the default OpenAI chat model's pool for web search models.
     *
     * @throws Exception
     */
    private function decreaseCreditFromDefaultChatModel(float $value): bool
    {
        $defaultModelSlug = Setting::getCache()?->openai_default_model ?? EntityEnum::GPT_5_MINI->slug();
        $defaultEnum = EntityEnum::fromSlug($defaultModelSlug);

        if (! $defaultEnum || $defaultEnum === $this->enum()) {
            return $this->updateUserCredit($value, function ($creditBalance, $credit) {
                return max(0, $creditBalance - $credit);
            });
        }

        $driver = Entity::driver($defaultEnum);

        $user = $this->getUser();
        if ($user) {
            $driver = $driver->forUser($user);
        }

        if ($this->team?->exists) {
            $driver = $driver->forTeam($this->team);
        }

        return $driver->decreaseCredit($value);
    }

    /**
     * @throws Exception
     */
    private function updateUserCredit(float $value, Closure $callback, bool $skipCalculatedCredit = false): bool
    {
        $user = $this->getUserWithCredit();
        $team = $this->team;

        if ($skipCalculatedCredit) {
            $credit = $value;
        } else {
            $credit = $this->calculatedInputCredit ?: $value;
        }

        $creditKey = $this->creditKey();

        $engineKey = $this->engine()->slug();

        $isTeamCredit = isset($team) && $team->exists;

        $target = $isTeamCredit ? $team : $user;
        $creditsArr = $target && isset($target->entity_credits) ? $target->entity_credits : User::getFreshCredits();

        $creditsArr[$engineKey][$creditKey] = [
            'credit'      => $callback($this->creditBalance(), $credit),
            'isUnlimited' => $creditsArr[$engineKey][$creditKey]['isUnlimited'] ?? false,
        ];

        return $target?->update([
            'entity_credits' => $creditsArr,
        ]);
    }

    public function getCreditIndex(): float
    {
        if ($this->isSharedCreditUser()) {
            $entity = $this->creditEnum();
            $service = app(SharedCreditService::class);
            $costOverride = $service->getCostOverride($entity->slug());

            return $costOverride ? $costOverride->base_cost : $entity->sharedCreditIndex();
        }

        return $this->creditEnum()->creditIndex();
    }

    public function getCalculatedInputCredit(): float
    {
        return $this->calculatedInputCredit;
    }

    /**
     * @throws Exception
     */
    public function hasCreditBalanceForInput(): bool
    {
        if ($this->isUnlimitedCredit()) {
            return true;
        }

        return $this->creditBalance() >= $this->getCalculatedInputCredit();
    }

    public function setCalculatedInputCredit($value = 0.0): static
    {
        $this->calculatedInputCredit = $value;

        return $this;
    }

    private function isFeatureLimitExceeded(User $user, EntityEnum $entity, array $featureLimits): bool
    {
        $tokenType = null;

        try {
            $tokenType = $entity->tokenType()->value;
        } catch (Throwable) {
            return false;
        }

        $entitySlug = $entity->slug();

        // Check per-model limits first
        $perModelLimits = $featureLimits['per_model_limits'][$entitySlug] ?? null;
        if ($perModelLimits) {
            if ($this->checkTransactionLimit($user, $entitySlug, $perModelLimits, 'entity_key')) {
                return true;
            }
        }

        // Check global token-type limits
        $dailyKey = "daily_{$tokenType}_limit";
        $monthlyKey = "monthly_{$tokenType}_limit";

        if (isset($featureLimits[$dailyKey])) {
            $todayCount = SharedCreditTransaction::query()
                ->where('user_id', $user->id)
                ->where('action_type', 'deduct')
                ->where('feature_type', $tokenType)
                ->whereDate('created_at', now()->toDateString())
                ->count();

            if ($todayCount >= (int) $featureLimits[$dailyKey]) {
                return true;
            }
        }

        if (isset($featureLimits[$monthlyKey])) {
            $monthCount = SharedCreditTransaction::query()
                ->where('user_id', $user->id)
                ->where('action_type', 'deduct')
                ->where('feature_type', $tokenType)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();

            if ($monthCount >= (int) $featureLimits[$monthlyKey]) {
                return true;
            }
        }

        return false;
    }

    private function checkTransactionLimit(User $user, string $entitySlug, array $limits, string $column): bool
    {
        if (isset($limits['daily'])) {
            $todayCount = SharedCreditTransaction::query()
                ->where('user_id', $user->id)
                ->where('action_type', 'deduct')
                ->where($column, $entitySlug)
                ->whereDate('created_at', now()->toDateString())
                ->count();

            if ($todayCount >= (int) $limits['daily']) {
                return true;
            }
        }

        if (isset($limits['monthly'])) {
            $monthCount = SharedCreditTransaction::query()
                ->where('user_id', $user->id)
                ->where('action_type', 'deduct')
                ->where($column, $entitySlug)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();

            if ($monthCount >= (int) $limits['monthly']) {
                return true;
            }
        }

        return false;
    }
}
