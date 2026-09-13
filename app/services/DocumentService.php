<?php

require_once __DIR__.'/DocumentNumberService.php';

class DocumentService
{
    private $db;
    public function __construct(PDO $db=null){$this->db=$db?:Database::getInstance()->connection();}

    public function register($typeCode,$siteId,$entityType,$entityId,$status,$documentDate,array $user,$amount=0)
    {
        $own=!$this->db->inTransaction(); if($own){$this->db->beginTransaction();}
        try{
            $existing=$this->findByEntity($typeCode,$entityType,$entityId,true); if($existing){if($own){$this->db->commit();}return $existing;}
            $type=$this->type($typeCode);$number=(new DocumentNumberService($this->db))->next($typeCode,$siteId,$documentDate);
            $status=in_array($status,['validated','approved'],true)?'approved':(in_array($status,['pending_validation','submitted'],true)?'pending_approval':$status);$validated=$status==='approved';
            $s=$this->db->prepare("INSERT INTO documents(document_type_id,document_number,site_id,period_key,entity_type,entity_id,status,document_date,created_by,validated_by,validated_at) VALUES(:type,:number,:site,:period,:entity_type,:entity_id,:status,:document_date,:creator,:validator,".($validated?'NOW()':'NULL').")");
            $s->execute(['type'=>$type['id'],'number'=>$number['number'],'site'=>$siteId,'period'=>$number['period_key'],'entity_type'=>$entityType,'entity_id'=>$entityId,'status'=>$status,'document_date'=>$documentDate,'creator'=>$user['id']??null,'validator'=>$validated?($user['id']??null):null]);
            $id=$this->db->lastInsertId();$this->history($id,null,$status,$user['id']??null,'Création du document');
            require_once __DIR__.'/WorkflowService.php';(new WorkflowService($this->db))->createForDocument($id,$amount,$user);
            $document=$this->findById($id,true);if($own){$this->db->commit();}return $document;
        }catch(Exception $e){if($own&&$this->db->inTransaction()){$this->db->rollBack();}throw $e;}
    }

    public function transition($id,$newStatus,array $user,$reason=null)
    {
        $allowed=['draft'=>['pending_validation','cancelled'],'pending_validation'=>['validated','cancelled'],'validated'=>['cancelled'],'cancelled'=>[]];
        $this->db->beginTransaction();try{$doc=$this->findById($id,true,true);if(!$doc){throw new RuntimeException('Document introuvable.');}Auth::requireSiteAccess($doc['site_id']);if(!in_array($newStatus,$allowed[$doc['status']]??[],true)){throw new RuntimeException('Transition documentaire interdite.');}
            if($newStatus==='validated'&&!empty($doc['created_by'])&&(int)$doc['created_by']===(int)$user['id']&&!Auth::canSelfValidate($user['id'])){throw new RuntimeException('Séparation des tâches: le créateur ne peut pas valider ce document.');}
            $fields="status=:status";$params=['status'=>$newStatus,'id'=>$id];if($newStatus==='validated'){$fields.=',validated_by=:actor,validated_at=NOW()';$params['actor']=$user['id'];}if($newStatus==='cancelled'){$fields.=',cancelled_by=:actor,cancelled_at=NOW()';$params['actor']=$user['id'];}
            $this->db->prepare("UPDATE documents SET {$fields} WHERE id=:id")->execute($params);$this->history($id,$doc['status'],$newStatus,$user['id'],$reason);$this->db->commit();
        }catch(Exception $e){$this->db->rollBack();throw $e;}
    }

    public function findByEntity($typeCode,$entityType,$entityId,$forUpdate=false){$sql="SELECT d.*,dt.code type_code,dt.display_code,dt.name type_name FROM documents d INNER JOIN document_types dt ON dt.id=d.document_type_id WHERE dt.code=:code AND d.entity_type=:entity_type AND d.entity_id=:entity_id AND d.deleted_at IS NULL LIMIT 1".($forUpdate?' FOR UPDATE':'');$s=$this->db->prepare($sql);$s->execute(['code'=>strtoupper($typeCode),'entity_type'=>$entityType,'entity_id'=>$entityId]);return $s->fetch();}
    private function type($code){$s=$this->db->prepare("SELECT * FROM document_types WHERE code=:code AND status='active' AND deleted_at IS NULL LIMIT 1");$s->execute(['code'=>strtoupper($code)]);$r=$s->fetch();if(!$r){throw new RuntimeException('Type documentaire introuvable: '.$code);}return $r;}
    private function findById($id,$ignoreScope=false,$forUpdate=false){$sql='SELECT d.*,dt.code type_code,dt.display_code,dt.name type_name,s.code site_code,s.name site_name FROM documents d INNER JOIN document_types dt ON dt.id=d.document_type_id INNER JOIN sites s ON s.id=d.site_id WHERE d.id=:id AND d.deleted_at IS NULL LIMIT 1'.($forUpdate?' FOR UPDATE':'');$s=$this->db->prepare($sql);$s->execute(['id'=>$id]);return $s->fetch();}
    private function history($id,$old,$new,$actor,$reason){$s=$this->db->prepare('INSERT INTO document_status_history(document_id,old_status,new_status,changed_by,reason) VALUES(:id,:old,:new,:actor,:reason)');$s->execute(['id'=>$id,'old'=>$old,'new'=>$new,'actor'=>$actor,'reason'=>$reason]);}
}
