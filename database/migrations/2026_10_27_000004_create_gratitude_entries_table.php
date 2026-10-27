<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gratitude tokens: one row per thing a visitor did, never a running total.
 *
 * A balance is the sum of the rows, so every token on the site can be traced
 * to the act that earned it and the moment it was earned. A stored total
 * would be faster and would quietly drift; this is a ledger because people
 * will ask where their tokens went, and the honest answer has to exist.
 *
 * Spending is the same table with a negative amount, so there is one history
 * and no second place for a balance to disagree with itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gratitude_entries')) {
            return;
        }

        Schema::create('gratitude_entries', function (Blueprint $table) {
            $table->id();

            // Who earned it. The answer form is anonymous, so this is the
            // opaque id from the visitor's signed cookie — never a name.
            $table->string('visitor_id', 64)->index();

            // Set when a visitor claims their trail with a name and email.
            // Until then the tokens are pending and cannot be spent, which is
            // what stops the economy being farmed by clearing cookies.
            $table->string('claimed_email')->nullable()->index();
            $table->timestamp('claimed_at')->nullable();

            // What happened. 'answer', 'why', 'share', 'newsletter', 'claim',
            // 'streak', 'first_answer', 'vote', 'episode_pick', 'spend'.
            $table->string('reason', 40)->index();
            $table->integer('amount'); // negative when spent

            // What it was for, so an award can be idempotent: one answer on
            // one question earns once, however many times the page is loaded.
            $table->string('subject_type', 40)->nullable();
            $table->string('subject_id', 120)->nullable();

            // An answer earns only once it clears review, so a token cannot
            // be bought with text nobody will keep.
            $table->boolean('confirmed')->default(true);

            $table->json('metadata')->nullable();
            $table->timestamps();

            // The idempotency guarantee, enforced by the database rather than
            // by remembering to check: one award per visitor, per reason, per
            // subject. Nullable subject columns mean repeatable reasons
            // (a daily vote) are left to the service to bound.
            $table->unique(['visitor_id', 'reason', 'subject_type', 'subject_id'], 'gratitude_once_per_subject');
            $table->index(['visitor_id', 'confirmed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gratitude_entries');
    }
};
