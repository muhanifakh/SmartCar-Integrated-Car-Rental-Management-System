<?php
// handlers/add_booking.php
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
        $customer_search = trim($_POST['customer_search'] ?? '');
        $customer_selection = $_POST['customer_selection'] ?? '';
        $car_id = $_POST['car_id'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $stmt_car_rate = $pdo->prepare("SELECT Car_Price FROM Car WHERE Car_ID = ?");
        $stmt_car_rate->execute([$car_id]);
        $daily_rate = $stmt_car_rate->fetchColumn();
        
        // Validate required fields
        if (empty($customer_search) || empty($car_id) || empty($start_date) || empty($end_date)) {
            throw new Exception('All fields are required');
        }
        
        // Validate dates
        if (strtotime($start_date) >= strtotime($end_date)) {
            throw new Exception('End date must be after start date');
        }
        
        if (strtotime($start_date) < strtotime(date('Y-m-d'))) {
            throw new Exception('Start date cannot be in the past');
        }
        
        // Calculate total amount
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $days = $start->diff($end)->days + 1;
        $total_amount = $days * $daily_rate;
        
        // Generate unique IDs using database functions
        $booking_id = $pdo->query("SELECT GenerateBookingID()")->fetchColumn();
        $transaction_id = $pdo->query("SELECT GenerateTransactionID()")->fetchColumn();
        
        // Check if customer exists by phone
        $stmt_check = $pdo->prepare("SELECT ID_Card, Drivers_License FROM Customer WHERE Phone_Number = ? LIMIT 1");
        $stmt_check->execute([$customer_phone]);
        $existing_customer = $stmt_check->fetch();
        
        // Handle customer selection
        $customer_selection = $_POST['customer_selection'] ?? '';

        if ($customer_selection === 'new_customer') { 
            // Create new customer
            $customer_name = trim($_POST['customer_search'] ?? '');
            $customer_phone = trim($_POST['customer_phone'] ?? '');
            $id_card = trim($_POST['id_card'] ?? '');
            $license_number = trim($_POST['license_number'] ?? '');
            $address = trim($_POST['address'] ?? '');
            
            // Validate new customer fields
            if (empty($customer_name) || empty($customer_phone) || empty($id_card) || 
                empty($license_number) || empty($address)) {
                throw new Exception('All customer fields are required for new customer');
            }
            
            // Validate ID Card format
            if (!preg_match('/^\d{16}$/', $id_card)) {
                throw new Exception('ID Card must be exactly 16 digits');
            }
            
            // Check for duplicates
            $stmt_check_phone = $pdo->prepare("SELECT ID_Card FROM Customer WHERE Phone_Number = ?");
            $stmt_check_phone->execute([$customer_phone]);
            if ($stmt_check_phone->fetch()) {
                throw new Exception('Phone number already exists');
            }
            
            $stmt_check_id = $pdo->prepare("SELECT ID_Card FROM Customer WHERE ID_Card = ?");
            $stmt_check_id->execute([$id_card]);
            if ($stmt_check_id->fetch()) {
                throw new Exception('ID Card already exists');
            }
            
            $stmt_check_license = $pdo->prepare("SELECT ID_Card FROM Customer WHERE Drivers_License = ?");
            $stmt_check_license->execute([$license_number]);
            if ($stmt_check_license->fetch()) {
                throw new Exception('Driver license already exists');
            }
            
            // Insert new customer
            $stmt_customer = $pdo->prepare("
                INSERT INTO Customer (ID_Card, Drivers_License, Name, Phone_Number, Address) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt_customer->execute([$id_card, $license_number, $customer_name, $customer_phone, $address]);
            
            $customer_id_card = $id_card;
            $drivers_license = $license_number;
            
        } else {
            // Use existing customer
            $stmt_customer = $pdo->prepare("SELECT ID_Card, Drivers_License FROM Customer WHERE ID_Card = ?");
            $stmt_customer->execute([$customer_selection]);
            $existing_customer = $stmt_customer->fetch();
            
            if (!$existing_customer) {
                throw new Exception('Selected customer not found');
            }
            
            $customer_id_card = $existing_customer['ID_Card'];
            $drivers_license = $existing_customer['Drivers_License'];
        }
        
        // Get random staff ID
        $stmt_staff = $pdo->prepare("SELECT Staff_ID FROM Staff ORDER BY RAND() LIMIT 1");
        $stmt_staff->execute();
        $staff_id = $stmt_staff->fetchColumn();
        
        if (!$staff_id) {
            throw new Exception('No staff available to process booking');
        }
        
        // Check car availability for the dates
        $stmt_availability = $pdo->prepare("
            SELECT COUNT(*) FROM Booking b
            JOIN Transaction t ON b.Booking_ID = t.Booking_Booking_ID
            JOIN Car_Transaction ct ON t.Transaction_ID = ct.Transaction_Transaction_ID
            WHERE ct.Car_Car_ID = ? 
            AND ((b.Start_Date <= ? AND b.End_Date >= ?) 
                 OR (b.Start_Date <= ? AND b.End_Date >= ?)
                 OR (b.Start_Date >= ? AND b.End_Date <= ?))
        ");
        $stmt_availability->execute([
            $car_id, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date
        ]);
        
        if ($stmt_availability->fetchColumn() > 0) {
            throw new Exception('Car is not available for the selected dates');
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Insert booking
        $stmt_booking = $pdo->prepare("
            INSERT INTO Booking (Booking_ID, Date_Preserved, Start_Date, End_Date, 
                               Customer_Drivers_License, Customer_ID_Card, Staff_Staff_ID) 
            VALUES (?, CURDATE(), ?, ?, ?, ?, ?)
        ");
        $stmt_booking->execute([
            $booking_id, $start_date, $end_date, $drivers_license, $customer_id_card, $staff_id
        ]);
        
        // Insert transaction
        $stmt_transaction = $pdo->prepare("
            INSERT INTO Transaction (Transaction_ID, Payment_Method, Amount, Payment_Date, 
                                   Staff_Staff_ID, Booking_Booking_ID) 
            VALUES (?, 'Cash', ?, NOW(), ?, ?)
        ");
        $stmt_transaction->execute([$transaction_id, $total_amount, $staff_id, $booking_id]);
        
        // Insert car transaction relationship
        $stmt_car_trans = $pdo->prepare("
            INSERT INTO Car_Transaction (Car_Car_ID, Transaction_Transaction_ID) 
            VALUES (?, ?)
        ");
        $stmt_car_trans->execute([$car_id, $transaction_id]);
        
        // Commit transaction
        $pdo->commit();
        
        // Redirect with success message
        header("Location: ../dashboard.php?success=" . urlencode("Booking created successfully! ID: $booking_id"));
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction if it was started
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        header("Location: ../dashboard.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: ../dashboard.php?error=" . urlencode("Invalid request method"));
    exit();
}
?>