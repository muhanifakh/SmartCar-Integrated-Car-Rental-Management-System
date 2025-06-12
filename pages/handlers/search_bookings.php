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

    // Total count
    $count_query = "
        SELECT COUNT(DISTINCT b.Booking_ID)
        FROM Booking b
        LEFT JOIN Customer c ON b.Customer_ID_Card = c.ID_Card
        LEFT JOIN Staff s ON b.Staff_Staff_ID = s.Staff_ID
        LEFT JOIN Transaction t ON b.Booking_ID = t.Booking_Booking_ID
        LEFT JOIN Car_Transaction ct ON t.Transaction_ID = ct.Transaction_Transaction_ID
        LEFT JOIN Car car ON ct.Car_Car_ID = car.Car_ID
        WHERE 
            b.Booking_ID LIKE :search OR
            c.Name LIKE :search OR
            c.Phone_Number LIKE :search OR
            s.Name LIKE :search OR
            car.Car_Name LIKE :search
    ";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute(['search' => "%$search%"]);
    $total = $count_stmt->fetchColumn();

    // Get bookings per page
    $query = "
        SELECT 
            b.Booking_ID,
            b.Date_Preserved,
            b.Start_Date,
            b.End_Date,
            DATEDIFF(b.End_Date, b.Start_Date) as Rental_Days,
            c.Name as Customer_Name,
            c.Phone_Number as Customer_Phone,
            s.Name as Staff_Name,
            GROUP_CONCAT(DISTINCT car.Car_Name SEPARATOR ', ') as Cars_Rented,
            t.Amount as Total_Amount,
            t.Payment_Method,
            CASE 
                WHEN b.Start_Date <= CURRENT_DATE AND b.End_Date >= CURRENT_DATE THEN 'Active'
                WHEN b.Start_Date > CURRENT_DATE THEN 'Upcoming'
                WHEN b.End_Date < CURRENT_DATE THEN 'Completed'
                ELSE 'Unknown'
            END as Booking_Status
        FROM Booking b
        LEFT JOIN Customer c ON b.Customer_ID_Card = c.ID_Card
        LEFT JOIN Staff s ON b.Staff_Staff_ID = s.Staff_ID
        LEFT JOIN Transaction t ON b.Booking_ID = t.Booking_Booking_ID
        LEFT JOIN Car_Transaction ct ON t.Transaction_ID = ct.Transaction_Transaction_ID
        LEFT JOIN Car car ON ct.Car_Car_ID = car.Car_ID
        WHERE 
            b.Booking_ID LIKE :search OR
            c.Name LIKE :search OR
            c.Phone_Number LIKE :search OR
            s.Name LIKE :search OR
            car.Car_Name LIKE :search
        GROUP BY b.Booking_ID, b.Date_Preserved, b.Start_Date, b.End_Date, 
                c.Name, c.Phone_Number, s.Name, t.Amount, t.Payment_Method
        ORDER BY b.Date_Preserved DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':search', "%$search%");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
        'data' => $bookings,
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
