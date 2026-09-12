<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\GraphDriver;
use App\Graph\GraphBindings;
use App\Models\Passkey;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Passkeys;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $driver = GraphDriver::tryFrom(config()->string('graph.driver')) ?? GraphDriver::Memory;

        new GraphBindings($this->app)->register($driver);
    }

    public function boot(): void
    {
        Passkeys::usePasskeyModel(Passkey::class);

        Gate::defaultDenialResponse(
            Response::denyAsNotFound()
        );
    }
}
