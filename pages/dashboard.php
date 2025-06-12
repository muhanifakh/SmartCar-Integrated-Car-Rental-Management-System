<?php
ob_start();

// Database connection sesuai dengan setup Anda
$host = '127.0.0.1';
$dbname = 'smart_car';
$username = 'root';
$password = 'Avangarde13';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Using Views - Replace all complex queries with simple view queries
    $dashboard_data = $pdo->query("SELECT * FROM dashboard_stats")->fetch();
    $recent_bookings = $pdo->query("SELECT * FROM recent_bookings_dashboard LIMIT 4")->fetchAll();
    $activities = $pdo->query("SELECT * FROM activity_timeline LIMIT 5")->fetchAll();
    
    // NEW: Chart Data Queries
    
    // 1. Monthly Bookings Chart Data (last 12 months)
    $monthly_bookings_query = "
        SELECT 
            YEAR(Date_Preserved) as year,
            MONTH(Date_Preserved) as month,
            MONTHNAME(Date_Preserved) as month_name,
            COUNT(*) as booking_count
        FROM Booking 
        WHERE Date_Preserved >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
        GROUP BY YEAR(Date_Preserved), MONTH(Date_Preserved), MONTHNAME(Date_Preserved)
        ORDER BY year ASC, month ASC
    ";
    $monthly_bookings_data = $pdo->query($monthly_bookings_query)->fetchAll();
    
    // 2. Revenue Growth Chart Data (last 12 months)
    $revenue_growth_query = "
        SELECT 
            YEAR(Payment_Date) as year,
            MONTH(Payment_Date) as month,
            MONTHNAME(Payment_Date) as month_name,
            SUM(Amount) as monthly_revenue,
            COUNT(*) as transaction_count
        FROM Transaction 
        WHERE Payment_Date >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
        GROUP BY YEAR(Payment_Date), MONTH(Payment_Date), MONTHNAME(Payment_Date)
        ORDER BY year ASC, month ASC
    ";
    $revenue_growth_data = $pdo->query($revenue_growth_query)->fetchAll();
    
    // 3. Car Utilization Chart Data
    $car_utilization_query = "
        SELECT 
            cc.Class_Name,
            COUNT(ct.Car_Car_ID) as rental_count,
            ROUND(
                COUNT(ct.Car_Car_ID) * 100.0 / 
                (SELECT COUNT(*) FROM Car_Transaction), 2
            ) as utilization_percentage
        FROM Car_Class cc
        LEFT JOIN Car c ON cc.CC_ID = c.Car_Class_CC_ID
        LEFT JOIN Car_Transaction ct ON c.Car_ID = ct.Car_Car_ID
        GROUP BY cc.CC_ID, cc.Class_Name
        ORDER BY rental_count DESC
    ";
    $car_utilization_data = $pdo->query($car_utilization_query)->fetchAll();
    
    // Prepare JavaScript arrays for charts
    $monthly_labels = [];
    $monthly_booking_counts = [];
    
    foreach($monthly_bookings_data as $month) {
        $monthly_labels[] = substr($month['month_name'], 0, 3); // Short month names
        $monthly_booking_counts[] = intval($month['booking_count']);
    }
    
    $revenue_labels = [];
    $revenue_amounts = [];
    
    foreach($revenue_growth_data as $month) {
        $revenue_labels[] = substr($month['month_name'], 0, 3);
        $revenue_amounts[] = floatval($month['monthly_revenue']);
    }
    
    $utilization_labels = [];
    $utilization_percentages = [];
    
    foreach($car_utilization_data as $class) {
        $utilization_labels[] = $class['Class_Name'];
        $utilization_percentages[] = floatval($class['utilization_percentage']);
    }
    
    // Get data for dropdowns
    $cars_list = $pdo->query("SELECT Car_ID, Car_Name, Car_Price FROM Car ORDER BY Car_Name")->fetchAll();
    $car_classes = $pdo->query("SELECT CC_ID, Class_Name FROM Car_Class ORDER BY Class_Name")->fetchAll();
    $customers_list = $pdo->query("SELECT ID_Card, Name, Phone_Number FROM Customer ORDER BY Name")->fetchAll();
    
} catch(PDOException $e) {
    // Fallback values if database fails
    $dashboard_data = [
        'today_revenue' => 0, 'today_transactions' => 0, 'active_rentals' => 0,
        'total_cars' => 0, 'available_cars' => 0, 'total_customers' => 0, 'utilization_rate' => 0.00
    ];
    $recent_bookings = [];
    $activities = [];
    $cars_list = [];
    $car_classes = [];
    
    // Fallback chart data
    $monthly_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
    $monthly_booking_counts = [0, 0, 0, 0, 0, 0];
    $revenue_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
    $revenue_amounts = [0, 0, 0, 0, 0, 0];
    $utilization_labels = ['SUV', 'Sedan', 'MPV'];
    $utilization_percentages = [0, 0, 0];
    
    $error_message = "Database connection failed: " . $e->getMessage();
}

