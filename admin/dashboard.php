<?php
require_once '../reusable/db_setup.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isUserAdmin($_SESSION['user_id'])) {
  header("Location: ../login/login.php");
  exit;
}

// Handle adding new service
$serviceMessage = '';
if (isset($_POST['add_service'])) {
  $name = $_POST['service_name'];
  $description = $_POST['service_description'];
  $price = $_POST['service_price'];

  $conn = getDbConnection();
  $stmt = $conn->prepare("INSERT INTO services (name, description, price) VALUES (?, ?, ?)");
  $stmt->bind_param("ssd", $name, $description, $price);

  if ($stmt->execute()) {
    $serviceMessage = '<div class="alert alert-success">Service added successfully!</div>';
  } else {
    $serviceMessage = '<div class="alert alert-danger">Error adding service: ' . $conn->error . '</div>';
  }

  $stmt->close();
  $conn->close();
}

// Handle editing existing service
if (isset($_POST['edit_service'])) {
  $service_id = $_POST['service_id'];
  $name = $_POST['service_name'];
  $description = $_POST['service_description'];
  $price = $_POST['service_price'];

  $conn = getDbConnection();
  $stmt = $conn->prepare("UPDATE services SET name = ?, description = ?, price = ? WHERE id = ?");
  $stmt->bind_param("ssdi", $name, $description, $price, $service_id);

  if ($stmt->execute()) {
    $serviceMessage = '<div class="alert alert-success">Service updated successfully!</div>';
  } else {
    $serviceMessage = '<div class="alert alert-danger">Error updating service: ' . $conn->error . '</div>';
  }

  $stmt->close();
  $conn->close();
}

// Handle deleting service
if (isset($_POST['delete_service'])) {
  $service_id = $_POST['service_id'];

  $conn = getDbConnection();
  // Check if service has any appointments
  $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE service_id = ?");
  $checkStmt->bind_param("i", $service_id);
  $checkStmt->execute();
  $result = $checkStmt->get_result()->fetch_assoc();
  $checkStmt->close();

  if ($result['count'] > 0) {
    $serviceMessage = '<div class="alert alert-warning">Cannot delete service: It has ' . $result['count'] . ' associated appointment(s). Please reassign or delete those appointments first.</div>';
  } else {
    $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
    $stmt->bind_param("i", $service_id);

    if ($stmt->execute()) {
      $serviceMessage = '<div class="alert alert-success">Service deleted successfully!</div>';
    } else {
      $serviceMessage = '<div class="alert alert-danger">Error deleting service: ' . $conn->error . '</div>';
    }

    $stmt->close();
  }
  $conn->close();
}

// Handle rescheduling appointment
$appointmentMessage = '';
if (isset($_POST['reschedule_appointment'])) {
  require_once __DIR__ . '/../reusable/notifications_service.php';
  $appointment_id = (int)$_POST['appointment_id'];
  $new_date = $_POST['new_date'];
  $new_time = $_POST['new_time'];
  $status = $_POST['status'];

  $conn = getDbConnection();
  // Fetch existing values to detect changes
  $origStmt = $conn->prepare("SELECT appointment_date, appointment_time, status FROM appointments WHERE id = ?");
  $origStmt->bind_param("i", $appointment_id);
  $origStmt->execute();
  $orig = $origStmt->get_result()->fetch_assoc();
  $origStmt->close();

  $stmt = $conn->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = ? WHERE id = ?");
  $stmt->bind_param("sssi", $new_date, $new_time, $status, $appointment_id);

  if ($stmt->execute()) {
    $appointmentMessage = '<div class="alert alert-success">Appointment rescheduled successfully!</div>';
    // Decide notification
    if ($orig) {
      $dateChanged = ($orig['appointment_date'] !== $new_date);
      $timeChanged = ($orig['appointment_time'] !== $new_time);
      $statusChanged = ($orig['status'] !== $status);
      if ($status === 'cancelled' && $orig['status'] !== 'cancelled') {
        notifyAppointmentCancelled($appointment_id);
      } elseif ($dateChanged || $timeChanged || $statusChanged) {
        notifyAppointmentModified($appointment_id);
      }
    }
  } else {
    $appointmentMessage = '<div class="alert alert-danger">Error rescheduling appointment: ' . $conn->error . '</div>';
  }

  $stmt->close();
  $conn->close();
}

// Handle cash settlement of user dues
$duesMessage = '';
if (isset($_POST['settle_dues'])) {
  $settle_user_id = (int)$_POST['user_id'];
  $settle_amount = floatval($_POST['dues_amount']);
  
  $conn = getDbConnection();
  
  // Record cash settlement payment
  $transaction_id = 'CASH' . time() . rand(1000, 9999);
  $stmt = $conn->prepare("INSERT INTO payments (user_id, transaction_id, amount, payment_method, payment_type, notes, created_at) VALUES (?, ?, ?, 'cash', 'cash_settlement', 'Admin cash settlement', NOW())");
  $stmt->bind_param("isd", $settle_user_id, $transaction_id, $settle_amount);
  $stmt->execute();
  $stmt->close();
  
  // Clear user dues
  $stmt = $conn->prepare("UPDATE users SET dues = 0 WHERE id = ?");
  $stmt->bind_param("i", $settle_user_id);
  if ($stmt->execute()) {
    $duesMessage = '<div class="alert alert-success">Dues settled successfully for user!</div>';
  } else {
    $duesMessage = '<div class="alert alert-danger">Error settling dues: ' . $conn->error . '</div>';
  }
  $stmt->close();
  $conn->close();
}

