<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../reusable/head.php'; ?>
    <title>FAQ - Glamour Salon</title>
</head>

<body>
    <?php include '../reusable/header.php'; ?>

    <header class="faq-header text-center py-5">
        <div class="container">
            <h1>Frequently Asked Questions</h1>
            <p>Find answers to common questions about our services and policies.</p>
        </div>
    </header>

    <main class="container my-5 faq-section">
        <div class="accordion accordion-flush" id="faqAccordion">

            <!-- FAQ Item 1 -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingOne">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                        <i class="fas fa-question-circle me-3"></i> What services do you offer?
                    </button>
                </h2>
                <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        We offer a wide range of beauty services including haircuts, styling, coloring, highlights, manicures, pedicures, and facials. Check out our <a href="/services/services.php">Services page</a> for a full list and descriptions.
                    </div>
                </div>
            </div>

            <!-- FAQ Item 2 -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingTwo">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                        <i class="fas fa-calendar-alt me-3"></i> How can I book an appointment?
                    </button>
                </h2>
                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        You can easily book an appointment online through our <a href="/booking/booking.php">Booking page</a>. Alternatively, you can call us directly at (+966) 11-493-2646 during business hours.
                    </div>
                </div>
            </div>

            <!-- FAQ Item 3 -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingThree">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                        <i class="fas fa-clock me-3"></i> What are your opening hours?
                    </button>
                </h2>
                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        Our salon hours are:
                        <ul class="list-group list-group-flush mt-2">
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <span><i class="far fa-calendar-days me-2 text-muted"></i>Sunday - Thursday</span>
                                <span class="badge bg-info-subtle text-info-emphasis rounded-pill">9:00 AM - 12:00 AM</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <span><i class="far fa-calendar-days me-2 text-muted"></i>Friday</span>
                                <span class="badge bg-info-subtle text-info-emphasis rounded-pill">1:00 PM - 12:00 AM</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <span><i class="far fa-calendar-days me-2 text-muted"></i>Saturday</span>
                                <span class="badge bg-info-subtle text-info-emphasis rounded-pill">10:00 AM - 12:00 AM</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- FAQ Item 4 -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingFour">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                        <i class="fas fa-parking me-3"></i> Is there parking available?
                    </button>
                </h2>
                <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        Yes, we have free parking available for our clients right outside the salon.
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php include '../reusable/footer.php'; ?>
    <?php include '../reusable/scripts.php'; ?>
</body>

</html>