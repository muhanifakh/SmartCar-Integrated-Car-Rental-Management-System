<?php
ob_start();

// Database connection
$host = '127.0.0.1';
$dbname = 'smart_car'; 
$username = 'root';
$password = 'Avangarde13';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Simple Query: Show all bookings for today with detailed information
    $today_bookings_query = "
        SELECT 
            b.Booking_ID,
            b.Date_Preserved,
            b.Start_Date,
            b.End_Date,
            DATEDIFF(b.End_Date, b.Start_Date) as Rental_Days,
            c.Name as Customer_Name,
            c.Phone_Number as Customer_Phone,
            s.Name as Staff_Name,
            s.Position as Staff_Position,
            GROUP_CONCAT(CONCAT(car.Car_Name, ' (', car.Car_Type, ')') SEPARATOR ', ') as Rented_Cars,
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
        WHERE DATE(b.Start_Date) = CURRENT_DATE 
           OR DATE(b.End_Date) = CURRENT_DATE
           OR (b.Start_Date <= CURRENT_DATE AND b.End_Date >= CURRENT_DATE)
        GROUP BY b.Booking_ID, b.Date_Preserved, b.Start_Date, b.End_Date, 
                 c.Name, c.Phone_Number, s.Name, s.Position, t.Amount, t.Payment_Method
        ORDER BY b.Start_Date DESC
    ";
    $today_bookings = $pdo->query($today_bookings_query)->fetchAll();
    
    // Simple Query: Customer booking history (top 20 customers by bookings)
    $customer_history_query = "
        SELECT 
            c.Name as Customer_Name,
            c.Phone_Number,
            COUNT(b.Booking_ID) as Total_Bookings,
            SUM(t.Amount) as Total_Spent,
            MAX(b.Date_Preserved) as Last_Booking_Date,
            AVG(DATEDIFF(b.End_Date, b.Start_Date)) as Avg_Rental_Days,
            CASE 
                WHEN COUNT(b.Booking_ID) >= 5 THEN 'VIP Customer'
                WHEN COUNT(b.Booking_ID) >= 3 THEN 'Regular Customer'
                WHEN COUNT(b.Booking_ID) >= 1 THEN 'New Customer'
                ELSE 'Prospect'
            END as Customer_Type
        FROM Customer c
        LEFT JOIN Booking b ON c.ID_Card = b.Customer_ID_Card
        LEFT JOIN Transaction t ON b.Booking_ID = t.Booking_Booking_ID
        GROUP BY c.ID_Card, c.Name, c.Phone_Number
        HAVING COUNT(b.Booking_ID) > 0
        ORDER BY Total_Bookings DESC
        LIMIT 10
    ";
    $customer_history = $pdo->query($customer_history_query)->fetchAll();
    
    // Complex Query: Seasonal booking patterns (last 6 months)
    $seasonal_patterns_query = "
        SELECT 
            YEAR(b.Start_Date) as Booking_Year,
            MONTH(b.Start_Date) as Booking_Month,
            MONTHNAME(b.Start_Date) as Month_Name,
            COUNT(b.Booking_ID) as Total_Bookings,
            SUM(t.Amount) as Monthly_Revenue,
            ROUND(AVG(t.Amount), 2) as Average_Booking_Value,
            COUNT(DISTINCT b.Customer_ID_Card) as Unique_Customers,
            ROUND(AVG(DATEDIFF(b.End_Date, b.Start_Date)), 1) as Average_Rental_Days,
            ROUND(
                COUNT(b.Booking_ID) * 100.0 / 
                SUM(COUNT(b.Booking_ID)) OVER(), 2
            ) as Booking_Percentage
        FROM Booking b
        JOIN Transaction t ON b.Booking_ID = t.Booking_Booking_ID
        WHERE b.Start_Date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
        GROUP BY YEAR(b.Start_Date), MONTH(b.Start_Date), MONTHNAME(b.Start_Date)
        ORDER BY Booking_Year DESC, Booking_Month DESC
    ";
    $seasonal_patterns = $pdo->query($seasonal_patterns_query)->fetchAll();
    
    // Get all recent bookings for main table
    $all_bookings_query = "
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
        GROUP BY b.Booking_ID, b.Date_Preserved, b.Start_Date, b.End_Date, 
                 c.Name, c.Phone_Number, s.Name, t.Amount, t.Payment_Method
        ORDER BY b.Date_Preserved DESC
        LIMIT 50
    ";
    $all_bookings = $pdo->query($all_bookings_query)->fetchAll();
    
    // Get booking statistics for summary cards
    $booking_stats_query = "
        SELECT 
            COUNT(CASE WHEN DATE(Start_Date) = CURRENT_DATE OR DATE(End_Date) = CURRENT_DATE 
                      OR (Start_Date <= CURRENT_DATE AND End_Date >= CURRENT_DATE) THEN 1 END) as Today_Bookings,
            COUNT(CASE WHEN Start_Date <= CURRENT_DATE AND End_Date >= CURRENT_DATE THEN 1 END) as Active_Bookings,
            COUNT(CASE WHEN Start_Date > CURRENT_DATE THEN 1 END) as Upcoming_Bookings,
            COUNT(CASE WHEN End_Date < CURRENT_DATE THEN 1 END) as Completed_Bookings,
            ROUND(AVG(DATEDIFF(End_Date, Start_Date)), 1) as Avg_Rental_Duration
        FROM Booking
    ";
    $booking_stats = $pdo->query($booking_stats_query)->fetch();
    
} catch(PDOException $e) {
    $today_bookings = [];
    $customer_history = [];
    $seasonal_patterns = [];
    $all_bookings = [];
    $booking_stats = [
        'Today_Bookings' => 0, 'Active_Bookings' => 0, 
        'Upcoming_Bookings' => 0, 'Completed_Bookings' => 0, 
        'Avg_Rental_Duration' => 0
    ];
    $error_message = "Database connection failed: " . $e->getMessage();
}

