<?php
declare(strict_types=1);

use PDOException;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed',
    ]);
    exit;
}

// Ensure POST data is available
if (empty($_POST)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No form data received. Please check your submission.',
    ]);
    exit;
}

$payload = [
    'space_id' => (int) ($_POST['space_id'] ?? 0),
    'date' => trim((string) ($_POST['date'] ?? '')),
    'start_time' => trim((string) ($_POST['start_time'] ?? '')),
    'end_time' => trim((string) ($_POST['end_time'] ?? '')),
    'people' => (int) ($_POST['people'] ?? 0),
    'customer_name' => trim((string) ($_POST['customer_name'] ?? '')),
    'customer_email' => trim((string) ($_POST['customer_email'] ?? '')),
    'customer_phone' => trim((string) ($_POST['customer_phone'] ?? '')),
    'notes' => trim((string) ($_POST['notes'] ?? '')),
];

$validation = validateBookingPayload($payload);
if ($validation['errors']) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => implode(' ', $validation['errors']),
    ]);
    exit;
}

try {
    $booking = createBooking($payload, $validation['space']);
    
    // Ensure no output before JSON
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Booking confirmed.',
        'booking' => $booking,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    exit;
} catch (PDOException $exception) {
    http_response_code(500);
    error_log('Booking PDO error: ' . $exception->getMessage());
    
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error. Please check your connection and try again.',
        'debug' => $exception->getMessage(),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    exit;
} catch (Throwable $exception) {
    http_response_code(500);
    error_log('Booking error: ' . $exception->getMessage());
    
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Unable to complete your booking. ' . $exception->getMessage(),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    exit;
}

function validateBookingPayload(array $payload): array
{
    $errors = [];

    if ($payload['space_id'] <= 0) {
        $errors[] = 'Select a valid workspace.';
    }

    if ($payload['date'] === '' || !validateDate($payload['date'])) {
        $errors[] = 'Choose a valid booking date.';
    }

    if ($payload['start_time'] === '' || !validateTime($payload['start_time'])) {
        $errors[] = 'Enter a valid start time.';
    }

    if ($payload['end_time'] === '' || !validateTime($payload['end_time'])) {
        $errors[] = 'Enter a valid end time.';
    }

    if ($payload['start_time'] && $payload['end_time'] && $payload['start_time'] >= $payload['end_time']) {
        $errors[] = 'End time must be later than the start time.';
    }

    if ($payload['people'] <= 0) {
        $errors[] = 'Enter the number of people attending.';
    }

    if ($payload['customer_name'] === '') {
        $errors[] = 'Enter your name.';
    }

    if (!filter_var($payload['customer_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    $space = null;
    if (!$errors) {
        $space = fetch_space($payload['space_id']);
        if (!$space) {
            $errors[] = 'The selected space is no longer available.';
        } elseif ($payload['people'] > (int) $space['capacity']) {
            $errors[] = 'This space cannot accommodate your group size.';
        }
    }

    if (!$errors) {
        $isAvailable = is_space_available_for_slot(
            $payload['space_id'],
            $payload['date'],
            $payload['start_time'],
            $payload['end_time']
        );
        if (!$isAvailable) {
            $errors[] = 'The selected time slot is not available. Please choose a different time or date.';
        }
    }

    return [
        'errors' => $errors,
        'space' => $space,
    ];
}

function createBooking(array $payload, array $space): array
{
    $pricing = calculate_dynamic_price(
        (float) $space['hourly_rate'],
        (string) $space['type_slug'],
        $payload['start_time'],
        $payload['end_time']
    );

    $totalPrice = $pricing['total_price'];

    if ($totalPrice <= 0) {
        throw new RuntimeException('Unable to calculate price.');
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO bookings
            (space_id, booking_date, start_time, end_time, people_count, customer_name, customer_email, customer_phone, notes, total_price)
         VALUES
            (:space_id, :booking_date, :start_time, :end_time, :people_count, :customer_name, :customer_email, :customer_phone, :notes, :total_price)'
    );

    try {
        $result = $stmt->execute([
            ':space_id' => $payload['space_id'],
            ':booking_date' => $payload['date'],
            ':start_time' => $payload['start_time'],
            ':end_time' => $payload['end_time'],
            ':people_count' => $payload['people'],
            ':customer_name' => $payload['customer_name'],
            ':customer_email' => $payload['customer_email'],
            ':customer_phone' => $payload['customer_phone'] ?: null,
            ':notes' => $payload['notes'] ?: null,
            ':total_price' => $totalPrice,
        ]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException('Execute failed: ' . ($errorInfo[2] ?? 'Unknown error'));
        }
    } catch (PDOException $e) {
        error_log('Booking insert PDO error: ' . $e->getMessage());
        throw new RuntimeException('Failed to save booking to database: ' . $e->getMessage());
    }

    $rowsAffected = $stmt->rowCount();
    if ($rowsAffected !== 1) {
        error_log("Booking insert affected {$rowsAffected} rows, expected 1");
        throw new RuntimeException('Booking could not be saved. No rows affected.');
    }

    $bookingId = (int) $pdo->lastInsertId();
    if ($bookingId === 0) {
        throw new RuntimeException('Booking was saved but could not retrieve booking ID.');
    }
    $duration = calculate_duration_hours($payload['start_time'], $payload['end_time']);

    return [
        'id' => $bookingId,
        'reference' => sprintf('BK-%06d', $bookingId),
        'space_id' => $payload['space_id'],
        'space_name' => $space['name'],
        'location' => sprintf('%s, %s', $space['area'], $space['city']),
        'date' => $payload['date'],
        'start_time' => $payload['start_time'],
        'end_time' => $payload['end_time'],
        'duration_hours' => $duration,
        'people' => $payload['people'],
        'total_price' => $totalPrice,
        'pricing' => $pricing,
    ];
}

function validateDate(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

function validateTime(string $value): bool
{
    $time = DateTime::createFromFormat('H:i', $value);
    return $time && $time->format('H:i') === $value;
}