// Get all users
function getAllUsers() {
  $conn = getDbConnection();
  $result = $conn->query("SELECT id, username, email, role, dues, created_at FROM users ORDER BY created_at DESC");
  $users = [];
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $users[] = $row;
    }
  }
  $conn->close();
  return $users;
}

// Handle user role update
$userMessage = '';
if (isset($_POST['update_user_role'])) {
  $user_id = (int)$_POST['user_id'];
  $new_role = $_POST['role'];
  
  $conn = getDbConnection();
  $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
  $stmt->bind_param("si", $new_role, $user_id);
  
  if ($stmt->execute()) {
    $userMessage = '<div class="alert alert-success">User role updated successfully!</div>';
  } else {
    $userMessage = '<div class="alert alert-danger">Error updating user: ' . $conn->error . '</div>';
  }
  
  $stmt->close();
  $conn->close();
}

// Handle user deletion
if (isset($_POST['delete_user'])) {
  $user_id = (int)$_POST['user_id'];
  
  // Prevent deleting yourself
  if ($user_id == $_SESSION['user_id']) {
    $userMessage = '<div class="alert alert-danger">You cannot delete your own account!</div>';
  } else {
    $conn = getDbConnection();
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
      $userMessage = '<div class="alert alert-success">User deleted successfully!</div>';
    } else {
      $userMessage = '<div class="alert alert-danger">Error deleting user: ' . $conn->error . '</div>';
    }
    
    $stmt->close();
    $conn->close();
  }
}

// Get all payment methods
function getAllPaymentMethods() {
  $conn = getDbConnection();
  $result = $conn->query("SELECT * FROM accepted_payment_methods ORDER BY method_name");
  $methods = [];
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $methods[] = $row;
    }
  }
  $conn->close();
  return $methods;
}

// Handle payment method toggle
$paymentMethodMessage = '';
if (isset($_POST['toggle_payment_method'])) {
  $method_id = (int)$_POST['method_id'];
  $is_enabled = (int)$_POST['is_enabled'];
  
  $conn = getDbConnection();
  $stmt = $conn->prepare("UPDATE accepted_payment_methods SET is_enabled = ? WHERE id = ?");
  $stmt->bind_param("ii", $is_enabled, $method_id);
  
  if ($stmt->execute()) {
    $status = $is_enabled ? 'enabled' : 'disabled';
    $paymentMethodMessage = '<div class="alert alert-success">Payment method ' . $status . ' successfully!</div>';
  } else {
    $paymentMethodMessage = '<div class="alert alert-danger">Error updating payment method: ' . $conn->error . '</div>';
  }
  
  $stmt->close();
  $conn->close();
}

// Get all pending refund requests
function getPendingRefundRequests() {
  $conn = getDbConnection();
  $result = $conn->query("
    SELECT r.*, u.username, u.email, a.appointment_date, a.appointment_time, s.name as service_name
    FROM refund_requests r
    JOIN users u ON r.user_id = u.id
    JOIN appointments a ON r.appointment_id = a.id
    JOIN services s ON a.service_id = s.id
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC
  ");
  $requests = [];
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $requests[] = $row;
    }
  }
  $conn->close();
  return $requests;
}

