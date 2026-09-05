<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reglement effectif d'une facture autorisee.
 *
 * Le reglement n'est pas un statut de plus sur l'autorisation : c'est un fait
 * qui s'ajoute a elle. Une autorisation reglee reste `active` — elle n'est plus
 * remplacable ni revocable, ce que le depot fait respecter — et porte en plus
 * la date, la reference bancaire et l'auteur du paiement. Modeliser cela en
 * colonnes plutot qu'en statut evite de perdre l'information « ce montant
 * etait autorise » le jour ou l'on paie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_authorizations', function (Blueprint $table): void {
            $table->timestamp('settled_at')->nullable()->after('authorized_at');
            $table->string('payment_reference', 100)->nullable()->after('settled_at');
            $table->string('payment_method', 30)->nullable()->after('payment_reference');
            $table->foreignId('settled_by')->nullable()->after('payment_method')
                ->constrained('users')->nullOnDelete();

            $table->index('settled_at');
        });
    }

    public function down(): void
    {
        Schema::table('payment_authorizations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('settled_by');
            $table->dropIndex(['settled_at']);
            $table->dropColumn(['settled_at', 'payment_reference', 'payment_method']);
        });
    }
};