// Helper function
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Check for success/error messages from form submissions
$success_message = $_GET['success'] ?? '';
$error_message_form = $_GET['error'] ?? '';
?>

<!-- Car Rental Dashboard Content -->
<div class="container-fluid py-2">
  <!-- Success/Error Messages -->
  <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <strong>Success!</strong> <?php echo htmlspecialchars($success_message); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  
  <?php if ($error_message_form): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <strong>Error!</strong> <?php echo htmlspecialchars($error_message_form); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if (isset($error_message)): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
  <?php endif; ?>

  <div class="row">
    <div class="ms-3">
      <h3 class="mb-0 h4 font-weight-bolder">Car Rental Dashboard</h3>
      <p class="mb-4">
        Monitor your car rental business performance and operations.
      </p>
    </div>
    
    <!-- Statistics Cards Row -->
    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
      <div class="card">
        <div class="card-header p-2 ps-3">
          <div class="d-flex justify-content-between">
            <div>
              <p class="text-sm mb-0 text-capitalize">Today's Revenue</p>
              <h4 class="mb-0"><?php echo formatRupiah($dashboard_data['today_revenue']); ?></h4>
            </div>
            <div class="icon icon-md icon-shape bg-gradient-success shadow-success shadow text-center border-radius-lg">
              <i class="material-symbols-rounded opacity-10">payments</i>
            </div>
          </div>
        </div>
        <hr class="dark horizontal my-0">
        <div class="card-footer p-2 ps-3">
          <p class="mb-0 text-sm"><?php echo $dashboard_data['today_transactions']; ?> transactions today</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
      <div class="card">
        <div class="card-header p-2 ps-3">
          <div class="d-flex justify-content-between">
            <div>
              <p class="text-sm mb-0 text-capitalize">Active Rentals</p>
              <h4 class="mb-0"><?php echo $dashboard_data['active_rentals']; ?></h4>
            </div>
            <div class="icon icon-md icon-shape bg-gradient-info shadow-info shadow text-center border-radius-lg">
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
              <p class="text-sm mb-0 text-capitalize">Available Cars</p>
              <h4 class="mb-0"><?php echo $dashboard_data['available_cars']; ?></h4>
            </div>
            <div class="icon icon-md icon-shape bg-gradient-warning shadow-warning shadow text-center border-radius-lg">
              <i class="material-symbols-rounded opacity-10">garage</i>
            </div>
          </div>
        </div>
        <hr class="dark horizontal my-0">
        <div class="card-footer p-2 ps-3">
          <p class="mb-0 text-sm">Out of <?php echo $dashboard_data['total_cars']; ?> total cars</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card">
        <div class="card-header p-2 ps-3">
          <div class="d-flex justify-content-between">
            <div>
              <p class="text-sm mb-0 text-capitalize">Total Customers</p>
              <h4 class="mb-0"><?php echo number_format($dashboard_data['total_customers']); ?></h4>
            </div>
            <div class="icon icon-md icon-shape bg-gradient-primary shadow-primary shadow text-center border-radius-lg">
              <i class="material-symbols-rounded opacity-10">people</i>
            </div>
          </div>
        </div>
        <hr class="dark horizontal my-0">
        <div class="card-footer p-2 ps-3">
          <p class="mb-0 text-sm"><?php echo $dashboard_data['utilization_rate']; ?>% fleet utilization</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts Row -->
  <div class="row mt-4">
    <div class="col-lg-4 col-md-6 mt-4 mb-4">
      <div class="card">
        <div class="card-body">
          <h6 class="mb-0">Monthly Bookings</h6>
          <p class="text-sm">Booking trends over the last year</p>
          <div class="pe-2">
            <div class="chart">
              <canvas id="chart-bars" class="chart-canvas" height="170"></canvas>
            </div>
          </div>
          <hr class="dark horizontal">
          <div class="d-flex">
            <i class="material-symbols-rounded text-sm my-auto me-1">schedule</i>
            <p class="mb-0 text-sm">updated in real-time</p>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4 col-md-6 mt-4 mb-4">
      <div class="card">
        <div class="card-body">
          <h6 class="mb-0">Revenue Growth</h6>
          <p class="text-sm">Monthly revenue performance</p>
          <div class="pe-2">
            <div class="chart">
              <canvas id="chart-line" class="chart-canvas" height="170"></canvas>
            </div>
          </div>
          <hr class="dark horizontal">
          <div class="d-flex">
            <i class="material-symbols-rounded text-sm my-auto me-1">schedule</i>
            <p class="mb-0 text-sm">updated in real-time</p>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4 mt-4 mb-3">
      <div class="card">
        <div class="card-body">
          <h6 class="mb-0">Car Class Utilization</h6>
          <p class="text-sm">Fleet performance by car class</p>
          <div class="pe-2">
            <div class="chart">
              <canvas id="chart-line-tasks" class="chart-canvas" height="170"></canvas>
            </div>
          </div>
          <hr class="dark horizontal">
          <div class="d-flex">
            <i class="material-symbols-rounded text-sm my-auto me-1">schedule</i>
            <p class="mb-0 text-sm">real-time data</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tables Row -->
  <div class="row mb-4">
    <!-- Recent Bookings Table -->
    <div class="col-lg-8 col-md-6 mb-md-0 mb-4">
      <div class="card">
        <div class="card-header pb-0">
          <div class="row">
            <div class="col-lg-6 col-7">
              <h6>Recent Bookings</h6>
              <p class="text-sm mb-0">
                <i class="fa fa-check text-info" aria-hidden="true"></i>
                <span class="font-weight-bold ms-1"><?php echo count($recent_bookings); ?> recent</span> bookings
              </p>
            </div>
            <div class="col-lg-6 col-5 my-auto text-end">
              <div class="dropdown float-lg-end pe-4">
                <a class="cursor-pointer" id="dropdownTable" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="fa fa-ellipsis-v text-secondary"></i>
                </a>
                <ul class="dropdown-menu px-2 py-3 ms-sm-n4 ms-n5" aria-labelledby="dropdownTable">
                  <li><a class="dropdown-item border-radius-md" href="javascript:;">View All</a></li>
                  <li><a class="dropdown-item border-radius-md" href="javascript:;">Export</a></li>
                  <li><a class="dropdown-item border-radius-md" href="javascript:;">Filter</a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>
        <div class="card-body px-0 pb-2">
          <div class="table-responsive">
            <table class="table align-items-center mb-0">
              <thead>
                <tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Customer</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Car</th>
                  <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Duration</th>
                  <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                  <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Total</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recent_bookings)): ?>
                  <tr>
                    <td colspan="5" class="text-center py-4">No recent bookings available</td>
                  </tr>
                <?php else: ?>
                  <?php foreach($recent_bookings as $index => $booking): ?>
                    <tr>
                      <td>
                        <div class="d-flex px-2 py-1">
                          <div>
                            <img src="../assets/img/team-<?php echo ($index % 4) + 1; ?>.jpg" class="avatar avatar-sm me-3" alt="user">
                          </div>
                          <div class="d-flex flex-column justify-content-center">
                            <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($booking['customer_name'] ?? 'Unknown'); ?></h6>
                            <p class="text-xs text-secondary mb-0"><?php echo htmlspecialchars($booking['customer_phone'] ?? 'N/A'); ?></p>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="d-flex flex-column justify-content-center">
                          <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($booking['car_name'] ?? 'No car assigned'); ?></h6>
                          <p class="text-xs text-secondary mb-0"><?php echo htmlspecialchars($booking['Booking_ID']); ?></p>
                        </div>
                      </td>
                      <td class="align-middle text-center text-sm">
                        <span class="text-xs font-weight-bold"><?php echo $booking['duration_days']; ?> days</span>
                      </td>
                      <td class="align-middle text-center">
                        <?php 
                          $statusClass = '';
                          switch($booking['status']) {
                            case 'Active': $statusClass = 'bg-gradient-success'; break;
                            case 'Upcoming': $statusClass = 'bg-gradient-info'; break;
                            case 'Completed': $statusClass = 'bg-gradient-secondary'; break;
                            default: $statusClass = 'bg-gradient-dark'; break;
                          }
                        ?>
                        <span class="badge badge-sm <?php echo $statusClass; ?>"><?php echo $booking['status']; ?></span>
                      </td>
                      <td class="align-middle text-center">
                        <span class="text-secondary text-xs font-weight-bold"><?php echo formatRupiah($booking['total_amount'] ?? 0); ?></span>
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

    <!-- Quick Actions & Alerts -->
    <div class="col-lg-4 col-md-6">
      <div class="card h-100">
        <div class="card-header pb-0">
          <h6>Quick Actions</h6>
          <p class="text-sm">
            <i class="fa fa-clock text-warning" aria-hidden="true"></i>
            <span class="font-weight-bold">5 pending</span> maintenance alerts
          </p>
        </div>
        <div class="card-body p-3">
          <!-- Quick Action Buttons -->
          <div class="row mb-3">
            <div class="col-6 mb-2">
              <button class="btn btn-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#newBookingModal">
                <i class="material-symbols-rounded me-1">add</i>
                New Booking
              </button>
            </div>
            <div class="col-6 mb-2">
              <button class="btn btn-success btn-sm w-100" data-bs-toggle="modal" data-bs-target="#addCarModal">
                <i class="material-symbols-rounded me-1">directions_car</i>
                Add Car
              </button>
            </div>
            <div class="col-6 mb-2">
              <button class="btn btn-info btn-sm w-100" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                <i class="material-symbols-rounded me-1">person_add</i>
                New Customer
              </button>
            </div>
            <div class="col-6 mb-2">
              <button class="btn btn-warning btn-sm w-100">
                <i class="material-symbols-rounded me-1">build</i>
                Maintenance
              </button>
            </div>
          </div>

          <hr class="dark horizontal">

          <!-- Recent Activities Timeline -->
          <div class="timeline timeline-one-side">
            <?php if (empty($activities)): ?>
              <div class="timeline-block mb-3">
                <span class="timeline-step">
                  <i class="material-symbols-rounded text-secondary text-gradient">info</i>
                </span>
                <div class="timeline-content">
                  <h6 class="text-dark text-sm font-weight-bold mb-0">No recent activities</h6>
                  <p class="text-secondary font-weight-bold text-xs mt-1 mb-0">System just started</p>
                </div>
              </div>
            <?php else: ?>
              <?php foreach($activities as $activity): ?>
                <div class="timeline-block mb-3">
                    <span class="timeline-step">
                        <?php 
                          $iconClass = '';
                          switch($activity['activity_status']) {
                            case 'success': $iconClass = 'text-success'; break;
                            case 'info': $iconClass = 'text-info'; break;
                            case 'primary': $iconClass = 'text-primary'; break;
                            case 'warning': $iconClass = 'text-warning'; break;
                            default: $iconClass = 'text-secondary'; break;
                          }
                        ?>
                        <i class="material-symbols-rounded <?php echo $iconClass; ?> text-gradient">
                          <?php 
                            switch($activity['activity_type']) {
                              case 'booking_created': echo 'check_circle'; break;
                              case 'payment_received': echo 'payments'; break;
                              case 'car_added': echo 'directions_car'; break;
                              case 'customer_added': echo 'person_add'; break;
                              default: echo 'info'; break;
                            }
                          ?>
                        </i>
                    </span>
                    <div class="timeline-content">
                        <h6 class="text-dark text-sm font-weight-bold mb-0"><?php echo htmlspecialchars($activity['activity_message']); ?></h6>
                        <p class="text-secondary font-weight-bold text-xs mt-1 mb-0">
                          <?php echo date('M d, H:i', strtotime($activity['activity_time'])); ?>
                        </p>
                    </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODALS START HERE -->

