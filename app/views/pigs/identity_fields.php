<label><span>Sexe *</span><select name="sex" required><option value="">Choisir le sexe</option><option value="male">Mâles</option><option value="female">Femelles</option></select></label>
<label><span>Date de naissance *</span><input type="date" name="born_on" max="<?=e(date('Y-m-d'))?>" required></label>
<label><span>Nombre de porcs *</span><input type="number" name="heads" min="1" max="99999999" <?=isset($row)&&!$row['sex']?'value="'.(int)$row['current_heads'].'" readonly':''?> required></label>
