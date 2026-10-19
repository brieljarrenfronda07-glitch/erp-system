# Pawcare Veterinary Clinic Management

A responsive PHP 8 application with SQLite persistence, built for XAMPP. No Composer, Node.js, or database server setup is required.

## Run

Start Apache in XAMPP, then open **http://localhost/pet/**. Alternatively run `D:\xampp\php\php.exe -S localhost:8080 router.php` in this folder and visit http://localhost:8080.

The SQLite database is created automatically in `data/` on the first request. PHP needs PDO SQLite enabled (already available in this XAMPP installation). Apache must honor `data/.htaccess`, which blocks direct access to the database.

## Demo accounts

- Admin: `admin@pawcare.local` / `Admin123!`
- Staff: `staff@pawcare.local` / `Staff123!`

Admins can create staff/admin accounts, edit inventory, export CSV files, delete records, and view the revenue overview. Staff manage daily patient care, appointments, medical records, vaccinations, diagnostic reports, and billing. Staff can view stock availability but cannot edit inventory or see inventory unit prices. Linked owners and patients cannot be deleted while dependent records exist.

## Features

- Dashboard with appointment schedule, revenue collected, patient count, and low-stock alerts
- Workflow: register owner and pet once → book a visit and requested service → document completed care in consultation/vaccine history → bill actual services. Patient profiles offer shortcuts with the owner and pet prefilled. Registration no longer requires a service.
- Appointments provide **Save & continue to billing** and **Create bill** actions. The owner, pet, service, and visit reference carry over. Staff confirm actual services and prices before saving. Each visit can link to one invoice; an existing bill is reopened instead of duplicated.
- Admin-only Activity logs with sign-ins, failed sign-ins, logouts, account creation, and record creation/update/deletion; searchable and paginated. Logs begin after this feature is deployed and share the SQLite database's storage lifecycle.
- Owner directory and patient profiles with consultation, appointment, and vaccine histories
- Appointment scheduling and status tracking
- Consultation diagnoses, treatments, and notes
- Vaccination dates and booster due dates
- Inventory quantities, reorder thresholds, expiry dates, and unit prices
- Simple invoice records, amounts, and paid/unpaid status
- Billing links each invoice to an owner and their pet, with multiple service rows, quantities, unit prices, automatic totals, partial payments, and printable itemized statements.
- Admin account creation; role restrictions enforced on the server
- CSV exports, responsive layouts, form validation, CSRF protection, prepared queries, and password hashing

## Deployment notes

This is a local starter with demonstration accounts and sample records. Before exposing it publicly, replace the seeded credentials, use HTTPS with secure session cookies, configure backups and database file permissions, and review operational requirements. Invoice amounts and stock counts are maintained manually; there is no payment gateway, automated dispensing, email/SMS delivery, tax accounting, or document upload integration. Back up `data/clinic.sqlite` when the app is idle. The external Google Font is optional; system fonts work offline.
