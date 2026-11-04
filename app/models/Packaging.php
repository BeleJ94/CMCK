<?php

class Packaging extends Model
{
    protected $table = 'packaging';

    /** Farine en vrac disponible, regroupée par site et produit (somme de tous les lots). */
    public function availableProducts()
    {
        $params = [];
        $siteClause = Auth::siteClause('b.site_id', $params);
        return $this->query(
            "SELECT b.site_id, b.product_id, sites.code AS site_code, sites.name AS site_name,
                    products.name AS product_name, products.code AS product_code,
                    SUM(b.quantity_kg) AS available_quantity_kg, COUNT(*) AS lots_count,
                    MIN(COALESCE(pb.ended_at, pb.started_at)) AS oldest_at
             FROM bulk_flour_stocks b
             INNER JOIN production_batches pb ON pb.id = b.production_batch_id AND pb.status = 'validated' AND pb.deleted_at IS NULL
             INNER JOIN products ON products.id = b.product_id AND products.category = 'finished_product'
             INNER JOIN sites ON sites.id = b.site_id
             WHERE b.deleted_at IS NULL AND b.status = 'active' AND b.quantity_kg > 0{$siteClause}
             GROUP BY b.site_id, b.product_id, sites.code, sites.name, products.name, products.code
             ORDER BY sites.code, products.name", $params
        )->fetchAll();
    }

    public static function stockKey($siteId, $productId)
    {
        return (int) $siteId . ':' . (int) $productId;
    }

    public static function parseStockKey($key)
    {
        if (!is_string($key) || !preg_match('/^(\d+):(\d+)$/', $key, $m)) {
            return null;
        }

        return ['site_id' => (int) $m[1], 'product_id' => (int) $m[2]];
    }

    public function findAvailableProduct($key)
    {
        $parsed = self::parseStockKey($key);
        if (!$parsed) {
            return null;
        }
        foreach ($this->availableProducts() as $row) {
            if ((int) $row['site_id'] === $parsed['site_id'] && (int) $row['product_id'] === $parsed['product_id']) {
                return $row;
            }
        }

        return null;
    }

