<?php

namespace App\Providers;

use App\Services\Intelligence\CandidateIntelligenceProviderInterface;
use App\Services\Intelligence\RuleBasedIntelligenceProvider;
use App\Services\Sources\BraveWebSearchAdapter;
use App\Services\Sources\GenericSearchAdapter;
use App\Services\Sources\PublicDatasetAdapter;
use App\Services\Sources\SourceAdapterInterface;
use App\Services\Sources\SourceAdapterRegistry;
use App\Services\Sources\TavilySearchAdapter;
use App\Services\Sources\UserProvidedUrlAdapter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CandidateIntelligenceProviderInterface::class, RuleBasedIntelligenceProvider::class);

        $this->app->tag([
            PublicDatasetAdapter::class,
            GenericSearchAdapter::class,
            BraveWebSearchAdapter::class,
            TavilySearchAdapter::class,
            UserProvidedUrlAdapter::class,
        ], SourceAdapterInterface::class);

        $this->app->singleton(SourceAdapterRegistry::class, function ($app) {
            return new SourceAdapterRegistry($app->tagged(SourceAdapterInterface::class));
        });
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
