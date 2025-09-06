-- Tabella per utenti
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin') NOT NULL DEFAULT 'admin',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella per porti
CREATE TABLE IF NOT EXISTS marinas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(10) NOT NULL UNIQUE, -- BOLA, RITTER, RAST
  name VARCHAR(120) NOT NULL,
  kind ENUM('porto','rastrelliera') NOT NULL,
  total_slots INT UNSIGNED NOT NULL,
  numbering_direction ENUM('L2R','R2L') NOT NULL DEFAULT 'L2R',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella per posti barca
CREATE TABLE IF NOT EXISTS slots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  marina_id INT UNSIGNED NOT NULL,
  numero_esterno INT UNSIGNED NOT NULL,
  numero_interno VARCHAR(50) NULL,
  tipo ENUM('carrello','fune') NULL,
  stato ENUM('Libero','Occupato','Riservato','Manutenzione') NOT NULL DEFAULT 'Libero',
  note TEXT NULL,
  position_x DECIMAL(6,3) NULL,
  position_y DECIMAL(6,3) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_marina_num (marina_id, numero_esterno),
  CONSTRAINT fk_slots_marina FOREIGN KEY (marina_id) REFERENCES marinas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella per utenti porto
CREATE TABLE IF NOT EXISTS assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slot_id INT UNSIGNED NOT NULL,
  stato ENUM('Libero','Occupato','Riservato','Manutenzione') NOT NULL,
  proprietario VARCHAR(160) NULL,
  targa VARCHAR(80) NULL,
  email VARCHAR(190) NULL,
  telefono VARCHAR(60) NULL,
  data_inizio DATE NOT NULL,
  data_fine DATE NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_as_slot FOREIGN KEY (slot_id) REFERENCES slots(id) ON DELETE CASCADE,
  CONSTRAINT fk_as_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella per log accessi
CREATE TABLE IF NOT EXISTS event_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  entity VARCHAR(40) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(40) NOT NULL,
  details JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (entity, entity_id),
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella per waiting list
CREATE TABLE IF NOT EXISTS `waiting_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipologia` enum('Barca','Canoa') NOT NULL,
  `cognome` varchar(100) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `luogo` varchar(100) NOT NULL,
  `via` varchar(200) NOT NULL,
  `telefono` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `motore_kw` varchar(50) DEFAULT NULL,
  `dimensioni` varchar(100) DEFAULT NULL,
  `targa` varchar(50) DEFAULT NULL,
  `osservazioni` text DEFAULT NULL,
  `data_iscrizione` date NOT NULL,
  `ultima_verifica` date DEFAULT NULL,
  `attivo` tinyint(1) DEFAULT 1,
  `posizione` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tipologia` (`tipologia`),
  KEY `idx_attivo` (`attivo`),
  KEY `idx_luogo` (`luogo`),
  KEY `idx_data_iscrizione` (`data_iscrizione`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella per allegati generici
CREATE TABLE IF NOT EXISTS `attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` enum('slot','waiting_list','assignment') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity_type`, `entity_id`),
  KEY `idx_uploaded_at` (`uploaded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserimento porti
INSERT INTO marinas (code,name,kind,total_slots,numbering_direction,created_at) VALUES
('BOLA','Porto alla Bola','porto',47,'L2R',NOW()),
('RITTER','Porto W. Ritter','porto',18,'L2R',NOW()),
('RAST','Rastrelliera','rastrelliera',18,'L2R',NOW());

-- Crea slots per BOLA (1..47), tipo default carrello
INSERT INTO slots (marina_id, numero_esterno, tipo, stato, created_at)
SELECT m.id, n.num, 'carrello', 'Libero', NOW()
FROM marinas m
JOIN (
  SELECT 1 num UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL
  SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL
  SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL
  SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL
  SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL SELECT 25 UNION ALL
  SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30 UNION ALL
  SELECT 31 UNION ALL SELECT 32 UNION ALL SELECT 33 UNION ALL SELECT 34 UNION ALL SELECT 35 UNION ALL
  SELECT 36 UNION ALL SELECT 37 UNION ALL SELECT 38 UNION ALL SELECT 39 UNION ALL SELECT 40 UNION ALL
  SELECT 41 UNION ALL SELECT 42 UNION ALL SELECT 43 UNION ALL SELECT 44 UNION ALL SELECT 45 UNION ALL
  SELECT 46 UNION ALL SELECT 47
) n ON m.code='BOLA';

-- Crea slots per RITTER (48..65), tipo default carrello
INSERT INTO slots (marina_id, numero_esterno, tipo, stato, created_at)
SELECT m.id, n.num, 'carrello', 'Libero', NOW()
FROM marinas m
JOIN (
  SELECT 48 num UNION ALL SELECT 49 UNION ALL SELECT 50 UNION ALL SELECT 51 UNION ALL SELECT 52 UNION ALL
  SELECT 53 UNION ALL SELECT 54 UNION ALL SELECT 55 UNION ALL SELECT 56 UNION ALL SELECT 57 UNION ALL
  SELECT 58 UNION ALL SELECT 59 UNION ALL SELECT 60 UNION ALL SELECT 61 UNION ALL SELECT 62 UNION ALL
  SELECT 63 UNION ALL SELECT 64 UNION ALL SELECT 65
) n ON m.code='RITTER';

-- Crea slots per RASTRELLIERA (1..18)
INSERT INTO slots (marina_id, numero_esterno, tipo, stato, created_at)
SELECT m.id, n.num, NULL, 'Libero', NOW()
FROM marinas m
JOIN (
  SELECT 1 num UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL
  SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL
  SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL
  SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18
) n ON m.code='RAST';

-- Crea tabella per salvataggio coordinate posti barca
CREATE TABLE slot_coordinates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slot_id INT UNSIGNED NOT NULL,
  north DECIMAL(10,8) NOT NULL,   -- lat max ±90
  south DECIMAL(10,8) NOT NULL,   -- lat max ±90
  east  DECIMAL(11,8) NOT NULL,   -- lon max ±180
  west  DECIMAL(11,8) NOT NULL,   -- lon max ±180
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_slot_coordinates_slot
    FOREIGN KEY (slot_id) REFERENCES slots(id) ON DELETE CASCADE,
  UNIQUE KEY uq_slot (slot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Aggiunge rotazione alle coordinate
ALTER TABLE slot_coordinates 
ADD COLUMN rotation DECIMAL(5,2) DEFAULT 0 AFTER west,
ADD COLUMN center_lat DECIMAL(10,8) AFTER rotation,
ADD COLUMN center_lng DECIMAL(11,8) AFTER center_lat,
ADD COLUMN width DECIMAL(10,8) AFTER center_lng,
ADD COLUMN height DECIMAL(10,8) AFTER width;