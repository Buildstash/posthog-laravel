<?php

namespace Buildstash\PostHogLaravel;

use Auth;
use Log;
use PostHog\Client as PostHogClient;
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
    protected ?string $groupId = null;

    public function __construct()
    {
        $user = request()->user() ?? Auth::user();

        $this->sessionId = $user
            ? config('posthog.user_prefix', 'user').':' . $user->id
            : sha1(session()->getId());

        $this->groupType = config('posthog.group_type', 'workspace');

        $this->groupId = $user && $user->getAttribute('workspace') && $user->getAttribute('workspace')->sqid !== null
            ? $user->getAttribute('workspace')->sqid
            : null;
    }

    private function posthogEnabled(): bool
    {
        if (!config('posthog.enabled') || config('posthog.key') === '') {
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
        $this->groupId = $groupKey;

        if ($this->posthogEnabled())
        {
            PosthogGroupIdentifyJob::dispatch($this->sessionId, $this->groupType, $this->groupId, $properties);
        }
        else
        {
            Log::debug('PosthogGroupIdentifyJob not dispatched because posthog is disabled');
        }
    }

    public function capture(string $event, array $properties = []): void
    {
        if ($this->posthogEnabled()) {
            PosthogCaptureJob::dispatch($this->sessionId, $event, $properties, $this->groupType, $this->groupId);
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

        // If feature flags are not cached, cache them now
        $cachedData = cache()->get('posthog_flags_cached');
        $evaluateLocally = config('posthog.feature_flags.evaluate_locally') ?? false;

        // Ensure cached data is an array
        $cachedData = is_array($cachedData) ? $cachedData : null;

        if ($cachedData === null)
        {
            $this->refreshFlags();
            $cachedData = cache()->get('posthog_flags_cached');
            $cachedData = is_array($cachedData) ? $cachedData : null;
        }

        if ($this->posthogEnabled())
        {
            $this->posthogInit();

            return PostHog::getFeatureFlag(
                $featureKey,
                $this->sessionId,
                array_merge($groups, [
                    $this->groupType => $this->groupId
                ]),
                $personProperties,
                $groupProperties,
                $evaluateLocally ? $cachedData : null,
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
        // If feature flags are not cached, cache them now
        $cachedData = cache()->get('posthog_flags_cached');
        $evaluateLocally = config('posthog.feature_flags.evaluate_locally') ?? false;

        // Ensure cached data is an array
        $cachedData = is_array($cachedData) ? $cachedData : null;

        if ($cachedData === null)
        {
            $this->refreshFlags();
            $cachedData = cache()->get('posthog_flags_cached');
            $cachedData = is_array($cachedData) ? $cachedData : null;
        }

        if ($this->posthogEnabled()) {
            $this->posthogInit();

            return Posthog::getAllFlags(
                $this->sessionId,
                $groups,
                $personProperties,
                $groupProperties,
                $evaluateLocally,
                $cachedData,
            );
        }

        return [];
    }

    public function refreshFlags(): void
    {
        // Don't do anything if PostHog is disabled
        if (!$this->posthogEnabled())
            Log::debug('PostHog flags were not refreshed as it is disabled');

        // Check if we can retrieve PostHog feature flag definitions from cache
        if (cache()->has('posthog_flags_cached'))
        {
            // Since their cached we can query the cache directly, and don't need to request from PostHog API
            return;
        }

        // Ensure PostHog is initialized
        $this->posthogInit();

        // Reload PostHog feature flag definitions
        // NOTE: Despite PostHog::loadFlags() being documented, it doesn't actually exist. This currently relies on a forked version of PostHog PHP SDK!
        $flagsRequestPayload = PostHog::loadFlags();

        // Store feature flag definitions in cache (Cache for 30 seconds)
        cache()->put('posthog_flags_cached', [
            'flags' => $flagsRequestPayload['flags'],
            'group_type_mapping' => $flagsRequestPayload['group_type_mapping'],
            'cohorts' => $flagsRequestPayload['cohorts']
        ], now()->addSeconds(30));
    }
}
