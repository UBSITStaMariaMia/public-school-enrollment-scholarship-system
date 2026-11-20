
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | Baguio City National High School</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

    <!-- ADMIN HEADER -->
    <header class="main-header">

        <div class="header-container">

            <div class="school-brand">
                <img src="../assets/images/school-logo.png" alt="School Logo">

                <div class="school-name">
                    <h1>Baguio City National High School</h1>
                    <p>System Administrator Portal</p>
                </div>
            </div>

            <nav class="main-navigation">
                <a href="dashboard.php">Dashboard</a>
                <a href="user_management.php">User Management</a>
                <a href="../announcements.php">Announcements</a>
                <a href="../index.php">Logout</a>
            </nav>

        </div>

    </header>

    <!-- USER MANAGEMENT CONTENT -->
    <main class="records-section">

        <div class="records-heading">
            <h2>User Management</h2>
            <p>Create, view, and manage user accounts and their assigned system roles.</p>
        </div>

        <!-- USER MANAGEMENT ACTION -->
        <div class="application-table-card">

            <div class="application-card-heading">
                <h3>Registered User Accounts</h3>

                <a href="#" class="application-button">
                    Add New User
                </a>
            </div>

            <!-- SEARCH AND FILTER -->
            <div class="records-filters">

                <input type="text"
                       placeholder="Search by name or username">

                <select>
                    <option value="">All User Roles</option>
                    <option value="student_parent">Student/Parent</option>
                    <option value="registrar">School Registrar</option>
                    <option value="teacher">Teacher/Adviser</option>
                    <option value="enrollment_committee">Enrollment Committee</option>
                    <option value="scholarship_coordinator">Scholarship Coordinator</option>
                    <option value="admin">System Administrator</option>
                </select>

                <select>
                    <option value="">All Account Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>

            </div>

            <!-- USER ACCOUNTS TABLE -->
            <div class="table-responsive">

                <table class="records-table">

                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email Address</th>
                            <th>Account Role</th>
                            <th>Account Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td colspan="7" class="empty-table">
                                No user accounts available.
                            </td>
                        </tr>
                    </tbody>

                </table>

            </div>

        </div>

        <!-- USER MANAGEMENT GUIDELINES -->
        <div class="application-card">

            <div class="application-card-heading">
                <h3>User Account Management Guidelines</h3>
            </div>

            <div class="application-details">

                <p>
                    <strong>Student/Parent Accounts:</strong>
                    Users can create their own accounts through the registration page.
                </p>

                <p>
                    <strong>School Personnel Accounts:</strong>
                    The System Administrator creates accounts for authorized school personnel.
                </p>

                <p>
                    <strong>Role Assignment:</strong>
                    Assign the appropriate system role based on the user's responsibilities,
                    including School Registrar, Teacher/Adviser, Enrollment Committee,
                    and Scholarship Coordinator.
                </p>

                <p>
                    <strong>Account Status:</strong>
                    Manage account access by monitoring active and inactive accounts.
                </p>

                <p>
                    <strong>Account Security:</strong>
                    Assist users with account access concerns and password resets.
                </p>

            </div>

        </div>

    </main>

    <!-- WEBSITE FOOTER -->
    <footer class="main-footer">

        <div class="footer-content">
            <h3>Baguio City National High School</h3>
            <p>Enrollment and Student Records Management System</p>
            <p class="copyright">
                &copy; 2026 Baguio City National High School. All rights reserved.
            </p>
        </div>

    </footer>

</body>
</html>
