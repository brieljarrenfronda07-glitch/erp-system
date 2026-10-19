# Pawcare Veterinary Clinic Management

A responsive PHP 8 application with SQLite persistence, built for XAMPP. No Composer, Node.js, or database server setup is required.

## Run

Start Apache in XAMPP, then open **http://localhost/pet/**. Alternatively run `D:\xampp\php\php.exe -S localhost:8080 router.php` in this folder and visit http://localhost:8080.

The SQLite database is created automatically in `data/` on the first request. PHP needs PDO SQLite enabled (already available in this XAMPP installation). Apache must honor `data/.htaccess`, which blocks direct access to the database.

## Demo accounts

- Admin: `admin@pawcare.local` / `Admin123!`
- Staff: `staff@pawcare.local` / `Staff123!`

Admin can create accounts and delete records. Both roles can view, add, edit, search, and export clinic records. Linked owners and patients cannot be deleted while dependent records exist.

## Features

- Dashboard with appointment schedule, revenue collected, patient count, and low-stock alerts
- Owner directory and patient profiles with consultation, appointment, and vaccine histories
- Appointment scheduling and status tracking
- Consultation diagnoses, treatments, and notes
- Vaccination dates and booster due dates
- Inventory quantities, reorder thresholds, expiry dates, and unit prices
- Simple invoice records, amounts, and paid/unpaid status
- Admin account creation; role restrictions enforced on the server
- CSV exports, responsive layouts, form validation, CSRF protection, prepared queries, and password hashing

## Deployment notes

This is a local starter with demonstration accounts and sample records. Before exposing it publicly, replace the seeded credentials, use HTTPS with secure session cookies, configure backups and database file permissions, and review operational requirements. Invoice amounts and stock counts are maintained manually; there is no payment gateway, automated dispensing, email/SMS delivery, tax accounting, or document upload integration. Back up `data/clinic.sqlite` when the app is idle. The external Google Font is optional; system fonts work offline.
