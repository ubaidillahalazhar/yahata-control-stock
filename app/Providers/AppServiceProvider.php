<?php

namespace App\Providers;

use App\Services\ToolingCalculatorService;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ToolingCalculatorService::class);
    }

    public function boot(): void
    {
        Carbon::setLocale((string) config('app.locale', 'id'));
    }
}
