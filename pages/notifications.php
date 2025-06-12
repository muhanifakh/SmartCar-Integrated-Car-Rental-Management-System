<?php
ob_start();
$host = '127.0.0.1';
$dbname = 'smart_car';
$username = 'root';
$password = 'Avangarde13';

try {
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->query("UPDATE Notification SET Is_Read = 1 WHERE Is_Read = 0");
    // Ambil 50 notifikasi terbaru
    $notif_query = "SELECT * FROM Notification ORDER BY Created_At DESC LIMIT 50";
    $notifications = $pdo->query($notif_query)->fetchAll();
} catch(PDOException $e) {
    $notifications = [];
}
?>
<div class="container-fluid py-2">
  <div class="row">
    <div class="col-lg-8 col-md-10 mx-auto">
      <div class="card mt-4">
        <div class="card-header p-3">
          <h5 class="mb-0">Notifications</h5>
        </div>
        <div class="card-body p-3 pb-0">
          <?php if(empty($notifications)): ?>
            <div class="alert alert-secondary">No notifications yet.</div>
          <?php else: ?>
            <?php foreach($notifications as $notif): ?>
              <div class="alert alert-<?php echo htmlspecialchars($notif['Type']); ?> alert-dismissible text-white" role="alert">
                <span class="text-sm">
                  <?php
                  if($notif['Link']) {
                      echo '<a href="' . htmlspecialchars($notif['Link']) . '" class="alert-link text-white">' . htmlspecialchars($notif['Message']) . '</a>';
                  } else {
                      echo htmlspecialchars($notif['Message']);
                  }
                  ?>
                  <br>
                  <small class="text-light"><?php echo date("d M Y H:i", strtotime($notif['Created_At'])); ?></small>
                </span>
                <button type="button" class="btn-close text-lg py-3 opacity-10" data-bs-dismiss="alert" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../app.php';
?>
