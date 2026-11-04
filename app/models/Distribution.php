<?php

class Distribution extends Model
{
    protected $table = 'distributions';

    public function availableStocks()
    {
        $params = [];
        $siteClause = Auth::siteClause('finished_stocks.site_id', $params);
        return $this->query(
            "SELECT finished_stocks.id, finished_stocks.site_id, sites.code AS site_code, sites.name AS site_name,
                    finished_stocks.created_at AS stock_date,
                    finished_stocks.product_id,
                    finished_stocks.bag_format_id,
                    finished_stocks.quantity_bags - finished_stocks.reserved_bags AS quantity_bags,
                    finished_stocks.total_weight_kg - finished_stocks.reserved_weight_kg AS total_weight_kg,
                    products.name AS product_name,
                    products.code AS product_code,
                    bag_formats.name AS format_name,
                    bag_formats.weight_kg
             FROM finished_stocks
             INNER JOIN sites ON sites.id=finished_stocks.site_id
             INNER JOIN products ON products.id = finished_stocks.product_id
             INNER JOIN bag_formats ON bag_formats.id = finished_stocks.bag_format_id
             WHERE finished_stocks.deleted_at IS NULL
               AND finished_stocks.status IN ('active', 'validated')
               AND finished_stocks.quantity_bags - finished_stocks.reserved_bags > 0
               AND finished_stocks.total_weight_kg - finished_stocks.reserved_weight_kg > 0
               AND products.code IN ('FARINE-MAIS', 'ALIMENT-BETAIL'){$siteClause}
             ORDER BY products.name ASC, bag_formats.weight_kg ASC, finished_stocks.created_at ASC, finished_stocks.id ASC", $params
        )->fetchAll();
    }

    /**
     * Stock libre regroupé par site, produit et format : ce que l'utilisateur choisit.
     * Les lots sont listés du plus ancien au plus récent, dans l'ordre où ils seront prélevés.
     */
    public function availableProducts(?array $stocks = null)
    {
        $groups = [];
        foreach ($stocks ?? $this->availableStocks() as $stock) {
            $free = self::freeBags($stock['quantity_bags'], $stock['total_weight_kg'], $stock['weight_kg']);
            if ($free <= 0) {
                continue;
            }
            $siteId = (int) ($stock['site_id'] ?? 0);
            $key = self::stockKey($siteId, $stock['product_id'], $stock['bag_format_id']);
            if (!isset($groups[$key])) {
                $groups[$key] = ['key' => $key, 'site_id' => $siteId, 'site_code' => $stock['site_code'], 'site_name' => $stock['site_name'] ?? $stock['site_code'], 'product_id' => (int) $stock['product_id'], 'bag_format_id' => (int) $stock['bag_format_id'], 'product_name' => $stock['product_name'], 'format_name' => $stock['format_name'], 'weight_kg' => (float) $stock['weight_kg'], 'free_bags' => 0, 'lots' => []];
            }
            $groups[$key]['free_bags'] += $free;
            $groups[$key]['lots'][] = ['id' => (int) $stock['id'], 'date' => $stock['stock_date'] ? date('d/m/Y', strtotime($stock['stock_date'])) : '', 'bags' => $free];
        }
        uasort($groups, static function ($a, $b) { return [$a['site_name'], $a['product_name'], $a['weight_kg']] <=> [$b['site_name'], $b['product_name'], $b['weight_kg']]; });

        return array_values($groups);
    }

    public static function stockKey($siteId, $productId, $formatId)
    {
        return (int) $siteId . ':' . (int) $productId . ':' . (int) $formatId;
    }

    public static function parseStockKey($key)
    {
        if (!is_string($key) || !preg_match('/^(\d+):(\d+):(\d+)$/', $key, $m)) {
            return null;
        }

        return ['site_id' => (int) $m[1], 'product_id' => (int) $m[2], 'bag_format_id' => (int) $m[3]];
    }

    /** Sacs entiers réellement libres d'un lot (sacs et poids non réservés). */
    public static function freeBags($bags, $kg, $weight)
    {
        return max(0, min((int) $bags, (int) floor(((float) $kg + 0.0001) / max(0.001, (float) $weight))));
    }

