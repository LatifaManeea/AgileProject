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

// Check if there's a pending appointment
if (!isset($_SESSION['pending_appointment'])) {
    header("Location: ../booking/booking.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$pending = $_SESSION['pending_appointment'];

// Validate pending appointment data
if (empty($pending['service_id']) || empty($pending['appointment_date']) || empty($pending['appointment_time'])) {
    unset($_SESSION['pending_appointment']);
    header("Location: ../booking/booking.php");
    exit;
}

// Get service details
$conn = getDbConnection();
$stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
$stmt->bind_param("i", $pending['service_id']);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();
$conn->close();

if (!$service) {
    unset($_SESSION['pending_appointment']);
    header("Location: ../booking/booking.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Glamour Salon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .payment-container {
            max-width: 800px;
            margin: 80px auto 50px;
            padding: 40px;
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .payment-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .payment-icon {
            font-size: 3rem;
            color: #ff6f61;
            margin-bottom: 20px;
        }
        .appointment-summary {
            background: linear-gradient(135deg, #ffe6f2, #f0e6ff);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
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
            box-shadow: 0 5px 15px rgba(255, 111, 97, 0.2);
        }
        .payment-method input[type="radio"] {
            margin-right: 15px;
            width: 20px;
            height: 20px;
        }
        .card-input {
            position: relative;
        }
        .card-input i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        .btn-payment {
            background: linear-gradient(45deg, #ff6f61, #bd4f43);
            border: none;
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(217, 83, 79, 0.4);
            transition: all 0.3s ease;
            width: 100%;
        }
        .btn-payment:hover {
            box-shadow: 0 7px 20px rgba(217, 83, 79, 0.6);
            color: #fff;
            transform: translateY(-2px);
        }
        .price-display {
            font-size: 2rem;
            font-weight: 700;
            color: #ff6f61;
        }
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .detail-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <?php include '../reusable/header.php'; ?>

    <div class="container">
        <div class="payment-container">
            <div class="payment-header">
                <i class="fas fa-credit-card payment-icon"></i>
                <h2>Complete Your Payment</h2>
                <p class="text-muted">Secure payment processing for your appointment</p>
            </div>

            <form method="POST" action="process_payment.php" id="paymentForm">
                <!-- Appointment Summary -->
                <div class="appointment-summary">
                    <h4 class="mb-3"><i class="fas fa-calendar-check me-2"></i>Appointment Details</h4>
                    <div class="detail-item">
                        <span><i class="fas fa-spa me-2"></i><strong>Service:</strong></span>
                        <span><?= htmlspecialchars($service['name']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span><i class="fas fa-calendar me-2"></i><strong>Date:</strong></span>
                        <span><?= !empty($pending['appointment_date']) ? date('l, F j, Y', strtotime($pending['appointment_date'])) : 'Not set' ?></span>
                    </div>
                    <div class="detail-item">
                        <span><i class="fas fa-clock me-2"></i><strong>Time:</strong></span>
                        <span><?= !empty($pending['appointment_time']) ? date('h:i A', strtotime($pending['appointment_time'])) : 'Not set' ?></span>
                    </div>
                    <?php if (!empty($pending['notes'])): ?>
                    <div class="detail-item">
                        <span><i class="fas fa-comment me-2"></i><strong>Notes:</strong></span>
                        <span><?= htmlspecialchars($pending['notes']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item mt-3 pt-3" style="border-top: 2px solid rgba(0,0,0,0.2);">
                        <span class="h5 mb-0"><strong>Total Amount:</strong></span>
                        <span class="price-display">SAR <?= number_format($service['price'], 2) ?></span>
                    </div>
                </div>

                <!-- Payment Amount Selection -->
                <div class="mb-4">
                    <h4 class="mb-3"><i class="fas fa-money-bill-wave me-2"></i>Payment Amount</h4>
                    
                    <div class="payment-method" onclick="selectPaymentType('full')">
                        <label class="d-flex align-items-center mb-0">
                            <input type="radio" name="payment_type" value="full" id="fullPayment" checked required>
                            <div class="flex-grow-1">
                                <strong>Pay in Full</strong>
                                <div class="text-muted small">Pay the complete amount now</div>
                            </div>
                            <strong class="text-success">SAR <?= number_format($service['price'], 2) ?></strong>
                        </label>
                    </div>

                    <div class="payment-method" onclick="selectPaymentType('partial')">
                        <label class="d-flex align-items-center mb-0">
                            <input type="radio" name="payment_type" value="partial" id="partialPayment" required>
                            <div class="flex-grow-1">
                                <strong>Partial Payment</strong>
                                <div class="text-muted small">Pay a minimum of 50% now, rest later</div>
                            </div>
                            <i class="fas fa-percentage fa-lg text-muted"></i>
                        </label>
                    </div>

                    <!-- Partial Payment Amount Input -->
                    <div id="partialAmountSection" style="display: none;" class="mt-3 p-3 bg-light rounded">
                        <label for="partialAmount" class="form-label"><strong>Enter Payment Amount:</strong></label>
                        <div class="input-group mb-2">
                            <span class="input-group-text">SAR</span>
                            <input type="number" class="form-control" id="partialAmount" name="partial_amount" 
                                   min="<?= number_format($service['price'] * 0.5, 2, '.', '') ?>" 
                                   max="<?= number_format($service['price'], 2, '.', '') ?>" 
                                   step="0.01" placeholder="Enter amount">
                        </div>
                        <small class="text-muted">
                            Minimum: SAR <?= number_format($service['price'] * 0.5, 2) ?> (50%) | 
                            Maximum: SAR <?= number_format($service['price'], 2) ?>
                        </small>
                        <div id="duesDisplay" class="alert alert-info mt-2" style="display: none;">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Remaining dues:</strong> <span id="duesAmount">SAR 0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <h4 class="mb-3"><i class="fas fa-wallet me-2"></i>Payment Method</h4>
                
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
                        <div class="flex-grow-1">
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

                <!-- Card Details (shown only when card is selected) -->
                <div id="cardDetails" style="display: none;" class="mt-4">
                    <h5 class="mb-3">Card Information</h5>
                    <div class="mb-3">
                        <label for="cardNumber" class="form-label">Card Number</label>
                        <div class="card-input">
                            <input type="text" class="form-control" id="cardNumber" name="card_number" 
                                   placeholder="1234 5678 9012 3456" maxlength="19">
                            <i class="fas fa-credit-card"></i>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="expiryDate" class="form-label">Expiry Date</label>
                            <input type="text" class="form-control" id="expiryDate" name="expiry_date" 
                                   placeholder="MM/YY" maxlength="5">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="cvv" class="form-label">CVV</label>
                            <input type="text" class="form-control" id="cvv" name="cvv" 
                                   placeholder="123" maxlength="4">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="cardName" class="form-label">Cardholder Name</label>
                        <input type="text" class="form-control" id="cardName" name="card_name" 
                               placeholder="Name on card">
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn-payment" id="paymentBtn">
                        <span id="btnContent">
                            <i class="fas fa-lock me-2"></i>Complete Payment - <span id="btnAmount">SAR <?= number_format($service['price'], 2) ?></span>
                        </span>
                        <span id="btnLoader" style="display: none;">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            Processing Payment...
                        </span>
                    </button>
                </div>

                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Your payment information is secure and encrypted
                    </small>
                </div>
            </form>
        </div>
    </div>

    <?php include '../reusable/footer.php'; ?>

    <script>
        const servicePrice = <?= $service['price'] ?>;
        
        // Handle form submission with loading animation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('paymentBtn');
            const btnContent = document.getElementById('btnContent');
            const btnLoader = document.getElementById('btnLoader');
            
            // Show loading state
            btn.disabled = true;
            btnContent.style.display = 'none';
            btnLoader.style.display = 'inline-block';
        });
        
        // Payment type selection
        function selectPaymentType(type) {
            const partialSection = document.getElementById('partialAmountSection');
            const partialInput = document.getElementById('partialAmount');
            const duesDisplay = document.getElementById('duesDisplay');
            const btnAmount = document.getElementById('btnAmount');
            
            if (type === 'partial') {
                partialSection.style.display = 'block';
                partialInput.required = true;
                // Set default to 50%
                const partialAmount = (servicePrice * 0.5).toFixed(2);
                partialInput.value = partialAmount;
                btnAmount.textContent = 'SAR ' + parseFloat(partialAmount).toFixed(2);
                updateDuesDisplay();
            } else {
                partialSection.style.display = 'none';
                partialInput.required = false;
                partialInput.value = '';
                duesDisplay.style.display = 'none';
                btnAmount.textContent = 'SAR ' + servicePrice.toFixed(2);
            }
        }

        // Update dues display when partial amount changes
        document.getElementById('partialAmount')?.addEventListener('input', updateDuesDisplay);

        function updateDuesDisplay() {
            const partialAmount = parseFloat(document.getElementById('partialAmount').value) || 0;
            const dues = servicePrice - partialAmount;
            const duesDisplay = document.getElementById('duesDisplay');
            const duesAmount = document.getElementById('duesAmount');
            const btnAmount = document.getElementById('btnAmount');
            
            if (partialAmount > 0 && partialAmount < servicePrice) {
                duesDisplay.style.display = 'block';
                duesAmount.textContent = 'SAR ' + dues.toFixed(2);
                btnAmount.textContent = 'SAR ' + partialAmount.toFixed(2);
            } else if (partialAmount >= servicePrice) {
                duesDisplay.style.display = 'none';
                btnAmount.textContent = 'SAR ' + servicePrice.toFixed(2);
            } else {
                duesDisplay.style.display = 'none';
                btnAmount.textContent = 'SAR ' + servicePrice.toFixed(2);
            }
        }

        // Payment method selection
        function selectPayment(method) {
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            
            event.currentTarget.classList.add('selected');
            
            const cardDetails = document.getElementById('cardDetails');
            const cardInputs = cardDetails.querySelectorAll('input');
            
            if (method === 'card') {
                cardDetails.style.display = 'block';
                cardInputs.forEach(input => input.required = true);
            } else {
                cardDetails.style.display = 'none';
                cardInputs.forEach(input => {
                    input.required = false;
                    input.value = '';
                });
            }
        }

        // Card number formatting
        document.getElementById('cardNumber')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });

        // Expiry date formatting
        document.getElementById('expiryDate')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0, 2) + '/' + value.slice(2, 4);
            }
            e.target.value = value;
        });

        // CVV numeric only
        document.getElementById('cvv')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    </script>
</body>
</html>
