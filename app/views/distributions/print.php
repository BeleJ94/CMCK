<?php if (empty($pdfMode)): ?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title><?= e($title) ?> - <?= e($distribution['exit_voucher']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #162033; margin: 0; padding: 32px; }
        .sheet { max-width: 860px; margin: 0 auto; border: 1px solid #d9e1ea; padding: 30px; }
        .top { display: flex; justify-content: space-between; gap: 24px; border-bottom: 3px solid #0b1f35; padding-bottom: 18px; margin-bottom: 24px; }
        h1 { margin: 0; color: #0b1f35; font-size: 28px; }
        .meta { text-align: right; color: #667085; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #d9e1ea; padding: 12px; text-align: left; }
        th { background: #f6f8fb; color: #0b1f35; width: 34%; }
        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 60px; }
        .signature { border-top: 1px solid #0b1f35; padding-top: 10px; text-align: center; font-weight: 700; }
        .actions { max-width: 860px; margin: 0 auto 16px; text-align: right; }
        button, .actions a { display: inline-block; background: #0b1f35; color: #fff; border: 0; padding: 10px 16px; border-radius: 6px; cursor: pointer; text-decoration: none; font: inherit; }
        .actions a { background: #15803d; margin-right: 8px; }
        @media print { .actions { display: none; } body { padding: 0; } .sheet { border: 0; } }
    </style>
</head>
<body>
    <div class="actions">
        <a href="<?= e(base_url('distributions/' . $distribution['id'] . '/print?export=pdf')) ?>">Generer PDF</a>
        <button onclick="window.print()">Imprimer</button>
    </div>
<?php endif; ?>
    <main class="sheet">
        <div class="top">
            <div>
                <h1>CMCK MillTrack</h1>
                <strong>Bon de sortie produits finis</strong>
            </div>
            <div class="meta">
                <strong><?= e($distribution['exit_voucher']) ?></strong><br>
                <?= e($distribution['distributed_at']) ?>
            </div>
        </div>

        <table>
            <tbody>
                <tr><th>Client / destination</th><td><?= e($distribution['recipient_name']) ?></td></tr>
                <tr><th>Camion / transporteur</th><td><?= e($distribution['transporter'] ?: '-') ?></td></tr>
                <tr><th>Produit</th><td><?= e($distribution['product_name']) ?></td></tr>
                <tr><th>Format</th><td><?= e($distribution['format_name']) ?></td></tr>
                <tr><th>Nombre sacs</th><td><?= e(number_format((int) $distribution['quantity_bags'], 0, ',', ' ')) ?></td></tr>
                <tr><th>Quantite totale</th><td><?= e(number_format((float) $distribution['total_weight_kg'], 3, ',', ' ')) ?> kg</td></tr>
                <tr><th>Agent</th><td><?= e($distribution['agent_name'] ?: '-') ?></td></tr>
                <tr><th>Validateur</th><td><?= e($distribution['validator_name'] ?: '-') ?></td></tr>
            </tbody>
        </table>

        <div class="signatures">
            <div class="signature">Magasin</div>
            <div class="signature">Transporteur</div>
            <div class="signature">Reception</div>
        </div>
    </main>
<?php if (empty($pdfMode)): ?>
</body>
</html>
<?php endif; ?>