    public function history()
    {
        $params = [];
        $siteClause = Auth::siteClause('distributions.site_id', $params);
        return $this->query(
            "SELECT distributions.*, sites.code AS site_code,
                    products.name AS product_name,
                    products.code AS product_code,
                    bag_formats.name AS format_name,
                    bag_formats.weight_kg,
                    users.name AS agent_name,
                    validators.name AS validator_name,
                    official_document.document_number AS official_document_number,
                    (SELECT GROUP_CONCAT(CONCAT('#', sm.finished_stock_id, ' : ', sm.quantity_bags, ' sac(s)') ORDER BY sm.id SEPARATOR ' · ') FROM stock_movements sm WHERE sm.distribution_id = distributions.id AND sm.movement_type = 'out' AND sm.deleted_at IS NULL) AS lots_label
             FROM distributions
             INNER JOIN sites ON sites.id=distributions.site_id
             INNER JOIN products ON products.id = distributions.product_id
             INNER JOIN bag_formats ON bag_formats.id = distributions.bag_format_id
             LEFT JOIN users ON users.id = distributions.created_by
             LEFT JOIN users validators ON validators.id = distributions.validated_by
             LEFT JOIN documents official_document ON official_document.entity_type='distributions' AND official_document.entity_id=distributions.id AND official_document.document_type_id=(SELECT id FROM document_types WHERE code='BS' LIMIT 1) AND official_document.deleted_at IS NULL
             WHERE distributions.deleted_at IS NULL{$siteClause}
             ORDER BY distributions.distributed_at DESC, distributions.id DESC", $params
        )->fetchAll();
    }

    public function findDetailed($id)
    {
        $params = ['id' => $id];
        $siteClause = Auth::siteClause('distributions.site_id', $params);
        $row = $this->query(
            "SELECT distributions.*, sites.code AS site_code,
                    products.name AS product_name,
                    products.code AS product_code,
                    bag_formats.name AS format_name,
                    bag_formats.weight_kg,
                    users.name AS agent_name,
                    validators.name AS validator_name,
                    official_document.document_number AS official_document_number
             FROM distributions
             INNER JOIN sites ON sites.id=distributions.site_id
             INNER JOIN products ON products.id = distributions.product_id
             INNER JOIN bag_formats ON bag_formats.id = distributions.bag_format_id
             LEFT JOIN users ON users.id = distributions.created_by
             LEFT JOIN users validators ON validators.id = distributions.validated_by
             LEFT JOIN documents official_document ON official_document.entity_type = 'distributions'
                AND official_document.entity_id = distributions.id
                AND official_document.document_type_id = (SELECT id FROM document_types WHERE code = 'BS' LIMIT 1)
                AND official_document.deleted_at IS NULL
             WHERE distributions.id = :id
               AND distributions.deleted_at IS NULL{$siteClause}
             LIMIT 1",
            $params
        )->fetch();
        if ($row) {
            $row['lots'] = $this->lots($row['id']);
        }

        return $row;
    }

    /** Lots prélevés par une sortie (un mouvement « out » par lot). */
    public function lots($distributionId)
    {
        return $this->query(
            "SELECT sm.finished_stock_id, sm.quantity_bags, sm.quantity_kg, fs.created_at AS stock_date
             FROM stock_movements sm LEFT JOIN finished_stocks fs ON fs.id = sm.finished_stock_id
             WHERE sm.distribution_id = :id AND sm.movement_type = 'out' AND sm.deleted_at IS NULL
             ORDER BY sm.id",
            ['id' => $distributionId]
        )->fetchAll();
    }

