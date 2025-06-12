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
    
    // Complex Query: Revenue per month (last 12 months)
    $monthly_revenue_query = "
        SELECT 
            YEAR(Payment_Date) as Year,
            MONTH(Payment_Date) as Month,
            MONTHNAME(Payment_Date) as Month_Name,
            COUNT(Transaction_ID) as Total_Transactions,
            SUM(Amount) as Monthly_Revenue,
            ROUND(AVG(Amount), 2) as Average_Transaction
        FROM Transaction
        WHERE Payment_Date >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
        GROUP BY YEAR(Payment_Date), MONTH(Payment_Date), MONTHNAME(Payment_Date)
        ORDER BY YEAR(Payment_Date) ASC, MONTH(Payment_Date) ASC
    ";
    $monthly_revenue = $pdo->query($monthly_revenue_query)->fetchAll();
    
    // Complex Query: Top customers by bookings and revenue
    $top_customers_query = "
        SELECT 
            c.Name as Customer_Name,
            c.Phone_Number,
            COUNT(b.Booking_ID) as Total_Bookings,
            SUM(t.Amount) as Total_Spent,
            ROUND(AVG(t.Amount), 2) as Average_Booking_Value,
            MAX(b.Date_Preserved) as Last_Booking_Date,
            ROUND(AVG(DATEDIFF(b.End_Date, b.Start_Date)), 1) as Avg_Rental_Days
        FROM Customer c
        JOIN Booking b ON c.ID_Card = b.Customer_ID_Card
        JOIN Transaction t ON b.Booking_ID = t.Booking_Booking_ID
        GROUP BY c.ID_Card, c.Name, c.Phone_Number
        ORDER BY Total_Spent DESC
        LIMIT 10
    ";
    $top_customers = $pdo->query($top_customers_query)->fetchAll();
    
    // Complex Query: Vehicle utilization analysis
    $vehicle_utilization_query = "
        SELECT 
            c.Car_ID,
            c.Car_Name,
            cc.Class_Name,
            c.Car_Price,
            COUNT(ct.Transaction_Transaction_ID) as Rental_Count,
            SUM(t.Amount) as Total_Revenue,
            ROUND(AVG(t.Amount), 2) as Average_Revenue_Per_Rental,
            ROUND(
                COUNT(ct.Transaction_Transaction_ID) * 100.0 / 
                (SELECT COUNT(*) FROM Car_Transaction), 2
            ) as Utilization_Percentage
        FROM Car c
        JOIN Car_Class cc ON c.Car_Class_CC_ID = cc.CC_ID
        LEFT JOIN Car_Transaction ct ON c.Car_ID = ct.Car_Car_ID
        LEFT JOIN Transaction t ON ct.Transaction_Transaction_ID = t.Transaction_ID
        GROUP BY c.Car_ID, c.Car_Name, cc.Class_Name, c.Car_Price
        ORDER BY Total_Revenue DESC
        LIMIT 15
    ";
    $vehicle_utilization = $pdo->query($vehicle_utilization_query)->fetchAll();
    
    // Complex Query: Popular car classes
    $popular_classes_query = "
        SELECT 
            cc.Class_Name,
            COUNT(DISTINCT c.Car_ID) as Total_Cars_In_Class,
            COUNT(ct.Car_Car_ID) as Total_Rentals,
            SUM(t.Amount) as Class_Revenue,
            ROUND(AVG(c.Car_Price), 2) as Average_Daily_Rate,
            ROUND(
                COUNT(ct.Car_Car_ID) * 100.0 / 
                (SELECT COUNT(*) FROM Car_Transaction), 2
            ) as Popularity_Percentage
        FROM Car_Class cc
        LEFT JOIN Car c ON cc.CC_ID = c.Car_Class_CC_ID
        LEFT JOIN Car_Transaction ct ON c.Car_ID = ct.Car_Car_ID
        LEFT JOIN Transaction t ON ct.Transaction_Transaction_ID = t.Transaction_ID
        GROUP BY cc.CC_ID, cc.Class_Name
        ORDER BY Total_Rentals DESC
    ";
    $popular_classes = $pdo->query($popular_classes_query)->fetchAll();
    
    // Additional analytics for summary cards
    $summary_stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM Customer) as Total_Customers,
            (SELECT COUNT(*) FROM Car) as Total_Cars,
            (SELECT COUNT(*) FROM Booking) as Total_Bookings,
            (SELECT SUM(Amount) FROM Transaction) as Total_Revenue,
            (SELECT COUNT(*) FROM Transaction WHERE DATE(Payment_Date) = CURRENT_DATE) as Today_Transactions,
            (SELECT SUM(Amount) FROM Transaction WHERE DATE(Payment_Date) = CURRENT_DATE) as Today_Revenue
    ";
    $summary_stats = $pdo->query($summary_stats_query)->fetch();
    
    // Prepare data for charts (JavaScript arrays)
    $monthly_labels = [];
    $monthly_revenues = [];
    $monthly_transactions = [];
    
    foreach($monthly_revenue as $month) {
        $monthly_labels[] = $month['Month_Name'] . ' ' . $month['Year'];
        $monthly_revenues[] = $month['Monthly_Revenue'];
        $monthly_transactions[] = $month['Total_Transactions'];
    }
    
    $class_labels = [];
    $class_rentals = [];
    $class_revenues = [];
    
    foreach($popular_classes as $class) {
        $class_labels[] = $class['Class_Name'];
        $class_rentals[] = $class['Total_Rentals'];
        $class_revenues[] = $class['Class_Revenue'];
    }
    
} catch(PDOException $e) {
    $monthly_revenue = [];
    $top_customers = [];
    $vehicle_utilization = [];
    $popular_classes = [];
    $summary_stats = [
        'Total_Customers' => 0, 'Total_Cars' => 0, 'Total_Bookings' => 0,
        'Total_Revenue' => 0, 'Today_Transactions' => 0, 'Today_Revenue' => 0
    ];
    $monthly_labels = [];
    $monthly_revenues = [];
    $monthly_transactions = [];
    $class_labels = [];
    $class_rentals = [];
    $class_revenues = [];
    $error_message = "Database connection failed: " . $e->getMessage();
}

