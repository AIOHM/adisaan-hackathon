<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The "one email when a new episode is out" form on the home page. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('join_leads', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->boolean('consent_updates')->default(false);
            $table->string('source_channel', 64)->nullable();
            $table->string('referrer', 2048)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('join_leads');
    }
};
