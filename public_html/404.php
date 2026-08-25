<?php
// Standalone by design: a 404 page must never fail itself, so this does not
// require config.php or touch the database — just static branding + links.
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found | Prosperminds</title>
    <meta name="robots" content="noindex, follow">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background-color: #f4f7f6; }
        .error-header {
            background: linear-gradient(135deg, var(--dark-green), var(--primary-green));
            color: white;
            padding: 140px 20px 60px;
            text-align: center;
        }
        .error-header .code {
            font-size: 5rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 10px;
        }
        .error-container {
            max-width: 700px;
            margin: -30px auto 80px;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        .error-container p {
            color: #475569;
            line-height: 1.7;
            margin-bottom: 25px;
        }
        .error-links {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            justify-content: center;
        }
        .error-links a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 22px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
        }
        .error-links .primary {
            background: var(--primary-green);
            color: white;
        }
        .error-links .secondary {
            border: 2px solid var(--primary-green);
            color: var(--primary-green);
        }
    </style>
</head>
<body>
    <header>
        <div class="container navbar">
            <a href="/index.php" class="logo">
                <img src="assets/images/fisrt-logo.png" alt="Prosperminds Logo">
            </a>
            <nav class="nav-links">
                <a href="/index.php#home">Home</a>
                <a href="/index.php#events">Events</a>
            </nav>
        </div>
    </header>

    <div class="error-header">
        <div class="container">
            <div class="code">404</div>
            <h1 style="font-size: 1.8rem; margin-bottom: 10px;">Page Not Found</h1>
            <p style="opacity: 0.9;">The page you're looking for doesn't exist or may have moved.</p>
        </div>
    </div>

    <div class="container">
        <div class="error-container">
            <p>It might have been a mistyped link, or an event page that's no longer active. Here's where you probably want to go instead:</p>
            <div class="error-links">
                <a href="/index.php" class="primary"><i class="fas fa-home"></i> Back to Home</a>
                <a href="/index.php#events" class="secondary"><i class="far fa-calendar-alt"></i> View Upcoming Events</a>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Prosperminds. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
