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
    
    // Simple Query: Get all staff data
    $staff_query = "SELECT Staff_ID, Name, Contact, Position FROM Staff ORDER BY Name";
    $staff_data = $pdo->query($staff_query)->fetchAll();
    
    // DYNAMIC PAGINATION - Replace static values with database calculations
    $customers_per_page = 100;
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    
    // Get real customer count from database
    $count_query = "SELECT COUNT(*) FROM Customer";
    $total_customers = $pdo->query($count_query)->fetchColumn();
    
    // Calculate actual pages needed
    $total_pages = ceil($total_customers / $customers_per_page);
    
    // Use real page count for boundary checking
    $current_page = max(1, min($total_pages, $current_page));
    
    // Calculate offset
    $offset = ($current_page - 1) * $customers_per_page;
    
    // Calculate range info
    $start_record = $offset + 1;
    $end_record = min($offset + $customers_per_page, $total_customers);
    
    // Get customers for current page
    $customer_query = "
        SELECT ID_Card, Name, Phone_Number, Address, Drivers_License
        FROM Customer 
        ORDER BY Name 
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($customer_query);
    $stmt->bindValue(':limit', $customers_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $customer_data = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $staff_data = [];
    $customer_data = [];
    $total_customers = 0;
    $total_pages = 1;
    $current_page = 1;
    $start_record = 0;
    $end_record = 0;
    $error_message = "Database connection failed: " . $e->getMessage();
}

?>
<style>
.input-bordered-group {
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 14px 16px 10px 16px;
  margin-bottom: 18px;
  background: #fff;
}
</style>

