USE cmck_milltrack;

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM activity_logs;
DELETE FROM alerts;
DELETE FROM stock_movements;
DELETE FROM distributions;
DELETE FROM finished_stocks;
DELETE FROM packaging;
DELETE FROM waste_processings;
DELETE FROM waste_stocks;
DELETE FROM production_batches;
DELETE FROM machine_feeds;
DELETE FROM silo_movements;
DELETE FROM weighings;
DELETE FROM bag_formats;
DELETE FROM machines;
DELETE FROM silos;
DELETE FROM trucks;
DELETE FROM suppliers;
DELETE FROM products;
DELETE FROM users;
DELETE FROM roles;

ALTER TABLE roles AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE suppliers AUTO_INCREMENT = 1;
ALTER TABLE trucks AUTO_INCREMENT = 1;
ALTER TABLE products AUTO_INCREMENT = 1;
ALTER TABLE silos AUTO_INCREMENT = 1;
ALTER TABLE machines AUTO_INCREMENT = 1;
ALTER TABLE bag_formats AUTO_INCREMENT = 1;
ALTER TABLE weighings AUTO_INCREMENT = 1;
ALTER TABLE silo_movements AUTO_INCREMENT = 1;
ALTER TABLE machine_feeds AUTO_INCREMENT = 1;
ALTER TABLE production_batches AUTO_INCREMENT = 1;
ALTER TABLE waste_stocks AUTO_INCREMENT = 1;
ALTER TABLE waste_processings AUTO_INCREMENT = 1;
ALTER TABLE packaging AUTO_INCREMENT = 1;
ALTER TABLE finished_stocks AUTO_INCREMENT = 1;
ALTER TABLE distributions AUTO_INCREMENT = 1;
ALTER TABLE stock_movements AUTO_INCREMENT = 1;
ALTER TABLE alerts AUTO_INCREMENT = 1;
ALTER TABLE activity_logs AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO roles (id, name, slug, status) VALUES
(1, 'Administrateur', 'administrateur', 'active'),
(2, 'Direction', 'direction', 'active'),
(3, 'Agent pont-bascule', 'agent-pont-bascule', 'active'),
(4, 'Agent silo', 'agent-silo', 'active'),
(5, 'Agent production', 'agent-production', 'active'),
(6, 'Agent emballage', 'agent-emballage', 'active'),
(7, 'Agent distribution', 'agent-distribution', 'active');

INSERT INTO users (id, role_id, name, email, password, phone, status) VALUES
(1, 1, 'Admin CMCK', 'admin@cmck-milltrack.test', '$2y$10$0lMoZoXifCnicmd8f0cE7ORUFQBq.rPwdVYZp1CpOecCsnGEXz75q', '+243 990 000 001', 'active'),
(2, 2, 'Grace Mbuyi', 'direction@cmck-milltrack.test', '$2y$10$lwfTRKddRR1fUHyCXoaVX.iAsoI0ovLJsu6pKUHiDEkV3dHYggq.q', '+243 990 000 002', 'active'),
(3, 3, 'Joel Kabeya', 'pont@cmck-milltrack.test', '$2y$10$lwfTRKddRR1fUHyCXoaVX.iAsoI0ovLJsu6pKUHiDEkV3dHYggq.q', '+243 990 000 003', 'active'),
(4, 4, 'Sarah Ilunga', 'silo@cmck-milltrack.test', '$2y$10$lwfTRKddRR1fUHyCXoaVX.iAsoI0ovLJsu6pKUHiDEkV3dHYggq.q', '+243 990 000 004', 'active'),
(5, 5, 'Patrick Tshimanga', 'production@cmck-milltrack.test', '$2y$10$lwfTRKddRR1fUHyCXoaVX.iAsoI0ovLJsu6pKUHiDEkV3dHYggq.q', '+243 990 000 005', 'active'),
(6, 6, 'Mireille Kalala', 'emballage@cmck-milltrack.test', '$2y$10$lwfTRKddRR1fUHyCXoaVX.iAsoI0ovLJsu6pKUHiDEkV3dHYggq.q', '+243 990 000 006', 'active'),
(7, 7, 'David Kanku', 'distribution@cmck-milltrack.test', '$2y$10$lwfTRKddRR1fUHyCXoaVX.iAsoI0ovLJsu6pKUHiDEkV3dHYggq.q', '+243 990 000 007', 'active'),
(8, 1, 'Jeremy BELE BELE', 'jeremy@belej-consulting.com', '$2y$10$xSjHxYSXvLcaD4akvRfcX.1HlOuD.ZydCURMtl/yTnEcOoSDM1GCW', NULL, 'active');

