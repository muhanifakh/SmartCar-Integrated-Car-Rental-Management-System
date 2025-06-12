<?php
// handlers/add_car.php
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
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $year = $_POST['year'] ?? '';
        $car_class_id = $_POST['car_class_id'] ?? '';
        $car_type = $_POST['car_type'] ?? '';
        $daily_rate = $_POST['daily_rate'] ?? '';
        
        // Validate required fields
        if (empty($brand) || empty($model) || empty($year) || 
            empty($car_class_id) || empty($car_type) || empty($daily_rate)) {
            throw new Exception('All fields are required');
        }
        
        // Validate year
        $current_year = date('Y');
        if ($year < 1990 || $year > ($current_year + 1)) {
            throw new Exception('Invalid year. Must be between 1990 and ' . ($current_year + 1));
        }
        
        // Validate daily rate
        if ($daily_rate <= 0) {
            throw new Exception('Daily rate must be greater than 0');
        }
        
        // Validate car class exists
        $stmt_check_class = $pdo->prepare("SELECT CC_ID FROM Car_Class WHERE CC_ID = ?");
        $stmt_check_class->execute([$car_class_id]);
        if (!$stmt_check_class->fetch()) {
            throw new Exception('Invalid car class selected');
        }
        
        // Generate unique Car ID using database function
        $car_id = $pdo->query("SELECT GenerateCarID()")->fetchColumn();
        
        // Create car name
        $car_name = $brand . ' ' . $model . ' ' . $year;
        
        // Insert car into database
        $stmt = $pdo->prepare("
            INSERT INTO Car (Car_ID, Car_Name, Car_Type, Car_Price, Car_Class_CC_ID) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $car_id, 
            $car_name, 
            $car_type, 
            $daily_rate, 
            $car_class_id
        ]);
        
        // Redirect with success message
        header("Location: ../dashboard.php?success=" . urlencode("Car added successfully! ID: $car_id - $car_name"));
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