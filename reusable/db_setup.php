<?php
// Connection settings (host, port, database name, user, password) live in config.php
require_once __DIR__ . '/config.php';

// The database name is used inside an SQL identifier, so only allow safe characters
if (!preg_match('/^[A-Za-z0-9_]+$/', $db_name)) {
    die("Invalid database name in configuration");
}

// Connect to the server first, so the database can be created if it is missing
$conn = new mysqli($db_host, $db_user, $db_pass, '', (int)$db_port);

// Check connection first before any queries
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create the database on first run (no manual setup step needed in a new environment)
$conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4");
if (!$conn->select_db($db_name)) {
    die("Could not use database '$db_name': " . $conn->error);
}

// Track whether the services table existed BEFORE creation to decide on seeding
$services_table_existed = false;
try {
    $check = $conn->query("SHOW TABLES LIKE 'services'");
    if ($check) { $services_table_existed = ($check->num_rows > 0); }
} catch (Throwable $e) {
    // ignore here; table may not exist yet
}

// SQL to create tables
$tables = [
    "CREATE TABLE IF NOT EXISTS services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL
    )",
    
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        role ENUM('admin', 'user') DEFAULT 'user',
        dues DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        service_id INT,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        status ENUM('confirmed', 'cancelled', 'completed') DEFAULT 'confirmed',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (service_id) REFERENCES services(id)
    )",

    // Notifications for users about appointments and events
    "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(150) NOT NULL,
        message TEXT,
        appointment_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_user_created (user_id, created_at),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (appointment_id) REFERENCES appointments(id)
    )",

    // Payment transactions for tracking all payment activities
    "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        appointment_id INT NULL,
        transaction_id VARCHAR(50) UNIQUE NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        payment_type ENUM('full', 'partial', 'dues', 'cash_settlement') DEFAULT 'full',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (appointment_id) REFERENCES appointments(id)
    )",

    // Accepted payment methods configuration
    "CREATE TABLE IF NOT EXISTS accepted_payment_methods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        method_name VARCHAR(50) UNIQUE NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        description VARCHAR(255),
        is_enabled BOOLEAN DEFAULT true,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // Refund requests for cancelled appointments
    "CREATE TABLE IF NOT EXISTS refund_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        appointment_id INT NOT NULL,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        reason TEXT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_notes TEXT,
        processed_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL,
        FOREIGN KEY (appointment_id) REFERENCES appointments(id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (processed_by) REFERENCES users(id)
    )"
];

// Execute each table creation query
$success = true;
foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
        echo "Error creating table: " . $conn->error . "<br>";
        $success = false;
    }
}

// Seed default payment methods if table is empty
$checkPaymentMethods = $conn->query("SELECT COUNT(*) as count FROM accepted_payment_methods");
if ($checkPaymentMethods && $checkPaymentMethods->fetch_assoc()['count'] == 0) {
    $paymentMethods = [
        ["card", "Credit/Debit Card", "Pay with credit or debit card"],
        ["applepay", "Apple Pay", "Pay with Apple Pay"],
        ["stcpay", "STC Pay", "Pay with STC Pay wallet"]
    ];
    
    $stmt = $conn->prepare("INSERT INTO accepted_payment_methods (method_name, display_name, description, is_enabled) VALUES (?, ?, ?, true)");
    foreach ($paymentMethods as $pm) {
        $stmt->bind_param("sss", $pm[0], $pm[1], $pm[2]);
        $stmt->execute();
    }
    $stmt->close();
}

// Add dues column to users table if it doesn't exist (compatible with all MySQL versions)
$checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'dues'");
if ($checkColumn->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN dues DECIMAL(10,2) DEFAULT 0.00");
}

$checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'");
if ($checkColumn->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

// Add paid_amount column to appointments table if it doesn't exist
$checkColumn = $conn->query("SHOW COLUMNS FROM appointments LIKE 'paid_amount'");
if ($checkColumn->num_rows == 0) {
    $conn->query("ALTER TABLE appointments ADD COLUMN paid_amount DECIMAL(10,2) DEFAULT 0.00");
}

// Insert sample data only if services table did not exist previously
if ($success && !$services_table_existed) {
    // Sample services
    $services = [
        "INSERT INTO services (name, description, price) VALUES 
        ('Haircut & Style', 'Professional haircut and styling by our experts', 55.00)",
        "INSERT INTO services (name, description, price) VALUES 
        ('Hair Coloring', 'Full hair coloring with premium products', 85.00)",
        "INSERT INTO services (name, description, price) VALUES 
        ('Facial Treatment', 'Rejuvenating facial with organic products', 75.00)",
        "INSERT INTO services (name, description, price) VALUES 
        ('Manicure & Pedicure', 'Complete nail care package', 65.00)",
        "INSERT INTO services (name, description, price) VALUES 
        ('Hair Extension', 'Premium quality hair extensions', 150.00)",
    ];
    
    // Sample admin user
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $users = [
        "INSERT INTO users (username, password, email, role) VALUES 
        ('admin', '$admin_password', 'admin@glamoursalon.com', 'admin')",
    ];
    
    // Execute sample data insertion
    foreach ($services as $sql) {
        if (!$conn->query($sql)) {
            echo "Error inserting service data: " . $conn->error . "<br>";
        }
    }
    
    foreach ($users as $sql) {
        if (!$conn->query($sql)) {
            echo "Error inserting user data: " . $conn->error . "<br>";
        }
    }

}

$conn->close();

// Create database connection
function getDbConnection() {
    global $db_host, $db_user, $db_pass, $db_name, $db_port;
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Function to get all services
function getAllServices() {
    $conn = getDbConnection();
    $result = $conn->query("SELECT * FROM services ORDER BY price");
    
    $services = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
    }
    
    $conn->close();
    return $services;
}

// Function to get the enabled payment methods (used by payment.php and pay_dues.php)
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

// Function to get all staff members
function getAllStaff() {
    $conn = getDbConnection();
    $result = $conn->query("SELECT * FROM staff ORDER BY last_name");
    
    $staff = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $staff[] = $row;
        }
    }
    
    $conn->close();
    return $staff;
}

// User authentication functions
function authenticateUser($username, $password) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            
            // Return user details without password
            unset($user['password']);
            $stmt->close();
            $conn->close();
            return $user;
        }
    }
    
    $stmt->close();
    $conn->close();
    return false;
}

function isUserAdmin($userId) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $isAdmin = ($user['role'] === 'admin');
        $stmt->close();
        $conn->close();
        return $isAdmin;
    }
    
    $stmt->close();
    $conn->close();
    return false;
}

function getUserById($userId) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT id, username, email, role, dues, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $user;
    }
    
    $stmt->close();
    $conn->close();
    return false;
}
