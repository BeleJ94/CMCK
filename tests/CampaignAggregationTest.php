<?php
// Isolated relational regression: no application database or credentials used.
class Auth {
    public static $site=1;
    public static function siteClause($column,array &$params){$params['site']=self::$site;return " AND {$column}=:site";}
}
require dirname(__DIR__).'/app/core/Model.php';
require dirname(__DIR__).'/app/models/Agriculture.php';
class CampaignTestModel extends Agriculture {public function __construct(PDO $db){$this->db=$db;}}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE agricultural_campaigns(id INTEGER,site_id INTEGER,season_id INTEGER,start_date TEXT,deleted_at TEXT);
CREATE TABLE agricultural_seasons(id INTEGER,name TEXT);
CREATE TABLE sites(id INTEGER,code TEXT,name TEXT);
CREATE TABLE agricultural_campaign_plots(id INTEGER,campaign_id INTEGER,actual_area_ha REAL,target_tons_per_ha REAL);
CREATE TABLE agricultural_harvests(campaign_plot_id INTEGER,net_weight_kg REAL,status TEXT,deleted_at TEXT);
INSERT INTO agricultural_seasons VALUES(1,'Saison');INSERT INTO sites VALUES(1,'A','Ferme A'),(2,'B','Ferme B');
INSERT INTO agricultural_campaigns VALUES(1,1,1,'2026-01-01',NULL),(2,1,1,'2026-02-01',NULL),(3,2,1,'2026-01-01',NULL),(4,1,1,'2026-03-01','2026-04-01');
INSERT INTO agricultural_campaign_plots VALUES(1,1,10,2),(2,1,5,3),(3,3,50,10);
INSERT INTO agricultural_harvests VALUES(1,1000,'validated',NULL),(1,2000,'validated',NULL),(2,4000,'submitted',NULL),(2,8000,'validated','2026-01-01'),(3,9000,'validated',NULL);");
$rows=(new CampaignTestModel($db))->campaigns();
function campaignCheck($ok,$message){if(!$ok)throw new RuntimeException($message);echo "OK - {$message}\n";}
campaignCheck(count($rows)===2,'Campagnes supprimées et autres sites exclus');
campaignCheck((int)$rows[0]['id']===2,'Tri chronologique conservé');
campaignCheck((float)$rows[0]['target_tons']===0.0&&(float)$rows[0]['realized_tons']===0.0,'Campagne sans parcelle incluse avec des totaux nuls');
campaignCheck((float)$rows[1]['actual_area_ha']===15.0&&(float)$rows[1]['target_tons']===35.0,'Surfaces et objectifs indépendants du nombre de récoltes');
campaignCheck((float)$rows[1]['realized_tons']===3.0,'Seules les récoltes validées non supprimées comptent, en tonnes');
campaignCheck((int)$rows[1]['plot_count']===2,'Nombre de parcelles exact');