    public function workshopStocks(): array
    {
        $params = [];
        $scope = Auth::siteClause('s.site_id', $params);
        return $this->query("SELECT s.site_id, s.packaging_item_id, s.operational_quantity,
            s.physical_quantity, s.reserved_quantity, sites.name AS site_name
            FROM empty_packaging_stocks s JOIN sites ON sites.id=s.site_id
            WHERE s.deleted_at IS NULL{$scope}", $params)->fetchAll();
    }

    public function bagFormats()
    {
        return $this->query(
            "SELECT i.id packaging_item_id,b.id,b.name,b.weight_kg,i.code,i.target_product_code
             FROM empty_packaging_items i JOIN bag_formats b ON b.id=i.bag_format_id
             WHERE i.status='active' AND i.deleted_at IS NULL AND b.status = 'active' AND b.deleted_at IS NULL
             ORDER BY b.weight_kg ASC"
        )->fetchAll();
    }

    public function history()
    {
        $params = [];
        $siteClause = Auth::siteClause('packaging.site_id', $params);
        $lots = $this->hasLinesTable()
            ? "COALESCE((SELECT GROUP_CONCAT(CONCAT(lb.batch_number, ' (', REPLACE(FORMAT(pl.quantity_kg, 0), ',', ' '), ' kg)') ORDER BY pl.id SEPARATOR ', ')
                              FROM packaging_lines pl INNER JOIN production_batches lb ON lb.id = pl.production_batch_id
                              WHERE pl.packaging_id = packaging.id), production_batches.batch_number)"
            : 'production_batches.batch_number';
        return $this->query(
            "SELECT packaging.*,
                    {$lots} AS batch_number,
                    products.name AS product_name,
                    bag_formats.name AS format_name,
                    bag_formats.weight_kg AS format_weight_kg,
                    users.name AS agent_name
             FROM packaging
             INNER JOIN production_batches ON production_batches.id = packaging.production_batch_id
             INNER JOIN products ON products.id = packaging.product_id
             INNER JOIN bag_formats ON bag_formats.id = packaging.bag_format_id
             LEFT JOIN users ON users.id = packaging.created_by
             WHERE packaging.deleted_at IS NULL{$siteClause}
             ORDER BY packaging.packaged_at DESC, packaging.id DESC", $params
        )->fetchAll();
    }

    public function findBagFormat($id)
    {
        return $this->query(
            "SELECT id, name, weight_kg
             FROM bag_formats
             WHERE id = :id
               AND status = 'active'
               AND deleted_at IS NULL
             LIMIT 1",
            ['id' => $id]
        )->fetch();
    }

    /**
     * Conditionne une quantité globale de farine en vrac (site + produit).
     * La farine est prélevée sur les lots les plus anciens d'abord ; chaque lot consommé
     * est enregistré dans packaging_lines (traçabilité, annulation).
     */
    public function createPackaging(array $data, array $user)
    {
        require_once dirname(__DIR__) . '/services/StockAllocationService.php';
        $source = self::parseStockKey($data['stock_key'] ?? '');
        if (!$source) {
            throw new RuntimeException('Choisissez la farine à conditionner.');
        }
        if (!$this->hasLinesTable()) {
            throw new RuntimeException('Le conditionnement par quantité globale nécessite la migration 052 (packaging_lines).');
        }
        Auth::requireSiteAccess($source['site_id']);

        $this->db->beginTransaction();

        try {
            // Sac vide de l'atelier (parcours normal) ; à défaut, simple format de sac (compatibilité des scripts historiques).
            $packagingItem = null;
            if (!empty($data['packaging_item_id'])) {
                $packagingItem = $this->query("SELECT i.*,b.weight_kg,b.name format_name FROM empty_packaging_items i JOIN bag_formats b ON b.id=i.bag_format_id WHERE i.id=:id AND i.status='active' AND i.deleted_at IS NULL FOR UPDATE", ['id' => $data['packaging_item_id']])->fetch();
                $format = $packagingItem ? ['id' => $packagingItem['bag_format_id'], 'weight_kg' => $packagingItem['weight_kg'], 'name' => $packagingItem['format_name']] : null;
            } else {
                $format = $this->findBagFormat($data['bag_format_id'] ?? 0);
            }
            if (!$format) {
                throw new RuntimeException('Format sac introuvable.');
            }

            $product = $this->query("SELECT id, code FROM products WHERE id=:id AND category='finished_product' AND deleted_at IS NULL", ['id' => $source['product_id']])->fetch();
            if (!$product) {
                throw new RuntimeException('Produit à conditionner introuvable.');
            }
            if ($packagingItem && $product['code'] !== $packagingItem['target_product_code']) {
                throw new RuntimeException('Format d’emballage incompatible avec le produit.');
            }

            $bags = (int) $data['bags_count'];
            if ($bags <= 0) {
                throw new RuntimeException('Le nombre de sacs est obligatoire et positif.');
            }
            $totalWeight = round((float) $format['weight_kg'] * $bags, 3);

            $emptyStock = null;
            if ($packagingItem) {
            $emptyStock = $this->query('SELECT * FROM empty_packaging_stocks WHERE site_id=:site AND packaging_item_id=:item AND deleted_at IS NULL FOR UPDATE', ['site' => $source['site_id'], 'item' => $packagingItem['id']])->fetch();
            if (!$emptyStock || (int) $emptyStock['operational_quantity'] < $bags) {
                throw new RuntimeException(sprintf('Conditionnement non enregistré : %d sacs demandés au format %s, mais seulement %d disponibles à l’atelier de ce site. Il manque %d sacs. Dans Emballages vides → Atelier, créez une demande, faites-la approuver puis délivrer. Les sacs au magasin ne sont pas encore utilisables à l’atelier. Réduisez la quantité ou réapprovisionnez l’atelier, puis réessayez.', $bags, $packagingItem['code'], (int) ($emptyStock['operational_quantity'] ?? 0), $bags - (int) ($emptyStock['operational_quantity'] ?? 0)));
            }
            }

            // Lots de farine en vrac du site et du produit, du plus ancien au plus récent, verrouillés.
            $lots = $this->query(
                "SELECT b.*, pb.batch_number
                 FROM bulk_flour_stocks b
                 INNER JOIN production_batches pb ON pb.id = b.production_batch_id AND pb.status = 'validated' AND pb.deleted_at IS NULL
                 WHERE b.site_id = :site AND b.product_id = :product AND b.status = 'active' AND b.quantity_kg > 0 AND b.deleted_at IS NULL
                 ORDER BY COALESCE(pb.ended_at, pb.started_at) ASC, b.id ASC
                 FOR UPDATE",
                ['site' => $source['site_id'], 'product' => $source['product_id']]
            )->fetchAll();
            try {
                $allocation = StockAllocationService::allocate($lots, $totalWeight, 'quantity_kg', 'kg de farine en vrac');
            } catch (RuntimeException $exception) {
                throw new RuntimeException('Impossible d’emballer plus que la farine en vrac disponible. ' . $exception->getMessage());
            }

            $this->query(
                "INSERT INTO packaging (
                    site_id, production_batch_id, bag_format_id, packaging_item_id, product_id, bags_count,
                    total_weight_kg, packaged_at, status, created_by
                 ) VALUES (
                    :site_id, :production_batch_id, :bag_format_id, :packaging_item_id, :product_id, :bags_count,
                    :total_weight_kg, :packaged_at, 'validated', :created_by
                 )",
                [
                    'site_id' => $source['site_id'],
                    // Premier lot prélevé, conservé pour les écrans historiques ; le détail complet est dans packaging_lines.
                    'production_batch_id' => $allocation[0]['lot']['production_batch_id'],
                    'bag_format_id' => $format['id'],
                    'packaging_item_id' => $packagingItem['id'] ?? null,
                    'product_id' => $source['product_id'],
                    'bags_count' => $bags,
                    'total_weight_kg' => $totalWeight,
                    'packaged_at' => $data['packaged_at'],
                    'created_by' => $user['id'] ?? null,
                ]
            );
            $packagingId = $this->db->lastInsertId();

            if ($emptyStock) {
            $emptyBefore = (int) $emptyStock['operational_quantity'];
            $emptyAfter = $emptyBefore - $bags;
            $this->query('UPDATE empty_packaging_stocks SET operational_quantity=:qty WHERE id=:id', ['qty' => $emptyAfter, 'id' => $emptyStock['id']]);
            $this->query("INSERT INTO empty_packaging_movements(stock_id,movement_type,quantity,stock_before,stock_after,unit_cost,reference_type,reference_id,movement_at,created_by) VALUES(:stock,'consumption',:qty,:before,:after,:cost,'packaging',:packaging,:at,:user)", ['stock' => $emptyStock['id'], 'qty' => $bags, 'before' => $emptyBefore, 'after' => $emptyAfter, 'cost' => $emptyStock['average_unit_cost'], 'packaging' => $packagingId, 'at' => $data['packaged_at'], 'user' => $user['id']]);
            }

            $allocated = [];
            foreach ($allocation as $line) {
                $lot = $line['lot'];
                $before = (float) $lot['quantity_kg'];
                $after = round($before - $line['quantity'], 3);
                $this->query("UPDATE bulk_flour_stocks SET quantity_kg=:qty,status=:status WHERE id=:id", ['qty' => $after, 'status' => $after > 0 ? 'active' : 'depleted', 'id' => $lot['id']]);
                $this->query("INSERT INTO bulk_flour_stock_movements(bulk_flour_stock_id,production_batch_id,packaging_id,movement_type,quantity_kg,stock_before_kg,stock_after_kg,movement_at,created_by) VALUES(:stock,:batch,:packaging,'packaging_out',:qty,:before,:after,:at,:user)", ['stock' => $lot['id'], 'batch' => $lot['production_batch_id'], 'packaging' => $packagingId, 'qty' => $line['quantity'], 'before' => $before, 'after' => $after, 'at' => $data['packaged_at'], 'user' => $user['id']]);
                $this->query('INSERT INTO packaging_lines(packaging_id,production_batch_id,bulk_flour_stock_id,quantity_kg) VALUES(:packaging,:batch,:stock,:qty)', ['packaging' => $packagingId, 'batch' => $lot['production_batch_id'], 'stock' => $lot['id'], 'qty' => $line['quantity']]);
                $allocated[] = ['lot' => $lot['batch_number'], 'quantity_kg' => $line['quantity']];
            }

            $this->query(
                "INSERT INTO finished_stocks (
                    site_id, product_id, bag_format_id, packaging_id, quantity_bags,
                    total_weight_kg, status
                 ) VALUES (
                    :site_id, :product_id, :bag_format_id, :packaging_id, :quantity_bags,
                    :total_weight_kg, 'active'
                 )",
                [
                    'site_id' => $source['site_id'], 'product_id' => $source['product_id'],
                    'bag_format_id' => $format['id'],
                    'packaging_id' => $packagingId,
                    'quantity_bags' => $bags,
                    'total_weight_kg' => $totalWeight,
                ]
            );
            $finishedStockId = $this->db->lastInsertId();
            $stockBefore = $this->latestProductStock($source['product_id'], $source['site_id']);
            $stockAfter = $stockBefore + $totalWeight;

            $this->query(
                "INSERT INTO stock_movements (
                    site_id, product_id, finished_stock_id, distribution_id, movement_type,
                    quantity_bags, quantity_kg, stock_before_kg, stock_after_kg,
                    movement_at, status, created_by
                 ) VALUES (
                    :site_id, :product_id, :finished_stock_id, NULL, 'in',
                    :quantity_bags, :quantity_kg, :stock_before_kg, :stock_after_kg,
                    :movement_at, 'validated', :created_by
                 )",
                [
                    'site_id' => $source['site_id'], 'product_id' => $source['product_id'],
                    'finished_stock_id' => $finishedStockId,
                    'quantity_bags' => $bags,
                    'quantity_kg' => $totalWeight,
                    'stock_before_kg' => $stockBefore,
                    'stock_after_kg' => $stockAfter,
                    'movement_at' => $data['packaged_at'],
                    'created_by' => $user['id'] ?? null,
                ]
            );
            $stockMovementId = $this->db->lastInsertId();

            $this->logActivity(
                'stock_movement',
                'stocks-finis',
                'stock_movements',
                $stockMovementId,
                'Entree stock fini depuis emballage.',
                ['product_id' => $source['product_id'], 'stock_kg' => $stockBefore],
                [
                    'product_id' => $source['product_id'],
                    'finished_stock_id' => $finishedStockId,
                    'packaging_id' => $packagingId,
                    'quantity_bags' => $bags,
                    'quantity_kg' => $totalWeight,
                    'stock_kg' => $stockAfter,
                    'lots' => $allocated,
                ],
                $user
            );

            // New bags go first to validated client orders still waiting for this product and format.
            require_once dirname(__DIR__) . '/services/CustomerOrderService.php';
            CustomerOrderService::allocatePending($this->db, (int) $source['site_id'], (int) $source['product_id'], (int) $format['id'], $user);

            $this->db->commit();

            return (int) $packagingId;
        } catch (Exception $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function hasLinesTable()
    {
        return (bool) $this->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'packaging_lines'")->fetchColumn();
    }

    private function latestProductStock($productId, $siteId)
    {
        $row = $this->query(
            "SELECT stock_after_kg
             FROM stock_movements
             WHERE product_id = :product_id AND site_id = :site_id
               AND deleted_at IS NULL
             ORDER BY movement_at DESC, id DESC
             LIMIT 1",
            ['product_id' => $productId, 'site_id' => $siteId]
        )->fetch();

        return $row ? (float) $row['stock_after_kg'] : 0;
    }
}
