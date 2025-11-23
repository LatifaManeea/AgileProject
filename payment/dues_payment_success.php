<?php
session_start();

if (!isset($_SESSION['dues_payment_success'])) {
    header("Location: ../login/profile.php");
    exit;
}

$payment_info = $_SESSION['dues_payment_success'];
unset($_SESSION['dues_payment_success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Glamour Salon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .success-container {
            max-width: 600px;
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
            from { transform: scale(0); }
            to { transform: scale(1); }
        }
        .payment-summary {
            background: linear-gradient(135deg, #ffe6f2, #f0e6ff);
            padding: 25px;
            border-radius: 10px;
            margin: 25px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .btn-action {
            background: linear-gradient(45deg, #ff6f61, #bd4f43);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
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
            
            <h2 class="mb-3">Payment Successful!</h2>
            <p class="text-muted mb-4">Your dues payment has been processed successfully.</p>

            <div class="payment-summary">
                <div class="detail-row">
                    <span><strong>Transaction ID:</strong></span>
                    <span><?php echo htmlspecialchars($payment_info['transaction_id']); ?></span>
                </div>
                <div class="detail-row">
                    <span><strong>Amount Paid:</strong></span>
                    <span class="text-success"><strong>SAR <?php echo number_format($payment_info['amount'], 2); ?></strong></span>
                </div>
                <div class="detail-row">
                    <span><strong>Payment Method:</strong></span>
                    <span><?php echo ucfirst($payment_info['payment_method']); ?></span>
                </div>
                <?php if ($payment_info['remaining_dues'] > 0): ?>
                <div class="detail-row" style="border-top: 2px solid rgba(0,0,0,0.2); margin-top: 10px; padding-top: 15px;">
                    <span><strong>Remaining Dues:</strong></span>
                    <span class="text-warning"><strong>SAR <?php echo number_format($payment_info['remaining_dues'], 2); ?></strong></span>
                </div>
                <?php else: ?>
                <div class="alert alert-success mt-3">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>All dues cleared!</strong> You can now book new appointments.
                </div>
                <?php endif; ?>
            </div>

            <div class="mt-4">
                <a href="../login/profile.php" class="btn-action">
                    <i class="fas fa-user me-2"></i>Back to Profile
                </a>
                <?php if ($payment_info['remaining_dues'] <= 0): ?>
                <a href="../booking/booking.php" class="btn-action">
                    <i class="fas fa-calendar-plus me-2"></i>Book Appointment
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../reusable/footer.php'; ?>
</body>
</html>
