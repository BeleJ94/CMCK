<?php

class FinishedStock extends Model
{
    protected $table = 'finished_stocks';

    public function productStock()
    {
        $params = [];
        $siteId = Auth::currentSiteId();
        $finishedSite = $siteId === null && Auth::canViewConsolidated() ? '' : ' AND finished_stocks.site_id = :finished_scope_site_id';
        $entriesSite = $siteId === null && Auth::canViewConsolidated() ? '' : ' AND site_id = :entries_scope_site_id';
        $outputsSite = $siteId === null && Auth::canViewConsolidated() ? '' : ' AND site_id = :outputs_scope_site_id';
        if ($finishedSite !== '') {
            $params['finished_scope_site_id'] = $siteId;
            $params['entries_scope_site_id'] = $siteId;
            $params['outputs_scope_site_id'] = $siteId;
        }
        return $this->query(
            "SELECT products.id,
                    products.name,
                    products.code,
                    COALESCE(SUM(finished_stocks.quantity_bags), 0) AS available_bags,
                    COALESCE(SUM(finished_stocks.total_weight_kg), 0) AS available_kg,
                    COALESCE(entries.total_in_kg, 0) AS entries_kg,
                    COALESCE(entries.total_in_bags, 0) AS entries_bags,
                    COALESCE(outputs.total_out_kg, 0) AS outputs_kg,
                    COALESCE(outputs.total_out_bags, 0) AS outputs_bags
             FROM products
             LEFT JOIN finished_stocks ON finished_stocks.product_id = products.id
                AND finished_stocks.deleted_at IS NULL
                AND finished_stocks.status IN ('active', 'validated'){$finishedSite}
             LEFT JOIN (
                SELECT product_id,
                       SUM(quantity_kg) AS total_in_kg,
                       SUM(quantity_bags) AS total_in_bags
                FROM stock_movements
                WHERE movement_type = 'in'
                  AND status IN ('active', 'validated')
                  AND deleted_at IS NULL{$entriesSite}
                GROUP BY product_id
             ) entries ON entries.product_id = products.id
             LEFT JOIN (
                SELECT product_id,
                       SUM(quantity_kg) AS total_out_kg,
                       SUM(quantity_bags) AS total_out_bags
                FROM stock_movements
                WHERE movement_type = 'out'
                  AND status IN ('active', 'validated')
                  AND deleted_at IS NULL{$outputsSite}
                GROUP BY product_id
             ) outputs ON outputs.product_id = products.id
             WHERE products.code IN ('FARINE-MAIS', 'ALIMENT-BETAIL')
               AND products.deleted_at IS NULL
             GROUP BY products.id, products.name, products.code, entries.total_in_kg, entries.total_in_bags, outputs.total_out_kg, outputs.total_out_bags
             ORDER BY products.name ASC", $params
        )->fetchAll();
    }

    public function formatStock()
    {
        $params = [];
        $siteClause = Auth::siteClause('finished_stocks.site_id', $params);
        return $this->query(
            "SELECT products.name AS product_name,
                    products.code AS product_code,
                    bag_formats.name AS format_name,
                    bag_formats.weight_kg,
                    COALESCE(SUM(finished_stocks.quantity_bags), 0) AS available_bags,
                    COALESCE(SUM(finished_stocks.total_weight_kg), 0) AS available_kg
             FROM finished_stocks
             INNER JOIN products ON products.id = finished_stocks.product_id
             INNER JOIN bag_formats ON bag_formats.id = finished_stocks.bag_format_id
             WHERE finished_stocks.deleted_at IS NULL
               AND finished_stocks.status IN ('active', 'validated')
               AND products.code IN ('FARINE-MAIS', 'ALIMENT-BETAIL'){$siteClause}
             GROUP BY products.id, bag_formats.id
             ORDER BY products.name ASC, bag_formats.weight_kg ASC", $params
        )->fetchAll();
    }

