<?php

namespace App\Domains\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** A generated or uploaded file (audio, thumbnail mockup, reference photo) in private storage. */
class MediaFile extends Model
{
    protected $fillable = ['kind', 'script_id', 'title', 'provider', 'model', 'prompt', 'meta', 'path', 'mime', 'bytes'];

    protected $appends = ['url'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    /** Served by MediaController::file, behind the login. */
    public function getUrlAttribute(): string
    {
        return "/api/media/{$this->id}/file";
    }

    public static function store(string $kind, string $bytes, string $ext, string $mime, array $attrs = []): self
    {
        $path = "media/{$kind}/".now()->format('Y/m').'/'.Str::uuid().".{$ext}";
        Storage::disk('local')->put($path, $bytes);

        return self::create($attrs + ['kind' => $kind, 'path' => $path, 'mime' => $mime, 'bytes' => strlen($bytes)]);
    }

    public function contents(): ?string
    {
        return Storage::disk('local')->get($this->path);
    }

    protected static function booted(): void
    {
        static::deleted(fn (self $m) => Storage::disk('local')->delete($m->path));
    }
}
