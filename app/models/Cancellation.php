<?php

class Cancellation extends Model
{
    protected $table = 'cancellation_requests';

    public function searchableOperations($type, $search='')
    {
        $map=[
            'weighing'=>['weighings','reference','r.site_id'], 'brs'=>['silo_movements','id','r.site_id'],
            'bss'=>['machine_feeds','id','r.site_id'], 'transfer'=>['stock_transfers','transfer_number','r.source_site_id'],
            'production'=>['production_batches','batch_number','r.site_id'], 'packaging'=>['packaging','id','r.site_id'],
            'distribution'=>['distributions','exit_voucher','r.site_id'],
            'waste_sale'=>['waste_sales','sale_number','(SELECT site_id FROM waste_stocks WHERE id=r.waste_stock_id)'],
            'waste_transfer'=>['waste_transfers','transfer_number','r.source_site_id'],
            'pelletization'=>['pellet_orders','order_number','r.site_id'],
            'empty_packaging_receipt'=>['empty_packaging_receipts','receipt_number','(SELECT site_id FROM empty_packaging_purchases WHERE id=r.purchase_id)'],
            'fuel_receipt'=>['fuel_receipts','receipt_number','(SELECT site_id FROM fuel_purchase_orders WHERE id=r.purchase_order_id)'],
            'butchery_receipt'=>['butchery_receipts','receipt_number','r.site_id']
        ];
        if(!isset($map[$type]))throw new RuntimeException('Choisissez un type d’opération disponible.');
        $policy=$this->query("SELECT * FROM cancellation_policies WHERE entity_type=:type AND status='active'",['type'=>$type])->fetch();
        if(!$policy)return [];
        [$table,$reference,$site]=$map[$type];
        $columns=array_column($this->query("SHOW COLUMNS FROM `$table`")->fetchAll(),'Field');
        $date='NULL';foreach(['distributed_at','weighed_at','packaged_at','received_at','sold_at','processed_at','started_at','movement_at','fed_at','created_at'] as $field){if(in_array($field,$columns,true)){$date='r.'.$field;break;}}
        $status=in_array('status',$columns,true)?'r.status':"'validated'";
        $params=['type_pending'=>$type,'type_reversed'=>$type];$scope=Auth::siteClause($site,$params);
        $allowed=[];foreach(explode(',',$policy['allowed_statuses']) as $i=>$value){$params['status'.$i]=trim($value);$allowed[]=':status'.$i;}
        $deleted=in_array('deleted_at',$columns,true)?' AND r.deleted_at IS NULL':'';
        if($type==='brs')$deleted.=" AND r.movement_type='in' AND r.weighing_id IS NOT NULL";
        $document="(SELECT d.document_number FROM documents d WHERE d.entity_type='$table' AND d.entity_id=r.id AND d.deleted_at IS NULL ORDER BY d.id DESC LIMIT 1)";
        $where="";
        if($search!==''){$where=" AND (CAST(r.$reference AS CHAR) LIKE :query_reference OR s.code LIKE :query_site OR $document LIKE :query_document)";foreach(['reference','site','document'] as $key)$params['query_'.$key]='%'.mb_substr($search,0,100).'%';}
        $rows=$this->query("SELECT r.id,$site site_id,s.code site_code,CAST(r.$reference AS CHAR) reference,$document document_number,$date operation_date,$status status FROM `$table` r JOIN sites s ON s.id=$site WHERE $status IN (".implode(',',$allowed).") $deleted $scope $where AND NOT EXISTS(SELECT 1 FROM cancellation_requests cr WHERE cr.entity_type=:type_pending AND cr.entity_id=r.id AND cr.status IN('pending_approval','approved')) AND NOT EXISTS(SELECT 1 FROM cancellation_reversals rv WHERE rv.entity_type=:type_reversed AND rv.entity_id=r.id) ORDER BY r.id DESC LIMIT 100",$params)->fetchAll();
        return array_values(array_filter($rows,static function($row)use($policy){return Auth::can($policy['component'],$policy['authorized_action'],$row['site_id']);}));
    }

    public function dashboardData()
    {
        $params = [];
        $siteClause = Auth::siteClause('cr.site_id', $params);

        return [
            'policies' => $this->query(
                "SELECT * FROM cancellation_policies WHERE status='active' ORDER BY label"
            )->fetchAll(),
            'requests' => $this->query(
                "SELECT cr.*,cp.label,cp.stock_effects,cp.blocking_dependencies,cp.document_types,
                        s.code site_code,requester.name requester_name,approver.name approver_name,
                        reversal.reversal_number
                 FROM cancellation_requests cr
                 JOIN cancellation_policies cp ON cp.entity_type=cr.entity_type
                 JOIN sites s ON s.id=cr.site_id
                 JOIN users requester ON requester.id=cr.requested_by
                 LEFT JOIN users approver ON approver.id=cr.approved_by
                 LEFT JOIN cancellation_reversals reversal ON reversal.request_id=cr.id
                 WHERE 1=1{$siteClause}
                 ORDER BY cr.requested_at DESC,cr.id DESC LIMIT 200",
                $params
            )->fetchAll(),
        ];
    }
}
