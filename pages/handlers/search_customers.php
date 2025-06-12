<?php
$host = '127.0.0.1';
$dbname = 'smart_car'; 
$username = 'root';
$password = 'Avangarde13';

$search = isset($_GET['q']) ? $_GET['q'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 100;
$offset = ($page - 1) * $per_page;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Hitung total data
    $count_query = "SELECT COUNT(*) FROM Customer 
        WHERE Name LIKE :search
           OR Phone_Number LIKE :search
           OR ID_Card LIKE :search
           OR Drivers_License LIKE :search
           OR Address LIKE :search";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute(['search' => "%$search%"]);
    $total = $count_stmt->fetchColumn();

    // Ambil data sesuai page & query
    $query = "SELECT ID_Card, Name, Phone_Number, Address, Drivers_License
              FROM Customer
              WHERE Name LIKE :search
                 OR Phone_Number LIKE :search
                 OR ID_Card LIKE :search
                 OR Drivers_License LIKE :search
                 OR Address LIKE :search
              ORDER BY Name
              LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':search', "%$search%");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Response: data + info paging
    header('Content-Type: application/json');
    echo json_encode([
        'data' => $customers,
        'total' => intval($total),
        'per_page' => $per_page,
        'page' => $page,
        'total_pages' => ceil($total / $per_page)
    ]);
    exit();
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}
?>
