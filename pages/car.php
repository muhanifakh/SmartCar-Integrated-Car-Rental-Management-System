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
    
    // Simple Query: List all available cars with their class information
    $available_cars_query = "
        SELECT 
            c.Car_ID,
            c.Car_Name,
            c.Car_Type,
            c.Car_Price,
            cc.Class_Name
        FROM Car c
        JOIN Car_Class cc ON c.Car_Class_CC_ID = cc.CC_ID
        ORDER BY cc.Class_Name, c.Car_Price ASC
    ";
    $available_cars = $pdo->query($available_cars_query)->fetchAll();
    
    // Simple Query: Cars by class (grouped summary)
    $cars_by_class_query = "
        SELECT 
            cc.Class_Name,
            COUNT(c.Car_ID) as Total_Cars,
            ROUND(AVG(c.Car_Price), 0) as Average_Price,
            MIN(c.Car_Price) as Min_Price,
            MAX(c.Car_Price) as Max_Price
        FROM Car_Class cc
        LEFT JOIN Car c ON cc.CC_ID = c.Car_Class_CC_ID
        GROUP BY cc.CC_ID, cc.Class_Name
        ORDER BY Total_Cars DESC
    ";
    $cars_by_class = $pdo->query($cars_by_class_query)->fetchAll();
    
    // Complex Query: Car utilization analysis (simplified)
    $car_utilization_query = "
        SELECT 
            c.Car_ID,
            c.Car_Name,
            cc.Class_Name,
            c.Car_Price,
            COUNT(ct.Transaction_Transaction_ID) as Rental_Count,
            COALESCE(SUM(t.Amount), 0) as Total_Revenue,
            CASE 
                WHEN COUNT(ct.Transaction_Transaction_ID) = 0 THEN 'Never Rented'
                WHEN COUNT(ct.Transaction_Transaction_ID) < 3 THEN 'Low Usage'
                WHEN COUNT(ct.Transaction_Transaction_ID) < 8 THEN 'Medium Usage'
                ELSE 'High Usage'
            END as Usage_Status
        FROM Car c
        JOIN Car_Class cc ON c.Car_Class_CC_ID = cc.CC_ID
        LEFT JOIN Car_Transaction ct ON c.Car_ID = ct.Car_Car_ID
        LEFT JOIN Transaction t ON ct.Transaction_Transaction_ID = t.Transaction_ID
        GROUP BY c.Car_ID, c.Car_Name, cc.Class_Name, c.Car_Price
        ORDER BY Total_Revenue DESC
        LIMIT 20
    ";
    $car_utilization = $pdo->query($car_utilization_query)->fetchAll();
    
    // Get fleet statistics for summary cards
    $fleet_stats_query = "
        SELECT 
            COUNT(*) as Total_Cars,
            COUNT(DISTINCT Car_Class_CC_ID) as Total_Classes,
            ROUND(AVG(Car_Price), 0) as Average_Price,
            COUNT(CASE WHEN Car_Price > (SELECT AVG(Car_Price) FROM Car) THEN 1 END) as Premium_Cars
        FROM Car
    ";
    $fleet_stats = $pdo->query($fleet_stats_query)->fetch();
    
    // Get rental statistics
    $rental_stats_query = "
        SELECT 
            COUNT(DISTINCT ct.Car_Car_ID) as Cars_Ever_Rented,
            COUNT(ct.Transaction_Transaction_ID) as Total_Rentals,
            COALESCE(SUM(t.Amount), 0) as Total_Fleet_Revenue
        FROM Car_Transaction ct
        LEFT JOIN Transaction t ON ct.Transaction_Transaction_ID = t.Transaction_ID
    ";
    $rental_stats = $pdo->query($rental_stats_query)->fetch();
    
} catch(PDOException $e) {
    $available_cars = [];
    $cars_by_class = [];
    $car_utilization = [];
    $fleet_stats = ['Total_Cars' => 0, 'Total_Classes' => 0, 'Average_Price' => 0, 'Premium_Cars' => 0];
    $rental_stats = ['Cars_Ever_Rented' => 0, 'Total_Rentals' => 0, 'Total_Fleet_Revenue' => 0];
    $error_message = "Database connection failed: " . $e->getMessage();
}

// Helper functions
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function getUsageBadge($status) {
    switch($status) {
        case 'High Usage': return 'bg-gradient-success';
        case 'Medium Usage': return 'bg-gradient-warning';
        case 'Low Usage': return 'bg-gradient-info';
        case 'Never Rented': return 'bg-gradient-secondary';
        default: return 'bg-gradient-dark';
    }
}
?>

