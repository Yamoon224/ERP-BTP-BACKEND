<?php

namespace App\Domains\Matching\Enums;

/**
 * Typologie des écarts détectés par le moteur. Chaque type porte sa gravité et
 * indique si un arbitrage favorable peut débloquer le paiement de la ligne.
 */
enum DiscrepancyType: string
{
    /** Prix unitaire facturé hors tolérance par rapport au PO. */
    case PriceVariance = 'price_variance';

    /** Quantité facturée supérieure à la quantité commandée restante. */
    case QuantityOverOrdered = 'quantity_over_ordered';

    /** Quantité livrée supérieure à la quantité commandée. */
    case QuantityOverDelivered = 'quantity_over_delivered';

    /** La ligne de facture ne référence aucune ligne de PO valide. */
    case MissingPurchaseOrderLine = 'missing_purchase_order_line';

    /** Le fournisseur de la facture n'est pas celui du PO. */
    case SupplierMismatch = 'supplier_mismatch';

    /**
     * Facture et bon de commande sont dans deux devises differentes, et aucun
     * taux de change n'est connu pour les rapprocher. Le systeme ne devine
     * jamais un taux : il bloque et le signale.
     */
    case MissingExchangeRate = 'missing_exchange_rate';

    /** Le PO n'est plus ouvert (clôturé ou annulé). */
    case PurchaseOrderNotOpen = 'purchase_order_not_open';

    public function severity(): DiscrepancySeverity
    {
        return match ($this) {
            self::SupplierMismatch,
            self::MissingExchangeRate,
            self::MissingPurchaseOrderLine,
            self::PurchaseOrderNotOpen => DiscrepancySeverity::Critical,
            self::QuantityOverOrdered,
            self::QuantityOverDelivered => DiscrepancySeverity::High,
            self::PriceVariance => DiscrepancySeverity::Medium,
        };
    }

    /**
     * Un arbitrage favorable sur ce type d'écart peut-il débloquer le paiement
     * de la ligne concernée ? Un fournisseur qui ne correspond pas ne se
     * « valide » pas : la facture doit être corrigée ou rejetée à la source.
     */
    public function isOverridable(): bool
    {
        return in_array($this, [self::PriceVariance, self::QuantityOverOrdered], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::PriceVariance => 'Écart de prix',
            self::QuantityOverOrdered => 'Quantité facturée supérieure au bon de commande',
            self::QuantityOverDelivered => 'Quantité livrée supérieure au bon de commande',
            self::MissingPurchaseOrderLine => 'Ligne de bon de commande absente',
            self::SupplierMismatch => 'Fournisseur non concordant',
            self::MissingExchangeRate => 'Taux de change indisponible',
            self::PurchaseOrderNotOpen => 'Bon de commande non ouvert',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
