<?php

namespace Buildstash\PostHogLaravel\Facades;

use Illuminate\Support\Facades\Facade;

class PostHog extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'PostHogLaravel';
    }
}
