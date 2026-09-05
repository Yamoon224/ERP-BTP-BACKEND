<?php

namespace App\Domains\Matching\Policies;

use App\Domains\Matching\Contracts\TolerancePolicyContract;
use App\Domains\Matching\DTOs\InvoiceLineInput;
use App\Domains\Matching\DTOs\InvoiceMatchInput;
use App\Domains\Matching\DTOs\Tolerance;

/**
 * Politique de tolérance par défaut : un seuil unique, lu dans
 * `config/matching.php`, appliqué à toutes les lignes.
 *
 * Sa seule subtilité est monétaire. Le seuil **relatif** (1 %) est indépendant
 * de la devise. Le seuil **absolu** ne l'est pas : 0,50 configuré en euro vaut
 * environ 328 francs CFA, et l'appliquer tel quel à un prix en XOF reviendrait
 * à ne tolérer aucun arrondi du tout. Le seuil absolu est donc converti dans la
 * devise de comparaison avant d'être appliqué.
 *
 * C'est aussi le point d'extension prévu par l'Open/Closed : une politique
 * contractuelle par fournisseur, ou plus permissive sur les matières en vrac
 * (sable, béton — dont la quantité livrée varie par nature), s'ajoute en
 * implémentant TolerancePolicyContract et en changeant un binding, sans jamais
 * rouvrir le moteur.
 */
final class ConfiguredTolerancePolicy implements TolerancePolicyContract
{
    public function __construct(private readonly Tolerance $tolerance) {}

    public function forLine(InvoiceMatchInput $input, InvoiceLineInput $line): Tolerance
    {
        return $this->inComparisonCurrency($input);
    }

    public function snapshot(InvoiceMatchInput $input): Tolerance
    {
        // On archive la tolérance telle qu'elle a été APPLIQUÉE, pas telle
        // qu'elle est configurée : c'est la première qui explique la décision.
        return $this->inComparisonCurrency($input);
    }

    private function inComparisonCurrency(InvoiceMatchInput $input): Tolerance
    {
        $comparison = $input->comparisonCurrency();

        if ($comparison === $this->tolerance->currency) {
            return $this->tolerance;
        }

        // Sans taux connu vers la devise de comparaison, on conserve le seuil
        // relatif et on neutralise le seuil absolu plutôt que d'appliquer une
        // valeur qui n'a pas de sens dans cette devise. Le moteur bloque de
        // toute façon la facture pour taux manquant.
        $rate = $input->baseToComparisonRate;

        if ($rate === null) {
            return new Tolerance(
                priceRatio: $this->tolerance->priceRatio,
                priceAbsolute: 0.0,
                quantityRatio: $this->tolerance->quantityRatio,
                quantityAbsolute: $this->tolerance->quantityAbsolute,
                currency: $comparison,
            );
        }

        return $this->tolerance->convertedTo($comparison, $rate->rate);
    }
}
