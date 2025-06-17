<?php
// Cek jika sudah include database connection, skip ini
$host = '127.0.0.1';
$dbname = 'smart_car';
$username = 'root';
$password = 'Avangarde13';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // CHART DATA QUERIES - Only run if we're on dashboard page
    $is_dashboard = strpos($_SERVER['REQUEST_URI'], 'dashboard.php') !== false;
    
    if ($is_dashboard) {
        // Monthly Bookings Chart Data (last 12 months)
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
        
        // Revenue Growth Chart Data (last 12 months)
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
        
        // Car Utilization Chart Data
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
    } else {
        // Fallback for non-dashboard pages
        $monthly_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'];
        $monthly_booking_counts = [50, 45, 22, 28, 50, 60, 76];
        $revenue_labels = ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'];
        $revenue_amounts = [120, 230, 130, 440, 250, 360, 270, 180, 90, 300, 310, 220];
        $utilization_labels = ['SUV', 'Sedan', 'MPV'];
        $utilization_percentages = [30, 40, 30];
    }
    
} catch (Exception $e) {
    $unread_count = 0; // fallback
    // Fallback chart data
    $monthly_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'];
    $monthly_booking_counts = [50, 45, 22, 28, 50, 60, 76];
    $revenue_labels = ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'];
    $revenue_amounts = [120, 230, 130, 440, 250, 360, 270, 180, 90, 300, 310, 220];
    $utilization_labels = ['SUV', 'Sedan', 'MPV'];
    $utilization_percentages = [30, 40, 30];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
  <link rel="icon" type="image/png" href="../assets/img/favicon.png">
  <title>
    SmartCar Dashboard
  </title>
  <!--     Fonts and icons     -->
  <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
  <!-- Nucleo Icons -->
  <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
  <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
  <!-- Font Awesome Icons -->
  <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
  <!-- Material Icons -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
  <!-- CSS Files -->
  <link id="pagestyle" href="../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />
</head>

<body class="g-sidenav-show  bg-gray-100">
  <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2  bg-white my-2" id="sidenav-main">
    <div class="sidenav-header">
      <i class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
      <a class="navbar-brand px-4 py-3 m-0" href=" https://demos.creative-tim.com/material-dashboard/pages/dashboard " target="_blank">
        <img src="../assets/img/logo-ct-dark.png" class="navbar-brand-img" width="26" height="26" alt="main_logo">
        <span class="ms-1 text-sm text-dark">The kriwuls</span>
      </a>
    </div>
    <hr class="horizontal dark mt-0 mb-2">
    <div class="collapse navbar-collapse  w-auto " id="sidenav-collapse-main">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link text-dark" href="../pages/dashboard.php">
            <i class="material-symbols-rounded opacity-5">dashboard</i>
            <span class="nav-link-text ms-1">Dashboard</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-dark" href="../pages/users.php">
            <i class="material-symbols-rounded opacity-5">person</i>
            <span class="nav-link-text ms-1">Users</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-dark" href="/pages/booking.php">
            <i class="material-symbols-rounded opacity-5">table_view</i>
            <span class="nav-link-text ms-1">Bookings</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-dark" href="../pages/transactions.php">
            <i class="material-symbols-rounded opacity-5">table_view</i>
            <span class="nav-link-text ms-1">Transactions</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-dark" href="../pages/reporting.php">
            <i class="material-symbols-rounded opacity-5">receipt_long</i>
            <span class="nav-link-text ms-1">Reporting</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-dark" href="../pages/car.php">
            <i class="material-symbols-rounded opacity-5">directions_car</i>
            <span class="nav-link-text ms-1">Cars</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-dark" href="../pages/car_catalog.php">
            <i class="material-symbols-rounded opacity-5">directions_car</i>
            <span class="nav-link-text ms-1">Cars Catalog</span>
          </a>
        </li>
      </ul>
    </div>
    <div class="sidenav-footer position-absolute w-100 bottom-0 ">
      <div class="mx-3">
        <a class="btn bg-gradient-dark w-100" href="coba" type="button">SmartCar Integrated System</a>
      </div>
    </div>
  </aside>
  <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg ">
    <!-- Navbar -->
    <nav class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl" id="navbarBlur" data-scroll="true">
      <div class="container-fluid py-1 px-3">
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
            <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="javascript:;">Pages</a></li>
            <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Dashboard</li>
          </ol>
        </nav>
      </div>
    </nav>
    <!-- End Navbar -->

    <!-- ==== KONTEN HALAMAN MASUK SINI ==== -->
    <?php if (isset($pageContent)) echo $pageContent; ?>  

    <div class="fixed-plugin">
    <a class="fixed-plugin-button text-dark position-fixed px-3 py-2">
      <i class="material-symbols-rounded py-2">settings</i>
    </a>
    <div class="card shadow-lg">
      <div class="card-header pb-0 pt-3">
        <div class="float-start">
          <h5 class="mt-3 mb-0">Material UI Configurator</h5>
          <p>See our dashboard options.</p>
        </div>
        <div class="float-end mt-4">
          <button class="btn btn-link text-dark p-0 fixed-plugin-close-button">
            <i class="material-symbols-rounded">clear</i>
          </button>
        </div>
        <!-- End Toggle Button -->
      </div>
      <hr class="horizontal dark my-1">
      <div class="card-body pt-sm-3 pt-0">
        <!-- Sidebar Backgrounds -->
        <!-- Sidenav Type -->
        <div class="mt-3">
          <h6 class="mb-0">Sidenav Type</h6>
          <p class="text-sm">Choose between different sidenav types.</p>
        </div>
        <div class="d-flex">
          <button class="btn bg-gradient-dark px-3 mb-2" data-class="bg-gradient-dark" onclick="sidebarType(this)">Dark</button>
          <button class="btn bg-gradient-dark px-3 mb-2 ms-2" data-class="bg-transparent" onclick="sidebarType(this)">Transparent</button>
          <button class="btn bg-gradient-dark px-3 mb-2  active ms-2" data-class="bg-white" onclick="sidebarType(this)">White</button>
        </div>
        <p class="text-sm d-xl-none d-block mt-2">You can change the sidenav type just on desktop view.</p>
        <!-- Navbar Fixed -->
        <div class="mt-3 d-flex">
          <h6 class="mb-0">Navbar Fixed</h6>
          <div class="form-check form-switch ps-0 ms-auto my-auto">
            <input class="form-check-input mt-1 ms-auto" type="checkbox" id="navbarFixed" onclick="navbarFixed(this)">
          </div>
        </div>
        <hr class="horizontal dark my-3">
        <div class="mt-2 d-flex">
          <h6 class="mb-0">Light / Dark</h6>
          <div class="form-check form-switch ps-0 ms-auto my-auto">
            <input class="form-check-input mt-1 ms-auto" type="checkbox" id="dark-version" onclick="darkMode(this)">
          </div>
        </div>
        <hr class="horizontal dark my-sm-4">
      </div>
    </div>
  </div>
  <!--   Core JS Files   -->
  <script src="../assets/js/core/popper.min.js"></script>
  <script src="../assets/js/core/bootstrap.min.js"></script>
  <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
  <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
  <script src="../assets/js/plugins/chartjs.min.js"></script>
  <script>
    // DYNAMIC CHARTS - Uses real data when on dashboard, demo data otherwise
    var ctx = document.getElementById("chart-bars")?.getContext("2d");

    if(ctx) {
      new Chart(ctx, {
        type: "bar",
        data: {
          labels: <?php echo json_encode($monthly_labels); ?>,
          datasets: [{
            label: "<?php echo $is_dashboard ? 'Monthly Bookings' : 'Demo Views'; ?>",
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

    var ctx2 = document.getElementById("chart-line")?.getContext("2d");
    if(ctx2) {
      new Chart(ctx2, {
        type: "line",
        data: {
          labels: <?php echo json_encode($revenue_labels); ?>,
          datasets: [{
            label: "<?php echo $is_dashboard ? 'Monthly Revenue' : 'Demo Sales'; ?>",
            tension: 0.4,
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: "#43A047",
            pointBorderColor: "transparent",
            borderColor: "#43A047",
            backgroundColor: "rgba(67, 160, 71, 0.1)",
            fill: true,
            data: <?php echo json_encode($revenue_amounts); ?>,
            maxBarThickness: 6
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
                <?php if ($is_dashboard): ?>
                label: function(context) {
                  return 'Revenue: Rp ' + context.parsed.y.toLocaleString('id-ID');
                }
                <?php else: ?>
                title: function(context) {
                  const fullMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                  return fullMonths[context[0].dataIndex];
                }
                <?php endif; ?>
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
                <?php if ($is_dashboard): ?>
                callback: function(value) {
                  return 'Rp ' + value.toLocaleString('id-ID');
                }
                <?php endif; ?>
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

    var ctx3 = document.getElementById("chart-line-tasks")?.getContext("2d");
    if(ctx3) {
      new Chart(ctx3, {
        type: "<?php echo $is_dashboard ? 'doughnut' : 'line'; ?>",
        data: {
          labels: <?php echo json_encode($utilization_labels); ?>,
          datasets: [{
            label: "<?php echo $is_dashboard ? 'Utilization %' : 'Demo Tasks'; ?>",
            <?php if ($is_dashboard): ?>
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
            <?php else: ?>
            tension: 0,
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: "#43A047",
            pointBorderColor: "transparent",
            borderColor: "#43A047",
            backgroundColor: "transparent",
            fill: true,
            data: <?php echo json_encode($utilization_percentages); ?>,
            maxBarThickness: 6
            <?php endif; ?>
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              <?php if ($is_dashboard): ?>
              position: 'bottom',
              labels: {
                padding: 20,
                usePointStyle: true,
                font: {
                  size: 12
                }
              }
              <?php else: ?>
              display: false,
              <?php endif; ?>
            },
            tooltip: {
              callbacks: {
                <?php if ($is_dashboard): ?>
                label: function(context) {
                  return context.label + ': ' + context.parsed + '%';
                }
                <?php endif; ?>
              }
            }
          },
          <?php if ($is_dashboard): ?>
          cutout: '60%',
          <?php else: ?>
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
                padding: 10,
                color: '#737373',
                font: {
                  size: 14,
                  lineHeight: 2
                },
              }
            },
            x: {
              grid: {
                drawBorder: false,
                display: false,
                drawOnChartArea: false,
                drawTicks: false,
                borderDash: [4, 4]
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
          <?php endif; ?>
        },
      });
    }
  </script>
  <script>
    var win = navigator.platform.indexOf('Win') > -1;
    if (win && document.querySelector('#sidenav-scrollbar')) {
      var options = {
        damping: '0.5'
      }
      Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
    }
  </script>
  <!-- Github buttons -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>
  <!-- Control Center for Material Dashboard: parallax effects, scripts for the example pages etc -->
  <script src="../assets/js/material-dashboard.min.js?v=3.2.0"></script>
</body>

</html>