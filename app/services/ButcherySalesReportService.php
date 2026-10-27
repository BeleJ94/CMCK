<?php
require_once __DIR__.'/SpreadsheetExportService.php';

class ButcherySalesReportService
{
    private function text(array $query,string $key,string $default=''): string
    {
        $value=$query[$key]??$default;
        if(!is_string($value)||mb_strlen($value)>180)throw new InvalidArgumentException('Filtre invalide : '.$key.'.');
        return trim($value);
    }
    private function day(string $value): DateTimeImmutable
    {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if(!$date||$date->format('Y-m-d')!==$value||$value<'1900-01-01'||$value>'2199-12-31')throw new InvalidArgumentException('Renseignez une date valide.');
        return $date;
    }
    private function units($value,int $precision=2): int
    {
        $parts=explode('.',(string)$value);return (int)$parts[0]*(10**$precision)+(int)str_pad(substr($parts[1]??'',0,$precision),$precision,'0');
    }
    public function report(PDO $db,int $site,array $query,array $counters,array $settings): array
    {
        $filters=['from'=>$this->text($query,'from',date('Y-m-01')),'to'=>$this->text($query,'to',date('Y-m-d')),'counter'=>$this->text($query,'counter'),'currency'=>$this->text($query,'currency'),'payment'=>$this->text($query,'payment'),'status'=>$this->text($query,'status'),'q'=>$this->text($query,'q')];
        $start=$this->day($filters['from']);$end=$this->day($filters['to']);
        if($start>$end)throw new InvalidArgumentException('La date de début doit précéder la date de fin.');
        $counterNames=[];foreach($counters as$c)$counterNames[(string)$c['id']]=$c['name'];
        if($filters['counter']!==''&&!isset($counterNames[$filters['counter']]))throw new InvalidArgumentException('Comptoir hors de votre périmètre.');
        if($filters['currency']!==''&&!in_array($filters['currency'],$settings['currencies'],true))throw new InvalidArgumentException('Devise invalide.');
        if($filters['payment']!==''&&!isset($settings['payment_methods'][$filters['payment']]))throw new InvalidArgumentException('Mode de paiement invalide.');
        if(!in_array($filters['status'],['','paid','cancelled'],true))throw new InvalidArgumentException('État invalide.');
        $where='t.site_id=? AND t.sold_at>=? AND t.sold_at<?';$params=[$site,$start->format('Y-m-d').' 00:00:00',$end->modify('+1 day')->format('Y-m-d').' 00:00:00'];
        foreach(['counter'=>'counter_id','currency'=>'currency','payment'=>'payment_method','status'=>'status']as$key=>$field){if($filters[$key]!==''){$where.=' AND t.'.$field.'=?';$params[]=$filters[$key];}}
        if($filters['q']!==''){$where.=' AND (LOCATE(?,t.ticket_number)>0 OR LOCATE(?,t.customer_name)>0 OR LOCATE(?,t.cashier_name)>0)';array_push($params,$filters['q'],$filters['q'],$filters['q']);}
        $ownsTransaction=!$db->inTransaction();
        if($ownsTransaction){$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->beginTransaction();}
        try{
            $statement=$db->prepare('SELECT t.*,(SELECT COALESCE(SUM(l.quantity_kg),0) FROM butchery_pos_lines l WHERE l.ticket_id=t.id) quantity_kg FROM butchery_pos_tickets t WHERE '.$where.' ORDER BY t.sold_at DESC,t.id DESC');$statement->execute($params);$tickets=$statement->fetchAll(PDO::FETCH_ASSOC);
            $statement=$db->prepare('SELECT l.*,t.currency,t.status,t.payment_method FROM butchery_pos_lines l JOIN butchery_pos_tickets t ON t.id=l.ticket_id WHERE '.$where.' ORDER BY t.sold_at DESC,t.id DESC,l.id');$statement->execute($params);$lines=$statement->fetchAll(PDO::FETCH_ASSOC);
            if($ownsTransaction)$db->commit();
        }catch(Throwable $error){if($ownsTransaction&&$db->inTransaction())$db->rollBack();throw $error;}
        $summary=[];$payments=[];$articles=[];
        foreach($tickets as$t){
            $currency=$t['currency'];if(!isset($summary[$currency]))$summary[$currency]=['currency'=>$currency,'paid'=>0,'cancelled'=>0,'revenue_cents'=>0,'cancelled_cents'=>0,'grams'=>0];
            $amount=$this->units($t['total']);
            if($t['status']==='cancelled'){$summary[$currency]['cancelled']++;$summary[$currency]['cancelled_cents']+=$amount;continue;}
            $summary[$currency]['paid']++;$summary[$currency]['revenue_cents']+=$amount;$summary[$currency]['grams']+=$this->units($t['quantity_kg'],3);
            $key=$currency.':'.$t['payment_method'];if(!isset($payments[$key]))$payments[$key]=['currency'=>$currency,'method'=>$settings['payment_methods'][$t['payment_method']]??$t['payment_method'],'count'=>0,'cents'=>0];
            $payments[$key]['count']++;$payments[$key]['cents']+=$amount;
        }
        foreach($lines as$l){if($l['status']!=='paid')continue;$key=$l['currency'].':'.$l['item_id'];if(!isset($articles[$key]))$articles[$key]=['name'=>$l['item_name'],'currency'=>$l['currency'],'count'=>0,'grams'=>0,'pieces'=>null,'cents'=>0];$articles[$key]['count']++;$articles[$key]['grams']+=$this->units($l['quantity_kg'],3);if(isset($l['quantity_pieces']))$articles[$key]['pieces']=(int)$articles[$key]['pieces']+(int)$l['quantity_pieces'];$articles[$key]['cents']+=$this->units($l['amount']);}
        ksort($summary);ksort($payments);uasort($articles,static fn($a,$b)=>strcmp($a['currency'],$b['currency'])?:($b['cents']<=>$a['cents'])?:strnatcasecmp($a['name'],$b['name']));
        $scope='Du '.$start->format('d/m/Y').' au '.$end->format('d/m/Y').' · '.($filters['counter']===''?'Tous les comptoirs':$counterNames[$filters['counter']]).' · '.($filters['currency']?:'Devises séparées').' · '.($filters['payment']===''?'Tous les paiements':$settings['payment_methods'][$filters['payment']]).' · '.([''=>'Tous les états','paid'=>'Encaissés','cancelled'=>'Annulés'][$filters['status']]).($filters['q']!==''?' · Recherche : '.$filters['q']:'');
        return ['filters'=>$filters,'tickets'=>$tickets,'summary'=>array_values($summary),'payments'=>array_values($payments),'articles'=>array_values($articles),'scope'=>$scope,'issuedAt'=>date('d/m/Y à H:i'),'methods'=>$settings['payment_methods'],'counters'=>$counters,'currencies'=>$settings['currencies']];
    }
    public function spreadsheet(array $report): string
    {
        $prefix=[['DAGRIL — RAPPORT DE VENTES BOUCHERIE'],[$report['scope']],['Ventes POS uniquement · Encaissements hors annulations · Devises jamais additionnées'],['Édité le '.$report['issuedAt'].' · Tous les résultats filtrés, toutes pages confondues']];
        $tickets=array_merge($prefix,[['Ticket','Date','Comptoir','Vendeur','Client','Devise','Total','Mode de paiement','Montant reçu','Monnaie rendue','Poids (kg)','État']]);
        foreach($report['tickets']as$t)$tickets[]=[$t['ticket_number'],$t['sold_at'],$t['counter_name'],$t['cashier_name'],$t['customer_name'],$t['currency'],(float)$t['total'],$report['methods'][$t['payment_method']]??$t['payment_method'],(float)$t['received_amount'],(float)$t['change_amount'],(float)$t['quantity_kg'],$t['status']==='paid'?'Encaissé':'Annulé'];
        $tickets[]=['FIN',count($report['tickets']).' ticket(s)'];
        $summary=array_merge($prefix,[['Devise','Tickets encaissés','Chiffre d’affaires','Poids vendu (kg)','Tickets annulés','Montant annulé']]);
        foreach($report['summary']as$s)$summary[]=[$s['currency'],(float)$s['paid'],(float)($s['revenue_cents']/100),(float)($s['grams']/1000),(float)$s['cancelled'],(float)($s['cancelled_cents']/100)];$summary[]=['FIN','Aucun total entre devises différentes'];
        $articles=array_merge($prefix,[['Article','Devise','Poids vendu (kg)','Pièces vendues','Tickets','Montant vendu']]);
        foreach($report['articles']as$a)$articles[]=[$a['name'],$a['currency'],(float)($a['grams']/1000),($a['pieces']??null)===null?'':(float)$a['pieces'],(float)$a['count'],(float)($a['cents']/100)];$articles[]=['FIN','Ventes encaissées uniquement'];
        $payments=array_merge($prefix,[['Devise','Mode de paiement','Tickets encaissés','Montant vendu']]);
        foreach($report['payments']as$p)$payments[]=[$p['currency'],$p['method'],(float)$p['count'],(float)($p['cents']/100)];$payments[]=['FIN','Montants vendus, hors monnaie rendue'];
        return (new SpreadsheetExportService())->workbook([
            ['name'=>'Synthèse','rows'=>$summary,'widths'=>[15,22,25,24,22,25],'formats'=>[1=>0,2=>2,4=>0,5=>2]],
            ['name'=>'Tickets','rows'=>$tickets,'widths'=>[40,23,25,25,30,12,20,24,20,20,18,16],'formats'=>[6=>2,8=>2,9=>2]],
            ['name'=>'Articles','rows'=>$articles,'widths'=>[35,15,24,18,18,25],'formats'=>[3=>0,4=>0,5=>2]],
            ['name'=>'Paiements','rows'=>$payments,'widths'=>[15,28,24,25],'formats'=>[2=>0,3=>2]],
        ]);
    }
}
