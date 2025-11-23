<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>
<nav class="navbar navbar-expand-lg glamour-navbar">
  <div class="container">
    <a class="navbar-brand" href="/index.php">
      <div class="brand-container">
        <div class="brand-icon">
          <i class="fas fa-spa fa-lg text-white"></i>
        </div>
        <span class="brand-text">Glamour Salon</span>
      </div>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="/index.php">
            <i class="fas fa-home nav-icon"></i>Home
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'services') !== false ? 'active' : ''; ?>" href="/services/services.php">
            <i class="fas fa-scissors nav-icon"></i>Services
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link book-now-btn <?php echo strpos($_SERVER['PHP_SELF'], 'booking') !== false ? 'active' : ''; ?>" href="/booking/booking.php">
            <i class="far fa-calendar-check nav-icon"></i>Book Now
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'faq') !== false ? 'active' : ''; ?>" href="/faq/faq.php">
            <i class="fas fa-question-circle nav-icon"></i>FAQ
          </a>
        </li>

        <!-- Authentication section -->
        <?php if (isset($_SESSION['user_id']) && isset($_SESSION['username'])): ?>
          <li class="nav-item dropdown">
            <!-- changed: anchor -> button, reliable Bootstrap toggle -->
            <button
              class="nav-link dropdown-toggle no-arrow d-flex align-items-center bg-transparent border-0"
              id="userDropdown"
              type="button"
              data-bs-toggle="dropdown"
              data-bs-auto-close="outside"
              aria-expanded="false"
              style="cursor:pointer; pointer-events:auto;">
              <i class="fas fa-user-circle nav-icon me-1"></i>
              Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>!
            </button>
            <ul class="dropdown-menu dropdown-menu-end custom-dropdown" aria-labelledby="userDropdown">
              <li><a class="dropdown-item" href="/login/profile.php"><i class="fas fa-id-card me-2"></i>My Profile</a></li>
              <?php if ($_SESSION['role'] == 'admin'): ?>
                <li><a class="dropdown-item" href="/admin/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="/login/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sign Out</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link" href="/login/login.php">
              <i class="fas fa-sign-in-alt nav-icon"></i>Login
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<style>
/* Keep arrow hidden, ensure clickability and styling */
.no-arrow::after { display: none !important; }
.custom-dropdown { border: none !important; box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
#userDropdown { z-index: 2; pointer-events: auto; }
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Ensure Bootstrap dropdown is initialized; add fallback if bundle missing
(function() {
  const trigger = document.getElementById('userDropdown');
  if (!trigger) return;

  try {
    if (window.bootstrap && bootstrap.Dropdown) {
      new bootstrap.Dropdown(trigger);
    }
  } catch (e) {}

  // Fallback toggle if Bootstrap JS not bound for any reason
  trigger.addEventListener('click', function(e) {
    const menu = document.querySelector('[aria-labelledby="userDropdown"]');
    if (!window.bootstrap || !bootstrap.Dropdown) {
      e.preventDefault();
      e.stopPropagation();
      menu.classList.toggle('show');
      document.addEventListener('click', function onDocClick(ev) {
        if (!menu.contains(ev.target) && ev.target !== trigger) {
          menu.classList.remove('show');
          document.removeEventListener('click', onDocClick);
        }
      });
    }
  });
})();
</script>