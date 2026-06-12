<?php

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfService
{
    public function stream($title, $bodyHtml, $filename, $orientation = 'landscape')
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
        $options->set('chroot', dirname(__DIR__, 2));
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->document($title, $bodyHtml), 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();
        $dompdf->stream($filename, ['Attachment' => true]);
    }

    private function runtimePaths()
    {
        $base = dirname(__DIR__, 2) . '/storage/dompdf';
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

        return '<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>
    <style>' . $css . '</style>
</head>
<body>
    <header class="pdf-header">
        <div>
            <p>CMCK MillTrack</p>
            <h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>
        </div>
        <span>Document professionnel</span>
    </header>
    <main>' . $bodyHtml . '</main>
    <footer class="pdf-footer">
        <span>Genere par CMCK MillTrack</span>
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
            .signatures { display: table; width: 100%; border-spacing: 16px; margin-top: 52px; }
            .signature { display: table-cell; width: 33.33%; border-top: 1px solid #071527; padding-top: 8px; text-align: center; font-weight: bold; }
        ';
    }
}
