<?php

use App\Domains\Invoicing\Enums\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference', 100);
            $table->foreignUuid('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('purchase_order_id')->constrained()->restrictOnDelete();
            // Devise de facturation : celle dans laquelle le fournisseur
            // sera regle. Elle peut differer de celle du bon de commande,
            // le rapprochement convertit alors au taux du jour de facture.
            $table->char('currency', 3);
            $table->string('status')->default(InvoiceStatus::Received->value);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Contrainte anti-doublon de paiement : un fournisseur ne peut pas
            // soumettre deux fois la meme reference de facture.
            $table->unique(['supplier_id', 'reference']);
            $table->index(['purchase_order_id', 'status']);
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            // Nullable : une facture peut arriver avec une ligne qui ne
            // reference aucune ligne de PO. Le moteur doit pouvoir la recevoir
            // pour la signaler, plutot que de rejeter la facture a la saisie.
            $table->foreignUuid('purchase_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('description');
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 4);
            $table->timestamps();

            $table->unique(['invoice_id', 'line_number']);
            $table->index('purchase_order_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
