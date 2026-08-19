<?php
require_once 'includes/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND is_active = 1");
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    header("Location: index.php");
    exit;
}

// Generate the full URL for this event (for QR code and sharing)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$eventUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($eventUrl);

// Calculate countdown target (event_start_date)
$startDate = $event['event_start_date'];
if (empty($startDate)) {
    // If no start date, just fallback to some future date for display
    $startDate = date('Y-m-d', strtotime('+30 days'));
}
$targetTimestamp = strtotime($startDate . " 09:00:00");

// Brochure content (optional — sections only render when data exists)
$whyParagraphs = !empty($event['why_intro']) ? array_filter(array_map('trim', explode("\n", $event['why_intro']))) : [];
$masterPoints  = !empty($event['master_points']) ? array_filter(array_map('trim', explode("\n", $event['master_points']))) : [];
$agendaDays    = !empty($event['agenda']) ? json_decode($event['agenda'], true) : [];
$audienceList  = !empty($event['audience']) ? array_filter(array_map('trim', explode("\n", $event['audience']))) : [];
$vvipPerks     = !empty($event['vvip_perks']) ? array_filter(array_map('trim', explode("\n", $event['vvip_perks']))) : [];
$vipPerks      = !empty($event['vip_perks']) ? array_filter(array_map('trim', explode("\n", $event['vip_perks']))) : [];
$regularPerks  = !empty($event['regular_perks']) ? array_filter(array_map('trim', explode("\n", $event['regular_perks']))) : [];
$hasTiers      = $vvipPerks || $vipPerks || $regularPerks;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title']); ?> - Prosperminds</title>
    <meta property="og:title" content="<?php echo htmlspecialchars($event['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($event['tagline']); ?>">
    <?php if (!empty($event['image_path'])): ?>
    <meta property="og:image" content="<?php echo $protocol . $_SERVER['HTTP_HOST'] . '/' . htmlspecialchars($event['image_path']); ?>">
    <?php endif; ?>
    <meta property="og:url" content="<?php echo $eventUrl; ?>">

    <!-- Fonts and Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        body {
            background-color: #f4f7f6;
        }
        
        /* Modern Header for Event */
        .event-detail-header {
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            color: white;
            padding: 100px 20px 60px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .event-detail-header::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('assets/images/pattern.svg') center/cover;
            opacity: 0.1;
        }
        
        .event-detail-header h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
            text-shadow: 0 4px 10px rgba(0,0,0,0.3);
            color: #ffffff !important;
        }

        .event-detail-header p.tagline {
            font-size: 1.25rem;
            color: #d1d5db;
            max-width: 700px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        /* Two Column Layout */
        .event-content-wrapper {
            max-width: 1100px;
            margin: -40px auto 60px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            position: relative;
            z-index: 10;
            display: flex;
            flex-direction: row;
            overflow: hidden;
        }

        .event-left {
            flex: 1;
            position: relative;
            min-height: 400px;
            background-color: #f8fafc;
        }

        .event-left img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background-color: #ffffff;
            position: absolute;
            top: 0;
            left: 0;
            border-radius: 16px 0 0 16px;
        }

        /* If no image, a gradient placeholder */
        .event-left.no-img {
            background: linear-gradient(135deg, var(--primary-green), #004d40);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
        }

        .event-right {
            flex: 1.2;
            padding: 40px;
            display: flex;
            flex-direction: column;
        }

        /* Event Info Badges */
        .event-badges {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .badge-info {
            background: #f1f5f9;
            color: #334155;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        
        .badge-info i {
            color: var(--primary-green);
        }

        .event-price-tag {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--dark-green);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Early Bird Section */
        .early-bird-box {
            background: linear-gradient(to right, #ecfdf5, #ffffff);
            border-left: 4px solid var(--primary-green);
            padding: 20px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }

        .early-bird-box h4 {
            color: #065f46;
            margin-bottom: 12px;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
        }

        .early-bird-list li {
            list-style: none;
            margin-bottom: 8px;
            font-size: 0.95rem;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .early-bird-list li i {
            color: var(--primary-green);
        }

        /* Actions */
        .event-actions {
            display: flex;
            gap: 15px;
            margin-top: auto; /* push to bottom of right col */
            padding-top: 30px;
        }

        .btn-large {
            padding: 16px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .btn-register-pulse {
            background: var(--primary-green);
            color: white;
            flex: 1.5;
            box-shadow: 0 10px 20px rgba(0, 177, 64, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-register-pulse:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(0, 177, 64, 0.4);
            color: white;
        }

        /* QR Code & Share */
        .share-section {
            margin-top: 60px;
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1100px;
            margin-left: auto;
            margin-right: auto;
        }

        .share-content h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--dark-green);
        }
        
        .share-content p {
            color: #64748b;
            font-size: 1.1rem;
            margin-bottom: 20px;
        }
        
        .social-share-btns a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #334155;
            margin-right: 10px;
            font-size: 1.2rem;
            transition: all 0.3s;
        }
        .social-share-btns a:hover {
            background: var(--primary-green);
            color: white;
            transform: translateY(-3px);
        }

        .qr-wrapper {
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
        }
        .qr-wrapper img {
            display: block;
        }
        .qr-wrapper span {
            display: block;
            margin-top: 10px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
        }

        /* Flip Countdown Styles */
        .countdown-section {
            margin: 60px auto;
            max-width: 1100px;
            text-align: center;
        }
        
        .countdown-section h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 2rem;
            margin-bottom: 30px;
            color: #1e293b;
        }

        .flip-clock {
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .flip-unit {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .flip-card {
            background: #1e293b;
            color: white;
            font-family: 'Outfit', sans-serif;
            font-size: 3.5rem;
            font-weight: 800;
            padding: 20px 25px;
            border-radius: 12px;
            min-width: 100px;
            position: relative;
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
            margin-bottom: 15px;
            text-align: center;
            background-image: linear-gradient(to bottom, #1e293b 50%, #0f172a 50%);
        }
        
        /* Line across the middle of the flip card */
        .flip-card::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(0,0,0,0.4);
            box-shadow: 0 1px 1px rgba(255,255,255,0.1);
        }

        .flip-label {
            text-transform: uppercase;
            font-size: 0.85rem;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 1px;
        }

        /* Responsive */
        @media (max-width: 991px) {
            .event-content-wrapper {
                flex-direction: column;
                margin-left: 20px;
                margin-right: 20px;
            }
            .event-left {
                min-height: 300px;
            }
            .share-section {
                flex-direction: column;
                text-align: center;
                gap: 30px;
                margin: 40px 20px;
            }
            .flip-card {
                font-size: 2.5rem;
                padding: 15px 20px;
                min-width: 70px;
            }
            .flip-clock {
                gap: 10px;
            }
        }

        /* Brochure sections (why / master / agenda / audience / tiers) */
        .event-brochure {
            max-width: 1100px;
            margin: 0 auto 20px;
            padding: 0 20px;
        }
        .event-section {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            padding: 40px;
            margin-bottom: 24px;
        }
        .event-section h2 {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.6rem;
            color: #0f2027;
            margin-bottom: 20px;
        }
        .focus-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 18px;
        }
        .focus-tag {
            background: #ecfdf5;
            color: var(--dark-green, #065f46);
            font-size: 0.8rem;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 20px;
            border: 1px solid #bbf7d0;
        }
        .why-text p {
            color: #475569;
            line-height: 1.9;
            font-size: 1.02rem;
            margin-bottom: 16px;
        }
        .master-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }
        .master-list li {
            list-style: none;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: #334155;
            font-size: 0.98rem;
            line-height: 1.5;
        }
        .master-list li i {
            color: var(--primary-green);
            margin-top: 4px;
        }
        .agenda-grid {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .agenda-day {
            display: flex;
            gap: 20px;
            padding: 18px;
            border-radius: 10px;
            background: #f8fafc;
            border-left: 4px solid var(--primary-green);
        }
        .agenda-day .day-num {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.3rem;
            color: var(--primary-green);
            min-width: 70px;
        }
        .agenda-day h4 {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            color: #0f2027;
            margin-bottom: 4px;
        }
        .agenda-day p {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .audience-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .audience-tag {
            background: #f1f5f9;
            color: #334155;
            font-size: 0.9rem;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
        }
        .tiers-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .tier-card {
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            display: flex;
            flex-direction: column;
        }
        .tier-card.vvip {
            border-color: #d4af37;
            background: linear-gradient(180deg, #fffbeb 0%, #ffffff 100%);
        }
        .tier-card.vip {
            border-color: var(--primary-green);
            background: linear-gradient(180deg, #ecfdf5 0%, #ffffff 100%);
        }
        .tier-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.05rem;
            color: #0f2027;
            margin-bottom: 4px;
        }
        .tier-seats-note {
            font-size: 0.8rem;
            color: #b45309;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .tier-price {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.7rem;
            color: #0f2027;
            margin-bottom: 14px;
        }
        .tier-perks {
            flex: 1;
        }
        .tier-perks li {
            list-style: none;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 0.9rem;
            color: #475569;
            margin-bottom: 10px;
            line-height: 1.4;
        }
        .tier-perks li i {
            color: var(--primary-green);
            margin-top: 3px;
        }

        @media (max-width: 768px) {
            .master-list {
                grid-template-columns: 1fr;
            }
            .tiers-grid {
                grid-template-columns: 1fr;
            }
            .event-section {
                padding: 24px;
            }
        }
    </style>
    <?php include __DIR__ . '/includes/google-tag.php'; ?>
</head>
<body>
    <header>
        <div class="container navbar">
            <a href="index.php" class="logo">
                <img src="assets/images/fisrt-logo.png" alt="Prosperminds Logo">
            </a>
            <nav class="nav-links">
                <a href="index.php#home">Home</a>
                <a href="index.php#events">Events</a>
                <a href="index.php#about">About</a>
                <a href="index.php#services">Services</a>
                <a href="index.php#contact">Contact</a>
            </nav>
        </div>
    </header>

    <div class="event-detail-header">
        <div class="container">
            <h1><?php echo htmlspecialchars($event['title']); ?></h1>
            <?php if ($event['tagline']): ?>
                <p class="tagline"><?php echo htmlspecialchars($event['tagline']); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="event-content-wrapper">
        <!-- Left Side: Photo -->
        <div class="event-left <?php echo empty($event['image_path']) ? 'no-img' : ''; ?>">
            <?php if (!empty($event['image_path'])): ?>
                <img src="<?php echo htmlspecialchars($event['image_path']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
            <?php else: ?>
                <i class="fas fa-calendar-alt"></i>
            <?php endif; ?>
        </div>
        
        <!-- Right Side: Details -->
        <div class="event-right">
            <?php if (!empty($event['focus_tags'])): ?>
            <div class="focus-tags">
                <?php foreach (array_map('trim', explode('·', $event['focus_tags'])) as $tag): ?>
                    <?php if ($tag !== ''): ?><span class="focus-tag"><?php echo htmlspecialchars($tag); ?></span><?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="event-badges">
                <div class="badge-info">
                    <i class="far fa-calendar-alt"></i> <?php echo htmlspecialchars($event['date_display']); ?>
                </div>
                <div class="badge-info">
                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?>
                </div>
            </div>

            <div class="event-price-tag">
                <i class="fas fa-tag" style="color: var(--primary-green);"></i>
                <?php echo htmlspecialchars($event['price']); ?>
            </div>

            <p style="color: #475569; line-height: 1.8; margin-bottom: 25px; font-size: 1.05rem;">
                Join government executives and finance leaders for this exclusive CPD event. Equip yourself with modern strategies, ensure IPSAS and compliance success, and network with leading minds. Don't miss the opportunity to transform your public finance approach.
            </p>
            
            <?php
            $earlyBirds = [];
            if ($event['early_bird_1_date'] && $event['early_bird_1_pct']) {
                $earlyBirds[] = ['pct' => $event['early_bird_1_pct'], 'date' => $event['early_bird_1_date']];
            }
            if ($event['early_bird_2_date'] && $event['early_bird_2_pct']) {
                $earlyBirds[] = ['pct' => $event['early_bird_2_pct'], 'date' => $event['early_bird_2_date']];
            }
            if ($event['early_bird_3_date'] && $event['early_bird_3_pct']) {
                $earlyBirds[] = ['pct' => $event['early_bird_3_pct'], 'date' => $event['early_bird_3_date']];
            }
            if ($earlyBirds):
            ?>
            <div class="early-bird-box">
                <h4><i class="fas fa-gift" style="margin-right: 8px;"></i> Early Bird Savings</h4>
                <ul class="early-bird-list">
                <?php foreach ($earlyBirds as $eb): ?>
                    <li><i class="fas fa-check-circle"></i> Save <?php echo (int)$eb['pct']; ?>% — Register by <strong><?php echo date('j F Y', strtotime($eb['date'])); ?></strong></li>
                <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="event-actions">
                <a href="event-registration.php?id=<?php echo $event['id']; ?>" class="btn btn-large btn-register-pulse">
                    Reserve Your Seat
                </a>
                <?php if (!empty($event['pdf_file'])): ?>
                    <a href="<?php echo htmlspecialchars($event['pdf_file']); ?>" target="_blank" class="btn btn-outline btn-large" style="flex: 1;">
                        <i class="fas fa-file-pdf"></i> Brochure
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Brochure Sections -->
    <div class="event-brochure">
        <?php if ($whyParagraphs): ?>
        <div class="event-section">
            <h2>Why This Programme, Why Now</h2>
            <div class="why-text">
                <?php foreach ($whyParagraphs as $para): ?>
                    <p><?php echo htmlspecialchars($para); ?></p>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($masterPoints): ?>
        <div class="event-section">
            <h2>What You Will Master</h2>
            <ul class="master-list">
                <?php foreach ($masterPoints as $point): ?>
                    <li><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($point); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($agendaDays): ?>
        <div class="event-section">
            <h2>The Five-Day Programme</h2>
            <div class="agenda-grid">
                <?php foreach ($agendaDays as $day): ?>
                    <div class="agenda-day">
                        <div class="day-num">Day <?php echo (int)($day['day'] ?? 0); ?></div>
                        <div>
                            <h4><?php echo htmlspecialchars($day['title'] ?? ''); ?></h4>
                            <p><?php echo htmlspecialchars($day['desc'] ?? ''); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($audienceList): ?>
        <div class="event-section">
            <h2>Who Should Attend</h2>
            <div class="audience-tags">
                <?php foreach ($audienceList as $role): ?>
                    <span class="audience-tag"><?php echo htmlspecialchars($role); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($hasTiers): ?>
        <div class="event-section">
            <h2>Investment &amp; Delegate Tiers</h2>
            <div class="tiers-grid">
                <?php if ($vvipPerks): ?>
                <div class="tier-card vvip">
                    <div class="tier-name"><i class="fas fa-crown" style="color:#d4af37;"></i> VVIP — Sovereign Circle</div>
                    <?php if (!empty($event['vvip_seats_note'])): ?>
                        <div class="tier-seats-note"><?php echo htmlspecialchars($event['vvip_seats_note']); ?></div>
                    <?php endif; ?>
                    <div class="tier-price"><?php echo htmlspecialchars($event['vvip_price'] ?? ''); ?></div>
                    <ul class="tier-perks">
                        <?php foreach ($vvipPerks as $perk): ?>
                            <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($perk); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php if ($vipPerks): ?>
                <div class="tier-card vip">
                    <div class="tier-name"><i class="fas fa-star" style="color:var(--primary-green);"></i> VIP — Executive Delegate</div>
                    <div class="tier-price"><?php echo htmlspecialchars($event['vip_price'] ?? ''); ?></div>
                    <ul class="tier-perks">
                        <?php foreach ($vipPerks as $perk): ?>
                            <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($perk); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php if ($regularPerks): ?>
                <div class="tier-card">
                    <div class="tier-name"><i class="fas fa-user"></i> Regular — Professional Delegate</div>
                    <div class="tier-price"><?php echo htmlspecialchars($event['regular_price'] ?? ''); ?></div>
                    <ul class="tier-perks">
                        <?php foreach ($regularPerks as $perk): ?>
                            <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($perk); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Countdown Section -->
    <div class="countdown-section">
        <h3>Time Remaining Until Event</h3>
        <div class="flip-clock" id="flip-clock">
            <div class="flip-unit">
                <div class="flip-card" id="days">00</div>
                <div class="flip-label">Days</div>
            </div>
            <div class="flip-unit">
                <div class="flip-card" id="hours">00</div>
                <div class="flip-label">Hours</div>
            </div>
            <div class="flip-unit">
                <div class="flip-card" id="minutes">00</div>
                <div class="flip-label">Minutes</div>
            </div>
            <div class="flip-unit">
                <div class="flip-card" id="seconds">00</div>
                <div class="flip-label">Seconds</div>
            </div>
        </div>
    </div>

    <!-- Share & QR Section -->
    <div class="share-section">
        <div class="share-content">
            <h3>Share This Event</h3>
            <p>Invite your colleagues and network to join you at this exclusive gathering.</p>
            <div class="social-share-btns">
                <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode($eventUrl); ?>&title=<?php echo urlencode($event['title']); ?>" target="_blank" title="Share on LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($eventUrl); ?>&text=<?php echo urlencode("Join me at " . $event['title']); ?>" target="_blank" title="Share on Twitter"><i class="fab fa-twitter"></i></a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($eventUrl); ?>" target="_blank" title="Share on Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="mailto:?subject=<?php echo urlencode($event['title']); ?>&body=<?php echo urlencode("Check out this event: " . $eventUrl); ?>" title="Share via Email"><i class="fas fa-envelope"></i></a>
            </div>
        </div>
        <div class="qr-wrapper">
            <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code to Event">
            <span>Scan to view on Mobile</span>
        </div>
    </div>

    <footer>
        <div class="container" style="text-align: center; padding: 40px 20px;">
            <p style="color: #64748b;">&copy; <?php echo date('Y'); ?> Prosperminds. All rights reserved.</p>
        </div>
    </footer>

    <!-- Countdown Logic -->
    <script>
        const targetDate = <?php echo $targetTimestamp * 1000; ?>;
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = targetDate - now;

            if (distance < 0) {
                document.getElementById('days').innerText = "00";
                document.getElementById('hours').innerText = "00";
                document.getElementById('minutes').innerText = "00";
                document.getElementById('seconds').innerText = "00";
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById('days').innerText = days.toString().padStart(2, '0');
            document.getElementById('hours').innerText = hours.toString().padStart(2, '0');
            document.getElementById('minutes').innerText = minutes.toString().padStart(2, '0');
            document.getElementById('seconds').innerText = seconds.toString().padStart(2, '0');
        }

        // Run immediately and then every second
        updateCountdown();
        setInterval(updateCountdown, 1000);
    </script>
</body>
</html>
