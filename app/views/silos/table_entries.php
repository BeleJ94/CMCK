<div class="table-responsive">
    <table class="enterprise-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Silo</th>
                <th>Reference</th>
                <th>Fournisseur</th>
                <th>Camion</th>
                <th>Quantite</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['movement_at']) ?></td>
                    <td><?= e($row['silo_name']) ?></td>
                    <td><strong><?= e($row['weighing_reference']) ?></strong></td>
                    <td><?= e($row['supplier_name']) ?></td>
                    <td><?= e($row['plate_number']) ?></td>
                    <td><?= e(number_format((float) $row['quantity_kg'], 0, ',', ' ')) ?> kg</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
