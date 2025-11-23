<?php
require_once '../reusable/db_setup.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate required fields
    if (empty($_POST['service_id']) || empty($_POST['appointment_date']) || empty($_POST['appointment_time'])) {
        $_SESSION['booking_error'] = "Please fill in all required fields";
        header("Location: booking.php");
        exit;
    }
    
    // Store appointment details in session
    $_SESSION['pending_appointment'] = [
        'service_id' => intval($_POST['service_id']),
        'appointment_date' => $_POST['appointment_date'],
        'appointment_time' => $_POST['appointment_time'],
        'notes' => $_POST['notes'] ?? ''
    ];
    
    // Redirect to payment page
    header("Location: ../payment/payment.php");
    exit;
} else {
    header("Location: booking.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include '../reusable/head.php'; ?>
  <title>Booking Error</title>
</head>

<body>
  <?php include '../reusable/header.php'; ?>

  <div class="container my-5">
    <div class="alert alert-danger">
      <h4>Error Processing Your Booking</h4>
      <p><?= isset($error) ? $error : 'An unexpected error occurred.' ?></p>
      <a href="booking.php" class="btn btn-outline-dark mt-3">Try Again</a>
    </div>
  </div>

  <?php include '../reusable/footer.php'; ?>
  <?php include '../reusable/scripts.php'; ?>
</body>

</html>