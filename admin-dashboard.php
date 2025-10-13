<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}
$config = include __DIR__ . '/config.php';
if (!is_array($config)) {
    $config = ['site' => ['name' => 'AEIMS']];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - <?php echo $config['site']['name']; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --background: #0f0f23;
            --surface: #252547;
            --text-primary: #ffffff;
            --text-secondary: #a1a1aa;
            --primary-color: #1e40af;
            --accent-color: #059669;
            --border: #374151;
            --gradient-primary: linear-gradient(135deg, #1e40af 0%, #3730a3 100%);
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--background);
            color: var(--text-primary);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            background: var(--surface);
            padding: 3rem;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }
        h1 {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1.5rem;
            font-size: 2.5rem;
        }
        p { color: var(--text-secondary); margin: 0.75rem 0; }
        .welcome { font-size: 1.2rem; color: var(--text-primary); margin-bottom: 2rem; }
        .btn {
            padding: 0.75rem 1.5rem;
            background: var(--gradient-primary);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            margin: 1rem 0.5rem 0 0;
            border: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4); }
        .btn-secondary { background: var(--surface); border: 1px solid var(--border); }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-top: 2rem; }
        .info-card { background: var(--background); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border); }
        .info-card h3 { color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .info-card p { color: var(--text-primary); font-size: 1.5rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Dashboard</h1>
        <p class="welcome">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>

        <div class="info-grid">
            <div class="info-card">
                <h3>User Type</h3>
                <p><?php echo htmlspecialchars(ucfirst($_SESSION['user_type'])); ?></p>
            </div>
            <div class="info-card">
                <h3>Login Time</h3>
                <p><?php echo date('H:i', $_SESSION['login_time']); ?></p>
            </div>
            <div class="info-card">
                <h3>Status</h3>
                <p style="color: var(--accent-color);">Active</p>
            </div>
        </div>

        <div style="margin-top: 2rem;">
            <a href="auth.php" class="btn">View Auth Status</a>
            <a href="logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </div>
</body>
</html>
