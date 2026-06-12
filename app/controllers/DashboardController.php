<?php

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user && !in_array($user['role_slug'], ['administrateur', 'direction'], true)) {
            redirect(Auth::homePathFor($user));
        }

        $this->direction();
    }

    public function pontBasculeHome()
    {
        $this->fieldHome('agent-pont-bascule');
    }

    public function siloHome()
    {
        $this->fieldHome('agent-silo');
    }

    public function productionHome()
    {
        $this->fieldHome('agent-production');
    }

    public function packagingHome()
    {
        $this->fieldHome('agent-emballage');
    }

    public function distributionHome()
    {
        $this->fieldHome('agent-distribution');
    }

    public function direction()
    {
        try {
            $this->model('Alert')->generateSystemAlerts();
            $dashboard = $this->model('DashboardModel')->directionData();
        } catch (Exception $exception) {
            $dashboard = $this->emptyDashboard();
        }

        $today = $dashboard['today'];
        $stock = $dashboard['stockSnapshot'];
        $alerts = $dashboard['alertStats'];
        $cards = [
            ['label' => 'Mais recu aujourd hui', 'value' => $this->kg($today['maize_received']), 'icon' => 'bi-truck', 'tone' => 'green', 'trend' => $dashboard['trends']['maize_received']],
            ['label' => 'Stock total silos', 'value' => $this->kg($today['silo_stock']), 'icon' => 'bi-database-check', 'tone' => 'orange', 'hint' => 'Matiere premiere disponible'],
            ['label' => 'Stock produits finis', 'value' => $this->kg($stock['finished_stock']), 'icon' => 'bi-boxes', 'tone' => 'blue', 'hint' => number_format((int) $stock['finished_bags'], 0, ',', ' ') . ' sacs disponibles'],
            ['label' => 'Production du jour', 'value' => $this->kg($today['flour_produced']), 'icon' => 'bi-box-seam', 'tone' => 'green', 'trend' => $dashboard['trends']['flour_produced']],
            ['label' => 'Rendement moyen du jour', 'value' => $this->percent($today['average_yield']), 'icon' => 'bi-activity', 'tone' => 'green', 'trend' => $dashboard['trends']['average_yield']],
            ['label' => 'Dechets generes', 'value' => $this->kg($today['waste_generated']), 'icon' => 'bi-recycle', 'tone' => 'red', 'hint' => 'Stock dechets ' . $this->kg($stock['waste_stock'])],
            ['label' => 'Distribution du jour', 'value' => $this->kg($today['distributed_products']), 'icon' => 'bi-send-check', 'tone' => 'blue', 'trend' => $dashboard['trends']['distributed_products']],
            ['label' => 'Alertes ouvertes', 'value' => (int) $alerts['total'], 'icon' => 'bi-exclamation-triangle', 'tone' => (int) $alerts['danger'] > 0 ? 'red' : ((int) $alerts['warning'] > 0 ? 'orange' : 'green'), 'hint' => (int) $alerts['danger'] . ' danger / ' . (int) $alerts['warning'] . ' warning'],
        ];

        $this->view('dashboard.index', [
            'title' => 'Dashboard Direction',
            'subtitle' => 'Vue executive des receptions, silos, production, rendement et distributions.',
            'cards' => $cards,
            'decisionSummary' => $this->decisionSummary($dashboard),
            'charts' => $this->charts($dashboard),
            'alerts' => $dashboard['alerts'],
        ], 'layouts.main');
    }

    public function weighings()
    {
        $this->page('Pont-bascule', 'Enregistrement et validation des pesees fournisseurs.', [
            ['label' => 'Pesees du jour', 'value' => '5', 'icon' => 'bi-truck', 'tone' => 'blue'],
            ['label' => 'Poids net', 'value' => '141 250 kg', 'icon' => 'bi-bar-chart', 'tone' => 'green'],
            ['label' => 'En attente', 'value' => '1', 'icon' => 'bi-clock', 'tone' => 'orange'],
        ]);
    }

    public function silos()
    {
        $this->page('Gestion des silos', 'Controle des mouvements et niveaux de stock matiere premiere.', [
            ['label' => 'Silos actifs', 'value' => '3', 'icon' => 'bi-database', 'tone' => 'blue'],
            ['label' => 'Capacite totale', 'value' => '300 000 kg', 'icon' => 'bi-graph-up-arrow', 'tone' => 'green'],
            ['label' => 'Stock courant', 'value' => '117 300 kg', 'icon' => 'bi-boxes', 'tone' => 'orange'],
        ]);
    }

    public function production()
    {
        $this->page('Production', 'Suivi des alimentations machines et lots produits.', [
            ['label' => 'Machines actives', 'value' => '3', 'icon' => 'bi-gear-wide-connected', 'tone' => 'blue'],
            ['label' => 'Lots termines', 'value' => '3', 'icon' => 'bi-check2-circle', 'tone' => 'green'],
            ['label' => 'Dechets traites', 'value' => '1 520 kg', 'icon' => 'bi-recycle', 'tone' => 'orange'],
        ]);
    }

    public function packaging()
    {
        $this->page('Emballage', 'Conditionnement des produits finis par formats de sacs.', [
            ['label' => 'Sacs produits', 'value' => '650', 'icon' => 'bi-box-seam', 'tone' => 'orange'],
            ['label' => 'Formats actifs', 'value' => '4', 'icon' => 'bi-check2-circle', 'tone' => 'green'],
            ['label' => 'Poids emballe', 'value' => '21 000 kg', 'icon' => 'bi-bar-chart', 'tone' => 'blue'],
        ]);
    }

    public function distributions()
    {
        $this->page('Distribution', 'Sorties de stock fini et livraisons clients.', [
            ['label' => 'Sorties validees', 'value' => '3', 'icon' => 'bi-check2-circle', 'tone' => 'green'],
            ['label' => 'Sacs livres', 'value' => '66', 'icon' => 'bi-box-seam', 'tone' => 'orange'],
            ['label' => 'Poids livre', 'value' => '2 650 kg', 'icon' => 'bi-send-check', 'tone' => 'blue'],
        ]);
    }

    public function placeholder()
    {
        $this->page('Module', 'Ce module est pret a recevoir ses ecrans metier.', [
            ['label' => 'Statut', 'value' => 'Actif', 'icon' => 'bi-check2-circle', 'tone' => 'green'],
        ]);
    }

    private function page($title, $subtitle, array $cards)
    {
        $this->view('dashboard.index', [
            'title' => $title,
            'subtitle' => $subtitle,
            'cards' => $cards,
        ], 'layouts.main');
    }

    private function fieldHome($role)
    {
        $homes = $this->fieldHomeDefinitions();

        if (!isset($homes[$role])) {
            redirect('dashboard');
        }

        $home = $homes[$role];

        $this->view('dashboard.field_home', [
            'title' => $home['title'],
            'subtitle' => $home['subtitle'],
            'icon' => $home['icon'],
            'actions' => $home['actions'],
        ], 'layouts.main');
    }

    private function fieldHomeDefinitions()
    {
        return [
            'agent-pont-bascule' => [
                'title' => 'Accueil pont-bascule',
                'subtitle' => 'Choisissez l action a faire maintenant.',
                'icon' => 'bi-truck',
                'actions' => [
                    ['label' => 'Nouvelle pesee entree', 'hint' => 'Enregistrer un camion qui arrive.', 'path' => 'weighings/entry', 'icon' => 'bi-box-arrow-in-down', 'tone' => 'green'],
                    ['label' => 'Valider pesee sortie', 'hint' => 'Finaliser le poids net apres dechargement.', 'path' => 'weighings/exit', 'icon' => 'bi-box-arrow-up-right', 'tone' => 'orange'],
                    ['label' => 'Tickets du jour', 'hint' => 'Voir les pesees et imprimer un ticket.', 'path' => 'weighings', 'icon' => 'bi-receipt', 'tone' => 'blue'],
                ],
            ],
            'agent-silo' => [
                'title' => 'Accueil silo',
                'subtitle' => 'Stock, alimentation machine et mouvements.',
                'icon' => 'bi-database',
                'actions' => [
                    ['label' => 'Voir stock silos', 'hint' => 'Controler les niveaux disponibles.', 'path' => 'silos', 'icon' => 'bi-database-check', 'tone' => 'green'],
                    ['label' => 'Alimenter machine', 'hint' => 'Envoyer du mais vers une machine.', 'path' => 'machine-feeds/create', 'icon' => 'bi-arrow-down-up', 'tone' => 'orange'],
                    ['label' => 'Historique mouvements', 'hint' => 'Voir entrees et sorties silo.', 'path' => 'silos/movements', 'icon' => 'bi-clock-history', 'tone' => 'blue'],
                ],
            ],
            'agent-production' => [
                'title' => 'Accueil production',
                'subtitle' => 'Encoder les resultats de production et dechets.',
                'icon' => 'bi-gear-wide-connected',
                'actions' => [
                    ['label' => 'Encoder production', 'hint' => 'Valider un lot apres traitement.', 'path' => 'production/create', 'icon' => 'bi-check2-circle', 'tone' => 'green'],
                    ['label' => 'Encoder dechets', 'hint' => 'Transformer les dechets en aliment betail.', 'path' => 'waste/process', 'icon' => 'bi-recycle', 'tone' => 'orange'],
                    ['label' => 'Rendement du jour', 'hint' => 'Voir les lots et les rendements.', 'path' => 'production', 'icon' => 'bi-speedometer2', 'tone' => 'blue'],
                ],
            ],
            'agent-emballage' => [
                'title' => 'Accueil emballage',
                'subtitle' => 'Conditionnement et stock disponible.',
                'icon' => 'bi-box-seam',
                'actions' => [
                    ['label' => 'Nouvel emballage', 'hint' => 'Declarer des sacs produits.', 'path' => 'packaging/create', 'icon' => 'bi-plus-circle', 'tone' => 'green'],
                    ['label' => 'Stock disponible', 'hint' => 'Voir le stock fini par produit.', 'path' => 'finished-stocks', 'icon' => 'bi-boxes', 'tone' => 'blue'],
                    ['label' => 'Emballages du jour', 'hint' => 'Consulter les emballages recents.', 'path' => 'packaging/history', 'icon' => 'bi-clock-history', 'tone' => 'orange'],
                ],
            ],
            'agent-distribution' => [
                'title' => 'Accueil distribution',
                'subtitle' => 'Sorties de stock et bons de livraison.',
                'icon' => 'bi-send-check',
                'actions' => [
                    ['label' => 'Nouvelle sortie', 'hint' => 'Creer un bon de sortie stock.', 'path' => 'distributions/create', 'icon' => 'bi-plus-circle', 'tone' => 'green'],
                    ['label' => 'Stock disponible', 'hint' => 'Voir les produits prets a sortir.', 'path' => 'finished-stocks', 'icon' => 'bi-boxes', 'tone' => 'blue'],
                    ['label' => 'Bons de sortie du jour', 'hint' => 'Consulter ou imprimer les bons.', 'path' => 'distributions', 'icon' => 'bi-receipt', 'tone' => 'orange'],
                ],
            ],
        ];
    }

    private function charts(array $dashboard)
    {
        return [
            'productionSevenDays' => [
                'labels' => array_column($dashboard['productionSevenDays'], 'day'),
                'values' => array_map('floatval', array_column($dashboard['productionSevenDays'], 'total')),
            ],
            'distributionSevenDays' => [
                'labels' => array_column($dashboard['distributionSevenDays'], 'day'),
                'values' => array_map('floatval', array_column($dashboard['distributionSevenDays'], 'total')),
            ],
            'yieldByMachine' => [
                'labels' => array_column($dashboard['yieldByMachine'], 'name'),
                'values' => array_map('floatval', array_column($dashboard['yieldByMachine'], 'yield_rate')),
            ],
            'receptionBySupplier' => [
                'labels' => array_column($dashboard['receptionBySupplier'], 'name'),
                'values' => array_map('floatval', array_column($dashboard['receptionBySupplier'], 'total')),
            ],
        ];
    }

    private function decisionSummary(array $dashboard)
    {
        $today = $dashboard['today'];
        $stock = $dashboard['stockSnapshot'];
        $alerts = $dashboard['alertStats'];
        $balance = (float) $today['flour_produced'] - (float) $today['distributed_products'];

        return [
            ['label' => 'Flux net du jour', 'value' => $this->kg($balance), 'tone' => $balance >= 0 ? 'green' : 'red', 'text' => $balance >= 0 ? 'La production couvre la distribution du jour.' : 'La distribution depasse la production du jour.'],
            ['label' => 'Couverture stock fini', 'value' => $this->kg($stock['finished_stock']), 'tone' => (float) $stock['finished_stock'] > 500 ? 'green' : 'orange', 'text' => 'Stock disponible pour les prochaines sorties.'],
            ['label' => 'Priorite direction', 'value' => (int) $alerts['danger'] . ' critiques', 'tone' => (int) $alerts['danger'] > 0 ? 'red' : 'green', 'text' => (int) $alerts['danger'] > 0 ? 'Action immediate recommandee.' : 'Aucune alerte danger non lue.'],
        ];
    }

    private function kg($value)
    {
        return number_format((float) $value, 0, ',', ' ') . ' kg';
    }

    private function percent($value)
    {
        return number_format((float) $value, 1, ',', ' ') . '%';
    }

    private function emptyDashboard()
    {
        return [
            'today' => [
                'maize_received' => 0,
                'trucks_received' => 0,
                'silo_stock' => 0,
                'treated_quantity' => 0,
                'flour_produced' => 0,
                'waste_generated' => 0,
                'animal_feed_produced' => 0,
                'distributed_products' => 0,
                'average_yield' => 0,
            ],
            'productionSevenDays' => [],
            'yieldByMachine' => [],
            'receptionBySupplier' => [],
            'distributionSevenDays' => [],
            'stockSnapshot' => [
                'finished_stock' => 0,
                'finished_bags' => 0,
                'waste_stock' => 0,
            ],
            'alertStats' => [
                'danger' => 0,
                'warning' => 0,
                'info' => 0,
                'total' => 0,
            ],
            'trends' => [
                'maize_received' => ['label' => 'Stable', 'value' => 0, 'tone' => 'blue'],
                'treated_quantity' => ['label' => 'Stable', 'value' => 0, 'tone' => 'blue'],
                'flour_produced' => ['label' => 'Stable', 'value' => 0, 'tone' => 'blue'],
                'distributed_products' => ['label' => 'Stable', 'value' => 0, 'tone' => 'blue'],
                'average_yield' => ['label' => 'Stable', 'value' => 0, 'tone' => 'blue'],
            ],
            'alerts' => [],
        ];
    }
}
