<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The analytics choice a visitor made, as the privacy page says it is logged. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookie_consent_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('consent_id', 64)->index();
            $table->string('decision', 24);
            $table->string('region', 16)->nullable();
            $table->json('preferences')->nullable();
            $table->json('detected_cookies')->nullable();
            $table->string('page', 2048)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookie_consent_logs');
    }
};
