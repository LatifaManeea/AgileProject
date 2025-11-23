<?php
require_once __DIR__ . '/db_setup.php';

function getUpcomingAppointments($user_id, $windowMinutes = 30) {
    $conn = getDbConnection();
    $now = date('Y-m-d H:i:s');
    $end = date('Y-m-d H:i:s', strtotime("+$windowMinutes minutes"));

    $stmt = $conn->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time, a.status, s.name AS service_name
        FROM appointments a
        JOIN services s ON s.id = a.service_id
        WHERE a.user_id = ?
          AND a.status = 'confirmed'
          AND CONCAT(a.appointment_date,' ',a.appointment_time) BETWEEN ? AND ?
        ORDER BY a.appointment_date, a.appointment_time
    ");
    $stmt->bind_param("iss", $user_id, $now, $end);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    $conn->close();
    return $rows;
}
?>
