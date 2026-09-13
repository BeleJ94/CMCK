<?php
require_once dirname(__DIR__) . '/app/services/SiloDashboardService.php';
function dashboard_silo($id, $stock, $capacity, $status = 'active', $threshold = 0) {
    return ['id' => $id, 'name' => 'Silo ' . $id, 'code' => 'S-' . $id, 'site_name' => 'Usine', 'product_name' => 'Maïs', 'product_id' => 1, 'current_stock_kg' => $stock, 'capacity_kg' => $capacity, 'status' => $status, 'alert_threshold_kg' => $threshold];
}
$checks = 0;
function dashboard_check($ok, $label) { global $checks; if (!$ok) { throw new RuntimeException($label); } $checks++; echo 'OK - ' . $label . PHP_EOL; }
$rows = [dashboard_silo(1, 1200, 1000), dashboard_silo(2, 100, 9000, 'validated', 200), dashboard_silo(3, 500, 1000, 'inactive'), dashboard_silo(4, 950, 1000), dashboard_silo(5, 400, 1000)];
$d = SiloDashboardService::build($rows);
dashboard_check($d['summary']['stock'] === 2650.0 && $d['summary']['capacity'] === 12000.0, 'stock et capacité des seuls silos actifs');
dashboard_check(abs($d['summary']['occupation'] - 2650 / 12000 * 100) < 0.00001, 'occupation pondérée par la capacité');
dashboard_check($d['summary']['available'] === 9550.0, 'capacité disponible sans compensation par un dépassement ailleurs');
dashboard_check($d['summary']['inactive'] === 1 && $d['summary']['inactive_stock'] === 500.0, 'stock hors service distingué');
dashboard_check($d['summary']['alerts'] === 3, 'alertes limitées aux silos actifs');
dashboard_check(array_column($d['alerts'], 'id') === [1, 2, 4], 'dépassement puis stock bas puis presque plein');
dashboard_check($d['silos'][0]['occupation'] === 120.0, 'dépassement réel conservé au-delà de 100 %');
$f = SiloDashboardService::build($rows, ['status' => 'inactive', 'unit' => 't']);
dashboard_check(count($f['silos']) === 1 && $f['silos'][0]['id'] === 3 && $f['summary'] === $d['summary'], 'filtre indépendant de la synthèse du périmètre');
dashboard_check($f['filters']['unit'] === 't', 'choix de tonnes conservé');
dashboard_check(count(SiloDashboardService::build($rows, ['q' => 's-2'])['silos']) === 1, 'recherche par code sans distinction de casse');
dashboard_check(count(SiloDashboardService::build($rows, ['q' => 'maïs', 'alerts' => '1'])['silos']) === 3, 'recherche combinée aux alertes');
dashboard_check(count(SiloDashboardService::build($rows, ['product' => '999'])['silos']) === 0, 'filtre produit sans résultat');
$unset = dashboard_silo(6, 0, 100); $unset['product_id'] = null; $unset['product_name'] = null;
dashboard_check(count(SiloDashboardService::build([$unset], ['product' => 'none'])['silos']) === 1, 'silo sans produit filtrable');
$empty = SiloDashboardService::build([]);
dashboard_check($empty['summary']['occupation'] === null && !$empty['silos'], 'périmètre vide sans division par zéro');
$unknown = SiloDashboardService::build([dashboard_silo(7, 100, 0)]);
dashboard_check($unknown['summary']['occupation'] === null && $unknown['summary']['unknown_capacity'] === 1 && $unknown['summary']['alerts'] === 1, 'capacité inconnue signalée, aucun pourcentage trompeur');
$invalid = SiloDashboardService::build($rows, ['q' => [], 'unit' => ['t'], 'alerts' => [], 'status' => '<script>']);
dashboard_check($invalid['filters']['q'] === '' && $invalid['filters']['unit'] === 'kg' && $invalid['filters']['status'] === '', 'paramètres invalides normalisés');
echo $checks . " vérifications réussies.\n";
