CREATE DATABASE IF NOT EXISTS booking_db;
USE booking_db;

DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS space_availability;
DROP TABLE IF EXISTS spaces;
DROP TABLE IF EXISTS locations;
DROP TABLE IF EXISTS space_types;

CREATE TABLE space_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    slug VARCHAR(60) NOT NULL UNIQUE
);

CREATE TABLE locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    city VARCHAR(80) NOT NULL,
    area VARCHAR(80) NOT NULL
);

CREATE TABLE spaces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    space_type_id INT NOT NULL,
    location_id INT NOT NULL,
    capacity INT NOT NULL,
    hourly_rate DECIMAL(10, 2) NOT NULL,
    description TEXT,
    FOREIGN KEY (space_type_id) REFERENCES space_types(id),
    FOREIGN KEY (location_id) REFERENCES locations(id)
);

CREATE TABLE space_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    space_id INT NOT NULL,
    available_date DATE NOT NULL,
    open_time TIME NOT NULL,
    close_time TIME NOT NULL,
    FOREIGN KEY (space_id) REFERENCES spaces(id)
);

CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    space_id INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    people_count INT NOT NULL,
    customer_name VARCHAR(120) NOT NULL,
    customer_email VARCHAR(120) NOT NULL,
    customer_phone VARCHAR(40) NULL,
    notes TEXT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (space_id) REFERENCES spaces(id)
);

INSERT INTO space_types (name, slug) VALUES
('Meeting Room', 'meeting-room'),
('Day Office', 'day-office'),
('Co-working', 'co-working'),
('Private Office', 'private'),
('Custom Space', 'custom');

INSERT INTO locations (city, area) VALUES
('Mumbai', 'Bandra Kurla Complex'),
('Mumbai', 'Lower Parel'),
('Bengaluru', 'Koramangala'),
('Bengaluru', 'Whitefield'),
('Delhi', 'Connaught Place'),
('Hyderabad', 'HITEC City');

INSERT INTO spaces (name, space_type_id, location_id, capacity, hourly_rate, description) VALUES
('Gateway Boardroom', 1, 1, 12, 1800.00, 'Premium meeting room with video conferencing and concierge support.'),
('Sea Link Day Office', 2, 2, 4, 1200.00, 'Private day office with skyline views and ergonomic seating.'),
('Startup Collaboration Hub', 3, 3, 24, 750.00, 'Open co-working floor with hot desks, lockers, and breakout lounges.'),
('Skyline Executive Suite', 4, 4, 8, 1500.00, 'Secluded private office designed for leadership offsites.'),
('Capital Innovation Lab', 5, 5, 32, 2800.00, 'Flexible lab with modular furniture, AV wall, and catering pantry.'),
('Charminar Workshop Loft', 5, 6, 40, 3000.00, 'Industrial loft ideal for workshops and product showcases.');

INSERT INTO space_availability (space_id, available_date, open_time, close_time) VALUES
(1, CURDATE(), '08:00:00', '18:00:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00', '18:00:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '08:00:00', '18:00:00'),
(2, CURDATE(), '09:00:00', '17:00:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', '17:00:00'),
(3, CURDATE(), '07:00:00', '20:00:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '07:00:00', '20:00:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '07:00:00', '20:00:00'),
(4, CURDATE(), '08:00:00', '19:00:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00', '19:00:00'),
(5, CURDATE(), '08:00:00', '22:00:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00', '22:00:00'),
(6, CURDATE(), '08:00:00', '22:00:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00', '22:00:00');

