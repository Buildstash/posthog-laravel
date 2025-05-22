<?php

namespace Buildstash\PostHogLaravel;

use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class PosthogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('PostHogLaravel', function ($app) {
            return new PostHogLaravel;
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/posthog.php' => config_path('posthog.php'),
        ]);

        Feature::extend('posthog', fn () => new Extensions\PosthogFeatureDriver);
    }
}
