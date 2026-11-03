<?php

namespace App\Models;

use App\Helpers\Classes\MarketplaceHelper;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Schema;

class UserOpenaiChatMessage extends Model
{
    use HasFactory;

    protected $table = 'user_openai_chat_messages';

    protected $fillable = [
        'user_openai_chat_id',
        'user_id',
        'input',
        'response',
        'output',
        'hash',
        'credits',
        'words',
        'images',
        'pdfName',
        'pdfPath',
        'outputImage',
        'realtime',
        'is_chatbot',
        'suggestions_response',
        'is_council',
        'council_response',
    ];

    protected $casts = [
        'suggestions_response' => 'array',
        'council_response'     => 'array',
        'used_skills'          => 'array',
        'used_connectors'      => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (Schema::hasColumn($this->getTable(), 'model_slug')) {
            $this->fillable[] = 'model_slug';
        }

        if (Schema::hasColumn($this->getTable(), 'shared_uuid')) {
            $this->fillable[] = 'shared_uuid';
        }

        if (Schema::hasColumn($this->getTable(), 'is_council')) {
            $this->fillable[] = 'is_council';
        }

        if (Schema::hasColumn($this->getTable(), 'council_response')) {
            $this->fillable[] = 'council_response';
        }

        if (Schema::hasColumn($this->getTable(), 'used_skills')) {
            $this->fillable[] = 'used_skills';
        }

        if (Schema::hasColumn($this->getTable(), 'used_connectors')) {
            $this->fillable[] = 'used_connectors';
        }

        if (Schema::hasColumn($this->getTable(), 'highlight_context')) {
            $this->fillable[] = 'highlight_context';
        }
    }

    protected static function booted(): void
    {
        static::created(static function ($message) {
            // Whenever a new message is added, mark chat as not empty
            if ($message->response !== 'First Initiation') {
                $message->chat()->update(['is_empty' => false]);
            }
        });

        static::deleted(static function ($message) {
            // flip back to empty if no messages remain
            if ($message->chat && $message->chat->messagesWithoutInitial()->count() === 0) {
                $message->chat->update(['is_empty' => true]);
            }
        });
    }

    public function chat()
    {
        return $this->belongsTo(UserOpenaiChat::class, 'user_openai_chat_id', 'id');
    }

    /**
     * Get the AI-generated images for this message (only if extension is installed).
     */
    public function aiChatProImages(): HasMany
    {
        if (! MarketplaceHelper::isRegistered('ai-chat-pro-image-chat') || ! $this->tableExists('ai_chat_pro_image')) {
            return $this->hasMany(self::class, 'id', 'id')->whereRaw('1 = 0');
        }

        $modelClass = 'App\\Extensions\\AiChatProImageChat\\System\\Models\\AiChatProImageModel';

        return $this->hasMany($modelClass, 'message_id');
    }

    public function councilResponses(): HasMany
    {
        if (! MarketplaceHelper::isRegistered('model-council') || ! class_exists('App\\Extensions\\ModelCouncil\\System\\Models\\ModelCouncilResponse') || ! $this->tableExists('model_council_responses')) {
            return $this->hasMany(self::class, 'id', 'id')->whereRaw('1 = 0');
        }

        $modelClass = 'App\\Extensions\\ModelCouncil\\System\\Models\\ModelCouncilResponse';

        return $this->hasMany($modelClass, 'user_openai_chat_message_id');
    }

    /**
     * Get the suggestions response from linked AI image records.
     */
    public function getSuggestionsResponseAttribute(): ?array
    {
        // Check direct column value first (text chat suggestions)
        $directValue = $this->attributes['suggestions_response'] ?? null;
        if ($directValue) {
            return is_array($directValue) ? $directValue : json_decode($directValue, true);
        }

        // Fall back to image chat suggestions
        if (! MarketplaceHelper::isRegistered('ai-chat-pro-image-chat')) {
            return null;
        }

        $imageRecord = $this->aiChatProImages()
            ->whereNotNull('suggestions_response')
            ->latest()
            ->first();

        return $imageRecord?->suggestions_response;
    }

    /**
     * Get the smart images for this message (only if extension is installed).
     */
    public function chatSmartImages(): HasMany
    {
        if (! MarketplaceHelper::isRegistered('ai-chat-pro-smart-image') || ! $this->tableExists('chat_smart_images')) {
            return $this->hasMany(self::class, 'id', 'id')->whereRaw('1 = 0');
        }

        $modelClass = 'App\\Extensions\\AiChatProSmartImage\\System\\Models\\ChatSmartImage';

        return $this->hasMany($modelClass, 'message_id');
    }

    /**
     * Get the entity highlights for this message (only if extension is installed).
     */
    public function chatEntityHighlights(): HasMany
    {
        if (! MarketplaceHelper::isRegistered('ai-chat-pro-entity-highlight') || ! $this->tableExists('chat_entity_highlights')) {
            return $this->hasMany(self::class, 'id', 'id')->whereRaw('1 = 0');
        }

        $modelClass = 'App\\Extensions\\AiChatProEntityHighlight\\System\\Models\\ChatEntityHighlight';

        return $this->hasMany($modelClass, 'message_id');
    }

    // tiptap edit result
    public function tiptapContent(): MorphOne
    {
        $canvasModel = 'App\\Extensions\\Canvas\\System\\Http\\Models\\UserTiptapContent';
        $drModel = 'App\\Extensions\\AIChatProDeepResearch\\System\\Http\\Models\\DrTiptapContent';

        // Canvas extension takes priority
        if (class_exists($canvasModel) && $this->tableExists('user_tiptap_contents')) {
            return $this->morphOne($canvasModel, 'save_contentable');
        }

        // Fallback to Deep Research's own tiptap table
        if (class_exists($drModel) && $this->tableExists('dr_tiptap_contents')) {
            return $this->morphOne($drModel, 'save_contentable');
        }

        return $this->morphOne(self::class, 'user_openai_chat', 'user_id', 'id')->whereRaw('1 = 0');
    }

    /**
     * Check if a table exists in the database
     */
    private function tableExists($tableName): bool
    {
        try {
            return Schema::hasTable($tableName);
        } catch (Exception $e) {
            return false;
        }
    }
}
