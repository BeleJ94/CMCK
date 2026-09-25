<?php
// Controller response contract, isolated from sessions and database writes.
require __DIR__ . '/../app/core/Controller.php';
require __DIR__ . '/../app/controllers/ButcheryController.php';
class Auth {
    public static $administer = true;
    public static function user() { return ['id' => 1]; }
    public static function requirePermission($component, $action) {
        if (!self::$administer) throw new RuntimeException('Permission refusée');
    }
}
function verify_csrf($token) { return $token === 'valid'; }
function base_url($path) { return 'https://example.test/' . $path; }
class NavigationController extends ButcheryController {
    public $calls = 0;
    public $failure = false;
    protected function model($name) { return $this; }
    public function sell($data, $user) {
        $this->calls++;
        if ($this->failure) throw new RuntimeException('Stock vendable insuffisant.');
    }
    protected function json($data, $code = 200) { echo json_encode(['code' => $code, 'data' => $data]); }
}
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$controller = new NavigationController();
foreach (['receipts', 'slaughters', 'production', 'stocks', 'outgoing', 'recipes', 'https://invalid.test', ['outgoing']] as $section) {
    $_POST = ['_token' => 'valid', '_section' => $section];
    ob_start(); $controller->sale(); $response = json_decode(ob_get_clean(), true);
    $expected = is_string($section) && strpos($section, 'https:') !== 0 ? $section : 'receipts';
    check($response['code'] === 200 && $response['data']['ok'], 'Succès JSON');
    check($response['data']['refresh_url'] === base_url('butchery/' . $expected), 'Retour dans la bonne activité sans redirection externe');
}
$_POST = ['_token' => 'invalid', '_section' => 'outgoing'];
$before = $controller->calls;
ob_start(); $controller->sale(); $response = json_decode(ob_get_clean(), true);
check($response['code'] === 419 && !$response['data']['ok'] && $controller->calls === $before, 'CSRF refusé avant écriture');
$_POST['_token'] = 'valid'; $controller->failure = true;
ob_start(); $controller->sale(); $response = json_decode(ob_get_clean(), true);
check($response['code'] === 422 && !isset($response['data']['refresh_url']), 'Erreur métier sans rafraîchissement ni perte de saisie');
Auth::$administer = false;
try { $controller->index('recipes'); throw new LogicException('Accès recettes accordé'); }
catch (RuntimeException $e) { check(!($e instanceof LogicException), 'Recettes réservées aux utilisateurs autorisés'); }
ob_start(); $controller->index('unknown'); ob_end_clean();
check(http_response_code() === 404, 'Sous-page inconnue refusée');
echo "OK : activité conservée, retour interne, CSRF, erreur métier, droits recettes et 404.\n";
