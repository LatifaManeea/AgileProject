<?php
require_once '../reusable/db_setup.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

// Check if there's a pending appointment
if (!isset($_SESSION['pending_appointment'])) {
    header("Location: ../booking/booking.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $pending = $_SESSION['pending_appointment'];
    $payment_method = $_POST['payment_method'];
    $payment_type = $_POST['payment_type'] ?? 'full';
    $partial_amount = isset($_POST['partial_amount']) ? floatval($_POST['partial_amount']) : 0;
    
    // Get service details
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->bind_param("i", $pending['service_id']);
    $stmt->execute();
    $service = $stmt->get_result()->fetch_assoc();
    
    if (!$service) {
        unset($_SESSION['pending_appointment']);
        $_SESSION['payment_error'] = "Invalid service selected";
        header("Location: ../booking/booking.php");
        exit;
    }

    // Calculate payment amount and dues
    $service_price = floatval($service['price']);
    $payment_amount = $service_price;
    $dues_amount = 0;

    if ($payment_type === 'partial') {
        // Validate partial payment (minimum 50%)
        $min_payment = $service_price * 0.5;
        if ($partial_amount < $min_payment || $partial_amount > $service_price) {
            $_SESSION['payment_error'] = "Partial payment must be between SAR " . number_format($min_payment, 2) . " and SAR " . number_format($service_price, 2);
            header("Location: payment.php");
            exit;
        }
        $payment_amount = $partial_amount;
        $dues_amount = $service_price - $partial_amount;
    }
    
    // Simulate payment processing delay
    sleep(2);
    
    // Generate transaction ID
    $transaction_id = 'TXN' . time() . rand(1000, 9999);
    
    // Create the appointment after successful payment
    $status = 'confirmed';
    $stmt = $conn->prepare("INSERT INTO appointments (user_id, service_id, appointment_date, appointment_time, status, notes, paid_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissssd", 
        $user_id, 
        $pending['service_id'], 
        $pending['appointment_date'], 
        $pending['appointment_time'], 
        $status, 
        $pending['notes'],
        $payment_amount
    );
    
    if ($stmt->execute()) {
        $appointment_id = $stmt->insert_id;

        // Record payment transaction
        $payStmt = $conn->prepare("INSERT INTO payments (user_id, appointment_id, transaction_id, amount, payment_method, payment_type, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $payment_notes = $payment_type === 'partial' ? "Partial payment - Dues: SAR " . number_format($dues_amount, 2) : "Full payment";
        $payStmt->bind_param("iisdsss", $user_id, $appointment_id, $transaction_id, $payment_amount, $payment_method, $payment_type, $payment_notes);
        $payStmt->execute();
        $payStmt->close();

        // Update user dues if partial payment
        if ($dues_amount > 0) {
            $duesStmt = $conn->prepare("UPDATE users SET dues = dues + ? WHERE id = ?");
            $duesStmt->bind_param("di", $dues_amount, $user_id);
            $duesStmt->execute();
            $duesStmt->close();
        }

        // Store payment and appointment information
        $_SESSION['payment_success'] = [
            'transaction_id' => $transaction_id,
            'appointment_id' => $appointment_id,
            'service_name' => $service['name'],
            'service_price' => $service['price'],
            'payment_amount' => $payment_amount,
            'payment_type' => $payment_type,
            'dues_amount' => $dues_amount,
            'appointment_date' => $pending['appointment_date'],
            'appointment_time' => $pending['appointment_time'],
            'payment_method' => $payment_method,
            'payment_date' => date('Y-m-d H:i:s')
        ];

        // Persist a booking confirmation notification
        require_once __DIR__ . '/../reusable/notifications_service.php';
        $whenStr = date('M j, g:i A', strtotime($pending['appointment_date'].' '.$pending['appointment_time']));
        $title = 'Booking Confirmed';
        $message = "Appointment confirmed for {$service['name']} on {$whenStr}.";
        createNotification($user_id, 'booking_confirmed', $title, $message, (int)$appointment_id);

        // Clear pending appointment
        unset($_SESSION['pending_appointment']);
        
        $stmt->close();
        $conn->close();
        
        // Redirect to confirmation
        header("Location: payment_success.php");
        exit;
    } else {
        $_SESSION['payment_error'] = "Error creating appointment. Please try again.";
        $stmt->close();
        $conn->close();
        header("Location: payment.php");
        exit;
    }
} else {
    header("Location: ../booking/booking.php");
    exit;
}
?>
