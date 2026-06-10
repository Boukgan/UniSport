-- UniSport Facility Reservation System (full schema + v6 migration)
-- Import in phpMyAdmin to create or reset the database.

CREATE DATABASE IF NOT EXISTS facility_reservation
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE facility_reservation;

DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS availability;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS facilities;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  user_id         INT AUTO_INCREMENT PRIMARY KEY,
  full_name       VARCHAR(120) NOT NULL,
  matric_number   VARCHAR(40)  DEFAULT NULL UNIQUE,
  email           VARCHAR(160) NOT NULL UNIQUE,
  phone           VARCHAR(30)  DEFAULT NULL,
  department      VARCHAR(120) DEFAULT NULL,
  password        VARCHAR(255) NOT NULL,
  profile_picture VARCHAR(255) DEFAULT NULL,
  role            ENUM('user','staff','admin') NOT NULL DEFAULT 'user',
  dark_mode       TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE facilities (
  facility_id        INT AUTO_INCREMENT PRIMARY KEY,
  facility_name      VARCHAR(120) NOT NULL,
  description        TEXT,
  capacity           INT DEFAULT 0,
  image              VARCHAR(255) DEFAULT NULL,
  operating_hours    VARCHAR(60) DEFAULT '8:00AM - 11:00PM',
  maintenance_status ENUM('available','limited','full','maintenance') NOT NULL DEFAULT 'available',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE reservations (
  reservation_id     INT AUTO_INCREMENT PRIMARY KEY,
  user_id            INT NOT NULL,
  facility_id        INT NOT NULL,
  booking_date       DATE NOT NULL,
  start_time         TIME NOT NULL,
  end_time           TIME NOT NULL,
  reservation_status ENUM('Pending','Confirmed','Cancelled','Completed') NOT NULL DEFAULT 'Pending',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)     REFERENCES users(user_id)         ON DELETE CASCADE,
  FOREIGN KEY (facility_id) REFERENCES facilities(facility_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE availability (
  availability_id INT AUTO_INCREMENT PRIMARY KEY,
  facility_id     INT NOT NULL,
  date            DATE NOT NULL,
  start_time      TIME NOT NULL,
  end_time        TIME NOT NULL,
  status          ENUM('available','limited','full','maintenance') NOT NULL DEFAULT 'available',
  UNIQUE KEY uniq_slot (facility_id, date, start_time),
  FOREIGN KEY (facility_id) REFERENCES facilities(facility_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  title           VARCHAR(160) NOT NULL DEFAULT 'Notification',
  message         VARCHAR(500) NOT NULL,
  is_read         TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  token       VARCHAR(128) NOT NULL UNIQUE,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_token (token)
) ENGINE=InnoDB;

-- ===== Sample users (password = "password123") =====
INSERT INTO users (full_name, matric_number, email, phone, department, password, role) VALUES
('System Admin',  NULL,        'admin@utem.edu.my',             '0123456789','Sports Centre','$2b$10$RvKvQ0vCdGWEWP6Ossire.1Qr1r/1OEtKwiqKDoS0L29nt3b4MVLO','admin'),
('Ahmad Faiz',    'D032410091','d032410091@student.utem.edu.my','0111111111','FTMK',         '$2b$10$RvKvQ0vCdGWEWP6Ossire.1Qr1r/1OEtKwiqKDoS0L29nt3b4MVLO','user'),
('Siti Nurul',    'D012410456','d012410456@student.utem.edu.my','0122222222','FKE',          '$2b$10$RvKvQ0vCdGWEWP6Ossire.1Qr1r/1OEtKwiqKDoS0L29nt3b4MVLO','user'),
('Dr. Rahman',    NULL,        'rahman@utem.edu.my',            '0133333333','Sports Centre','$2b$10$RvKvQ0vCdGWEWP6Ossire.1Qr1r/1OEtKwiqKDoS0L29nt3b4MVLO','staff');

-- ===== Sample facilities =====
INSERT INTO facilities (facility_name, description, capacity, image, operating_hours, maintenance_status) VALUES
('Indoor Stadium',     'Multi-purpose indoor stadium suitable for basketball, volleyball, and large indoor events.', 1500,'indoor_stadium.jpg',  '8:00AM - 11:00PM','available'),
('Outdoor Stadium',    'Main UTeM outdoor stadium with running track and central field.',                            5000,'outdoor_stadium.jpg', '8:00AM - 11:00PM','available'),
('Indoor Gym',         'Fully equipped gym with cardio and weight training machines.',                                  60,'gym.jpg',             '8:00AM - 11:00PM','limited'),
('Tennis Court',       'Hard surface tennis court available for singles or doubles.',                                    4,'tennis.jpg',          '8:00AM - 11:00PM','available'),
('Rugby Field',        'Full-size grass rugby field with goal posts.',                                                  30,'rugby.jpg',           '8:00AM - 11:00PM','available'),
('Archery Field',      'Outdoor archery range with target stands.',                                                     12,'archery.jpg',         '8:00AM - 11:00PM','maintenance'),
('Volleyball Field',   'Outdoor volleyball court with sand surface.',                                                   12,'volleyball.jpg',      '8:00AM - 11:00PM','available'),
('Handball Field',     'Outdoor handball field with artificial turf.',                                                  14,'handball_field.jpg',  '8:00AM - 11:00PM','available'),
('Futsal Court',       'Indoor futsal court with proper sports flooring.',                                              10,'futsal_court.jpg',    '8:00AM - 11:00PM','full'),
('Takraw Court',       'Standard takraw court for sepak takraw matches.',                                                6,'takraw.jpg',          '8:00AM - 11:00PM','available'),
('Softball Field',     'Outdoor softball field with bases and dugouts.',                                                20,'softball.jpg',        '8:00AM - 11:00PM','available'),
('Football Field',     'Main football field with natural grass.',                                                       30,'football.jpg',        '8:00AM - 11:00PM','available'),
('Kayak',              'Kayak rental at the campus lake. Includes life vest and paddle.',                               20,'kayak.jpg',           '8:00AM - 11:00PM','limited'),
('Hockey Field',       'Outdoor field hockey pitch with synthetic turf and goal posts.',                                22,'hockey_field.jpg',    '8:00AM - 11:00PM','available'),
('Basketball Court',   'Outdoor basketball court with hardwood-style surface and hoops.',                               10,'basketball_court.jpg','8:00AM - 11:00PM','available'),
('Netball Field',      'Standard outdoor netball court with goal rings.',                                               14,'netball_field.jpg',   '8:00AM - 11:00PM','available');

INSERT INTO reservations (user_id, facility_id, booking_date, start_time, end_time, reservation_status) VALUES
(2, 3, CURDATE() + INTERVAL 2 DAY, '08:00:00', '09:00:00', 'Pending'),
(2, 1, CURDATE() + INTERVAL 5 DAY, '10:00:00', '12:00:00', 'Confirmed'),
(3, 5, CURDATE() - INTERVAL 3 DAY, '14:00:00', '16:00:00', 'Completed');

INSERT INTO notifications (user_id, title, message) VALUES
(2, 'Welcome to UniSport', 'Reserve a facility from the dashboard.'),
(2, 'Reservation submitted', 'Your reservation #1 is awaiting approval.'),
(3, 'Reservation completed', 'Thanks for using UniSport!');

-- ==========================================
-- v6 migration: prevent concurrent double-booking at the DB level
-- ==========================================
ALTER TABLE reservations
  ADD UNIQUE KEY uniq_booking (facility_id, booking_date, start_time);
