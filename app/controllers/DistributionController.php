<?php

class DistributionController extends Controller
{
    public function index()
    {
        $model = $this->model('Distribution');

        $this->view('distributions.index', [
            'title' => 'Distribution',
            'availableStocks' => $model->availableStocks(),
            'distributions' => $model->history(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'layouts.main');
    }

    public function create()
    {
        $model = $this->model('Distribution');

        $this->view('distributions.create', [
            'title' => 'Nouvelle distribution',
            'availableStocks' => $model->availableStocks(),
            'distribution' => $this->old($model),
            'errors' => flash('errors') ?: [],
            'error' => flash('error'),
        ], 'layouts.main');
    }

    public function store()
    {
        $this->ensureCsrf('distributions/create');

        $model = $this->model('Distribution');
        $data = $this->input();
        $errors = $this->validate($data, $model);

        if (!empty($errors)) {
            flash('errors', $errors);
            $_SESSION['old_distribution'] = $data;
            redirect('distributions/create');
        }

        try {
            $id = $model->createDistribution($data, Auth::user());
            flash('success', 'Sortie stock creee avec succes.');
            redirect('distributions/' . $id);
        } catch (Exception $exception) {
            flash('error', $exception->getMessage());
            $_SESSION['old_distribution'] = $data;
            redirect('distributions/create');
        }
    }

    public function show($id)
    {
        $distribution = $this->model('Distribution')->findDetailed($id);

        if (!$distribution) {
            flash('error', 'Distribution introuvable.');
            redirect('distributions');
        }

        $this->view('distributions.show', [
            'title' => 'Detail distribution',
            'distribution' => $distribution,
        ], 'layouts.main');
    }

    public function print($id)
    {
        $distribution = $this->model('Distribution')->findDetailed($id);

        if (!$distribution) {
            http_response_code(404);
            echo 'Distribution introuvable.';
            return;
        }

        if (($_GET['export'] ?? '') === 'pdf') {
            $html = $this->renderViewToString('distributions.print', [
                'title' => 'Bon de sortie',
                'distribution' => $distribution,
                'pdfMode' => true,
            ]);
            (new PdfService())->stream('Bon de sortie produits finis', $html, 'bon-sortie-' . $this->slug($distribution['exit_voucher']) . '.pdf', 'portrait');
            return;
        }

        $this->view('distributions.print', [
            'title' => 'Bon de sortie',
            'distribution' => $distribution,
        ]);
    }

    private function slug($value)
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value));
        return trim($slug, '-') ?: 'document';
    }

    private function input()
    {
        return [
            'finished_stock_id' => trim($_POST['finished_stock_id'] ?? ''),
            'recipient_name' => trim($_POST['recipient_name'] ?? ''),
            'transporter' => trim($_POST['transporter'] ?? ''),
            'exit_voucher' => trim($_POST['exit_voucher'] ?? ''),
            'quantity_bags' => trim($_POST['quantity_bags'] ?? ''),
            'distributed_at' => trim($_POST['distributed_at'] ?? ''),
        ];
    }

    private function validate(array $data, Distribution $model)
    {
        $errors = [];

        if ($data['finished_stock_id'] === '' || !ctype_digit((string) $data['finished_stock_id'])) {
            $errors['finished_stock_id'] = 'Le stock produit fini est obligatoire.';
        }

        if ($data['recipient_name'] === '') {
            $errors['recipient_name'] = 'Le client ou destination est obligatoire.';
        }

        if ($data['quantity_bags'] === '' || !ctype_digit((string) $data['quantity_bags']) || (int) $data['quantity_bags'] <= 0) {
            $errors['quantity_bags'] = 'Le nombre de sacs est obligatoire et positif.';
        }

        if ($data['distributed_at'] === '') {
            $errors['distributed_at'] = 'La date de sortie est obligatoire.';
        }

        if (empty($errors['finished_stock_id']) && empty($errors['quantity_bags'])) {
            $stock = null;
            foreach ($model->availableStocks() as $item) {
                if ((string) $item['id'] === (string) $data['finished_stock_id']) {
                    $stock = $item;
                    break;
                }
            }

            if (!$stock) {
                $errors['finished_stock_id'] = 'Stock produit fini introuvable.';
            } elseif ((int) $data['quantity_bags'] > (int) $stock['quantity_bags']) {
                $errors['quantity_bags'] = 'Impossible de sortir plus que le stock disponible.';
            }
        }

        return $errors;
    }

    private function old(Distribution $model)
    {
        Auth::start();
        $old = $_SESSION['old_distribution'] ?? null;
        unset($_SESSION['old_distribution']);

        return $old ?: [
            'finished_stock_id' => '',
            'recipient_name' => '',
            'transporter' => '',
            'exit_voucher' => $model->nextVoucher(),
            'quantity_bags' => '',
            'distributed_at' => date('Y-m-d\TH:i'),
        ];
    }

    private function ensureCsrf($redirect)
    {
        if (!verify_csrf($_POST['_token'] ?? '')) {
            flash('error', 'Session expiree. Veuillez reessayer.');
            redirect($redirect);
        }
    }
}
