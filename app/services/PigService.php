<?php

/** First stage of the porcine lifecycle. Head counts remain in livestock_batches. */
class PigService extends Model
{
    public function __construct(PDO $db = null)
    {
        $this->db = $db ?: Database::getInstance()->connection();
    }

    public static function date($value): DateTimeImmutable
    {
        if (!is_string($value)) throw new RuntimeException('Saisissez une date de naissance valide.');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new RuntimeException('Saisissez une date de naissance valide.');
        return $date;
    }

    public static function expectedGroup(string $bornOn, ?DateTimeImmutable $today = null): string
    {
        $birth = self::date($bornOn);
        $today = ($today ?: new DateTimeImmutable('today'))->setTime(0, 0);
        if ($birth > $today) throw new RuntimeException('La naissance ne peut pas être dans le futur.');
        // Calendar months, clamped to the last day of the destination month.
        $threshold = static function ($months) use ($birth) {
            $month = $birth->modify('first day of this month')->modify('+'.$months.' months');
            return $month->setDate((int)$month->format('Y'), (int)$month->format('m'), min((int)$birth->format('d'), (int)$month->format('t')));
        };
        return $today < $threshold(2) ? 'A' : ($today < $threshold(3) ? 'B' : 'C');
    }

    public static function version(array $row): string
    {
        $values = [];
        foreach (['id','current_heads','purpose','status','sex','born_on','confirmed_group'] as $key) $values[$key] = (string)($row[$key] ?? '');
        return hash('sha256', json_encode($values));
    }

    public function available(): bool
    {
        $q = $this->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('livestock_pig_profiles','livestock_pig_events')");
        return (int)$q->fetchColumn() === 2;
    }

    /** Keep the generic livestock exits consistent with the porcine lifecycle. */
    public function assertExitAllowed($batchId, string $operation): void
    {
        $species = $this->query("SELECT sp.code FROM livestock_batches b JOIN livestock_species sp ON sp.id=b.species_id WHERE b.id=?", [$batchId])->fetchColumn();
        if ($species !== 'PORC' || !$this->available()) return;
        if ($operation !== 'slaughter') throw new RuntimeException('Les porcs sont abattus à la ferme. Transférez ensuite les carcasses vers la boucherie.');
        $profile = $this->row('SELECT born_on,confirmed_group FROM livestock_pig_profiles WHERE batch_id=?', [$batchId]);
        if (!$profile || $profile['confirmed_group'] !== 'C' || self::expectedGroup($profile['born_on']) !== 'C') throw new RuntimeException('Renseignez le lot et validez son passage au groupe C avant l’abattage.');
    }

