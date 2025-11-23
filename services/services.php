<?php
include '../reusable/db_setup.php';
$services = getAllServices(); // Fetch services from DB
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include '../reusable/head.php'; ?>
  <title>Our Services - Glamour Salon</title>
  <link rel="stylesheet" href="/assets/css/services.css">
</head>

<body>
  <?php include '../reusable/header.php'; ?>

  <div class="container mt-5 pt-5">
    <header class="text-center py-5">
      <h1 class="display-4">Our Services</h1>
      <p class="lead">Experience luxury and transformation with our premium salon services</p>
    </header>

    <main>
      <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($services as $service): ?>
          <?php
          // Assign icons based on service name (simple example)
          $icon = 'fa-spa'; // Default icon
          if (stripos($service['name'], 'Haircut') !== false || stripos($service['name'], 'Style') !== false) $icon = 'fa-scissors';
          if (stripos($service['name'], 'Coloring') !== false) $icon = 'fa-palette';
          if (stripos($service['name'], 'Facial') !== false) $icon = 'fa-face-smile-beam';
          if (stripos($service['name'], 'Manicure') !== false || stripos($service['name'], 'Pedicure') !== false) $icon = 'fa-hand-sparkles';
          if (stripos($service['name'], 'Extension') !== false) $icon = 'fa-user-plus'; // Example icon
          ?>
          <div class="col">
            <a href="../booking/booking.php?service_id=<?= $service['id'] ?>" class="card h-100 text-center service-card shadow-sm">
              <div class="card-body">
                <i class="fas <?= $icon ?> service-icon"></i>
                <h5 class="card-title"><?= htmlspecialchars($service['name']) ?></h5>
                <p class="card-text"><?= htmlspecialchars($service['description']) ?></p>
              </div>
              <div class="card-footer bg-transparent border-top-0">
                <span class="fw-bold fs-5 text-primary">SAR <?= number_format($service['price'], 2) ?></span>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </main>
  </div>

  <?php include '../reusable/footer.php'; ?>
  <?php include '../reusable/scripts.php'; ?>
</body>

</html>