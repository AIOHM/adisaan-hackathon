<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signal_questions', function (Blueprint $table): void {
            $table->string('brief_audio_path', 512)->nullable()->after('brief_script');
        });
    }

    public function down(): void
    {
        Schema::table('signal_questions', function (Blueprint $table): void {
            $table->dropColumn('brief_audio_path');
        });
    }
};
