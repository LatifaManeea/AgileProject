<?php
require_once '../reusable/db_setup.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

// Check if user has outstanding dues
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
if ($user && floatval($user['dues'] ?? 0) > 0) {
    $_SESSION['booking_error'] = "You have outstanding dues of SAR " . number_format($user['dues'], 2) . ". Please clear your dues before booking a new appointment.";
    header("Location: ../login/profile.php");
    exit;
}

$conn = getDbConnection();
$services = $conn->query("SELECT * FROM services ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$conn->close();

$selected_service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : null;

// Display error if exists
$error = isset($_SESSION['booking_error']) ? $_SESSION['booking_error'] : '';
unset($_SESSION['booking_error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book Appointment - Glamour Salon</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php include '../reusable/header.php'; ?>

  <header class="text-center py-5 bg-light">
    <h1>Book Your Appointment</h1>
    <p>Schedule your glam session with ease!</p>
  </header>

  <main class="container my-5">
    <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <form method="POST" action="book.php" class="mx-auto" style="max-width: 600px;">
      <div class="mb-3">
        <label for="service" class="form-label">Service:</label>
        <select class="form-select" id="service" name="service_id" required>
          <option value="">Select a service</option>
          <?php foreach ($services as $service): ?>
            <option value="<?= $service['id'] ?>" <?= ($selected_service_id === $service['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($service['name']) ?> - SAR <?= number_format($service['price'], 2) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label for="date" class="form-label">Appointment Date:</label>
        <input type="date" class="form-control" id="date" name="appointment_date" 
               min="<?= date('Y-m-d') ?>" required>
      </div>

      <div class="mb-3">
        <label for="time" class="form-label">Appointment Time:</label>
        <input type="time" class="form-control" id="time" name="appointment_time" required>
      </div>

      <div class="mb-3">
        <label for="notes" class="form-label">Special Requests or Notes (Optional):</label>
        <textarea class="form-control" id="notes" name="notes" rows="3" 
                  placeholder="Any special requests or notes for your appointment..."></textarea>
      </div>

      <button type="submit" class="btn btn-custom w-100">
        <i class="fas fa-arrow-right me-2"></i>Proceed to Payment
      </button>
    </form>
  </main>

  <?php include '../reusable/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>