<!-- Enhanced New Booking Modal -->
<div class="modal fade" id="newBookingModal" tabindex="-1" aria-labelledby="newBookingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="newBookingModalLabel">
          <i class="material-symbols-rounded me-2">add</i>Create New Booking
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="handlers/add_booking.php">
        <div class="modal-body">
          <div class="row">
            <!-- Customer Search -->
            <div class="col-md-6">
              <div class="input-group input-group-outline mb-3" style="position: relative;">
                <label class="form-label">Customer Name</label>
                <input type="text" class="form-control" name="customer_search" id="customerSearch"
                      oninput="filterCustomers()" onfocus="showDropdown()" onblur="hideDropdown()">
                <input type="hidden" name="customer_selection" id="customerSelection">
                
                <!-- Dropdown for customer suggestions -->
                <div id="customerDropdown" style="
                  display: none; 
                  position: absolute; 
                  top: 100%; 
                  left: 0; 
                  right: 0; 
                  z-index: 1050; 
                  max-height: 200px; 
                  overflow-y: auto; 
                  background: white; 
                  border: 1px solid #ddd; 
                  border-radius: 8px; 
                  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                  margin-top: 2px;">
                  
                  <div onclick="selectNewCustomer()" style="
                    cursor: pointer; 
                    padding: 10px 15px; 
                    background-color: #f8f9fa; 
                    font-weight: bold; 
                    border-bottom: 1px solid #eee;">
                    <i class="material-symbols-rounded me-2">person_add</i>+ Add New Customer
                  </div>
                  
                  <?php foreach($customers_list as $customer): ?>
                    <div class="customer-option" 
                        data-id="<?php echo htmlspecialchars($customer['ID_Card']); ?>"
                        data-name="<?php echo htmlspecialchars($customer['Name']); ?>"
                        data-phone="<?php echo htmlspecialchars($customer['Phone_Number']); ?>"
                        onclick="selectCustomer(this)" 
                        style="
                          cursor: pointer; 
                          padding: 10px 15px; 
                          border-bottom: 1px solid #f0f0f0;"
                        onmouseover="this.style.backgroundColor='#f8f9fa'" 
                        onmouseout="this.style.backgroundColor='white'">
                      <?php echo htmlspecialchars($customer['Name']) . ' - ' . htmlspecialchars($customer['Phone_Number']); ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- Car Selection -->
            <div class="col-md-6">
              <div class="input-group input-group-outline mb-3">
                <select class="form-control" name="car_id" required>
                  <option value="">Select Car</option>
                  <?php foreach($cars_list as $car): ?>
                    <option value="<?php echo $car['Car_ID']; ?>">
                      <?php echo htmlspecialchars($car['Car_Name']) . ' - ' . formatRupiah($car['Car_Price']) . '/day'; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- New Customer Fields (Hidden by default) -->
            <div id="newCustomerFields" style="display: none;" class="col-12">
              <div class="row">
                <div class="col-md-6">
                  <div class="input-group input-group-outline mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" name="customer_phone" id="customerPhone">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="input-group input-group-outline mb-3">
                    <label class="form-label">ID Card Number</label>
                    <input type="text" class="form-control" name="id_card" id="idCard" maxlength="16">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="input-group input-group-outline mb-3">
                    <label class="form-label">Driver License Number</label>
                    <input type="text" class="form-control" name="license_number" id="licenseNumber">
                  </div>
                </div>
                <div class="col-12">
                  <div class="input-group input-group-outline mb-3">
                    <label class="form-label">Address</label>
                    <input type="text" class="form-control" name="address" id="address" rows="2">
                  </div>
                </div>
              </div>
            </div>

            <!-- Booking Dates -->
            <div class="col-md-6">
              <label class="form-label mb-1">Start Date</label>
              <div class="input-group input-group-outline mb-3">
                <input type="date" class="form-control" name="start_date" required>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label mb-1">End Date</label>
              <div class="input-group input-group-outline mb-3">
                <input type="date" class="form-control" name="end_date" required>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Booking</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Car Modal -->
