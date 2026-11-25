# Glamour Salon – Appointment Booking System

A PHP/MySQL web application for a beauty salon. Customers can browse services, book and pay for appointments, and manage their bookings. An administrator manages services, appointments, users, dues, refunds and payment methods.

## Features
- **Customers:** sign up / log in, browse services (prices in SAR), read the FAQ, book an appointment, pay in full or partially (minimum 50%; the rest becomes "dues"), view and cancel their upcoming appointments, request refunds for cancelled appointments, change profile and password, receive in-page notifications.
- **Admin:** dashboard with statistics, add / edit / delete services, reschedule or cancel any appointment, manage user roles, settle or review dues, approve or reject refund requests, enable / disable payment methods.

## Requirements
- PHP 8 with the `mysqli` extension
- MySQL or MariaDB (the project was built with MAMP: MySQL on port **8889**, user `root`, password `root`)
- The MySQL user must be allowed to create the database, or the database must already exist
- A modern web browser (Bootstrap 5 and Font Awesome are loaded from a CDN, so an internet connection is needed for styling)

## Setup
1. Start MySQL.
2. Check the connection settings in `reusable/config.php`. The defaults match MAMP (host `localhost`, port `8889`, database `WebProject`, user `root`, password `root`). To use other settings without editing code, set the environment variables `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` and `DB_PASS` (use `DB_HOST=127.0.0.1` to force a TCP connection to the given port).
3. Serve the project folder as the web root (links use paths starting with `/`). Either set the MAMP document root to the project folder, or run from the project folder: `php -S localhost:8000`
4. Open `http://localhost:8000/index.php`. On the first request the database is created if it does not exist, then the tables are created and filled with sample services, three payment methods and a default admin account.

Default admin login: **admin / admin123** (change it after the first login).

## User guide
1. **Sign up** from the top menu, then **log in**.
2. Open **Services** to see what the salon offers, or click **Book Now**.
3. Choose a service, date and time (add notes if you like) and continue to payment.
4. Pick **full** or **partial** payment and a method (card, Apple Pay or STC Pay; payment is simulated, no real transaction happens). The appointment is confirmed after payment.
5. Open **My Profile** to see your appointments and payments, cancel an upcoming appointment, request a refund for a cancelled appointment, or edit your username, email and password.
6. If you paid partially you will have **dues**. Clear them with **Pay now** on your profile before booking again.
7. The bell icon shows notifications: booking confirmed, appointment changed or cancelled, and upcoming appointments.

## Admin guide
Log in as an admin, then choose **Admin Dashboard** from the user menu. From there you can manage services, appointments, users and roles, dues, refund requests and payment methods.

## Project structure
```
index.php               home page
services/ faq/          public pages
login/                  login, signUp, logout, profile
booking/                booking form and hand-off to payment
payment/                payment form, processing, success pages, pay dues
admin/dashboard.php     admin dashboard
reusable/               db_setup (schema + helper functions), layout fragments, notifications
assets/                 CSS, JavaScript, images
```
