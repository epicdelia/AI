<?php

namespace App\Http\Controllers\Api;

use App\Domains\Backup\Backup;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Settings → Backups: list, back up now, download (the database, or a zip with her media). */
class BackupController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'backups' => Backup::list(),
            'keep' => Backup::KEEP,
            'zip' => class_exists(\ZipArchive::class),
            'last_error' => Setting::get('last_error_backup'),
        ]);
    }

    public function store(): JsonResponse
    {
        try {
            $b = Backup::run();
        } catch (Throwable $e) {
            Setting::put('last_error_backup', mb_substr(now()->toIso8601String().' '.$e->getMessage(), 0, 300));

            return response()->json(['error' => $e->getMessage()], 500);
        }
        Setting::put('last_run_backup', now()->toIso8601String());
        Setting::put('last_error_backup', null);

        return response()->json(['message' => 'Backed up. '.round($b['bytes'] / 1024).' KB saved.', 'backups' => Backup::list()]);
    }

    public function download(Request $request, string $name): BinaryFileResponse|JsonResponse
    {
        if (! Backup::path($name)) {
            return response()->json(['error' => 'No such backup.'], 404);
        }
        if ($request->boolean('media') && ($zip = Backup::zip($name))) {
            return response()->download($zip, str_replace('.sqlite', '.zip', $name))->deleteFileAfterSend();
        }

        return response()->download(Backup::path($name), $name);
    }
}
