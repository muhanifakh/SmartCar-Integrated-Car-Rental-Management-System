<?php
$host = '127.0.0.1';
$dbname = 'smart_car'; 
$username = 'root';
$password = 'Avangarde13';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_POST['action'];
    $id = $_POST['Staff_ID'];

    if ($action === 'update') {
        $sql = "UPDATE Staff SET Name=?, Position=?, Contact=? WHERE Staff_ID=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['Name'],
            $_POST['Position'],
            $_POST['Contact'],
            $id
        ]);
        // INSERT NOTIFICATION
        $message = "Staff '{$_POST['Name']}' updated successfully!";
        $type = "info";
        $link = "/pages/users.php?staff_id={$id}";
        $notif_query = "INSERT INTO Notification (User_ID, Message, Type, Link) VALUES (?, ?, ?, ?)";
        $stmt_notif = $pdo->prepare($notif_query);
        $stmt_notif->execute([null, $message, $type, $link]);

        header("Location: ../users.php?success=" . urlencode($message));
        exit();
    } elseif ($action === 'delete') {
        $sql = "DELETE FROM Staff WHERE Staff_ID=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        // INSERT NOTIFICATION
        $message = "Staff ID '{$id}' deleted!";
        $type = "danger";
        $link = "/pages/users.php";
        $notif_query = "INSERT INTO Notification (User_ID, Message, Type, Link) VALUES (?, ?, ?, ?)";
        $stmt_notif = $pdo->prepare($notif_query);
        $stmt_notif->execute([null, $message, $type, $link]);

        header("Location: ../users.php?success=" . urlencode($message));
        exit();
    }
} catch(PDOException $e) {
    // INSERT NOTIFICATION for error
    $message = "Database error: " . $e->getMessage();
    $type = "danger";
    $link = "/pages/users.php";
    // (Optional) Notif error juga dicatat
    try {
        $notif_query = "INSERT INTO Notification (User_ID, Message, Type, Link) VALUES (?, ?, ?, ?)";
        $stmt_notif = $pdo->prepare($notif_query);
        $stmt_notif->execute([null, $message, $type, $link]);
    } catch (Exception $err) { /* skip */ }
    header("Location: ../users.php?error=" . urlencode($message));
    exit();
}
?>
