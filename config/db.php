<?php
declare(strict_types=1);

/**
 * Centralised database utilities for the booking portal.
 *
 * - Uses PDO for secure queries and automatic prepared statements.
 * - Exposes helpers for space types, locations, availability, and pricing.
 */

/**
 * Retrieve database connection configuration, allowing environment overrides.
 */
function get_db_config(): array
{
    return [
        'host' => getenv('BOOKING_DB_HOST') ?: 'localhost',
        'port' => getenv('BOOKING_DB_PORT') ?: '3306',
        'name' => getenv('BOOKING_DB_NAME') ?: 'booking_db',
        'user' => getenv('BOOKING_DB_USER') ?: 'root',
        'pass' => getenv('BOOKING_DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ];
}

/**
 * Shared PDO instance for the application.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = get_db_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['name'],
        $config['charset']
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
    } catch (PDOException $exception) {
        http_response_code(500);
        die('Database connection failed: ' . $exception->getMessage());
    }

    return $pdo;
}

/**
 * Fetch all available space types ordered alphabetically.
 */
function fetch_space_types(): array
{
    $statement = db()->query(
        'SELECT id, name, slug FROM space_types ORDER BY name ASC'
    );

    return $statement->fetchAll() ?: [];
}

/**
 * Return location suggestions (city/area) matching a search term.
 */
function fetch_location_suggestions(string $term = '', int $limit = 8): array
{
    $pdo = db();

    if ($term === '') {
        $stmt = $pdo->prepare(
            'SELECT id, city, area FROM locations ORDER BY city ASC, area ASC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, city, area
             FROM locations
             WHERE city LIKE :like OR area LIKE :like
             ORDER BY city ASC, area ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':like', '%' . $term . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    }

    $results = [];
    foreach ($stmt->fetchAll() as $row) {
        $results[] = [
            'id' => (int) $row['id'],
            'city' => $row['city'],
            'area' => $row['area'],
            'label' => sprintf('%s, %s', $row['area'], $row['city']),
        ];
    }

    return $results;
}

/**
 * Retrieve a single space record with relations.
 */
function fetch_space(int $spaceId): ?array
{
    $stmt = db()->prepare(
        'SELECT s.id,
                s.name,
                s.capacity,
                s.hourly_rate,
                s.description,
                st.name AS type_name,
                st.slug AS type_slug,
                l.city,
                l.area
         FROM spaces s
         INNER JOIN space_types st ON st.id = s.space_type_id
         INNER JOIN locations l ON l.id = s.location_id
         WHERE s.id = :space_id'
    );
    $stmt->execute([':space_id' => $spaceId]);
    $space = $stmt->fetch();

    return $space ?: null;
}

/**
 * Determine if a space is free for the requested slot.
 */
function is_space_available_for_slot(int $spaceId, string $date, string $startTime, string $endTime): bool
{
    $pdo = db();

    // Check if space exists
    $spaceCheck = $pdo->prepare('SELECT id FROM spaces WHERE id = :space_id');
    $spaceCheck->execute([':space_id' => $spaceId]);
    if ($spaceCheck->rowCount() === 0) {
        return false;
    }

    // Check if there's a specific availability record that covers this slot
    $availabilityStmt = $pdo->prepare(
        'SELECT COUNT(*) AS matches
         FROM space_availability
         WHERE space_id = :space_id
           AND available_date = :date
           AND open_time <= :start_time
           AND close_time >= :end_time'
    );
    $availabilityStmt->execute([
        ':space_id' => $spaceId,
        ':date' => $date,
        ':start_time' => $startTime,
        ':end_time' => $endTime,
    ]);

    $hasAvailabilityRecord = (int) $availabilityStmt->fetchColumn() > 0;
    
    // If no specific availability record exists, check if there's any availability record for this date
    // If no records at all, we'll allow the booking (treat as available)
    if (!$hasAvailabilityRecord) {
        $anyAvailabilityStmt = $pdo->prepare(
            'SELECT COUNT(*) AS matches
             FROM space_availability
             WHERE space_id = :space_id
               AND available_date = :date'
        );
        $anyAvailabilityStmt->execute([
            ':space_id' => $spaceId,
            ':date' => $date,
        ]);
        
        $hasAnyRecord = (int) $anyAvailabilityStmt->fetchColumn() > 0;
        
        // If there are availability records for this date but none cover the requested time, deny
        if ($hasAnyRecord) {
            return false;
        }
        // If no availability records exist for this date, allow booking (assume available)
    }

    // Ensure no overlapping bookings already exist.
    $bookingStmt = $pdo->prepare(
        'SELECT COUNT(*) AS conflicts
         FROM bookings
         WHERE space_id = :space_id
           AND booking_date = :date
           AND (:start_time < end_time AND :end_time > start_time)'
    );
    $bookingStmt->execute([
        ':space_id' => $spaceId,
        ':date' => $date,
        ':start_time' => $startTime,
        ':end_time' => $endTime,
    ]);

    return (int) $bookingStmt->fetchColumn() === 0;
}

/**
 * Calculate the duration in hours between two HH:MM formatted times.
 */
function calculate_duration_hours(string $startTime, string $endTime): float
{
    $start = DateTime::createFromFormat('H:i', $startTime);
    $end = DateTime::createFromFormat('H:i', $endTime);

    if (!$start || !$end || $end <= $start) {
        return 0.0;
    }

    $interval = $start->diff($end);
    $minutes = ($interval->h * 60) + $interval->i;

    return round($minutes / 60, 2);
}

/**
 * Produce the total price for a space based on rate and requested slot.
 */
function calculate_price(float $hourlyRate, string $startTime, string $endTime): float
{
    $durationHours = calculate_duration_hours($startTime, $endTime);
    if ($durationHours <= 0) {
        return 0.0;
    }

    return round($hourlyRate * $durationHours, 2);
}

/**
 * Multiplier per space category.
 */
function get_category_multiplier(string $typeSlug): float
{
    $multipliers = [
        'meeting-room' => 1.0,
        'day-office' => 1.05,
        'co-working' => 0.9,
        'private' => 1.2,
        'custom' => 1.3,
    ];

    return $multipliers[$typeSlug] ?? 1.0;
}

/**
 * Multiplier applied when the booking duration increases.
 *
 * - ≤ 2 hours: base rate
 * - > 2 to 4 hours: +10%
 * - > 4 to 6 hours: +20%
 * - > 6 hours: +35%
 */
function get_duration_multiplier(float $durationHours): float
{
    if ($durationHours <= 2) {
        return 1.0;
    }

    if ($durationHours <= 4) {
        return 1.1;
    }

    if ($durationHours <= 6) {
        return 1.2;
    }

    return 1.35;
}

/**
 * Calculate total, duration, and multipliers for a booking slot.
 */
function calculate_dynamic_price(float $hourlyRate, string $typeSlug, string $startTime, string $endTime): array
{
    $durationHours = calculate_duration_hours($startTime, $endTime);
    if ($durationHours <= 0) {
        return [
            'duration' => 0.0,
            'category_multiplier' => 1.0,
            'duration_multiplier' => 1.0,
            'base_price' => 0.0,
            'total_price' => 0.0,
        ];
    }

    $basePrice = round($hourlyRate * $durationHours, 2);
    $categoryMultiplier = get_category_multiplier($typeSlug);
    $durationMultiplier = get_duration_multiplier($durationHours);
    $totalPrice = round($basePrice * $categoryMultiplier * $durationMultiplier, 2);

    return [
        'duration' => $durationHours,
        'category_multiplier' => $categoryMultiplier,
        'duration_multiplier' => $durationMultiplier,
        'base_price' => $basePrice,
        'total_price' => $totalPrice,
    ];
}

/**
 * Core query to gather spaces honouring filters and availability.
 */
function find_available_spaces(array $filters): array
{
    $pdo = db();
    $conditions = [];
    $params = [];

    if (!empty($filters['space_type'])) {
        $conditions[] = 'st.slug = :space_type';
        $params[':space_type'] = $filters['space_type'];
    }

    if (!empty($filters['capacity'])) {
        $conditions[] = 's.capacity >= :capacity';
        $params[':capacity'] = (int) $filters['capacity'];
    }

    if (!empty($filters['location_id'])) {
        $conditions[] = 's.location_id = :location_id';
        $params[':location_id'] = (int) $filters['location_id'];
    } elseif (!empty($filters['location_term'])) {
        $conditions[] = '(l.city LIKE :location_term OR l.area LIKE :location_term)';
        $params[':location_term'] = '%' . $filters['location_term'] . '%';
    }

    $sql = 'SELECT s.id,
                   s.name,
                   s.capacity,
                   s.hourly_rate,
                   s.description,
                   st.name AS type_name,
                   st.slug AS type_slug,
                   l.city,
                   l.area
            FROM spaces s
            INNER JOIN space_types st ON st.id = s.space_type_id
            INNER JOIN locations l ON l.id = s.location_id';

    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY s.name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $spaces = $stmt->fetchAll();

    if (!$spaces) {
        return [];
    }

    $date = $filters['date'] ?? null;
    $startTime = $filters['start_time'] ?? null;
    $endTime = $filters['end_time'] ?? null;

    $results = [];
    foreach ($spaces as $space) {
        $spaceId = (int) $space['id'];
        $isAvailable = true;
        $price = null;
        $duration = null;
        $pricing = null;

        if ($date && $startTime && $endTime) {
            $isAvailable = is_space_available_for_slot($spaceId, $date, $startTime, $endTime);
            $pricing = calculate_dynamic_price((float) $space['hourly_rate'], (string) $space['type_slug'], $startTime, $endTime);
            $duration = $pricing['duration'];
            $price = $pricing['total_price'];
        }

        if (!$isAvailable) {
            continue;
        }

        $results[] = array_merge($space, [
            'location_label' => sprintf('%s, %s', $space['area'], $space['city']),
            'duration_hours' => $duration,
            'total_price' => $price,
            'pricing' => $pricing ?? null,
        ]);
    }

    return $results;
}

