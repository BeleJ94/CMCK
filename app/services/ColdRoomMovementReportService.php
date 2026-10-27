<?php
require_once __DIR__.'/SpreadsheetExportService.php';

class ColdRoomMovementReportService
{
    public function report(PDO $db,int $site,array $query): array
    {
        $f=[];foreach(['from'=>date('Y-m-01'),'to'=>date('Y-m-d'),'kind'=>'','direction'=>'','q'=>''] as $key=>$default){$v=$query[$key]??$default;if(!is_string($v)||mb_strlen($v)>180)throw new InvalidArgumentException('Filtre invalide.');$f[$key]=trim($v);}
        foreach(['from','to'] as $key){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$f[$key]);if(!$d||$d->format('Y-m-d')!==$f[$key]||$f[$key]<'1900-01-01'||$f[$key]>'2199-12-31')throw new InvalidArgumentException('Date invalide.');}
        if($f['from']>$f['to'])throw new InvalidArgumentException('La date de début doit précéder la date de fin.');
        if(!in_array($f['kind'],['','raw','finished'],true)||!in_array($f['direction'],['','in','out','reservation'],true))throw new InvalidArgumentException('Filtre invalide.');
        $params=[$site,$f['from'].' 00:00:00',(new DateTimeImmutable($f['to']))->modify('+1 day')->format('Y-m-d').' 00:00:00'];
        $owned=!$db->inTransaction();if($owned){$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->beginTransaction();}
        try{
            $rows=[];
            // Live poultry (044) never enters the cold room.
            $live=(int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='butchery_items' AND column_name='is_live'")->fetchColumn()?' AND i.is_live=0':'';
            foreach(['raw','finished'] as $kind){
                $exclude=$kind==='finished'?" AND NOT (m.reference_type='butchery_sales' AND EXISTS(SELECT 1 FROM butchery_sales s WHERE s.id=m.reference_id AND s.counter_stock_id IS NOT NULL)){$live}":'';
                $sql="SELECT m.*,l.lot_number,l.item_id,i.name article FROM butchery_{$kind}_movements m JOIN butchery_{$kind}_lots l ON l.id=m.{$kind}_lot_id JOIN butchery_items i ON i.id=l.item_id WHERE l.site_id=? AND m.created_at>=? AND m.created_at<?".$exclude;
                $s=$db->prepare($sql);$s->execute($params);foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){$r['kind']=$kind;$r['grams']=(int)round((float)$r['stock_after_kg']*1000)-(int)round((float)$r['stock_before_kg']*1000);$references=['butchery_receipts'=>'Réception','butchery_orders'=>'Fabrication','butchery_slaughters'=>'Abattage','poultry_butchery_slaughters'=>'Abattage en boucherie','butchery_sales'=>'Vente','butchery_transfers'=>'Transfert'];$r['reference']=($references[$r['reference_type']]??'Opération').' #'.$r['reference_id'];$rows[]=$r;}
            }
            $s=$db->prepare("SELECT m.*,l.lot_number,l.item_id,i.name article,c.name counter_name FROM butchery_counter_movements m JOIN butchery_counter_stocks cs ON cs.id=m.counter_stock_id JOIN butchery_counters c ON c.id=cs.counter_id JOIN butchery_finished_lots l ON l.id=cs.finished_lot_id JOIN butchery_items i ON i.id=l.item_id WHERE l.site_id=? AND m.created_at>=? AND m.created_at<? AND c.site_id=l.site_id AND m.movement_type IN('supply','return_cold'){$live}");$s->execute($params);
            foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){$r['kind']='finished';$r['grams']=(int)round((float)$r['quantity_kg']*1000)*($r['movement_type']==='supply'?-1:1);$r['reference']='Opération #'.$r['operation_id'].' · '.$r['counter_name'];$rows[]=$r;}
            if($owned)$db->commit();
        }catch(Throwable $e){if($owned&&$db->inTransaction())$db->rollBack();throw $e;}
        return $this->summarize($rows,$f);
    }
    public function summarize(array $rows,array $f): array
    {
        $labels=['receipt_in'=>'Réception','slaughter_in'=>'Abattage réceptionné','production_in'=>'Production obtenue','production_out'=>'Retrait pour transformation','direct_sale_out'=>'Vente directe historique','sale_out'=>'Vente directe historique','transfer_out'=>'Expédition','supply'=>'Envoi au comptoir','return_cold'=>'Retour du comptoir','reserve'=>'Réservation','release'=>'Libération','reversal'=>'Annulation / correction'];
        $journal=[];$articles=[];$totals=['in'=>0,'out'=>0,'reservation'=>0];
        foreach($rows as $r){$r['direction']=$r['grams']>0?'in':($r['grams']<0?'out':'reservation');if($r['grams']===0&&!in_array($r['movement_type'],['reserve','release'],true))continue;
            if($f['kind']!==''&&$r['kind']!==$f['kind']||$f['direction']!==''&&$r['direction']!==$f['direction'])continue;
            if($f['q']!==''&&mb_stripos($r['article'].' '.$r['lot_number'].' '.$r['reference'],$f['q'])===false)continue;
            $r['label']=$labels[$r['movement_type']]??$r['movement_type'];$journal[]=$r;
            if($r['direction']==='reservation'){$totals['reservation']++;continue;}
            $totals[$r['direction']]+=abs($r['grams']);$key=$r['kind'].':'.$r['item_id'];if(!isset($articles[$key]))$articles[$key]=['article'=>$r['article'],'kind'=>$r['kind'],'in'=>0,'out'=>0];$articles[$key][$r['direction']]+=abs($r['grams']);
        }
        usort($journal,static fn($a,$b)=>strcmp($b['created_at'],$a['created_at'])?:($b['id']<=>$a['id']));uasort($articles,static fn($a,$b)=>strnatcasecmp($a['article'],$b['article']));
        $scope='Du '.date('d/m/Y',strtotime($f['from'])).' au '.date('d/m/Y',strtotime($f['to'])).' · '.([''=>'Toutes catégories','raw'=>'Matières premières','finished'=>'Produits obtenus'][$f['kind']]).' · '.([''=>'Tous les mouvements','in'=>'Entrées','out'=>'Sorties','reservation'=>'Réservations / libérations'][$f['direction']]).($f['q']!==''?' · Recherche : '.$f['q']:'');
        return ['filters'=>$f,'journal'=>$journal,'articles'=>array_values($articles),'totals'=>$totals,'scope'=>$scope,'issuedAt'=>date('d/m/Y à H:i')];
    }
    public function spreadsheet(array $r): string
    {
        $prefix=[['DAGRIL — MOUVEMENTS CHAMBRE FROIDE'],[$r['scope']],['Boucherie · Ventes des comptoirs exclues · Réservations sans mouvement physique'],['Édité le '.$r['issuedAt'].' · Tous les résultats filtrés']];
        $summary=array_merge($prefix,[['Indicateur','Quantité (kg)']],[['Entrées',(float)($r['totals']['in']/1000)],['Sorties',(float)($r['totals']['out']/1000)],['Solde des mouvements',(float)(($r['totals']['in']-$r['totals']['out'])/1000)],['FIN','Le solde des mouvements ne représente pas le stock disponible']]);
        $articles=array_merge($prefix,[['Article','Catégorie','Entrées (kg)','Sorties (kg)','Solde (kg)']]);foreach($r['articles'] as $a)$articles[]=[$a['article'],$a['kind']==='raw'?'Matière première':'Produit obtenu',(float)($a['in']/1000),(float)($a['out']/1000),(float)(($a['in']-$a['out'])/1000)];$articles[]=['FIN'];
        $journal=array_merge($prefix,[['Date','Article','Catégorie','Lot','Mouvement','Référence','Entrée (kg)','Sortie (kg)','Réservation / libération (kg)']]);foreach($r['journal'] as $m)$journal[]=[$m['created_at'],$m['article'],$m['kind']==='raw'?'Matière première':'Produit obtenu',$m['lot_number'],$m['label'],$m['reference'],(float)(max(0,$m['grams'])/1000),(float)(max(0,-$m['grams'])/1000),$m['direction']==='reservation'?(float)$m['quantity_kg']:''];$journal[]=['FIN',count($r['journal']).' mouvement(s)'];
        return (new SpreadsheetExportService())->workbook([['name'=>'Synthèse','rows'=>$summary,'widths'=>[45,40]],['name'=>'Articles','rows'=>$articles,'widths'=>[38,24,20,20,20]],['name'=>'Journal','rows'=>$journal,'widths'=>[24,32,24,35,30,48,20,20,30]]]);
    }
}
