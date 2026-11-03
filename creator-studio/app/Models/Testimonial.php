<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonial extends Model
{
    public const STATUSES = ['requested', 'submitted', 'approved', 'hidden'];

    protected $fillable = ['deal_id', 'token', 'name', 'role', 'company', 'quote', 'rating', 'permission', 'status', 'position', 'requested_at', 'submitted_at', 'approved_at'];

    protected $hidden = ['token'];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * The private review link for a client (one per deal, reused).
     */
    public static function requestFor(Deal $deal): self
    {
        $t = self::firstOrCreate([
            'deal_id' => $deal->id,
        ], [
            'token' => \Illuminate\Support\Str::random(32),
            'name' => $deal->contact_name,
            'company' => $deal->brand_name,
            'status' => 'requested',
            'requested_at' => now(),
        ]);
        if (!$t->token) {
            $t->forceFill([
                'token' => \Illuminate\Support\Str::random(32),
            ])->save();
        }

        return $t;
    }

    public function url(): string
    {
        return url('/review/' . $this->token);
    }

    /**
     * What the landing page shows: approved, allowed, with words in it.
     */
    public static function public(): array
    {
        return self::where('status', 'approved')->where('permission', true)->whereNotNull('quote')
            ->orderBy('position')->orderByDesc('approved_at')->take(12)->get()
            ->map(fn (self $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'role' => $t->role,
                'company' => $t->company,
                'quote' => $t->quote,
                'rating' => $t->rating,
            ])
            ->values()->all();
    }

    protected function casts(): array
    {
        return [
            'permission' => 'boolean',
            'rating' => 'integer',
            'requested_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