INSERT INTO suppliers (id, name, contact_name, phone, email, address, status) VALUES
(1, 'Cooperative Agricole Katanga Grains', 'Jean Mutombo', '+243 991 120 101', 'contact@katangagrains.test', 'Route Kasumbalesa, Lubumbashi', 'active'),
(2, 'Ferme Lukuni', 'Aline Tshibuabua', '+243 991 120 102', 'vente@lukuni.test', 'Plaine de Lukuni, Haut-Katanga', 'active'),
(3, 'Agri Sud Congo', 'Eric Mwamba', '+243 991 120 103', 'operations@agrisud.test', 'Avenue Industrielle, Lubumbashi', 'active'),
(4, 'Depot Cerealier Kafubu', 'Nadine Banza', '+243 991 120 104', 'kafubu@cereales.test', 'Kafubu, Lubumbashi', 'active'),
(5, 'ETS Maize Logistics', 'Samuel Lwamba', '+243 991 120 105', 'logistics@maize.test', 'Quartier Industriel, Lubumbashi', 'active');

INSERT INTO trucks (id, supplier_id, plate_number, driver_name, driver_phone, status) VALUES
(1, 1, 'CGO-4217-HA', 'Emmanuel Kitenge', '+243 992 300 101', 'active'),
(2, 2, 'CGO-8842-HK', 'Blaise Kapenda', '+243 992 300 102', 'active'),
(3, 3, 'CGO-7320-LU', 'Cedrick Monga', '+243 992 300 103', 'active'),
(4, 4, 'CGO-1059-KA', 'Moses Kazadi', '+243 992 300 104', 'active'),
(5, 5, 'CGO-6631-MI', 'Junior Beya', '+243 992 300 105', 'active');

INSERT INTO products (id, name, code, category, unit, status) VALUES
(1, 'Mais brut', 'MAIS-BRUT', 'raw_material', 'kg', 'active'),
(2, 'Farine de mais', 'FARINE-MAIS', 'finished_product', 'kg', 'active'),
(3, 'Dechets de mais', 'DECHETS-MAIS', 'waste', 'kg', 'active'),
(4, 'Aliment pour betail', 'ALIMENT-BETAIL', 'finished_product', 'kg', 'active');

INSERT INTO silos (id, name, code, product_id, capacity_kg, current_stock_kg, status) VALUES
(1, 'Silo reception A', 'SILO-A', 1, 120000.000, 58250.000, 'active'),
(2, 'Silo reception B', 'SILO-B', 1, 120000.000, 40750.000, 'active'),
(3, 'Silo tampon production', 'SILO-TAMPON', 1, 60000.000, 18300.000, 'active');

INSERT INTO machines (id, name, code, machine_type, capacity_kg_hour, status) VALUES
(1, 'Machine principale 1', 'MILL-L1', 'main', 3500.000, 'active'),
(2, 'Machine principale 2', 'MILL-L2', 'main', 3200.000, 'active'),
(3, 'Machine dechets', 'WASTE-FEED-01', 'waste', 1400.000, 'active');

INSERT INTO bag_formats (id, name, weight_kg, status) VALUES
(1, 'Sac 5kg', 5.000, 'active'),
(2, 'Sac 10kg', 10.000, 'active'),
(3, 'Sac 25kg', 25.000, 'active'),
(4, 'Sac 50kg', 50.000, 'active');

