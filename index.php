<?php
require_once 'config.php';

// Get all events from database
$conn = getDBConnection();
$events = [];
$result = $conn->query("SELECT * FROM events ORDER BY id");
if ($result) {
    $events = $result->fetch_all(MYSQLI_ASSOC);
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProsperMinds – 2026 CPD Calendar</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="header">
        <h1>ProsperMinds – 2026 CPD Calendar</h1>
        <p>"Build the mind that crises hate to meet."</p>
    </div>

    <div class="events-container">
        <?php foreach ($events as $event): ?>
        <div class="event-card">
            <div class="event-header">
                <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                <div class="event-dates"><?php echo htmlspecialchars($event['dates']); ?></div>
                <div class="event-venue"><?php echo htmlspecialchars($event['venue']); ?></div>
            </div>

            <div class="event-body">
                <div class="event-tagline"><?php echo htmlspecialchars($event['tagline']); ?></div>
                <div class="event-description"><?php echo htmlspecialchars($event['description']); ?></div>

                <div class="event-details">
                    <div class="event-detail-item">
                        <span class="event-detail-label">Objective:</span>
                        <span class="event-detail-value"><?php echo htmlspecialchars($event['objective']); ?></span>
                    </div>

                    <div class="event-detail-item">
                        <span class="event-detail-label">Method:</span>
                        <span class="event-detail-value"><?php echo htmlspecialchars($event['method']); ?></span>
                    </div>

                    <div class="event-detail-item">
                        <span class="event-detail-label">Benefit:</span>
                        <span class="event-detail-value"><?php echo htmlspecialchars($event['benefit']); ?></span>
                    </div>
                </div>

                <div class="event-cost"><?php echo htmlspecialchars($event['cost']); ?></div>

                <a href="registration_page.php?event_id=<?php echo $event['id']; ?>" class="register-btn" style="text-align: center; text-decoration: none;">Register</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>


    <div class="footer">
        <p>Contact Us: <a href="mailto:info@prosper-minds.com">info@prosper-minds.com</a></p>
        <p>&copy; <?php echo date('Y'); ?> ProsperMinds. All rights reserved.</p>
    </div>

    <script src="scripts.js"></script>
</body>
</html>
