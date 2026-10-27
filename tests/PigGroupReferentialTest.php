<?php
/** Isolated tests of the pig age-group referential and of the rules that now depend on it.
 * SQLite executes production SQL except row-lock syntax. */
require_once __DIR__.'/../app/core/Model.php';
require_once __DIR__.'/../app/services/PigService.php';

class Auth
{
    public static $site = 1;
    public static $admin = true;
    public static $permissions = ['read','create','update','validate'];
    public static function currentSiteId() { return self::$site; }
    public static function requireSiteAccess($id) { if (!in_array((int)$id,[1,2],true)) throw new RuntimeException('Site refusé.'); }
    public static function requirePermission($component,$action,$id) { if (!in_array($action,self::$permissions,true)) throw new RuntimeException('Permission refusée.'); }
    public static function can($component,$action,$id = null) { return $action === 'administer' ? self::$admin : in_array($action,self::$permissions,true); }
    public static function siteClause($column,&$params) { if (self::$site===null) return ''; $params['scope']=self::$site; return " AND $column=:scope"; }
}
class PigTestDatabase extends PDO
{
    public function prepare($query, $options = []): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); }
}
$db = new PigTestDatabase('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec(<<<'SQL'
CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT);
CREATE TABLE sites(id INTEGER PRIMARY KEY,code TEXT,name TEXT,status TEXT,deleted_at TEXT);
CREATE TABLE livestock_species(id INTEGER PRIMARY KEY,code TEXT,status TEXT,deleted_at TEXT);
CREATE TABLE livestock_facilities(id INTEGER PRIMARY KEY,site_id INTEGER,name TEXT,facility_type TEXT,status TEXT,capacity_heads INTEGER,deleted_at TEXT);
CREATE TABLE livestock_batches(id INTEGER PRIMARY KEY AUTOINCREMENT,site_id INTEGER,species_id INTEGER,facility_id INTEGER,batch_number TEXT UNIQUE,purpose TEXT,started_at TEXT,initial_heads INTEGER,current_heads INTEGER,initial_average_weight_kg REAL,current_average_weight_kg REAL,status TEXT,created_by INTEGER,deleted_at TEXT);
CREATE TABLE livestock_pig_groups(id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT NOT NULL UNIQUE,name TEXT NOT NULL,description TEXT,min_age_months INTEGER NOT NULL,accepts_births INTEGER NOT NULL DEFAULT 0,allows_breeding INTEGER NOT NULL DEFAULT 0,allows_slaughter INTEGER NOT NULL DEFAULT 0,illustration TEXT NOT NULL DEFAULT 'young-pig',status TEXT NOT NULL DEFAULT 'active',created_by INTEGER,updated_by INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE livestock_pig_profiles(batch_id INTEGER PRIMARY KEY REFERENCES livestock_batches(id),sex TEXT,born_on TEXT,confirmed_group TEXT REFERENCES livestock_pig_groups(code),mother_batch_id INTEGER,source_batch_id INTEGER,submission_key TEXT UNIQUE,confirmed_by INTEGER,confirmed_at TEXT);
CREATE TABLE livestock_pig_events(id INTEGER PRIMARY KEY AUTOINCREMENT,batch_id INTEGER REFERENCES livestock_batches(id),event_type TEXT,old_group TEXT REFERENCES livestock_pig_groups(code),new_group TEXT REFERENCES livestock_pig_groups(code),quantity_heads INTEGER,reason TEXT,created_by INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE livestock_head_movements(id INTEGER PRIMARY KEY AUTOINCREMENT,batch_id INTEGER,movement_type TEXT,quantity_heads INTEGER,heads_before INTEGER,heads_after INTEGER,reference_type TEXT,reference_id INTEGER,movement_at TEXT,created_by INTEGER,UNIQUE(batch_id,movement_type,reference_type,reference_id));
CREATE TABLE livestock_conversions(id INTEGER PRIMARY KEY,batch_id INTEGER,status TEXT);
CREATE TABLE livestock_transfers(id INTEGER PRIMARY KEY,batch_id INTEGER,source_unit TEXT,status TEXT);
CREATE TABLE activity_logs(id INTEGER PRIMARY KEY,user_id INTEGER,site_id INTEGER,action TEXT,module TEXT,entity_type TEXT,entity_id INTEGER,description TEXT,old_values TEXT,new_values TEXT,ip_address TEXT,user_agent TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
INSERT INTO users VALUES(1,'Admin test');
INSERT INTO sites VALUES(1,'FARM-MUT','Mutala','active',NULL),(2,'FARM-DIK','Dikapa','active',NULL),(3,'BOUCH','Boucherie','active',NULL);
INSERT INTO livestock_species VALUES(1,'PORC','active',NULL);
INSERT INTO livestock_facilities VALUES(1,1,'Porcherie','housing','active',500,NULL);
INSERT INTO livestock_pig_groups(code,name,description,min_age_months,accepts_births,allows_breeding,allows_slaughter,illustration) VALUES
('A','Porcelets','Naissances et premiers mois à la ferme.',0,1,0,0,'piglet'),
('B','Porcs en croissance','Croissance avant la maturité.',2,0,0,0,'young-pig'),
('C','Porcs adultes','Destinés à l’abattage ou à la reproduction.',3,0,1,1,'adult-pig');
SQL);
class AvailablePigService extends PigService { public function available(): bool { return true; } }
$pigs = new AvailablePigService($db);
$referential = new PigGroupService($db);
$user = ['id'=>1,'role_slug'=>'administrateur'];
$checks = 0;
function check($condition,$message) { global $checks; if (!$condition) throw new RuntimeException('ÉCHEC : '.$message); $checks++; echo "OK : $message\n"; }
function rejected($callback,$message,$expected = null) { try { $callback(); } catch (RuntimeException $e) { check($expected === null || strpos($e->getMessage(),$expected) !== false,$message.' · « '.$e->getMessage().' »'); return; } throw new RuntimeException('ÉCHEC : '.$message); }
function group($code) { global $referential; foreach ($referential->directory()['groups'] as $g) if ($g['code']===$code) return $g; throw new RuntimeException('Groupe absent : '.$code); }
function lot($id) { global $pigs; foreach ($pigs->directory() as $r) if ((int)$r['id']===$id) return $r; throw new RuntimeException('Lot absent'); }
function fields($code,$patch=[]) { $g=group($code); return array_replace(['version'=>$g['version'],'name'=>$g['name'],'description'=>$g['description']??'','min_age_months'=>(string)$g['min_age_months'],'illustration'=>$g['illustration'],'accepts_births'=>$g['accepts_births']?'1':'','allows_breeding'=>$g['allows_breeding']?'1':'','allows_slaughter'=>$g['allows_slaughter']?'1':''],$patch); }
function pigs($bornOn,$patch=[]) { global $pigs,$user; return $pigs->register(array_replace(['site_id'=>'1','facility_id'=>'1','origin'=>'entry','sex'=>'female','born_on'=>$bornOn,'heads'=>'10','submission_key'=>bin2hex(random_bytes(16))],$patch),$user); }
$ago = static function ($modifier) { return (new DateTimeImmutable('today'))->modify($modifier)->format('Y-m-d'); };

// Classification with an arbitrary referential, same calendar rule as before.
$custom = PigGroupService::normalize([
    ['code'=>'A','name'=>'Porcelets','min_age_months'=>0,'accepts_births'=>1,'allows_breeding'=>0,'allows_slaughter'=>0],
    ['code'=>'D','name'=>'Réforme','min_age_months'=>6,'accepts_births'=>0,'allows_breeding'=>0,'allows_slaughter'=>1],
    ['code'=>'C','name'=>'Adultes','min_age_months'=>3,'accepts_births'=>0,'allows_breeding'=>1,'allows_slaughter'=>1],
    ['code'=>'X','name'=>'Ancien','min_age_months'=>1,'accepts_births'=>0,'allows_breeding'=>0,'allows_slaughter'=>0,'status'=>'inactive'],
]);
check(array_column($custom,'code')===['A','C','D','X'],'Ordre : groupes actifs par âge, puis désactivés');
check($custom[0]['age_label']==='Moins de 3 mois'&&$custom[1]['age_label']==='De 3 à moins de 6 mois'&&$custom[2]['age_label']==='Dès 6 mois','Tranches déduites de l’âge d’entrée du groupe suivant');
foreach ([['2026-01-15','2026-02-20','A'],['2026-01-15','2026-04-14','A'],['2026-01-15','2026-04-15','C'],['2026-01-15','2026-07-14','C'],['2026-01-15','2026-07-15','D'],['2025-08-31','2026-02-28','D']] as [$birth,$today,$code])
    check(PigService::expectedGroup($birth,new DateTimeImmutable($today),$custom)===$code,"Référentiel personnalisé : $birth → $today = $code (groupe désactivé ignoré)");
check(PigService::expectedGroup('2026-01-15',new DateTimeImmutable('2026-03-15'))==='B','Sans référentiel fourni : règles historiques A/B/C');
$codes = static function (...$codes) { return PigGroupService::suggestCode(array_map(static function ($code) { return ['code'=>$code]; }, $codes)); };
check($codes('A','B','C')==='D'&&$codes('A','B','C','D')==='E'&&$codes()==='A','Code proposé : lettre suivant la plus haute utilisée');
check($codes('A','C')==='D'&&$codes('A','Z')==='B'&&$codes(...range('A','Z'))==='G1'&&$codes(...array_merge(range('A','Z'),['G1']))==='G2','Code proposé : trous comblés après Z, puis G1, G2');
check(PigGroupService::names($custom,'allows_slaughter')==='groupes C ou D'&&PigGroupService::names($custom,'allows_breeding')==='groupe C','Messages : liste lisible des groupes autorisés');

// Coherence rules.
$ok = static function ($groups) { return array_map(static function ($c) { return $c['ok']; }, PigGroupService::checks(PigGroupService::normalize($groups))); };
$base = PigGroupService::defaults();
check(!in_array(false,$ok($base),true),'Règles historiques cohérentes');
$broken = static function ($patch) use ($base) { $groups=$base; foreach ($patch as $i=>$values) $groups[$i]=array_replace($groups[$i],$values); return PigGroupService::checks(PigGroupService::normalize($groups)); };
check(!$broken([0=>['min_age_months'=>1]])['start']['ok'],'Contrôle : un groupe doit commencer à 0 mois');
check(!$broken([1=>['min_age_months'=>3]])['distinct']['ok'],'Contrôle : deux groupes ne peuvent pas commencer au même âge');
check(!$broken([0=>['accepts_births'=>0]])['births']['ok'],'Contrôle : le premier groupe accueille les naissances');
check(!$broken([2=>['allows_slaughter'=>0]])['slaughter']['ok']&&!$broken([2=>['allows_breeding'=>0]])['breeding']['ok'],'Contrôle : abattage et reproduction restent possibles');

// Directory and permissions.
$young = pigs($ago('-2 months -10 days'));
$adult = pigs($ago('-4 months'));
$old = pigs($ago('-8 months'),['sex'=>'male','heads'=>'5']);
$directory = $referential->directory();
check($directory['installed']&&$directory['canAdminister']&&count($directory['groups'])===3&&$directory['suggestedCode']==='D','Référentiel installé, administrable, trois groupes');
check(group('C')['usage']==['lots'=>2,'heads'=>15,'pending'=>0]&&group('B')['usage']['lots']===1,'Compteurs par groupe dans le périmètre');
check(group('A')['reference_count']===0&&group('B')['reference_count']>0&&group('C')['reference_count']>0,'Références historiques comptées (A encore jamais utilisé)');
Auth::$admin = false;
check(!$referential->directory()['canAdminister'],'Lecture seule sans droit d’administration');
rejected(fn()=>$referential->create(['code'=>'D','name'=>'Réforme','min_age_months'=>'6','illustration'=>'adult-pig','allows_slaughter'=>'1'],$user),'Création refusée sans administration des deux fermes','deux fermes');
Auth::$admin = true;

// Creating a group changes expected groups, never confirmed ones.
rejected(fn()=>$referential->create(['code'=>'c','name'=>'Doublon','min_age_months'=>'9','illustration'=>'adult-pig'],$user),'Code existant refusé, même en minuscules','existe déjà');
rejected(fn()=>$referential->create(['code'=>'É!','name'=>'Code invalide','min_age_months'=>'6','illustration'=>'adult-pig'],$user),'Code invalide refusé','Code');
rejected(fn()=>$referential->create(['code'=>'D','name'=>'Trop vieux','min_age_months'=>'121','illustration'=>'adult-pig'],$user),'Âge d’entrée hors limites refusé','Âge');
rejected(fn()=>$referential->create(['code'=>'D','name'=>'Même âge','min_age_months'=>'3','illustration'=>'adult-pig'],$user),'Âge d’entrée déjà utilisé refusé','commencent à 3 mois');
rejected(fn()=>$referential->create(['code'=>'D','name'=>'Image','min_age_months'=>'6','illustration'=>'../x'],$user),'Illustration inconnue refusée','illustration');
rejected(fn()=>$referential->create(['code'=>'D','name'=>str_repeat('x',81),'min_age_months'=>'6','illustration'=>'adult-pig'],$user),'Libellé trop long refusé','Libellé');
$impact = $referential->create(['code'=>'d','name'=>'Porcs de réforme','min_age_months'=>'6','illustration'=>'adult-pig','allows_slaughter'=>'1'],$user);
check($impact===['lots'=>1,'heads'=>5,'code'=>'D'],'Création de D : impact calculé (1 lot de 5 porcs)');
check(PigGroupService::impactMessage($impact)==='1 lot(s) (5 porcs) changent de groupe attendu : ils apparaissent dans « Passages à vérifier ».','Message d’impact lisible');
$r = lot($old);
check($r['confirmed_group']==='C'&&$r['expected_group']==='D'&&$r['pending']&&!$r['can_slaughter'],'Lot de 8 mois : C conservé, D attendu, abattage suspendu jusqu’à validation');
rejected(fn()=>$pigs->assertExitAllowed($old,'slaughter'),'Abattage refusé tant que le passage n’est pas validé','groupes C ou D');
$pigs->confirmGroup($old,['version'=>$r['version'],'target_group'=>'D','field_checked'=>'1'],$user);
check(lot($old)['confirmed_group']==='D'&&lot($old)['can_slaughter'],'Passage vers D validé sur le terrain : abattage possible');
$pigs->assertExitAllowed($old,'slaughter');
check(true,'Abattage autorisé par la capacité du groupe D');
rejected(fn()=>$pigs->orient($old,['version'=>lot($old)['version'],'heads'=>'1','purpose'=>'breeding','reason'=>'Essai'],$user),'Reproduction refusée dans un groupe qui ne l’autorise pas','reproduction');
check((int)$db->query("SELECT COUNT(*) FROM livestock_pig_events WHERE new_group='D'")->fetchColumn()===1,'Historique du lot avec le nouveau code');

// Updates: optimistic locking, immutable code, coherence.
$stale = fields('C');
$referential->update(group('C')['id'],fields('C',['name'=>'Porcs adultes et reproducteurs','code'=>'Z']),$user);
check(group('C')['name']==='Porcs adultes et reproducteurs','Libellé modifié, code inchangé malgré la saisie');
rejected(fn()=>$referential->update(group('C')['id'],array_replace($stale,['name'=>'Autre']),$user),'Version périmée refusée','modifié entre-temps');
rejected(fn()=>$referential->update(group('C')['id'],fields('C'),$user),'Enregistrement sans modification signalé','Aucune modification');
rejected(fn()=>$referential->update(group('A')['id'],fields('A',['accepts_births'=>'']),$user),'Premier groupe sans naissances refusé','doit accueillir les naissances');
rejected(fn()=>$referential->update(group('A')['id'],fields('A',['min_age_months'=>'1']),$user),'Plus aucun groupe dès la naissance refusé','commencer à 0 mois');
$impact = $referential->update(group('B')['id'],fields('B',['min_age_months'=>'1']),$user);
check($impact['lots']===0,'B avancé à 1 mois : aucun lot de ce périmètre ne change');
$referential->update(group('B')['id'],fields('B',['min_age_months'=>'2','accepts_births'=>'1']),$user);
check(PigGroupService::allows($pigs->groups(),'B','accepts_births'),'Cache actualisé après modification');

// Births follow the capability, not the letter.
$mothers = $pigs->orient($adult,['version'=>lot($adult)['version'],'heads'=>'4','purpose'=>'breeding','reason'=>'Sélection'],$user);
$late = pigs($ago('-2 months -5 days'),['origin'=>'birth','mother_batch_id'=>(string)$mothers,'sex'=>'male','heads'=>'3']);
check(lot($late)['confirmed_group']==='B'&&(int)lot($late)['mother_batch_id']===$mothers,'Naissance acceptée en B qui accueille désormais les naissances');
check(lot($mothers)['confirmed_group']==='C','Lot de reproductrices issu d’une sélection : groupe du lot d’origine conservé');
$referential->update(group('B')['id'],fields('B',['accepts_births'=>'']),$user);
rejected(fn()=>pigs($ago('-2 months -5 days'),['origin'=>'birth','mother_batch_id'=>(string)$mothers,'sex'=>'male','heads'=>'3']),'Naissance refusée dans un groupe sans cette capacité','groupe A');

// Status changes and deletion.
rejected(fn()=>$referential->setStatus(group('C')['id'],['version'=>group('C')['version'],'status'=>'inactive'],$user),'Désactivation du seul groupe de reproduction refusée','permettre la reproduction');
$impact = $referential->setStatus(group('B')['id'],['version'=>group('B')['version'],'status'=>'inactive'],$user);
check($impact['lots']===2&&lot($young)['expected_group']==='A'&&lot($young)['pending'],'B désactivé : ses lots deviennent à reclasser');
check(group('B')['status']==='inactive'&&group('A')['age_label']==='Moins de 3 mois','A couvre désormais jusqu’à 3 mois');
rejected(fn()=>$referential->create(['code'=>'B','name'=>'Doublon','min_age_months'=>'2','illustration'=>'young-pig'],$user),'Recréer un code désactivé : réactivation proposée','réactivez-le');
rejected(fn()=>$referential->delete(group('B')['id'],['version'=>group('B')['version']],$user),'Suppression d’un groupe utilisé refusée','désactivez-le');
$referential->setStatus(group('B')['id'],['version'=>group('B')['version'],'status'=>'active'],$user);
check(!lot($young)['pending'],'B réactivé : plus rien à reclasser');
rejected(fn()=>$referential->setStatus(group('B')['id'],['version'=>group('B')['version'],'status'=>'active'],$user),'Réactivation en double signalée','déjà actif');
check($referential->directory()['suggestedCode']==='E','Code proposé après D');
check($referential->create(['code'=>'  ','name'=>'Essai','min_age_months'=>'24','illustration'=>'pig-farm'],$user)['code']==='E','Code laissé vide : E attribué automatiquement');
$referential->delete(group('E')['id'],['version'=>group('E')['version']],$user);
check(!in_array('E',array_column($referential->directory()['groups'],'code'),true),'Groupe jamais utilisé supprimé');

// Audit trail and atomicity.
$logs = $db->query("SELECT action FROM activity_logs WHERE entity_type='livestock_pig_groups' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
check(in_array('pig_group_create',$logs,true)&&in_array('pig_group_update',$logs,true)&&in_array('pig_group_deactivate',$logs,true)&&in_array('pig_group_activate',$logs,true)&&in_array('pig_group_delete',$logs,true),'Chaque modification est auditée');
check(count($referential->directory()['history'])===8,'Historique récent affiché (8 dernières modifications)');
$before = $db->query('SELECT COUNT(*) FROM livestock_pig_groups')->fetchColumn();
$db->exec("CREATE TRIGGER fail_group_log BEFORE INSERT ON activity_logs BEGIN SELECT RAISE(ABORT,'Simulated storage error'); END");
try { $referential->create(['code'=>'F','name'=>'Panne','min_age_months'=>'30','illustration'=>'pig-farm'],$user); throw new LogicException('Erreur attendue'); } catch (PDOException $e) {}
check($db->query('SELECT COUNT(*) FROM livestock_pig_groups')->fetchColumn()===$before&&!$db->inTransaction(),'Erreur d’audit : création annulée');
echo "$checks vérifications réussies.\n";
