<?php
/** Isolated business tests. SQLite executes production SQL except row-lock syntax;
 * real MySQL concurrency must be checked separately against a disposable database. */
require_once __DIR__.'/../app/core/Model.php';
require_once __DIR__.'/../app/services/PigService.php';

class Auth
{
    public static $site = 1;
    public static $permissions = ['read','create','update','validate'];
    public static function currentSiteId() { return self::$site; }
    public static function requireSiteAccess($id) { if (!in_array((int)$id,[1,2],true)) throw new RuntimeException('Site refusé.'); }
    public static function requirePermission($component,$action,$id) { if (!in_array($action,self::$permissions,true)) throw new RuntimeException('Permission refusée.'); }
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
CREATE TABLE sites(id INTEGER PRIMARY KEY,code TEXT,name TEXT,status TEXT,deleted_at TEXT);
CREATE TABLE livestock_species(id INTEGER PRIMARY KEY,code TEXT,status TEXT,deleted_at TEXT);
CREATE TABLE livestock_facilities(id INTEGER PRIMARY KEY,site_id INTEGER,name TEXT,facility_type TEXT,status TEXT,capacity_heads INTEGER,deleted_at TEXT);
CREATE TABLE livestock_batches(id INTEGER PRIMARY KEY AUTOINCREMENT,site_id INTEGER,species_id INTEGER,facility_id INTEGER,batch_number TEXT UNIQUE,purpose TEXT,started_at TEXT,initial_heads INTEGER,current_heads INTEGER,initial_average_weight_kg REAL,current_average_weight_kg REAL,status TEXT,created_by INTEGER,deleted_at TEXT);
CREATE TABLE livestock_pig_profiles(batch_id INTEGER PRIMARY KEY REFERENCES livestock_batches(id),sex TEXT,born_on TEXT,confirmed_group TEXT,mother_batch_id INTEGER,source_batch_id INTEGER,submission_key TEXT UNIQUE,confirmed_by INTEGER,confirmed_at TEXT);
CREATE TABLE livestock_pig_events(id INTEGER PRIMARY KEY AUTOINCREMENT,batch_id INTEGER REFERENCES livestock_batches(id),event_type TEXT,old_group TEXT,new_group TEXT,quantity_heads INTEGER,reason TEXT,created_by INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE livestock_head_movements(id INTEGER PRIMARY KEY AUTOINCREMENT,batch_id INTEGER,movement_type TEXT,quantity_heads INTEGER,heads_before INTEGER,heads_after INTEGER,reference_type TEXT,reference_id INTEGER,movement_at TEXT,created_by INTEGER,UNIQUE(batch_id,movement_type,reference_type,reference_id));
CREATE TABLE livestock_conversions(id INTEGER PRIMARY KEY,batch_id INTEGER,status TEXT);
CREATE TABLE livestock_transfers(id INTEGER PRIMARY KEY,batch_id INTEGER,source_unit TEXT,status TEXT);
CREATE TABLE activity_logs(id INTEGER PRIMARY KEY,user_id INTEGER,site_id INTEGER,action TEXT,module TEXT,entity_type TEXT,entity_id INTEGER,description TEXT,old_values TEXT,new_values TEXT,ip_address TEXT,user_agent TEXT);
INSERT INTO sites VALUES(1,'FARM-MUT','Mutala','active',NULL),(2,'FARM-DIK','Dikapa','active',NULL),(3,'BOUCH','Boucherie','active',NULL);
INSERT INTO livestock_species VALUES(1,'PORC','active',NULL),(2,'BOVIN','active',NULL);
INSERT INTO livestock_facilities VALUES(1,1,'Porcherie','housing','active',200,NULL),(2,2,'Porcherie Dikapa','housing','active',200,NULL),(3,1,'Étang','pond','active',200,NULL);
SQL);
class AvailablePigService extends PigService { public function available(): bool { return true; } }
$service = new AvailablePigService($db);
$user = ['id'=>1,'role_slug'=>'administrateur'];
$checks = 0;
function check($condition,$message) { global $checks; if (!$condition) throw new RuntimeException('ÉCHEC : '.$message); $checks++; echo "OK : $message\n"; }
function rejected($callback,$message) { try { $callback(); } catch (RuntimeException $e) { check(true,$message); return; } throw new RuntimeException('ÉCHEC : '.$message); }
function row($id) { global $service; foreach ($service->directory() as $r) if ((int)$r['id']===$id) return $r; throw new RuntimeException('Lot absent'); }
function data($patch=[]) { return array_replace(['site_id'=>'1','facility_id'=>'1','origin'=>'entry','sex'=>'female','born_on'=>date('Y-m-d',strtotime('-5 months')),'heads'=>'20','submission_key'=>bin2hex(random_bytes(16))],$patch); }
function heads() { global $db; return (int)$db->query('SELECT SUM(current_heads) FROM livestock_batches')->fetchColumn(); }
foreach ([['2026-01-15','2026-03-14','A'],['2026-01-15','2026-03-15','B'],['2026-01-15','2026-04-14','B'],['2026-01-15','2026-04-15','C'],['2024-12-31','2025-02-28','B'],['2024-11-30','2025-02-28','C'],['2025-12-31','2026-02-27','A'],['2024-02-29','2025-02-28','C']] as [$birth,$today,$group]) check(PigService::expectedGroup($birth,new DateTimeImmutable($today))===$group,"Âge $birth → $today : $group");
rejected(fn()=>PigService::expectedGroup('2026-02-30'),'Date impossible refusée');
rejected(fn()=>PigService::expectedGroup('2027-01-01',new DateTimeImmutable('2026-01-01')),'Naissance future refusée');
$base=data();$id=$service->register($base,$user);check(heads()===20,'Entrée de 20 femelles');
check($service->register($base,$user)===$id&&heads()===20,'Double soumission sans double entrée');
check(row($id)['sex']==='female'&&row($id)['confirmed_group']==='C','Sexe et groupe initial conservés');
foreach ([['heads'=>'1.5'],['heads'=>'-2'],['sex'=>'other'],['born_on'=>'2026-02-30'],['facility_id'=>'2'],['facility_id'=>'3'],['site_id'=>'3'],['heads'=>'201']] as $bad) rejected(fn()=>$service->register(data($bad),$user),'Entrée invalide refusée : '.json_encode($bad));
check(heads()===20,'Refus sans effet partiel');
$stale=row($id)['version'];
$selected=$service->orient($id,['version'=>$stale,'heads'=>'6','purpose'=>'breeding','reason'=>'Sélection terrain'],$user);
check($selected!==$id&&heads()===20&&row($id)['current_heads']==14&&row($selected)['current_heads']==6,'Sélection partielle : 14 + 6, total conservé');
check(row($selected)['purpose']==='breeding'&&row($selected)['source_batch_id']===$id,'Reproductrices et origine conservées');
rejected(fn()=>$service->orient($id,['version'=>$stale,'heads'=>'6','purpose'=>'breeding','reason'=>'Double clic'],$user),'Version périmée refusée');
$birth=$service->register(data(['origin'=>'birth','mother_batch_id'=>$selected,'born_on'=>date('Y-m-d'),'sex'=>'male','heads'=>'4']),$user);
check(row($birth)['confirmed_group']==='A'&&row($birth)['mother_batch_id']===$selected&&row($selected)['current_heads']==6,'Naissance en A sans déduction des mères');
rejected(fn()=>$service->register(data(['origin'=>'birth','mother_batch_id'=>$id,'born_on'=>date('Y-m-d')]),$user),'Mères non reproductrices refusées');
rejected(fn()=>$service->orient($birth,['version'=>row($birth)['version'],'heads'=>'1','purpose'=>'breeding','reason'=>'Trop jeune'],$user),'Reproduction avant C refusée');
$service->assertExitAllowed($selected,'slaughter');
check(true,'Reproductrices C abattables');
rejected(fn()=>$service->assertExitAllowed($birth,'slaughter'),'Abattage avant C refusé');
rejected(fn()=>$service->assertExitAllowed($selected,'live_transfer'),'Transfert de porcs vivants vers la boucherie refusé');
$turned=$service->orient($selected,['version'=>row($selected)['version'],'heads'=>'2','purpose'=>'fattening','reason'=>'Réforme pour abattage'],$user);
check(heads()===24&&row($selected)['current_heads']==4&&row($turned)['purpose']==='fattening','Reproductrices réorientables vers abattage');
$service->orient($selected,['version'=>row($selected)['version'],'heads'=>'4','purpose'=>'fattening','reason'=>'Réforme du reste'],$user);
check(heads()===24&&row($selected)['purpose']==='fattening','Orientation totale sans création d’animaux');
$young=$service->register(data(['born_on'=>date('Y-m-d',strtotime('-2 months')),'heads'=>'3']),$user);
$db->exec("UPDATE livestock_pig_profiles SET confirmed_group='A' WHERE batch_id=".$young);
$r=row($young);check($r['pending']&&$r['confirmed_group']==='A'&&$r['expected_group']==='B','Âge détecté automatiquement, groupe validé conservé');
rejected(fn()=>$service->confirmGroup($young,['version'=>$r['version'],'target_group'=>'B'],$user),'Vérification terrain obligatoire');
rejected(fn()=>$service->confirmGroup($young,['version'=>$r['version'],'target_group'=>'C','field_checked'=>'1'],$user),'Groupe cible falsifié refusé');
Auth::$permissions=['read','create','update'];rejected(fn()=>$service->confirmGroup($young,['version'=>$r['version'],'target_group'=>'B','field_checked'=>'1'],$user),'Validation sans permission refusée');Auth::$permissions[]='validate';
$service->confirmGroup($young,['version'=>$r['version'],'target_group'=>'B','field_checked'=>'1'],$user);
check(row($young)['confirmed_group']==='B'&&!row($young)['pending'],'Validation terrain retire la notification');
rejected(fn()=>$service->confirmGroup($young,['version'=>$r['version'],'target_group'=>'B','field_checked'=>'1'],$user),'Double validation refusée');
Auth::$site=2;check(count($service->directory())===0,'Lecture limitée au site courant');
rejected(fn()=>$service->orient($id,['version'=>$stale,'heads'=>'1','purpose'=>'breeding','reason'=>'Autre site'],$user),'Écriture hors contexte refusée');
Auth::$site=1;
$db->exec("INSERT INTO livestock_conversions VALUES(1,$id,'submitted')");
rejected(fn()=>$service->orient($id,['version'=>row($id)['version'],'heads'=>'1','purpose'=>'breeding','reason'=>'Déjà engagé'],$user),'Orientation avec abattage en attente refusée');
check((int)$db->query('SELECT COUNT(*) FROM livestock_pig_events')->fetchColumn()>0&&(int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()>0,'Historique et audit des opérations conservés');
$db->exec("INSERT INTO livestock_batches(site_id,species_id,facility_id,batch_number,purpose,started_at,initial_heads,current_heads,status,created_by) VALUES(1,1,1,'LEGACY-PIG','fattening','2026-01-01',7,7,'active',1)");
$legacy=(int)$db->lastInsertId();$old=row($legacy);$total=heads();
check($old['sex']===null&&$old['expected_group']===null,'Ancien lot sans sexe ni âge inventés');
$identity=['version'=>$old['version'],'heads'=>'7','sex'=>'male','born_on'=>date('Y-m-d',strtotime('-5 months'))];
rejected(fn()=>$service->identify($legacy,array_replace($identity,['heads'=>'6']),$user),'Identification avec effectif différent refusée');
$service->identify($legacy,$identity,$user);
check(heads()===$total&&row($legacy)['sex']==='male'&&row($legacy)['confirmed_group']==='C','Identification conserve les animaux existants');
rejected(fn()=>$service->identify($legacy,$identity,$user),'Double identification refusée');
// Simulate a storage error after writes to check atomic rollback, including head effects.
$before=heads();$db->exec("CREATE TRIGGER fail_pig_event BEFORE INSERT ON livestock_pig_events BEGIN SELECT RAISE(ABORT,'Simulated storage error'); END");
try { $service->register(data(['heads'=>'2']),$user); throw new LogicException('Erreur attendue'); } catch (PDOException $e) {}
check(heads()===$before&&!$db->inTransaction(),'Erreur SQL : rollback complet du lot et du profil');
echo "$checks vérifications réussies.\n";
