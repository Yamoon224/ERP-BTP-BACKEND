<?php

use App\Domains\Procurement\Enums\PurchaseOrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Longueur bornee : la reference sert d'index unique, et un
            // varchar(255) en utf8mb4 gonfle l'index sans rien apporter.
            $table->string('reference', 100)->unique();
            $table->foreignUuid('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('project_id')->constrained()->restrictOnDelete();
            // Devise du bon de commande : c'est la reference contractuelle
            // dans laquelle les prix des factures seront confrontes.
            $table->char('currency', 3);
            $table->string('status')->default(PurchaseOrderStatus::Open->value);
            $table->date('ordered_at');
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index('project_id');
        });

        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('item_code');
            $table->string('description');
            $table->string('unit', 16);
            // Quantites en 15,3 : le BTP se compte en m3, tonnes et heures,
            // pas seulement en unites entieres.
            $table->decimal('quantity_ordered', 15, 3);
            // Prix unitaires en 15,4 : un prix au kg peut porter 4 decimales
            // significatives, et arrondir avant le calcul fausserait le total.
            $table->decimal('unit_price', 15, 4);
            $table->timestamps();

            $table->unique(['purchase_order_id', 'line_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