    public function directory(): array
    {
        $params = [];
        $scope = Auth::siteClause('b.site_id', $params);
        $rows = $this->query("SELECT b.*,p.sex,p.born_on,p.confirmed_group,p.mother_batch_id,p.source_batch_id,f.name facility_name,s.name site_name
            FROM livestock_batches b JOIN livestock_species sp ON sp.id=b.species_id
            JOIN livestock_facilities f ON f.id=b.facility_id JOIN sites s ON s.id=b.site_id
            LEFT JOIN livestock_pig_profiles p ON p.batch_id=b.id
            WHERE sp.code='PORC' AND b.deleted_at IS NULL AND b.status='active' AND b.current_heads>0{$scope}
            ORDER BY p.born_on,b.id", $params)->fetchAll();
        foreach ($rows as &$row) {
            $row['expected_group'] = $row['born_on'] ? self::expectedGroup($row['born_on']) : null;
            $row['pending'] = $row['expected_group'] !== null && $row['expected_group'] !== $row['confirmed_group'];
            $row['version'] = self::version($row);
        }
        unset($row);
        return $rows;
    }

    private function count($value): int
    {
        if (!is_scalar($value) || !preg_match('/^[0-9]{1,8}$/D', (string)$value) || (int)$value < 1) throw new RuntimeException('Saisissez un effectif entier de 1 à 99 999 999.');
        return (int)$value;
    }

    private function farm($value, string $permission): int
    {
        if (!is_scalar($value) || !ctype_digit((string)$value)) throw new RuntimeException('Choisissez une ferme autorisée.');
        $id = (int)$value;
        Auth::requireSiteAccess($id);
        Auth::requirePermission('livestock', $permission, $id);
        $site = $this->row("SELECT id FROM sites WHERE id=? AND code IN ('FARM-MUT','FARM-DIK') AND status='active' AND deleted_at IS NULL", [$id]);
        if (!$site) throw new RuntimeException('Choisissez MUTALA ou DIKAPA.');
        $selected = Auth::currentSiteId();
        if ($selected !== null && (int)$selected !== $id) throw new RuntimeException('Le site choisi ne correspond pas au site courant.');
        return $id;
    }

    private function locked($id, string $permission): array
    {
        $row = $this->row("SELECT b.*,p.sex,p.born_on,p.confirmed_group,p.mother_batch_id,p.source_batch_id FROM livestock_batches b
            JOIN livestock_species sp ON sp.id=b.species_id LEFT JOIN livestock_pig_profiles p ON p.batch_id=b.id
            WHERE b.id=? AND sp.code='PORC' AND b.deleted_at IS NULL FOR UPDATE", [$id]);
        if (!$row) throw new RuntimeException('Lot de porcs introuvable.');
        $this->farm($row['site_id'], $permission);
        if ($row['status'] !== 'active' || (int)$row['current_heads'] < 1) throw new RuntimeException('Ce lot ne contient plus de porcs actifs.');
        return $row;
    }

    private function checkVersion(array $row, array $data): void
    {
        if (!is_string($data['version'] ?? null) || !hash_equals(self::version($row), $data['version'])) throw new RuntimeException('Ce lot a changé. Actualisez la page avant de recommencer.');
    }

    private function identity(array $data): array
    {
        $sex = $data['sex'] ?? '';
        if (!in_array($sex, ['male','female'], true)) throw new RuntimeException('Choisissez mâles ou femelles.');
        $birth = self::date($data['born_on'] ?? '')->format('Y-m-d');
        return [$sex, $birth, self::expectedGroup($birth)];
    }

    private function event(int $batchId, string $type, ?string $old, string $new, int $heads, string $reason, array $user): void
    {
        $this->query('INSERT INTO livestock_pig_events(batch_id,event_type,old_group,new_group,quantity_heads,reason,created_by) VALUES(?,?,?,?,?,?,?)', [$batchId,$type,$old,$new,$heads,$reason,$user['id']]);
    }

    private function headMove(int $id, string $type, int $quantity, int $before, int $after, int $reference, array $user): void
    {
        $this->query("INSERT INTO livestock_head_movements(batch_id,movement_type,quantity_heads,heads_before,heads_after,reference_type,reference_id,movement_at,created_by) VALUES(?,?,?,?,?,'livestock_pig_events',?,CURRENT_TIMESTAMP,?)", [$id,$type,$quantity,$before,$after,$reference,$user['id']]);
    }

    public function register(array $data, array $user): int
    {
        $site = $this->farm($data['site_id'] ?? '', 'create');
        [$sex,$birth,$group] = $this->identity($data);
        $heads = $this->count($data['heads'] ?? '');
        $key = $data['submission_key'] ?? '';
        if (!is_string($key) || !preg_match('/^[a-f0-9]{32}$/D', $key)) throw new RuntimeException('Rechargez le formulaire avant de réessayer.');
        $this->db->beginTransaction();
        try {
            // Lock the facility before the idempotency check to serialize duplicate submissions.
            $facility = $this->row("SELECT * FROM livestock_facilities WHERE id=? AND site_id=? AND facility_type='housing' AND status='active' AND deleted_at IS NULL FOR UPDATE", [$data['facility_id'] ?? 0,$site]);
            if (!$facility) throw new RuntimeException('Choisissez un bâtiment actif de cette ferme.');
            $duplicate = $this->row('SELECT b.id,b.site_id FROM livestock_pig_profiles p JOIN livestock_batches b ON b.id=p.batch_id WHERE p.submission_key=?', [$key]);
            if ($duplicate) {
                if ((int)$duplicate['site_id'] !== $site) throw new RuntimeException('Cette soumission a déjà été utilisée.');
                $this->db->commit();
                return (int)$duplicate['id'];
            }
            $occupied = (int)$this->query("SELECT COALESCE(SUM(current_heads),0) FROM livestock_batches WHERE facility_id=? AND status='active' AND deleted_at IS NULL", [$facility['id']])->fetchColumn();
            if ($facility['capacity_heads'] !== null && $occupied + $heads > (int)$facility['capacity_heads']) throw new RuntimeException('La capacité du bâtiment serait dépassée.');
            $species = $this->row("SELECT id FROM livestock_species WHERE code='PORC' AND status='active' AND deleted_at IS NULL");
            if (!$species) throw new RuntimeException('L’espèce Porc doit être active.');
            $origin = $data['origin'] ?? 'entry';
            if (!in_array($origin, ['entry','birth'], true)) throw new RuntimeException('Origine invalide.');
            $mother = null;
            if ($origin === 'birth') {
                $mother = $this->locked($data['mother_batch_id'] ?? 0, 'create');
                if ((int)$mother['site_id'] !== $site || $mother['sex'] !== 'female' || $mother['purpose'] !== 'breeding' || $mother['confirmed_group'] !== 'C') throw new RuntimeException('Choisissez un lot de femelles reproductrices de cette ferme.');
                if ($group !== 'A' || $birth <= $mother['born_on']) throw new RuntimeException('Une naissance doit alimenter le groupe A, après la naissance des reproductrices.');
            }
            $number = 'PORC-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(5)));
            $this->query("INSERT INTO livestock_batches(site_id,species_id,facility_id,batch_number,purpose,started_at,initial_heads,current_heads,status,created_by) VALUES(?,?,?,?,'fattening',?,?,?,'active',?)", [$site,$species['id'],$facility['id'],$number,date('Y-m-d'),$heads,$heads,$user['id']]);
            $id = (int)$this->db->lastInsertId();
            $this->query('INSERT INTO livestock_pig_profiles(batch_id,sex,born_on,confirmed_group,mother_batch_id,submission_key,confirmed_by,confirmed_at) VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP)', [$id,$sex,$birth,$group,$mother['id'] ?? null,$key,$user['id']]);
            $this->event($id,'registration',null,$group,$heads,$origin === 'birth' ? 'Naissance à la ferme.' : 'Entrée et classement initial vérifiés.', $user);
            $reference = (int)$this->db->lastInsertId();
            $this->headMove($id,$origin === 'birth' ? 'birth' : 'stocking',$heads,0,$heads,$reference,$user);
            $this->logActivity('pig_registration','livestock','livestock_batches',$id,'Enregistrement des porcs.',null,['sex'=>$sex,'born_on'=>$birth,'group'=>$group,'heads'=>$heads,'mother_batch_id'=>$mother['id'] ?? null],$user,$site);
            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function identify($id, array $data, array $user): void
    {
        [$sex,$birth,$group] = $this->identity($data);
        $this->db->beginTransaction();
        try {
            $row = $this->locked($id,'update');
            $this->checkVersion($row,$data);
            if ($row['sex'] !== null) throw new RuntimeException('Ce lot possède déjà un suivi porcin.');
            if ($this->count($data['heads'] ?? '') !== (int)$row['current_heads']) throw new RuntimeException('Confirmez l’effectif actuel complet de ce lot.');
            if ($row['purpose'] === 'breeding' && $group !== 'C') throw new RuntimeException('Un lot reproducteur doit appartenir au groupe C.');
            $this->query('INSERT INTO livestock_pig_profiles(batch_id,sex,born_on,confirmed_group,confirmed_by,confirmed_at) VALUES(?,?,?,?,?,CURRENT_TIMESTAMP)', [$id,$sex,$birth,$group,$user['id']]);
            $this->event((int)$id,'registration',null,$group,(int)$row['current_heads'],'Renseignement du lot existant après vérification terrain.',$user);
            $this->logActivity('pig_identification','livestock','livestock_batches',$id,'Identification du lot existant.',$row,['sex'=>$sex,'born_on'=>$birth,'confirmed_group'=>$group],$user,(int)$row['site_id']);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function confirmGroup($id, array $data, array $user): void
    {
        $this->db->beginTransaction();
        try {
            $row = $this->locked($id,'validate');
            $this->checkVersion($row,$data);
            if (!$row['born_on']) throw new RuntimeException('Renseignez la date de naissance de ce lot.');
            $expected = self::expectedGroup($row['born_on']);
            if ($expected === $row['confirmed_group']) throw new RuntimeException('Ce lot est déjà dans le bon groupe.');
            if (($data['target_group'] ?? '') !== $expected) throw new RuntimeException('Le groupe attendu a changé. Actualisez la page.');
            if (($data['field_checked'] ?? '') !== '1') throw new RuntimeException('Confirmez la vérification des animaux sur le terrain.');
            $this->query('UPDATE livestock_pig_profiles SET confirmed_group=?,confirmed_by=?,confirmed_at=CURRENT_TIMESTAMP WHERE batch_id=?', [$expected,$user['id'],$id]);
            $this->event((int)$id,'group_confirmation',$row['confirmed_group'],$expected,(int)$row['current_heads'],'Passage de groupe vérifié sur le terrain.',$user);
            $this->logActivity('pig_group_confirmation','livestock','livestock_batches',$id,'Validation terrain du changement de groupe.',['group'=>$row['confirmed_group']],['group'=>$expected],$user,(int)$row['site_id']);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function orient($id, array $data, array $user): int
    {
        $heads = $this->count($data['heads'] ?? '');
        $purpose = $data['purpose'] ?? '';
        $reason = $data['reason'] ?? '';
        if (!in_array($purpose,['breeding','fattening'],true) || !is_string($reason) || trim($reason) === '' || mb_strlen($reason) > 500) throw new RuntimeException('Choisissez une orientation et précisez un motif (500 caractères maximum).');
        $this->db->beginTransaction();
        try {
            $row = $this->locked($id,'update');
            $this->checkVersion($row,$data);
            if ($row['confirmed_group'] !== 'C' || !$row['born_on'] || self::expectedGroup($row['born_on']) !== 'C') throw new RuntimeException('Validez d’abord le passage au groupe C.');
            if ($heads > (int)$row['current_heads']) throw new RuntimeException('Effectif disponible insuffisant.');
            if ($row['purpose'] === $purpose) throw new RuntimeException('Ces porcs ont déjà cette orientation.');
            // Existing head commitments cannot be reassigned while awaiting execution.
            if ((int)$this->query("SELECT COUNT(*) FROM livestock_conversions WHERE batch_id=? AND status='submitted'",[$id])->fetchColumn() || (int)$this->query("SELECT COUNT(*) FROM livestock_transfers WHERE batch_id=? AND source_unit='head' AND status IN ('draft','approved')",[$id])->fetchColumn()) throw new RuntimeException('Terminez les opérations en attente de ce lot avant de changer son orientation.');
            $target = (int)$id;
            if ($heads < (int)$row['current_heads']) {
                $number = 'PORC-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(5)));
                $this->query("INSERT INTO livestock_batches(site_id,species_id,facility_id,batch_number,purpose,started_at,initial_heads,current_heads,initial_average_weight_kg,current_average_weight_kg,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,'active',?)", [$row['site_id'],$row['species_id'],$row['facility_id'],$number,$purpose,$row['started_at'],$heads,$heads,$row['initial_average_weight_kg'],$row['current_average_weight_kg'],$user['id']]);
                $target = (int)$this->db->lastInsertId();
                $this->query('INSERT INTO livestock_pig_profiles(batch_id,sex,born_on,confirmed_group,mother_batch_id,source_batch_id,confirmed_by,confirmed_at) VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP)', [$target,$row['sex'],$row['born_on'],'C',$row['mother_batch_id'],$id,$user['id']]);
                $this->query('UPDATE livestock_batches SET current_heads=current_heads-? WHERE id=?',[$heads,$id]);
                $this->event((int)$id,'split','C','C',$heads,mb_substr('Sélection vers le lot #'.$target.' : '.trim($reason),0,500),$user);
                $reference = (int)$this->db->lastInsertId();
                $this->headMove((int)$id,'correction',$heads,(int)$row['current_heads'],(int)$row['current_heads']-$heads,$reference,$user);
                $this->headMove($target,'correction',$heads,0,$heads,$reference,$user);
            } else {
                $this->query('UPDATE livestock_batches SET purpose=? WHERE id=?',[$purpose,$id]);
            }
            $this->event($target,$purpose,'C','C',$heads,trim($reason),$user);
            $this->logActivity('pig_orientation','livestock','livestock_batches',$id,trim($reason),['purpose'=>$row['purpose'],'heads'=>$row['current_heads']],['purpose'=>$purpose,'heads'=>$heads,'target_batch_id'=>$target],$user,(int)$row['site_id']);
            $this->db->commit();
            return $target;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function row(string $sql, array $params = [])
    {
        return $this->query($sql,$params)->fetch();
    }
}
