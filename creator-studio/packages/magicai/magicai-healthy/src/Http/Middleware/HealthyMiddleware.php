<?php

namespace MagicAI\Healthy\Http\Middleware;

use App\Helpers\Classes\Helper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MagicAI\Healthy\Exceptions\InvalidLicenseException;
use MagicAI\Healthy\Helpers\ObfuscationHelper;
use MagicAI\Healthy\Services\ExtensionCacheService;
use MagicAI\Healthy\Services\ExtensionValidationService;
use Symfony\Component\HttpFoundation\Response;

class HealthyMiddleware
{
    public function __construct(
        private ExtensionValidationService $s1,
        private ExtensionCacheService $s2,
        private ObfuscationHelper $s3
    ) {}

    public function handle(Request $r, Closure $n, string $k): Response
    {
        if (Helper::appIsDemo()) {
            return $n($r);
        }

        try {
            $this->verify($k);

            return $n($r);

        } catch (InvalidLicenseException $e) {
            Log::warning('Access violation', [
                'id'     => $this->s3->obfuscateKey($k),
                'msg'    => $e->getMessage(),
                'addr'   => $r->ip(),
                'agent'  => $r->userAgent(),
            ]);

            $this->s2->clearAll($k);
            abort(403);
        }
    }

    private function verify(string $k): void
    {
        $c = $this->s2->get($k);
        if ($c !== null) {
            if (! $c) {
                throw new InvalidLicenseException('Verification failed from cache.');
            }

            return;
        }

        $v = $this->s1->performValidation($k);
        $this->s2->put($k, $v);

        if (! $v) {
            throw new InvalidLicenseException('Access denied.');
        }
    }
}
