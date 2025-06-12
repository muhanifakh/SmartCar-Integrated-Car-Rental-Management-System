<?php
$host = '127.0.0.1';
$dbname = 'smart_car'; 
$username = 'root';
$password = 'Avangarde13';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_POST['action'];
    $id = $_POST['ID_Card'];

    if ($action === 'update') {
        // Update
        $sql = "UPDATE Customer SET Name=?, Phone_Number=?, Address=?, Drivers_License=? WHERE ID_Card=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['Name'],
            $_POST['Phone_Number'],
            $_POST['Address'],
            $_POST['Drivers_License'],
            $id
        ]);
        // INSERT NOTIFICATION
        $message = "Customer '{$_POST['Name']}' updated successfully!";
        $type = "success";
        $link = "/pages/users.php?customer_id={$id}";
        $notif_query = "INSERT INTO Notification (User_ID, Message, Type, Link) VALUES (?, ?, ?, ?)";
        $stmt_notif = $pdo->prepare($notif_query);
        $stmt_notif->execute([null, $message, $type, $link]);

        header("Location: ../users.php?success=" . urlencode($message));
        exit();
    } elseif ($action === 'delete') {
        // Delete
        $sql = "DELETE FROM Customer WHERE ID_Card=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        // INSERT NOTIFICATION
        $message = "Customer ID '{$id}' deleted!";
        $type = "danger";
        $link = "/pages/users.php";
        $notif_query = "INSERT INTO Notification (User_ID, Message, Type, Link) VALUES (?, ?, ?, ?)";
        $stmt_notif = $pdo->prepare($notif_query);
        $stmt_notif->execute([null, $message, $type, $link]);

        header("Location: ../users.php?success=" . urlencode($message));
        exit();
    }
    header('Location: ../users.php');
    exit;

} catch(PDOException $e) {
    // Error redirect
    header("Location: ../users.php?error=" . urlencode("Database error: " . $e->getMessage()));
    exit();
}
?>
