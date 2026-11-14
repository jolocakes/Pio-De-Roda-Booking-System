<?php
/**
 * T-26: Resolve SERVICE_ID from the database catalog.
 *
 * The page's service name is matched against the `name` column in
 * `services`, so renaming a service in the admin catalog automatically
 * propagates here. The static fallback keeps the page usable on a fresh
 * clone whose database has not been seeded; it is never used while the
 * catalog is available.
 */
require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Non-Coffee Booth Package';
$SERVICE_ID_DB = 'noncoffee';

try {
    $svc = $pdo->prepare("SELECT id FROM services WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $svc->execute([$pageTitle]);
    $found = $svc->fetchColumn();

    if (!$found) {
        $svc = $pdo->prepare("SELECT id FROM services WHERE LOWER(name) LIKE LOWER(?) LIMIT 1");
        $svc->execute(['%' . $pageTitle . '%']);
        $found = $svc->fetchColumn();
    }

    if ($found) {
        $SERVICE_ID_DB = $found;
    }
} catch (Throwable $e) {
    error_log('Service bootstrap failed for ' . $pageTitle . ': ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Pio De Roda — Non-Coffee Booth Package</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="services.css?v=3.2">
</head>
<body>

    <div class="topbar">
      <div class="brand">Pio De Roda Cafe & Restaurant<small>Non-Coffee Booth Package</small></div>
      <a class="back" href="nav.html" aria-label="Back to home">← Back to Home</a>
    </div>

    <div class="layout">
      <!-- BOOKING PANEL -->
      <div class="panel card" id="bookingPanel"></div>
      <!-- SERVICE DETAILS -->
      <div class="details card" id="serviceDetails"></div>
    </div>

    <!-- REVIEW MODAL -->
    <div class="modal-bg" id="reviewModal">
      <div class="modal">
        <h2>Review Your Reservation</h2>
        <p class="note">Please review your details below. Nothing is submitted yet.</p>
        <div id="reviewBody"></div>
        <div class="chk"><input type="checkbox" id="chkCorrect"><label for="chkCorrect">I confirm that the information provided above is correct.</label></div>
        <div class="chk"><input type="checkbox" id="chkGuidelines"><label for="chkGuidelines">I have read and understood the reservation guidelines.</label></div>
        <button class="btn-main" id="submitBtn" disabled>Submit Reservation</button>
        <button class="btn-ghost" id="editBtn">Edit Reservation</button>
      </div>
    </div>

    <!-- SUCCESS MODAL -->
    <div class="modal-bg" id="successModal">
      <div class="modal" id="successBody"></div>
    </div>
    
    <script src="services.js?v=3.4"></script> 
    <script>
      /* T-26: SERVICE_ID resolved from the database catalog so renaming a
         service in the DB automatically propagates to all pages that reference it.
         The page title "Pio De Roda — Non-Coffee Booth Package" is matched against
         the `name` column in `services`; the corresponding `id` value becomes
         the SERVICE_ID used by reservation2.js. */
      var SERVICE_ID = "<?php echo $SERVICE_ID_DB; ?>";
    </script>
<script src="pdr-dates.js?v=1.0"></script>
<script src="reservation2.js?v=3.4"></script>
</body>
</html>