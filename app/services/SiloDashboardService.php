<?php

/** Presentation calculations shared by the silo overview and its filters. */
class SiloDashboardService
{
    public static function build(array $silos, array $input = [])
    {
        $read = function ($key, $default = '') use ($input) {
            return isset($input[$key]) && is_scalar($input[$key]) ? trim((string) $input[$key]) : $default;
        };
        $filters = ['q' => mb_substr($read('q'), 0, 120), 'product' => $read('product'), 'status' => $read('status'), 'alerts' => $read('alerts') === '1', 'unit' => $read('unit') === 't' ? 't' : 'kg'];
        if (!in_array($filters['status'], ['', 'active', 'inactive'], true)) { $filters['status'] = ''; }
        $summary = ['stock' => 0.0, 'capacity' => 0.0, 'available' => 0.0, 'active' => 0, 'inactive' => 0, 'inactive_stock' => 0.0, 'alerts' => 0, 'unknown_capacity' => 0];
        $products = []; $alerts = []; $rows = [];
        foreach ($silos as $silo) {
            $stock = (float) $silo['current_stock_kg']; $capacity = (float) $silo['capacity_kg'];
            $active = in_array($silo['status'], ['active', 'validated'], true);
            $silo['occupation'] = $capacity > 0 ? $stock / $capacity * 100 : null;
            $silo['available'] = max(0, $capacity - $stock);
            $silo['active'] = $active;
            $silo['health'] = 'normal'; $silo['health_label'] = 'Normal'; $silo['priority'] = 4;
            if (!$active) {
                $silo['health'] = 'inactive'; $silo['health_label'] = ['inactive' => 'Inactif', 'pending' => 'En attente', 'cancelled' => 'Annulé'][$silo['status']] ?? 'Hors service'; $silo['priority'] = 5;
            } elseif ($capacity <= 0) {
                $silo['health'] = 'warning'; $silo['health_label'] = 'Capacité à renseigner'; $silo['priority'] = 1;
            } elseif ($stock > $capacity) {
                $silo['health'] = 'danger'; $silo['health_label'] = 'Capacité dépassée'; $silo['priority'] = 0;
            } elseif ((float) $silo['alert_threshold_kg'] > 0 && $stock <= (float) $silo['alert_threshold_kg']) {
                $silo['health'] = 'warning'; $silo['health_label'] = 'Stock bas'; $silo['priority'] = 2;
            } elseif ($silo['occupation'] >= 90) {
                $silo['health'] = 'warning'; $silo['health_label'] = 'Presque plein'; $silo['priority'] = 3;
            }
            $silo['alert'] = $active && $silo['health'] !== 'normal';
            if ($active) {
                $summary['active']++; $summary['stock'] += $stock; $summary['capacity'] += $capacity;
                $summary['available'] += $silo['available'];
                if ($capacity <= 0) { $summary['unknown_capacity']++; }
                if ($silo['alert']) { $summary['alerts']++; $alerts[] = $silo; }
            } else { $summary['inactive']++; $summary['inactive_stock'] += $stock; }
            $productKey = $silo['product_id'] === null ? 'none' : (string) $silo['product_id'];
            $products[$productKey] = $silo['product_name'] ?: 'Non affecté';
            $search = mb_strtolower($silo['code'] . ' ' . $silo['name'] . ' ' . ($silo['site_name'] ?? '') . ' ' . ($silo['product_name'] ?? ''));
            if ($filters['q'] !== '' && mb_strpos($search, mb_strtolower($filters['q'])) === false) { continue; }
            if ($filters['product'] !== '' && $filters['product'] !== $productKey) { continue; }
            if ($filters['status'] === 'active' && !$active || $filters['status'] === 'inactive' && $active) { continue; }
            if ($filters['alerts'] && !$silo['alert']) { continue; }
            $rows[] = $silo;
        }
        $sort = function ($a, $b) { return $a['priority'] <=> $b['priority'] ?: strnatcasecmp($a['name'], $b['name']); };
        usort($rows, $sort); usort($alerts, $sort); asort($products, SORT_NATURAL | SORT_FLAG_CASE);
        $summary['occupation'] = $summary['capacity'] > 0 && !$summary['unknown_capacity'] ? $summary['stock'] / $summary['capacity'] * 100 : null;
        return ['summary' => $summary, 'silos' => $rows, 'alerts' => $alerts, 'products' => $products, 'filters' => $filters, 'total' => count($silos)];
    }
}
