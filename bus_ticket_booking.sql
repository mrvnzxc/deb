-- =====================================================
-- BUS TICKET BOOKING SYSTEM - Database
-- Paste this in phpMyAdmin > SQL tab, then click "Go"
-- =====================================================

CREATE DATABASE IF NOT EXISTS bus_ticket_booking
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE bus_ticket_booking;

-- Start fresh: remove old tables so this file can be pasted again.
-- WARNING: this deletes all existing data in bus_ticket_booking.
SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW  IF EXISTS vw_booking_summary;
DROP TABLE IF EXISTS payments, bookings, schedules, routes, buses, locations, password_resets, users;
SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------
-- USERS (admin and customer)
-- email is UNIQUE so an admin and a customer can't share one
-- password = password_hash() in PHP (bcrypt)
-- -----------------------------------------------------
CREATE TABLE users (
  user_id      INT AUTO_INCREMENT PRIMARY KEY,
  full_name    VARCHAR(100) NOT NULL,
  email        VARCHAR(100) NOT NULL UNIQUE,
  contact_no   VARCHAR(20)  NULL,
  password     VARCHAR(255) NOT NULL,
  role         ENUM('admin','customer') NOT NULL DEFAULT 'customer',
  status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- PASSWORD RESETS (forgot password)
-- -----------------------------------------------------
CREATE TABLE password_resets (
  reset_id     INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  token        VARCHAR(100) NOT NULL UNIQUE,
  expires_at   DATETIME NOT NULL,
  is_used      TINYINT(1) NOT NULL DEFAULT 0,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- LOCATIONS (terminals: current location / destination)
-- -----------------------------------------------------
CREATE TABLE locations (
  location_id    INT AUTO_INCREMENT PRIMARY KEY,
  location_name  VARCHAR(100) NOT NULL UNIQUE,
  province       VARCHAR(100) NULL
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- BUSES
-- -----------------------------------------------------
CREATE TABLE buses (
  bus_id        INT AUTO_INCREMENT PRIMARY KEY,
  bus_number    VARCHAR(20) NOT NULL UNIQUE,
  plate_number  VARCHAR(20) NOT NULL UNIQUE,
  bus_type      ENUM('Ordinary','Aircon','Deluxe') NOT NULL DEFAULT 'Aircon',
  total_seats   INT NOT NULL DEFAULT 45,
  status        ENUM('available','unavailable','maintenance') NOT NULL DEFAULT 'available',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- ROUTES (admin changes the rate/fare per destination here)
-- -----------------------------------------------------
CREATE TABLE routes (
  route_id         INT AUTO_INCREMENT PRIMARY KEY,
  origin_id        INT NOT NULL,
  destination_id   INT NOT NULL,
  fare             DECIMAL(10,2) NOT NULL,
  travel_hours     DECIMAL(4,1) NULL,
  updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_route (origin_id, destination_id),
  FOREIGN KEY (origin_id)      REFERENCES locations(location_id),
  FOREIGN KEY (destination_id) REFERENCES locations(location_id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- SCHEDULES (which bus runs which route and when)
-- -----------------------------------------------------
CREATE TABLE schedules (
  schedule_id      INT AUTO_INCREMENT PRIMARY KEY,
  bus_id           INT NOT NULL,
  route_id         INT NOT NULL,
  departure_time   DATETIME NOT NULL,
  available_seats  INT NOT NULL,
  status           ENUM('open','full','departed','cancelled') NOT NULL DEFAULT 'open',
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bus_id)   REFERENCES buses(bus_id),
  FOREIGN KEY (route_id) REFERENCES routes(route_id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- BOOKINGS
-- -----------------------------------------------------
CREATE TABLE bookings (
  booking_id     INT AUTO_INCREMENT PRIMARY KEY,
  booking_code   VARCHAR(20) NOT NULL UNIQUE,
  user_id        INT NOT NULL,
  schedule_id    INT NOT NULL,
  num_seats      INT NOT NULL DEFAULT 1,
  total_amount   DECIMAL(10,2) NOT NULL,
  status         ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  booked_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)     REFERENCES users(user_id),
  FOREIGN KEY (schedule_id) REFERENCES schedules(schedule_id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- PAYMENTS (GCash or Card)
-- never store the full card number / CVV, last 4 digits only
-- -----------------------------------------------------
CREATE TABLE payments (
  payment_id      INT AUTO_INCREMENT PRIMARY KEY,
  booking_id      INT NOT NULL,
  payment_method  ENUM('gcash','card') NOT NULL,
  amount          DECIMAL(10,2) NOT NULL,
  reference_no    VARCHAR(50) NULL,
  gcash_number    VARCHAR(20) NULL,
  card_name       VARCHAR(100) NULL,
  card_last4      CHAR(4) NULL,
  status          ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  paid_at         DATETIME NULL,
  FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- VIEW: admin booking tracker (how many booked per trip)
-- =====================================================
CREATE VIEW vw_booking_summary AS
SELECT
  s.schedule_id,
  b.bus_number,
  o.location_name AS origin,
  d.location_name AS destination,
  r.fare,
  s.departure_time,
  b.total_seats,
  s.available_seats,
  COUNT(bk.booking_id)                AS total_bookings,
  COALESCE(SUM(bk.num_seats), 0)      AS seats_booked,
  COALESCE(SUM(bk.total_amount), 0)   AS total_sales
FROM schedules s
JOIN buses b      ON b.bus_id = s.bus_id
JOIN routes r     ON r.route_id = s.route_id
JOIN locations o  ON o.location_id = r.origin_id
JOIN locations d  ON d.location_id = r.destination_id
LEFT JOIN bookings bk ON bk.schedule_id = s.schedule_id AND bk.status <> 'cancelled'
GROUP BY s.schedule_id;

-- =====================================================
-- SAMPLE DATA
-- Both accounts below use the password:  password
-- =====================================================
INSERT INTO users (full_name, email, contact_no, password, role) VALUES
('System Admin', 'admin@busticket.com',    '09170000000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Juan Dela Cruz', 'juan@gmail.com',       '09171234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer');

-- -----------------------------------------------------
-- LOCATIONS: General Santos City hub + South Cotabato
-- and neighboring provinces (SOCCSKSARGEN / Davao)
-- -----------------------------------------------------
INSERT INTO locations (location_name, province) VALUES
('General Santos City', 'South Cotabato'),         -- 1  (main terminal)
('Koronadal City',      'South Cotabato'),         -- 2  (Marbel)
('Polomolok',           'South Cotabato'),         -- 3
('Tupi',                'South Cotabato'),         -- 4
('Banga',               'South Cotabato'),         -- 5
('Surallah',            'South Cotabato'),         -- 6
('Lake Sebu',           'South Cotabato'),         -- 7
('T''boli',             'South Cotabato'),         -- 8
('Tacurong City',       'Sultan Kudarat'),         -- 9
('Isulan',              'Sultan Kudarat'),         -- 10
('Alabel',              'Sarangani'),              -- 11
('Malungon',            'Sarangani'),              -- 12
('Glan',                'Sarangani'),              -- 13
('Kiamba',              'Sarangani'),              -- 14
('Digos City',          'Davao del Sur'),          -- 15
('Davao City',          'Davao del Sur'),          -- 16
('Cotabato City',       'Maguindanao del Norte');  -- 17

INSERT INTO buses (bus_number, plate_number, bus_type, total_seats, status) VALUES
('BUS-101', 'MAB 1012', 'Deluxe',   30, 'available'),
('BUS-102', 'MAC 2045', 'Aircon',   45, 'available'),
('BUS-103', 'MAD 3178', 'Aircon',   45, 'available'),
('BUS-104', 'MAE 4290', 'Ordinary', 50, 'available'),
('BUS-105', 'MAF 5316', 'Ordinary', 50, 'available'),
('BUS-106', 'MAG 6427', 'Aircon',   45, 'maintenance');

-- Sample fares (PHP) - admin can change these in Routes
INSERT INTO routes (origin_id, destination_id, fare, travel_hours) VALUES
( 1,  2, 110.00, 1.5),  -- 1  GenSan    -> Koronadal
( 2,  1, 110.00, 1.5),  -- 2  Koronadal -> GenSan
( 1,  3,  45.00, 0.5),  -- 3  GenSan    -> Polomolok
( 1,  4,  70.00, 1.0),  -- 4  GenSan    -> Tupi
( 1,  6, 160.00, 2.0),  -- 5  GenSan    -> Surallah
( 1,  7, 210.00, 3.0),  -- 6  GenSan    -> Lake Sebu
( 1,  8, 190.00, 2.5),  -- 7  GenSan    -> T'boli
( 1,  9, 190.00, 2.5),  -- 8  GenSan    -> Tacurong
( 1, 10, 220.00, 3.0),  -- 9  GenSan    -> Isulan
( 1, 11,  35.00, 0.5),  -- 10 GenSan    -> Alabel
( 1, 12,  90.00, 1.5),  -- 11 GenSan    -> Malungon
( 1, 13, 120.00, 2.0),  -- 12 GenSan    -> Glan
( 1, 14, 190.00, 3.0),  -- 13 GenSan    -> Kiamba
( 1, 15, 220.00, 2.5),  -- 14 GenSan    -> Digos
( 1, 16, 330.00, 4.0),  -- 15 GenSan    -> Davao City
(16,  1, 330.00, 4.0),  -- 16 Davao City -> GenSan
( 2,  6,  55.00, 0.8),  -- 17 Koronadal -> Surallah
( 2,  9,  85.00, 1.0),  -- 18 Koronadal -> Tacurong
( 2, 17, 280.00, 3.5),  -- 19 Koronadal -> Cotabato City
( 2,  5,  30.00, 0.5),  -- 20 Koronadal -> Banga
( 9,  1, 190.00, 2.5);  -- 21 Tacurong  -> GenSan

-- available_seats = bus total_seats minus seats already booked below
INSERT INTO schedules (bus_id, route_id, departure_time, available_seats, status) VALUES
(1, 15, '2026-11-11 06:00:00', 25, 'open'),  -- 1  GenSan -> Davao City
(2,  1, '2026-11-11 07:30:00', 40, 'open'),  -- 2  GenSan -> Koronadal
(4,  3, '2026-11-11 09:00:00', 50, 'open'),  -- 3  GenSan -> Polomolok
(3,  8, '2026-11-12 08:00:00', 43, 'open'),  -- 4  GenSan -> Tacurong
(5, 12, '2026-11-12 10:00:00', 50, 'open'),  -- 5  GenSan -> Glan
(2,  2, '2026-11-12 15:00:00', 45, 'open'),  -- 6  Koronadal -> GenSan
(1, 16, '2026-11-13 13:00:00', 29, 'open'),  -- 7  Davao City -> GenSan
(3,  6, '2026-11-14 06:30:00', 41, 'open'),  -- 8  GenSan -> Lake Sebu
(4, 14, '2026-11-14 11:00:00', 48, 'open'),  -- 9  GenSan -> Digos
(5, 19, '2026-11-15 07:00:00', 48, 'open'),  -- 10 Koronadal -> Cotabato City
(2,  5, '2026-11-15 14:00:00', 44, 'open'),  -- 11 GenSan -> Surallah
(1, 15, '2026-11-16 06:00:00', 28, 'open');  -- 12 GenSan -> Davao City

-- Sample bookings spread over the last two weeks so the dashboard charts have data
INSERT INTO bookings (booking_code, user_id, schedule_id, num_seats, total_amount, status, booked_at) VALUES
('BTB-261028-A1B2C3', 2,  1, 2, 660.00, 'confirmed', '2026-10-28 10:12:00'),
('BTB-261030-D4E5F6', 2,  2, 3, 330.00, 'confirmed', '2026-10-30 14:40:00'),
('BTB-261101-A7B8C9', 2,  4, 2, 380.00, 'confirmed', '2026-11-01 09:05:00'),
('BTB-261102-D1E2F3', 2,  7, 1, 330.00, 'confirmed', '2026-11-02 18:22:00'),
('BTB-261104-A4B5C6', 2,  8, 4, 840.00, 'confirmed', '2026-11-04 08:50:00'),
('BTB-261105-D7E8F9', 2,  1, 3, 990.00, 'confirmed', '2026-11-05 12:30:00'),
('BTB-261106-B1C2D3', 2,  6, 2, 220.00, 'cancelled', '2026-11-06 16:15:00'),
('BTB-261107-E4F5A6', 2,  9, 2, 440.00, 'confirmed', '2026-11-07 07:45:00'),
('BTB-261108-B7C8D9', 2, 11, 1, 160.00, 'confirmed', '2026-11-08 20:10:00'),
('BTB-261109-E1F2A3', 2, 12, 2, 660.00, 'confirmed', '2026-11-09 11:00:00'),
('BTB-261110-B4C5D6', 2,  2, 2, 220.00, 'confirmed', '2026-11-10 08:30:00'),
('BTB-261110-E7F8A9', 2, 10, 2, 560.00, 'confirmed', '2026-11-10 09:15:00');

INSERT INTO payments (booking_id, payment_method, amount, reference_no, gcash_number, card_name, card_last4, status, paid_at) VALUES
( 1, 'gcash', 660.00, 'GC261028482913', '09171234567', NULL, NULL, 'paid',     '2026-10-28 10:12:00'),
( 2, 'card',  330.00, 'CD261030739204', NULL, 'Juan Dela Cruz', '4242', 'paid', '2026-10-30 14:40:00'),
( 3, 'gcash', 380.00, 'GC261101215867', '09171234567', NULL, NULL, 'paid',     '2026-11-01 09:05:00'),
( 4, 'gcash', 330.00, 'GC261102908431', '09171234567', NULL, NULL, 'paid',     '2026-11-02 18:22:00'),
( 5, 'card',  840.00, 'CD261104563720', NULL, 'Juan Dela Cruz', '4242', 'paid', '2026-11-04 08:50:00'),
( 6, 'gcash', 990.00, 'GC261105374159', '09171234567', NULL, NULL, 'paid',     '2026-11-05 12:30:00'),
( 7, 'gcash', 220.00, 'GC261106641298', '09171234567', NULL, NULL, 'refunded', '2026-11-06 16:15:00'),
( 8, 'gcash', 440.00, 'GC261107125984', '09171234567', NULL, NULL, 'paid',     '2026-11-07 07:45:00'),
( 9, 'card',  160.00, 'CD261108847362', NULL, 'Juan Dela Cruz', '4242', 'paid', '2026-11-08 20:10:00'),
(10, 'gcash', 660.00, 'GC261109590317', '09171234567', NULL, NULL, 'paid',     '2026-11-09 11:00:00'),
(11, 'gcash', 220.00, 'GC261110273648', '09171234567', NULL, NULL, 'paid',     '2026-11-10 08:30:00'),
(12, 'card',  560.00, 'CD261110918526', NULL, 'Juan Dela Cruz', '4242', 'paid', '2026-11-10 09:15:00');
