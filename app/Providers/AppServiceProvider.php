<?php

namespace App\Providers;

use App\Automations\AutomationEngine;
use App\Events\DomainEvent;
use App\Models\CaseFile;
use App\Models\Document;
use App\Policies\CaseFilePolicy;
use App\Policies\DocumentPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Gate::policy(CaseFile::class, CaseFilePolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);

        // Super Administrators pass all ability checks.
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Administrator') ? true : null;
        });

        // Every DomainEvent flows into the automation engine.
        Event::listen('App\Events\*', function (string $eventName, array $data) {
            $event = $data[0] ?? null;
            if ($event instanceof DomainEvent) {
                app(AutomationEngine::class)->handle($event);
            }
        });
    }
}
