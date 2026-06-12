<?php

class ReportController extends Controller
{
    public function index()
    {
        $model = $this->model('ReportModel');
        $filters = $this->filters();
        $summary = $model->globalSummary($filters);

        $this->render('reports.index', [
            'title' => 'Rapport periodique global',
            'filters' => $filters,
            'references' => $model->referenceData(),
            'summary' => $summary,
            'executiveSummary' => $model->executiveSummary($filters, $summary),
            'highlights' => $model->reportHighlights($filters),
            'directionRows' => $this->directionRows($summary),
        ], 'rapport-periodique-global');
    }

    public function daily()
    {
        $model = $this->model('ReportModel');
        $filters = $this->filters();
        $type = $this->reportType($_GET['type'] ?? 'reception', ['reception', 'supplier'], 'reception');
        $rows = $type === 'supplier' ? $model->receptionBySupplier($filters) : $model->dailyReception($filters);

        $this->render('reports.daily', [
            'title' => $type === 'supplier' ? 'Rapport fournisseur' : 'Rapport journalier reception',
            'type' => $type,
            'filters' => $filters,
            'references' => $model->referenceData(),
            'rows' => $rows,
        ], $type === 'supplier' ? 'rapport-fournisseur' : 'rapport-journalier-reception');
    }

    public function supplier()
    {
        $_GET['type'] = 'supplier';
        $this->daily();
    }

    public function production()
    {
        $model = $this->model('ReportModel');
        $filters = $this->filters();
        $type = $this->reportType($_GET['type'] ?? 'production', ['production', 'waste', 'yield'], 'production');
        $titles = [
            'production' => 'Rapport journalier production',
            'waste' => 'Rapport dechets',
            'yield' => 'Rapport rendement machine',
        ];

        if ($type === 'waste') {
            $rows = $model->waste($filters);
        } elseif ($type === 'yield') {
            $rows = $model->yieldByMachine($filters);
        } else {
            $rows = $model->production($filters);
        }

        $this->render('reports.production', [
            'title' => $titles[$type],
            'type' => $type,
            'filters' => $filters,
            'references' => $model->referenceData(),
            'rows' => $rows,
        ], $titles[$type]);
    }

    public function waste()
    {
        $_GET['type'] = 'waste';
        $this->production();
    }

    public function yield()
    {
        $_GET['type'] = 'yield';
        $this->production();
    }

    public function stocks()
    {
        $model = $this->model('ReportModel');
        $filters = $this->filters();

        $this->render('reports.stocks', [
            'title' => 'Rapport stock silos',
            'filters' => $filters,
            'references' => $model->referenceData(),
            'silos' => $model->siloStocks(),
            'finishedStocks' => $model->finishedStocks(),
        ], 'rapport-stock-silos');
    }

    public function distribution()
    {
        $model = $this->model('ReportModel');
        $filters = $this->filters();
        $type = $this->reportType($_GET['type'] ?? 'distribution', ['distribution', 'packaging'], 'distribution');
        $rows = $type === 'packaging' ? $model->packaging($filters) : $model->distribution($filters);

        $this->render('reports.distribution', [
            'title' => $type === 'packaging' ? 'Rapport emballage' : 'Rapport distribution',
            'type' => $type,
            'filters' => $filters,
            'references' => $model->referenceData(),
            'rows' => $rows,
        ], $type === 'packaging' ? 'rapport-emballage' : 'rapport-distribution');
    }

    public function packaging()
    {
        $_GET['type'] = 'packaging';
        $this->distribution();
    }

    private function render($view, array $data, $filename)
    {
        $export = $_GET['export'] ?? '';
        $data['generatedAt'] = date('d/m/Y H:i');

        if ($export === 'excel') {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $this->slug($filename) . '.xls"');
            header('Pragma: no-cache');
            header('Expires: 0');
            $data['exportMode'] = 'excel';
            $this->view($view, $data);
            return;
        }

        if ($export === 'pdf') {
            $data['pdfMode'] = true;
            $data['exportMode'] = 'pdf';
            $html = $this->renderViewToString($view, $data);
            (new PdfService())->stream($data['title'] ?? 'Rapport CMCK', $html, $this->slug($filename) . '.pdf');
            return;
        }

        if ($export === 'print') {
            $data['printMode'] = true;
            $data['autoPrint'] = true;
            $data['exportMode'] = $export;
            $this->view($view, $data, 'layouts.main');
            return;
        }

        $this->view($view, $data, 'layouts.main');
    }

    private function filters()
    {
        $today = date('Y-m-d');
        $start = $_GET['start_date'] ?? $today;
        $end = $_GET['end_date'] ?? $start;

        $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ? $start : $today;
        $end = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) ? $end : $start;

        if ($end < $start) {
            $end = $start;
        }

        return [
            'start_date' => $start,
            'end_date' => $end,
            'supplier_id' => $this->positiveId($_GET['supplier_id'] ?? ''),
            'machine_id' => $this->positiveId($_GET['machine_id'] ?? ''),
        ];
    }

    private function directionRows(array $summary)
    {
        return [
            ['label' => 'Receptions matieres premieres', 'count' => $summary['reception_count'], 'quantity' => $summary['received_kg']],
            ['label' => 'Production validee', 'count' => $summary['production_count'], 'quantity' => $summary['produced_kg']],
            ['label' => 'Dechets generes', 'count' => $summary['production_count'], 'quantity' => $summary['waste_kg']],
            ['label' => 'Dechets traites', 'count' => $summary['waste_count'], 'quantity' => $summary['waste_processed_kg']],
            ['label' => 'Emballage produits finis', 'count' => $summary['packaging_count'], 'quantity' => $summary['packaged_kg']],
            ['label' => 'Distribution', 'count' => $summary['distribution_count'], 'quantity' => $summary['distributed_kg']],
        ];
    }

    private function reportType($value, array $allowed, $default)
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function positiveId($value)
    {
        return ctype_digit((string) $value) && (int) $value > 0 ? (string) $value : '';
    }

    private function slug($value)
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value));
        return trim($slug, '-') ?: 'rapport-cmck';
    }
}
