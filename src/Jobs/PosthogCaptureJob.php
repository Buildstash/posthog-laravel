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

class PosthogCaptureJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use UsesPosthog;

    public function __construct(private string $sessionId, private string $event, private array $properties = [], private ?string $groupType = null, private ?string $groupKey = null) {}

    public function handle(): void
    {
        $this->posthogInit();

        try
        {
            if (is_null($this->groupType) || is_null($this->groupKey))
            {
                Posthog::capture([
                    'distinctId' => $this->sessionId,
                    'event' => $this->event,
                    'properties' => $this->properties,
                ]);
            }
            else
            {
                Posthog::capture([
                    'distinctId' => $this->sessionId,
                    'event' => $this->event,
                    'properties' => $this->properties,
                    '$groups' => array($this->groupType => $this->groupKey)
                ]);
            }
        }
        catch (Exception $e)
        {
            Log::info('Posthog capture call failed:'.$e->getMessage());
        }
    }
}
