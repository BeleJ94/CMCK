<?php

/** Referential of pig age groups. Business rules rely on group capabilities, never on group letters. */
class PigGroupService extends Model
{
    public const CAPABILITIES = [
        'accepts_births' => ['Naissances', 'bi-stars', 'Les porcelets nés à la ferme sont enregistrés dans ce groupe.'],
        'allows_breeding' => ['Reproduction', 'bi-heart-pulse', 'Sélection des reproducteurs, réorientation et lots de mères.'],
        'allows_slaughter' => ['Abattage', 'bi-clipboard-check', 'Les lots confirmés dans ce groupe peuvent être abattus à la ferme.'],
    ];
    public const ILLUSTRATIONS = ['piglet' => 'Porcelet', 'young-pig' => 'Porc en croissance', 'adult-pig' => 'Porc adulte', 'pig-farm' => 'Élevage'];
    public const MAX_AGE_MONTHS = 120;
    private const MAX_ACTIVE = 12;
    private static $cache = [];

    public function __construct(PDO $db = null)
    {
        $this->db = $db ?: Database::getInstance()->connection();
    }

    /** Historical A/B/C rules, applied until migration 041 creates the referential. */
    public static function defaults(): array
    {
        $groups = [];
        foreach ([['A','Porcelets',0,1,0,0,'piglet'],['B','Porcs en croissance',2,0,0,0,'young-pig'],['C','Porcs adultes',3,0,1,1,'adult-pig']] as [$code,$name,$age,$births,$breeding,$slaughter,$art]) {
            $groups[] = ['id'=>null,'code'=>$code,'name'=>$name,'description'=>null,'min_age_months'=>$age,'accepts_births'=>$births,'allows_breeding'=>$breeding,'allows_slaughter'=>$slaughter,'illustration'=>$art,'status'=>'active'];
        }
        return self::normalize($groups);
    }

