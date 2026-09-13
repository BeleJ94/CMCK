<?php

class WasteExportService
{
    public function filtered(array $lines, array $filters): array
    {
        $normalize = function ($value) {
            $value = mb_strtolower((string)$value, 'UTF-8');
            return strtr($value, ['é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a','ä'=>'a','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c']);
        };
        return array_values(array_filter($lines, function ($l) use ($filters, $normalize) {
            $usable = $l['status']==='active' && (float)$l['quantity_kg']>0 && in_array($l['storage_status'], ['available','buffer'], true);
            $state = $usable ? $l['storage_status'] : 'empty';
            $type = $l['waste_type_name'] ?: 'Non précisé';
            $text = $this->searchText($l);
            return (!$filters['type'] || $type===$filters['type']) && (!$filters['site'] || $l['site_code']===$filters['site']) && (!$filters['state'] || ($filters['state']==='usable' ? $usable : $state===$filters['state'])) && (!$filters['search'] || strpos($normalize($text),$normalize($filters['search']))!==false);
        }));
    }

    public function searchText(array $l): string
    {
        return implode(' ', [$l['batch_number'] ?: 'Stock de déchets', $l['product_name'], ($l['waste_type_name'] ?: 'Non précisé'), 'Qualité : '.($l['quality_grade'] ?: 'Non précisée'), $l['site_code'], number_format((float)$l['quantity_kg'],3,',',' ').' kg', $this->status($l), date('d/m/Y H:i',strtotime($l['created_at']))]);
    }

    public function status(array $l): string
    {
        $labels=['available'=>'Disponible','buffer'=>'En tampon','consumed'=>'Consommé','sold'=>'Vendu','reserved'=>'Réservé'];
        return $labels[$l['storage_status']] ?? 'Indisponible';
    }

    public function xlsx(array $lines, string $summary): string
    {
        $xml = function ($s) { return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$s), ENT_XML1 | ENT_QUOTES, 'UTF-8'); };
        $rows = [['DAGRIL · Déchets et coproduits'], ['Exporté le '.date('d/m/Y H:i')], [$summary], [], ['Origine','Produit','Type / qualité','Site','Disponible (kg)','Situation','Créé le']];
        foreach ($lines as $l) $rows[] = [$l['batch_number'] ?: 'Stock de déchets', $l['product_name'], ($l['waste_type_name'] ?: 'Non précisé').' / '.($l['quality_grade'] ?: 'Non précisée'), $l['site_code'], (float)$l['quantity_kg'], $this->status($l), date('d/m/Y H:i',strtotime($l['created_at']))];
        $last = count($rows);
        $rows[] = ['Total · '.count($lines).' stock(s)', '', '', '', array_sum(array_column($lines,'quantity_kg'))];
        require_once __DIR__.'/SpreadsheetExportService.php';
        return (new SpreadsheetExportService())->build($rows,[38,26,30,12,20,19,22]);
    }
}
