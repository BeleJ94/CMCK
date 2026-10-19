<?php
require_once __DIR__.'/SpreadsheetExportService.php';

class PigExportService
{
    private static function normalize(string $value): string
    {
        return strtolower(strtr($value, ['À'=>'a','Â'=>'a','Ä'=>'a','à'=>'a','â'=>'a','ä'=>'a','É'=>'e','È'=>'e','Ê'=>'e','Ë'=>'e','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','Î'=>'i','Ï'=>'i','î'=>'i','ï'=>'i','Ô'=>'o','Ö'=>'o','ô'=>'o','ö'=>'o','Ù'=>'u','Û'=>'u','Ü'=>'u','ù'=>'u','û'=>'u','ü'=>'u','Ç'=>'c','ç'=>'c']));
    }

    public function report(array $directory, array $query): array
    {
        $labels=[''=>'Tous les lots','A'=>'Groupe A','B'=>'Groupe B','C'=>'Groupe C','male'=>'Mâles','female'=>'Femelles','breeding'=>'Reproducteurs','pending'=>'Passages à vérifier','unknown'=>'Lots à renseigner'];
        $filter=is_string($query['filter']??null)?$query['filter']:'';
        $metric=is_string($query['metric']??null)?$query['metric']:'';
        $search=is_string($query['q']??null)?trim($query['q']):'';
        if (!isset($labels[$filter]) || !in_array($metric,['','male','female','breeding'],true)) throw new InvalidArgumentException('Filtres invalides.');
        $rows=array_values(array_filter($directory,static function($r) use($filter,$metric,$search){
            $matches=static function($v) use($r){return $v==='' || ($r['confirmed_group']??'unknown')===$v || ($r['sex']??'unknown')===$v || ($v==='breeding'&&$r['purpose']==='breeding') || ($v==='pending'&&$r['pending']);};
            return $matches($filter)&&$matches($metric)&&strpos(self::normalize($r['batch_number'].' '.$r['site_name'].' '.$r['facility_name']),self::normalize($search))!==false;
        }));
        $sort=is_string($query['sort']??null)?$query['sort']:'lot';
        if(!in_array($sort,['lot','heads','sex','group'],true))$sort='lot';
        $direction=($query['direction']??'')==='desc'?-1:1;
        usort($rows,static function($a,$b) use($sort,$direction){
            $field=['lot'=>'id','heads'=>'current_heads','sex'=>'sex','group'=>'confirmed_group'][$sort];
            return strnatcasecmp((string)$a[$field],(string)$b[$field])*$direction;
        });
        $totals=['heads'=>0,'male'=>0,'female'=>0,'unknown'=>0,'breeders'=>0];
        foreach($rows as$r){$n=(int)$r['current_heads'];$totals['heads']+=$n;$totals[$r['sex']?:'unknown']+=$n;if($r['purpose']==='breeding')$totals['breeders']+=$n;}
        $scope=$labels[$filter].($metric?' · '.$labels[$metric]:'').($search?' · Recherche : '.$search:'');
        $sites=array_values(array_unique(array_column($directory,'site_name')));
        return ['rows'=>$rows,'totals'=>$totals,'scope'=>$scope,'sites'=>$sites,'issuedAt'=>date('d/m/Y à H:i'),'headers'=>['Lot','Ferme','Bâtiment','Groupe','Sexe','Effectif','Orientation','Naissance','Passage à vérifier']];
    }

    public function spreadsheet(array $report): string
    {
        $t=$report['totals'];
        $sheet=[['DAGRIL — ÉLEVAGE PORCIN'],[implode(' · ',$report['sites']).' | '.$report['scope']],[$t['heads'].' porcs vivants · '.$t['male'].' mâles · '.$t['female'].' femelles · '.$t['breeders'].' reproducteurs (inclus)'],['Édité le '.$report['issuedAt'].' · Tous les résultats filtrés, toutes pages confondues'],$report['headers']];
        foreach($report['rows'] as$r)$sheet[]=$this->cells($r);
        $sheet[]=['TOTAL',count($report['rows']).' lots','','','',$t['heads'],'Reproducteurs inclus dans les effectifs'];
        return (new SpreadsheetExportService())->build($sheet,[32,25,30,14,18,14,25,18,24]);
    }

    public function cells(array $r): array
    {
        return [$r['batch_number'],$r['site_name'],$r['facility_name'],$r['confirmed_group']?'Groupe '.$r['confirmed_group']:'À renseigner',['male'=>'Mâles','female'=>'Femelles'][$r['sex']]??'À renseigner',(int)$r['current_heads'],$r['purpose']==='breeding'?'Reproducteurs':'Élevage pour abattage',$r['born_on']?date('d/m/Y',strtotime($r['born_on'])):'À renseigner',$r['pending']?($r['confirmed_group'].' → '.$r['expected_group']):'—'];
    }
}
