<?php

namespace App\Domains\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Daily copies of the studio database.
 * - SQLite (her laptop): SQLite's VACUUM INTO, a consistent copy that's safe while running.
 * - MySQL / MariaDB (the VPS): a gzipped SQL dump written in PHP (no mysqldump needed).
 * The newest 14 stay in storage/app/backups. With BACKUP_DISK set, each one is also encrypted
 * (AES-256-GCM, BACKUP_PASSPHRASE or else APP_KEY) and copied off the server to that disk, so
 * losing the server doesn't lose the backups. `php artisan studio:backup-decrypt` reverses it.
 * Saved API keys are encrypted with APP_KEY: restoring elsewhere needs the same .env.
 */
class Backup
{
    public const KEEP = 14;

    public const NAME = '/^studio-\d{4}-\d{2}-\d{2}-\d{6}\.(sqlite|sql\.gz)$/';

    private const MAGIC = 'CSBK1';

    public static function dir(): string
    {
        return storage_path('app/backups');
    }

    /** @return array{name: string, bytes: int, offsite: ?string} */
    public static function run(): array
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new RuntimeException("Backups support SQLite and MySQL/MariaDB (this is {$driver}).");
        }
        if (DB::transactionLevel() > 0) {
            throw new RuntimeException('Can’t back up in the middle of a database transaction. Try again in a moment.');
        }
        if (! is_dir(self::dir())) {
            mkdir(self::dir(), 0775, true);
        }
        $stamp = 'studio-'.now()->format('Y-m-d-His');
        $name = $stamp.($driver === 'sqlite' ? '.sqlite' : '.sql.gz');
        $path = self::dir().DIRECTORY_SEPARATOR.$name;
        if (is_file($path)) {
            unlink($path);
        }
        $driver === 'sqlite' ? DB::statement('VACUUM INTO ?', [$path]) : self::dumpMysql($path);
        self::prune();

        return ['name' => $name, 'bytes' => filesize($path) ?: 0, 'offsite' => self::offsite($path)];
    }

    /** Encrypt and copy to BACKUP_DISK; returns where it went (null when off-server copies are off). */
    public static function offsite(string $path): ?string
    {
        $disk = config('services.backup.disk');
        if (! $disk) {
            return null;
        }
        $target = 'studio-backups/'.basename($path).'.enc';
        Storage::disk($disk)->put($target, self::encrypt((string) file_get_contents($path)));
        $files = collect(Storage::disk($disk)->files('studio-backups'))->filter(fn ($f) => str_ends_with($f, '.enc'))->sort()->values();
        foreach ($files->slice(0, max(0, $files->count() - max(1, (int) config('services.backup.keep', 30)))) as $old) {
            Storage::disk($disk)->delete($old);
        }

        return "{$disk}:{$target}";
    }

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Couldn’t encrypt the backup (is the openssl extension on?).');
        }

        return self::MAGIC.$iv.$tag.$cipher;
    }

    public static function decrypt(string $blob): string
    {
        if (! str_starts_with($blob, self::MAGIC)) {
            throw new RuntimeException('That isn’t an encrypted studio backup.');
        }
        $o = strlen(self::MAGIC);
        $plain = openssl_decrypt(substr($blob, $o + 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($blob, $o, 12), substr($blob, $o + 12, 16));
        if ($plain === false) {
            throw new RuntimeException('Wrong key: use the same BACKUP_PASSPHRASE (or APP_KEY) the backup was made with.');
        }

        return $plain;
    }

    /** Newest first. @return array<int, array{name: string, bytes: int, created_at: string}> */
    public static function list(): array
    {
        $out = [];
        foreach (glob(self::dir().DIRECTORY_SEPARATOR.'studio-*') ?: [] as $file) {
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
        $sqlite = str_ends_with($name, '.sqlite');
        $zipPath = tempnam(sys_get_temp_dir(), 'studio').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($db, $sqlite ? 'database.sqlite' : 'database.sql.gz');
        $media = storage_path('app/private/media');
        if (is_dir($media)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($media, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $zip->addFile($file->getPathname(), 'media/'.str_replace('\\', '/', substr($file->getPathname(), strlen($media) + 1)));
            }
        }
        $step2 = $sqlite ? '2. Copy database.sqlite over creator-studio/database/database.sqlite.'
            : '2. Load the dump into an empty database: gunzip < database.sql.gz | mysql -u USER -p DATABASE';
        $zip->addFromString('RESTORE.txt', "Creator Studio backup {$name}\n\n1. Stop the app.\n{$step2}\n3. Copy the media folder into creator-studio/storage/app/private/.\n4. Keep the same .env (APP_KEY), or re-enter your API keys in Settings.\n");
        $zip->close();

        return $zipPath;
    }

    /** Every table's structure and rows as a gzipped SQL file (chunked, so memory stays flat). */
    private static function dumpMysql(string $path): void
    {
        $gz = gzopen($path, 'wb6');
        if (! $gz) {
            throw new RuntimeException('Can’t write the backup file.');
        }
        $pdo = DB::connection()->getPdo();
        gzwrite($gz, "-- Creator Studio backup ".now()->toIso8601String()."\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");
        foreach (DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $row) {
            $table = array_values((array) $row)[0];
            $create = array_values((array) DB::selectOne("SHOW CREATE TABLE `{$table}`"))[1];
            gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n");
            $offset = 0;
            do {
                $rows = DB::table($table)->offset($offset)->limit(500)->get();
                foreach ($rows->chunk(100) as $chunk) {
                    $values = $chunk->map(fn ($r) => '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $r)).')');
                    gzwrite($gz, "INSERT INTO `{$table}` VALUES ".$values->implode(",\n").";\n");
                }
                $offset += 500;
            } while ($rows->count() === 500);
            gzwrite($gz, "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
    }

    private static function key(): string
    {
        $secret = (string) (config('services.backup.passphrase') ?: config('app.key'));
        if ($secret === '') {
            throw new RuntimeException('Set BACKUP_PASSPHRASE (or APP_KEY) to encrypt off-server backups.');
        }

        return hash('sha256', $secret, true);
    }

    private static function prune(): void
    {
        foreach (array_slice(self::list(), self::KEEP) as $old) {
            @unlink(self::dir().DIRECTORY_SEPARATOR.$old['name']);
        }
    }
}
