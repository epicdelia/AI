<?php

namespace App\Domains\Backup;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

/**
 * Daily copies of the studio database. Everything she makes lives in one SQLite file, so a
 * consistent copy of it (SQLite's VACUUM INTO, safe while the app is running) is the backup.
 * Saved API keys are encrypted with APP_KEY: restoring elsewhere needs the same .env.
 */
class Backup
{
    public const KEEP = 14;

    public const NAME = '/^studio-\d{4}-\d{2}-\d{2}-\d{6}\.sqlite$/';

    public static function dir(): string
    {
        return storage_path('app/backups');
    }

    /** @return array{name: string, bytes: int} */
    public static function run(): array
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Backups support the SQLite database only.');
        }
        if (DB::transactionLevel() > 0) {
            throw new RuntimeException('Can’t back up in the middle of a database transaction. Try again in a moment.');
        }
        if (! is_dir(self::dir())) {
            mkdir(self::dir(), 0775, true);
        }
        $name = 'studio-'.now()->format('Y-m-d-His').'.sqlite';
        $path = self::dir().DIRECTORY_SEPARATOR.$name;
        if (is_file($path)) {
            unlink($path);
        }
        DB::statement('VACUUM INTO ?', [$path]);
        self::prune();

        return ['name' => $name, 'bytes' => filesize($path) ?: 0];
    }

    /** Newest first. @return array<int, array{name: string, bytes: int, created_at: string}> */
    public static function list(): array
    {
        $out = [];
        foreach (glob(self::dir().DIRECTORY_SEPARATOR.'studio-*.sqlite') ?: [] as $file) {
            if (preg_match(self::NAME, basename($file))) {
                $out[] = ['name' => basename($file), 'bytes' => filesize($file) ?: 0, 'created_at' => date(DATE_ATOM, filemtime($file))];
            }
        }
        usort($out, fn ($a, $b) => strcmp($b['name'], $a['name']));

        return $out;
    }

    /** Full path of a backup by name, or null (names are checked, so no path tricks). */
    public static function path(string $name): ?string
    {
        $path = self::dir().DIRECTORY_SEPARATOR.$name;

        return preg_match(self::NAME, $name) && is_file($path) ? $path : null;
    }

    /** The backup plus her generated media (thumbnails, audio, clips) as one zip, if zip is available. */
    public static function zip(string $name): ?string
    {
        $db = self::path($name);
        if (! $db || ! class_exists(ZipArchive::class)) {
            return null;
        }
        $zipPath = tempnam(sys_get_temp_dir(), 'studio').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($db, 'database.sqlite');
        $media = storage_path('app/private/media');
        if (is_dir($media)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($media, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $zip->addFile($file->getPathname(), 'media/'.str_replace('\\', '/', substr($file->getPathname(), strlen($media) + 1)));
            }
        }
        $zip->addFromString('RESTORE.txt', "Creator Studio backup {$name}\n\n1. Stop the app.\n2. Copy database.sqlite over creator-studio/database/database.sqlite.\n3. Copy the media folder into creator-studio/storage/app/private/.\n4. Keep the same .env (APP_KEY), or re-enter your API keys in Settings.\n");
        $zip->close();

        return $zipPath;
    }

    private static function prune(): void
    {
        foreach (array_slice(self::list(), self::KEEP) as $old) {
            @unlink(self::dir().DIRECTORY_SEPARATOR.$old['name']);
        }
    }
}
