<?php
class ProductionExportService
{
    public const STATES=['pending'=>'À produire','in_progress'=>'À produire','results_submitted'=>'À valider','pending_additional_approval'=>'Écart à approuver','validated'=>'Validée','cancelled'=>'Annulée'];
    public function filtered(array $batches, array $f): array
    {
        $norm=function($s){return strtr(mb_strtolower($s,'UTF-8'),['é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a','ä'=>'a','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c']);};
        if($f['from']&&$f['to']&&$f['from']>$f['to'])return [];
        return array_values(array_filter($batches,function($b)use($f,$norm){
            $state=in_array($b['status'],['pending','in_progress'],true)?'todo':$b['status'];
            $date=substr(($f['date-kind']==='end'?$b['ended_at']:$b['started_at'])??'',0,10);
            return (!$f['state']||$f['state']===$state)&&(!$f['machine']||$f['machine']===$b['machine_code'])&&(!$f['silo']||$f['silo']===(string)$b['silo_id'])&&(!$f['from']||($date&&$date>=$f['from']))&&(!$f['to']||($date&&$date<=$f['to']))&&(!$f['search']||strpos($norm($b['batch_number'].' '.$b['machine_name'].' '.$b['silo_name'].' '.$b['bss_number']),$norm($f['search']))!==false);
        }));
    }
    public function rows(array $batches): array
    {
        $rows=[];
        foreach($batches as$b){$todo=in_array($b['status'],['pending','in_progress'],true);$input=(float)$b['actual_input_quantity_kg'];$rows[]=[date('d/m/Y H:i',strtotime($b['started_at'])),$b['ended_at']?date('d/m/Y H:i',strtotime($b['ended_at'])):'—',$b['batch_number'],$b['bss_number']?:'—',$b['machine_name'],$b['silo_name'],$input,$todo?'—':(float)$b['output_quantity_kg'],$todo?'—':(float)$b['waste_quantity_kg'],$todo||$input<=0?'—':round(100*(float)$b['output_quantity_kg']/$input,1),self::STATES[$b['status']]??$b['status']];}
        return $rows;
    }
    public function headers(): array {return ['Début','Fin','Lot','BSS','Machine','Silo','Chargé (kg)','Farine (kg)','Déchets (kg)','Rendement (%)','Étape'];}
    public function totals(array $batches): array
    {
        $validated=array_filter($batches,fn($b)=>$b['status']==='validated');
        return ['lots'=>count($batches),'validated'=>count($validated),'loaded'=>array_sum(array_column($batches,'actual_input_quantity_kg')),'flour'=>array_sum(array_column($validated,'output_quantity_kg')),'waste'=>array_sum(array_column($validated,'waste_quantity_kg'))];
    }
}
