<?php

class Traceability extends Model
{
    protected $table = 'documents';

    public function filters()
    {
        $start = $_GET['start_date'] ?? date('Y-m-01', strtotime('-12 months'));
        $end = $_GET['end_date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) { $start=date('Y-m-01',strtotime('-12 months')); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) { $end=date('Y-m-d'); }
        $site = trim($_GET['site_id'] ?? '');
        if ($site !== '' && $site !== 'all') { Auth::requireSiteAccess((int)$site); }
        if ($site === 'all' && !Auth::canViewConsolidated()) { $site=(string)Auth::requireCurrentSite(); }
        return ['q'=>trim($_GET['q']??''),'site_id'=>$site,'start_date'=>$start,'end_date'=>$end];
    }

    public function sites()
    {
        if (Auth::canViewConsolidated()) return $this->query("SELECT id,code,name FROM sites WHERE status='active' AND deleted_at IS NULL ORDER BY code")->fetchAll();
        return Auth::sites();
    }

    public function search(array $f)
    {
        $out=[];
        if ($this->exists('agricultural_campaigns')) $out=array_merge($out,$this->agriculturalRoots($f));
        if ($this->exists('production_batches')) $out=array_merge($out,$this->millRoots($f));
        if ($this->exists('waste_stocks')) $out=array_merge($out,$this->wasteRoots($f));
        if ($this->exists('livestock_batches') && $this->exists('butchery_receipts')) $out=array_merge($out,$this->butcheryRoots($f));
        usort($out,function($a,$b){return strcmp($b['operation_date'],$a['operation_date']);});
        return array_slice($out,0,200);
    }

    public function dossier($chain,$id)
    {
        $allowed=['agriculture','mill','waste','butchery'];
        if(!in_array($chain,$allowed,true)) throw new RuntimeException('Chaîne de traçabilité inconnue.');
        $method=$chain.'Timeline';
        $rows=$this->$method((int)$id);
        if(!$rows) throw new RuntimeException('Dossier introuvable ou hors de vos sites autorisés.');
        return $rows;
    }

    private function agriculturalRoots($f)
    {
        $p=['start'=>$f['start_date'],'end'=>$f['end_date']];$scope=$this->scope('c.site_id',$f,$p);
        $like=$this->like($f,$p,'agriculture');
        $sql="SELECT DISTINCT 'agriculture' chain,c.id root_id,c.code reference,CONCAT('Campagne ',c.name) label,s.code site_code,c.start_date operation_date,c.status
              FROM agricultural_campaigns c JOIN sites s ON s.id=c.site_id
              LEFT JOIN agricultural_campaign_plots cp ON cp.campaign_id=c.id LEFT JOIN agricultural_plots ap ON ap.id=cp.plot_id
              LEFT JOIN agricultural_harvests h ON h.campaign_plot_id=cp.id LEFT JOIN agricultural_stocks ast ON ast.harvest_id=h.id
              LEFT JOIN agricultural_transports at ON at.stock_id=ast.id LEFT JOIN documents d ON d.entity_type='agricultural_transports' AND d.entity_id=at.id
              LEFT JOIN weighbridge_transports wt ON wt.agricultural_transport_id=at.id LEFT JOIN trucks tr ON tr.id=wt.truck_id LEFT JOIN products pr ON pr.id=wt.product_id LEFT JOIN suppliers sup ON sup.id=wt.supplier_id
              WHERE c.deleted_at IS NULL AND c.start_date BETWEEN :start AND :end{$scope}{$like}";
        return $this->all($sql,$p);
    }

    private function millRoots($f)
    {
        $p=['start'=>$f['start_date'],'end'=>$f['end_date']];$scope=$this->scope('pb.site_id',$f,$p);$like=$this->like($f,$p,'mill');
        return $this->all("SELECT DISTINCT 'mill' chain,pb.id root_id,pb.batch_number reference,CONCAT('Lot minoterie ',pb.batch_number) label,s.code site_code,DATE(pb.started_at) operation_date,pb.status FROM production_batches pb JOIN sites s ON s.id=pb.site_id JOIN machine_feeds mf ON mf.id=pb.machine_feed_id JOIN silos si ON si.id=mf.silo_id JOIN machines ma ON ma.id=mf.machine_id JOIN products pr ON pr.id=pb.product_id LEFT JOIN documents d ON d.entity_type IN('production_batches','silo_movements') AND d.entity_id IN(pb.id,mf.silo_movement_id) LEFT JOIN packaging pa ON pa.production_batch_id=pb.id LEFT JOIN finished_stocks fs ON fs.packaging_id=pa.id LEFT JOIN distributions di ON di.finished_stock_id=fs.id LEFT JOIN stock_transfer_items sti ON sti.source_finished_stock_id=fs.id LEFT JOIN stock_transfers st ON st.id=sti.transfer_id WHERE pb.deleted_at IS NULL AND DATE(pb.started_at) BETWEEN :start AND :end{$scope}{$like}",$p);
    }

    private function wasteRoots($f)
    {
        $p=['start'=>$f['start_date'],'end'=>$f['end_date']];$scope=$this->scope('pb.site_id',$f,$p);$like=$this->like($f,$p,'waste');
        return $this->all("SELECT DISTINCT 'waste' chain,pb.id root_id,pb.batch_number reference,CONCAT('Déchets du lot ',pb.batch_number) label,s.code site_code,DATE(pb.started_at) operation_date,pb.status FROM production_batches pb JOIN sites s ON s.id=pb.site_id JOIN waste_stocks ws ON ws.production_batch_id=pb.id LEFT JOIN waste_sales sale ON sale.waste_stock_id=ws.id LEFT JOIN waste_transfer_lines wtl ON wtl.source_waste_stock_id=ws.id LEFT JOIN waste_transfers wt ON wt.id=wtl.transfer_id LEFT JOIN pellet_order_waste_consumptions pc ON pc.waste_stock_id IN(ws.id,wtl.destination_waste_stock_id) LEFT JOIN pellet_orders po ON po.id=pc.pellet_order_id LEFT JOIN pellet_stocks ps ON ps.pellet_order_id=po.id LEFT JOIN pellet_packaging pp ON pp.pellet_order_id=po.id LEFT JOIN finished_stocks fs ON fs.id=pp.finished_stock_id LEFT JOIN products pr ON pr.id=fs.product_id LEFT JOIN documents d ON d.entity_type IN('production_batches','waste_transfers','pellet_orders') AND d.entity_id IN(pb.id,wt.id,po.id) WHERE pb.deleted_at IS NULL AND DATE(pb.started_at) BETWEEN :start AND :end{$scope}{$like}",$p);
    }

    private function butcheryRoots($f)
    {
        $p=['start'=>$f['start_date'],'end'=>$f['end_date']];$scope=$this->scope('lb.site_id',$f,$p);$like=$this->like($f,$p,'butchery');
        return $this->all("SELECT DISTINCT 'butchery' chain,lb.id root_id,lb.batch_number reference,CONCAT('Lot élevage ',lb.batch_number) label,s.code site_code,lb.started_at operation_date,lb.status FROM livestock_batches lb JOIN sites s ON s.id=lb.site_id LEFT JOIN livestock_transfers lt ON lt.batch_id=lb.id LEFT JOIN butchery_receipts br ON br.livestock_transfer_id=lt.id LEFT JOIN butchery_raw_lots raw ON raw.livestock_batch_id=lb.id LEFT JOIN butchery_order_consumptions boc ON boc.raw_lot_id=raw.id LEFT JOIN butchery_orders bo ON bo.id=boc.order_id LEFT JOIN butchery_order_outputs boo ON boo.order_id=bo.id LEFT JOIN butchery_finished_lots bf ON bf.order_output_id=boo.id LEFT JOIN butchery_sales bs ON bs.finished_lot_id=bf.id OR bs.raw_lot_id=raw.id LEFT JOIN butchery_transfers bt ON bt.finished_lot_id=bf.id LEFT JOIN documents d ON d.entity_type IN('livestock_transfers','butchery_receipts','butchery_orders','butchery_transfers') AND d.entity_id IN(lt.id,br.id,bo.id,bt.id) LEFT JOIN products pr ON 1=0 WHERE lb.deleted_at IS NULL AND lb.started_at BETWEEN :start AND :end{$scope}{$like}",$p);
    }

    private function agricultureTimeline($id)
    {
        $p=['id'=>$id];$scope=Auth::siteClause('c.site_id',$p);
        $root=$this->one("SELECT c.id,c.code reference,c.name label,c.status,c.start_date event_date,c.site_id,s.code site_code,c.created_by user_id,u.name user_name FROM agricultural_campaigns c JOIN sites s ON s.id=c.site_id LEFT JOIN users u ON u.id=c.created_by WHERE c.id=:id AND c.deleted_at IS NULL{$scope}",$p);if(!$root)return[];
        $rows=[];$this->node($rows,'Campagne','agricultural_campaigns',$root);
        foreach($this->all("SELECT cp.id,CONCAT(ap.code,' — ',ap.name) reference,'Parcelle' label,cp.status,c.start_date event_date,c.site_id,s.code site_code,NULL user_id,NULL user_name,cp.actual_area_ha quantity,'ha' unit FROM agricultural_campaign_plots cp JOIN agricultural_campaigns c ON c.id=cp.campaign_id JOIN agricultural_plots ap ON ap.id=cp.plot_id JOIN sites s ON s.id=c.site_id WHERE cp.campaign_id=?",[$id])as$r)$this->node($rows,'Parcelle','agricultural_campaign_plots',$r);
        foreach($this->all("SELECT h.id,h.harvest_number reference,'Récolte' label,h.status,h.harvested_at event_date,h.site_id,s.code site_code,h.created_by user_id,u.name user_name,h.net_weight_kg quantity,'kg' unit,h.validated_by,h.validated_at FROM agricultural_harvests h JOIN agricultural_campaign_plots cp ON cp.id=h.campaign_plot_id JOIN sites s ON s.id=h.site_id LEFT JOIN users u ON u.id=h.created_by WHERE cp.campaign_id=? AND h.deleted_at IS NULL",[$id])as$r)$this->node($rows,'Récolte','agricultural_harvests',$r);
        $this->appendAgricultureDownstream($rows,$id);return$this->enrich($rows);
    }

    private function appendAgricultureDownstream(&$rows,$id)
    {
        $sql="SELECT ast.id,CONCAT('STOCK-AG-',ast.id) reference,'Stock agricole' label,ast.status,ast.created_at event_date,ast.site_id,s.code site_code,NULL user_id,NULL user_name,ast.physical_quantity_kg quantity,'kg' unit FROM agricultural_stocks ast JOIN agricultural_harvests h ON h.id=ast.harvest_id JOIN agricultural_campaign_plots cp ON cp.id=h.campaign_plot_id JOIN sites s ON s.id=ast.site_id WHERE cp.campaign_id=?";foreach($this->all($sql,[$id])as$r)$this->node($rows,'Stock agricole','agricultural_stocks',$r);
        $sql="SELECT at.id,at.transport_number reference,'BT agricole' label,at.status,at.created_at event_date,at.source_site_id site_id,s.code site_code,at.created_by user_id,u.name user_name,at.quantity_kg quantity,'kg' unit,at.approved_by,at.dispatched_at validated_at FROM agricultural_transports at JOIN agricultural_stocks ast ON ast.id=at.stock_id JOIN agricultural_harvests h ON h.id=ast.harvest_id JOIN agricultural_campaign_plots cp ON cp.id=h.campaign_plot_id JOIN sites s ON s.id=at.source_site_id LEFT JOIN users u ON u.id=at.created_by WHERE cp.campaign_id=? AND at.deleted_at IS NULL";foreach($this->all($sql,[$id])as$r)$this->node($rows,'Transport','agricultural_transports',$r);
        if($this->hasColumn('weighbridge_transports','agricultural_transport_id')){$sql="SELECT wt.id,wt.transport_reference reference,CONCAT('Camion ',tr.plate_number) label,wt.status,wt.created_at event_date,wt.site_id,s.code site_code,wt.created_by user_id,u.name user_name,wt.shipped_quantity_kg quantity,'kg' unit FROM weighbridge_transports wt JOIN agricultural_transports at ON at.id=wt.agricultural_transport_id JOIN agricultural_stocks ast ON ast.id=at.stock_id JOIN agricultural_harvests h ON h.id=ast.harvest_id JOIN agricultural_campaign_plots cp ON cp.id=h.campaign_plot_id JOIN trucks tr ON tr.id=wt.truck_id JOIN sites s ON s.id=wt.site_id LEFT JOIN users u ON u.id=wt.created_by WHERE cp.campaign_id=?";foreach($this->all($sql,[$id])as$r)$this->node($rows,'Camion / BT','weighbridge_transports',$r);}
        if($this->hasColumn('weighbridge_transports','agricultural_transport_id')){foreach($this->all("SELECT w.id,w.reference,CONCAT('Pesée nette ',w.poids_net,' kg') label,w.status,w.weighed_at event_date,w.site_id,s.code site_code,w.created_by user_id,u.name user_name,w.poids_net quantity,'kg' unit,w.validated_by,w.quality_checked_at validated_at FROM weighings w JOIN weighbridge_transports wt ON wt.id=w.transport_id JOIN agricultural_transports at ON at.id=wt.agricultural_transport_id JOIN agricultural_stocks ast ON ast.id=at.stock_id JOIN agricultural_harvests h ON h.id=ast.harvest_id JOIN agricultural_campaign_plots cp ON cp.id=h.campaign_plot_id JOIN sites s ON s.id=w.site_id LEFT JOIN users u ON u.id=w.created_by WHERE cp.campaign_id=?",[$id])as$r)$this->node($rows,'Pesée / contrôle qualité','weighings',$r);foreach($this->all("SELECT sm.id,CONCAT('BRS-',sm.id) reference,si.name label,sm.status,sm.movement_at event_date,sm.site_id,s.code site_code,sm.created_by user_id,u.name user_name,sm.quantity_kg quantity,'kg' unit FROM silo_movements sm JOIN weighings w ON w.id=sm.weighing_id JOIN weighbridge_transports wt ON wt.id=w.transport_id JOIN agricultural_transports at ON at.id=wt.agricultural_transport_id JOIN agricultural_stocks ast ON ast.id=at.stock_id JOIN agricultural_harvests h ON h.id=ast.harvest_id JOIN agricultural_campaign_plots cp ON cp.id=h.campaign_plot_id JOIN silos si ON si.id=sm.silo_id JOIN sites s ON s.id=sm.site_id LEFT JOIN users u ON u.id=sm.created_by WHERE cp.campaign_id=?",[$id])as$r)$this->node($rows,'BRS / silo','silo_movements',$r);}
    }

    private function millTimeline($id){return$this->productionTimeline($id,false);}
    private function wasteTimeline($id){return$this->productionTimeline($id,true);}
    private function productionTimeline($id,$waste)
    {
        $p=['id'=>$id];$scope=Auth::siteClause('pb.site_id',$p);$rows=[];$b=$this->one("SELECT pb.id,pb.batch_number reference,CONCAT(ma.code,' — lot de production') label,pb.status,pb.started_at event_date,pb.site_id,s.code site_code,pb.created_by user_id,u.name user_name,pb.output_quantity_kg quantity,'kg' unit,pb.validated_by,pb.ended_at validated_at FROM production_batches pb JOIN machine_feeds mf ON mf.id=pb.machine_feed_id JOIN machines ma ON ma.id=mf.machine_id JOIN sites s ON s.id=pb.site_id LEFT JOIN users u ON u.id=pb.created_by WHERE pb.id=:id AND pb.deleted_at IS NULL{$scope}",$p);if(!$b)return[];$this->node($rows,'Lot de production','production_batches',$b);
        foreach($this->all("SELECT mf.id,COALESCE(d.document_number,CONCAT('BSS-',mf.id)) reference,CONCAT(si.name,' → ',ma.code) label,mf.status,mf.fed_at event_date,mf.site_id,s.code site_code,mf.created_by user_id,u.name user_name,mf.quantity_kg quantity,'kg' unit FROM machine_feeds mf JOIN production_batches pb ON pb.machine_feed_id=mf.id JOIN silos si ON si.id=mf.silo_id JOIN machines ma ON ma.id=mf.machine_id JOIN sites s ON s.id=mf.site_id LEFT JOIN users u ON u.id=mf.created_by LEFT JOIN documents d ON d.id=mf.bss_document_id WHERE pb.id=?",[$id])as$r)$this->node($rows,'Silo / BSS / machine','machine_feeds',$r);
        if($waste)$this->appendWaste($rows,$id);else$this->appendFlour($rows,$id);return$this->enrich($rows);
    }

    private function appendFlour(&$rows,$id)
    {
        foreach($this->all("SELECT b.id,CONCAT('VRAC-',b.id) reference,'Farine non emballée' label,b.status,b.created_at event_date,b.site_id,s.code site_code,NULL user_id,NULL user_name,b.quantity_kg quantity,'kg' unit FROM bulk_flour_stocks b JOIN sites s ON s.id=b.site_id WHERE b.production_batch_id=?",[$id])as$r)$this->node($rows,'Farine','bulk_flour_stocks',$r);
        foreach($this->all("SELECT p.id,CONCAT('EMB-',p.id) reference,CONCAT(p.bags_count,' sacs') label,p.status,p.packaged_at event_date,p.site_id,s.code site_code,p.created_by user_id,u.name user_name,p.total_weight_kg quantity,'kg' unit FROM packaging p JOIN sites s ON s.id=p.site_id LEFT JOIN users u ON u.id=p.created_by WHERE p.production_batch_id=? AND p.deleted_at IS NULL",[$id])as$r)$this->node($rows,'Emballage','packaging',$r);
        foreach($this->all("SELECT fs.id,CONCAT('STOCK-',fs.id) reference,pr.name label,fs.status,fs.created_at event_date,fs.site_id,s.code site_code,NULL user_id,NULL user_name,fs.total_weight_kg quantity,'kg' unit FROM finished_stocks fs JOIN packaging p ON p.id=fs.packaging_id JOIN products pr ON pr.id=fs.product_id JOIN sites s ON s.id=fs.site_id WHERE p.production_batch_id=? AND fs.deleted_at IS NULL",[$id])as$r)$this->node($rows,'Stock fini','finished_stocks',$r);
        foreach($this->all("SELECT di.id,COALESCE(di.exit_voucher,CONCAT('BL-',di.id)) reference,di.recipient_name label,di.status,di.distributed_at event_date,di.site_id,s.code site_code,di.created_by user_id,u.name user_name,di.total_weight_kg quantity,'kg' unit FROM distributions di JOIN finished_stocks fs ON fs.id=di.finished_stock_id JOIN packaging p ON p.id=fs.packaging_id JOIN sites s ON s.id=di.site_id LEFT JOIN users u ON u.id=di.created_by WHERE p.production_batch_id=?",[$id])as$r)$this->node($rows,'BL / destinataire','distributions',$r);
        foreach($this->all("SELECT st.id,st.transfer_number reference,CONCAT(ss.code,' → ',ds.code) label,st.status,st.created_at event_date,st.source_site_id site_id,ss.code site_code,st.created_by user_id,u.name user_name,SUM(sti.requested_kg) quantity,'kg' unit,st.approved_by,st.approved_at validated_at FROM stock_transfers st JOIN stock_transfer_items sti ON sti.transfer_id=st.id JOIN finished_stocks fs ON fs.id=sti.source_finished_stock_id JOIN packaging p ON p.id=fs.packaging_id JOIN sites ss ON ss.id=st.source_site_id JOIN sites ds ON ds.id=st.destination_site_id LEFT JOIN users u ON u.id=st.created_by WHERE p.production_batch_id=? GROUP BY st.id",[$id])as$r)$this->node($rows,'BTR / BTI','stock_transfers',$r);
    }

    private function appendWaste(&$rows,$id)
    {
        foreach($this->all("SELECT ws.id,CONCAT('DECHET-',ws.id) reference,wt.name label,ws.status,ws.created_at event_date,ws.site_id,s.code site_code,NULL user_id,NULL user_name,ws.quantity_kg quantity,'kg' unit FROM waste_stocks ws LEFT JOIN waste_types wt ON wt.id=ws.waste_type_id JOIN sites s ON s.id=ws.site_id WHERE ws.production_batch_id=?",[$id])as$r)$this->node($rows,'Déchet / stock tampon','waste_stocks',$r);
        foreach($this->all("SELECT sale.id,sale.sale_number reference,sale.customer_name label,sale.status,sale.sold_at event_date,ws.site_id,s.code site_code,sale.created_by user_id,u.name user_name,sale.quantity_kg quantity,'kg' unit FROM waste_sales sale JOIN waste_stocks ws ON ws.id=sale.waste_stock_id JOIN sites s ON s.id=ws.site_id LEFT JOIN users u ON u.id=sale.created_by WHERE ws.production_batch_id=?",[$id])as$r)$this->node($rows,'Vente brute','waste_sales',$r);
        foreach($this->all("SELECT po.id,po.order_number reference,'Lot aliment pelletisé' label,po.status,po.created_at event_date,po.site_id,s.code site_code,po.created_by user_id,u.name user_name,po.output_quantity_kg quantity,'kg' unit,po.validated_by,po.ended_at validated_at FROM pellet_orders po JOIN pellet_order_waste_consumptions pc ON pc.pellet_order_id=po.id JOIN waste_stocks ws ON ws.id=pc.waste_stock_id JOIN sites s ON s.id=po.site_id LEFT JOIN users u ON u.id=po.created_by WHERE ws.production_batch_id=?",[$id])as$r)$this->node($rows,'Pelletisation / OF','pellet_orders',$r);
        foreach($this->all("SELECT wt.id,wt.transfer_number reference,CONCAT(ss.code,' → ',ds.code) label,wt.status,wt.created_at event_date,wt.source_site_id site_id,ss.code site_code,wt.created_by user_id,u.name user_name,SUM(wtl.quantity_kg) quantity,'kg' unit,wt.approved_by,wt.shipped_at validated_at FROM waste_transfers wt JOIN waste_transfer_lines wtl ON wtl.transfer_id=wt.id JOIN waste_stocks ws ON ws.id=wtl.source_waste_stock_id JOIN sites ss ON ss.id=wt.source_site_id JOIN sites ds ON ds.id=wt.destination_site_id LEFT JOIN users u ON u.id=wt.created_by WHERE ws.production_batch_id=? GROUP BY wt.id",[$id])as$r)$this->node($rows,'BTI déchets','waste_transfers',$r);
        foreach($this->all("SELECT fs.id,CONCAT('STOCK-',fs.id) reference,pr.name label,fs.status,pp.packaged_at event_date,fs.site_id,s.code site_code,pp.created_by user_id,u.name user_name,fs.total_weight_kg quantity,'kg' unit FROM finished_stocks fs JOIN pellet_packaging pp ON pp.finished_stock_id=fs.id JOIN pellet_order_waste_consumptions pc ON pc.pellet_order_id=pp.pellet_order_id JOIN waste_stocks ws ON ws.id=pc.waste_stock_id JOIN products pr ON pr.id=fs.product_id JOIN sites s ON s.id=fs.site_id LEFT JOIN users u ON u.id=pp.created_by WHERE ws.production_batch_id=?",[$id])as$r)$this->node($rows,'Emballage / stock fini','finished_stocks',$r);
    }

    private function butcheryTimeline($id)
    {
        $p=['id'=>$id];$scope=Auth::siteClause('lb.site_id',$p);$rows=[];$b=$this->one("SELECT lb.id,lb.batch_number reference,sp.name label,lb.status,lb.started_at event_date,lb.site_id,s.code site_code,lb.created_by user_id,u.name user_name,lb.current_heads quantity,'têtes' unit FROM livestock_batches lb JOIN livestock_species sp ON sp.id=lb.species_id JOIN sites s ON s.id=lb.site_id LEFT JOIN users u ON u.id=lb.created_by WHERE lb.id=:id AND lb.deleted_at IS NULL{$scope}",$p);if(!$b)return[];$this->node($rows,'Lot élevage','livestock_batches',$b);
        foreach($this->all("SELECT lt.id,lt.transfer_number reference,'BTR vers boucherie' label,lt.status,lt.created_at event_date,lt.source_site_id site_id,s.code site_code,lt.created_by user_id,u.name user_name,COALESCE(lt.quantity_kg,lt.quantity_heads) quantity,lt.source_unit unit,lt.approved_by,lt.dispatched_at validated_at FROM livestock_transfers lt JOIN sites s ON s.id=lt.source_site_id LEFT JOIN users u ON u.id=lt.created_by WHERE lt.batch_id=? AND lt.deleted_at IS NULL",[$id])as$r)$this->node($rows,'BTR','livestock_transfers',$r);
        foreach($this->all("SELECT br.id,br.receipt_number reference,CONCAT('Contrôle sanitaire — ',br.source_type) label,br.status,br.received_at event_date,br.site_id,s.code site_code,br.created_by user_id,u.name user_name,COALESCE(br.received_weight_kg,br.received_heads) quantity,br.received_unit unit,br.validated_by FROM butchery_receipts br JOIN livestock_transfers lt ON lt.id=br.livestock_transfer_id JOIN sites s ON s.id=br.site_id LEFT JOIN users u ON u.id=br.created_by WHERE lt.batch_id=?",[$id])as$r)$this->node($rows,'Réception / sanitaire','butchery_receipts',$r);
        foreach($this->all("SELECT raw.id,raw.lot_number reference,bi.name label,raw.status,raw.created_at event_date,raw.site_id,s.code site_code,NULL user_id,NULL user_name,raw.quantity_kg quantity,'kg' unit FROM butchery_raw_lots raw JOIN butchery_items bi ON bi.id=raw.item_id JOIN sites s ON s.id=raw.site_id WHERE raw.livestock_batch_id=?",[$id])as$r)$this->node($rows,'Lot matière','butchery_raw_lots',$r);
        foreach($this->all("SELECT bo.id,bo.order_number reference,'OF boucherie' label,bo.status,bo.created_at event_date,bo.site_id,s.code site_code,bo.created_by user_id,u.name user_name,bo.output_kg quantity,'kg' unit,bo.validated_by,bo.validated_at FROM butchery_orders bo JOIN butchery_order_consumptions boc ON boc.order_id=bo.id JOIN butchery_raw_lots raw ON raw.id=boc.raw_lot_id JOIN sites s ON s.id=bo.site_id LEFT JOIN users u ON u.id=bo.created_by WHERE raw.livestock_batch_id=?",[$id])as$r)$this->node($rows,'OF / produit dérivé','butchery_orders',$r);
        foreach($this->all("SELECT bf.id,bf.lot_number reference,bi.name label,bf.status,bf.created_at event_date,bf.site_id,s.code site_code,NULL user_id,NULL user_name,bf.quantity_kg quantity,'kg' unit FROM butchery_finished_lots bf JOIN butchery_order_outputs boo ON boo.id=bf.order_output_id JOIN butchery_orders bo ON bo.id=boo.order_id JOIN butchery_order_consumptions boc ON boc.order_id=bo.id JOIN butchery_raw_lots raw ON raw.id=boc.raw_lot_id JOIN butchery_items bi ON bi.id=bf.item_id JOIN sites s ON s.id=bf.site_id WHERE raw.livestock_batch_id=?",[$id])as$r)$this->node($rows,'Stock produit','butchery_finished_lots',$r);
        foreach($this->all("SELECT bs.id,bs.sale_number reference,bs.customer_name label,bs.status,bs.sold_at event_date,bs.site_id,s.code site_code,bs.created_by user_id,u.name user_name,bs.quantity_kg quantity,'kg' unit FROM butchery_sales bs LEFT JOIN butchery_finished_lots bf ON bf.id=bs.finished_lot_id LEFT JOIN butchery_order_outputs boo ON boo.id=bf.order_output_id LEFT JOIN butchery_order_consumptions boc ON boc.order_id=boo.order_id AND boc.raw_lot_id IS NOT NULL LEFT JOIN butchery_raw_lots raw ON raw.id=COALESCE(bs.raw_lot_id,boc.raw_lot_id) JOIN sites s ON s.id=bs.site_id LEFT JOIN users u ON u.id=bs.created_by WHERE raw.livestock_batch_id=?",[$id])as$r)$this->node($rows,'Vente','butchery_sales',$r);
        foreach($this->all("SELECT bt.id,bt.transfer_number reference,CONCAT(ss.code,' → ',ds.code) label,bt.status,bt.created_at event_date,bt.source_site_id site_id,ss.code site_code,bt.created_by user_id,u.name user_name,bt.quantity_kg quantity,'kg' unit,bt.approved_by,bt.dispatched_at validated_at FROM butchery_transfers bt JOIN butchery_finished_lots bf ON bf.id=bt.finished_lot_id JOIN butchery_order_outputs boo ON boo.id=bf.order_output_id JOIN butchery_order_consumptions boc ON boc.order_id=boo.order_id AND boc.raw_lot_id IS NOT NULL JOIN butchery_raw_lots raw ON raw.id=boc.raw_lot_id JOIN sites ss ON ss.id=bt.source_site_id JOIN sites ds ON ds.id=bt.destination_site_id LEFT JOIN users u ON u.id=bt.created_by WHERE raw.livestock_batch_id=?",[$id])as$r)$this->node($rows,'Transfert DECO','butchery_transfers',$r);
        return$this->enrich($rows);
    }

    private function enrich($rows)
    {
        foreach($rows as &$r){$validator=$r['validated_by']??$r['approved_by']??null;$r['validator_name']=$validator?$this->one('SELECT name FROM users WHERE id=?',[$validator])['name']??null:null;$r['documents']=$this->exists('documents')?$this->all("SELECT d.id,dt.display_code,d.document_number,d.status,d.document_date,u.name creator_name,v.name validator_name FROM documents d JOIN document_types dt ON dt.id=d.document_type_id LEFT JOIN users u ON u.id=d.created_by LEFT JOIN users v ON v.id=d.validated_by WHERE d.entity_type=? AND d.entity_id=? AND d.deleted_at IS NULL",[$r['entity_type'],$r['entity_id']]):[];$r['movements']=$this->movements($r['entity_type'],$r['entity_id']);$r['nonconformities']=$this->nonconformities($r['entity_type'],$r['entity_id']);$r['corrections']=$this->exists('cancellation_requests')?$this->all("SELECT cr.request_number,cr.status,cr.reason,rv.reversal_number,cr.requested_at FROM cancellation_requests cr LEFT JOIN cancellation_reversals rv ON rv.request_id=cr.id WHERE cr.entity_type=? AND cr.entity_id=?",[$this->cancelType($r['entity_type']),$r['entity_id']]):[];}$this->sortTimeline($rows);return$rows;
    }

    private function movements($e,$id){$map=['agricultural_stocks'=>['agricultural_stock_movements','stock_id','movement_at'],'waste_stocks'=>['waste_stock_movements','waste_stock_id','movement_at'],'butchery_raw_lots'=>['butchery_raw_movements','raw_lot_id','created_at'],'butchery_finished_lots'=>['butchery_finished_movements','finished_lot_id','created_at'],'finished_stocks'=>['stock_movements','finished_stock_id','movement_at']];if(!isset($map[$e])||!$this->exists($map[$e][0]))return[];$m=$map[$e];return$this->all("SELECT *,{$m[2]} movement_date FROM {$m[0]} WHERE {$m[1]}=? ORDER BY {$m[2]}",[$id]);}
    private function nonconformities($e,$id){if($e==='weighings'&&$this->exists('weighbridge_non_conformities'))return$this->all('SELECT reference,status,description,created_at FROM weighbridge_non_conformities WHERE weighing_id=? AND deleted_at IS NULL',[$id]);if($e==='stock_transfers'&&$this->exists('transfer_non_conformities'))return$this->all('SELECT reference,status,description,created_at FROM transfer_non_conformities WHERE transfer_id=?',[$id]);if($e==='butchery_receipts'&&$this->exists('butchery_sanitary_controls'))return$this->all("SELECT CONCAT('SAN-',id) reference,decision status,notes description,controlled_at created_at FROM butchery_sanitary_controls WHERE receipt_id=? AND decision='reject'",[$id]);return[];}
    private function cancelType($e){$m=['weighings'=>'weighing','silo_movements'=>'brs','machine_feeds'=>'bss','production_batches'=>'production','packaging'=>'packaging','distributions'=>'distribution','waste_sales'=>'waste_sale','waste_transfers'=>'waste_transfer','pellet_orders'=>'pelletization','butchery_receipts'=>'butchery_receipt'];return$m[$e]??$e;}
    private function node(&$rows,$stage,$type,$r){$r['stage']=$stage;$r['entity_type']=$type;$r['entity_id']=$r['id'];$r['quantity']=$r['quantity']??null;$r['unit']=$r['unit']??'';$rows[]=$r;}
    private function like($f,&$p,$chain){if($f['q']==='')return'';$fields=['agriculture'=>['c.code','h.harvest_number','at.transport_number','d.document_number','tr.plate_number','pr.name','sup.name','s.code'],'mill'=>['pb.batch_number','d.document_number','ma.code','pr.name','di.recipient_name','di.exit_voucher','st.transfer_number','s.code'],'waste'=>['pb.batch_number','sale.sale_number','wt.transfer_number','po.order_number','d.document_number','pr.name','s.code'],'butchery'=>['lb.batch_number','lt.transfer_number','br.receipt_number','raw.lot_number','bo.order_number','bf.lot_number','bs.sale_number','bt.transfer_number','d.document_number','s.code']];$parts=[];foreach($fields[$chain] as$i=>$field){$key='q'.$i;$p[$key]='%'.$f['q'].'%';$parts[]=$field.' LIKE :'.$key;}return' AND ('.implode(' OR ',$parts).')';}
    private function scope($column,$f,&$p){if($f['site_id']!==''&&$f['site_id']!=='all'){$p['site']=(int)$f['site_id'];return" AND {$column}=:site";}$extra=Auth::siteClause($column,$p);return$extra;}
    private function sortTimeline(&$r){usort($r,function($a,$b){return strcmp((string)$a['event_date'],(string)$b['event_date']);});}
    private function exists($t){$q=$this->db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$q->execute([$t]);return(bool)$q->fetchColumn();}
    private function hasColumn($t,$c){$q=$this->db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$q->execute([$t,$c]);return(bool)$q->fetchColumn();}
    private function one($s,$p=[]){$q=$this->db->prepare($s);$q->execute($p);return$q->fetch();}
    public function all($s=null,$p=[]){if($s===null)return parent::all();$q=$this->db->prepare($s);$q->execute($p);return$q->fetchAll();}
}