<div class="modal fade" id="addCarModal" tabindex="-1" aria-labelledby="addCarModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addCarModalLabel">
          <i class="material-symbols-rounded me-2">directions_car</i>Add New Car
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="handlers/add_car.php">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="input-group input-group-outline mb-3">
                <label class="form-label">Brand</label>
                <input type="text" class="form-control" name="brand" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="input-group input-group-outline mb-3">
                <label class="form-label">Model</label>
                <input type="text" class="form-control" name="model" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="input-group input-group-outline mb-3">
                <label class="form-label">Year</label>
                <input type="number" class="form-control" name="year" min="1990" max="2025" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="input-group input-group-outline mb-3">
                <select class="form-control" name="car_class_id" required>
                  <option value="">Select Car Class</option>
                  <?php foreach($car_classes as $class): ?>
                    <option value="<?php echo $class['CC_ID']; ?>">
                      <?php echo htmlspecialchars($class['Class_Name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="input-group input-group-outline mb-3">
                <select class="form-control" name="car_type" required>
                  <option value="">Select Transmission</option>
                  <option value="Manual">Manual</option>
                  <option value="Automatic">Automatic</option>
                  <option value="CVT">CVT</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="input-group input-group-outline mb-3">
                <label class="form-label">Daily Rate</label>
                <input type="number" class="form-control" name="daily_rate" step="0.01" required>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Add Car</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
let selectedCustomer = null;

function filterCustomers() {
    const searchTerm = document.getElementById('customerSearch').value.toLowerCase();
    const dropdown = document.getElementById('customerDropdown');
    const options = dropdown.querySelectorAll('.customer-option');
    let hasVisibleOptions = false;
    
    // Reset selection when typing
    selectedCustomer = null;
    document.getElementById('customerSelection').value = '';
    
    options.forEach(option => {
        const customerName = option.getAttribute('data-name').toLowerCase();
        if (customerName.includes(searchTerm)) {
            option.style.display = 'block';
            hasVisibleOptions = true;
        } else {
            option.style.display = 'none';
        }
    });
    
    // Always show dropdown when user is typing
    dropdown.style.display = 'block';
    
    // If no matches found, show new customer fields
    if (!hasVisibleOptions && searchTerm.length > 0) {
        toggleCustomerFields(true);
    } else if (hasVisibleOptions) {
        toggleCustomerFields(false);
    }
}

function showDropdown() {
    document.getElementById('customerDropdown').style.display = 'block';
}

function hideDropdown() {
    setTimeout(() => {
        document.getElementById('customerDropdown').style.display = 'none';
    }, 300);
}

function selectCustomer(element) {
    const customerName = element.getAttribute('data-name');
    const customerId = element.getAttribute('data-id');
    
    document.getElementById('customerSearch').value = customerName;
    document.getElementById('customerSelection').value = customerId;
    document.getElementById('customerDropdown').style.display = 'none';
    
    selectedCustomer = customerId;
    toggleCustomerFields(false);
}

function selectNewCustomer() {
    const searchValue = document.getElementById('customerSearch').value;
    
    document.getElementById('customerSelection').value = 'new_customer';
    document.getElementById('customerDropdown').style.display = 'none';
    
    selectedCustomer = 'new_customer';
    toggleCustomerFields(true);
}

function toggleCustomerFields(showFields) {
    const newCustomerFields = document.getElementById('newCustomerFields');
    const fields = ['customerPhone', 'idCard', 'licenseNumber', 'address'];
    
    if (showFields) {
        newCustomerFields.style.display = 'block';
        fields.forEach(fieldId => {
            document.getElementById(fieldId).required = true;
        });
    } else {
        newCustomerFields.style.display = 'none';
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            field.required = false;
            if (selectedCustomer !== 'new_customer') {
                field.value = '';
            }
        });
    }
}

