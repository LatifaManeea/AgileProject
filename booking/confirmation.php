<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login/login.php");
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include '../reusable/head.php'; ?>
  <title>Booking Confirmation</title>
</head>

<body>
  <?php include '../reusable/header.php'; ?>

  <div class="container my-5 text-center">
    <div class="card p-5">
      <i class="fas fa-check-circle text-success display-1 mb-4"></i>
      <h1 class="mb-4">Booking Confirmed!</h1>
      <p class="lead">Your appointment has been successfully scheduled.</p>
      <p>Thank you for choosing our salon.</p>
      <a href="/index.php" class="btn btn-custom mt-3">Return to Homepage</a>
    </div>
  </div>

  <?php include '../reusable/footer.php'; ?>
  <?php include '../reusable/scripts.php'; ?>
</body>

</html>