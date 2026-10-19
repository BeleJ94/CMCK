-- Étape 1 : lots homogènes de porcs, groupes validés et reproduction.
-- Les anciens lots restent sans profil jusqu’à renseignement sur le terrain.
CREATE TABLE livestock_pig_profiles (
 batch_id BIGINT UNSIGNED PRIMARY KEY,
 sex ENUM('male','female') NOT NULL,
 born_on DATE NOT NULL,
 confirmed_group ENUM('A','B','C') NOT NULL,
 mother_batch_id BIGINT UNSIGNED NULL,
 source_batch_id BIGINT UNSIGNED NULL,
 submission_key CHAR(32) NULL UNIQUE,
 confirmed_by INT UNSIGNED NOT NULL,
 confirmed_at DATETIME NOT NULL,
 CONSTRAINT fk_pig_batch FOREIGN KEY(batch_id) REFERENCES livestock_batches(id),
 CONSTRAINT fk_pig_mother FOREIGN KEY(mother_batch_id) REFERENCES livestock_batches(id),
 CONSTRAINT fk_pig_source FOREIGN KEY(source_batch_id) REFERENCES livestock_batches(id),
 CONSTRAINT fk_pig_confirmer FOREIGN KEY(confirmed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE livestock_pig_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 batch_id BIGINT UNSIGNED NOT NULL,
 event_type ENUM('registration','group_confirmation','breeding','fattening','split') NOT NULL,
 old_group ENUM('A','B','C') NULL,
 new_group ENUM('A','B','C') NOT NULL,
 quantity_heads INT UNSIGNED NOT NULL,
 reason VARCHAR(500) NOT NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_pige_batch FOREIGN KEY(batch_id) REFERENCES livestock_batches(id),
 CONSTRAINT fk_pige_user FOREIGN KEY(created_by) REFERENCES users(id),
 KEY idx_pige_batch(batch_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
