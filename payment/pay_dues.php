<?php
require_once '../reusable/db_setup.php';
session_start();

// Get enabled payment methods
function getEnabledPaymentMethods() {
  $conn = getDbConnection();
  $result = $conn->query("SELECT * FROM accepted_payment_methods WHERE is_enabled = 1 ORDER BY method_name");
  $methods = [];
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $methods[] = $row;
    }
  }
  $conn->close();
  return $methods;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$dues_amount = floatval($user['dues'] ?? 0);

// If no dues, redirect to profile
if ($dues_amount <= 0) {
    $_SESSION['profile_message'] = '<div class="alert alert-info">You have no outstanding dues.</div>';
    header("Location: ../login/profile.php");
    exit;
}

// Handle payment submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $payment_method = $_POST['payment_method'];
    $pay_amount = floatval($_POST['pay_amount'] ?? $dues_amount);
    
    // Validate payment amount
    if ($pay_amount <= 0 || $pay_amount > $dues_amount) {
        $error = "Invalid payment amount. Please enter an amount between SAR 0.01 and SAR " . number_format($dues_amount, 2);
    } else {
        // Simulate payment processing
        sleep(2);
        
        // Generate transaction ID
        $transaction_id = 'DUE' . time() . rand(1000, 9999);
        
        $conn = getDbConnection();
        
        // Record payment
        $stmt = $conn->prepare("INSERT INTO payments (user_id, transaction_id, amount, payment_method, payment_type, notes, created_at) VALUES (?, ?, ?, ?, 'dues', 'Dues payment', NOW())");
        $stmt->bind_param("isds", $user_id, $transaction_id, $pay_amount, $payment_method);
        $stmt->execute();
        $stmt->close();
        
        // Update user dues
        $stmt = $conn->prepare("UPDATE users SET dues = dues - ? WHERE id = ?");
        $stmt->bind_param("di", $pay_amount, $user_id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        
        $_SESSION['dues_payment_success'] = [
            'transaction_id' => $transaction_id,
            'amount' => $pay_amount,
            'remaining_dues' => max(0, $dues_amount - $pay_amount),
            'payment_method' => $payment_method
        ];
        
        header("Location: dues_payment_success.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Dues - Glamour Salon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .payment-container {
            max-width: 700px;
            margin: 80px auto 50px;
            padding: 40px;
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .dues-display {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 30px;
        }
        .dues-amount {
            font-size: 3rem;
            font-weight: 700;
            color: #dc3545;
        }
        .payment-method {
            border: 2px solid #e0e0e0;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .payment-method:hover {
            border-color: #ff6f61;
            background-color: #fff5f5;
        }
        .payment-method.selected {
            border-color: #ff6f61;
            background-color: #fff5f5;
        }
        .btn-payment {
            background: linear-gradient(45deg, #ff6f61, #bd4f43);
            border: none;
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
        }
    </style>
</head>
<body>
    <?php include '../reusable/header.php'; ?>

    <div class="container">
        <div class="payment-container">
            <h2 class="text-center mb-4"><i class="fas fa-file-invoice-dollar me-2"></i>Pay Your Dues</h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="dues-display">
                <h5 class="mb-3">Outstanding Balance</h5>
                <div class="dues-amount">SAR <?php echo number_format($dues_amount, 2); ?></div>
                <p class="mt-3 mb-0 text-muted">Clear your dues to book new appointments</p>
            </div>

            <form method="POST" id="duesPaymentForm">
                <!-- Payment Amount -->
                <div class="mb-4">
                    <label class="form-label"><strong>Payment Amount</strong></label>
                    <div class="input-group">
                        <span class="input-group-text">SAR</span>
                        <input type="number" class="form-control" name="pay_amount" id="payAmount" 
                               value="<?php echo number_format($dues_amount, 2, '.', ''); ?>" 
                               min="0.01" max="<?php echo number_format($dues_amount, 2, '.', ''); ?>" 
                               step="0.01" required>
                    </div>
                    <small class="text-muted">You can pay the full amount or a partial amount</small>
                </div>

                <!-- Payment Method -->
                <h5 class="mb-3">Payment Method</h5>
                
                <?php
                $enabledMethods = getEnabledPaymentMethods();
                if (count($enabledMethods) > 0):
                  foreach ($enabledMethods as $method):
                    $icon = 'credit-card';
                    $iconClass = 'fas';
                    if ($method['method_name'] === 'applepay') {
                      $icon = 'apple-pay';
                      $iconClass = 'fab';
                    } elseif ($method['method_name'] === 'stcpay') {
                      $icon = 'mobile-alt';
                      $iconClass = 'fas';
                    }
                ?>
                  <div class="payment-method" onclick="selectPayment('<?php echo $method['method_name']; ?>')">
                    <label class="d-flex align-items-center mb-0">
                        <input type="radio" name="payment_method" value="<?php echo $method['method_name']; ?>" id="<?php echo $method['method_name']; ?>Payment" required>
                        <div class="flex-grow-1 ms-3">
                            <strong><?php echo htmlspecialchars($method['display_name']); ?></strong>
                            <div class="text-muted small"><?php echo htmlspecialchars($method['description']); ?></div>
                        </div>
                        <i class="<?php echo $iconClass; ?> fa-<?php echo $icon; ?> fa-2x text-muted"></i>
                    </label>
                  </div>
                <?php
                  endforeach;
                else:
                ?>
                  <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    No payment methods are currently available. Please contact support.
                  </div>
                <?php endif; ?>

                <div class="mt-4">
                    <button type="submit" class="btn-payment">
                        <i class="fas fa-check-circle me-2"></i>Pay Now
                    </button>
                </div>

                <div class="text-center mt-3">
                    <a href="../login/profile.php" class="text-muted">
                        <i class="fas fa-arrow-left me-1"></i>Back to Profile
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php include '../reusable/footer.php'; ?>

    <script>
        function selectPayment(method) {
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
        }
    </script>
</body>
</html>
