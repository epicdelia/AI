<?php

namespace RachidLaasri\LaravelInstaller\Helpers;

class RequirementsChecker
{
    /**
     * Minimum PHP Version Supported (Override is in installer.php config file).
     *
     * @var _minPhpVersion
     */
    private $_minPhpVersion = '7.0.0';

    /**
     * Check for the server requirements.
     */
    public function check(array $requirements): array
    {
        $results = [];

        foreach ($requirements as $type => $requirement) {
            switch ($type) {
                // check php requirements
                case 'php':
                    foreach ($requirement as $subRequirement) {
                        $results['requirements'][$type][$subRequirement] = true;

                        if (! extension_loaded($subRequirement)) {
                            $results['requirements'][$type][$subRequirement] = false;

                            $results['errors'] = true;
                        }
                    }

                    break;
                    // check apache requirements
                case 'apache':
                    foreach ($requirement as $subRequirement) {
                        // if function doesn't exist we can't check apache modules
                        if (function_exists('apache_get_modules')) {
                            $results['requirements'][$type][$subRequirement] = true;

                            if (! in_array($subRequirement, apache_get_modules(), true)) {
                                $results['requirements'][$type][$subRequirement] = false;

                                $results['errors'] = true;
                            }
                        }
                    }

                    break;
            }
        }

        return $results;
    }

    /**
     * Check PHP version requirement.
     *
     * @return array
     */
    public function checkPHPversion(?string $minPhpVersion = null)
    {
        $minVersionPhp = $minPhpVersion;
        $currentPhpVersion = self::getPhpVersionInfo();
        $supported = false;

        if ($minPhpVersion === null) {
            $minVersionPhp = $this->getMinPhpVersion();
        }

        if (version_compare($currentPhpVersion['version'], $minVersionPhp) >= 0) {
            $supported = true;
        }

        return [
            'full'      => $currentPhpVersion['full'],
            'current'   => $currentPhpVersion['version'],
            'minimum'   => $minVersionPhp,
            'supported' => $supported,
        ];
    }

    /**
     * Get current Php version information.
     *
     * @return array
     */
    private static function getPhpVersionInfo()
    {
        $currentVersionFull = PHP_VERSION;
        preg_match("#^\d+(\.\d+)*#", $currentVersionFull, $filtered);
        $currentVersion = $filtered[0];

        return [
            'full'    => $currentVersionFull,
            'version' => $currentVersion,
        ];
    }

    /**
     * Get minimum PHP version ID.
     *
     * @return string _minPhpVersion
     */
    protected function getMinPhpVersion()
    {
        return $this->_minPhpVersion;
    }
}
