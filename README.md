# Event Management System

This is a comprehensive, role-based web application designed to streamline the management of academic and extracurricular events within an educational institution. The system provides distinct functionalities for administrators, teachers, and students, ensuring a secure and efficient workflow.

## Key Features

### User & Access Management
*   **Role-Based Access Control (RBAC):** Separate interfaces and permissions for Administrators, Teachers, and Students.
*   **Secure Authentication:** Robust login system with password management and a "Forgot Password" feature based on Date of Birth verification.
*   **Two-Factor Authentication (2FA):** Enhanced security using Time-based One-Time Passwords (TOTP) for user accounts.
*   **Session Management:** Implements session timeouts and other security measures to protect user accounts.

### Administrator Panels
*   **User Management:** Admins can add, edit, and manage user accounts. Includes a bulk import feature for creating multiple user accounts at once.
*   **Hackathon & Event Management:** Full CRUD (Create, Read, Update, Delete) functionality for hackathons and other events.
*   **Participant Oversight:** View and manage all event participants and their application statuses.
*   **Data Export:** Generate and export reports in Excel format for users, participants, and event applications.
*   **Counselor Management:** Assign and manage teacher counselors for students.

### Student Portal
*   **Event Discovery:** Browse and view details of upcoming hackathons and events, including posters.
*   **Online Application:** Students can apply for hackathons and track the status of their applications.
*   **OD (On-Duty) Requests:** Automated system for requesting, generating, and downloading OD letters for event participation.
*   **Internship Submissions:** A dedicated module for students to submit internship-related documents.
*   **Personalized Dashboard:** View personal participation history and manage profile information.

### Teacher/Counselor Module
*   **Student Oversight:** Teachers assigned as counselors can view and manage their designated students.
*   **Event Verification:** Teachers may have permissions to verify student participations or event-related requests.
*   **Reminders:** Ability to send reminders to students.

### Technical Details
*   **Backend:** PHP
*   **Frontend:** HTML, CSS, JavaScript
*   **Database:** MySQL (with migration scripts for version control)
*   **Security:** Implements Cross-Site Request Forgery (CSRF) protection and other security best practices.
*   **Push Notifications:** Integrates with OneSignal for sending push notifications to users.
*   **PDF Generation:** Dynamically generates PDF documents for certificates and OD letters.
*   **Asynchronous Operations:** Uses AJAX for responsive user interactions without full page reloads.

## Project Structure

The application is organized into role-specific directories (`/admin`, `/student`, `/teacher`) with a shared core logic in the `/includes` directory. This modular structure separates concerns and improves maintainability. The `/sql` directory contains database migration scripts, which is crucial for tracking and managing database schema changes over times.
