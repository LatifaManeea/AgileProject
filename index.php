<?php require_once './reusable/db_setup.php';?>

<html lang="en">
<head>
    <title>Glamour Salon - Home</title>
    <?php include './reusable/head.php'; ?>
</head>

<body>
    <?php include './reusable/header.php'; ?>
    <header>
        <h1>Welcome to Glamour Salon</h1>
        <p>Your beauty, our passion. Book your glam session today!</p>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content container">
            <h1 class="hero-title">Beauty Unleashed</h1>
            <p class="hero-subtitle">Your journey to beauty excellence begins here</p>
            <a href="./booking/booking.php" class="btn btn-custom hero-btn">Book Your Appointment</a>
        </div>
    </section>

    <!-- Welcome Section -->
    <section>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="section-title">Welcome to Glamour</h2>
                    <p class="lead">At Glamour Salon, we believe in bringing out your natural beauty through our premium services and expert care.</p>
                    <p>With our team of professional stylists and estheticians, we provide personalized treatments tailored to your unique style and preferences. Our commitment to excellence is reflected in every service we offer.</p>
                    <div class="mt-4">
                        <a href="./services/services.php" class="btn btn-custom me-3">Our Services</a>
                    </div>
                </div>
                <div class="col-lg-6 mt-5 mt-lg-0">
                    <div class="position-relative">
                        <img src="/assets/images/salon.jpeg" alt="Salon Interior" class="img-fluid rounded-lg shadow-lg">
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- Why Choose Us -->
    <section class="bg-white py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Why Choose Us</h2>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="text-center p-4">
                        <div class="display-4 text-primary mb-4 float"><i class="fas fa-award"></i></div>
                        <h4>Expert Stylists</h4>
                        <p>Our team consists of experienced, passionate professionals who stay updated with the latest trends and techniques.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center p-4">
                        <div class="display-4 mb-4 float gold"><i class="fas fa-star"></i></div>
                        <h4>Premium Products</h4>
                        <p>We use only high-quality, professional-grade products to ensure the best results for your hair and skin.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center p-4">
                        <div class="display-4  mb-4 float red"><i class="fas fa-heart"></i></div>
                        <h4>Personal Attention</h4>
                        <p>Every client receives personalized care tailored to their unique needs and preferences.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 offset-lg-2 text-center">
                    <h2 class="mb-4">Ready to Experience the Glamour Difference?</h2>
                    <p class="lead mb-4">Book your appointment today and let our experts transform your look.</p>
                    <a href="./booking/booking.php" class="btn btn-custom">Book Now</a>
                </div>
            </div>
        </div>
    </section>

    <!-- About Us -->
    <main>
        <section>
            <h2>About Us</h2>
            <p>Welcome to Glamour, where artistry meets expertise. Founded by five beauty visionaries with a shared dream, our salon blends luxury with creativity. Our award-winning team combines cutting-edge techniques with personalized attention, ensuring every client leaves feeling transformed and confident. Experience the difference that passion makes.</p>
        </section>
    </main>

    <?php include './reusable/footer.php'; ?>
    <?php include './reusable/scripts.php'; ?>
</body>

</html>
