<?php
require_once '../reusable/db_setup.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ./login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$message = '';
$passwordMessage = '';
$appointmentMessage = '';

// Display booking error if user was redirected from booking page
if (isset($_SESSION['booking_error'])) {
    $appointmentMessage = '<div class="alert alert-danger">' . $_SESSION['booking_error'] . '</div>';
    unset($_SESSION['booking_error']);
}

// Display profile message if set
if (isset($_SESSION['profile_message'])) {
    $message = $_SESSION['profile_message'];
    unset($_SESSION['profile_message']);
}

// Handle profile update
if (isset($_POST['update_profile'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];

    $conn = getDbConnection();

    // Check if username is already taken by another user
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $username, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $message = '<div class="alert alert-danger">Username already exists</div>';
    } else {
        // Check if email is already taken by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $message = '<div class="alert alert-danger">Email already exists</div>';
        } else {
            // Update user information
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $username, $email, $user_id);

            if ($stmt->execute()) {
                $_SESSION['username'] = $username;
                $message = '<div class="alert alert-success">Profile updated successfully</div>';
                $user['username'] = $username;
                $user['email'] = $email;
            } else {
                $message = '<div class="alert alert-danger">Error updating profile: ' . $conn->error . '</div>';
            }
        }
    }

    $stmt->close();
    $conn->close();
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $passwordMessage = '<div class="alert alert-danger">New passwords do not match</div>';
    } else {
        $conn = getDbConnection();

        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();

        if (password_verify($current_password, $user_data['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);

            if ($stmt->execute()) {
                $passwordMessage = '<div class="alert alert-success">Password changed successfully</div>';
            } else {
                $passwordMessage = '<div class="alert alert-danger">Error changing password: ' . $conn->error . '</div>';
            }
        } else {
            $passwordMessage = '<div class="alert alert-danger">Current password is incorrect</div>';
        }

        $stmt->close();
        $conn->close();
    }
}

// Handle appointment status change
if (isset($_POST['update_appointment'])) {
    require_once __DIR__ . '/../reusable/notifications_service.php';
    $appointment_id = (int)$_POST['appointment_id'];
    $status = $_POST['status'] ?? '';

    $conn = getDbConnection();
    // Fetch the appointment (it must belong to this user) to validate the change
    $origStmt = $conn->prepare("SELECT status, appointment_date, appointment_time FROM appointments WHERE id = ? AND user_id = ?");
    $origStmt->bind_param("ii", $appointment_id, $user_id);
    $origStmt->execute();
    $orig = $origStmt->get_result()->fetch_assoc();
    $origStmt->close();

    if (!$orig) {
        $appointmentMessage = '<div class="alert alert-danger">Appointment not found</div>';
    } elseif ($orig['status'] === $status) {
        $appointmentMessage = '<div class="alert alert-info">No change was made to the appointment</div>';
    } elseif ($orig['status'] !== 'confirmed' || $status !== 'cancelled') {
        // Users may only cancel a confirmed appointment; they cannot re-confirm,
        // complete or set any other status (this would also bypass refunds).
        $appointmentMessage = '<div class="alert alert-danger">Only a confirmed appointment can be cancelled. Cancelled or completed appointments cannot be changed</div>';
    } elseif (strtotime($orig['appointment_date'] . ' ' . $orig['appointment_time']) <= time()) {
        $appointmentMessage = '<div class="alert alert-danger">Past appointments cannot be changed</div>';
    } else {
        $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'confirmed'");
        $stmt->bind_param("ii", $appointment_id, $user_id);

        if ($stmt->execute()) {
            $appointmentMessage = '<div class="alert alert-success">Appointment cancelled successfully</div>';
            notifyAppointmentCancelled($appointment_id);
        } else {
            $appointmentMessage = '<div class="alert alert-danger">Error updating appointment: ' . $conn->error . '</div>';
        }
        $stmt->close();
    }

    $conn->close();
}

// Handle refund request submission
$refundMessage = '';
if (isset($_POST['request_refund'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $reason = trim($_POST['refund_reason']);
    
    $conn = getDbConnection();
    
    // Check if appointment is cancelled and belongs to user
    $checkStmt = $conn->prepare("SELECT a.*, s.price FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.id = ? AND a.user_id = ? AND a.status = 'cancelled'");
    $checkStmt->bind_param("ii", $appointment_id, $user_id);
    $checkStmt->execute();
    $appt = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    
    if ($appt) {
        // Check if refund request already exists
        $existsStmt = $conn->prepare("SELECT id FROM refund_requests WHERE appointment_id = ?");
        $existsStmt->bind_param("i", $appointment_id);
        $existsStmt->execute();
        $exists = $existsStmt->get_result()->num_rows > 0;
        $existsStmt->close();
        
        if (!$exists) {
            $refund_amount = $appt['paid_amount'] > 0 ? $appt['paid_amount'] : $appt['price'];
            $stmt = $conn->prepare("INSERT INTO refund_requests (appointment_id, user_id, amount, reason, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
            $stmt->bind_param("iids", $appointment_id, $user_id, $refund_amount, $reason);
            
            if ($stmt->execute()) {
                $refundMessage = '<div class="alert alert-success">Refund request submitted successfully. We will review it shortly.</div>';
            } else {
                $refundMessage = '<div class="alert alert-danger">Error submitting refund request: ' . $conn->error . '</div>';
            }
            $stmt->close();
        } else {
            $refundMessage = '<div class="alert alert-info">A refund request for this appointment already exists.</div>';
        }
    } else {
        $refundMessage = '<div class="alert alert-danger">Invalid appointment or appointment is not cancelled.</div>';
    }
    
    $conn->close();
}

// Get user's appointments
function getUserAppointments($user_id)
{
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT a.*, s.name as service_name, s.price, r.id as refund_id, r.status as refund_status, r.amount as refund_amount
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        LEFT JOIN refund_requests r ON a.id = r.appointment_id
        WHERE a.user_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $appointments = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $appointments[] = $row;
        }
    }

    $stmt->close();
    $conn->close();
    return $appointments;
}

$appointments = getUserAppointments($user_id);

// Get payment history
function getUserPayments($user_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT p.*, s.name as service_name
        FROM payments p
        LEFT JOIN appointments a ON p.appointment_id = a.id
        LEFT JOIN services s ON a.service_id = s.id
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT 20
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
    }
    $stmt->close();
    $conn->close();
    return $payments;
}

$payments = getUserPayments($user_id);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../reusable/head.php'; ?>
    <title>My Profile - Glamour Salon</title>
    <link rel="stylesheet" href="/assets/css/profile.css">
</head>

<body>
    <?php include '../reusable/header.php'; ?>

    <div class="container py-5">
        <div class="profile-header text-center">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h2><?php echo htmlspecialchars($user['username']); ?></h2>
            <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
            <p>
                <span class="badge bg-info rounded-pill">
                    <i class="fas fa-user me-1"></i>
                    <?php echo ucfirst($user['role']); ?>
                </span>
                <span class="badge bg-secondary rounded-pill ms-2">
                    <i class="fas fa-clock me-1"></i>
                    Member since <?php echo date('M Y', strtotime($user['created_at'] ?? 'now')); ?>
                </span>
                <?php if (isset($user['dues']) && floatval($user['dues']) > 0): ?>
                <span class="badge bg-warning text-dark rounded-pill ms-2">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Dues: SAR <?php echo number_format($user['dues'], 2); ?>
                </span>
                <?php endif; ?>
            </p>
            <?php if (isset($user['dues']) && floatval($user['dues']) > 0): ?>
            <div class="alert alert-warning mt-3">
                <i class="fas fa-info-circle me-2"></i>
                You have outstanding dues of <strong>SAR <?php echo number_format($user['dues'], 2); ?></strong>. 
                <a href="../payment/pay_dues.php" class="alert-link">Pay now</a> to book new appointments.
            </div>
            <?php endif; ?>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-pills mb-4 justify-content-center" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="true">
                    <i class="fas fa-user-edit me-2"></i>Profile Info
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="appointments-tab" data-bs-toggle="tab" data-bs-target="#appointments" type="button" role="tab" aria-controls="appointments" aria-selected="false">
                    <i class="fas fa-calendar-check me-2"></i>My Appointments
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab" aria-controls="password" aria-selected="false">
                    <i class="fas fa-key me-2"></i>Change Password
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab" aria-controls="payments" aria-selected="false">
                    <i class="fas fa-receipt me-2"></i>Payment History
                </button>
            </li>
        </ul>

        <div class="tab-content" id="profileTabsContent">
            <!-- Profile Info Tab -->
            <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                <div class="profile-section">
                    <h4><i class="fas fa-user-circle"></i> Profile Information</h4>
                    <?php echo $message; ?>
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username:</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            <div class="invalid-feedback">
                                Please enter a username.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email:</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            <div class="invalid-feedback">
                                Please enter a valid email.
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" name="update_profile" class="btn btn-profile">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Appointments Tab -->
            <div class="tab-pane fade" id="appointments" role="tabpanel" aria-labelledby="appointments-tab">
                <div class="profile-section">
                    <h4><i class="far fa-calendar-alt"></i> My Appointments</h4>
                    <?php echo $appointmentMessage; ?>
                    <?php echo $refundMessage; ?>

                    <?php if (count($appointments) > 0): ?>
                        <!-- Filter bar: handled by assets/js/profile.js -->
                        <div class="row g-2 mb-3" id="appointmentFilterBar">
                            <div class="col-md-4">
                                <label for="statusFilter" class="form-label visually-hidden">Filter by status</label>
                                <select class="form-select" id="statusFilter">
                                    <option value="all">All statuses</option>
                                    <option value="confirmed">Confirmed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label for="serviceSearch" class="form-label visually-hidden">Search by service name</label>
                                <input type="search" class="form-control" id="serviceSearch" placeholder="Search by service name">
                            </div>
                        </div>
                        <div class="row">
                            <?php foreach ($appointments as $appointment): ?>
                                <?php
                                $statusClass = '';
                                switch ($appointment['status']) {
                                    case 'confirmed':
                                        $statusClass = 'confirmed';
                                        $badgeClass = 'bg-success-subtle text-success-emphasis';
                                        break;
                                    case 'cancelled':
                                        $statusClass = 'cancelled';
                                        $badgeClass = 'bg-danger-subtle text-danger-emphasis';
                                        break;
                                    case 'completed':
                                        $statusClass = 'completed';
                                        $badgeClass = 'bg-primary-subtle text-primary-emphasis';
                                        break;
                                }
                                ?>
                                <div class="col-lg-6 mb-3 appointment-item"
                                     data-status="<?php echo htmlspecialchars($appointment['status']); ?>"
                                     data-service="<?php echo htmlspecialchars(strtolower($appointment['service_name'])); ?>">
                                    <div class="card appointment-card <?php echo $statusClass; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <h5 class="card-title"><?php echo htmlspecialchars($appointment['service_name']); ?></h5>
                                                <span class="status-badge <?php echo $badgeClass; ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </div>
                                            <div class="mb-3">
                                                <p class="mb-1">
                                                    <i class="fas fa-calendar me-2 text-muted"></i>
                                                    <?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?>
                                                </p>
                                                <p class="mb-1">
                                                    <i class="fas fa-clock me-2 text-muted"></i>
                                                    <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                                </p>
                                                <p class="mb-1">
                                                    <i class="fas fa-money-bill-wave me-2 text-muted"></i>
                                                    SAR <?php echo number_format($appointment['price'], 2); ?>
                                                </p>
                                                <?php if (!empty($appointment['notes'])): ?>
                                                    <p class="mb-1">
                                                        <i class="fas fa-comment-dots me-2 text-muted"></i>
                                                        <?php echo htmlspecialchars($appointment['notes']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (
                                                $appointment['status'] === 'confirmed' &&
                                                strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']) > time()
                                            ): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#appointmentModal<?php echo $appointment['id']; ?>">
                                                    <i class="fas fa-edit me-1"></i> Update Status
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($appointment['status'] === 'cancelled' && !$appointment['refund_id']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary mt-2"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#refundModal<?php echo $appointment['id']; ?>">
                                                    <i class="fas fa-undo me-1"></i> Request Refund
                                                </button>
                                            <?php elseif ($appointment['refund_id']): ?>
                                                <div class="mt-2">
                                                    <?php
                                                    $refundBadge = '';
                                                    switch($appointment['refund_status']) {
                                                        case 'pending':
                                                            $refundBadge = 'bg-warning';
                                                            break;
                                                        case 'approved':
                                                            $refundBadge = 'bg-success';
                                                            break;
                                                        case 'rejected':
                                                            $refundBadge = 'bg-danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $refundBadge; ?>">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Refund <?php echo ucfirst($appointment['refund_status']); ?>
                                                        <?php if ($appointment['refund_status'] !== 'rejected'): ?>
                                                            - SAR <?php echo number_format($appointment['refund_amount'], 2); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Refund Request Modal -->
                                <div class="modal fade" id="refundModal<?php echo $appointment['id']; ?>" tabindex="-1"
                                    aria-labelledby="refundModalLabel<?php echo $appointment['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="refundModalLabel<?php echo $appointment['id']; ?>">
                                                    Request Refund
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                    <p>Service: <strong><?php echo htmlspecialchars($appointment['service_name']); ?></strong></p>
                                                    <p>Amount Paid: <strong>SAR <?php echo number_format($appointment['paid_amount'] > 0 ? $appointment['paid_amount'] : $appointment['price'], 2); ?></strong></p>

                                                    <div class="mb-3">
                                                        <label for="refund_reason<?php echo $appointment['id']; ?>" class="form-label">Reason for Refund:</label>
                                                        <textarea class="form-control" id="refund_reason<?php echo $appointment['id']; ?>" name="refund_reason" rows="3" required placeholder="Please explain why you're requesting a refund..."></textarea>
                                                    </div>
                                                    
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        Your refund request will be reviewed by our admin team. You will be notified once it's processed.
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="request_refund" class="btn btn-primary">Submit Request</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Appointment Status Modal -->
                                <div class="modal fade" id="appointmentModal<?php echo $appointment['id']; ?>" tabindex="-1"
                                    aria-labelledby="appointmentModalLabel<?php echo $appointment['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="appointmentModalLabel<?php echo $appointment['id']; ?>">
                                                    Update Appointment Status
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                    <p>Service: <strong><?php echo htmlspecialchars($appointment['service_name']); ?></strong></p>
                                                    <p>Date: <strong><?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?></strong></p>
                                                    <p>Time: <strong><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></strong></p>

                                                    <div class="mb-3">
                                                        <label for="status<?php echo $appointment['id']; ?>" class="form-label">Status:</label>
                                                        <select class="form-select" id="status<?php echo $appointment['id']; ?>" name="status">
                                                            <option value="confirmed" <?php echo ($appointment['status'] == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                                            <option value="cancelled" <?php echo ($appointment['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" name="update_appointment" class="btn btn-profile">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-muted text-center d-none" id="noAppointmentMatch">
                            No appointments match your filter.
                        </p>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="far fa-calendar-times display-1 text-muted mb-4"></i>
                            <h5>No appointments found</h5>
                            <p class="text-muted">You haven't booked any appointments yet.</p>
                            <a href="../booking/booking.php" class="btn btn-profile mt-3">
                                <i class="fas fa-calendar-plus me-2"></i>Book Now
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Change Password Tab -->
            <div class="tab-pane fade" id="password" role="tabpanel" aria-labelledby="password-tab">
                <div class="profile-section">
                    <h4><i class="fas fa-lock"></i> Change Password</h4>
                    <?php echo $passwordMessage; ?>
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password:</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <div class="invalid-feedback">
                                Please enter your current password.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password:</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <div class="invalid-feedback">
                                Please enter a new password.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password:</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <div class="invalid-feedback">
                                Please confirm your new password.
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" name="change_password" class="btn btn-profile">
                                <i class="fas fa-key me-2"></i>Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payment History Tab -->
            <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab">
                <div class="profile-section">
                    <h4><i class="fas fa-file-invoice-dollar"></i> Payment History</h4>
                    
                    <?php if (count($payments) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Transaction ID</th>
                                        <th>Type</th>
                                        <th>Service</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($payment['created_at'])); ?></td>
                                            <td><small><?php echo htmlspecialchars($payment['transaction_id']); ?></small></td>
                                            <td>
                                                <?php
                                                $typeClass = '';
                                                $typeLabel = ucfirst($payment['payment_type']);
                                                switch($payment['payment_type']) {
                                                    case 'full':
                                                        $typeClass = 'bg-success';
                                                        break;
                                                    case 'partial':
                                                        $typeClass = 'bg-warning';
                                                        break;
                                                    case 'dues':
                                                        $typeClass = 'bg-info';
                                                        break;
                                                    case 'cash_settlement':
                                                        $typeClass = 'bg-secondary';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $typeClass; ?>"><?php echo $typeLabel; ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($payment['service_name'] ?? 'Dues Payment'); ?></td>
                                            <td><strong>SAR <?php echo number_format($payment['amount'], 2); ?></strong></td>
                                            <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="far fa-credit-card display-1 text-muted mb-4"></i>
                            <h5>No payment history</h5>
                            <p class="text-muted">Your payment transactions will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../reusable/footer.php'; ?>
    <?php include '../reusable/scripts.php'; ?>
    <script src="/assets/js/profile.js"></script>
</body>

</html>