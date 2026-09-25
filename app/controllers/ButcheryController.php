<?php

class ButcheryController extends Controller
{
    private const SECTIONS = ['receipts', 'slaughters', 'production', 'stocks', 'outgoing', 'recipes'];

    public function index($section = 'receipts')
    {
        if (!in_array($section, self::SECTIONS, true)) {
            http_response_code(404);
            echo 'Page introuvable';
            return;
        }
        if ($section === 'recipes') {
            Auth::requirePermission('butchery', 'administer');
        }
        $model = $this->model('Butchery');
        $this->view('butchery.index', array_merge([
            'section' => $section,
            'title' => 'Boucherie',
            'success' => flash('success'),
            'error' => flash('error'),
            'siteRequired' => !$model->hasOperationalSiteContext(),
            'receipts' => $model->pendingReceipts(),
            'slaughters' => $model->pendingSlaughters(),
            'history' => $section === 'receipts' ? $model->receiptHistory() : ($section === 'slaughters' ? $model->slaughterHistory() : []),
            'productionLines' => $section === 'production' ? $model->productionLines() : ['inputs'=>[],'outputs'=>[]],
            'recipeDirectory' => $section === 'recipes' ? $model->recipeDirectory() : ['versions'=>[],'components'=>[]],
            'sales' => $section === 'outgoing' ? $model->recentSales() : [],
        ], $model->dashboard()), 'layouts.main');
    }

    public function receipt() { $this->go(function ($m, $u) { $m->createReceipt($_POST, $u); }, 'Réception enregistrée. Vous pouvez maintenant effectuer le contrôle sanitaire.'); }
    public function control($id) { $this->go(function ($m, $u) use ($id) { $m->controlReceipt($id, $_POST, $u); }, 'Contrôle sanitaire enregistré.'); }
    public function slaughter() { $this->go(function ($m, $u) { $m->createSlaughter($_POST, $u); }, 'Abattage enregistré, en attente de validation.'); }
    public function validateSlaughter($id) { $this->go(function ($m, $u) use ($id) { $m->validateSlaughter($id, $_POST, $u); }, 'Abattage validé. Le stock de viande est disponible.'); }
    public function recipe() { $this->go(function ($m, $u) { $m->createRecipe($_POST, $u); }, 'Nouvelle version de recette enregistrée.'); }
    public function order() { $this->go(function ($m, $u) { $m->createOrder($_POST, $u); }, 'Fabrication planifiée. Vous pouvez saisir ses résultats.'); }
    public function results($id) { $this->go(function ($m, $u) use ($id) { $m->submitOrder($id, $_POST, $u); }, 'Résultats enregistrés, en attente de validation.'); }
    public function validateOrder($id) { $this->go(function ($m, $u) use ($id) { $m->validateOrder($id, $u); }, 'Fabrication validée. Les produits finis sont disponibles.'); }
    public function sale() { $this->go(function ($m, $u) { $m->sell($_POST, $u); }, 'Vente enregistrée et stock mis à jour.'); }
    public function transfer() { $this->go(function ($m, $u) { $m->createTransfer($_POST, $u); }, 'Transfert créé, en attente d’approbation.'); }
    public function approveTransfer($id) { $this->go(function ($m, $u) use ($id) { $m->approveTransfer($id, $u); }, 'Transfert approuvé. Vous pouvez l’expédier.'); }
    public function dispatchTransfer($id) { $this->go(function ($m, $u) use ($id) { $m->dispatchTransfer($id, $u); }, 'Transfert expédié vers DECO.'); }

    private function go($operation, $message)
    {
        $section = $_POST['_section'] ?? 'receipts';
        if (!in_array($section, self::SECTIONS, true)) {
            $section = 'receipts';
        }
        $returnPath = 'butchery/' . $section;
        $ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
        if (!verify_csrf($_POST['_token'] ?? '')) {
            if ($ajax) {
                return $this->json(['ok' => false, 'message' => 'Session expirée. Rechargez la page puis réessayez.'], 419);
            }
            flash('error', 'Session expirée.');
            redirect($returnPath);
        }
        try {
            $operation($this->model('Butchery'), Auth::user());
            if ($ajax) {
                return $this->json(['ok' => true, 'message' => $message, 'refresh_url' => base_url($returnPath)]);
            }
            flash('success', $message);
        } catch (Exception $exception) {
            $message = $exception instanceof PDOException
                ? 'Enregistrement impossible. Vérifiez les références et les champs saisis.'
                : $exception->getMessage();
            if ($ajax) {
                return $this->json(['ok' => false, 'message' => $message], 422);
            }
            flash('error', $message);
        }
        redirect($returnPath);
    }
}
