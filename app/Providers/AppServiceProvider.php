<?php

namespace App\Providers;

use App\Llm\AnthropicLlmClient;
use App\Llm\GeminiLlmClient;
use App\Llm\LlmClient;
use App\Llm\MockLlmClient;
use App\Models\AgentTask;
use App\Models\Document;
use App\Policies\DocumentPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LlmClient::class, function () {
            $driver = config('trustgate.llm_driver');

            if ($driver === 'anthropic') {
                return new AnthropicLlmClient(
                    (string) config('services.anthropic.key', ''),
                    (string) config('services.anthropic.model', 'claude-3-5-sonnet-latest'),
                );
            }

            if ($driver === 'gemini') {
                return new GeminiLlmClient(
                    (string) config('services.gemini.key', ''),
                    (string) config('services.gemini.model', 'gemini-1.5-flash-latest'),
                );
            }

            return new MockLlmClient;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(AgentTask::class, TaskPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
    }
}
