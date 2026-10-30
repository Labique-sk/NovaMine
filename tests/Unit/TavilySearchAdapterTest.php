<?php

namespace Tests\Unit;

use App\Services\Sources\TavilySearchAdapter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TavilySearchAdapterTest extends TestCase
{
    public function test_it_uses_bearer_auth_and_ignores_job_titles_as_candidate_names(): void
    {
        config()->set('services.tavily.key', 'tvly-test-key');

        Http::fake([
            'https://api.tavily.com/search' => Http::response([
                'results' => [
                    [
                        'title' => 'Jane Doe | Machine Learning Engineer',
                        'url' => 'https://janedoe.example/about',
                        'content' => 'Jane Doe builds machine learning systems.',
                    ],
                    [
                        'title' => 'Applied Machine Learning Engineer Resume',
                        'url' => 'https://example.test/resume',
                        'content' => 'Resume and experience details.',
                    ],
                ],
            ]),
        ]);

        $records = (new TavilySearchAdapter)->collect(['role' => 'Machine Learning Engineer']);

        $this->assertSame(['Jane Doe'], array_column($records, 'name'));
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer tvly-test-key'));
    }
}
