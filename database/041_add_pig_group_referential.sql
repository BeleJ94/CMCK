-- Référentiel des groupes d’âge porcins : tranches d’âge et capacités métier paramétrables.
-- A, B et C reprennent exactement les règles précédentes ; les profils et l’historique sont conservés.
CREATE TABLE livestock_pig_groups (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(10) NOT NULL,
 name VARCHAR(80) NOT NULL,
 description VARCHAR(255) NULL,
 min_age_months SMALLINT UNSIGNED NOT NULL,
 accepts_births TINYINT(1) NOT NULL DEFAULT 0,
 allows_breeding TINYINT(1) NOT NULL DEFAULT 0,
 allows_slaughter TINYINT(1) NOT NULL DEFAULT 0,
 illustration VARCHAR(30) NOT NULL DEFAULT 'young-pig',
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_by INT UNSIGNED NULL,
 updated_by INT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_pig_group_code(code),
 KEY idx_pig_group_age(status,min_age_months),
 CONSTRAINT fk_pig_group_creator FOREIGN KEY(created_by) REFERENCES users(id),
 CONSTRAINT fk_pig_group_editor FOREIGN KEY(updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO livestock_pig_groups(code,name,description,min_age_months,accepts_births,allows_breeding,allows_slaughter,illustration) VALUES
('A','Porcelets','Naissances et premiers mois à la ferme.',0,1,0,0,'piglet'),
('B','Porcs en croissance','Croissance avant la maturité.',2,0,0,0,'young-pig'),
('C','Porcs adultes','Destinés à l’abattage ou à la reproduction.',3,0,1,1,'adult-pig');

-- Les codes deviennent des références : un groupe utilisé ne peut plus être supprimé, seulement désactivé.
ALTER TABLE livestock_pig_profiles
 MODIFY confirmed_group VARCHAR(10) NOT NULL,
 ADD CONSTRAINT fk_pig_profile_group FOREIGN KEY(confirmed_group) REFERENCES livestock_pig_groups(code);

ALTER TABLE livestock_pig_events
 MODIFY old_group VARCHAR(10) NULL,
 MODIFY new_group VARCHAR(10) NOT NULL,
 ADD CONSTRAINT fk_pige_old_group FOREIGN KEY(old_group) REFERENCES livestock_pig_groups(code),
 ADD CONSTRAINT fk_pige_new_group FOREIGN KEY(new_group) REFERENCES livestock_pig_groups(code);