// Handle refund request processing
$refundRequestMessage = '';
if (isset($_POST['process_refund'])) {
  $refund_id = (int)$_POST['refund_id'];
  $action = $_POST['action']; // approve or reject
  $admin_notes = trim($_POST['admin_notes']);
  $admin_id = $_SESSION['user_id'];
  
  $conn = getDbConnection();
  
  // Get refund request details
  $stmt = $conn->prepare("SELECT r.*, a.paid_amount FROM refund_requests r JOIN appointments a ON r.appointment_id = a.id WHERE r.id = ?");
  $stmt->bind_param("i", $refund_id);
  $stmt->execute();
  $refund = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  
  if ($refund && $refund['status'] === 'pending') {
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    
    // Update refund request status
    $updateStmt = $conn->prepare("UPDATE refund_requests SET status = ?, admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("ssii", $new_status, $admin_notes, $admin_id, $refund_id);
    
    if ($updateStmt->execute()) {
      // If approved, credit the amount back to user (reduce dues or add credit)
      if ($action === 'approve') {
        // Reduce user dues by refund amount
        $creditStmt = $conn->prepare("UPDATE users SET dues = GREATEST(0, dues - ?) WHERE id = ?");
        $creditStmt->bind_param("di", $refund['amount'], $refund['user_id']);
        $creditStmt->execute();
        $creditStmt->close();
        
        // Record refund transaction
        $transaction_id = 'REFUND' . time() . rand(1000, 9999);
        $refund_notes = 'Refund approved: ' . $admin_notes;
        $payStmt = $conn->prepare("INSERT INTO payments (user_id, appointment_id, transaction_id, amount, payment_method, payment_type, notes, created_at) VALUES (?, ?, ?, ?, 'refund', 'full', ?, NOW())");
        $negative_amount = -abs($refund['amount']);
        $payStmt->bind_param("iisds", $refund['user_id'], $refund['appointment_id'], $transaction_id, $negative_amount, $refund_notes);
        $payStmt->execute();
        $payStmt->close();
      }
      
      $statusText = $action === 'approve' ? 'approved' : 'rejected';
      $refundRequestMessage = '<div class="alert alert-success">Refund request ' . $statusText . ' successfully!</div>';
    } else {
      $refundRequestMessage = '<div class="alert alert-danger">Error processing refund: ' . $conn->error . '</div>';
    }
    
    $updateStmt->close();
  } else {
    $refundRequestMessage = '<div class="alert alert-danger">Invalid refund request or already processed.</div>';
  }
  
  $conn->close();
}

// Get all services
$services = getAllServices();

// Get all appointments with user and service details
function getAllAppointmentsWithDetails()
{
  $conn = getDbConnection();
  $sql = "SELECT a.*, u.username, u.email, s.name as service_name, s.price 
            FROM appointments a 
            JOIN users u ON a.user_id = u.id 
            JOIN services s ON a.service_id = s.id 
            ORDER BY a.appointment_date DESC, a.appointment_time DESC";

  $result = $conn->query($sql);

  $appointments = [];
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $appointments[] = $row;
    }
  }

  $conn->close();
  return $appointments;
}

// Get all users with dues
function getUsersWithDues() {
  $conn = getDbConnection();
  $result = $conn->query("SELECT id, username, email, dues FROM users WHERE dues > 0 ORDER BY dues DESC");
  $users = [];
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $users[] = $row;
    }
  }
  $conn->close();
  return $users;
}

// Get dashboard statistics
function getDashboardStats()
{
  $conn = getDbConnection();

  // Total appointments
  $totalAppts = $conn->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];

  // Today's appointments
  $today = date('Y-m-d');
  $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ?");
  $stmt->bind_param("s", $today);
  $stmt->execute();
  $todayAppts = $stmt->get_result()->fetch_assoc()['count'];

  // Upcoming appointments (next 7 days)
  $next7days = date('Y-m-d', strtotime('+7 days'));
  $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ?");
  $stmt->bind_param("ss", $today, $next7days);
  $stmt->execute();
  $upcomingAppts = $stmt->get_result()->fetch_assoc()['count'];

  // Revenue stats
  $totalRevenue = $conn->query("SELECT SUM(s.price) as total FROM appointments a JOIN services s ON a.service_id = s.id")->fetch_assoc()['total'];
  if (!$totalRevenue) $totalRevenue = 0;

  $stats = [
    'total_appointments' => $totalAppts,
    'today_appointments' => $todayAppts,
    'upcoming_appointments' => $upcomingAppts,
    'total_revenue' => $totalRevenue
  ];

  $conn->close();
  return $stats;
}

$appointments = getAllAppointmentsWithDetails();
$stats = getDashboardStats();
$usersWithDues = getUsersWithDues();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include '../reusable/head.php'; ?>
  <title>Admin Dashboard - Glamour Salon</title>
  <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>