    public static function installed(PDO $db): bool
    {
        try {
            return $db->query('SELECT 1 FROM livestock_pig_groups LIMIT 1') !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    /** Active groups ordered by entry age, cached per connection for the current request. */
    public static function active(PDO $db): array
    {
        $key = spl_object_id($db);
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = self::installed($db)
                ? self::normalize($db->query("SELECT * FROM livestock_pig_groups WHERE status='active'")->fetchAll(PDO::FETCH_ASSOC))
                : self::defaults();
        }
        return self::$cache[$key];
    }

    /** Every group, including inactive ones still carried by lots awaiting reclassification. */
    public static function catalog(PDO $db): array
    {
        return self::installed($db) ? self::normalize($db->query('SELECT * FROM livestock_pig_groups')->fetchAll(PDO::FETCH_ASSOC)) : self::defaults();
    }

    public static function forget(): void
    {
        self::$cache = [];
    }

    /** Active groups first, by entry age; each active group ends where the next one starts. */
    public static function normalize(array $groups): array
    {
        foreach ($groups as &$group) {
            foreach (['min_age_months','accepts_births','allows_breeding','allows_slaughter'] as $field) $group[$field] = (int)$group[$field];
            $group['status'] = $group['status'] ?? 'active';
        }
        unset($group);
        usort($groups, static function ($a, $b) {
            return [$a['status'] !== 'active', $a['min_age_months'], $a['code']] <=> [$b['status'] !== 'active', $b['min_age_months'], $b['code']];
        });
        foreach ($groups as $index => &$group) {
            $next = $groups[$index + 1] ?? null;
            $group['max_age_months'] = $group['status'] === 'active' && $next && $next['status'] === 'active' ? $next['min_age_months'] : null;
            $group['age_label'] = self::ageLabel($group);
        }
        unset($group);
        return $groups;
    }

    public static function ageLabel(array $group): string
    {
        $min = (int)$group['min_age_months'];
        $max = $group['max_age_months'] ?? null;
        if ($max === null) return $min === 0 ? 'Tous les âges' : 'Dès '.$min.' mois';
        return $min === 0 ? 'Moins de '.$max.' mois' : 'De '.$min.' à moins de '.$max.' mois';
    }

    /** Calendar months, clamped to the last day of the destination month. */
    public static function entryDate(DateTimeImmutable $birth, int $months): DateTimeImmutable
    {
        $month = $birth->modify('first day of this month')->modify('+'.$months.' months');
        return $month->setDate((int)$month->format('Y'), (int)$month->format('m'), min((int)$birth->format('d'), (int)$month->format('t')));
    }

    /** The oldest active group whose entry age has been reached. */
    public static function classify(DateTimeImmutable $birth, DateTimeImmutable $today, array $groups): array
    {
        $found = null;
        foreach ($groups as $group) {
            if ($group['status'] !== 'active' || $today < self::entryDate($birth, (int)$group['min_age_months'])) continue;
            if ($found === null || $group['min_age_months'] > $found['min_age_months']) $found = $group;
        }
        if ($found === null) throw new RuntimeException('Aucun groupe actif ne couvre cet âge. Vérifiez le référentiel des groupes.');
        return $found;
    }

    public static function byCode(array $groups, ?string $code): ?array
    {
        foreach ($groups as $group) if ($group['code'] === $code) return $group;
        return null;
    }

    public static function allows(array $groups, ?string $code, string $capability): bool
    {
        $group = self::byCode($groups, $code);
        return $group !== null && $group['status'] === 'active' && !empty($group[$capability]);
    }

    /** Readable list for messages: « groupe C » or « groupes C ou D ». */
    public static function names(array $groups, string $capability): string
    {
        $codes = [];
        foreach ($groups as $group) if ($group['status'] === 'active' && !empty($group[$capability])) $codes[] = $group['code'];
        if (!$codes) return 'aucun groupe actif';
        $last = array_pop($codes);
        return $codes ? 'groupes '.implode(', ', $codes).' ou '.$last : 'groupe '.$last;
    }

    /** Rules that keep every lot classifiable and every step of the porcine flow reachable. */
    public static function checks(array $groups): array
    {
        $active = array_values(array_filter($groups, static function ($group) { return $group['status'] === 'active'; }));
        $first = null;
        $ages = [];
        foreach ($active as $group) {
            if ((int)$group['min_age_months'] === 0) $first = $group;
            $ages[(int)$group['min_age_months']][] = $group['code'];
        }
        $duplicates = array_keys(array_filter($ages, static function ($codes) { return count($codes) > 1; }));
        $with = static function ($capability) use ($active) {
            return array_column(array_filter($active, static function ($group) use ($capability) { return !empty($group[$capability]); }), 'code');
        };
        $list = static function (array $codes) { return (count($codes) > 1 ? 'Groupes ' : 'Groupe ').implode(', ', $codes).'.'; };
        $breeding = $with('allows_breeding');
        $slaughter = $with('allows_slaughter');
        return [
            'start' => ['ok'=>$first !== null, 'label'=>'Un groupe dès la naissance', 'detail'=>$first ? 'Groupe '.$first['code'].', à partir de 0 mois.' : 'Un groupe actif doit commencer à 0 mois.'],
            'distinct' => ['ok'=>!$duplicates, 'label'=>'Des tranches d’âge distinctes', 'detail'=>$duplicates ? 'Plusieurs groupes actifs commencent à '.implode(', ', $duplicates).' mois.' : 'Chaque groupe commence à un âge différent.'],
            'births' => ['ok'=>$first !== null && !empty($first['accepts_births']), 'label'=>'Naissances accueillies', 'detail'=>$first !== null && !empty($first['accepts_births']) ? 'Les nouveau-nés rejoignent le groupe '.$first['code'].'.' : 'Le groupe qui commence à 0 mois doit accueillir les naissances.'],
            'breeding' => ['ok'=>(bool)$breeding, 'label'=>'Reproduction possible', 'detail'=>$breeding ? $list($breeding) : 'Au moins un groupe actif doit permettre la reproduction.'],
            'slaughter' => ['ok'=>(bool)$slaughter, 'label'=>'Abattage possible', 'detail'=>$slaughter ? $list($slaughter) : 'Au moins un groupe actif doit permettre l’abattage.'],
        ];
    }

    /** Next free letter after the highest one in use (A, B, C → D), then earlier gaps, then G1, G2… */
    public static function suggestCode(array $groups): string
    {
        $used = [];
        foreach ($groups as $group) $used[strtoupper((string)$group['code'])] = true;
        $letters = array_filter(array_map('strval', array_keys($used)), static function ($code) { return (bool)preg_match('/^[A-Z]$/D', $code); });
        $start = $letters ? ord(max($letters)) + 1 : ord('A');
        foreach (array_merge(range($start, ord('Z')), range(ord('A'), $start - 1)) as $letter) {
            if ($letter >= ord('A') && $letter <= ord('Z') && !isset($used[chr($letter)])) return chr($letter);
        }
        for ($number = 1; isset($used['G'.$number]); $number++);
        return 'G'.$number;
    }

    public static function version(array $group): string
    {
        $values = [];
        foreach (['id','code','name','description','min_age_months','accepts_births','allows_breeding','allows_slaughter','illustration','status','updated_at'] as $key) $values[$key] = (string)($group[$key] ?? '');
        return hash('sha256', json_encode($values));
    }

    public static function impactMessage(array $impact): string
    {
        if (!$impact['lots']) return 'Aucun lot existant ne change de groupe attendu.';
        return $impact['lots'].' lot(s) ('.$impact['heads'].' porcs) changent de groupe attendu : ils apparaissent dans « Passages à vérifier ».';
    }

    /** Referential page: groups with their usage in the visible scope, coherence and recent changes. */
    public function directory(): array
    {
        $installed = self::installed($this->db);
        $groups = $installed ? self::normalize($this->query("SELECT g.*,u.name editor_name,
                (SELECT COUNT(*) FROM livestock_pig_profiles p WHERE p.confirmed_group=g.code)
                + (SELECT COUNT(*) FROM livestock_pig_events e WHERE e.new_group=g.code OR e.old_group=g.code) reference_count
            FROM livestock_pig_groups g LEFT JOIN users u ON u.id=COALESCE(g.updated_by,g.created_by)")->fetchAll(PDO::FETCH_ASSOC)) : self::defaults();
        $active = array_values(array_filter($groups, static function ($group) { return $group['status'] === 'active'; }));
        $params = [];
        $scope = Auth::siteClause('b.site_id', $params);
        $lots = $this->query("SELECT p.born_on,p.confirmed_group,b.current_heads FROM livestock_pig_profiles p JOIN livestock_batches b ON b.id=p.batch_id
            WHERE b.status='active' AND b.current_heads>0 AND b.deleted_at IS NULL{$scope}", $params)->fetchAll(PDO::FETCH_ASSOC);
        $today = new DateTimeImmutable('today');
        $usage = [];
        foreach ($lots as $lot) {
            $code = $lot['confirmed_group'];
            $usage[$code] = $usage[$code] ?? ['lots'=>0,'heads'=>0,'pending'=>0];
            $usage[$code]['lots']++;
            $usage[$code]['heads'] += (int)$lot['current_heads'];
            if ($this->expected($lot['born_on'], $today, $active) !== $code) $usage[$code]['pending']++;
        }
        foreach ($groups as &$group) {
            $group['usage'] = $usage[$group['code']] ?? ['lots'=>0,'heads'=>0,'pending'=>0];
            $group['reference_count'] = (int)($group['reference_count'] ?? 0);
            $group['version'] = self::version($group);
        }
        unset($group);
        $history = $installed ? $this->query("SELECT l.action,l.description,l.created_at,u.name user_name FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id
            WHERE l.entity_type='livestock_pig_groups' ORDER BY l.id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC) : [];
        return [
            'installed' => $installed,
            'groups' => $groups,
            'checks' => self::checks($groups),
            'lots' => array_map(static function ($lot) { return [$lot['born_on'], $lot['confirmed_group'], (int)$lot['current_heads']]; }, $lots),
            'history' => $history,
            'canAdminister' => $installed && $this->canAdminister(),
            'suggestedCode' => self::suggestCode($groups),
        ];
    }

    /** The referential applies to both farms: editing requires livestock administration on each. */
    public function canAdminister(): bool
    {
        $farms = $this->farms();
        foreach ($farms as $site) if (!Auth::can('livestock', 'administer', $site)) return false;
        return (bool)$farms;
    }

    public function create(array $data, array $user): array
    {
        $this->authorize();
        $values = $this->input($data, null);
        return $this->transaction(function (array $all) use ($values, $user) {
            // An empty code is generated under the referential lock, so concurrent creations stay unique.
            if ($values['code'] === null) $values['code'] = self::suggestCode($all);
            $existing = self::byCode($all, $values['code']);
            if ($existing) throw new RuntimeException('Le code '.$values['code'].' existe déjà'.($existing['status'] === 'active' ? '.' : ' dans les groupes désactivés : réactivez-le plutôt.'));
            $candidate = array_merge($all, [$values + ['id'=>0,'status'=>'active']]);
            $this->assertCoherent($candidate);
            $this->query('INSERT INTO livestock_pig_groups(code,name,description,min_age_months,accepts_births,allows_breeding,allows_slaughter,illustration,status,created_by,updated_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
                [$values['code'],$values['name'],$values['description'],$values['min_age_months'],$values['accepts_births'],$values['allows_breeding'],$values['allows_slaughter'],$values['illustration'],'active',$user['id'],$user['id']]);
            $id = (int)$this->db->lastInsertId();
            $impact = $this->impact($all, $candidate);
            $this->logActivity('pig_group_create', 'livestock', 'livestock_pig_groups', $id, 'Création du groupe '.$values['code'].' · '.$values['name'].'.', null, $values + ['impact'=>$impact], $user);
            return $impact + ['code'=>$values['code']];
        });
    }

    public function update($id, array $data, array $user): array
    {
        $this->authorize();
        return $this->transaction(function (array $all) use ($id, $data, $user) {
            $current = $this->current($all, $id, $data);
            $values = $this->input($data, $current);
            $changed = array_filter($values, static function ($value, $key) use ($current) { return (string)$value !== (string)$current[$key]; }, ARRAY_FILTER_USE_BOTH);
            if (!$changed) throw new RuntimeException('Aucune modification à enregistrer.');
            $candidate = $this->replace($all, $current, $values);
            $this->assertCoherent($candidate);
            $this->query('UPDATE livestock_pig_groups SET name=?,description=?,min_age_months=?,accepts_births=?,allows_breeding=?,allows_slaughter=?,illustration=?,updated_by=? WHERE id=?',
                [$values['name'],$values['description'],$values['min_age_months'],$values['accepts_births'],$values['allows_breeding'],$values['allows_slaughter'],$values['illustration'],$user['id'],$current['id']]);
            $impact = $this->impact($all, $candidate);
            $this->logActivity('pig_group_update', 'livestock', 'livestock_pig_groups', $current['id'], 'Modification du groupe '.$current['code'].'.', array_intersect_key($current, $changed), $changed + ['impact'=>$impact], $user);
            return $impact;
        });
    }

    public function setStatus($id, array $data, array $user): array
    {
        $this->authorize();
        $status = $data['status'] ?? '';
        if (!in_array($status, ['active','inactive'], true)) throw new RuntimeException('Statut invalide.');
        return $this->transaction(function (array $all) use ($id, $data, $user, $status) {
            $current = $this->current($all, $id, $data);
            if ($current['status'] === $status) throw new RuntimeException($status === 'active' ? 'Ce groupe est déjà actif.' : 'Ce groupe est déjà désactivé.');
            $candidate = $this->replace($all, $current, ['status'=>$status]);
            $this->assertCoherent($candidate);
            $this->query('UPDATE livestock_pig_groups SET status=?,updated_by=? WHERE id=?', [$status,$user['id'],$current['id']]);
            $impact = $this->impact($all, $candidate);
            $this->logActivity($status === 'active' ? 'pig_group_activate' : 'pig_group_deactivate', 'livestock', 'livestock_pig_groups', $current['id'], ($status === 'active' ? 'Réactivation' : 'Désactivation').' du groupe '.$current['code'].'.', ['status'=>$current['status']], ['status'=>$status,'impact'=>$impact], $user);
            return $impact;
        });
    }

    public function delete($id, array $data, array $user): void
    {
        $this->authorize();
        $this->transaction(function (array $all) use ($id, $data, $user) {
            $current = $this->current($all, $id, $data);
            $references = (int)$this->query('SELECT (SELECT COUNT(*) FROM livestock_pig_profiles WHERE confirmed_group=?) + (SELECT COUNT(*) FROM livestock_pig_events WHERE new_group=? OR old_group=?)', [$current['code'],$current['code'],$current['code']])->fetchColumn();
            if ($references) throw new RuntimeException('Le groupe '.$current['code'].' figure dans l’historique des lots : désactivez-le plutôt que de le supprimer.');
            $this->assertCoherent(array_values(array_filter($all, static function ($group) use ($current) { return $group['id'] !== $current['id']; })));
            $this->query('DELETE FROM livestock_pig_groups WHERE id=?', [$current['id']]);
            $this->logActivity('pig_group_delete', 'livestock', 'livestock_pig_groups', $current['id'], 'Suppression du groupe '.$current['code'].' jamais utilisé.', array_diff_key($current, ['age_label'=>1,'max_age_months'=>1]), null, $user);
        });
    }

    private function farms(): array
    {
        return array_map('intval', $this->query("SELECT id FROM sites WHERE code IN ('FARM-MUT','FARM-DIK') AND status='active' AND deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN));
    }

    private function authorize(): void
    {
        if (!self::installed($this->db)) throw new RuntimeException('Le référentiel des groupes n’est pas encore activé : appliquez la migration 041.');
        if (!$this->canAdminister()) throw new RuntimeException('Ces règles s’appliquent aux deux fermes : il faut le droit d’administrer l’élevage sur chacune d’elles.');
    }

    private function transaction(callable $write)
    {
        $this->db->beginTransaction();
        try {
            // Locking the whole referential serializes edits, so coherence is checked on the final set.
            $all = self::normalize($this->query('SELECT * FROM livestock_pig_groups FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC));
            foreach ($all as &$group) $group['id'] = (int)$group['id'];
            unset($group);
            $result = $write($all);
            $this->db->commit();
            self::forget();
            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function current(array $all, $id, array $data): array
    {
        foreach ($all as $group) {
            if ($group['id'] !== (int)$id) continue;
            if (!is_string($data['version'] ?? null) || !hash_equals(self::version($group), $data['version'])) throw new RuntimeException('Ce groupe a été modifié entre-temps. Actualisez la page avant de recommencer.');
            return $group;
        }
        throw new RuntimeException('Groupe introuvable.');
    }

    private function replace(array $all, array $current, array $values): array
    {
        foreach ($all as &$group) if ($group['id'] === $current['id']) $group = array_replace($group, $values);
        unset($group);
        return $all;
    }

    private function input(array $data, ?array $current): array
    {
        $code = $current ? $current['code'] : strtoupper(trim(is_string($data['code'] ?? null) ? $data['code'] : ''));
        if ($code === '' && !$current) $code = null;
        elseif (!preg_match('/^[A-Z0-9][A-Z0-9-]{0,9}$/D', $code)) throw new RuntimeException('Code : de 1 à 10 caractères (lettres, chiffres ou tirets).');
        $name = $data['name'] ?? null;
        if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 80) throw new RuntimeException('Libellé : saisissez de 1 à 80 caractères.');
        $description = $data['description'] ?? '';
        if (!is_string($description) || mb_strlen(trim($description)) > 255) throw new RuntimeException('Description : 255 caractères au maximum.');
        $age = $data['min_age_months'] ?? '';
        if (!is_scalar($age) || !preg_match('/^\d{1,3}$/D', (string)$age) || (int)$age > self::MAX_AGE_MONTHS) throw new RuntimeException('Âge d’entrée : nombre entier de mois, de 0 à '.self::MAX_AGE_MONTHS.'.');
        $illustration = $data['illustration'] ?? '';
        if (!is_string($illustration) || !isset(self::ILLUSTRATIONS[$illustration])) throw new RuntimeException('Choisissez une illustration.');
        $values = ['code'=>$code,'name'=>trim($name),'description'=>trim($description) === '' ? null : trim($description),'min_age_months'=>(int)$age,'illustration'=>$illustration];
        foreach (array_keys(self::CAPABILITIES) as $capability) $values[$capability] = ($data[$capability] ?? '') === '1' ? 1 : 0;
        return $values;
    }

    private function assertCoherent(array $groups): void
    {
        $groups = self::normalize($groups);
        $active = array_filter($groups, static function ($group) { return $group['status'] === 'active'; });
        if (count($active) > self::MAX_ACTIVE) throw new RuntimeException('Au plus '.self::MAX_ACTIVE.' groupes actifs.');
        foreach (self::checks($groups) as $check) if (!$check['ok']) throw new RuntimeException($check['detail']);
    }

    /** Lots whose expected group changes, across both farms, with the pending total afterwards. */
    private function impact(array $before, array $after): array
    {
        $before = array_values(array_filter(self::normalize($before), static function ($group) { return $group['status'] === 'active'; }));
        $after = array_values(array_filter(self::normalize($after), static function ($group) { return $group['status'] === 'active'; }));
        $today = new DateTimeImmutable('today');
        $impact = ['lots'=>0,'heads'=>0];
        foreach ($this->query("SELECT p.born_on,b.current_heads FROM livestock_pig_profiles p JOIN livestock_batches b ON b.id=p.batch_id WHERE b.status='active' AND b.current_heads>0 AND b.deleted_at IS NULL")->fetchAll(PDO::FETCH_ASSOC) as $lot) {
            if ($this->expected($lot['born_on'], $today, $before) === $this->expected($lot['born_on'], $today, $after)) continue;
            $impact['lots']++;
            $impact['heads'] += (int)$lot['current_heads'];
        }
        return $impact;
    }

    private function expected(string $bornOn, DateTimeImmutable $today, array $groups): ?string
    {
        $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $bornOn);
        if (!$birth || $birth > $today) return null;
        try {
            return self::classify($birth, $today, $groups)['code'];
        } catch (RuntimeException $e) {
            return null;
        }
    }
}
