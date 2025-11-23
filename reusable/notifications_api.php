<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
header('Content-Type: application/json');

// Guard: only for logged-in non-admin users
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] === 'admin') {
    echo json_encode([]);
    exit;
}

require_once __DIR__ . '/notifications_service.php';

$user_id = (int)$_SESSION['user_id'];

// POST: mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    // Support JSON payloads too
    if (empty($input)) {
        $json = json_decode(file_get_contents('php://input'), true);
        if (is_array($json)) $input = $json;
    }
    $action = isset($input['action']) ? $input['action'] : null;
    if ($action === 'ack' && isset($input['id'])) {
        $nid = (int)$input['id'];
        $ok = markNotificationRead($user_id, $nid);
        echo json_encode(['ok' => $ok]);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'Invalid action']);
    exit;
}

// GET: generate and fetch
ensureUpcomingNotifications($user_id, 60);
ensureTodayNotifications($user_id);
$rows = getNotifications($user_id);

$out = [];
foreach ($rows as $r) {
    $out[] = [
        'id' => (int)$r['id'],
        'type' => $r['type'],
        'title' => $r['title'],
        'message' => $r['message'],
        'appointment_id' => $r['appointment_id'] ? (int)$r['appointment_id'] : null,
        'created_at' => $r['created_at'],
        'read' => !empty($r['read_at'])
    ];
}

echo json_encode($out);
exit;