<body>
  <?php include '../reusable/header.php'; ?>

  <header class="py-5 bg-light">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-8">
          <h1 class="fw-bold mb-0">Admin Dashboard</h1>
          <p class="lead text-muted">Manage services and appointments for Glamour Salon</p>
        </div>
        <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
          <div class="d-inline-block position-relative">
            <h5 class="mb-0 d-flex align-items-center">
              <i class="fas fa-user-shield me-2"></i>
              <?php echo htmlspecialchars($_SESSION['username']); ?>
            </h5>
          </div>
        </div>
      </div>
    </div>
  </header>

  <div class="container py-5">
    <!-- Dashboard Stats -->
    <div class="row mb-5">
      <div class="col-md-3">
        <div class="dashboard-card card p-4 text-center">
          <div><i class="fas fa-calendar-check stat-icon"></i></div>
          <div class="stat-number"><?php echo $stats['total_appointments']; ?></div>
          <div class="text-muted">Total Appointments</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="dashboard-card card p-4 text-center">
          <div><i class="fas fa-calendar-day stat-icon"></i></div>
          <div class="stat-number"><?php echo $stats['today_appointments']; ?></div>
          <div class="text-muted">Today's Appointments</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="dashboard-card card p-4 text-center">
          <div><i class="fas fa-calendar-week stat-icon"></i></div>
          <div class="stat-number"><?php echo $stats['upcoming_appointments']; ?></div>
          <div class="text-muted">Next 7 Days</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="dashboard-card card p-4 text-center">
          <div><i class="fas fa-coins stat-icon"></i></div>
          <div class="stat-number">SAR <?php echo number_format($stats['total_revenue'], 2); ?></div>
          <div class="text-muted">Total Revenue</div>
        </div>
      </div>
    </div>

    <!-- Tabs for different sections -->
    <ul class="nav nav-pills mb-4" id="adminTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="appointments-tab" data-bs-toggle="tab" data-bs-target="#appointments" type="button" role="tab" aria-controls="appointments" aria-selected="true">
          <i class="fas fa-calendar-alt me-2"></i>Appointments
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="services-tab" data-bs-toggle="tab" data-bs-target="#services" type="button" role="tab" aria-controls="services" aria-selected="false">
          <i class="fas fa-spa me-2"></i>Services
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="dues-tab" data-bs-toggle="tab" data-bs-target="#dues" type="button" role="tab" aria-controls="dues" aria-selected="false">
          <i class="fas fa-file-invoice-dollar me-2"></i>User Dues
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab" aria-controls="users" aria-selected="false">
          <i class="fas fa-users me-2"></i>Users
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab" aria-controls="payments" aria-selected="false">
          <i class="fas fa-credit-card me-2"></i>Accepted Payments
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="refunds-tab" data-bs-toggle="tab" data-bs-target="#refunds" type="button" role="tab" aria-controls="refunds" aria-selected="false">
          <i class="fas fa-undo me-2"></i>Refund Requests
          <?php
          $pendingRefunds = getPendingRefundRequests();
          if (count($pendingRefunds) > 0):
          ?>
            <span class="badge bg-danger rounded-pill ms-1"><?php echo count($pendingRefunds); ?></span>
          <?php endif; ?>
        </button>
      </li>
    </ul>

    <div class="tab-content" id="adminTabsContent">
      <!-- Appointments Tab -->
      <div class="tab-pane fade show active" id="appointments" role="tabpanel" aria-labelledby="appointments-tab">
        <div class="admin-section">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-clipboard-list me-2"></i>Appointments</h4>
            <div class="btn-group" role="group" aria-label="Appointment filter">
              <input type="radio" class="btn-check" name="appointmentFilter" id="filterAll" autocomplete="off" checked>
              <label class="btn btn-outline-primary" for="filterAll" onclick="filterAppointments('all')">
                <i class="fas fa-list me-1"></i>All
              </label>

              <input type="radio" class="btn-check" name="appointmentFilter" id="filterWeek" autocomplete="off">
              <label class="btn btn-outline-primary" for="filterWeek" onclick="filterAppointments('week')">
                <i class="fas fa-calendar-week me-1"></i>This Week
              </label>

              <input type="radio" class="btn-check" name="appointmentFilter" id="filterToday" autocomplete="off">
              <label class="btn btn-outline-primary" for="filterToday" onclick="filterAppointments('today')">
                <i class="fas fa-calendar-day me-1"></i>Today
              </label>
            </div>
          </div>
          <?php echo $appointmentMessage; ?>

          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Client</th>
                  <th>Service</th>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Price</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($appointments) > 0): ?>
                  <?php foreach ($appointments as $appointment): ?>
                    <tr class="appointment-row" data-date="<?php echo $appointment['appointment_date']; ?>">
                      <td>#<?php echo $appointment['id']; ?></td>
                      <td>
                        <div><?php echo htmlspecialchars($appointment['username']); ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars($appointment['email']); ?></small>
                      </td>
                      <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                      <td><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></td>
                      <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                      <td>SAR <?php echo number_format($appointment['price'], 2); ?></td>
                      <td>
                        <?php
                        $statusClass = '';
                        $statusBg = '';
                        switch ($appointment['status']) {
                          case 'confirmed':
                            $statusClass = 'bg-success-subtle text-success-emphasis';
                            break;
                          case 'cancelled':
                            $statusClass = 'bg-danger-subtle text-danger-emphasis';
                            break;
                          case 'completed':
                            $statusClass = 'bg-primary-subtle text-primary-emphasis';
                            break;
                        }
                        ?>
                        <span class="status-badge <?php echo $statusClass; ?>">
                          <?php echo ucfirst($appointment['status']); ?>
                        </span>
                      </td>
                      <td>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#rescheduleModal<?php echo $appointment['id']; ?>">
                          <i class="fas fa-edit me-1"></i> Edit
                        </button>
                      </td>
                    </tr>

                    <!-- Reschedule Modal for each appointment -->
                    <div class="modal fade" id="rescheduleModal<?php echo $appointment['id']; ?>" tabindex="-1" aria-labelledby="rescheduleModalLabel<?php echo $appointment['id']; ?>" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="rescheduleModalLabel<?php echo $appointment['id']; ?>">
                              Edit Appointment #<?php echo $appointment['id']; ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <form method="POST">
                            <div class="modal-body">
                              <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                              <div class="mb-3">
                                <label class="form-label">Client: <?php echo htmlspecialchars($appointment['username']); ?></label>
                              </div>
                              <div class="mb-3">
                                <label class="form-label">Service: <?php echo htmlspecialchars($appointment['service_name']); ?></label>
                              </div>
                              <div class="mb-3">
                                <label for="new_date<?php echo $appointment['id']; ?>" class="form-label">Date:</label>
                                <input type="date" class="form-control" id="new_date<?php echo $appointment['id']; ?>" name="new_date" value="<?php echo $appointment['appointment_date']; ?>" required>
                              </div>
                              <div class="mb-3">
                                <label for="new_time<?php echo $appointment['id']; ?>" class="form-label">Time:</label>
                                <input type="time" class="form-control" id="new_time<?php echo $appointment['id']; ?>" name="new_time" value="<?php echo $appointment['appointment_time']; ?>" required>
                              </div>
                              <div class="mb-3">
                                <label for="status<?php echo $appointment['id']; ?>" class="form-label">Status:</label>
                                <select class="form-select" id="status<?php echo $appointment['id']; ?>" name="status" required>
                                  <option value="confirmed" <?php echo ($appointment['status'] == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                  <option value="cancelled" <?php echo ($appointment['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                  <option value="completed" <?php echo ($appointment['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                </select>
                              </div>
                              <div class="mb-3">
                                <label class="form-label">Notes:</label>
                                <p class="form-text"><?php echo htmlspecialchars($appointment['notes'] ?? 'No notes provided'); ?></p>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" class="btn btn-admin-custom" name="reschedule_appointment">Save Changes</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8" class="text-center py-4">No appointments found</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Services Tab -->
      <div class="tab-pane fade" id="services" role="tabpanel" aria-labelledby="services-tab">
        <div class="row">
          <!-- Existing Services -->
          <div class="col-lg-8">
            <div class="admin-section">
              <h4><i class="fas fa-list-alt me-2"></i>Existing Services</h4>
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Service Name</th>
                      <th>Description</th>
                      <th>Price (SAR)</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($services) > 0): ?>
                      <?php foreach ($services as $service): ?>
                        <tr>
                          <td><?php echo $service['id']; ?></td>
                          <td><?php echo htmlspecialchars($service['name']); ?></td>
                          <td><?php echo htmlspecialchars($service['description']); ?></td>
                          <td>SAR <?php echo number_format($service['price'], 2); ?></td>
                          <td>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editServiceModal<?php echo $service['id']; ?>">
                              <i class="fas fa-edit me-1"></i> Edit
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteServiceModal<?php echo $service['id']; ?>">
                              <i class="fas fa-trash me-1"></i> Delete
                            </button>
                          </td>
                        </tr>

                        <!-- Edit Service Modal -->
                        <div class="modal fade" id="editServiceModal<?php echo $service['id']; ?>" tabindex="-1" aria-labelledby="editServiceModalLabel<?php echo $service['id']; ?>" aria-hidden="true">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="editServiceModalLabel<?php echo $service['id']; ?>">
                                  Edit Service
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                              </div>
                              <form method="POST">
                                <div class="modal-body">
                                  <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                  <div class="mb-3">
                                    <label for="edit_service_name<?php echo $service['id']; ?>" class="form-label">Service Name:</label>
                                    <input type="text" class="form-control" id="edit_service_name<?php echo $service['id']; ?>" name="service_name" value="<?php echo htmlspecialchars($service['name']); ?>" required>
                                  </div>
                                  <div class="mb-3">
                                    <label for="edit_service_description<?php echo $service['id']; ?>" class="form-label">Description:</label>
                                    <textarea class="form-control" id="edit_service_description<?php echo $service['id']; ?>" name="service_description" rows="4" required><?php echo htmlspecialchars($service['description']); ?></textarea>
                                  </div>
                                  <div class="mb-3">
                                    <label for="edit_service_price<?php echo $service['id']; ?>" class="form-label">Price (SAR):</label>
                                    <input type="number" class="form-control" id="edit_service_price<?php echo $service['id']; ?>" name="service_price" step="0.01" min="0" value="<?php echo $service['price']; ?>" required>
                                  </div>
                                </div>
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                  <button type="submit" class="btn btn-admin-custom" name="edit_service">Save Changes</button>
                                </div>
                              </form>
                            </div>
                          </div>
                        </div>

                        <!-- Delete Service Modal -->
                        <div class="modal fade" id="deleteServiceModal<?php echo $service['id']; ?>" tabindex="-1" aria-labelledby="deleteServiceModalLabel<?php echo $service['id']; ?>" aria-hidden="true">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="deleteServiceModalLabel<?php echo $service['id']; ?>">
                                  Delete Service
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                              </div>
                              <form method="POST">
                                <div class="modal-body">
                                  <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                  <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Warning:</strong> This action cannot be undone!
                                  </div>
                                  <p>Are you sure you want to delete the service <strong><?php echo htmlspecialchars($service['name']); ?></strong>?</p>
                                  <p class="text-muted">Price: SAR <?php echo number_format($service['price'], 2); ?></p>
                                </div>
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                  <button type="submit" class="btn btn-danger" name="delete_service">Delete Service</button>
                                </div>
                              </form>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="4" class="text-center py-4">No services found</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Add New Service -->
          <div class="col-lg-4">
            <div class="admin-section">
              <h4><i class="fas fa-plus-circle me-2"></i>Add New Service</h4>
              <?php echo $serviceMessage; ?>
              <form method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                  <label for="service_name" class="form-label">Service Name:</label>
                  <input type="text" class="form-control" id="service_name" name="service_name" required>
                  <div class="invalid-feedback">Please enter a service name.</div>
                </div>
                <div class="mb-3">
                  <label for="service_description" class="form-label">Description:</label>
                  <textarea class="form-control" id="service_description" name="service_description" rows="4" required></textarea>
                  <div class="invalid-feedback">Please enter a description.</div>
                </div>
                <div class="mb-3">
                  <label for="service_price" class="form-label">Price (SAR):</label>
                  <input type="number" class="form-control" id="service_price" name="service_price" step="0.01" min="0" required>
                  <div class="invalid-feedback">Please enter a valid price.</div>
                </div>
                <button type="submit" class="btn btn-admin-custom w-100" name="add_service">Add Service</button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- User Dues Tab -->
      <div class="tab-pane fade" id="dues" role="tabpanel" aria-labelledby="dues-tab">
        <div class="admin-section">
          <h4><i class="fas fa-user-clock me-2"></i>Users with Outstanding Dues</h4>
          <?php echo $duesMessage; ?>
          
          <?php if (count($usersWithDues) > 0): ?>
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>User ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Outstanding Dues</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($usersWithDues as $duser): ?>
                    <tr>
                      <td>#<?php echo $duser['id']; ?></td>
                      <td><?php echo htmlspecialchars($duser['username']); ?></td>
                      <td><?php echo htmlspecialchars($duser['email']); ?></td>
                      <td><strong class="text-warning">SAR <?php echo number_format($duser['dues'], 2); ?></strong></td>
                      <td>
                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#settleDuesModal<?php echo $duser['id']; ?>">
                          <i class="fas fa-money-bill-wave me-1"></i> Settle in Cash
                        </button>
                      </td>
                    </tr>

                    <!-- Settle Dues Modal -->
                    <div class="modal fade" id="settleDuesModal<?php echo $duser['id']; ?>" tabindex="-1" aria-labelledby="settleDuesModalLabel<?php echo $duser['id']; ?>" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="settleDuesModalLabel<?php echo $duser['id']; ?>">
                              Settle Dues in Cash
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <form method="POST">
                            <div class="modal-body">
                              <input type="hidden" name="user_id" value="<?php echo $duser['id']; ?>">
                              <input type="hidden" name="dues_amount" value="<?php echo $duser['dues']; ?>">
                              
                              <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                This will mark the full dues amount as paid in cash and clear the user's balance.
                              </div>
                              
                              <p><strong>User:</strong> <?php echo htmlspecialchars($duser['username']); ?></p>
                              <p><strong>Email:</strong> <?php echo htmlspecialchars($duser['email']); ?></p>
                              <p><strong>Outstanding Amount:</strong> <span class="text-warning"><strong>SAR <?php echo number_format($duser['dues'], 2); ?></strong></span></p>
                              
                              <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Confirm:</strong> User has paid the above amount in cash?
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" name="settle_dues" class="btn btn-success">
                                <i class="fas fa-check me-1"></i> Confirm Cash Payment
                              </button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="text-center py-5">
              <i class="fas fa-check-circle display-1 text-success mb-3"></i>
              <h5>No Outstanding Dues</h5>
              <p class="text-muted">All users have cleared their dues.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <!-- End of Dues Tab -->

      <!-- Users Tab -->
      <div class="tab-pane fade" id="users" role="tabpanel" aria-labelledby="users-tab">
        <div class="admin-section">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-users me-2"></i>User Management</h4>
          </div>
          <?php echo $userMessage; ?>

          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Username</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Dues</th>
                  <th>Created</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $allUsers = getAllUsers();
                foreach ($allUsers as $user):
                ?>
                  <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                      <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                        <?php echo ucfirst($user['role']); ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($user['dues'] > 0): ?>
                        <span class="badge bg-warning">SAR <?php echo number_format($user['dues'], 2); ?></span>
                      <?php else: ?>
                        <span class="badge bg-success">SAR 0.00</span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    <td>
                      <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['id']; ?>">
                        <i class="fas fa-edit"></i> Edit
                      </button>
                      <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal<?php echo $user['id']; ?>">
                          <i class="fas fa-trash"></i> Delete
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>

                  <!-- Edit User Modal -->
                  <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="editUserModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog">
                      <div class="modal-content">
                        <form method="POST">
                          <div class="modal-header">
                            <h5 class="modal-title" id="editUserModalLabel<?php echo $user['id']; ?>">Edit User: <?php echo htmlspecialchars($user['username']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <div class="mb-3">
                              <label class="form-label">Username</label>
                              <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            </div>
                            <div class="mb-3">
                              <label class="form-label">Email</label>
                              <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            </div>
                            <div class="mb-3">
                              <label for="role<?php echo $user['id']; ?>" class="form-label">Role</label>
                              <select class="form-select" id="role<?php echo $user['id']; ?>" name="role" required>
                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                              </select>
                            </div>
                            <div class="mb-3">
                              <label class="form-label">Current Dues</label>
                              <input type="text" class="form-control" value="SAR <?php echo number_format($user['dues'], 2); ?>" disabled>
                              <?php if ($user['dues'] > 0): ?>
                                <small class="text-muted">Use the User Dues tab to settle dues</small>
                              <?php endif; ?>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_user_role" class="btn btn-primary">Save Changes</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                  <!-- Delete User Modal -->
                  <?php if ($user['id'] != $_SESSION['user_id']): ?>
                    <div class="modal fade" id="deleteUserModal<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="deleteUserModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form method="POST">
                            <div class="modal-header">
                              <h5 class="modal-title" id="deleteUserModalLabel<?php echo $user['id']; ?>">Delete User</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                              <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                              <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Warning:</strong> This action cannot be undone!
                              </div>
                              <p>Are you sure you want to delete user <strong><?php echo htmlspecialchars($user['username']); ?></strong>?</p>
                              <p class="text-muted">This will permanently delete:</p>
                              <ul class="text-muted">
                                <li>User account and profile</li>
                                <li>All appointments</li>
                                <li>Payment history</li>
                              </ul>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" name="delete_user" class="btn btn-danger">Delete User</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  <?php endif; ?>

                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <!-- End of Users Tab -->

      <!-- Accepted Payments Tab -->
      <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab">
        <div class="admin-section">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-credit-card me-2"></i>Accepted Payment Methods</h4>
          </div>
          <?php echo $paymentMethodMessage; ?>
          
          <p class="text-muted mb-4">Enable or disable payment methods that customers can use when booking services.</p>

          <div class="row">
            <?php
            $paymentMethods = getAllPaymentMethods();
            foreach ($paymentMethods as $method):
            ?>
              <div class="col-md-6 mb-4">
                <div class="card h-100">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                      <div class="flex-grow-1">
                        <h5 class="card-title mb-2">
                          <i class="fas fa-<?php 
                            echo $method['method_name'] === 'card' ? 'credit-card' : 
                                 ($method['method_name'] === 'applepay' ? 'apple-alt' : 'wallet'); 
                          ?> me-2"></i>
                          <?php echo htmlspecialchars($method['display_name']); ?>
                        </h5>
                        <p class="card-text text-muted"><?php echo htmlspecialchars($method['description']); ?></p>
                      </div>
                      <div class="form-check form-switch ms-3">
                        <form method="POST" class="d-inline">
                          <input type="hidden" name="method_id" value="<?php echo $method['id']; ?>">
                          <input type="hidden" name="is_enabled" value="<?php echo $method['is_enabled'] ? 0 : 1; ?>">
                          <input 
                            class="form-check-input" 
                            type="checkbox" 
                            role="switch" 
                            id="switch<?php echo $method['id']; ?>" 
                            <?php echo $method['is_enabled'] ? 'checked' : ''; ?>
                            onchange="this.form.submit()"
                          >
                          <input type="hidden" name="toggle_payment_method" value="1">
                        </form>
                      </div>
                    </div>
                    <div class="mt-3">
                      <span class="badge bg-<?php echo $method['is_enabled'] ? 'success' : 'secondary'; ?>">
                        <?php echo $method['is_enabled'] ? 'Active' : 'Disabled'; ?>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if (count($paymentMethods) == 0): ?>
            <div class="text-center py-5">
              <i class="fas fa-credit-card display-1 text-muted mb-3"></i>
              <h5>No Payment Methods Found</h5>
              <p class="text-muted">Payment methods will appear here once configured.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <!-- End of Accepted Payments Tab -->

      <!-- Refund Requests Tab -->
      <div class="tab-pane fade" id="refunds" role="tabpanel" aria-labelledby="refunds-tab">
        <div class="admin-section">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-undo me-2"></i>Refund Requests</h4>
          </div>
          <?php echo $refundRequestMessage; ?>

          <?php if (count($pendingRefunds) > 0): ?>
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Appointment Date</th>
                    <th>Amount</th>
                    <th>Reason</th>
                    <th>Requested</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pendingRefunds as $refund): ?>
                    <tr>
                      <td><?php echo $refund['id']; ?></td>
                      <td>
                        <?php echo htmlspecialchars($refund['username']); ?><br>
                        <small class="text-muted"><?php echo htmlspecialchars($refund['email']); ?></small>
                      </td>
                      <td><?php echo htmlspecialchars($refund['service_name']); ?></td>
                      <td><?php echo date('M d, Y', strtotime($refund['appointment_date'])); ?><br>
                        <small class="text-muted"><?php echo date('h:i A', strtotime($refund['appointment_time'])); ?></small>
                      </td>
                      <td><strong>SAR <?php echo number_format($refund['amount'], 2); ?></strong></td>
                      <td>
                        <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?php echo htmlspecialchars($refund['reason']); ?>">
                          <?php echo htmlspecialchars($refund['reason']); ?>
                        </span>
                      </td>
                      <td><?php echo date('M d, Y', strtotime($refund['created_at'])); ?></td>
                      <td>
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#processRefundModal<?php echo $refund['id']; ?>">
                          <i class="fas fa-check me-1"></i> Process
                        </button>
                      </td>
                    </tr>

                    <!-- Process Refund Modal -->
                    <div class="modal fade" id="processRefundModal<?php echo $refund['id']; ?>" tabindex="-1" aria-labelledby="processRefundModalLabel<?php echo $refund['id']; ?>" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="processRefundModalLabel<?php echo $refund['id']; ?>">
                              Process Refund Request #<?php echo $refund['id']; ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <form method="POST">
                            <div class="modal-body">
                              <input type="hidden" name="refund_id" value="<?php echo $refund['id']; ?>">
                              
                              <div class="mb-3">
                                <h6>Request Details:</h6>
                                <p><strong>Customer:</strong> <?php echo htmlspecialchars($refund['username']); ?> (<?php echo htmlspecialchars($refund['email']); ?>)</p>
                                <p><strong>Service:</strong> <?php echo htmlspecialchars($refund['service_name']); ?></p>
                                <p><strong>Appointment:</strong> <?php echo date('F j, Y \a\t h:i A', strtotime($refund['appointment_date'] . ' ' . $refund['appointment_time'])); ?></p>
                                <p><strong>Amount:</strong> SAR <?php echo number_format($refund['amount'], 2); ?></p>
                                <p><strong>Customer Reason:</strong> <?php echo htmlspecialchars($refund['reason']); ?></p>
                              </div>

                              <div class="mb-3">
                                <label for="admin_notes<?php echo $refund['id']; ?>" class="form-label">Admin Notes:</label>
                                <textarea class="form-control" id="admin_notes<?php echo $refund['id']; ?>" name="admin_notes" rows="3" required placeholder="Add notes about your decision..."></textarea>
                              </div>

                              <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> If approved, the refund amount (SAR <?php echo number_format($refund['amount'], 2); ?>) will be credited to the user's account by reducing their dues.
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" name="process_refund" value="reject" onclick="this.form.action.value='reject'" class="btn btn-danger">
                                <i class="fas fa-times me-1"></i> Reject
                              </button>
                              <button type="submit" name="process_refund" value="approve" onclick="this.form.action.value='approve'" class="btn btn-success">
                                <i class="fas fa-check me-1"></i> Approve Refund
                              </button>
                              <input type="hidden" name="action" value="approve">
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="text-center py-5">
              <i class="fas fa-check-circle display-1 text-success mb-3"></i>
              <h5>No Pending Refund Requests</h5>
              <p class="text-muted">All refund requests have been processed.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <!-- End of Refund Requests Tab -->

    </div>
  </div>

  <?php include '../reusable/footer.php'; ?>
  <?php include '../reusable/scripts.php'; ?>
  <script src="/assets/js/dashboard.js"></script>

  <script>
    // Filter appointments by date range
    function filterAppointments(filter) {
      const rows = document.querySelectorAll('.appointment-row');
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      
      const todayYear = today.getFullYear();
      const todayMonth = String(today.getMonth() + 1).padStart(2, '0');
      const todayDay = String(today.getDate()).padStart(2, '0');
      const todayStr = `${todayYear}-${todayMonth}-${todayDay}`;
      
      // Calculate start of this week (Sunday)
      const startOfWeek = new Date(today);
      startOfWeek.setDate(today.getDate() - today.getDay());
      
      // Calculate end of this week (Saturday)
      const endOfWeek = new Date(startOfWeek);
      endOfWeek.setDate(startOfWeek.getDate() + 6);
      
      rows.forEach(row => {
        const appointmentDate = row.getAttribute('data-date');
        const apptDate = new Date(appointmentDate + 'T00:00:00');
        
        let show = false;
        
        switch(filter) {
          case 'all':
            show = true;
            break;
          case 'today':
            show = (appointmentDate === todayStr);
            break;
          case 'week':
            show = (apptDate >= startOfWeek && apptDate <= endOfWeek);
            break;
        }
        
        row.style.display = show ? '' : 'none';
      });
    }
  </script>

</body>
</html>