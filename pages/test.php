<?php
// test_connection.php
$host = '127.0.0.1';
$dbname = 'smart_car';
$username = 'root';
$password = 'Avangarde13'; // Your MySQL password if you set one

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>✅ Database Connection Successful!</h2>";
    
    // Test query
    $stmt = $pdo->query("SELECT * FROM Car_Class");
    $results = $stmt->fetchAll();
    
    echo "<h3>Test Data from Car_Class table:</h3>";
    foreach($results as $row) {
        echo "ID: " . $row['CC_ID'] . " - Name: " . $row['Class_Name'] . "<br>";
    }
    
} catch(PDOException $e) {
    echo "<h2>❌ Connection Failed!</h2>";
    echo "Error: " . $e->getMessage();
}
?>