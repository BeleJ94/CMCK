ALTER TABLE fish_catches ADD COLUMN conversion_id BIGINT UNSIGNED NULL AFTER batch_id,ADD UNIQUE KEY uq_fc_conversion(conversion_id),ADD CONSTRAINT fk_fc_conversion FOREIGN KEY(conversion_id) REFERENCES livestock_conversions(id);
DELIMITER $$
CREATE TRIGGER trg_livestock_conversion_validates_catch AFTER UPDATE ON livestock_conversions FOR EACH ROW BEGIN IF NEW.status='validated' AND OLD.status<>'validated' THEN UPDATE fish_catches SET status='validated',validated_by=NEW.validated_by,validated_at=NEW.validated_at WHERE conversion_id=NEW.id; END IF; END$$
DELIMITER ;
