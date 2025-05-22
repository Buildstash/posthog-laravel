<?php

namespace Buildstash\PostHogLaravel\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PostHog\PostHog;
use Buildstash\PostHogLaravel\Traits\UsesPosthog;

class PosthogGroupIdentifyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use UsesPosthog;

    public function __construct(private string $groupType, private string $groupKey, private array $properties = []) {}

    public function handle(): void
    {
        $this->posthogInit();

        try
        {
            PostHog::groupIdentify([
                'groupType' => $this->groupType,
                'groupKey' => $this->groupKey,
                'properties' => $this->properties,
            ]);
        }
        catch (Exception $e)
        {
            Log::info('Posthog group identify call failed:'.$e->getMessage());
        }
    }
}
