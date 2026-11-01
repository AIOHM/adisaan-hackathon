<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signal_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('signal_title');
            $table->string('signal_url', 1024);
            $table->string('signal_source')->nullable();
            $table->string('signal_category', 64)->nullable();
            $table->text('question');
            $table->text('why');
            $table->text('brief_script');
            $table->string('status', 16)->default('draft')->index();
            $table->string('episode_ref')->nullable()->unique();
            $table->date('scheduled_for')->nullable()->index();
            $table->string('model')->nullable();
            $table->string('prompt_version', 32)->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signal_questions');
    }
};