    /**
     * Sortie de stock libre sans commande.
     * Parcours normal : produit + format d'un site (stock_key) ; les sacs sont prélevés sur les lots libres
     * les plus anciens, avec un mouvement « out » par lot. Un lot explicite (finished_stock_id) reste accepté.
     */
    public function createDistribution(array $data, array $user)
    {
        require_once dirname(__DIR__) . '/services/StockAllocationService.php';
        $bags = (int) $data['quantity_bags'];
        if ($bags <= 0) {
            throw new RuntimeException('Le nombre de sacs est obligatoire et positif.');
        }

        $this->db->beginTransaction();

        try {
            $source = self::parseStockKey($data['stock_key'] ?? '');
            if ($source) {
                Auth::requireSiteAccess($source['site_id']);
                $lots = $this->lockedFreeLots($source['site_id'], $source['product_id'], $source['bag_format_id']);
                if (!$lots) {
                    throw new RuntimeException('Aucun stock libre pour ce produit sur ce site.');
                }
            } else {
                $stock = $this->lockedStock($data['finished_stock_id'] ?? 0);
                if (!$stock) {
                    throw new RuntimeException('Stock produit fini introuvable.');
                }
                Auth::requireSiteAccess($stock['site_id']);
                $stock['free_bags'] = self::freeBags($stock['available_bags'], $stock['available_weight_kg'], $stock['weight_kg']);
                $lots = [$stock];
            }

            try {
                $allocation = StockAllocationService::allocate($lots, $bags, 'free_bags', 'sac(s) libres', 0);
            } catch (RuntimeException $exception) {
                throw new RuntimeException('Impossible de sortir plus que le stock disponible. ' . $exception->getMessage());
            }
            $first = $allocation[0]['lot'];
            $weight = (float) $first['weight_kg'];
            $totalWeight = $weight * $bags;

            $voucher = $this->uniqueVoucher($data['exit_voucher'] ?: null);

            $this->query(
                "INSERT INTO distributions (
                    site_id, finished_stock_id, product_id, bag_format_id, recipient_name,
                    transporter, exit_voucher, quantity_bags, total_weight_kg,
                    distributed_at, status, created_by, validated_by
                 ) VALUES (
                    :site_id, :finished_stock_id, :product_id, :bag_format_id, :recipient_name,
                    :transporter, :exit_voucher, :quantity_bags, :total_weight_kg,
                    :distributed_at, 'validated', :created_by, :validated_by
                 )",
                [
                    // Premier lot prélevé, pour les écrans historiques ; le détail complet est dans stock_movements.
                    'site_id' => $first['site_id'], 'finished_stock_id' => $first['id'],
                    'product_id' => $first['product_id'],
                    'bag_format_id' => $first['bag_format_id'],
                    'recipient_name' => $data['recipient_name'],
                    'transporter' => $data['transporter'] ?: null,
                    'exit_voucher' => $voucher,
                    'quantity_bags' => $bags,
                    'total_weight_kg' => $totalWeight,
                    'distributed_at' => $data['distributed_at'],
                    'created_by' => $user['id'] ?? null,
                    'validated_by' => $user['id'] ?? null,
                ]
            );

            $distributionId = $this->db->lastInsertId();
            if (!empty($data['customer_id']) && $this->hasCustomers()) {
                $this->query('UPDATE distributions SET customer_id = :customer WHERE id = :id', ['customer' => (int) $data['customer_id'], 'id' => $distributionId]);
            }

            require_once dirname(__DIR__) . '/services/DocumentService.php';
            (new DocumentService($this->db))->register('BS', $first['site_id'], 'distributions', $distributionId, 'validated', $data['distributed_at'], $user);

            $stockBefore = $this->latestProductStock($first['product_id'], $first['site_id']);
            $lotsLog = [];
            foreach ($allocation as $line) {
                $lot = $line['lot'];
                $lotBags = (int) $line['quantity'];
                $lotKg = $weight * $lotBags;
                $this->query(
                    "UPDATE finished_stocks
                     SET quantity_bags = quantity_bags - :quantity_bags_update,
                         total_weight_kg = total_weight_kg - :total_weight_kg_update,
                         status = CASE
                            WHEN quantity_bags - :quantity_bags_status <= 0 OR total_weight_kg - :total_weight_kg_status <= 0 THEN 'inactive'
                            ELSE status
                         END
                     WHERE id = :id",
                    [
                        'quantity_bags_update' => $lotBags,
                        'total_weight_kg_update' => $lotKg,
                        'quantity_bags_status' => $lotBags,
                        'total_weight_kg_status' => $lotKg,
                        'id' => $lot['id'],
                    ]
                );

                $stockAfter = max($stockBefore - $lotKg, 0);
                $this->query(
                    "INSERT INTO stock_movements (
                        site_id, product_id, finished_stock_id, distribution_id, movement_type,
                        quantity_bags, quantity_kg, stock_before_kg, stock_after_kg,
                        movement_at, status, created_by
                     ) VALUES (
                        :site_id, :product_id, :finished_stock_id, :distribution_id, 'out',
                        :quantity_bags, :quantity_kg, :stock_before_kg, :stock_after_kg,
                        :movement_at, 'validated', :created_by
                     )",
                    [
                        'site_id' => $lot['site_id'], 'product_id' => $lot['product_id'],
                        'finished_stock_id' => $lot['id'],
                        'distribution_id' => $distributionId,
                        'quantity_bags' => $lotBags,
                        'quantity_kg' => $lotKg,
                        'stock_before_kg' => $stockBefore,
                        'stock_after_kg' => $stockAfter,
                        'movement_at' => $data['distributed_at'],
                        'created_by' => $user['id'] ?? null,
                    ]
                );
                $stockMovementId = $this->db->lastInsertId();
                $this->logActivity(
                    'stock_movement',
                    'stocks-finis',
                    'stock_movements',
                    $stockMovementId,
                    'Sortie stock fini par distribution.',
                    ['finished_stock_id' => $lot['id'], 'stock_kg' => $stockBefore, 'quantity_bags' => $lot['quantity_bags']],
                    ['finished_stock_id' => $lot['id'], 'stock_kg' => $stockAfter, 'quantity_bags_out' => $lotBags, 'quantity_kg' => $lotKg],
                    $user
                );
                $stockBefore = $stockAfter;
                $lotsLog[] = ['finished_stock_id' => (int) $lot['id'], 'quantity_bags' => $lotBags];
            }

