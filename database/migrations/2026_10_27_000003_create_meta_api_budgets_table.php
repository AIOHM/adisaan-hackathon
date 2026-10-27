<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_api_budgets', function (Blueprint $table): void {
            $table->string('period', 7)->primary();
            $table->unsignedBigInteger('committed_micros')->default(0);
            $table->unsignedInteger('requests')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_api_budgets');
    }
};
