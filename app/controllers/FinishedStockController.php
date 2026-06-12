<?php

class FinishedStockController extends Controller
{
    public function index()
    {
        $model = $this->model('FinishedStock');

        $this->view('finished_stocks.index', [
            'title' => 'Stock produits finis',
            'productStock' => $model->productStock(),
            'formatStock' => $model->formatStock(),
            'entries' => $model->entriesByPackaging(),
            'outputs' => $model->outputsByDistribution(),
            'alerts' => $model->ruptureAlerts(),
        ], 'layouts.main');
    }

    public function movements()
    {
        $model = $this->model('FinishedStock');

        $this->view('finished_stocks.movements', [
            'title' => 'Mouvements stock produits finis',
            'movements' => $model->movements(),
            'alerts' => $model->ruptureAlerts(),
        ], 'layouts.main');
    }
}
