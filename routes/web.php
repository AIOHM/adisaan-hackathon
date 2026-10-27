<?php

declare(strict_types=1);

use App\Http\Controllers\AdiSaanSiteController;
use App\Http\Controllers\TbwimController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
 * The public site: the AIOHM platform's adisaan.com pages, rendered here from
 * the movement's tables. Route names are the platform's, so the templates
 * are used unchanged. Pages that only exist on the brand site (members, news,
 * the journey, the manifesto) send the visitor there.
 */
Route::controller(AdiSaanSiteController::class)->group(function (): void {
    // The root serves the question experience (the hackathon entry point);
    // the brand home lives on at /construction.
    Route::get('/', 'question')->name('adisaan.public.home');
    Route::get('/construction', 'home')->name('adisaan.public.construction');
    Route::get('/ro', 'homeRo')->name('adisaan.public.home_ro');
    Route::post('/', 'lead')->middleware('throttle:30,1')->name('adisaan.public.construction.lead');
    Route::get('/question', 'question')->name('adisaan.public.question');
    Route::redirect('/questions', '/question', 301);
    Route::redirect('/episodes', '/question', 301)->name('adisaan.public.episodes');
    Route::get('/answer', 'answer')->name('adisaan.public.answer');
    Route::post('/answer', 'storeAnswer')->middleware('throttle:20,1')->name('adisaan.public.answer.store');
    Route::get('/answer/received', 'received')->name('adisaan.public.received');
    Route::post('/answer/received', 'storeReceived')->middleware('throttle:30,1')->name('adisaan.public.received.store');
    Route::get('/million', 'million')->name('adisaan.public.million');
    Route::get('/privacy', 'privacy')->name('adisaan.public.privacy');
    Route::get('/api/journey/scoreboard', 'boardJson')->middleware('throttle:60,1')->name('adisaan.public.board');
    Route::post('/cookie-consent/log', 'cookieConsent')->middleware('throttle:30,1')->name('cookie-consent.log');
    Route::post('/api/ask-muse', 'askMuse')->withoutMiddleware([ValidateCsrfToken::class])->middleware('throttle:30,1')->name('adisaan.public.ask-muse');
    Route::post('/api/ask-muse/follow-up', 'askMuseFollowUp')->withoutMiddleware([ValidateCsrfToken::class])->name('adisaan.public.ask-muse.follow-up');
});

foreach (['members' => '/members', 'members.profile' => '/members/profile', 'news' => '/news', 'journey' => '/journey', 'manifesto' => '/manifesto', 'about' => '/about'] as $name => $path) {
    Route::get($path, static fn () => redirect()->away(rtrim((string) config('adisaan_public.brand_url'), '/').$path, 302))->name('adisaan.public.'.$name);
}

/*
 * The movement's machine side: the agent contract, the dataset, the
 * spending ledger, the manifest. Unchanged.
 */
Route::controller(TbwimController::class)->group(function (): void {
    Route::get('/llms.txt', 'llms')->name('tbwim.llms');
    Route::get('/sitemap.xml', 'sitemap')->name('tbwim.sitemap');

    Route::middleware('throttle:60,1')->withoutMiddleware([ValidateCsrfToken::class])->group(function (): void {
        Route::get('/api/agent/answer', 'agentAnswerInfo')->name('adisaan.public.agent.answer.info');
        Route::post('/api/agent/answer', 'storeAgentAnswer')->name('tbwim.agent.answer');
        Route::options('/api/agent/answer', 'agentAnswerPreflight')->name('tbwim.agent.answer.preflight');
        Route::get('/api/agent/answer/{receipt}', 'agentAnswerStatus')->where('receipt', '[0-9A-Za-z]{26}')->name('tbwim.agent.answer.status');
        Route::get('/api/agent/answers', 'agentAnswers')->name('tbwim.agent.answers');
        Route::get('/api/dataset.jsonl', 'dataset')->name('tbwim.dataset');
        Route::get('/api/spending', 'spending')->name('tbwim.spending');
        Route::get('/.well-known/agent.json', 'agentManifest')->name('tbwim.agent.manifest');
    });
});
