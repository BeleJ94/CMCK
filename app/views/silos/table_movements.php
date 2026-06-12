<div class="table-responsive">
    <table class="enterprise-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Silo</th>
                <th>Type</th>
                <th>Produit</th>
                <th>Quantite</th>
                <th>Stock avant</th>
                <th>Stock apres</th>
                <th>Reference</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['movement_at']) ?></td>
                    <td><?= e($row['silo_name']) ?></td>
                    <td><span class="status-badge status-<?= $row['movement_type'] === 'in' ? 'validated' : 'pending' ?>"><?= e($row['movement_type']) ?></span></td>
                    <td><?= e($row['product_name']) ?></td>
                    <td><?= e(number_format((float) $row['quantity_kg'], 0, ',', ' ')) ?> kg</td>
                    <td><?= e(number_format((float) $row['stock_before_kg'], 0, ',', ' ')) ?> kg</td>
                    <td><?= e(number_format((float) $row['stock_after_kg'], 0, ',', ' ')) ?> kg</td>
                    <td><?= e($row['weighing_reference'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
