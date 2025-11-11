<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'suggest') {
    echo json_encode(handleLocationSuggestions());
    exit;
}

if ($method === 'POST') {
    echo json_encode(handleSpaceFilter());
    exit;
}

http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Method not allowed',
]);
exit;

function handleLocationSuggestions(): array
{
    $term = trim((string) ($_GET['term'] ?? ''));
    $suggestions = fetch_location_suggestions($term);

    return [
        'success' => true,
        'suggestions' => $suggestions,
    ];
}

function handleSpaceFilter(): array
{
    $spaceType = trim((string) ($_POST['space_type'] ?? ''));
    $date = trim((string) ($_POST['date'] ?? ''));
    $startTime = trim((string) ($_POST['start_time'] ?? ''));
    $endTime = trim((string) ($_POST['end_time'] ?? ''));
    $people = (int) ($_POST['people'] ?? 1);
    $locationId = trim((string) ($_POST['location_id'] ?? ''));
    $locationTerm = trim((string) ($_POST['location_term'] ?? ''));

    $filters = [
        'space_type' => $spaceType,
        'date' => $date,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'capacity' => $people > 0 ? $people : 1,
        'people' => $people > 0 ? $people : 1,
    ];

    if ($locationId !== '') {
        $filters['location_id'] = $locationId;
    } elseif ($locationTerm !== '') {
        $filters['location_term'] = $locationTerm;
    }

    if ($date && (!validateDate($date) || $date < date('Y-m-d'))) {
        return [
            'success' => false,
            'message' => 'Please choose a valid date.',
        ];
    }

    if (($startTime && !validateTime($startTime)) || ($endTime && !validateTime($endTime))) {
        return [
            'success' => false,
            'message' => 'Please enter valid time values.',
        ];
    }

    if ($startTime && $endTime && $startTime >= $endTime) {
        return [
            'success' => false,
            'message' => 'Time until must be later than time from.',
        ];
    }

    $spaces = find_available_spaces($filters);

    return [
        'success' => true,
        'spaces' => $spaces,
        'applied_filters' => $filters,
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

