<?php

use App\Domains\Receiving\Enums\DeliveryNoteStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 100);
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('status')->default(DeliveryNoteStatus::Draft->value);
            $table->date('received_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Un meme numero de BL ne peut etre enregistre deux fois pour un
            // fournisseur : premiere barriere contre la livraison fictive
            // ressaisie pour gonfler les quantites recues.
            $table->unique(['supplier_id', 'reference']);
            $table->index(['purchase_order_id', 'status']);
        });

        Schema::create('delivery_note_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_received', 15, 3);
            $table->timestamps();

            $table->index('purchase_order_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_lines');
        Schema::dropIfExists('delivery_notes');
    }
};
