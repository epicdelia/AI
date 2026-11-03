<?php

namespace MagicAI\Updater\Traits;

use MagicAI\Updater\Exceptions\ZipException;
use Throwable;
use ZipArchive;

trait HasZipper
{
    public function unzip(string $zipFile, ?string $destination = null): void
    {
        $destination = $destination ?? base_path();

        if (! is_dir($destination)) {
            throw new ZipException("Destination directory does not exist: {$destination}");
        }

        if (! is_writable($destination)) {
            throw new ZipException("Destination directory is not writable: {$destination}. Please check permissions.");
        }

        if (! file_exists($zipFile)) {
            throw new ZipException("Zip file does not exist: {$zipFile}");
        }

        if (! is_readable($zipFile)) {
            throw new ZipException("Zip file is not readable: {$zipFile}");
        }

        $zip = new ZipArchive;
        $openResult = $zip->open($zipFile);

        if ($openResult !== true) {
            $errorMessage = $this->getZipErrorMessage($openResult);

            throw new ZipException("Failed to open zip file: {$errorMessage}");
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $targetPath = $destination . '/' . $stat['name'];
                $targetDir = dirname($targetPath);

                if (! is_dir($targetDir)) {
                    if (! @mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
                        $zip->close();

                        throw new ZipException("Cannot create directory: {$targetDir}. Permission denied.");
                    }
                }

                if (file_exists($targetPath) && ! is_writable($targetPath)) {
                    $zip->close();

                    throw new ZipException("Cannot overwrite file: {$targetPath}. Permission denied.");
                }
            }

            if (! $zip->extractTo($destination)) {
                $zip->close();

                throw new ZipException("Failed to extract zip file to: {$destination}. Check directory permissions.");
            }

            $zip->close();

        } catch (Throwable $e) {
            try {
                if (isset($zip) && $zip instanceof ZipArchive) {
                    @$zip->close();
                }
            } catch (Throwable $closeException) {
            }

            if (str_contains($e->getMessage(), 'Permission denied')) {
                throw new ZipException(
                    "Permission denied while extracting files. Please run:\n" .
                    "sudo chown -R www-data:www-data {$destination}\n" .
                    "sudo chmod -R 755 {$destination}\n" .
                    'Original error: ' . $e->getMessage()
                );
            }

            throw new ZipException('Failed to extract zip file: ' . $e->getMessage());
        }
    }

    private function getZipErrorMessage(int $code): string
    {
        $errors = [
            ZipArchive::ER_EXISTS => 'File already exists',
            ZipArchive::ER_INCONS => 'Zip archive inconsistent',
            ZipArchive::ER_INVAL  => 'Invalid argument',
            ZipArchive::ER_MEMORY => 'Malloc failure',
            ZipArchive::ER_NOENT  => 'No such file',
            ZipArchive::ER_NOZIP  => 'Not a zip archive',
            ZipArchive::ER_OPEN   => 'Can\'t open file',
            ZipArchive::ER_READ   => 'Read error',
            ZipArchive::ER_SEEK   => 'Seek error',
        ];

        return $errors[$code] ?? "Unknown error (code: {$code})";
    }
}
