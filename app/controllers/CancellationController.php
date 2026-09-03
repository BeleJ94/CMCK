<?php
require_once dirname(__DIR__).'/services/CancellationService.php';

class CancellationController extends Controller
{
    public function index()
    {
        $data = $this->model('Cancellation')->dashboardData();
        $data['title'] = 'Annulations et corrections';
        $data['success'] = flash('success');
        $data['error'] = flash('error');
        $this->view('cancellations.index', $data, 'layouts.main');
    }

    public function store()
    {
        $this->csrf();
        try {
            (new CancellationService())->request(trim($_POST['entity_type'] ?? ''), (int)($_POST['entity_id'] ?? 0), trim($_POST['reason'] ?? ''), Auth::user());
            flash('success', 'Demande enregistrée. Les effets validés attendent une seconde approbation.');
        } catch (Exception $e) { flash('error', $e->getMessage()); }
        redirect('cancellations');
    }

    public function approve($id) { $this->decide($id, true); }
    public function reject($id) { $this->decide($id, false); }

    private function decide($id, $approve)
    {
        $this->csrf();
        try {
            $service = new CancellationService();
            if ($approve) { $service->approve((int)$id, Auth::user()); }
            else { $service->reject((int)$id, trim($_POST['reason'] ?? ''), Auth::user()); }
            flash('success', $approve ? 'Contre-opération exécutée.' : 'Demande rejetée.');
        } catch (Exception $e) { flash('error', $e->getMessage()); }
        redirect('cancellations');
    }

    private function csrf()
    {
        if (!verify_csrf($_POST['_token'] ?? '')) { throw new RuntimeException('Jeton CSRF invalide.'); }
    }
}
