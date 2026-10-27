<?php

declare(strict_types=1);

use App\Http\Controllers\ObservatoryController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::controller(ObservatoryController::class)->group(function (): void {
    Route::get('/', 'home')->name('observatory.home');
    Route::get('/question', 'question')->name('observatory.question');
    Route::get('/answer', 'answer')->name('observatory.answer');
    Route::post('/answer', 'storeAnswer')->middleware('throttle:20,1')->name('observatory.answer.store');
    Route::get('/answer/received', 'received')->name('observatory.received');
    Route::get('/million', 'million')->name('observatory.million');
    Route::get('/privacy', 'privacy')->name('observatory.privacy');
    Route::get('/llms.txt', 'llms')->name('observatory.llms');
    Route::get('/sitemap.xml', 'sitemap')->name('observatory.sitemap');

    Route::middleware('throttle:60,1')->withoutMiddleware([ValidateCsrfToken::class])->group(function (): void {
        Route::get('/api/agent/answer', 'agentAnswerInfo')->name('observatory.agent.answer.info');
        Route::post('/api/agent/answer', 'storeAgentAnswer')->name('observatory.agent.answer');
        Route::options('/api/agent/answer', 'agentAnswerPreflight')->name('observatory.agent.answer.preflight');
        Route::get('/api/agent/answer/{receipt}', 'agentAnswerStatus')->where('receipt', '[0-9A-Za-z]{26}')->name('observatory.agent.answer.status');
        Route::get('/api/agent/answers', 'agentAnswers')->name('observatory.agent.answers');
        Route::get('/api/dataset.jsonl', 'dataset')->name('observatory.dataset');
        Route::get('/api/spending', 'spending')->name('observatory.spending');
        Route::get('/.well-known/agent.json', 'agentManifest')->name('observatory.agent.manifest');
    });
});
