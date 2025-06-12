<?php
ob_start();

// Database connection
$host = '127.0.0.1';
$dbname = 'smart_car'; // Update with your database name
$username = 'root';
$password = 'Avangarde13'; // Update with your password if needed

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Simple Query: Get all transactions with related data
    $transactions_query = "
        SELECT 
            t.Transaction_ID,
            t.Payment_Method,
            t.Amount,
            t.Payment_Date,
            t.Staff_Staff_ID,
            t.Booking_Booking_ID,
            c.Name as Customer_Name,
            s.Name as Staff_Name
        FROM Transaction t
        LEFT JOIN Booking b ON t.Booking_Booking_ID = b.Booking_ID
        LEFT JOIN Customer c ON b.Customer_ID_Card = c.ID_Card
        LEFT JOIN Staff s ON t.Staff_Staff_ID = s.Staff_ID
        ORDER BY t.Payment_Date DESC
        LIMIT 50
    ";
    $transactions_data = $pdo->query($transactions_query)->fetchAll();
    
    // Simple Query: Monthly revenue total
    $monthly_revenue_query = "
        SELECT 
            YEAR(Payment_Date) as Year,
            MONTH(Payment_Date) as Month,
            MONTHNAME(Payment_Date) as Month_Name,
            COUNT(*) as Total_Transactions,
            SUM(Amount) as Total_Revenue
        FROM Transaction
        WHERE Payment_Date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
        GROUP BY YEAR(Payment_Date), MONTH(Payment_Date), MONTHNAME(Payment_Date)
        ORDER BY YEAR(Payment_Date) DESC, MONTH(Payment_Date) DESC
    ";
    $monthly_revenue = $pdo->query($monthly_revenue_query)->fetchAll();
    
    // Complex Query: Payment method analysis and trends
    $payment_analysis_query = "
        SELECT 
            t.Payment_Method,
            COUNT(t.Transaction_ID) as Transaction_Count,
            SUM(t.Amount) as Total_Revenue,
            ROUND(AVG(t.Amount), 2) as Average_Transaction_Value,
            MIN(t.Amount) as Minimum_Transaction,
            MAX(t.Amount) as Maximum_Transaction,
            ROUND(
                COUNT(t.Transaction_ID) * 100.0 / 
                (SELECT COUNT(*) FROM Transaction), 2
            ) as Usage_Percentage,
            ROUND(
                SUM(t.Amount) * 100.0 / 
                (SELECT SUM(Amount) FROM Transaction), 2
            ) as Revenue_Percentage,
            COUNT(DISTINCT b.Customer_ID_Card) as Unique_Customers
        FROM Transaction t
        JOIN Booking b ON t.Booking_Booking_ID = b.Booking_ID
        GROUP BY t.Payment_Method
        ORDER BY Total_Revenue DESC
    ";
    $payment_analysis = $pdo->query($payment_analysis_query)->fetchAll();
    
    // Get today's stats for summary cards
    $today_stats_query = "
        SELECT 
            COUNT(*) as Today_Transactions,
            COALESCE(SUM(Amount), 0) as Today_Revenue,
            COALESCE(AVG(Amount), 0) as Today_Average
        FROM Transaction 
        WHERE DATE(Payment_Date) = CURRENT_DATE
    ";
    $today_stats = $pdo->query($today_stats_query)->fetch();
    
} catch(PDOException $e) {
    $transactions_data = [];
    $monthly_revenue = [];
    $payment_analysis = [];
    $today_stats = ['Today_Transactions' => 0, 'Today_Revenue' => 0, 'Today_Average' => 0];
    $error_message = "Database connection failed: " . $e->getMessage();
}

// Helper function to format currency
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
?>

<div class="container-fluid py-2">
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Summary Cards Row -->
    <div class="row mb-4">
        <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">Today's Transactions</p>
                            <h4 class="mb-0"><?php echo $today_stats['Today_Transactions']; ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-primary shadow-primary shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">receipt</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm">Updated in real-time</p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">Today's Revenue</p>
                            <h4 class="mb-0"><?php echo formatRupiah($today_stats['Today_Revenue']); ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-success shadow-success shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">monetization_on</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm">Average: <?php echo formatRupiah($today_stats['Today_Average']); ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 col-sm-6">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">Total Transactions</p>
                            <h4 class="mb-0"><?php echo count($transactions_data); ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-info shadow-info shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">analytics</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm">Last 50 transactions shown</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Method Analysis Row -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <!-- Monthly Revenue Chart -->
            <div class="card">
                <div class="card-header pb-0 px-3">
                    <h6 class="mb-0">Monthly Revenue Trend</h6>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Month</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Year</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Transactions</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($monthly_revenue as $month): ?>
                                    <tr>
                                        <td>
                                            <p class="text-sm font-weight-bold mb-0"><?php echo htmlspecialchars($month['Month_Name']); ?></p>
                                        </td>
                                        <td>
                                            <span class="text-sm"><?php echo $month['Year']; ?></span>
                                        </td>
                                        <td>
                                            <span class="text-sm"><?php echo $month['Total_Transactions']; ?></span>
                                        </td>
                                        <td>
                                            <span class="text-sm font-weight-bold"><?php echo formatRupiah($month['Total_Revenue']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Payment Method Analysis -->
            <div class="card h-100">
                <div class="card-header pb-0 px-3">
                    <h6 class="mb-0">Payment Method Analysis</h6>
                </div>
                <div class="card-body p-3">
                    <?php foreach($payment_analysis as $payment): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="text-sm font-weight-bold"><?php echo htmlspecialchars($payment['Payment_Method']); ?></span>
                                <span class="text-sm"><?php echo $payment['Usage_Percentage']; ?>%</span>
                            </div>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-gradient-primary" role="progressbar" 
                                     style="width: <?php echo $payment['Usage_Percentage']; ?>%" 
                                     aria-valuenow="<?php echo $payment['Usage_Percentage']; ?>" 
                                     aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted"><?php echo $payment['Transaction_Count']; ?> transactions</small>
                                <small class="text-muted"><?php echo formatRupiah($payment['Total_Revenue']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transactions Table -->
    <div class="row">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-dark shadow-dark border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">Recent Transactions (<?php echo count($transactions_data); ?> records)</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <div class="table-responsive p-0">
                        <table class="table align-items-center justify-content-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Transaction ID</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Customer</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Payment Method</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Amount</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Payment Date</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Staff</th>
                                    <th class="text-secondary opacity-7"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions_data)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">No transaction data available</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($transactions_data as $transaction): ?>
                                        <tr>
                                            <td>
                                                <p class="text-sm font-weight-bold mb-0"><?php echo htmlspecialchars($transaction['Transaction_ID']); ?></p>
                                            </td>
                                            <td>
                                                <span class="text-sm"><?php echo htmlspecialchars($transaction['Customer_Name'] ?? 'N/A'); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge badge-sm bg-gradient-info"><?php echo htmlspecialchars($transaction['Payment_Method']); ?></span>
                                            </td>
                                            <td>
                                                <p class="text-sm font-weight-bold mb-0"><?php echo formatRupiah($transaction['Amount']); ?></p>
                                            </td>
                                            <td>
                                                <span class="text-sm"><?php echo date('Y-m-d H:i', strtotime($transaction['Payment_Date'])); ?></span>
                                            </td>
                                            <td>
                                                <span class="text-sm"><?php echo htmlspecialchars($transaction['Staff_Name'] ?? $transaction['Staff_Staff_ID']); ?></span>
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

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../app.php';
?>