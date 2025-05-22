<?php

namespace Buildstash\PostHogLaravel;

use Auth;
use Log;
use PostHog\PostHog;
use Buildstash\PostHogLaravel\Jobs\PosthogAliasJob;
use Buildstash\PostHogLaravel\Jobs\PosthogCaptureJob;
use Buildstash\PostHogLaravel\Jobs\PosthogGroupIdentifyJob;
use Buildstash\PostHogLaravel\Jobs\PosthogIdentifyJob;
use Buildstash\PostHogLaravel\Traits\UsesPosthog;

class PostHogLaravel
{
    use UsesPosthog;

    protected string $sessionId;
    protected string $groupType;
    protected string $groupId;

    public function __construct()
    {
        $this->sessionId = Auth::user()
            ? config('posthog.user_prefix', 'user').':'.Auth::user()->id
            : sha1(session()->getId());

        $this->groupType = config('posthog.group_type', 'workspace');
    }

    private function posthogEnabled(): bool
    {
        if (! config('posthog.enabled') || config('posthog.key') === '') {
            return false;
        }

        return true;
    }

    public function identify(string $email, array $properties = []): void
    {
        if ($this->posthogEnabled()) {
            PosthogIdentifyJob::dispatch($this->sessionId, $email, $properties, $this->groupType, $this->groupId);
        } else {
            Log::debug('PosthogIdentifyJob not dispatched because posthog is disabled');
        }
    }

    public function groupIdentify(string $groupKey, array $properties = []): void
    {
        if ($this->posthogEnabled())
        {
            $this->groupId = $groupKey;
            PosthogGroupIdentifyJob::dispatch($this->groupType, $this->groupId, $properties);
        }
        else
        {
            Log::debug('PosthogGroupIdentifyJob not dispatched because posthog is disabled');
        }
    }

    public function capture(string $event, array $properties = []): void
    {
        if ($this->posthogEnabled()) {
            PosthogCaptureJob::dispatch($this->sessionId, $event, $properties);
        } else {
            Log::debug('PosthogCaptureJob not dispatched because posthog is disabled');
        }
    }

    public function alias(string $userId): void
    {
        if ($this->posthogEnabled()) {
            PosthogAliasJob::dispatch($this->sessionId, $userId);
        } else {
            Log::debug('PosthogAliasJob not dispatched because posthog is disabled');
        }
    }

    public function isFeatureEnabled(
        string $featureKey,
        array $groups = [],
        array $personProperties = [],
        array $groupProperties = [],
    ): bool {
        return (bool) $this->getFeatureFlag(
            $featureKey,
            $groups,
            $personProperties,
            $groupProperties,
        );
    }

    public function getFeatureFlag(
        string $featureKey,
        array $groups = [],
        array $personProperties = [],
        array $groupProperties = [],
    ): null|bool|string {
        if ($this->posthogEnabled()) {
            $this->posthogInit();

            return Posthog::getFeatureFlag(
                $featureKey,
                $this->sessionId,
                $groups,
                $personProperties,
                $groupProperties,
                config('posthog.feature_flags.evaluate_locally') ?? false,
                config('posthog.feature_flags.send_events') ?? true,
            );
        }

        return config('posthog.feature_flags.default_enabled') ?? false;
    }

    public function getAllFlags(
        array $groups = [],
        array $personProperties = [],
        array $groupProperties = [],
    ): array {
        if ($this->posthogEnabled()) {
            $this->posthogInit();

            return Posthog::getAllFlags(
                $this->sessionId,
                $groups,
                $personProperties,
                $groupProperties,
                config('posthog.feature_flags.evaluate_locally') ?? false
            );
        }

        return [];
    }
}
