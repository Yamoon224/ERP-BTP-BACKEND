<?php

use App\Domains\Payments\Enums\PaymentAuthorizationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_authorizations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('match_run_id')->constrained()->cascadeOnDelete();
            // Le paiement est autorise dans la devise de la facture.
            $table->char('currency', 3);
            $table->decimal('amount', 15, 2);

            // Contre-valeur en devise de reference et taux applique, pour le
            // pilotage et la comptabilite.
            $table->char('base_currency', 3);
            $table->decimal('base_amount', 15, 2)->default(0);
            $table->decimal('exchange_rate', 20, 10)->default(1);
            $table->string('status')->default(PaymentAuthorizationStatus::Active->value);
            $table->timestamp('authorized_at');
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->unique('match_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_authorizations');
    }
};
