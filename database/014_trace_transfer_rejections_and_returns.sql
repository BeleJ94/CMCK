USE cmck_milltrack;
DELIMITER $$
CREATE TRIGGER trg_transfer_return_item_reject_movement
AFTER INSERT ON transfer_return_items
FOR EACH ROW
BEGIN
  INSERT INTO stock_transfer_movements(
    transfer_id,transfer_item_id,site_id,movement_type,quantity_bags,quantity_kg,
    physical_before_kg,physical_after_kg,reserved_before_kg,reserved_after_kg,
    transit_before_kg,transit_after_kg,source_type,source_id,created_by
  )
  SELECT tr.transfer_id,NEW.transfer_item_id,t.destination_site_id,'reject',NEW.quantity_bags,NEW.quantity_kg,
         0,0,0,0,(ti.shipped_kg-ti.received_kg)+NEW.quantity_kg,ti.shipped_kg-ti.received_kg,
         'return',NEW.return_id,tr.created_by
  FROM transfer_returns tr
  INNER JOIN stock_transfers t ON t.id=tr.transfer_id
  INNER JOIN stock_transfer_items ti ON ti.id=NEW.transfer_item_id
  WHERE tr.id=NEW.return_id;
END$$

CREATE TRIGGER trg_transfer_return_dispatch_movement
AFTER UPDATE ON transfer_returns
FOR EACH ROW
BEGIN
  IF OLD.status='planned' AND NEW.status='dispatched' THEN
    INSERT INTO stock_transfer_movements(
      transfer_id,transfer_item_id,site_id,movement_type,quantity_bags,quantity_kg,
      physical_before_kg,physical_after_kg,reserved_before_kg,reserved_after_kg,
      transit_before_kg,transit_after_kg,source_type,source_id,created_by
    )
    SELECT NEW.transfer_id,ri.transfer_item_id,t.destination_site_id,'return_dispatch',ri.quantity_bags,ri.quantity_kg,
           0,0,0,0,0,ri.quantity_kg,'return',NEW.id,NEW.created_by
    FROM transfer_return_items ri INNER JOIN stock_transfers t ON t.id=NEW.transfer_id
    WHERE ri.return_id=NEW.id;
  END IF;
END$$
DELIMITER ;
