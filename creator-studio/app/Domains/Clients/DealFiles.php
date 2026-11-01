<?php

namespace App\Domains\Clients;

use App\Domains\Clients\Models\DealFile;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files on a request, for both sides. Only document, image, audio, video and zip types; a size
 * cap (CLIENT_UPLOAD_MAX_MB, 25 MB by default) and a count cap per request; stored privately
 * under a random name; always downloaded as an attachment (never shown inline), so an uploaded
 * file can't run as a page on this site.
 */
class DealFiles
{
    public const EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'key', 'pages', 'numbers', 'txt', 'md', 'rtf',
        'png', 'jpg', 'jpeg', 'webp', 'gif', 'heic', 'zip', 'mp4', 'mov', 'webm', 'mp3', 'wav', 'm4a'];

    public const MAX_PER_DEAL = 100;

    public static function maxMb(): int
    {
        return max(1, (int) config('services.client_files.max_mb', 25));
    }

    /** Validation rules for an upload. */
    public static function rules(): array
    {
        return ['file' => 'required|file|extensions:'.implode(',', self::EXTENSIONS).'|max:'.(self::maxMb() * 1024)];
    }

    public static function store(Deal $deal, UploadedFile $file, User $by, bool $fromAdmin): DealFile
    {
        abort_if($deal->files()->count() >= self::MAX_PER_DEAL, 422, 'This request already has '.self::MAX_PER_DEAL.' files. Remove some first.');
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs('deal-files/'.$deal->id, Str::uuid().'.'.$ext, 'local');
        $name = mb_substr(preg_replace('/[^\pL\pN ._()-]+/u', '_', $file->getClientOriginalName()) ?: 'file.'.$ext, 0, 180);

        return $deal->files()->create([
            'user_id' => $by->id, 'from_admin' => $fromAdmin, 'name' => $name, 'path' => $path,
            'mime' => $file->getClientMimeType(), 'size' => $file->getSize(),
        ]);
    }

    public static function download(DealFile $file): StreamedResponse
    {
        return Storage::disk('local')->download($file->path, $file->name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
