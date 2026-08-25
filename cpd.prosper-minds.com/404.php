<?php
// Standalone by design: a 404 page must never fail itself, so this does not
// touch the database — just static branding + a link back to the calendar.
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found | ProsperMinds CPD Calendar</title>
    <meta name="robots" content="noindex, follow">
    <link rel="stylesheet" href="styles.css">
    <style>
        .not-found {
            max-width: 600px;
            margin: 60px auto;
            padding: 40px;
            text-align: center;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .not-found .code {
            font-size: 4rem;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1;
            margin-bottom: 10px;
        }
        .not-found a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: var(--primary-gradient);
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ProsperMinds CPD Calendar</h1>
    </div>

    <div class="not-found">
        <div class="code">404</div>
        <h2>Page Not Found</h2>
        <p>That page doesn't exist or may have moved.</p>
        <a href="/index.php">Back to the Calendar</a>
    </div>
</body>
</html>
