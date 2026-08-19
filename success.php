<?php
require_once 'config.php';

// Check if registration_id and event_id are provided
if (!isset($_GET['registration_id']) || !isset($_GET['event_id'])) {
    header("Location: index.php");
    exit;
}

$registration_id = intval($_GET['registration_id']);
$event_id = intval($_GET['event_id']);

// Get event details
$conn = getDBConnection();
$event_query = $conn->prepare("SELECT title, dates, venue FROM events WHERE id = ?");
$event_query->bind_param("i", $event_id);
$event_query->execute();
$event_result = $event_query->get_result();

if ($event_result->num_rows === 0) {
    header("Location: index.php");
    exit;
}

$event = $event_result->fetch_assoc();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - ProsperMinds</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .success-container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            text-align: center;
        }

        .success-icon {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .success-title {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .success-message {
            font-size: 1.1rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .event-info {
            background: var(--light-gray);
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            text-align: left;
        }

        .event-info h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .event-info p {
            margin-bottom: 10px;
        }

        .back-button {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(2, 173, 47, 0.3);
        }

        .registration-id {
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            display: inline-block;
            margin: 20px 0;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✓</div>
        <h1 class="success-title">Registration Successful!</h1>

        <div class="success-message">
            <p>Thank you for registering for our event. Your registration has been successfully processed.</p>
            <p>You will receive a confirmation email shortly with all the details.</p>
        </div>

        <div class="registration-id">
            Registration ID: #<?php echo $registration_id; ?>
        </div>

        <div class="event-info">
            <h3>Event Details</h3>
            <p><strong>Event:</strong> <?php echo htmlspecialchars($event['title']); ?></p>
            <p><strong>Dates:</strong> <?php echo htmlspecialchars($event['dates']); ?></p>
            <p><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
        </div>

        <p>We look forward to seeing you at the event. If you have any questions, please contact us at <a href="mailto:info@prosper-minds.com">info@prosper-minds.com</a>.</p>

        <button class="back-button" onclick="window.location.href='index.php'">Back to Events</button>
    </div>
</body>
</html>