// Helper function
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
?>

<div class="container-fluid py-2">
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <div class="page-header min-height-300 border-radius-xl mt-4" 
                 style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <span class="mask bg-gradient-dark opacity-6"></span>
                <div class="container">
                    <div class="row">
                        <div class="col-lg-7 text-center mx-auto">
                            <h1 class="text-white mb-2 mt-5">Analytics & Reporting Dashboard</h1>
                            <p class="text-white opacity-8 mb-4">Comprehensive business intelligence and performance metrics</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mt-4">
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">Total Revenue</p>
                            <h4 class="mb-0"><?php echo formatRupiah($summary_stats['Total_Revenue']); ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-primary shadow-primary shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">monetization_on</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm">Today: <?php echo formatRupiah($summary_stats['Today_Revenue']); ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">Total Bookings</p>
                            <h4 class="mb-0"><?php echo number_format($summary_stats['Total_Bookings']); ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-success shadow-success shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">event</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm">Today: <?php echo $summary_stats['Today_Transactions']; ?> transactions</p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">Total Customers</p>
                            <h4 class="mb-0"><?php echo number_format($summary_stats['Total_Customers']); ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-info shadow-info shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">people</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm">Active customer base</p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-sm-6">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">Fleet Size</p>
                            <h4 class="mb-0"><?php echo number_format($summary_stats['Total_Cars']); ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-warning shadow-warning shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">directions_car</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm">Available vehicles</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mt-4">
        <div class="col-lg-6 col-md-6 mb-4">
            <div class="card">
                <div class="card-header pb-0 px-3">
                    <h6 class="mb-0">Monthly Revenue Trend</h6>
                    <p class="text-sm mb-0">Revenue performance over the last 12 months</p>
                </div>
                <div class="card-body p-3">
                    <div class="chart">
                        <canvas id="monthly-revenue-chart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 col-md-6 mb-4">
            <div class="card">
                <div class="card-header pb-0 px-3">
                    <h6 class="mb-0">Popular Car Classes</h6>
                    <p class="text-sm mb-0">Rental distribution by vehicle class</p>
                </div>
                <div class="card-body p-3">
                    <div class="chart">
                        <canvas id="car-classes-chart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Tables Row -->
    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header pb-0 px-3">
                    <h6 class="mb-0">Top 10 Customers</h6>
                    <p class="text-sm mb-0">Highest spending customers</p>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Customer</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Bookings</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Total Spent</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Avg Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($top_customers as $index => $customer): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="icon icon-shape icon-sm bg-gradient-<?php echo ['primary', 'success', 'info', 'warning', 'danger'][($index % 5)]; ?> text-center border-radius-md me-2">
                                                    <span class="text-white font-weight-bold"><?php echo $index + 1; ?></span>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($customer['Customer_Name']); ?></h6>
                                                    <p class="text-xs text-muted mb-0"><?php echo htmlspecialchars($customer['Phone_Number']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-gradient-primary badge-sm"><?php echo $customer['Total_Bookings']; ?></span>
                                        </td>
                                        <td>
                                            <span class="text-sm font-weight-bold"><?php echo formatRupiah($customer['Total_Spent']); ?></span>
                                        </td>
                                        <td>
                                            <span class="text-sm"><?php echo formatRupiah($customer['Average_Booking_Value']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header pb-0 px-3">
                    <h6 class="mb-0">Vehicle Utilization Performance</h6>
                    <p class="text-sm mb-0">Top performing vehicles by revenue</p>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Vehicle</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Class</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Rentals</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach(array_slice($vehicle_utilization, 0, 10) as $vehicle): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="icon icon-shape icon-sm bg-gradient-info text-center border-radius-md me-2">
                                                    <i class="material-symbols-rounded text-white opacity-10">directions_car</i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($vehicle['Car_Name']); ?></h6>
                                                    <p class="text-xs text-muted mb-0"><?php echo $vehicle['Car_ID']; ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-gradient-secondary badge-sm"><?php echo htmlspecialchars($vehicle['Class_Name']); ?></span>
                                        </td>
                                        <td>
                                            <span class="text-sm font-weight-bold"><?php echo $vehicle['Rental_Count']; ?></span>
                                        </td>
                                        <td>
                                            <span class="text-sm font-weight-bold"><?php echo formatRupiah($vehicle['Total_Revenue']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Car Classes Analysis -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0 px-3">
                    <h6 class="mb-0">Car Classes Performance Analysis</h6>
                    <p class="text-sm mb-0">Detailed breakdown of vehicle class performance</p>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Class Name</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Fleet Size</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Total Rentals</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Class Revenue</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Avg Daily Rate</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Popularity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($popular_classes as $class): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="icon icon-shape icon-sm bg-gradient-warning text-center border-radius-md me-2">
                                                    <i class="material-symbols-rounded text-white opacity-10">category</i>
                                                </div>
                                                <span class="text-sm font-weight-bold"><?php echo htmlspecialchars($class['Class_Name']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-gradient-primary badge-sm"><?php echo $class['Total_Cars_In_Class']; ?> cars</span>
                                        </td>
                                        <td>
                                            <span class="text-sm font-weight-bold"><?php echo $class['Total_Rentals']; ?></span>
                                        </td>
                                        <td>
                                            <span class="text-sm font-weight-bold"><?php echo formatRupiah($class['Class_Revenue']); ?></span>
                                        </td>
                                        <td>
                                            <span class="text-sm"><?php echo formatRupiah($class['Average_Daily_Rate']); ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="text-sm font-weight-bold me-2"><?php echo $class['Popularity_Percentage']; ?>%</span>
                                                <div class="progress progress-sm w-50">
                                                    <div class="progress-bar bg-gradient-success" 
                                                         style="width: <?php echo $class['Popularity_Percentage']; ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
// Monthly Revenue Chart
const monthlyRevenueCtx = document.getElementById('monthly-revenue-chart').getContext('2d');
new Chart(monthlyRevenueCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($monthly_labels); ?>,
        datasets: [{
            label: 'Monthly Revenue',
            data: <?php echo json_encode($monthly_revenues); ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }, {
            label: 'Transactions',
            data: <?php echo json_encode($monthly_transactions); ?>,
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            borderWidth: 2,
            fill: false,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Revenue (Rp)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Transactions'
                },
                grid: {
                    drawOnChartArea: false,
                },
            }
        },
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Revenue Trend Analysis'
            }
        }
    }
});

// Car Classes Pie Chart
const carClassesCtx = document.getElementById('car-classes-chart').getContext('2d');
new Chart(carClassesCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($class_labels); ?>,
        datasets: [{
            label: 'Rentals by Class',
            data: <?php echo json_encode($class_rentals); ?>,
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 205, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)',
                'rgba(255, 159, 64, 0.8)'
            ],
            borderColor: [
                'rgba(255, 99, 132, 1)',
                'rgba(54, 162, 235, 1)',
                'rgba(255, 205, 86, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(153, 102, 255, 1)',
                'rgba(255, 159, 64, 1)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
            },
            title: {
                display: true,
                text: 'Car Class Popularity'
            }
        }
    }
});
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../app.php';
?>