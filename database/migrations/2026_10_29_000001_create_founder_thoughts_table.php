<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('founder_thoughts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('echo_submission_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 16);
            $table->text('question');
            $table->text('answer');
            $table->text('why_valid');
            $table->string('status', 16)->default('draft')->index();
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
        Schema::dropIfExists('founder_thoughts');
    }
};
