<?php

/**
 * Répartit une quantité globale sur des lots de stock, sans choix manuel.
 *
 * Les lots sont fournis déjà triés selon la règle métier (le plus ancien d'abord,
 * ou la DLC la plus proche d'abord en boucherie) et verrouillés (SELECT ... FOR UPDATE)
 * par l'appelant, dans sa transaction. Le service ne fait aucune écriture : il renvoie
 * la part prélevée sur chaque lot, que l'appelant enregistre ligne par ligne pour la traçabilité.
 */
class StockAllocationService
{
    const FIFO = 'fifo';
    const FEFO = 'fefo';

    /**
     * @param array  $lots      Lots triés et verrouillés.
     * @param float  $quantity  Quantité demandée (> 0).
     * @param string $key       Colonne contenant la quantité disponible du lot.
     * @param string $unit      Unité affichée dans le message d'erreur.
     * @param int    $decimals  Précision (3 pour les kg, 0 pour les unités entières).
     * @return array Liste de ['lot' => array, 'quantity' => float], dans l'ordre de prélèvement.
     */
    public static function allocate(array $lots, $quantity, $key = 'quantity_kg', $unit = 'kg', $decimals = 3)
    {
        $quantity = round((float) $quantity, $decimals);
        if (!is_finite($quantity) || $quantity <= 0) {
            throw new RuntimeException('Saisissez une quantité positive.');
        }

        $available = self::total($lots, $key, $decimals);
        if ($quantity > $available) {
            throw new RuntimeException(sprintf(
                'Stock insuffisant : %s %s demandés, %s %s disponibles au total.',
                self::format($quantity, $decimals), $unit, self::format($available, $decimals), $unit
            ));
        }

        $remaining = $quantity;
        $lines = [];
        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }
            $lotAvailable = round(max(0, (float) $lot[$key]), $decimals);
            if ($lotAvailable <= 0) {
                continue;
            }
            $take = round(min($lotAvailable, $remaining), $decimals);
            $lines[] = ['lot' => $lot, 'quantity' => $take];
            $remaining = round($remaining - $take, $decimals);
        }

        return $lines;
    }

    public static function total(array $lots, $key = 'quantity_kg', $decimals = 3)
    {
        $sum = 0.0;
        foreach ($lots as $lot) {
            $sum += max(0, (float) $lot[$key]);
        }

        return round($sum, $decimals);
    }

    private static function format($value, $decimals)
    {
        return number_format((float) $value, $decimals, ',', ' ');
    }
}
