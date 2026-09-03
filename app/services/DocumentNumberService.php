<?php

class DocumentNumberService
{
    private $db;
    public function __construct(PDO $db){$this->db=$db;}

    public function next($typeCode,$siteId,$documentDate)
    {
        if (!$this->db->inTransaction()) { throw new RuntimeException('La numerotation documentaire exige une transaction SQL.'); }
        $rule=$this->rule($typeCode,$siteId); if(!$rule){throw new RuntimeException('Regle de numerotation introuvable: '.$typeCode);}
        $timestamp=strtotime($documentDate); if($timestamp===false){throw new RuntimeException('Date documentaire invalide.');}
        $period=$rule['period_type']==='month'?date('Ym',$timestamp):($rule['period_type']==='none'?'GLOBAL':date('Y',$timestamp));
        $insert=$this->db->prepare("INSERT INTO document_number_counters(numbering_rule_id,site_id,period_key,last_sequence) VALUES(:rule,:site,:period,0) ON DUPLICATE KEY UPDATE last_sequence=last_sequence");
        $insert->execute(['rule'=>$rule['id'],'site'=>$siteId,'period'=>$period]);
        $lock=$this->db->prepare('SELECT id,last_sequence FROM document_number_counters WHERE numbering_rule_id=:rule AND site_id=:site AND period_key=:period FOR UPDATE');
        $lock->execute(['rule'=>$rule['id'],'site'=>$siteId,'period'=>$period]);$counter=$lock->fetch();
        $sequence=(int)$counter['last_sequence']+1;
        $this->db->prepare('UPDATE document_number_counters SET last_sequence=:sequence WHERE id=:id')->execute(['sequence'=>$sequence,'id'=>$counter['id']]);
        $number=str_replace(['{TYPE}','{SITE}','{YEAR}','{MONTH}','{PERIOD}'],[$rule['display_code'],$rule['site_code'],date('Y',$timestamp),date('m',$timestamp),$period],$rule['pattern']);
        $number=preg_replace_callback('/\{SEQ(?::(\d+))?\}/',function($m)use($sequence,$rule){$length=isset($m[1])?(int)$m[1]:(int)$rule['sequence_length'];return str_pad((string)$sequence,$length,'0',STR_PAD_LEFT);},$number);
        return ['number'=>$number,'period_key'=>$period,'sequence'=>$sequence];
    }

    private function rule($typeCode,$siteId)
    {
        $s=$this->db->prepare("SELECT r.*,dt.display_code,s.code site_code FROM document_numbering_rules r INNER JOIN document_types dt ON dt.id=r.document_type_id INNER JOIN sites s ON s.id=:site WHERE dt.code=:code AND r.status='active' AND r.deleted_at IS NULL AND (r.site_id=:site_specific OR r.site_id IS NULL) ORDER BY r.site_id IS NULL ASC LIMIT 1");
        $s->execute(['site'=>$siteId,'site_specific'=>$siteId,'code'=>strtoupper($typeCode)]);return $s->fetch();
    }
}