    public function movements()
    {
        $params = [];
        $siteClause = Auth::siteClause('stock_movements.site_id', $params);
        return $this->query(
            "SELECT stock_movements.*,
                    products.name AS product_name,
                    bag_formats.name AS format_name,
                    bag_formats.weight_kg AS format_weight_kg,
                    packaging.id AS packaging_id,
                    distributions.recipient_name,
                    users.name AS agent_name
             FROM stock_movements
             INNER JOIN products ON products.id = stock_movements.product_id
             LEFT JOIN finished_stocks ON finished_stocks.id = stock_movements.finished_stock_id
             LEFT JOIN bag_formats ON bag_formats.id = finished_stocks.bag_format_id
             LEFT JOIN packaging ON packaging.id = finished_stocks.packaging_id
             LEFT JOIN distributions ON distributions.id = stock_movements.distribution_id
             LEFT JOIN users ON users.id = stock_movements.created_by
             WHERE stock_movements.deleted_at IS NULL
               AND products.code IN ('FARINE-MAIS', 'ALIMENT-BETAIL'){$siteClause}
             ORDER BY stock_movements.movement_at DESC, stock_movements.id DESC", $params
        )->fetchAll();
    }

    public function entriesByPackaging()
    {
        $params = [];
        $siteClause = Auth::siteClause('packaging.site_id', $params);
        return $this->query(
            "SELECT packaging.*,
                    products.name AS product_name,
                    bag_formats.name AS format_name,
                    bag_formats.weight_kg AS format_weight_kg,
                    production_batches.batch_number,
                    users.name AS agent_name
             FROM packaging
             INNER JOIN products ON products.id = packaging.product_id
             INNER JOIN bag_formats ON bag_formats.id = packaging.bag_format_id
             INNER JOIN production_batches ON production_batches.id = packaging.production_batch_id
             LEFT JOIN users ON users.id = packaging.created_by
             WHERE packaging.deleted_at IS NULL
               AND packaging.status IN ('active', 'validated')
               AND products.code IN ('FARINE-MAIS', 'ALIMENT-BETAIL'){$siteClause}
             ORDER BY packaging.packaged_at DESC, packaging.id DESC", $params
        )->fetchAll();
    }

    public function outputsByDistribution()
    {
        $params = [];
        $siteClause = Auth::siteClause('distributions.site_id', $params);
        return $this->query(
            "SELECT distributions.*,
                    products.name AS product_name,
                    bag_formats.name AS format_name,
                    bag_formats.weight_kg AS format_weight_kg,
                    users.name AS agent_name,
                    validators.name AS validator_name
             FROM distributions
             INNER JOIN products ON products.id = distributions.product_id
             INNER JOIN bag_formats ON bag_formats.id = distributions.bag_format_id
             LEFT JOIN users ON users.id = distributions.created_by
             LEFT JOIN users validators ON validators.id = distributions.validated_by
             WHERE distributions.deleted_at IS NULL
               AND distributions.status IN ('active', 'validated')
               AND products.code IN ('FARINE-MAIS', 'ALIMENT-BETAIL'){$siteClause}
             ORDER BY distributions.distributed_at DESC, distributions.id DESC", $params
        )->fetchAll();
    }

    public function ruptureAlerts()
    {
        $alerts = [];

        foreach ($this->productStock() as $product) {
            $available = (float) $product['available_kg'];
            if ($available <= 0) {
                $alerts[] = [
                    'severity' => 'danger',
                    'title' => 'Rupture ' . $product['name'],
                    'message' => 'Aucun stock disponible pour ce produit fini.',
                ];
            } elseif ($available <= 500) {
                $alerts[] = [
                    'severity' => 'warning',
                    'title' => 'Stock faible ' . $product['name'],
                    'message' => 'Le solde disponible est inferieur ou egal a 500 kg.',
                ];
            }
        }

        return $alerts;
    }
}
