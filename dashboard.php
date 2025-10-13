<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$config = include 'config.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - <?php echo $config['site']['name']; ?></title>
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
        .user-info {
            background: var(--background);
            padding: 2rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            margin: 2rem 0;
        }
        .user-info p {
            color: var(--text-secondary);
            margin: 1rem 0;
            font-size: 1rem;
        }
        .user-info strong { color: var(--text-primary); }
        .btn {
            padding: 0.75rem 1.5rem;
            background: var(--gradient-primary);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            margin: 0.5rem 0.5rem 0 0;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4); }
    </style>
</head>
<body>
    <div class="container">
        <h1>Customer Dashboard</h1>
        <div class="user-info">
            <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            <p><strong>Type:</strong> <?php echo htmlspecialchars(ucfirst($_SESSION['user_type'])); ?></p>
            <p><strong>Login Time:</strong> <?php echo date('Y-m-d H:i:s', $_SESSION['login_time']); ?></p>
        </div>
        <a href="logout.php" class="btn">Logout</a>
    </div>
</body>
</html>