<div class="container-fluid py-2">
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Fleet Overview Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">Total Fleet</p>
                            <h4 class="mb-0"><?php echo $fleet_stats['Total_Cars']; ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-primary shadow-primary shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">directions_car</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm"><?php echo $fleet_stats['Total_Classes']; ?> different classes</p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">Cars Rented</p>
                            <h4 class="mb-0"><?php echo $rental_stats['Cars_Ever_Rented']; ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-success shadow-success shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">trending_up</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm"><?php echo $rental_stats['Total_Rentals']; ?> total rentals</p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">Fleet Revenue</p>
                            <h4 class="mb-0"><?php echo formatRupiah($rental_stats['Total_Fleet_Revenue']); ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-info shadow-info shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">monetization_on</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm">Total earnings from fleet</p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-sm-6">
            <div class="card">
                <div class="card-header p-2 ps-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-sm mb-0 text-capitalize">Avg Daily Rate</p>
                            <h4 class="mb-0"><?php echo formatRupiah($fleet_stats['Average_Price']); ?></h4>
                        </div>
                        <div class="icon icon-md icon-shape bg-gradient-warning shadow-warning shadow text-center border-radius-lg">
                            <i class="material-symbols-rounded opacity-10">payments</i>
                        </div>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                    <p class="mb-0 text-sm"><?php echo $fleet_stats['Premium_Cars']; ?> premium vehicles</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Cars by Class & Top Performers -->
    <div class="row mb-4">
      <div class="col-lg-6">
          <!-- Cars by Class -->
          <div class="card h-100">
              <div class="card-header pb-0 px-3">
                  <h6 class="mb-0">Fleet Distribution by Class</h6>
              </div>
              <div class="card-body p-3 d-flex flex-column">
                  <div class="table-responsive flex-grow-1">
                      <table class="table align-items-center mb-0 table-lg">
                          <thead>
                              <tr style="height: 60px;">
                                  <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7 py-3">Class</th>
                                  <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7 py-3">Count</th>
                                  <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7 py-3">Avg Price</th>
                                  <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7 py-3">Range</th>
                              </tr>
                          </thead>
                          <tbody>
                              <?php foreach($cars_by_class as $class): ?>
                                  <tr style="height: 80px;">
                                      <td class="py-4">
                                          <p class="text-base font-weight-bold mb-0"><?php echo htmlspecialchars($class['Class_Name']); ?></p>
                                      </td>
                                      <td class="py-4">
                                          <span class="badge bg-gradient-primary badge-lg px-3 py-2" style="font-size: 0.875rem;"><?php echo $class['Total_Cars']; ?> CARS</span>
                                      </td>
                                      <td class="py-4">
                                          <span class="text-base font-weight-bold"><?php echo formatRupiah($class['Average_Price']); ?></span>
                                      </td>
                                      <td class="py-4">
                                          <span class="text-sm text-muted"><?php echo formatRupiah($class['Min_Price']); ?> - <?php echo formatRupiah($class['Max_Price']); ?></span>
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
            <!-- Top Performing Cars -->
            <div class="card h-100">
                <div class="card-header pb-0 px-3">
                    <h6 class="mb-0">Top Revenue Generating Cars</h6>
                </div>
                <div class="card-body p-3">
                    <?php foreach(array_slice($car_utilization, 0, 8) as $car): ?>
                        <div class="mb-3 p-2 border-radius-lg bg-gray-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-sm"><?php echo htmlspecialchars($car['Car_Name']); ?></h6>
                                    <p class="text-xs text-muted mb-0"><?php echo htmlspecialchars($car['Class_Name']); ?> • <?php echo formatRupiah($car['Car_Price']); ?>/day</p>
                                </div>
                                <span class="badge <?php echo getUsageBadge($car['Usage_Status']); ?> badge-sm">
                                    <?php echo $car['Usage_Status']; ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <small class="text-muted"><?php echo $car['Rental_Count']; ?> rentals</small>
                                <strong class="text-sm"><?php echo formatRupiah($car['Total_Revenue']); ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- All Cars Table -->
    <div class="row">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-dark shadow-dark border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">Complete Fleet Overview (<?php echo count($available_cars); ?> vehicles)</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <div class="table-responsive p-0">
                        <table class="table align-items-center justify-content-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Car ID</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Vehicle</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Class</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Transmission</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Daily Rate</th>
                                    <th class="text-secondary opacity-7"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($available_cars)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">No cars available</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($available_cars as $car): ?>
                                        <tr>
                                            <td>
                                                <p class="text-sm font-weight-bold mb-0"><?php echo htmlspecialchars($car['Car_ID']); ?></p>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="icon icon-shape icon-sm bg-gradient-primary text-center border-radius-md me-2">
                                                        <i class="material-symbols-rounded text-white opacity-10">directions_car</i>
                                                    </div>
                                                    <span class="text-sm font-weight-bold"><?php echo htmlspecialchars($car['Car_Name']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-gradient-info badge-sm"><?php echo htmlspecialchars($car['Class_Name']); ?></span>
                                            </td>
                                            <td>
                                                <span class="text-sm"><?php echo htmlspecialchars($car['Car_Type']); ?></span>
                                            </td>
                                            <td>
                                                <p class="text-sm font-weight-bold mb-0"><?php echo formatRupiah($car['Car_Price']); ?></p>
                                            </td>
                                            <td class="align-middle">
                                                <button class="btn btn-link text-secondary mb-0" 
                                                        data-bs-toggle="tooltip" 
                                                        data-bs-placement="top" 
                                                        title="Manage Vehicle">
                                                    <i class="fa fa-cog text-xs"></i>
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