<?php

class MachineFeedController extends Controller
{
    public function index()
    {
        $this->view('machine_feeds.index', [
            'title' => 'Alimentation machines',
            'feeds' => $this->model('MachineFeed')->allDetailed(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'layouts.main');
    }

    public function create()
    {
        $model = $this->model('MachineFeed');

        $this->view('machine_feeds.create', [
            'title' => 'Nouvelle alimentation',
            'feed' => $this->old(),
            'silos' => $model->silosForSelect(),
            'machines' => $model->machinesForSelect(),
            'errors' => flash('errors') ?: [],
        ], 'layouts.main');
    }

    public function store()
    {
        $this->ensureCsrf('machine-feeds/create');
        $model = $this->model('MachineFeed');
        $data = $this->input();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('errors', $errors);
            $_SESSION['old_machine_feed'] = $data;
            redirect('machine-feeds/create');
        }

        try {
            $feedId = $model->createFeed($data, Auth::user());
            flash('success', 'Alimentation machine creee. Lot en attente production.');
            redirect('machine-feeds/' . $feedId);
        } catch (Exception $exception) {
            flash('error', $exception->getMessage());
            $_SESSION['old_machine_feed'] = $data;
            redirect('machine-feeds/create');
        }
    }

    public function show($id)
    {
        $feed = $this->model('MachineFeed')->findDetailed($id);

        if (!$feed) {
            flash('error', 'Alimentation introuvable.');
            redirect('machine-feeds');
        }

        $this->view('machine_feeds.show', [
            'title' => 'Detail alimentation',
            'feed' => $feed,
        ], 'layouts.main');
    }

    private function input()
    {
        return [
            'silo_id' => trim($_POST['silo_id'] ?? ''),
            'machine_id' => trim($_POST['machine_id'] ?? ''),
            'quantity_kg' => trim($_POST['quantity_kg'] ?? ''),
            'fed_at' => trim($_POST['fed_at'] ?? ''),
            'ended_at' => trim($_POST['ended_at'] ?? ''),
            'observation' => trim($_POST['observation'] ?? ''),
        ];
    }

    private function validate(array $data)
    {
        $errors = [];

        foreach (['silo_id' => 'silo source', 'machine_id' => 'machine principale'] as $field => $label) {
            if ($data[$field] === '' || !ctype_digit((string) $data[$field])) {
                $errors[$field] = 'Le champ ' . $label . ' est obligatoire.';
            }
        }

        if ($data['quantity_kg'] === '' || !is_numeric($data['quantity_kg']) || (float) $data['quantity_kg'] <= 0) {
            $errors['quantity_kg'] = 'La quantite envoyee doit etre superieure a zero.';
        }

        if ($data['fed_at'] === '') {
            $errors['fed_at'] = 'L heure debut est obligatoire.';
        }

        if ($data['ended_at'] !== '' && $data['fed_at'] !== '' && strtotime($data['ended_at']) < strtotime($data['fed_at'])) {
            $errors['ended_at'] = 'L heure fin ne peut pas etre anterieure a l heure debut.';
        }

        return $errors;
    }

    private function old()
    {
        Auth::start();
        $old = $_SESSION['old_machine_feed'] ?? null;
        unset($_SESSION['old_machine_feed']);

        return $old ?: [
            'silo_id' => '',
            'machine_id' => '',
            'quantity_kg' => '',
            'fed_at' => date('Y-m-d\TH:i'),
            'ended_at' => '',
            'observation' => '',
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
