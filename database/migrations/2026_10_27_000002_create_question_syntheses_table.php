<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_syntheses', function (Blueprint $table): void {
            $table->id();
            $table->string('question_ref')->index();
            $table->unsignedInteger('version');
            $table->text('body');
            $table->unsignedInteger('voices_site')->default(0);
            $table->unsignedInteger('voices_agents')->default(0);
            $table->json('themes')->nullable();
            $table->string('model')->nullable();
            $table->string('prompt_version', 32)->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->unique(['question_ref', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_syntheses');
    }
};
