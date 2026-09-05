<?php

use App\Domains\Matching\Enums\ReviewStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File de revue humaine (regle fonctionnelle n6). Un ecart n'est ni accepte ni
 * rejete silencieusement : il devient une ligne ici, avec son contexte chiffre,
 * et attend un arbitrage trace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            // Nullable : un ecart peut porter sur la facture entiere
            // (fournisseur, devise) et non sur une ligne precise.
            $table->foreignId('invoice_line_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('match_line_result_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('type');
            $table->string('severity');
            $table->string('message');
            $table->json('context');

            $table->string('review_status')->default(ReviewStatus::Open->value);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();

            $table->index(['review_status', 'severity']);
            $table->index(['invoice_id', 'review_status']);
            $table->index(['invoice_line_id', 'review_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_exceptions');
    }
};
