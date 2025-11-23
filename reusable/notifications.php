<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] === 'admin') {
    echo json_encode([]);
    exit;
}
require_once __DIR__ . '/appointment_check.php';
$appointments = getUpcomingAppointments($_SESSION['user_id']);
$out = [];
foreach ($appointments as $a) {
    $dt = $a['appointment_date'] . ' ' . $a['appointment_time'];
    $minutes = max(0, round((strtotime($dt) - time()) / 60));
    $out[] = [
        'id' => (int)$a['id'],
        'service' => $a['service_name'],
        'date' => $a['appointment_date'],
        'time' => $a['appointment_time'],
        'minutes' => $minutes
    ];
}
echo json_encode($out);
