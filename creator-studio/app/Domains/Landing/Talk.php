<?php

namespace App\Domains\Landing;

use App\Models\Deal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** One of her talks, shown in the landing page's "On Stage" and managed on her dashboard. */
class Talk extends Model
{
    public const KINDS = ['keynote' => 'Keynote', 'panel' => 'Panel', 'workshop' => 'Workshop', 'fireside' => 'Fireside chat', 'podcast' => 'Podcast', 'webinar' => 'Webinar'];

    /** Talk topics she offers organizers (shown with the "Book Delia to speak" button). */
    public const TOPICS = ['AI agents in production', 'Enterprise AI & knowledge management', 'Building in public', 'Remote & nomad engineering', 'Big Tech to indie'];

    protected $fillable = ['event', 'title', 'kind', 'description', 'date', 'time', 'venue', 'city', 'url', 'image', 'featured', 'visible', 'position'];

    protected $appends = ['image_url', 'kind_label', 'upcoming'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'featured' => 'boolean', 'visible' => 'boolean', 'position' => 'integer'];
    }

    protected static function booted(): void
    {
        // An uploaded photo goes with its talk.
        static::deleted(function (self $talk) {
            if ($talk->image && ! str_starts_with($talk->image, '/')) {
                Storage::disk('local')->delete($talk->image);
            }
        });
    }

    /** Bundled photos live in public/images; uploaded ones are served by /landing/talk-image/{id}. */
    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return str_starts_with($this->image, '/') ? $this->image : '/landing/talk-image/'.$this->id.'?v='.($this->updated_at?->timestamp ?? 0);
    }

    public function getKindLabelAttribute(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst((string) $this->kind);
    }

    public function getUpcomingAttribute(): bool
    {
        return $this->date !== null && $this->date->endOfDay()->isFuture();
    }

    /** Visible talks, featured first, then upcoming (soonest first), then past (newest first), then undated by position. */
    public static function ordered()
    {
        $talks = self::where('visible', true)->get();
        $upcoming = $talks->filter->upcoming->sortBy('date');
        $past = $talks->reject->upcoming->filter(fn ($t) => $t->date)->sortByDesc('date');
        $undated = $talks->filter(fn ($t) => ! $t->date)->sortBy('position');

        return $upcoming->concat($past)->concat($undated)->sortByDesc('featured')->values();
    }

    /** "On Stage" for the public page. */
    public static function publicSection(): array
    {
        $talks = self::ordered();

        return [
            'featured' => $talks->firstWhere('featured', true) ?? $talks->first(fn ($t) => $t->image) ?? $talks->first(),
            'upcoming' => $talks->filter->upcoming->sortBy('date')->values(),
            'talks' => $talks,
            'stats' => self::stats($talks),
            'topics' => self::TOPICS,
        ];
    }

    /** Only facts from her list: how many talks, keynotes and cities. */
    public static function stats($talks): array
    {
        return [
            'talks' => $talks->count(),
            'keynotes' => $talks->where('kind', 'keynote')->count(),
            'cities' => $talks->pluck('city')->filter()->unique()->count(),
            'upcoming' => $talks->filter->upcoming->count(),
        ];
    }

    /** Speaking requests from the website: intro calls about a keynote/workshop, not yet past the inquiry stage. */
    public static function requests()
    {
        return Deal::where('stage', 'inquiry')->get(['id', 'brand_name', 'contact_name', 'deliverables', 'key_talking_points', 'created_at'])
            ->filter(function (Deal $d) {
                $first = $d->deliverables[0] ?? [];

                return str_contains(strtolower(($first['format'] ?? '').' '.($first['service'] ?? '')), 'keynote')
                    || str_contains(strtolower(($first['format'] ?? '').' '.($first['service'] ?? '')), 'speak');
            })->values();
    }
}
