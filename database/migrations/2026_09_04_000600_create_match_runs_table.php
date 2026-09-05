<?php

use App\Domains\Matching\Enums\ActorType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Piste d'audit du rapprochement (regle fonctionnelle n5 : qui, quand, sur la
 * base de quelles donnees). Ces tables sont append-only : un nouveau
 * rapprochement d'une meme facture cree une nouvelle execution, il n'ecrase
 * jamais la precedente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            // QUI : soit le moteur (system), soit un utilisateur identifie qui
            // a declenche ou arbitre le rapprochement.
            $table->string('actor_type')->default(ActorType::System->value);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('trigger', 64);

            // SUR LA BASE DE QUELLES DONNEES : version du moteur et copie figee
            // des tolerances appliquees. Sans ca, une decision archivee ne peut
            // pas etre rejouee ni expliquee.
            $table->string('engine_version', 32);
            $table->json('tolerance_snapshot');

            $table->string('status');

            // Montants dans la devise de la FACTURE : c'est ce que le
            // fournisseur a reclame et ce qui lui sera regle.
            $table->char('currency', 3);
            $table->decimal('invoiced_amount', 15, 2);
            $table->decimal('matched_amount', 15, 2);
            $table->decimal('unmatched_amount', 15, 2);

            // Les memes montants dans la devise de reference, uniquement pour
            // l'agregation : additionner des euros, des dollars et des francs
            // CFA sur un tableau de bord ne produirait aucun chiffre sensé.
            $table->char('base_currency', 3);
            $table->decimal('base_matched_amount', 15, 2)->default(0);
            $table->decimal('base_unmatched_amount', 15, 2)->default(0);

            // Taux appliques, figes avec la decision : un montant converti sans
            // son taux est un chiffre invérifiable.
            $table->json('exchange_rate_snapshot')->nullable();

            $table->unsignedInteger('exception_count')->default(0);

            // QUAND : horodatage explicite de la decision, distinct de
            // created_at pour rester lisible meme si la ligne est copiee.
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->index(['invoice_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('match_line_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status');
            $table->decimal('quantity_invoiced', 15, 3);
            $table->decimal('quantity_matched', 15, 3);
            $table->decimal('quantity_unmatched', 15, 3);
            $table->decimal('unit_price_invoiced', 15, 4);
            $table->decimal('unit_price_ordered', 15, 4)->nullable();
            $table->decimal('price_variance_ratio', 10, 6)->nullable();
            $table->decimal('matched_amount', 15, 2);

            // Copie des agregats ayant servi au calcul (quantite commandee,
            // recue, deja rapprochee ailleurs). Permet de rejouer la ligne sans
            // dependre de l'etat courant du PO, qui aura change entre-temps.
            $table->json('evidence');
            $table->timestamps();

            $table->index('invoice_line_id');
            $table->index('purchase_order_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_line_results');
        Schema::dropIfExists('match_runs');
    }
};
