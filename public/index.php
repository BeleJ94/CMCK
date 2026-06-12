<?php

require_once dirname(__DIR__) . '/app/helpers/functions.php';
require_once dirname(__DIR__) . '/app/helpers/icons.php';

$autoloadFile = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadFile)) {
    require_once $autoloadFile;
}

date_default_timezone_set(config('app.timezone', 'UTC'));

if (config('app.debug', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Model.php';
require_once dirname(__DIR__) . '/app/core/Controller.php';
require_once dirname(__DIR__) . '/app/core/Auth.php';
require_once dirname(__DIR__) . '/app/core/Router.php';
require_once dirname(__DIR__) . '/app/services/PdfService.php';

$router = new Router();

$router->get('/', 'Dashboard@index', ['auth' => true]);
$router->get('/login', 'Auth@showLogin');
$router->post('/login', 'Auth@login');
$router->post('/logout', 'Auth@logout', ['auth' => true]);

$router->get('/dashboard', 'Dashboard@index', ['auth' => true, 'roles' => ['administrateur', 'direction']]);
$router->get('/direction', 'Dashboard@direction', ['auth' => true, 'roles' => ['direction', 'administrateur']]);
$router->get('/terrain/pont-bascule', 'Dashboard@pontBasculeHome', ['auth' => true, 'roles' => ['agent-pont-bascule']]);
$router->get('/terrain/silo', 'Dashboard@siloHome', ['auth' => true, 'roles' => ['agent-silo']]);
$router->get('/terrain/production', 'Dashboard@productionHome', ['auth' => true, 'roles' => ['agent-production']]);
$router->get('/terrain/emballage', 'Dashboard@packagingHome', ['auth' => true, 'roles' => ['agent-emballage']]);
$router->get('/terrain/distribution', 'Dashboard@distributionHome', ['auth' => true, 'roles' => ['agent-distribution']]);
$router->get('/reports', 'Report@index', ['auth' => true, 'roles' => ['administrateur', 'direction']]);
$router->get('/reports/daily', 'Report@daily', ['auth' => true, 'roles' => ['administrateur', 'direction']]);
$router->get('/reports/supplier', 'Report@supplier', ['auth' => true, 'roles' => ['administrateur', 'direction']]);
$router->get('/reports/production', 'Report@production', ['auth' => true, 'roles' => ['administrateur', 'direction']]);
$router->get('/reports/waste', 'Report@waste', ['auth' => true, 'roles' => ['administrateur', 'direction']]);
$router->get('/reports/yield', 'Report@yield', ['auth' => true, 'roles' => ['administrateur', 'direction']]);
$router->get('/reports/stocks', 'Report@stocks', ['auth' => true, 'roles' => ['administrateur', 'direction']]);
$router->get('/reports/distribution', 'Report@distribution', ['auth' => true, 'roles' => ['administrateur', 'direction']]);
$router->get('/reports/packaging', 'Report@packaging', ['auth' => true, 'roles' => ['administrateur', 'direction']]);
$router->get('/suppliers', 'Supplier@index', ['auth' => true, 'roles' => ['administrateur', 'direction', 'agent-pont-bascule']]);
$router->get('/suppliers/create', 'Supplier@create', ['auth' => true, 'roles' => ['administrateur', 'direction', 'agent-pont-bascule']]);
$router->post('/suppliers', 'Supplier@store', ['auth' => true, 'roles' => ['administrateur', 'direction', 'agent-pont-bascule']]);
$router->get('/suppliers/{id}/edit', 'Supplier@edit', ['auth' => true, 'roles' => ['administrateur', 'direction', 'agent-pont-bascule']]);
$router->post('/suppliers/{id}/update', 'Supplier@update', ['auth' => true, 'roles' => ['administrateur', 'direction', 'agent-pont-bascule']]);
$router->post('/suppliers/{id}/delete', 'Supplier@destroy', ['auth' => true, 'roles' => ['administrateur', 'direction']]);
$router->get('/trucks', 'Truck@index', ['auth' => true, 'roles' => ['administrateur', 'direction', 'agent-pont-bascule']]);
$router->get('/trucks/create', 'Truck@create', ['auth' => true, 'roles' => ['administrateur', 'direction', 'agent-pont-bascule']]);
$router->post('/trucks', 'Truck@store', ['auth' => true, 'roles' => ['administrateur', 'direction', 'agent-pont-bascule']]);
$router->get('/trucks/{id}/edit', 'Truck@edit', ['auth' => true, 'roles' => ['administrateur', 'direction', 'agent-pont-bascule']]);
$router->post('/trucks/{id}/update', 'Truck@update', ['auth' => true, 'roles' => ['administrateur', 'direction', 'agent-pont-bascule']]);
$router->post('/trucks/{id}/delete', 'Truck@destroy', ['auth' => true, 'roles' => ['administrateur', 'direction']]);
$router->get('/weighings', 'Weighing@index', ['auth' => true, 'roles' => ['agent-pont-bascule', 'direction', 'administrateur']]);
$router->get('/weighings/entry', 'Weighing@entry', ['auth' => true, 'roles' => ['agent-pont-bascule', 'direction', 'administrateur']]);
$router->post('/weighings/entry', 'Weighing@storeEntry', ['auth' => true, 'roles' => ['agent-pont-bascule', 'direction', 'administrateur']]);
$router->get('/weighings/exit', 'Weighing@exitList', ['auth' => true, 'roles' => ['agent-pont-bascule', 'direction', 'administrateur']]);
$router->get('/weighings/{id}/exit', 'Weighing@exitForm', ['auth' => true, 'roles' => ['agent-pont-bascule', 'direction', 'administrateur']]);
$router->post('/weighings/{id}/exit', 'Weighing@validateExit', ['auth' => true, 'roles' => ['agent-pont-bascule', 'direction', 'administrateur']]);
$router->get('/weighings/{id}/ticket', 'Weighing@ticket', ['auth' => true, 'roles' => ['agent-pont-bascule', 'direction', 'administrateur']]);
$router->get('/silos', 'Silo@index', ['auth' => true, 'roles' => ['agent-silo', 'direction', 'administrateur']]);
$router->get('/silos/movements', 'Silo@movements', ['auth' => true, 'roles' => ['agent-silo', 'direction', 'administrateur']]);
$router->get('/silos/{id}', 'Silo@show', ['auth' => true, 'roles' => ['agent-silo', 'direction', 'administrateur']]);
$router->get('/machines', 'Machine@index', ['auth' => true, 'roles' => ['agent-production', 'direction', 'administrateur']]);
$router->get('/machines/create', 'Machine@create', ['auth' => true, 'roles' => ['agent-production', 'direction', 'administrateur']]);
$router->post('/machines', 'Machine@store', ['auth' => true, 'roles' => ['agent-production', 'direction', 'administrateur']]);
$router->get('/machines/{id}/edit', 'Machine@edit', ['auth' => true, 'roles' => ['agent-production', 'direction', 'administrateur']]);
$router->post('/machines/{id}/update', 'Machine@update', ['auth' => true, 'roles' => ['agent-production', 'direction', 'administrateur']]);
$router->post('/machines/{id}/toggle', 'Machine@toggle', ['auth' => true, 'roles' => ['direction', 'administrateur']]);
$router->get('/machine-feeds', 'MachineFeed@index', ['auth' => true, 'roles' => ['agent-silo', 'agent-production', 'direction', 'administrateur']]);
$router->get('/machine-feeds/create', 'MachineFeed@create', ['auth' => true, 'roles' => ['agent-silo', 'agent-production', 'direction', 'administrateur']]);
$router->post('/machine-feeds', 'MachineFeed@store', ['auth' => true, 'roles' => ['agent-silo', 'agent-production', 'direction', 'administrateur']]);
$router->get('/machine-feeds/{id}', 'MachineFeed@show', ['auth' => true, 'roles' => ['agent-silo', 'agent-production', 'direction', 'administrateur']]);
$router->get('/production', 'Production@index', ['auth' => true, 'roles' => ['agent-production', 'direction', 'administrateur']]);
$router->get('/production/create', 'Production@create', ['auth' => true, 'roles' => ['agent-production', 'direction', 'administrateur']]);
$router->post('/production', 'Production@store', ['auth' => true, 'roles' => ['agent-production', 'direction', 'administrateur']]);
$router->get('/production/{id}', 'Production@show', ['auth' => true, 'roles' => ['agent-production', 'direction', 'administrateur']]);
$router->get('/waste', 'Waste@index', ['auth' => true, 'roles' => ['agent-production', 'direction', 'administrateur']]);
$router->get('/waste/process', 'Waste@process', ['auth' => true, 'roles' => ['agent-production', 'direction', 'administrateur']]);
$router->post('/waste/process', 'Waste@store', ['auth' => true, 'roles' => ['agent-production', 'direction', 'administrateur']]);
$router->get('/waste/history', 'Waste@history', ['auth' => true, 'roles' => ['agent-production', 'direction', 'administrateur']]);
$router->get('/packaging', 'Packaging@index', ['auth' => true, 'roles' => ['agent-emballage', 'direction', 'administrateur']]);
$router->get('/packaging/create', 'Packaging@create', ['auth' => true, 'roles' => ['agent-emballage', 'direction', 'administrateur']]);
$router->post('/packaging', 'Packaging@store', ['auth' => true, 'roles' => ['agent-emballage', 'direction', 'administrateur']]);
$router->get('/packaging/history', 'Packaging@history', ['auth' => true, 'roles' => ['agent-emballage', 'direction', 'administrateur']]);
$router->get('/finished-stocks', 'FinishedStock@index', ['auth' => true, 'roles' => ['agent-emballage', 'agent-distribution', 'direction', 'administrateur']]);
$router->get('/finished-stocks/movements', 'FinishedStock@movements', ['auth' => true, 'roles' => ['agent-emballage', 'agent-distribution', 'direction', 'administrateur']]);
$router->get('/distributions', 'Distribution@index', ['auth' => true, 'roles' => ['agent-distribution', 'direction', 'administrateur']]);
$router->get('/distributions/create', 'Distribution@create', ['auth' => true, 'roles' => ['agent-distribution', 'direction', 'administrateur']]);
$router->post('/distributions', 'Distribution@store', ['auth' => true, 'roles' => ['agent-distribution', 'direction', 'administrateur']]);
$router->get('/distributions/{id}', 'Distribution@show', ['auth' => true, 'roles' => ['agent-distribution', 'direction', 'administrateur']]);
$router->get('/distributions/{id}/print', 'Distribution@print', ['auth' => true, 'roles' => ['agent-distribution', 'direction', 'administrateur']]);
$router->get('/alerts', 'Alert@index', ['auth' => true, 'roles' => ['direction', 'administrateur']]);
$router->post('/alerts/mark-all-read', 'Alert@markAllRead', ['auth' => true, 'roles' => ['direction', 'administrateur']]);
$router->post('/alerts/{id}/read', 'Alert@markRead', ['auth' => true, 'roles' => ['direction', 'administrateur']]);
$router->get('/activity-logs', 'ActivityLog@index', ['auth' => true, 'roles' => ['direction', 'administrateur']]);
$router->get('/users', 'Dashboard@placeholder', ['auth' => true, 'roles' => ['administrateur']]);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
