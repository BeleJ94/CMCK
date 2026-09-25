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

    public function operations()
    {
        try{$rows=$this->model('Cancellation')->searchableOperations(trim((string)($_GET['type']??'')),trim((string)($_GET['q']??'')));$this->json(['operations'=>$rows]);}
        catch(Exception $e){$this->json(['message'=>'Impossible de charger les opérations. Réessayez.'],422);}
    }

    public function store()
    {
        try {
            $this->csrf();
            (new CancellationService())->request(trim($_POST['entity_type'] ?? ''), (int)($_POST['entity_id'] ?? 0), trim($_POST['reason'] ?? ''), Auth::user());
            $this->respond(true, 'Demande traitée. Consultez son statut dans le registre.');
        } catch (Exception $e) { $this->respond(false, $e->getMessage()); }
        redirect('cancellations');
    }

    public function approve($id) { $this->decide($id, true); }
    public function reject($id) { $this->decide($id, false); }

    private function decide($id, $approve)
    {
        try {
            $this->csrf();
            $service = new CancellationService();
            if ($approve) { $service->approve((int)$id, Auth::user()); }
            else { $service->reject((int)$id, trim($_POST['reason'] ?? ''), Auth::user()); }
            $this->respond(true, $approve ? 'Contre-opération exécutée.' : 'Demande rejetée.');
        } catch (Exception $e) { $this->respond(false, $e->getMessage()); }
        redirect('cancellations');
    }

    private function respond($ok,$message)
    {
        if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest'){
            http_response_code($ok?200:422);header('Content-Type: application/json');
            if($ok)flash('success',$message);
            echo json_encode(['success'=>$ok,'message'=>$message,'redirect'=>base_url('cancellations')]);exit;
        }
        flash($ok?'success':'error',$message);
    }

    private function csrf()
    {
        if (!verify_csrf($_POST['_token'] ?? '')) { throw new RuntimeException('Jeton CSRF invalide.'); }
    }
}
