<?php

use App\Domains\Shared\Enums\ExchangeRateSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique des taux de change.
 *
 * Table historisee (et non un simple couple devise/taux) : un rapprochement
 * doit pouvoir etre rejoue avec le taux en vigueur a la date de la facture, pas
 * avec celui d aujourd hui. Sans historique, rejouer une decision de l an
 * dernier produirait un montant different — et la piste d audit ne vaudrait
 * plus rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);

            // 20,10 : une parite comme 1 EUR = 655,957 XOF doit etre stockee
            // exactement, et l inverse (0,0015244...) reclame de la precision.
            $table->decimal('rate', 20, 10);

            $table->string('source', 32)->default(ExchangeRateSource::Manual->value);
            $table->date('effective_from');
            $table->timestamps();

            // Un seul taux par paire et par date d effet : deux taux
            // concurrents le meme jour rendraient le rapprochement non
            // deterministe.
            $table->unique(['base_currency', 'quote_currency', 'effective_from'], 'exchange_rates_pair_date_unique');
            $table->index(['base_currency', 'quote_currency', 'effective_from'], 'exchange_rates_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
