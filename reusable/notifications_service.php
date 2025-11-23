<?php
require_once __DIR__ . '/appointment_check.php';

function createNotification($user_id, $type, $title, $message, $appointment_id = null) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, appointment_id, created_at) VALUES (?,?,?,?,?, NOW())");
    $stmt->bind_param("isssi", $user_id, $type, $title, $message, $appointment_id);
    $ok = $stmt->execute();
    $id = $ok ? $stmt->insert_id : null;
    $stmt->close();
    $conn->close();
    return $id;
}

function markNotificationRead($user_id, $notification_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    $conn->close();
    return $affected > 0;
}

function ensureUpcomingNotifications($user_id, $windowMinutes = 60) {
    $conn = getDbConnection();

    // Appointments within next windowMinutes (default 30) that are not in the past
    $stmt = $conn->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time, s.name AS service_name
        FROM appointments a
        JOIN services s ON s.id = a.service_id
        WHERE a.user_id = ?
          AND a.status = 'confirmed'
          AND CONCAT(a.appointment_date,' ',a.appointment_time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? MINUTE)
        ORDER BY a.appointment_date, a.appointment_time
    ");
    $stmt->bind_param("ii", $user_id, $windowMinutes);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($appt = $res->fetch_assoc()) {
        // Prevent duplicates per appointment for 'upcoming'
        $chk = $conn->prepare("SELECT id FROM notifications WHERE user_id=? AND appointment_id=? AND type='upcoming'");
        $chk->bind_param("ii", $user_id, $appt['id']);
        $chk->execute();
        $exist = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($exist) continue;

        $dtStr = date('M j, g:i A', strtotime($appt['appointment_date'].' '.$appt['appointment_time']));
        $title = 'Upcoming Appointment';
        $msg = "Your ".$appt['service_name']." appointment is coming up at ".$dtStr.".";
        $type = 'upcoming';
        $ins = $conn->prepare("INSERT INTO notifications (user_id,type,title,message,appointment_id,created_at) VALUES (?,?,?,?,?,NOW())");
        $ins->bind_param("isssi", $user_id, $type, $title, $msg, $appt['id']);
        $ins->execute();
        $ins->close();
    }

    $stmt->close();
    $conn->close();
}

function ensureTodayNotifications($user_id) {
    $conn = getDbConnection();

    // Appointments that are confirmed, scheduled today, and not in the past
    $stmt = $conn->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time, s.name AS service_name
        FROM appointments a
        JOIN services s ON s.id = a.service_id
        WHERE a.user_id = ?
          AND a.status = 'confirmed'
          AND a.appointment_date = CURDATE()
          AND CONCAT(a.appointment_date,' ',a.appointment_time) >= NOW()
        ORDER BY a.appointment_time
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($appt = $res->fetch_assoc()) {
        // Prevent duplicates for this appointment and type
        $chk = $conn->prepare("SELECT id FROM notifications WHERE user_id=? AND appointment_id=? AND type='today_reminder'");
        $chk->bind_param("ii", $user_id, $appt['id']);
        $chk->execute();
        $exist = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($exist) continue;

        $timeStr = date('g:i A', strtotime($appt['appointment_time']));
        $title = 'Today’s Appointment';
        $msg = "You have ".$appt['service_name']." today at ".$timeStr.".";
        $ins = $conn->prepare("INSERT INTO notifications (user_id,type,title,message,appointment_id,created_at) VALUES (?,?,?,?,?,NOW())");
        $type = 'today_reminder';
        $ins->bind_param("isssi", $user_id, $type, $title, $msg, $appt['id']);
        $ins->execute();
        $ins->close();
    }

    $stmt->close();
    $conn->close();
}

function getNotifications($user_id, $limit = 50) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT id, user_id, type, title, message, appointment_id, created_at, read_at
                            FROM notifications
                            WHERE user_id = ?
                            ORDER BY created_at DESC
                            LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();
    $conn->close();
    return $rows;
}

// Helpers to notify on appointment updates
function notifyAppointmentModified($appointment_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT a.id, a.user_id, a.appointment_date, a.appointment_time, s.name AS service_name
                            FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    if (!$r) return false;
    $when = date('M j, g:i A', strtotime($r['appointment_date'].' '.$r['appointment_time']));
    $title = 'Appointment Updated';
    $msg = "Your ".$r['service_name']." appointment has been updated to ".$when.".";
    return createNotification((int)$r['user_id'], 'appointment_modified', $title, $msg, $appointment_id);
}

function notifyAppointmentCancelled($appointment_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT a.id, a.user_id, a.appointment_date, a.appointment_time, s.name AS service_name
                            FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    if (!$r) return false;
    $title = 'Appointment Cancelled';
    $msg = "Your ".$r['service_name']." appointment has been cancelled.";
    return createNotification((int)$r['user_id'], 'appointment_cancelled', $title, $msg, $appointment_id);
}