<?php
$root = dirname(__DIR__);
$service = file_get_contents($root.'/app/services/CancellationService.php');
$migration = file_get_contents($root.'/database/029_add_uniform_cancellations.sql');
$routes = file_get_contents($root.'/public/index.php');

$checks = [
    'transactions atomiques' => strpos($service, 'beginTransaction()') !== false && strpos($service, 'rollBack()') !== false,
    'verrouillage pessimiste' => substr_count($service, 'FOR UPDATE') >= 10,
    'motif obligatoire' => strpos($service, 'motif d’annulation est obligatoire') !== false,
    'séparation demandeur/approbateur' => strpos($service, "requested_by']===(int)\$user['id']") !== false,
    'contre-opération unique' => strpos($migration, 'uq_cancel_reversal_entity') !== false,
    'lien demande/contre-opération' => strpos($migration, 'request_id BIGINT UNSIGNED NOT NULL UNIQUE') !== false,
    'traçabilité des effets stock' => strpos($migration, 'cancellation_stock_effects') !== false,
    'historique des décisions' => strpos($migration, 'cancellation_events') !== false,
    'aucune suppression métier' => !preg_match('/DELETE\s+FROM/i', $service),
    'routes protégées' => substr_count($routes, "permission'=>['cancellations'") === 4,
];

$types = ['weighing','brs','bss','transfer','production','packaging','distribution','waste_sale','waste_transfer','pelletization','empty_packaging_receipt','fuel_receipt','butchery_receipt'];
foreach ($types as $type) {
    $checks['politique '.$type] = strpos($migration, "('{$type}'") !== false;
}

$failed = [];
foreach ($checks as $label => $ok) {
    echo ($ok ? '[OK] ' : '[ECHEC] ').$label.PHP_EOL;
    if (!$ok) { $failed[] = $label; }
}
exit($failed ? 1 : 0);