// Helper functions
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function getStatusBadge($status) {
    switch($status) {
        case 'Active': return 'bg-gradient-success';
        case 'Upcoming': return 'bg-gradient-info';
        case 'Completed': return 'bg-gradient-secondary';
        default: return 'bg-gradient-dark';
    }
}

function getCustomerTypeBadge($type) {
    switch($type) {
        case 'VIP Customer': return 'bg-gradient-warning';
        case 'Regular Customer': return 'bg-gradient-success';
        case 'New Customer': return 'bg-gradient-info';
        default: return 'bg-gradient-secondary';
    }
}
?>

<div class="container-fluid py-2">
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Summary Cards Row -->
    <div class="row mb-4">
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">Today's Activity</p>
                            <h4 class="mb-0"><?php echo $booking_stats['Today_Bookings']; ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-primary shadow-primary shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">today</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm">Bookings starting/ending today</p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">Active Rentals</p>
                            <h4 class="mb-0"><?php echo $booking_stats['Active_Bookings']; ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-success shadow-success shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">directions_car</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm">Currently rented vehicles</p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">Upcoming Bookings</p>
                            <h4 class="mb-0"><?php echo $booking_stats['Upcoming_Bookings']; ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-info shadow-info shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">schedule</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm">Future reservations</p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-sm-6">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">Avg Rental Days</p>
                            <h4 class="mb-0"><?php echo $booking_stats['Avg_Rental_Duration']; ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-warning shadow-warning shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">date_range</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm">Average rental duration</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Row -->
    <div class="row mb-4">
        <div class="col-12">
            <!-- Seasonal Booking Patterns -->
            <div class="card mb-4">
                <div class="card-header pb-0 px-3">
                    <h6 class="mb-0">Seasonal Booking Patterns (Last 6 Months)</h6>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Month</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Bookings</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Revenue</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Avg Days</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($seasonal_patterns as $pattern): ?>
                                    <tr>
                                        <td>
                                            <p class="text-sm font-weight-bold mb-0"><?php echo htmlspecialchars($pattern['Month_Name']); ?> <?php echo $pattern['Booking_Year']; ?></p>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="text-sm font-weight-bold"><?php echo $pattern['Total_Bookings']; ?></span>
                                                <div class="ms-2" style="width: 50px;">
                                                    <div class="progress progress-sm">
                                                        <div class="progress-bar bg-gradient-primary" style="width: <?php echo $pattern['Booking_Percentage']; ?>%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-sm font-weight-bold"><?php echo formatRupiah($pattern['Monthly_Revenue']); ?></span>
                                        </td>
                                        <td>
                                            <span class="text-sm"><?php echo $pattern['Average_Rental_Days']; ?> days</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-sm bg-gradient-info"><?php echo $pattern['Booking_Percentage']; ?>%</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Top Customers -->
            <div class="card">
                <div class="card-header pb-0 px-3">
                    <h6 class="mb-0">Top Customers by Bookings</h6>
                </div>
                <div class="card-body p-3">
                    <div class="row">
                        <?php foreach($customer_history as $customer): ?>
                            <div class="col-lg-6 col-md-6 mb-3">
                                <div class="p-2 border-radius-lg bg-gray-100">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1 text-sm"><?php echo htmlspecialchars($customer['Customer_Name']); ?></h6>
                                            <p class="text-xs text-muted mb-0"><?php echo htmlspecialchars($customer['Phone_Number']); ?></p>
                                        </div>
                                        <span class="badge <?php echo getCustomerTypeBadge($customer['Customer_Type']); ?> badge-sm">
                                            <?php echo $customer['Customer_Type']; ?>
                                        </span>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-4 text-center">
                                            <p class="text-xs text-muted mb-0">Bookings</p>
                                            <h6 class="mb-0 text-sm"><?php echo $customer['Total_Bookings']; ?></h6>
                                        </div>
                                        <div class="col-4 text-center">
                                            <p class="text-xs text-muted mb-0">Spent</p>
                                            <h6 class="mb-0 text-sm"><?php echo formatRupiah($customer['Total_Spent']); ?></h6>
                                        </div>
                                        <div class="col-4 text-center">
                                            <p class="text-xs text-muted mb-0">Avg Days</p>
                                            <h6 class="mb-0 text-sm"><?php echo round($customer['Avg_Rental_Days'], 1); ?></h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Today's Bookings Activity -->
    <?php if (!empty($today_bookings)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header pb-0 px-3">
                        <h6 class="mb-0">Today's Booking Activity (<?php echo count($today_bookings); ?> activities)</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="row">
                            <?php foreach($today_bookings as $booking): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card card-body border">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($booking['Customer_Name']); ?></h6>
                                            <span class="badge <?php echo getStatusBadge($booking['Booking_Status']); ?> badge-sm">
                                                <?php echo $booking['Booking_Status']; ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-muted mb-1">
                                            <i class="material-symbols-rounded text-xs">directions_car</i>
                                            <?php echo htmlspecialchars($booking['Rented_Cars'] ?: 'No cars assigned'); ?>
                                        </p>
                                        <p class="text-sm text-muted mb-1">
                                            <i class="material-symbols-rounded text-xs">schedule</i>
                                            <?php echo date('M d', strtotime($booking['Start_Date'])); ?> - <?php echo date('M d, Y', strtotime($booking['End_Date'])); ?>
                                            (<?php echo $booking['Rental_Days']; ?> days)
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">ID: <?php echo $booking['Booking_ID']; ?></small>
                                            <strong class="text-sm"><?php echo formatRupiah($booking['Total_Amount'] ?? 0); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- All Bookings Table -->
     <div class="px-4 pt-3">
        <input type="text" id="searchBookings" class="form-control mb-3" placeholder="Search bookings...">
    </div>
    <div id="bookingsPagination" class="d-flex justify-content-end mb-2"></div>

    <div class="row">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-dark shadow-dark border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">Total Bookings (<?php echo count($all_bookings); ?> records)</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <div class="table-responsive p-0">
                        <table class="table align-items-center justify-content-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Booking ID</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Customer</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Cars</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Duration</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Amount</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                                    <th class="text-secondary opacity-7"></th>
                                </tr>
                            </thead>
                            <tbody id="bookingsTableBody">
                                <?php if (empty($all_bookings)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">No booking data available</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($all_bookings as $booking): ?>
                                        <tr>
                                            <td>
                                                <p class="text-sm font-weight-bold mb-0"><?php echo htmlspecialchars($booking['Booking_ID']); ?></p>
                                                <p class="text-xs text-muted mb-0">by <?php echo htmlspecialchars($booking['Staff_Name']); ?></p>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="text-sm font-weight-bold"><?php echo htmlspecialchars($booking['Customer_Name']); ?></span>
                                                    <span class="text-xs text-muted"><?php echo htmlspecialchars($booking['Customer_Phone']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-sm"><?php echo htmlspecialchars($booking['Cars_Rented'] ?: 'No cars'); ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="text-sm"><?php echo date('M d', strtotime($booking['Start_Date'])); ?> - <?php echo date('M d', strtotime($booking['End_Date'])); ?></span>
                                                    <span class="text-xs text-muted"><?php echo $booking['Rental_Days']; ?> days</span>
                                                </div>
                                            </td>
                                            <td>
                                                <p class="text-sm font-weight-bold mb-0"><?php echo formatRupiah($booking['Total_Amount'] ?? 0); ?></p>
                                                <p class="text-xs text-muted mb-0"><?php echo htmlspecialchars($booking['Payment_Method'] ?? 'N/A'); ?></p>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo getStatusBadge($booking['Booking_Status']); ?> badge-sm">
                                                    <?php echo $booking['Booking_Status']; ?>
                                                </span>
                                            </td>
                                            <td class="align-middle">
                                                <button class="btn btn-link text-secondary mb-0" 
                                                        data-bs-toggle="tooltip" 
                                                        data-bs-placement="top" 
                                                        title="View Details">
                                                    <i class="fa fa-eye text-xs"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('searchBookings');
  const tableBody = document.getElementById('bookingsTableBody');
  const paginationDiv = document.getElementById('bookingsPagination');

  let currentQuery = '';
  let currentPage = 1;
  const perPage = 100;

  function formatRupiah(angka) {
      if (!angka) return "Rp 0";
      return 'Rp ' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function renderRows(bookings) {
    if (bookings.length === 0) {
      tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-4">No booking data found</td></tr>`;
      return;
    }
    let rows = '';
    bookings.forEach(b => {
      rows += `
        <tr>
          <td>
            <p class="text-sm font-weight-bold mb-0">${b.Booking_ID}</p>
            <p class="text-xs text-muted mb-0">by ${b.Staff_Name || '-'}</p>
          </td>
          <td>
            <div class="d-flex flex-column">
                <span class="text-sm font-weight-bold">${b.Customer_Name || '-'}</span>
                <span class="text-xs text-muted">${b.Customer_Phone || '-'}</span>
            </div>
          </td>
          <td>
            <span class="text-sm">${b.Cars_Rented || 'No cars'}</span>
          </td>
          <td>
            <div class="d-flex flex-column">
                <span class="text-sm">${b.Start_Date ? (new Date(b.Start_Date)).toLocaleDateString() : '-'} - ${b.End_Date ? (new Date(b.End_Date)).toLocaleDateString() : '-'}</span>
                <span class="text-xs text-muted">${b.Rental_Days || 0} days</span>
            </div>
          </td>
          <td>
            <p class="text-sm font-weight-bold mb-0">${formatRupiah(b.Total_Amount || 0)}</p>
            <p class="text-xs text-muted mb-0">${b.Payment_Method || 'N/A'}</p>
          </td>
          <td>
            <span class="badge badge-sm ${
              b.Booking_Status === 'Active' ? 'bg-gradient-success' :
              b.Booking_Status === 'Upcoming' ? 'bg-gradient-info' :
              b.Booking_Status === 'Completed' ? 'bg-gradient-secondary' : 'bg-gradient-dark'
            }">${b.Booking_Status}</span>
          </td>
        </tr>
      `;
    });
    tableBody.innerHTML = rows;
  }

  function renderPagination(totalPages, page) {
    let html = `<nav aria-label="Bookings pagination"><ul class="pagination pagination-sm mb-0">`;
    for (let i = 1; i <= totalPages; i++) {
      html += `<li class="page-item${i === page ? ' active' : ''}">
        <a class="page-link" href="#" data-page="${i}">${i}</a>
      </li>`;
    }
    html += `</ul></nav>`;
    paginationDiv.innerHTML = html;

    // Register click
    paginationDiv.querySelectorAll('.page-link').forEach(link => {
      link.addEventListener('click', function(e){
        e.preventDefault();
        fetchBookings(currentQuery, parseInt(this.getAttribute('data-page')));
      });
    });
  }

  function fetchBookings(query = '', page = 1) {
    fetch(`/pages/handlers/search_bookings.php?q=${encodeURIComponent(query)}&page=${page}&per_page=${perPage}`)
      .then(resp => resp.json())
      .then(result => {
        renderRows(result.data);
        renderPagination(result.total_pages, result.page);
        currentQuery = query;
        currentPage = result.page;
      })
      .catch(err => {
        tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Error loading data</td></tr>`;
        paginationDiv.innerHTML = '';
      });
  }

  // Initial load
  fetchBookings();

  // Search live
  searchInput.addEventListener('input', function() {
    fetchBookings(this.value, 1);
  });
});
</script>


<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../app.php';
?>