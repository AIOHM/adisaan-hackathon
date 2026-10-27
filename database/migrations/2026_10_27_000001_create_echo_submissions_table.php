<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('echo_submissions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('episode_ref')->nullable()->index();
            $table->string('prompt_key')->nullable()->index();
            $table->text('prompt_text')->nullable();
            $table->text('body');
            $table->string('signature')->nullable();
            $table->string('language', 12)->nullable();
            $table->string('country_code', 2)->nullable()->index();
            $table->string('city', 80)->nullable();
            $table->string('age_band', 16)->nullable();
            $table->string('theme')->nullable();
            $table->timestamp('synthesized_at')->nullable();
            $table->string('status', 24)->default('received');
            $table->string('quarantine_reason')->nullable();
            $table->string('contact_email')->nullable();
            $table->boolean('consent_quote')->default(false);
            $table->boolean('consent_followup')->default(false);
            $table->string('client_ip_hash', 64)->nullable()->index();
            $table->string('fingerprint', 64)->nullable()->index();
            $table->string('source_url', 2048)->nullable();
            $table->string('referrer', 2048)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('released_at')->nullable()->index();
            $table->text('display_body')->nullable();
            $table->text('transcript')->nullable();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->unsignedTinyInteger('review_score')->nullable();
            $table->string('review_reason', 255)->nullable();
            $table->json('review_meta')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['theme', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('echo_submissions');
    }
};
