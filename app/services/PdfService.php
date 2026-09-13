<?php

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfService
{
    public function stream($title, $bodyHtml, $filename, $orientation = 'landscape', $attachment = true)
    {
        $dompdf = $this->render($title, $bodyHtml, $orientation);
        $dompdf->stream($filename, ['Attachment' => $attachment]);
    }

    public function output($title, $bodyHtml, $orientation = 'landscape')
    {
        return $this->render($title, $bodyHtml, $orientation)->output();
    }

    private function render($title, $bodyHtml, $orientation)
    {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException('La bibliotheque PDF dompdf n est pas installee. Executez composer install.');
        }

        $paths = $this->runtimePaths();
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('tempDir', $paths['temp']);
        $options->set('fontDir', $paths['fonts']);
        $options->set('fontCache', $paths['cache']);
        $options->set('chroot', [dirname(__DIR__, 2), sys_get_temp_dir()]);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->document($title, $bodyHtml), 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();
        return $dompdf;
    }

    private function runtimePaths()
    {
        $candidates = [
            dirname(__DIR__, 2) . '/storage/dompdf',
            rtrim(sys_get_temp_dir(), '/') . '/dagril-erp-dompdf',
        ];

        $lastError = null;

        foreach ($candidates as $base) {
            try {
                return $this->prepareRuntimePaths($base);
            } catch (RuntimeException $exception) {
                $lastError = $exception->getMessage();
            }
        }

        throw new RuntimeException('Aucun dossier PDF accessible en ecriture. Derniere erreur: ' . $lastError);
    }

    private function prepareRuntimePaths($base)
    {
        $paths = [
            'base' => $base,
            'temp' => $base . '/temp',
            'fonts' => $base . '/fonts',
            'cache' => $base . '/cache',
        ];

        foreach ($paths as $path) {
            $this->ensureDirectory($path);
        }

        return $paths;
    }

    private function ensureDirectory($path)
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Impossible de creer le dossier PDF: ' . $path);
        }

        @chmod($path, 0777);

        if (!is_writable($path)) {
            throw new RuntimeException('Le dossier PDF n est pas accessible en ecriture: ' . $path);
        }
    }

    private function document($title, $bodyHtml)
    {
        $css = $this->css();
        $bodyClass = strpos($bodyHtml, 'compact-weighing-ticket') !== false ? 'pdf-weighing-ticket' : '';

        return '<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>
    <style>' . $css . '</style>
</head>
<body class="' . $bodyClass . '">
    <header class="pdf-header">
        <div>
            <p>DAGRIL ERP</p>
            <h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>
        </div>
        <span>Document professionnel</span>
    </header>
    <main>' . $bodyHtml . '</main>
    <footer class="pdf-footer">
        <span>Genere par DAGRIL ERP</span>
        <span>' . date('d/m/Y H:i') . '</span>
    </footer>
</body>
</html>';
    }

    private function css()
    {
        return '
            @page { margin: 22px 24px 28px; }
            * { box-sizing: border-box; }
            body { margin: 0; color: #162033; font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; }
            .pdf-header { display: table; width: 100%; padding-bottom: 12px; margin-bottom: 14px; border-bottom: 2px solid #15803d; }
            .pdf-header > div { display: table-cell; vertical-align: top; }
            .pdf-header p, .section-label { margin: 0 0 3px; color: #c77700; font-size: 9px; font-weight: bold; text-transform: uppercase; }
            .pdf-header h1 { margin: 0; color: #071527; font-size: 20px; }
            .pdf-header > span { display: table-cell; width: 180px; text-align: right; color: #667085; vertical-align: top; font-size: 9px; }
            .pdf-footer { position: fixed; left: 0; right: 0; bottom: -12px; display: table; width: 100%; color: #667085; border-top: 1px solid #d9e1ea; padding-top: 6px; font-size: 8px; }
            .pdf-footer span { display: table-cell; }
            .pdf-footer span:last-child { text-align: right; }
            .print-hidden, .sidebar, .topbar, script, .page-action, .btn-primary, .btn-secondary { display: none !important; }
            .dashboard-hero, .table-panel, .metric-card, .decision-item, .executive-summary > article { border: 1px solid #d9e1ea; border-radius: 4px; padding: 12px; margin-bottom: 12px; background: #fff; }
            .dashboard-hero { display: block; border-left: 4px solid #15803d; }
            .hero-icon, .panel-icon, .metric-icon { display: none; }
            h2, h3 { margin: 0 0 6px; color: #071527; }
            p { margin: 0 0 6px; color: #667085; line-height: 1.35; }
            .metric-grid { width: 100%; display: table; table-layout: fixed; border-spacing: 8px; margin: 0 0 10px; }
            .metric-card { display: table-cell; width: 16.66%; vertical-align: top; min-height: 72px; }
            .metric-card-top span:first-child, .decision-item span, .executive-summary span { display: block; color: #667085; font-size: 8px; font-weight: bold; text-transform: uppercase; }
            .metric-card strong, .decision-item strong, .executive-summary strong { display: block; margin-top: 8px; color: #071527; font-size: 14px; }
            .executive-summary, .decision-strip, .report-insights { display: block; margin-bottom: 12px; }
            .executive-summary > article, .decision-item { display: inline-block; width: 19%; min-height: 78px; vertical-align: top; }
            .executive-summary > .executive-main { width: 38%; border-left: 4px solid #15803d; }
            .report-insights .table-panel { display: inline-block; width: 49%; vertical-align: top; }
            .panel-heading { margin-bottom: 8px; }
            .table-responsive { width: 100%; overflow: visible; }
            table { width: 100%; border-collapse: collapse; margin-top: 6px; }
            th, td { border: 1px solid #d9e1ea; padding: 6px 7px; text-align: left; vertical-align: top; }
            th { color: #071527; background: #eef2f6; font-size: 8px; text-transform: uppercase; }
            td { font-size: 8.5px; }
            tbody tr:nth-child(even) td { background: #f9fbfd; }
            .tone-red, .tone-danger { border-left-color: #c24132 !important; }
            .tone-orange, .tone-warning { border-left-color: #c77700 !important; }
            .tone-green, .tone-success { border-left-color: #15803d !important; }
            .alert-list, .modal-backdrop, .notification-modal, .dashboard-charts { display: none; }
            .ticket-card, .sheet { border: 1px solid #d9e1ea; padding: 18px; margin: 0 auto 12px; max-width: 720px; }
            .ticket-header, .top { display: table; width: 100%; padding-bottom: 12px; margin-bottom: 16px; border-bottom: 2px solid #071527; }
            .ticket-header > div, .top > div:first-child { display: table-cell; vertical-align: top; }
            .ticket-header > strong, .meta { display: table-cell; text-align: right; color: #071527; vertical-align: top; }
            .ticket-grid, .ticket-footer { display: table; width: 100%; border-spacing: 8px; margin-bottom: 10px; }
            .ticket-grid > div, .ticket-footer > div { display: table-cell; width: 33.33%; border: 1px solid #d9e1ea; padding: 9px; }
            .ticket-weights { display: table; width: 100%; border-spacing: 8px; margin: 12px 0; }
            .ticket-weights > div { display: table-cell; width: 33.33%; padding: 12px; color: #071527; border: 1px solid #d9e1ea; background: #eef2f6; }
            .ticket-card span, .sheet th { color: #667085; font-size: 8px; font-weight: bold; text-transform: uppercase; }
            .ticket-card strong { display: block; margin-top: 4px; color: #071527; font-size: 11px; }
            .compact-weighing-ticket { padding: 12px; }
            .compact-weighing-ticket .ticket-header { margin-bottom: 8px; padding-bottom: 8px; }
            .compact-weighing-ticket .ticket-header h2 { font-size: 17px; margin: 5px 0; }
            .compact-weighing-ticket .ticket-reference { text-align: right; }
            .compact-weighing-ticket .ticket-reference small { display: block; font-size: 8px; margin-top: 4px; }
            .compact-weighing-ticket .ticket-grid, .compact-weighing-ticket .ticket-footer { border-spacing: 5px; margin-bottom: 3px; }
            .compact-weighing-ticket .ticket-grid > div, .compact-weighing-ticket .ticket-footer > div { padding: 6px; }
            .compact-weighing-ticket .ticket-weights { margin: 8px 0; border-spacing: 5px; }
            .compact-weighing-ticket .ticket-weights > div { padding: 9px; }
            .compact-weighing-ticket .ticket-weights strong { font-size: 16px; }
            .compact-weighing-ticket .ticket-section-title { font-size: 10px; margin: 10px 0 4px; }
            .compact-weighing-ticket .ticket-detail-note { font-size: 9px; }
            /* Monochrome weighing ticket shared by preview, download and print. */
            .pdf-weighing-ticket { color: #222222; }
            .pdf-weighing-ticket .pdf-header { display: none; }
            .pdf-weighing-ticket .compact-weighing-ticket { border: 0; padding: 0; max-width: none; }
            .pdf-weighing-ticket .ticket-card * { background: transparent; color: #222222; }
            .pdf-weighing-ticket .ticket-header { padding: 10px 6px 12px; border: 0; border-bottom: .5pt solid #888888; margin: 0 0 12px; }
            .pdf-weighing-ticket .ticket-header > div { width: 50%; }
            .pdf-weighing-ticket .ticket-header p { font-size: 9px; letter-spacing: 1px; margin: 0 0 6px; }
            .pdf-weighing-ticket .ticket-header h2 { font-size: 20px; margin: 0 0 8px; }
            .pdf-weighing-ticket .ticket-header strong { font-size: 10px; }
            .pdf-weighing-ticket .ticket-reference small { font-size: 8px; line-height: 1.5; }
            .pdf-weighing-ticket .ticket-state { display: inline-block; font-size: 9px; }
            .pdf-weighing-ticket .ticket-weights { border-spacing: 6px; margin: 0 0 12px; table-layout: fixed; }
            .pdf-weighing-ticket .ticket-weights > div { padding: 10px 8px; border: 0; border-bottom: .5pt solid #aaaaaa; background: transparent; }
            .pdf-weighing-ticket .ticket-weights strong { font-size: 18px; margin-top: 6px; }
            .pdf-weighing-ticket .ticket-section-title { font-size: 10px; margin: 12px 6px 4px; padding-bottom: 6px; border-bottom: .5pt solid #aaaaaa; }
            .pdf-weighing-ticket .ticket-section-title span { font-size: 10px; margin-left: 8px; }
            .pdf-weighing-ticket .ticket-grid { border-spacing: 6px; margin: 0; table-layout: fixed; }
            .pdf-weighing-ticket .ticket-grid > div { padding: 8px; border: 0; }
            .pdf-weighing-ticket .ticket-grid strong { font-size: 10px; font-weight: normal; line-height: 1.5; margin-top: 5px; overflow-wrap: anywhere; }
            .pdf-weighing-ticket .ticket-card span { font-size: 8px; font-weight: normal; color: #444444; }
            .pdf-weighing-ticket .ticket-detail-note { margin: 6px 14px; font-size: 9px; line-height: 1.5; }
            .pdf-weighing-ticket .ticket-footer { margin-top: 12px; border-top: .5pt solid #888888; border-spacing: 6px; table-layout: fixed; }
            .pdf-weighing-ticket .ticket-footer > div { border: 0; padding: 9px 8px; }
            .pdf-weighing-ticket .ticket-footer strong { font-size: 9px; font-weight: normal; }
            .pdf-weighing-ticket .pdf-footer { color: #444444; font-size: 8px; border-top: .5pt solid #aaaaaa; }
            .signatures { display: table; width: 100%; border-spacing: 16px; margin-top: 52px; }
            .signature { display: table-cell; width: 33.33%; border-top: 1px solid #071527; padding-top: 8px; text-align: center; font-weight: bold; }
        ';
    }
}
