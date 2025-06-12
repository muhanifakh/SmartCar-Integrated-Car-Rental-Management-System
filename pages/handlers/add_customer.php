<?php
// handlers/add_customer.php
session_start();

// Database connection
$host = '127.0.0.1';
$dbname = 'smart_car';
$username = 'root';
$password = 'Avangarde13';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    header("Location: ../dashboard.php?error=" . urlencode("Database connection failed"));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $license_number = trim($_POST['license_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $id_card = trim($_POST['id_card'] ?? '');

        // Validate required fields
        if (empty($full_name) || empty($phone) || empty($license_number) || empty($address) || empty($id_card)) {
            throw new Exception('All fields are required');
        }

        // Validate ID Card format (16 digits)
        if (!preg_match('/^\d{16}$/', $id_card)) {
            throw new Exception('ID Card must be exactly 16 digits');
        }
        
        // Validate phone number (basic validation)
        if (strlen($phone) < 10) {
            throw new Exception('Phone number must be at least 10 digits');
        }
        
        // Check if phone number already exists
        $stmt_check_phone = $pdo->prepare("SELECT ID_Card FROM Customer WHERE Phone_Number = ?");
        $stmt_check_phone->execute([$phone]);
        if ($stmt_check_phone->fetch()) {
            throw new Exception('Phone number already exists in our system');
        }
        
        // Check if license number already exists
        $stmt_check_license = $pdo->prepare("SELECT ID_Card FROM Customer WHERE Drivers_License = ?");
        $stmt_check_license->execute([$license_number]);
        if ($stmt_check_license->fetch()) {
            throw new Exception('Driver license number already exists in our system');
        }
        
        // Check if ID Card already exists
        $stmt_check_id = $pdo->prepare("SELECT ID_Card FROM Customer WHERE ID_Card = ?");
        $stmt_check_id->execute([$id_card]);
        if ($stmt_check_id->fetch()) {
            throw new Exception('ID Card number already exists in our system');
        }

        // Use the provided ID Card
        $customer_id_card = $id_card;
        
        // Insert customer into database
        $stmt = $pdo->prepare("
            INSERT INTO Customer (ID_Card, Drivers_License, Name, Phone_Number, Address) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $customer_id_card,
            $license_number,
            $full_name,
            $phone,
            $address
        ]);
        
        // Redirect with success message
        header("Location: ../dashboard.php?success=" . urlencode("Customer added successfully! ID: $customer_id_card - $full_name"));
        exit();
        
    } catch (Exception $e) {
        header("Location: ../dashboard.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: ../dashboard.php?error=" . urlencode("Invalid request method"));
    exit();
}
?>