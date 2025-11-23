<?php
session_start();

// Check if payment was successful
if (!isset($_SESSION['payment_success'])) {
    header("Location: ../booking/booking.php");
    exit;
}

$payment_info = $_SESSION['payment_success'];
unset($_SESSION['payment_success']); // Clear the session data
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed - Glamour Salon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .success-container {
            max-width: 700px;
            margin: 100px auto 50px;
            padding: 50px;
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(45deg, #20c997, #17a2b8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease;
        }
        .success-icon i {
            font-size: 50px;
            color: white;
        }
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        .payment-details {
            background: linear-gradient(135deg, #ffe6f2, #f0e6ff);
            padding: 30px;
            border-radius: 10px;
            margin: 30px 0;
            text-align: left;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #666;
        }
        .detail-value {
            color: #333;
            font-weight: 500;
        }
        .btn-action {
            background: linear-gradient(45deg, #ff6f61, #bd4f43);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
        }
        .btn-action:hover {
            box-shadow: 0 7px 20px rgba(217, 83, 79, 0.6);
            color: #fff;
            transform: translateY(-2px);
        }
        .btn-outline-action {
            border: 2px solid #ff6f61;
            color: #ff6f61;
            background: transparent;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
        }
        .btn-outline-action:hover {
            background: #ff6f61;
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <?php include '../reusable/header.php'; ?>

    <div class="container">
        <div class="success-container">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h2 class="mb-3">Appointment Confirmed!</h2>
            <p class="text-muted mb-4">Your appointment has been successfully booked and payment completed.</p>

            <div class="payment-details">
                <h5 class="mb-3 text-center"><i class="fas fa-calendar-check me-2"></i>Appointment Details</h5>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-spa me-2"></i>Service:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($payment_info['service_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-calendar me-2"></i>Date:</span>
                    <span class="detail-value"><?php echo date('l, F j, Y', strtotime($payment_info['appointment_date'])); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-clock me-2"></i>Time:</span>
                    <span class="detail-value"><?php echo date('h:i A', strtotime($payment_info['appointment_time'])); ?></span>
                </div>
                <div class="detail-row" style="border-top: 2px solid rgba(0,0,0,0.2); margin-top: 15px; padding-top: 20px;">
                    <span class="detail-label"><i class="fas fa-hashtag me-2"></i>Transaction ID:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($payment_info['transaction_id']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-money-bill-wave me-2"></i>Amount Paid:</span>
                    <span class="detail-value">SAR <?php echo number_format($payment_info['payment_amount'] ?? $payment_info['service_price'], 2); ?></span>
                </div>
                <?php if (isset($payment_info['dues_amount']) && $payment_info['dues_amount'] > 0): ?>
                <div class="detail-row" style="background: #fff3cd; margin: 10px -30px; padding: 15px 30px;">
                    <span class="detail-label"><i class="fas fa-exclamation-triangle me-2"></i>Remaining Dues:</span>
                    <span class="detail-value text-warning"><strong>SAR <?php echo number_format($payment_info['dues_amount'], 2); ?></strong></span>
                </div>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Payment Note:</strong> You have outstanding dues of SAR <?php echo number_format($payment_info['dues_amount'], 2); ?>. 
                    You can pay this amount from your profile or settle it in cash at the salon.
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-credit-card me-2"></i>Payment Method:</span>
                    <span class="detail-value"><?php echo ucfirst(htmlspecialchars($payment_info['payment_method'])); ?></span>
                </div>
            </div>

            <div class="mt-4">
                <p class="mb-3"><i class="fas fa-info-circle me-2"></i>A confirmation has been sent to your registered email address.</p>
            </div>

            <div class="mt-4">
                <a href="../login/profile.php" class="btn-action">
                    <i class="fas fa-user me-2"></i>View My Appointments
                </a>
                <a href="../index.php" class="btn-outline-action">
                    <i class="fas fa-home me-2"></i>Back to Home
                </a>
            </div>
        </div>
    </div>

    <?php include '../reusable/footer.php'; ?>
</body>
</html>
