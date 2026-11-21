
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Administrator Dashboard | Baguio City National High School</title>

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

    <!-- ADMIN DASHBOARD CONTENT -->
    <main class="dashboard-section">

        <div class="dashboard-welcome">
            <h2>Welcome, System Administrator</h2>
            <p>Manage user accounts, assign system roles, and maintain system access.</p>
        </div>

        <!-- DASHBOARD SUMMARY -->
        <div class="registrar-summary">

            <div class="summary-card">
                <h3>Total User Accounts</h3>
                <p>0</p>
            </div>

            <div class="summary-card">
                <h3>Student/Parent Accounts</h3>
                <p>0</p>
            </div>

            <div class="summary-card">
                <h3>School Personnel Accounts</h3>
                <p>0</p>
            </div>

            <div class="summary-card">
                <h3>Inactive Accounts</h3>
                <p>0</p>
            </div>

        </div>

        <!-- ADMIN SERVICES -->
        <h3 class="registrar-services-title">System Administration Services</h3>

        <div class="dashboard-cards">

            <div class="dashboard-card">
                <h3>User Management</h3>
                <p>
                    Create, view, update, and manage user accounts.
                    Assign appropriate system roles and manage account status.
                </p>
                <a href="user_management.php">Manage Users</a>
            </div>

            <div class="dashboard-card">
                <h3>System Activity</h3>
                <p>
                    Monitor system activities and maintain administrative
                    activity records. This feature will be developed later.
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