<!-- All HTML content here -->
<div class="container-fluid py-2">
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_GET['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_GET['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Staff Table -->
    <div class="row">
        <div class="col-12">
            <div class="px-4 pt-3">
                <input type="text" id="searchStaff" class="form-control mb-3" placeholder="Search staff ">
            </div>
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-dark shadow-dark border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">Staff Table (<?php echo count($staff_data); ?> Records)</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0" id="staffTable">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Staff</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Position</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Contact</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Staff ID</th>
                                    <th class="text-secondary opacity-7"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($staff_data)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No staff data available</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($staff_data as $staff): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex px-2 py-1">
                                                    <div class="d-flex flex-column justify-content-center">
                                                        <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($staff['Name']); ?></h6>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">
                                                    <?php echo htmlspecialchars($staff['Position']); ?>
                                                </p>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="text-xs text-secondary font-weight-bold">
                                                    <?php echo htmlspecialchars($staff['Contact']); ?>
                                                </span>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="text-secondary text-xs font-weight-bold">
                                                    <?php echo htmlspecialchars($staff['Staff_ID']); ?>
                                                </span>
                                            </td>
                                            <td class="align-middle">
                                                <a href="#" 
                                                    class="text-secondary font-weight-bold text-xs edit-staff-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#staffEditModal"
                                                    data-id="<?php echo htmlspecialchars($staff['Staff_ID']); ?>"
                                                    data-name="<?php echo htmlspecialchars($staff['Name']); ?>"
                                                    data-position="<?php echo htmlspecialchars($staff['Position']); ?>"
                                                    data-contact="<?php echo htmlspecialchars($staff['Contact']); ?>">
                                                    Edit
                                                </a>
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

    <!-- Customers Table with Dynamic Pagination -->
    <div class="row">
        <div class="col-12">
            <div class="px-4 pt-3">
                <input type="text" id="searchCustomer" class="form-control mb-3" placeholder="Search customer...">
            </div>
            <div id="customerPagination" class="d-flex justify-content-end mb-2"></div>
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-dark shadow-dark border-radius-lg pt-4 pb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="text-white text-capitalize ps-3 mb-0">
                                Customers Table (<?php echo $total_customers; ?> Total Records)
                            </h6>
                            <div class="text-white pe-3">
                                <small>Showing <?php echo $start_record; ?>-<?php echo $end_record; ?> of <?php echo $total_customers; ?> | Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0" id="customerTable">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Customer</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Phone</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ID Card</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Driver License</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Address</th>
                                    <th class="text-secondary opacity-7"></th>
                                </tr>
                            </thead>
                            <tbody id="customerTableBody">
                                <?php if (empty($customer_data)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No customer data available</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($customer_data as $customer): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex px-2 py-1">
                                                    <div class="d-flex flex-column justify-content-center">
                                                        <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($customer['Name']); ?></h6>
                                                        <p class="text-xs text-secondary mb-0">Customer</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">
                                                    <?php echo htmlspecialchars($customer['Phone_Number']); ?>
                                                </p>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="text-xs text-secondary font-weight-bold">
                                                    <?php echo htmlspecialchars($customer['ID_Card']); ?>
                                                </span>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="text-xs text-secondary font-weight-bold">
                                                    <?php echo htmlspecialchars($customer['Drivers_License']); ?>
                                                </span>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="text-xs text-secondary font-weight-bold" 
                                                      style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block;"
                                                      title="<?php echo htmlspecialchars($customer['Address']); ?>">
                                                    <?php echo htmlspecialchars(substr($customer['Address'], 0, 30)) . (strlen($customer['Address']) > 30 ? '...' : ''); ?>
                                                </span>
                                            </td>
                                            <td class="align-middle">
                                                <a href="#" 
                                                    class="text-secondary font-weight-bold text-xs edit-customer-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#customerEditModal"
                                                    data-id="<?php echo htmlspecialchars($customer['ID_Card']); ?>"
                                                    data-name="<?php echo htmlspecialchars($customer['Name']); ?>"
                                                    data-phone="<?php echo htmlspecialchars($customer['Phone_Number']); ?>"
                                                    data-address="<?php echo htmlspecialchars($customer['Address']); ?>"
                                                    data-dl="<?php echo htmlspecialchars($customer['Drivers_License']); ?>">
                                                    Edit
                                                </a>
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

    <!-- Modal for Update/Delete Customer -->
    <div class="modal fade" id="customerEditModal" tabindex="-1" aria-labelledby="customerEditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="customerEditForm" method="POST" action="/pages/handlers/edit_customer.php">
        <div class="modal-content">
            <div class="modal-header">
            <h5 class="modal-title" id="customerEditModalLabel">Edit Customer</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
            <!-- Hidden ID -->
            <input type="hidden" name="ID_Card" id="modalCustomerID">

            <div class="input-bordered-group">
                <label for="modalCustomerName" class="form-label">Name</label>
                <input type="text" class="form-control" name="Name" id="modalCustomerName" required>
                <div class="form-text text-muted" id="prevCustomerName"></div>
            </div>
            <div class="input-bordered-group">
                <label for="modalCustomerPhone" class="form-label">Phone</label>
                <input type="text" class="form-control" name="Phone_Number" id="modalCustomerPhone" required>
                <div class="form-text text-muted" id="prevCustomerPhone"></div>
            </div>
            <div class="input-bordered-group">
                <label for="modalCustomerAddress" class="form-label">Address</label>
                <input type="text" class="form-control" name="Address" id="modalCustomerAddress" required>
                <div class="form-text text-muted" id="prevCustomerAddress"></div>
            </div>
            <div class="input-bordered-group">
                <label for="modalCustomerDL" class="form-label">Driver License</label>
                <input type="text" class="form-control" name="Drivers_License" id="modalCustomerDL" required>
                <div class="form-text text-muted" id="prevCustomerDL"></div>
            </div>
            </div>
            <div class="modal-footer">
            <button type="submit" name="action" value="update" class="btn btn-success">Update</button>
            <button type="submit" name="action" value="delete" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
        </form>
    </div>
    </div>

    <!-- Modal for Update/Delete Staff -->
    <div class="modal fade" id="staffEditModal" tabindex="-1" aria-labelledby="staffEditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="staffEditForm" method="POST" action="/pages/handlers/edit_staff.php">
        <div class="modal-content">
            <div class="modal-header">
            <h5 class="modal-title" id="staffEditModalLabel">Edit Staff</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">

            <div class="input-bordered-group">
                <label for="modalStaffName" class="form-label mb-1">Name</label>
                <input type="text" class="form-control mb-1" name="Name" id="modalStaffName" required>
                <div class="form-text text-muted" id="prevStaffName"></div>
            </div>
            <div class="input-bordered-group">
                <label for="modalStaffPosition" class="form-label mb-1">Position</label>
                <input type="text" class="form-control mb-1" name="Position" id="modalStaffPosition" required>
                <div class="form-text text-muted" id="prevStaffPosition"></div>
            </div>
            <div class="input-bordered-group">
                <label for="modalStaffContact" class="form-label mb-1">Contact</label>
                <input type="text" class="form-control mb-1" name="Contact" id="modalStaffContact" required>
                <div class="form-text text-muted" id="prevStaffContact"></div>
            </div>
            <input type="hidden" name="Staff_ID" id="modalStaffID">
            </div>
            <div class="modal-footer">
            <button type="submit" name="action" value="update" class="btn btn-success">Update</button>
            <button type="submit" name="action" value="delete" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
        </form>
    </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var editBtns = document.querySelectorAll('.edit-customer-btn');
        editBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                // Current value
                document.getElementById('modalCustomerID').value = btn.getAttribute('data-id');
                document.getElementById('modalCustomerName').value = btn.getAttribute('data-name');
                document.getElementById('modalCustomerPhone').value = btn.getAttribute('data-phone');
                document.getElementById('modalCustomerAddress').value = btn.getAttribute('data-address');
                document.getElementById('modalCustomerDL').value = btn.getAttribute('data-dl');
                // Show "bayangan"
                document.getElementById('prevCustomerName').textContent = "Previous: " + btn.getAttribute('data-name');
                document.getElementById('prevCustomerPhone').textContent = "Previous: " + btn.getAttribute('data-phone');
                document.getElementById('prevCustomerAddress').textContent = "Previous: " + btn.getAttribute('data-address');
                document.getElementById('prevCustomerDL').textContent = "Previous: " + btn.getAttribute('data-dl');
            });
        });

        // Hilangkan history/bayangan saat input berubah (opsional)
        document.getElementById('customerEditForm').addEventListener('input', function(e){
            let id = e.target.id;
            if(id === 'modalCustomerName') document.getElementById('prevCustomerName').textContent = '';
            if(id === 'modalCustomerPhone') document.getElementById('prevCustomerPhone').textContent = '';
            if(id === 'modalCustomerAddress') document.getElementById('prevCustomerAddress').textContent = '';
            if(id === 'modalCustomerDL') document.getElementById('prevCustomerDL').textContent = '';
        });
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var editBtns = document.querySelectorAll('.edit-staff-btn');
        editBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('modalStaffID').value = btn.getAttribute('data-id');
                document.getElementById('modalStaffName').value = btn.getAttribute('data-name');
                document.getElementById('modalStaffPosition').value = btn.getAttribute('data-position');
                document.getElementById('modalStaffContact').value = btn.getAttribute('data-contact');
                // Show "bayangan" previous data
                document.getElementById('prevStaffName').textContent = "Previous: " + btn.getAttribute('data-name');
                document.getElementById('prevStaffPosition').textContent = "Previous: " + btn.getAttribute('data-position');
                document.getElementById('prevStaffContact').textContent = "Previous: " + btn.getAttribute('data-contact');
            });
        });

        // Remove previous data on input change
        document.getElementById('staffEditForm').addEventListener('input', function(e){
            let id = e.target.id;
            if(id === 'modalStaffName') document.getElementById('prevStaffName').textContent = '';
            if(id === 'modalStaffPosition') document.getElementById('prevStaffPosition').textContent = '';
            if(id === 'modalStaffContact') document.getElementById('prevStaffContact').textContent = '';
        });
    });
    </script>

    <script>
    // STAFF SEARCH
    document.addEventListener('DOMContentLoaded', function() {
        const staffInput = document.getElementById('searchStaff');
        if (staffInput) {
            staffInput.addEventListener('keyup', function() {
            let val = staffInput.value.toLowerCase();
            document.querySelectorAll('table#staffTable tbody tr').forEach(function(row) {
                let rowText = row.textContent.toLowerCase();
                row.style.display = rowText.includes(val) ? '' : 'none';
            });
            });
        }
        // CUSTOMER SEARCH
        const customerInput = document.getElementById('searchCustomer');
        if (customerInput) {
            customerInput.addEventListener('keyup', function() {
            let val = customerInput.value.toLowerCase();
            document.querySelectorAll('table#customerTable tbody tr').forEach(function(row) {
                let rowText = row.textContent.toLowerCase();
                row.style.display = rowText.includes(val) ? '' : 'none';
            });
            });
        }
    });
    </script>
 
    <script>
    setTimeout(function(){
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(a){ a.classList.remove('show'); });
    }, 4000); // hilang setelah 4 detik
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchCustomer');
    const tableBody = document.getElementById('customerTableBody');
    const paginationDiv = document.getElementById('customerPagination');

    let currentQuery = '';
    let currentPage = 1;
    const perPage = 100;

    function renderRows(customers) {
        if (customers.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="6" class="text-center">No customer data found</td></tr>`;
        return;
        }
        let rows = '';
        customers.forEach(customer => {
        rows += `
            <tr>
            <td>
                <div class="d-flex px-2 py-1">
                <div class="d-flex flex-column justify-content-center">
                    <h6 class="mb-0 text-sm">${customer.Name}</h6>
                    <p class="text-xs text-secondary mb-0">Customer</p>
                </div>
                </div>
            </td>
            <td>
                <p class="text-xs font-weight-bold mb-0">${customer.Phone_Number}</p>
            </td>
            <td class="align-middle text-center">
                <span class="text-xs text-secondary font-weight-bold">${customer.ID_Card}</span>
            </td>
            <td class="align-middle text-center">
                <span class="text-xs text-secondary font-weight-bold">${customer.Drivers_License}</span>
            </td>
            <td class="align-middle text-center">
                <span class="text-xs text-secondary font-weight-bold"
                    style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block;"
                    title="${customer.Address}">
                ${customer.Address.length > 30 ? customer.Address.substr(0,30) + '...' : customer.Address}
                </span>
            </td>
            <td class="align-middle">
                <a href="#"
                class="text-secondary font-weight-bold text-xs edit-customer-btn"
                data-bs-toggle="modal"
                data-bs-target="#customerEditModal"
                data-id="${customer.ID_Card}"
                data-name="${customer.Name.replace(/"/g,'&quot;')}"
                data-phone="${customer.Phone_Number.replace(/"/g,'&quot;')}"
                data-address="${customer.Address.replace(/"/g,'&quot;')}"
                data-dl="${customer.Drivers_License.replace(/"/g,'&quot;')}">
                Edit
                </a>
            </td>
            </tr>
        `;
        });
        tableBody.innerHTML = rows;

        // Re-register event for new edit buttons!
        document.querySelectorAll('.edit-customer-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('modalCustomerID').value = btn.getAttribute('data-id');
            document.getElementById('modalCustomerName').value = btn.getAttribute('data-name');
            document.getElementById('modalCustomerPhone').value = btn.getAttribute('data-phone');
            document.getElementById('modalCustomerAddress').value = btn.getAttribute('data-address');
            document.getElementById('modalCustomerDL').value = btn.getAttribute('data-dl');
            document.getElementById('prevCustomerName').textContent = "Previous: " + btn.getAttribute('data-name');
            document.getElementById('prevCustomerPhone').textContent = "Previous: " + btn.getAttribute('data-phone');
            document.getElementById('prevCustomerAddress').textContent = "Previous: " + btn.getAttribute('data-address');
            document.getElementById('prevCustomerDL').textContent = "Previous: " + btn.getAttribute('data-dl');
        });
        });
    }

    function renderPagination(totalPages, page) {
        let html = `<nav aria-label="Customer pagination"><ul class="pagination pagination-sm mb-0">`;
        for (let i = 1; i <= totalPages; i++) {
        html += `<li class="page-item${i === page ? ' active' : ''}">
            <a class="page-link" href="#" data-page="${i}">${i}</a>
        </li>`;
        }
        html += `</ul></nav>`;
        paginationDiv.innerHTML = html;

        // Register click event
        paginationDiv.querySelectorAll('.page-link').forEach(link => {
        link.addEventListener('click', function(e){
            e.preventDefault();
            fetchCustomers(currentQuery, parseInt(this.getAttribute('data-page')));
        });
        });
    }

    function fetchCustomers(query = '', page = 1) {
        fetch(`/pages/handlers/search_customers.php?q=${encodeURIComponent(query)}&page=${page}&per_page=${perPage}`)
        .then(resp => resp.json())
        .then(result => {
            renderRows(result.data);
            renderPagination(result.total_pages, result.page);
            // update global state
            currentQuery = query;
            currentPage = result.page;
        })
        .catch(err => {
            tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Error loading data</td></tr>`;
            paginationDiv.innerHTML = '';
        });
    }

    // Initial load
    fetchCustomers();

    // Event: search live
    searchInput.addEventListener('input', function() {
        fetchCustomers(this.value, 1);
    });
    });
    </script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../app.php';
?>