<?php

class Weighing extends Model
{
    protected $table = 'weighings';

    public function allWithRelations()
    {
        $params = [];
        $siteClause = Auth::siteClause('weighings.site_id', $params);
        return $this->query(
            "SELECT weighings.*,
                    COALESCE(suppliers.name, origin_site.name, 'Origine non renseignée') AS supplier_name,
                    trucks.plate_number,
                    trucks.driver_name,
                    products.name AS product_name
             FROM weighings
             LEFT JOIN suppliers ON suppliers.id = weighings.supplier_id
             LEFT JOIN weighbridge_transports transport ON transport.id=weighings.transport_id
             LEFT JOIN sites origin_site ON origin_site.id=transport.origin_site_id
             INNER JOIN trucks ON trucks.id = weighings.truck_id
             INNER JOIN products ON products.id = weighings.product_id
             WHERE weighings.deleted_at IS NULL{$siteClause}
             ORDER BY weighings.weighed_at DESC",
            $params
        )->fetchAll();
    }

    public function pending()
    {
        $params = [];
        $siteClause = Auth::siteClause('weighings.site_id', $params);
        return $this->query(
            "SELECT weighings.*,
                    COALESCE(suppliers.name, origin_site.name, 'Origine non renseignée') AS supplier_name,
                    trucks.plate_number,
                    trucks.driver_name,
                    products.name AS product_name
             FROM weighings
             LEFT JOIN suppliers ON suppliers.id = weighings.supplier_id
             LEFT JOIN weighbridge_transports transport ON transport.id=weighings.transport_id
             LEFT JOIN sites origin_site ON origin_site.id=transport.origin_site_id
             INNER JOIN trucks ON trucks.id = weighings.truck_id
             INNER JOIN products ON products.id = weighings.product_id
             WHERE weighings.status = 'pending'
               AND weighings.deleted_at IS NULL{$siteClause}
             ORDER BY weighings.weighed_at ASC",
            $params
        )->fetchAll();
    }

    public function findDetailed($id)
    {
        $params = ['id' => $id];
        $siteClause = Auth::siteClause('weighings.site_id', $params);
        return $this->query(
            "SELECT weighings.*,
                    COALESCE(suppliers.name, origin_site.name, 'Origine non renseignée') AS supplier_name,
                    trucks.plate_number,
                    trucks.driver_name,
                    trucks.driver_phone,
                    products.name AS product_name,
                    users.name AS agent_name,
                    validators.name AS validator_name,
                    silo_movements.silo_id,
                    silos.name AS silo_name,
                    silos.code AS silo_code,
                    transport.origin_type, transport.transport_reference, transport.route_description, transport.toll_amount, transport.toll_details,
                    origin_site.name AS origin_site_name, COALESCE(bt_document.document_number,transport.transport_reference) AS bt_number,
                    official_document.document_number AS official_document_number,
                    nc.reference AS nc_reference, wr.return_number, wr.status AS return_status
             FROM weighings
             LEFT JOIN suppliers ON suppliers.id = weighings.supplier_id
             LEFT JOIN weighbridge_transports transport ON transport.id=weighings.transport_id
             LEFT JOIN sites origin_site ON origin_site.id=transport.origin_site_id
             INNER JOIN trucks ON trucks.id = weighings.truck_id
             INNER JOIN products ON products.id = weighings.product_id
             LEFT JOIN users ON users.id = weighings.created_by
             LEFT JOIN users validators ON validators.id = weighings.validated_by
             LEFT JOIN silo_movements ON silo_movements.weighing_id = weighings.id AND silo_movements.deleted_at IS NULL
             LEFT JOIN silos ON silos.id = COALESCE(weighings.destination_silo_id,silo_movements.silo_id)
             LEFT JOIN documents bt_document ON bt_document.entity_type='weighbridge_transports' AND bt_document.entity_id=transport.id AND bt_document.document_type_id=(SELECT id FROM document_types WHERE code='BT' LIMIT 1) AND bt_document.deleted_at IS NULL
             LEFT JOIN weighbridge_non_conformities nc ON nc.weighing_id=weighings.id AND nc.deleted_at IS NULL
             LEFT JOIN weighbridge_returns wr ON wr.weighing_id=weighings.id AND wr.deleted_at IS NULL
             LEFT JOIN documents official_document ON official_document.entity_type='weighings' AND official_document.entity_id=weighings.id AND official_document.document_type_id=(SELECT id FROM document_types WHERE code='BRS' LIMIT 1) AND official_document.deleted_at IS NULL
             WHERE weighings.id = :id
               AND weighings.deleted_at IS NULL{$siteClause}
             LIMIT 1",
            $params
        )->fetch();
    }