            $this->logActivity(
                'distribution',
                'distribution',
                'distributions',
                $distributionId,
                'Creation et validation distribution.',
                null,
                [
                    'exit_voucher' => $voucher,
                    'recipient_name' => $data['recipient_name'],
                    'quantity_bags' => $bags,
                    'total_weight_kg' => $totalWeight,
                    'status' => 'validated',
                    'lots' => $lotsLog,
                ],
                $user
            );

            $this->db->commit();

            return $distributionId;
        } catch (Exception $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    /** Lots libres d'un site pour un produit et un format, du plus ancien au plus récent, verrouillés. */
    private function lockedFreeLots($siteId, $productId, $formatId)
    {
        $rows = $this->query(
            "SELECT finished_stocks.*,
                    finished_stocks.quantity_bags - finished_stocks.reserved_bags AS available_bags,
                    finished_stocks.total_weight_kg - finished_stocks.reserved_weight_kg AS available_weight_kg,
                    bag_formats.weight_kg
             FROM finished_stocks
             INNER JOIN bag_formats ON bag_formats.id = finished_stocks.bag_format_id
             INNER JOIN products ON products.id = finished_stocks.product_id
             WHERE finished_stocks.site_id = :site AND finished_stocks.product_id = :product AND finished_stocks.bag_format_id = :format
               AND finished_stocks.deleted_at IS NULL
               AND finished_stocks.status IN ('active', 'validated')
               AND finished_stocks.quantity_bags - finished_stocks.reserved_bags > 0
               AND products.code IN ('FARINE-MAIS', 'ALIMENT-BETAIL')
             ORDER BY finished_stocks.created_at ASC, finished_stocks.id ASC
             FOR UPDATE",
            ['site' => $siteId, 'product' => $productId, 'format' => $formatId]
        )->fetchAll();
        foreach ($rows as &$row) {
            $row['free_bags'] = self::freeBags($row['available_bags'], $row['available_weight_kg'], $row['weight_kg']);
        }
        unset($row);

        return $rows;
    }

    /** Active clients (047), to give a registered client to an exit without order. */
    public function activeCustomers()
    {
        return $this->hasCustomers() ? $this->query("SELECT id,code,name,phone FROM customers WHERE status='active' AND deleted_at IS NULL ORDER BY name")->fetchAll() : [];
    }

    public function activeCustomer($id)
    {
        return $this->hasCustomers() ? $this->query("SELECT id,code,name FROM customers WHERE id = :id AND status='active' AND deleted_at IS NULL", ['id' => $id])->fetch() : null;
    }

    private function hasCustomers()
    {
        return (int) $this->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='distributions' AND column_name='customer_id'")->fetchColumn() > 0;
    }

    public function nextVoucher()
    {
        return 'BS-' . date('Y') . '-' . str_pad((string) ($this->lastId() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function lockedStock($id)
    {
        return $this->query(
            "SELECT finished_stocks.*,
                    finished_stocks.quantity_bags - finished_stocks.reserved_bags AS available_bags,
                    finished_stocks.total_weight_kg - finished_stocks.reserved_weight_kg AS available_weight_kg,
                    products.name AS product_name,
                    bag_formats.name AS format_name,
                    bag_formats.weight_kg
             FROM finished_stocks
             INNER JOIN products ON products.id = finished_stocks.product_id
             INNER JOIN bag_formats ON bag_formats.id = finished_stocks.bag_format_id
             WHERE finished_stocks.id = :id
               AND finished_stocks.deleted_at IS NULL
               AND finished_stocks.status IN ('active', 'validated')
             LIMIT 1
             FOR UPDATE",
            ['id' => $id]
        )->fetch();
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

    private function uniqueVoucher($requested = null)
    {
        $base = strtoupper(trim((string) ($requested ?: $this->nextVoucher())));
        $base = preg_replace('/[^A-Z0-9-]+/', '-', $base) ?: $this->nextVoucher();
        $voucher = $base;
        $suffix = 1;

        while ($this->voucherExists($voucher)) {
            $voucher = $base . '-' . $suffix;
            $suffix++;
        }

        return $voucher;
    }

    private function voucherExists($voucher)
    {
        $row = $this->query(
            "SELECT COUNT(*) AS total FROM distributions WHERE exit_voucher = :exit_voucher",
            ['exit_voucher' => $voucher]
        )->fetch();

        return (int) $row['total'] > 0;
    }

    private function lastId()
    {
        $row = $this->query("SELECT COALESCE(MAX(id), 0) AS last_id FROM distributions")->fetch();

        return (int) $row['last_id'];
    }
}
