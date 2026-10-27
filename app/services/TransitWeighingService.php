<?php
require_once __DIR__.'/SpreadsheetExportService.php';

/** Farm-to-butchery double weighing: weighing input, transit gap, tolerances and gap report. */
class TransitWeighingService extends Model
{
    public const MAX_GAP_PCT = 50.0;
    private const ADMIN_ROLES = ['administrateur','administrateur-systeme'];

    public function __construct(PDO $db = null)
    {
        $this->db = $db ?: Database::getInstance()->connection();
    }

    public static function ready(PDO $db): bool
    {
        return (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='livestock_transit_tolerances'")->fetchColumn() === 1;
    }

    /** Weight entered directly as net, or as gross minus a tare (ice, crates, hooks…). */
    public static function parse(array $data, string $label = 'Poids'): array
    {
        $mode = $data['weighing_mode'] ?? 'net';
        if (!in_array($mode, ['net','gross'], true)) throw new RuntimeException('Mode de pesée invalide.');
        $note = $data['tare_note'] ?? '';
        if (!is_string($note) || mb_strlen(trim($note)) > 120) throw new RuntimeException('Nature de la tare : 120 caractères au maximum.');
        if ($mode === 'net') {
            $net = self::kg($data['net_kg'] ?? '', $label.' net');
            return ['mode'=>'net','gross'=>$net,'tare'=>0.0,'net'=>$net,'tare_note'=>null];
        }
        $gross = self::kg($data['gross_kg'] ?? '', $label.' brut');
        $tare = self::kg($data['tare_kg'] ?? '', 'Tare', true);
        $net = round($gross - $tare, 3);
        if ($net <= 0) throw new RuntimeException('La tare doit être inférieure au poids brut.');
        return ['mode'=>'gross','gross'=>$gross,'tare'=>$tare,'net'=>$net,'tare_note'=>trim($note) === '' ? null : trim($note)];
    }

    /** Same as parse(), but returns null when no weight was entered. */
    public static function parseOptional(array $data, string $label = 'Poids'): ?array
    {
        $mode = $data['weighing_mode'] ?? 'net';
        $empty = $mode === 'gross' ? trim((string)($data['gross_kg'] ?? '')) === '' && trim((string)($data['tare_kg'] ?? '')) === '' : trim((string)($data['net_kg'] ?? '')) === '';
        return $empty ? null : self::parse($data, $label);
    }

    private static function kg($value, string $label, bool $zero = false): float
    {
        if (!is_scalar($value) || !preg_match('/^\d{1,9}(?:[.,]\d{1,3})?$/D', trim((string)$value))) throw new RuntimeException($label.' : saisissez un poids en kg, avec au plus 3 décimales.');
        $kg = round((float)str_replace(',', '.', trim((string)$value)), 3);
        if ($zero ? $kg < 0 : $kg <= 0) throw new RuntimeException($label.' : la valeur doit être '.($zero ? 'positive ou nulle.' : 'supérieure à zéro.'));
        return $kg;
    }

    /** Positive gap = loss in transit; negative gap = more weight received than sent. */
    public static function gap(float $sent, float $received): array
    {
        $kg = round($sent - $received, 3);
        return ['kg'=>$kg, 'pct'=>$sent > 0 ? round($kg / $sent * 100, 3) : 0.0];
    }

    /** Without a configured tolerance, any gap must be justified and approved. */
    public static function exceeds(array $gap, ?float $tolerancePct): bool
    {
        return $tolerancePct === null ? abs($gap['kg']) > 0 : abs($gap['pct']) > $tolerancePct + 1e-9;
    }

    public static function describe(array $gap): string
    {
        $kg = number_format(abs($gap['kg']), 3, ',', ' ');
        $pct = number_format(abs($gap['pct']), 2, ',', ' ');
        if (abs($gap['kg']) < 0.0005) return 'Aucun écart';
        return ($gap['kg'] > 0 ? 'Perte de ' : 'Excédent de ').$kg.' kg ('.$pct.' %)';
    }

    public function tolerance(int $speciesId): array
    {
        $row = $this->query('SELECT t.tolerance_pct,t.approver_role_id,r.name role_name FROM livestock_transit_tolerances t LEFT JOIN roles r ON r.id=t.approver_role_id WHERE t.species_id=?', [$speciesId])->fetch();
        return $row ? ['pct'=>(float)$row['tolerance_pct'],'approver_role_id'=>$row['approver_role_id'] === null ? null : (int)$row['approver_role_id'],'role_name'=>$row['role_name']] : ['pct'=>null,'approver_role_id'=>null,'role_name'=>null];
    }

    /** The configured role approves exceeded gaps; administrators always can. */
    public function canApprove(int $userId, ?int $roleId, int $siteId): bool
    {
        $roles = $this->query("SELECT r.id,r.slug FROM user_role_assignments a JOIN roles r ON r.id=a.role_id
            WHERE a.user_id=? AND a.approval_status='approved' AND a.deleted_at IS NULL AND a.starts_at<=NOW() AND (a.expires_at IS NULL OR a.expires_at>NOW())
              AND (a.site_id IS NULL OR a.site_id=?) AND r.status='active' AND r.deleted_at IS NULL", [$userId,$siteId])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($roles as $role) if (in_array($role['slug'], self::ADMIN_ROLES, true) || ($roleId !== null && (int)$role['id'] === $roleId)) return true;
        return $roleId === null;
    }

    public function settings(): array
    {
        return [
            'species' => $this->query("SELECT s.id,s.code,s.name,s.category,t.tolerance_pct,t.approver_role_id,r.name role_name,t.updated_at,u.name editor_name
                FROM livestock_species s LEFT JOIN livestock_transit_tolerances t ON t.species_id=s.id LEFT JOIN roles r ON r.id=t.approver_role_id LEFT JOIN users u ON u.id=t.updated_by
                WHERE s.status='active' AND s.deleted_at IS NULL AND s.category IN ('livestock','fish') ORDER BY s.category DESC,s.name")->fetchAll(PDO::FETCH_ASSOC),
            'roles' => $this->query("SELECT id,name FROM roles WHERE status='active' AND deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function saveTolerance(array $data, array $user, int $butcherySite): void
    {
        if (!Auth::can('butchery', 'administer', $butcherySite)) throw new RuntimeException('Le paramétrage des écarts est réservé aux administrateurs de la boucherie.');
        $species = $data['species_id'] ?? '';
        if (!is_scalar($species) || !ctype_digit((string)$species)) throw new RuntimeException('Espèce invalide.');
        $row = $this->query("SELECT id,name FROM livestock_species WHERE id=? AND status='active' AND deleted_at IS NULL", [(int)$species])->fetch();
        if (!$row) throw new RuntimeException('Espèce introuvable.');
        $pct = str_replace(',', '.', trim((string)($data['tolerance_pct'] ?? '')));
        if (!preg_match('/^\d{1,2}(?:\.\d{1,2})?$/D', $pct) || (float)$pct > self::MAX_GAP_PCT) throw new RuntimeException('Tolérance : pourcentage de 0 à 50, avec au plus 2 décimales.');
        $role = $data['approver_role_id'] ?? '';
        if ($role !== '' && (!is_scalar($role) || !ctype_digit((string)$role) || !$this->query("SELECT id FROM roles WHERE id=? AND status='active' AND deleted_at IS NULL", [(int)$role])->fetch())) throw new RuntimeException('Rôle de validation introuvable.');
        $before = $this->tolerance((int)$row['id']);
        $this->db->beginTransaction();
        try {
            $this->query('INSERT INTO livestock_transit_tolerances(species_id,tolerance_pct,approver_role_id,updated_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE tolerance_pct=VALUES(tolerance_pct),approver_role_id=VALUES(approver_role_id),updated_by=VALUES(updated_by)',
                [(int)$row['id'], round((float)$pct, 2), $role === '' ? null : (int)$role, $user['id']]);
            $this->logActivity('transit_tolerance_update', 'butchery', 'livestock_transit_tolerances', (int)$row['id'], 'Tolérance d’écart de transport · '.$row['name'].'.', $before, ['pct'=>round((float)$pct, 2),'approver_role_id'=>$role === '' ? null : (int)$role], $user, $butcherySite);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Farm-to-butchery receipts with both weights, for a period, a species and a gap status. */
    public function report(int $butcherySite, array $query): array
    {
        $filters = [];
        foreach (['from'=>date('Y-m-01'),'to'=>date('Y-m-d'),'species'=>'','status'=>''] as $key => $default) {
            $value = $query[$key] ?? $default;
            if (!is_string($value) || mb_strlen($value) > 40) throw new InvalidArgumentException('Filtre invalide.');
            $filters[$key] = trim($value) === '' ? $default : trim($value);
        }
        foreach (['from','to'] as $key) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key]);
            if (!$date || $date->format('Y-m-d') !== $filters[$key]) throw new InvalidArgumentException('Date invalide.');
        }
        if ($filters['from'] > $filters['to']) throw new InvalidArgumentException('La date de début doit précéder la date de fin.');
        if (!in_array($filters['status'], ['','within','exceeded','unweighed'], true) || ($filters['species'] !== '' && !ctype_digit($filters['species']))) throw new InvalidArgumentException('Filtre invalide.');
        $params = [$butcherySite, $filters['from'].' 00:00:00', (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d').' 00:00:00'];
        $species = '';
        if ($filters['species'] !== '') { $species = ' AND b.species_id=?'; $params[] = (int)$filters['species']; }
        $rows = $this->query("SELECT r.id,r.receipt_number,r.received_at,r.status,r.sent_weight_kg,r.received_weight_kg,r.received_gross_kg,r.received_tare_kg,r.tare_note,r.weight_gap_kg,r.weight_gap_pct,r.tolerance_pct,r.gap_reason,r.gap_approved_at,
                t.transfer_number,t.quantity_kg,t.truck_plate,t.driver_name,b.batch_number,sp.id species_id,sp.name species_name,src.name source_name,weigher.name weigher_name,approver.name approver_name
            FROM butchery_receipts r JOIN livestock_transfers t ON t.id=r.livestock_transfer_id JOIN livestock_batches b ON b.id=t.batch_id JOIN livestock_species sp ON sp.id=b.species_id
            JOIN sites src ON src.id=t.source_site_id LEFT JOIN users weigher ON weigher.id=r.created_by LEFT JOIN users approver ON approver.id=r.gap_approved_by
            WHERE r.site_id=? AND r.source_type='internal_btr' AND (t.source_unit='kg' OR r.sent_weight_kg IS NOT NULL) AND r.deleted_at IS NULL AND r.status<>'cancelled' AND r.received_at>=? AND r.received_at<?{$species}
            ORDER BY r.received_at DESC,r.id DESC", $params)->fetchAll(PDO::FETCH_ASSOC);
        $kept = [];
        $totals = ['sent'=>0.0,'received'=>0.0,'gap'=>0.0,'weighed'=>0,'exceeded'=>0,'unweighed'=>0];
        $bySpecies = [];
        foreach ($rows as $row) {
            $row['weighed'] = $row['weight_gap_kg'] !== null;
            $row['exceeded'] = $row['weighed'] && self::exceeds(['kg'=>(float)$row['weight_gap_kg'],'pct'=>(float)$row['weight_gap_pct']], $row['tolerance_pct'] === null ? null : (float)$row['tolerance_pct']);
            $state = !$row['weighed'] ? 'unweighed' : ($row['exceeded'] ? 'exceeded' : 'within');
            if ($filters['status'] !== '' && $filters['status'] !== $state) continue;
            $row['state'] = $state;
            $kept[] = $row;
            if (!$row['weighed']) { $totals['unweighed']++; continue; }
            $sent = (float)$row['sent_weight_kg'];
            $received = (float)$row['received_weight_kg'];
            $totals['sent'] += $sent; $totals['received'] += $received; $totals['weighed']++;
            if ($row['exceeded']) $totals['exceeded']++;
            $key = $row['species_name'];
            $bySpecies[$key] = $bySpecies[$key] ?? ['species'=>$key,'receipts'=>0,'sent'=>0.0,'received'=>0.0,'exceeded'=>0];
            $bySpecies[$key]['receipts']++; $bySpecies[$key]['sent'] += $sent; $bySpecies[$key]['received'] += $received;
            if ($row['exceeded']) $bySpecies[$key]['exceeded']++;
        }
        $totals['gap'] = round($totals['sent'] - $totals['received'], 3);
        $totals['gap_pct'] = $totals['sent'] > 0 ? round($totals['gap'] / $totals['sent'] * 100, 2) : 0.0;
        foreach ($bySpecies as &$line) { $line['gap'] = round($line['sent'] - $line['received'], 3); $line['gap_pct'] = $line['sent'] > 0 ? round($line['gap'] / $line['sent'] * 100, 2) : 0.0; }
        unset($line);
        return ['filters'=>$filters,'rows'=>$kept,'totals'=>$totals,'bySpecies'=>array_values($bySpecies),'issuedAt'=>date('d/m/Y à H:i')];
    }

    public function spreadsheet(array $report): string
    {
        $t = $report['totals'];
        $sheet = [['DAGRIL — ÉCARTS DE PESÉE FERME / BOUCHERIE'],['Du '.date('d/m/Y', strtotime($report['filters']['from'])).' au '.date('d/m/Y', strtotime($report['filters']['to']))],
            [$t['weighed'].' réception(s) pesée(s) · envoyé '.self::number($t['sent']).' kg · reçu '.self::number($t['received']).' kg · écart '.self::number($t['gap']).' kg ('.str_replace('.', ',', (string)$t['gap_pct']).' %) · '.$t['exceeded'].' hors tolérance'],
            ['Édité le '.$report['issuedAt']],
            ['Date','Réception','Espèce','Lot','Ferme','BTR','Envoyé (kg)','Reçu net (kg)','Brut (kg)','Tare (kg)','Nature de la tare','Écart (kg)','Écart (%)','Tolérance (%)','État','Motif','Pesé par','Écart validé par']];
        $states = ['within'=>'Dans la tolérance','exceeded'=>'Hors tolérance','unweighed'=>'Non pesé à l’arrivée'];
        foreach ($report['rows'] as $r) {
            $sheet[] = [date('d/m/Y H:i', strtotime($r['received_at'])),$r['receipt_number'],$r['species_name'],$r['batch_number'],$r['source_name'],$r['transfer_number'],(float)($r['sent_weight_kg'] ?? $r['quantity_kg']),$r['weighed'] ? (float)$r['received_weight_kg'] : '',$r['received_gross_kg'] === null ? '' : (float)$r['received_gross_kg'],$r['received_tare_kg'] === null ? '' : (float)$r['received_tare_kg'],(string)$r['tare_note'],$r['weighed'] ? (float)$r['weight_gap_kg'] : '',$r['weighed'] ? (float)$r['weight_gap_pct'] : '',$r['tolerance_pct'] === null ? '' : (float)$r['tolerance_pct'],$states[$r['state']],(string)$r['gap_reason'],(string)$r['weigher_name'],(string)$r['approver_name']];
        }
        return (new SpreadsheetExportService())->build($sheet, [17,26,14,26,18,26,13,13,12,12,20,12,11,12,20,30,20,20]);
    }

    private static function number(float $value): string
    {
        return number_format($value, 3, ',', ' ');
    }
}
