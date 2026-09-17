<?php
require_once 'config.php';

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$conn = getDBConnection();

$event = null;
if ($event_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $event = $result->fetch_assoc();
    }
}

if (!$event) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo htmlspecialchars($event['title']); ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .registration-page-container {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        .registration-page-header {
            background: var(--primary-gradient);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .registration-page-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .registration-page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .event-info-box {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px;
            border-left: 4px solid var(--primary-color);
            border-radius: 4px;
        }
        .event-info-box h3 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        .event-info-box p {
            margin-bottom: 5px;
            color: #555;
        }
        .registration-page-body {
            padding: 30px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="registration-page-container">
        <div class="registration-page-header">
            <h1>Event Registration</h1>
            <p>Secure your spot for this upcoming event</p>
        </div>

        <div class="event-info-box">
            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
            <p><strong>Dates:</strong> <?php echo htmlspecialchars($event['dates']); ?></p>
            <p><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
            <p><strong>Cost:</strong> <?php echo htmlspecialchars($event['cost']); ?></p>
        </div>

        <div class="registration-page-body">
            <a href="index.php" class="back-link">&larr; Back to Events Calendar</a>
            
            <?php if (isset($_GET['error'])): ?>
                <div style="background: #fee; color: #c00; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #c00;">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>

            <form id="registrationForm" class="registration-form" method="POST" action="register.php">
                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">

                <div class="form-group">
                    <label for="firstName">First Name *</label>
                    <input type="text" id="firstName" name="first_name" required>
                </div>

                <div class="form-group">
                    <label for="lastName">Last Name *</label>
                    <input type="text" id="lastName" name="last_name" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="organization">Organization</label>
                    <input type="text" id="organization" name="organization">
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="submit-btn">Submit Registration</button>
                </div>
            </form>
        </div>
    </div>

    <div class="footer">
        <p>Contact Us: <a href="mailto:info@prosper-minds.com">info@prosper-minds.com</a></p>
        <p>&copy; <?php echo date('Y'); ?> ProsperMinds. All rights reserved.</p>
    </div>

    <script src="scripts.js"></script>
</body>
</html>
