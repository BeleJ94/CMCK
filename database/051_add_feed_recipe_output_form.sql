-- Aliment bétail : forme du produit obtenu par version de recette (granulé ou farine non granulée).
-- Les versions existantes sont des pellets : valeur par défaut « granule ».
ALTER TABLE pellet_recipe_versions
  ADD COLUMN output_form ENUM('granule','farine') NOT NULL DEFAULT 'granule' AFTER status;
