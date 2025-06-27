<?php

namespace Buildstash\PostHogLaravel\Traits;

use Exception;
use Illuminate\Support\Facades\Log;
use PostHog\PostHog;

trait UsesPosthog
{
    public function posthogInit(): void
    {
        try {
            PostHog::init(config('posthog.key'),
                ['host' => config('posthog.host')],
                null,
                config('posthog.secure_api_key')
            );
        } catch (Exception $e) {
            Log::error('Posthog initialization failed: '.$e->getMessage());
        }
    }
}