    public function suppliers()
    {
        return $this->query(
            "SELECT id, name
             FROM suppliers
             WHERE deleted_at IS NULL
               AND status IN ('active', 'validated')
             ORDER BY name ASC"
        )->fetchAll();
    }

    public function trucks()
    {
        return $this->query(
            "SELECT trucks.id, trucks.plate_number, trucks.driver_name, trucks.supplier_id, suppliers.name AS supplier_name
             FROM trucks
             LEFT JOIN suppliers ON suppliers.id = trucks.supplier_id
             WHERE trucks.deleted_at IS NULL
               AND trucks.status IN ('active', 'validated')
             ORDER BY trucks.plate_number ASC"
        )->fetchAll();
    }

    public function products()
    {
        return $this->query(
            "SELECT id, name
             FROM products
             WHERE deleted_at IS NULL
               AND status IN ('active', 'validated')
               AND category = 'raw_material'
             ORDER BY name ASC"
        )->fetchAll();
    }

    public function silos()
    {
        $params = [];
        $siteClause = Auth::siteClause('silos.site_id', $params);
        return $this->query(
            "SELECT id, name, code, product_id, capacity_kg, current_stock_kg
             FROM silos
             WHERE deleted_at IS NULL
               AND status IN ('active', 'validated'){$siteClause}
             ORDER BY name ASC",
            $params
        )->fetchAll();
    }

    public function availableTransports()
    {
        require_once __DIR__ . '/WeighbridgeTransport.php';
        return (new WeighbridgeTransport())->available();
    }

