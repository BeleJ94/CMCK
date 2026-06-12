<?php

class SiloController extends Controller
{
    public function index()
    {
        $model = $this->model('Silo');

        $this->view('silos.index', [
            'title' => 'Silos',
            'silos' => $model->allWithStats(),
            'alerts' => $model->alerts(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'layouts.main');
    }

    public function show($id)
    {
        $model = $this->model('Silo');
        $silo = $model->findDetailed($id);

        if (!$silo) {
            flash('error', 'Silo introuvable.');
            redirect('silos');
        }

        $this->view('silos.show', [
            'title' => 'Detail silo',
            'silo' => $silo,
            'movements' => array_slice($model->movements($id), 0, 10),
            'entries' => array_slice($model->entriesByDelivery($id), 0, 10),
            'exits' => array_slice($model->exitsToMachines($id), 0, 10),
            'lowStock' => $model->isLowStock($silo),
            'almostFull' => $model->isAlmostFull($silo),
        ], 'layouts.main');
    }

    public function movements()
    {
        $model = $this->model('Silo');

        $this->view('silos.movements', [
            'title' => 'Mouvements silos',
            'movements' => $model->movements(),
            'entries' => $model->entriesByDelivery(),
            'exits' => $model->exitsToMachines(),
        ], 'layouts.main');
    }
}
