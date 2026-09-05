<?php

namespace App\Domains\Matching\DTOs;

use App\Domains\Shared\Enums\Currency;

/**
 * Seuils d'écart acceptés sans revue humaine, exprimés dans une devise donnée.
 *
 * La devise n'est pas un détail : un seuil absolu de 0,50 a un sens en euro et
 * aucun en franc CFA, où 0,50 est un demi-franc — soit moins que la plus petite
 * unité qui existe. La tolérance absolue est donc configurée dans la devise de
 * référence, puis **convertie** dans la devise de comparaison avant d'être
 * appliquée (voir ConfiguredTolerancePolicy).
 *
 * Immuable et sérialisable : une copie est figée dans chaque exécution du
 * moteur, pour qu'une décision archivée reste explicable après un changement de
 * réglage.
 */
final readonly class Tolerance
{
    public function __construct(
        public float $priceRatio,
        public float $priceAbsolute,
        public float $quantityRatio,
        public float $quantityAbsolute,
        /** Devise dans laquelle `priceAbsolute` est exprimé. */
        public Currency $currency = Currency::EUR,
    ) {}

    /**
     * Écart de prix unitaire absolu toléré pour un prix de référence donné :
     * le plus permissif des trois seuils.
     *
     * Le seuil relatif protège les articles à forte valeur ; le seuil absolu
     * évite qu'un article à 0,80 EUR/u parte en revue pour un centime
     * d'arrondi ; et la plus petite unité de la devise sert de plancher — après
     * une conversion, un écart inférieur au centime (ou au franc) n'est pas un
     * écart de prix, c'est une limite de représentation.
     */
    public function absolutePriceThresholdFor(float $referenceUnitPrice): float
    {
        return max(
            abs($referenceUnitPrice) * $this->priceRatio,
            $this->priceAbsolute,
            $this->currency->smallestUnit() / 2,
        );
    }

    /** Sur-facturation en quantité tolérée pour une quantité de référence. */
    public function absoluteQuantityThresholdFor(float $referenceQuantity): float
    {
        return max(abs($referenceQuantity) * $this->quantityRatio, $this->quantityAbsolute);
    }

    /** La même tolérance, son seuil absolu converti dans une autre devise. */
    public function convertedTo(Currency $currency, float $rate): self
    {
        return new self(
            priceRatio: $this->priceRatio,
            priceAbsolute: $this->priceAbsolute * $rate,
            quantityRatio: $this->quantityRatio,
            quantityAbsolute: $this->quantityAbsolute,
            currency: $currency,
        );
    }

    /** @return array<string, float|string> */
    public function toArray(): array
    {
        return [
            'price_ratio' => $this->priceRatio,
            'price_absolute' => $this->priceAbsolute,
            'quantity_ratio' => $this->quantityRatio,
            'quantity_absolute' => $this->quantityAbsolute,
            'currency' => $this->currency->value,
        ];
    }

    /** @param  array<string, float|int|string>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            priceRatio: (float) ($data['price_ratio'] ?? 0),
            priceAbsolute: (float) ($data['price_absolute'] ?? 0),
            quantityRatio: (float) ($data['quantity_ratio'] ?? 0),
            quantityAbsolute: (float) ($data['quantity_absolute'] ?? 0),
            currency: Currency::from((string) ($data['currency'] ?? Currency::EUR->value)),
        );
    }
}
