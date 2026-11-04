-- Les « déchets » de meunerie sont des issues entièrement récupérées, matière première de l'aliment bétail.
-- Renommage des libellés uniquement : les codes produits et types restent inchangés (stocks, recettes et historiques en dépendent).
UPDATE products SET name = 'Issues de maïs' WHERE code = 'DECHETS-MAIS' AND name = 'Dechets de mais';
UPDATE products SET name = 'Aliment pour bétail' WHERE code = 'ALIMENT-BETAIL' AND name = 'Aliment pour betail';
