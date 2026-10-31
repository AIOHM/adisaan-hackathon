<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answer_votes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('echo_submission_id')->index();
            // Anonymous identity: a hash, never the raw address or agent.
            $table->string('fingerprint', 64);
            $table->string('client_ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['echo_submission_id', 'fingerprint'], 'answer_votes_echo_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_votes');
    }
};