INSERT INTO weighings (id, supplier_id, truck_id, product_id, reference, poids_brut, poids_tare, poids_net, weighed_at, status, created_by, validated_by) VALUES
(1, 1, 1, 1, 'PB-2026-0001', 42000.000, 12500.000, 29500.000, '2026-06-03 08:12:00', 'validated', 3, 2),
(2, 2, 2, 1, 'PB-2026-0002', 38500.000, 11800.000, 26700.000, '2026-06-03 10:35:00', 'validated', 3, 2),
(3, 3, 3, 1, 'PB-2026-0003', 44800.000, 13100.000, 31700.000, '2026-06-04 09:05:00', 'validated', 3, 2),
(4, 4, 4, 1, 'PB-2026-0004', 36200.000, 11650.000, 24550.000, '2026-06-05 14:20:00', 'validated', 3, 2),
(5, 5, 5, 1, 'PB-2026-0005', 41100.000, 12300.000, 28800.000, '2026-06-06 07:45:00', 'pending', 3, NULL);

INSERT INTO silo_movements (id, silo_id, product_id, weighing_id, movement_type, quantity_kg, stock_before_kg, stock_after_kg, movement_at, status, created_by) VALUES
(1, 1, 1, 1, 'in', 29500.000, 28750.000, 58250.000, '2026-06-03 08:45:00', 'validated', 4),
(2, 2, 1, 2, 'in', 26700.000, 14050.000, 40750.000, '2026-06-03 11:05:00', 'validated', 4),
(3, 3, 1, NULL, 'in', 18000.000, 300.000, 18300.000, '2026-06-04 12:10:00', 'validated', 4),
(4, 1, 1, NULL, 'out', 15000.000, 58250.000, 43250.000, '2026-06-07 06:15:00', 'validated', 4),
(5, 2, 1, NULL, 'out', 14000.000, 40750.000, 26750.000, '2026-06-07 07:00:00', 'validated', 4);

INSERT INTO machine_feeds (id, machine_id, silo_id, product_id, silo_movement_id, quantity_kg, fed_at, status, created_by) VALUES
(1, 1, 1, 1, 4, 15000.000, '2026-06-07 06:30:00', 'validated', 5),
(2, 2, 2, 1, 5, 14000.000, '2026-06-07 07:15:00', 'validated', 5),
(3, 3, 3, 3, NULL, 1800.000, '2026-06-08 13:00:00', 'validated', 5);

INSERT INTO production_batches (id, machine_feed_id, product_id, batch_number, input_quantity_kg, output_quantity_kg, waste_quantity_kg, started_at, ended_at, status, created_by, validated_by) VALUES
(1, 1, 2, 'PROD-2026-0001', 15000.000, 11250.000, 1450.000, '2026-06-07 06:45:00', '2026-06-07 12:20:00', 'validated', 5, 2),
(2, 2, 2, 'PROD-2026-0002', 14000.000, 10400.000, 1320.000, '2026-06-07 07:30:00', '2026-06-07 13:10:00', 'validated', 5, 2),
(3, 3, 4, 'PROD-2026-0003', 1800.000, 1600.000, 120.000, '2026-06-08 13:10:00', '2026-06-08 15:00:00', 'validated', 5, 2);

INSERT INTO waste_stocks (id, product_id, production_batch_id, quantity_kg, status) VALUES
(1, 3, 1, 1450.000, 'active'),
(2, 3, 2, 1320.000, 'active'),
(3, 3, 3, 120.000, 'active');

INSERT INTO waste_processings (id, waste_stock_id, machine_id, input_quantity_kg, output_quantity_kg, processed_at, status, created_by) VALUES
(1, 1, 3, 900.000, 760.000, '2026-06-08 08:30:00', 'validated', 5),
(2, 2, 3, 900.000, 760.000, '2026-06-08 10:20:00', 'validated', 5);

INSERT INTO packaging (id, production_batch_id, bag_format_id, product_id, bags_count, total_weight_kg, packaged_at, status, created_by) VALUES
(1, 1, 4, 2, 160, 8000.000, '2026-06-07 14:00:00', 'validated', 6),
(2, 1, 3, 2, 100, 2500.000, '2026-06-07 15:10:00', 'validated', 6),
(3, 2, 4, 2, 150, 7500.000, '2026-06-07 16:30:00', 'validated', 6),
(4, 2, 2, 2, 200, 2000.000, '2026-06-08 09:00:00', 'validated', 6),
(5, 3, 3, 4, 40, 1000.000, '2026-06-08 16:00:00', 'validated', 6);

