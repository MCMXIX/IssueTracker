<?php

namespace App\Providers;

use App\Interfaces\SummaryGenerator;
use App\Services\IssueSummary\FallbackSummaryGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SummaryGenerator::class, FallbackSummaryGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
