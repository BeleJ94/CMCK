<div class="table-responsive">
    <table class="enterprise-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Silo</th>
                <th>Machine</th>
                <th>Produit</th>
                <th>Quantite</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['fed_at']) ?></td>
                    <td><?= e($row['silo_name']) ?></td>
                    <td><?= e($row['machine_name']) ?></td>
                    <td><?= e($row['product_name']) ?></td>
                    <td><?= e(number_format((float) $row['quantity_kg'], 0, ',', ' ')) ?> kg</td>
                    <td><span class="status-badge status-<?= e($row['status']) ?>"><?= e($row['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
