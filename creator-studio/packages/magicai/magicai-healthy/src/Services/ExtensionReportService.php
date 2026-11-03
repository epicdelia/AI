<?php

namespace MagicAI\Healthy\Services;

use MagicAI\Healthy\Repositories\ExtensionRepository;

class ExtensionReportService
{
    public function __construct(
        private ExtensionRepository $extensionRepository
    ) {}

    public function generateLicenseReportSelected(array $extensions): array
    {
        $checkResults = $this->extensionRepository->checkSelectedExtensions($extensions);

        $report = [
            'total_extensions' => count($checkResults),
            'valid_licenses'   => 0,
            'invalid_licenses' => 0,
            'errors'           => 0,
            'extensions'       => $checkResults,
            'generated_at'     => time(),
        ];

        foreach ($checkResults as $result) {
            switch ($result['status']) {
                case 'valid':
                    $report['valid_licenses']++;

                    break;
                case 'invalid':
                    $report['invalid_licenses']++;

                    break;
                case 'error':
                    $report['errors']++;

                    break;
            }
        }

        return $report;
    }

    public function generateLicenseReport(): array
    {
        $checkResults = $this->extensionRepository->checkAllRegisteredExtensions();

        $report = [
            'total_extensions' => count($checkResults),
            'valid_licenses'   => 0,
            'invalid_licenses' => 0,
            'errors'           => 0,
            'extensions'       => $checkResults,
            'generated_at'     => time(),
        ];

        foreach ($checkResults as $result) {
            switch ($result['status']) {
                case 'valid':
                    $report['valid_licenses']++;

                    break;
                case 'invalid':
                    $report['invalid_licenses']++;

                    break;
                case 'error':
                    $report['errors']++;

                    break;
            }
        }

        return $report;
    }
}
