# AppointmentBookingSystem

An online appointment booking system for clinics with patient, manager, doctor, and administrator workflows.

## Features

### Patients
- Register an account
- Book an appointment
- Cancel an appointment
- View appointment status
- View doctor availability
- Search clinics and doctors

### Managers
- View appointments for assigned clinics
- Update appointment status

### Doctors
- View appointments assigned to the authenticated doctor
- Approve, complete, or cancel appointments

### Administrators
- Manage doctors, clinics, and managers
- Assign doctors and managers to clinics

## Technologies

- PHP
- MySQL / MariaDB
- HTML5 / CSS3
- JavaScript / AJAX / jQuery

## Local setup

1. Install PHP and MySQL/MariaDB through XAMPP, WAMP, or an equivalent local stack.
2. Create the `wt_database` database and import `wt_database.sql`.
3. Configure the database connection in `dbconfig.php` for your local environment.
4. Open `cover.php` through your local web server.

## Security note

This repository does not document or publish working administrator, manager, doctor, or patient credentials. Create local accounts with credentials appropriate for your development environment.

For production deployment, use hashed passwords, HTTPS, secure session-cookie settings, CSRF protection on state-changing requests, and non-privileged database credentials. The included database dump is intended for local development/testing and should not be used as a production database without further hardening.