INSERT INTO finished_stocks (id, product_id, bag_format_id, packaging_id, quantity_bags, total_weight_kg, status) VALUES
(1, 2, 4, 1, 120, 6000.000, 'active'),
(2, 2, 3, 2, 82, 2050.000, 'active'),
(3, 2, 4, 3, 150, 7500.000, 'active'),
(4, 2, 2, 4, 200, 2000.000, 'active'),
(5, 4, 3, 5, 32, 800.000, 'active');

INSERT INTO distributions (id, finished_stock_id, product_id, bag_format_id, recipient_name, transporter, exit_voucher, quantity_bags, total_weight_kg, distributed_at, status, created_by, validated_by) VALUES
(1, 1, 2, 4, 'Depot CMCK Lubumbashi Centre', 'Camion CMCK 01', 'BS-2026-0001', 40, 2000.000, '2026-06-08 11:30:00', 'validated', 7, 2),
(2, 2, 2, 3, 'Client Grossiste Kafubu', 'Transport Kafubu', 'BS-2026-0002', 18, 450.000, '2026-06-08 14:15:00', 'validated', 7, 2),
(3, 5, 4, 3, 'Ferme Partenaire Kipushi', 'Pickup ferme Kipushi', 'BS-2026-0003', 8, 200.000, '2026-06-09 09:40:00', 'validated', 7, 2);

INSERT INTO stock_movements (id, product_id, finished_stock_id, distribution_id, movement_type, quantity_bags, quantity_kg, stock_before_kg, stock_after_kg, movement_at, status, created_by) VALUES
(1, 2, 1, NULL, 'in', 160, 8000.000, 0.000, 8000.000, '2026-06-07 14:05:00', 'validated', 6),
(2, 2, 2, NULL, 'in', 100, 2500.000, 0.000, 2500.000, '2026-06-07 15:15:00', 'validated', 6),
(3, 2, 3, NULL, 'in', 150, 7500.000, 0.000, 7500.000, '2026-06-07 16:35:00', 'validated', 6),
(4, 2, 4, NULL, 'in', 200, 2000.000, 0.000, 2000.000, '2026-06-08 09:05:00', 'validated', 6),
(5, 4, 5, NULL, 'in', 40, 1000.000, 0.000, 1000.000, '2026-06-08 16:05:00', 'validated', 6),
(6, 2, 1, 1, 'out', 40, 2000.000, 8000.000, 6000.000, '2026-06-08 11:30:00', 'validated', 7),
(7, 2, 2, 2, 'out', 18, 450.000, 2500.000, 2050.000, '2026-06-08 14:15:00', 'validated', 7),
(8, 4, 5, 3, 'out', 8, 200.000, 1000.000, 800.000, '2026-06-09 09:40:00', 'validated', 7);

INSERT INTO alerts (id, user_id, title, message, severity, status) VALUES
(1, 2, 'Validation en attente', 'La pesee PB-2026-0005 attend une validation direction.', 'warning', 'active'),
(2, 4, 'Stock silo tampon', 'Le silo tampon production approche du seuil de reassort.', 'info', 'active'),
(3, 7, 'Distribution planifiee', 'Preparation d une sortie farine 50kg pour le depot centre.', 'info', 'active');

INSERT INTO activity_logs (id, user_id, action, entity_type, entity_id, description, ip_address, user_agent) VALUES
(1, 1, 'seed.imported', 'database', 1, 'Donnees initiales CMCK MillTrack importees.', '127.0.0.1', 'CMCK MillTrack Seeder'),
(2, 3, 'weighing.created', 'weighings', 1, 'Creation de la pesee PB-2026-0001.', '127.0.0.1', 'CMCK MillTrack Seeder'),
(3, 5, 'production.validated', 'production_batches', 1, 'Validation du lot PROD-2026-0001.', '127.0.0.1', 'CMCK MillTrack Seeder'),
(4, 7, 'distribution.validated', 'distributions', 1, 'Validation distribution Depot CMCK Lubumbashi Centre.', '127.0.0.1', 'CMCK MillTrack Seeder');