// Dynamic Chart Scripts - Replace the static chart data with dynamic data
document.addEventListener('DOMContentLoaded', function() {
    // 1. Monthly Bookings Bar Chart
    var ctx = document.getElementById("chart-bars");
    if(ctx) {
        ctx = ctx.getContext("2d");
        new Chart(ctx, {
            type: "bar",
            data: {
                labels: <?php echo json_encode($monthly_labels); ?>,
                datasets: [{
                    label: "Monthly Bookings",
                    tension: 0.4,
                    borderWidth: 0,
                    borderRadius: 4,
                    borderSkipped: false,
                    backgroundColor: "#43A047",
                    data: <?php echo json_encode($monthly_booking_counts); ?>,
                    barThickness: 'flex'
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                scales: {
                    y: {
                        grid: {
                            drawBorder: false,
                            display: true,
                            drawOnChartArea: true,
                            drawTicks: false,
                            borderDash: [5, 5],
                            color: '#e5e5e5'
                        },
                        ticks: {
                            suggestedMin: 0,
                            beginAtZero: true,
                            padding: 10,
                            font: {
                                size: 14,
                                lineHeight: 2
                            },
                            color: "#737373"
                        },
                    },
                    x: {
                        grid: {
                            drawBorder: false,
                            display: false,
                            drawOnChartArea: false,
                            drawTicks: false,
                            borderDash: [5, 5]
                        },
                        ticks: {
                            display: true,
                            color: '#737373',
                            padding: 10,
                            font: {
                                size: 14,
                                lineHeight: 2
                            },
                        }
                    },
                },
            },
        });
    }

    // 2. Revenue Growth Line Chart
    var ctx2 = document.getElementById("chart-line");
    if(ctx2) {
        ctx2 = ctx2.getContext("2d");
        new Chart(ctx2, {
            type: "line",
            data: {
                labels: <?php echo json_encode($revenue_labels); ?>,
                datasets: [{
                    label: "Monthly Revenue",
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: "#43A047",
                    pointBorderColor: "transparent",
                    borderColor: "#43A047",
                    backgroundColor: "rgba(67, 160, 71, 0.1)",
                    fill: true,
                    data: <?php echo json_encode($revenue_amounts); ?>,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: Rp ' + context.parsed.y.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                scales: {
                    y: {
                        grid: {
                            drawBorder: false,
                            display: true,
                            drawOnChartArea: true,
                            drawTicks: false,
                            borderDash: [4, 4],
                            color: '#e5e5e5'
                        },
                        ticks: {
                            display: true,
                            color: '#737373',
                            padding: 10,
                            font: {
                                size: 12,
                                lineHeight: 2
                            },
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    },
                    x: {
                        grid: {
                            drawBorder: false,
                            display: false,
                            drawOnChartArea: false,
                            drawTicks: false,
                            borderDash: [5, 5]
                        },
                        ticks: {
                            display: true,
                            color: '#737373',
                            padding: 10,
                            font: {
                                size: 12,
                                lineHeight: 2
                            },
                        }
                    },
                },
            },
        });
    }

    // 3. Car Class Utilization Chart
    var ctx3 = document.getElementById("chart-line-tasks");
    if(ctx3) {
        ctx3 = ctx3.getContext("2d");
        new Chart(ctx3, {
            type: "doughnut",
            data: {
                labels: <?php echo json_encode($utilization_labels); ?>,
                datasets: [{
                    label: "Utilization %",
                    data: <?php echo json_encode($utilization_percentages); ?>,
                    backgroundColor: [
                        "#43A047",
                        "#FFA726",
                        "#EF5350",
                        "#42A5F5",
                        "#AB47BC",
                        "#26A69A"
                    ],
                    borderWidth: 2,
                    borderColor: "#fff"
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + '%';
                            }
                        }
                    }
                },
                cutout: '60%',
            },
        });
    }
});
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../app.php';
?>