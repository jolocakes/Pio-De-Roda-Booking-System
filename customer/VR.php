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

$pageTitle = 'Venue Rentals';
$SERVICE_ID_DB = 'venue';

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
<title>Pio De Roda — Venue Rentals</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="nav.css?v=5">
<link rel="stylesheet" href="services.css?v=3.4">
</head>
<body data-service-id="<?php echo htmlspecialchars($SERVICE_ID_DB, ENT_QUOTES, 'UTF-8'); ?>">

  <header class="site-header" id="site-header">
    <nav class="navbar">
      <a href="nav.html#home" class="brand">
        <img src="pioderodalogo.jpg" alt="Pio De Roda Cafe and Restaurant logo" class="brand-mark" decoding="async" />
        <span class="brand-name">Pio De Roda Cafe<small>Restaurant &amp; Catering Services</small></span>
      </a>

      <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false" aria-controls="navLinks">
        <span></span><span></span><span></span>
      </button>

      <ul class="nav-links" id="navLinks">
        <li><a href="nav.html#home" class="nav-link">Home</a></li>
        <li><a href="nav.html#about" class="nav-link">About Us</a></li>
        <li><a href="nav.html#availability" class="nav-link">Availability</a></li>
        <li><a href="nav.html#guide" class="nav-link">Guide</a></li>
        <li><a href="nav.html#contact" class="nav-link">Contact</a></li>
        <li><a href="booking-status.html" class="nav-link">Booking Status</a></li>
      </ul>
    </nav>
  </header>
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
    
  <footer class="site-footer">
    <div class="footer-grid">
      <div class="footer-brand">
        <img src="pioderodalogo.jpg" alt="Pio De Roda Cafe and Restaurant logo" class="brand-mark" loading="lazy" decoding="async" />
        <div>
          <p class="footer-title">Pio De Roda Cafe</p>
          <p class="footer-tagline">Catering, celebrations, and events made simple.</p>
        </div>
      </div>
      <ul class="footer-links">
        <li><a href="nav.html#home">Home</a></li>
        <li><a href="nav.html#about">About Us</a></li>
        <li><a href="nav.html#contact">Contact</a></li>
        <li><a href="nav.html#guide">Guide</a></li>
        <li><a href="booking-status.html">Booking Status</a></li>
        <li><a href="privacy.html">Privacy Policy</a></li>
        <li><a href="terms.html">Terms of Service</a></li>
        <li><a href="cancellation.html">Cancellation &amp; Refund Policy</a></li>
      </ul>
    </div>
    <p class="footer-copy">&copy; 2026 Pio De Roda Cafe. All Rights Reserved.</p>
  </footer>
    <script src="nav.js?v=1.1"></script>
    <script src="services.js?v=3.5"></script>
<script src="pdr-dates.js?v=1.0"></script>
<script src="reservation2.js?v=3.7"></script>
</body>
</html>