    public function createEntry(array $data, array $user)
    {
        $siteId = Auth::requireCurrentSite();
        $this->db->beginTransaction();

        try {
            if (empty($data['transport_id'])) {
                $transport=['id'=>null,'site_id'=>$siteId,'supplier_id'=>$data['supplier_id'],'truck_id'=>$this->findOrCreateTruck($data),'product_id'=>$data['product_id'],'shipped_quantity_kg'=>$data['shipped_quantity_kg']??$data['poids_brut']];
            } else {
                $transport = $this->query("SELECT * FROM weighbridge_transports WHERE id=:id AND status='in_transit' AND deleted_at IS NULL FOR UPDATE", ['id'=>$data['transport_id']])->fetch();
                if (!$transport) { throw new RuntimeException('BT introuvable, déjà arrivé ou non en transit.'); }
                Auth::requireSiteAccess($transport['site_id']);
                if ((int)$transport['site_id'] !== (int)$siteId) { throw new RuntimeException('Le BT ne correspond pas au site sélectionné.'); }
                $existing=$this->query('SELECT id FROM weighings WHERE transport_id=:id AND deleted_at IS NULL FOR UPDATE',['id'=>$transport['id']])->fetch();
                if($existing){throw new RuntimeException('Ce BT possède déjà une première pesée.');}
            }

            $this->query(
                "INSERT INTO weighings (
                    site_id, transport_id, supplier_id, truck_id, product_id, reference, poids_brut, poids_tare, poids_net,shipped_quantity_kg,
                    weighed_at, status, created_by
                 ) VALUES (
                    :site_id, :transport_id, :supplier_id, :truck_id, :product_id, :reference, :poids_brut, 0, 0,:shipped,
                    NOW(), 'pending', :created_by
                 )",
                [
                    'site_id' => $siteId, 'transport_id'=>$transport['id'],'supplier_id' => $transport['supplier_id'],
                    'truck_id' => $transport['truck_id'],
                    'product_id' => $transport['product_id'],'shipped'=>$transport['shipped_quantity_kg'],
                    'reference' => $this->reference(),
                    'poids_brut' => $data['poids_brut'],
                    'created_by' => $user['id'] ?? null,
                ]
            );

            $id = $this->db->lastInsertId();
            if($transport['id']){$this->query("UPDATE weighbridge_transports SET status='arrived',arrived_at=COALESCE(arrived_at,NOW()) WHERE id=:id",['id'=>$transport['id']]);}
            $this->logActivity('create', 'pont-bascule', 'weighings', $id, 'Première pesée brute; aucun mouvement de stock.', null, $data, $user);
            $this->db->commit();

            return $id;
        } catch (Exception $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function saveExitDraft($id, array $data, array $user)
    {
        $this->db->beginTransaction();
        try {
            $weighing=$this->query('SELECT * FROM weighings WHERE id=:id AND deleted_at IS NULL FOR UPDATE',['id'=>$id])->fetch();
            if(!$weighing || $weighing['status']!=='pending')throw new RuntimeException('Cette pesée ne peut plus être préparée.');
            Auth::requireSiteAccess($weighing['site_id']);
            Auth::requirePermission('weighings','update',$weighing['site_id']);
            $tare=(float)($data['poids_tare']??-1);
            if(!isset($data['poids_tare']) || !is_numeric($data['poids_tare']) || $tare<0 || $tare>=(float)$weighing['poids_brut'])throw new RuntimeException('La tare doit être positive ou nulle et inférieure au poids brut.');
            $silo=$this->query('SELECT id FROM silos WHERE id=:id AND site_id=:site AND deleted_at IS NULL',['id'=>$data['silo_id']??0,'site'=>$weighing['site_id']])->fetch();
            if(!$silo)throw new RuntimeException('Sélectionnez un silo du site de réception.');
            $fields=['poids_tare','silo_id','humidity_percent','impurities_percent','weight_tolerance_percent','max_humidity_percent','max_impurities_percent','decision','quality_notes'];
            $draft=array_intersect_key($data,array_flip($fields));
            foreach(['humidity_percent','impurities_percent','weight_tolerance_percent','max_humidity_percent','max_impurities_percent'] as$field){
                if(!isset($draft[$field])||!is_numeric($draft[$field])||(float)$draft[$field]<0)throw new RuntimeException('Renseignez les mesures et seuils de qualité.');
            }
            if(!in_array($draft['decision']??'',['accept','reject'],true))throw new RuntimeException('Décision invalide.');
            if($draft['decision']==='reject'&&trim($draft['quality_notes']??'')==='')throw new RuntimeException('Le motif du refus est obligatoire.');
            $this->query('UPDATE weighings SET exit_draft=:draft,exit_prepared_by=:actor,exit_prepared_at=NOW() WHERE id=:id',['draft'=>json_encode($draft,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'actor'=>$user['id'],'id'=>$id]);
            $this->logActivity('prepare_exit','pont-bascule','weighings',$id,'Sortie enregistrée à valider, sans mouvement de stock.',null,$draft,$user);
            $this->db->commit();
        } catch(Exception $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function validateExit($id, array $data, array $user)
    {
        $data=array_merge(['humidity_percent'=>0,'impurities_percent'=>0,'weight_tolerance_percent'=>2,'max_humidity_percent'=>14,'max_impurities_percent'=>2,'quality_notes'=>'','decision'=>'accept'],$data);
        $this->db->beginTransaction();

        try {
            $weighing = $this->query(
                "SELECT *
                 FROM weighings
                 WHERE id = :id
                   AND deleted_at IS NULL
                 FOR UPDATE",
                ['id' => $id]
            )->fetch();

            if (!$weighing) {
                throw new RuntimeException('Pesee introuvable.');
            }
            Auth::requireSiteAccess($weighing['site_id']);
            if(!empty($weighing['exit_prepared_by']) && (int)$weighing['exit_prepared_by']===(int)$user['id']&&!Auth::canSelfValidate($user['id']))throw new RuntimeException('La sortie doit être validée par un autre utilisateur que son préparateur.');
            require_once dirname(__DIR__) . '/services/AuthorizationService.php';
            (new AuthorizationService($this->db))->assertCanValidateRecord($user['id'] ?? null, 'weighings', $weighing);

            if ($weighing['status'] !== 'pending') {
                throw new RuntimeException('Cette pesée a déjà fait l’objet d’une décision.');
            }

            $tare=(float)$data['poids_tare'];
            if($tare<0||$tare>(float)$weighing['poids_brut']){throw new RuntimeException('La tare doit être comprise entre zéro et le poids brut.');}
            $poidsNet = (float) $weighing['poids_brut'] - $tare;
            if($poidsNet<=0){throw new RuntimeException('Le poids net doit être supérieur à zéro.');}
            $shipped=(float)$weighing['shipped_quantity_kg'];
            $variance=$poidsNet-$shipped;
            $variancePct=$shipped>0?($variance/$shipped*100):null;
            $weightOk=$variancePct===null||abs($variancePct)<=(float)$data['weight_tolerance_percent'];
            $qualityOk=(float)$data['humidity_percent']<=(float)$data['max_humidity_percent']&&(float)$data['impurities_percent']<=(float)$data['max_impurities_percent'];
            $conform=$weightOk&&$qualityOk;
            $decision=$data['decision'];

            if(!$conform){$this->createNonConformity($weighing,$poidsNet,$variance,$data,$decision,$user);}
            if($decision==='reject'){
                $transport=$weighing['transport_id']?$this->query('SELECT * FROM weighbridge_transports WHERE id=:id FOR UPDATE',['id'=>$weighing['transport_id']])->fetch():null;
                $status=$transport&&$transport['origin_type']==='internal_farm'?'return_pending':'rejected';
                $this->query("UPDATE weighings SET poids_tare=:tare,poids_net=:net,weight_variance_kg=:variance,weight_variance_percent=:pct,weight_tolerance_percent=:tol,humidity_percent=:humidity,impurities_percent=:impurities,max_humidity_percent=:max_h,max_impurities_percent=:max_i,conformity_status='rejected',quality_notes=:notes,quality_checked_by=:user,quality_checked_at=NOW(),unloaded_at=NOW(),status=:status,validated_by=:user2 WHERE id=:id",['tare'=>$tare,'net'=>$poidsNet,'variance'=>$variance,'pct'=>$variancePct,'tol'=>$data['weight_tolerance_percent'],'humidity'=>$data['humidity_percent'],'impurities'=>$data['impurities_percent'],'max_h'=>$data['max_humidity_percent'],'max_i'=>$data['max_impurities_percent'],'notes'=>$data['quality_notes'],'user'=>$user['id'],'status'=>$status,'user2'=>$user['id'],'id'=>$id]);
                if($transport){$this->query('UPDATE weighbridge_transports SET status=:status,completed_at=NOW() WHERE id=:id',['status'=>$status,'id'=>$transport['id']]);}
                if($status==='return_pending'){$this->createReturn($weighing,$data,$user);}
                $this->logActivity('reject_weighing','pont-bascule','weighings',$id,$status==='return_pending'?'Livraison interne refusée; retour obligatoire créé.':'Livraison externe refusée; aucun stock DAGRIL impacté.',$weighing,['status'=>$status],$user);
                $this->db->commit(); return;
            }

            $silo = $this->query(
                "SELECT *
                 FROM silos
                 WHERE id = :id
                   AND deleted_at IS NULL
                 FOR UPDATE",
                ['id' => $data['silo_id']]
            )->fetch();

            if (!$silo) {
                throw new RuntimeException('Silo destination introuvable.');
            }
            if (!in_array($silo['status'], ['active', 'validated'], true)) {
                throw new RuntimeException('Ce silo est inactif et ne peut pas recevoir de livraison.');
            }
            Auth::requireSiteAccess($silo['site_id']);
            if ((int) $silo['site_id'] !== (int) $weighing['site_id']) {
                throw new RuntimeException('Le silo et la pesee doivent appartenir au meme site.');
            }

            $stockBefore = (float) $silo['current_stock_kg'];
            $stockAfter = $stockBefore + $poidsNet;
            if($silo['product_id']!==null&&(int)$silo['product_id']!==(int)$weighing['product_id']){throw new RuntimeException('Produit incompatible avec le silo sélectionné.');}
            if($stockAfter<0||(float)$silo['capacity_kg']>0&&$stockAfter>(float)$silo['capacity_kg']){throw new RuntimeException('La capacité du silo serait dépassée.');}

            $this->query(
                "UPDATE weighings
                 SET poids_tare = :poids_tare,
                     poids_net = :poids_net,destination_silo_id=:silo,weight_variance_kg=:variance,weight_variance_percent=:pct,
                     weight_tolerance_percent=:tol,humidity_percent=:humidity,impurities_percent=:impurities,max_humidity_percent=:max_h,max_impurities_percent=:max_i,
                     conformity_status=:conformity,quality_notes=:notes,quality_checked_by=:quality_user,quality_checked_at=NOW(),unloaded_at=NOW(),
                     status = 'validated',
                     validated_by = :validated_by
                 WHERE id = :id",
                [
                    'poids_tare' => $data['poids_tare'],
                    'poids_net' => $poidsNet,'silo'=>$silo['id'],'variance'=>$variance,'pct'=>$variancePct,'tol'=>$data['weight_tolerance_percent'],
                    'humidity'=>$data['humidity_percent'],'impurities'=>$data['impurities_percent'],'max_h'=>$data['max_humidity_percent'],'max_i'=>$data['max_impurities_percent'],
                    'conformity'=>$conform?'conform':'non_conform','notes'=>$data['quality_notes'],'quality_user'=>$user['id'],
                    'validated_by' => $user['id'] ?? null,
                    'id' => $id,
                ]
            );

            $this->query(
                "INSERT INTO silo_movements (
                    site_id, silo_id, product_id, weighing_id, movement_type, quantity_kg,
                    stock_before_kg, stock_after_kg, movement_at, status, created_by
                 ) VALUES (
                    :site_id, :silo_id, :product_id, :weighing_id, 'in', :quantity_kg,
                    :stock_before_kg, :stock_after_kg, NOW(), 'validated', :created_by
                 )",
                [
                    'site_id' => $weighing['site_id'], 'silo_id' => $data['silo_id'],
                    'product_id' => $weighing['product_id'],
                    'weighing_id' => $id,
                    'quantity_kg' => $poidsNet,
                    'stock_before_kg' => $stockBefore,
                    'stock_after_kg' => $stockAfter,
                    'created_by' => $user['id'] ?? null,
                ]
            );
            $movementId = $this->db->lastInsertId();

            $this->query(
                "UPDATE silos
                 SET current_stock_kg = :stock,product_id=COALESCE(product_id,:product)
                 WHERE id = :id",
                [
                    'stock' => $stockAfter,
                    'id' => $data['silo_id'],'product'=>$weighing['product_id'],
                ]
            );

            require_once dirname(__DIR__) . '/services/DocumentService.php';
            (new DocumentService($this->db))->register('BRS', $weighing['site_id'], 'weighings', $id, 'validated', date('Y-m-d H:i:s'), $user);
            if($weighing['transport_id']){$this->query("UPDATE weighbridge_transports SET status='completed',completed_at=NOW() WHERE id=:id",['id'=>$weighing['transport_id']]);}

            if (!empty($weighing['transport_id'])) {
                $this->query("UPDATE agricultural_transports a JOIN weighbridge_transports t ON t.agricultural_transport_id=a.id SET a.status='received',a.received_at=NOW() WHERE t.id=:id AND a.status='in_transit' AND a.deleted_at IS NULL", ['id'=>$weighing['transport_id']]);
            }

            $this->logActivity(
                'validate_weighing',
                'pont-bascule',
                'weighings',
                $id,
                'Validation pesee et entree silo.',
                $weighing,
                [
                    'poids_tare' => $data['poids_tare'],
                    'poids_net' => $poidsNet,
                    'status' => 'validated',
                    'silo_id' => $data['silo_id'],
                ],
                $user
            );
            $this->logActivity(
                'stock_movement',
                'silos',
                'silo_movements',
                $movementId,
                'Mouvement stock silo entree depuis pesee.',
                ['silo_id' => $data['silo_id'], 'stock_kg' => $stockBefore],
                ['silo_id' => $data['silo_id'], 'stock_kg' => $stockAfter, 'quantity_kg' => $poidsNet],
                $user
            );

            $this->db->commit();
        } catch (Exception $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function createNonConformity(array $w,$net,$variance,array $data,$decision,array $user)
    {
        $weightBad=abs((float)($w['shipped_quantity_kg']?($variance/$w['shipped_quantity_kg']*100):0))>(float)$data['weight_tolerance_percent'];
        $qualityBad=(float)$data['humidity_percent']>(float)$data['max_humidity_percent']||(float)$data['impurities_percent']>(float)$data['max_impurities_percent'];
        $type=$weightBad&&$qualityBad?'weight_and_quality':($qualityBad?'quality':'weight');
        $ref='NC-PB-'.date('Ymd-His').'-'.random_int(100,999);
        $status=$decision==='reject'?'open':'accepted_with_reservation';
        $this->query('INSERT INTO weighbridge_non_conformities(weighing_id,reference,discrepancy_type,shipped_quantity_kg,net_quantity_kg,variance_kg,humidity_percent,impurities_percent,description,status,created_by) VALUES(:weighing,:ref,:type,:shipped,:net,:variance,:humidity,:impurities,:description,:status,:user)',['weighing'=>$w['id'],'ref'=>$ref,'type'=>$type,'shipped'=>$w['shipped_quantity_kg'],'net'=>$net,'variance'=>$variance,'humidity'=>$data['humidity_percent'],'impurities'=>$data['impurities_percent'],'description'=>$data['quality_notes']?:'Écart automatique détecté au pont-bascule.','status'=>$status,'user'=>$user['id']]);
        $ncId=$this->db->lastInsertId(); require_once dirname(__DIR__).'/services/DocumentService.php';
        (new DocumentService($this->db))->register('NC',$w['site_id'],'weighbridge_non_conformities',$ncId,'approved',date('Y-m-d H:i:s'),$user);
    }

    private function createReturn(array $w,array $data,array $user)
    {
        $number='RET-PB-'.date('Ymd-His').'-'.random_int(100,999);
        $this->query("INSERT INTO weighbridge_returns(weighing_id,return_number,reason,status,planned_at,created_by) VALUES(:weighing,:number,:reason,'planned',NOW(),:user)",['weighing'=>$w['id'],'number'=>$number,'reason'=>$data['quality_notes']?:'Livraison interne refusée','user'=>$user['id']]);
        $returnId=$this->db->lastInsertId(); require_once dirname(__DIR__).'/services/DocumentService.php';
        (new DocumentService($this->db))->register('BRET',$w['site_id'],'weighbridge_returns',$returnId,'approved',date('Y-m-d H:i:s'),$user);
    }

    public function progressReturn($weighingId,$action,array $user)
    {
        $map=['dispatch'=>['planned','dispatched','dispatched_at'],'receive'=>['dispatched','received','received_at'],'close'=>['received','closed',null]];
        if(!isset($map[$action])){throw new RuntimeException('Action de retour invalide.');}
        $this->db->beginTransaction();
        try{
            $w=$this->query('SELECT * FROM weighings WHERE id=:id AND deleted_at IS NULL FOR UPDATE',['id'=>$weighingId])->fetch();
            if(!$w){throw new RuntimeException('Pesée introuvable.');} Auth::requireSiteAccess($w['site_id']);
            $r=$this->query('SELECT * FROM weighbridge_returns WHERE weighing_id=:id AND deleted_at IS NULL FOR UPDATE',['id'=>$weighingId])->fetch();
            if(!$r||$r['status']!==$map[$action][0]){throw new RuntimeException('Transition de retour interdite ou déjà exécutée.');}
            $dateSql=$map[$action][2]?', '.$map[$action][2].'=NOW()':'';
            $this->query('UPDATE weighbridge_returns SET status=:status'.$dateSql.' WHERE id=:id',['status'=>$map[$action][1],'id'=>$r['id']]);
            if($action==='close'){$this->query("UPDATE weighings SET status='returned' WHERE id=:id",['id'=>$weighingId]);$this->query("UPDATE weighbridge_transports SET status='returned',completed_at=NOW() WHERE id=:id",['id'=>$w['transport_id']]);}
            $this->logActivity('return_'.$action,'pont-bascule','weighbridge_returns',$r['id'],'Progression du retour de livraison interne.',['status'=>$r['status']],['status'=>$map[$action][1]],$user);
            $this->db->commit();
        }catch(Exception$e){$this->db->rollBack();throw$e;}
    }

    private function reference()
    {
        return 'PB-' . date('Ymd-His') . '-' . random_int(100, 999);
    }

    private function findOrCreateTruck(array $data)
    {
        $plateNumber = strtoupper(trim($data['truck_plate_number'] ?? ''));
        $driverName = trim($data['driver_name'] ?? '');
        $driverPhone = trim($data['driver_phone'] ?? '');

        $truck = $this->query(
            "SELECT *
             FROM trucks
             WHERE plate_number = :plate_number
               AND deleted_at IS NULL
             LIMIT 1",
            ['plate_number' => $plateNumber]
        )->fetch();

        if ($truck) {
            $this->query(
                "UPDATE trucks
                 SET supplier_id = :supplier_id,
                     driver_name = :driver_name,
                     driver_phone = :driver_phone,
                     status = 'active'
                 WHERE id = :id",
                [
                    'supplier_id' => $data['supplier_id'],
                    'driver_name' => $driverName !== '' ? $driverName : $truck['driver_name'],
                    'driver_phone' => $driverPhone !== '' ? $driverPhone : $truck['driver_phone'],
                    'id' => $truck['id'],
                ]
            );

            return (int) $truck['id'];
        }

        $this->query(
            "INSERT INTO trucks (supplier_id, plate_number, driver_name, driver_phone, status)
             VALUES (:supplier_id, :plate_number, :driver_name, :driver_phone, 'active')",
            [
                'supplier_id' => $data['supplier_id'],
                'plate_number' => $plateNumber,
                'driver_name' => $driverName,
                'driver_phone' => $driverPhone,
            ]
        );

        return (int) $this->db->lastInsertId();
    }
}
