<?php
$footerLinksHtml = '
    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <div class="footer-logo">
                        <img src="assets/images/fisrt-logo.png" alt="Prosperminds">
                    </div>
                    <p class="footer-desc">Empowering African governments with practical training, IPSAS mastery, AI automation and sustainability reporting.</p>
                    <div style="display: flex; gap: 15px;">
                        <a href="#" style="color: var(--white); font-size: 20px;"><i class="fab fa-linkedin"></i></a>
                        <a href="#" style="color: var(--white); font-size: 20px;"><i class="fab fa-twitter"></i></a>
                        <a href="#" style="color: var(--white); font-size: 20px;"><i class="fab fa-facebook"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <h4 class="footer-heading">Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="index.php#home">Home</a></li>
                        <li><a href="index.php#events">Upcoming Events</a></li>
                        <li><a href="index.php#about">About Us</a></li>
                        <li><a href="index.php#services">Services</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4 class="footer-heading">Our Services</h4>
                    <ul class="footer-links">
                        <li><a href="service-pfm.php">PFM & IPSAS Mastery</a></li>
                        <li><a href="service-data.php">Data Analytics & AI</a></li>
                        <li><a href="service-sustainability.php">Sustainability Reporting</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4 class="footer-heading">Newsletter</h4>
                    <p style="color: #aaa; margin-bottom: 15px; font-size: 0.9rem;">Subscribe to our newsletter for the latest updates.</p>
                    <form style="display: flex;">
                        <input type="email" placeholder="Your email address" style="padding: 10px; border: none; border-radius: 4px 0 0 4px; width: 100%; outline: none;" required>
                        <button type="submit" style="background: var(--primary-green); border: none; color: white; padding: 0 15px; border-radius: 0 4px 4px 0; cursor: pointer;"><i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; ' . date('Y') . ' Prosperminds. All rights reserved.</p>
            </div>
        </div>
    </footer>
';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PFM, IPSAS & IFRS Mastery | Prosperminds</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .page-hero { background: var(--faded-green); padding: 150px 0 80px; text-align: center; }
        .page-hero h1 { color: var(--dark-green); font-size: 3rem; margin-bottom: 15px; }
        .page-hero p { color: #555; max-width: 800px; margin: 0 auto; font-size: 1.2rem; }
        .service-details { padding: 100px 0; }
        .service-img-wrapper img { width: 100%; border-radius: 8px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
        .service-list li { margin-bottom: 15px; font-size: 1.1rem; color: #444; display: flex; align-items: flex-start; gap: 10px; }
        .service-list i { color: var(--primary-green); margin-top: 5px; }
        @media (max-width: 768px) {
            .page-hero { padding: 120px 20px 60px; }
            .page-hero h1 { font-size: 2.2rem; }
            .content-grid { display: grid; grid-template-columns: 1fr !important; gap: 40px !important; }
        }
    </style>
    <?php include __DIR__ . '/includes/google-tag.php'; ?>
</head>
<body>

    <header>
        <div class="container navbar">
            <a href="index.php" class="logo"><img src="assets/images/fisrt-logo.png" alt="Prosperminds"></a>
            <nav class="nav-links">
                <a href="index.php#home">Home</a>
                <a href="index.php#events">Events</a>
                <a href="index.php#about">About</a>
                <a href="index.php#services">Services</a>
                <a href="index.php#contact">Contact</a>
            </nav>
            <div class="mobile-menu-btn"><i class="fas fa-bars"></i></div>
        </div>
    </header>

    <div class="page-hero">
        <div class="container">
            <h1 class="reveal">PFM, IPSAS & IFRS Mastery</h1>
            <p class="reveal">Build the technical foundation your finance teams need to execute flawless public sector reporting.</p>
        </div>
    </div>

    <section class="service-details">
        <div class="container content-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center;">
            <div class="service-img-wrapper reveal">
                <img src="assets/images/service_pfm_africa.png" alt="PFM Mastery Africa">
            </div>
            <div class="reveal">
                <h2 style="font-size: 2.2rem; margin-bottom: 25px;">Strengthen Institutional Transparency</h2>
                <p style="color: #555; font-size: 1.1rem; margin-bottom: 20px; line-height: 1.8;">Public Financial Management (PFM) requires rigorous adherence to international standards. At Prosperminds, we equip government officials and finance teams with practical tools to implement IPSAS and IFRS seamlessly across operations.</p>
                <p style="color: #555; font-size: 1.1rem; margin-bottom: 30px; line-height: 1.8;">Our intensive programs cover everything from cash-basis to accrual accounting transitions, ensuring zero-failure reporting, total compliance, and ultimately leading to clean audits across African government sectors.</p>
                
                <ul class="service-list" style="list-style: none; padding: 0; margin-bottom: 40px;">
                    <li><i class="fas fa-check-circle"></i> Complete IPSAS Implementation Strategy</li>
                    <li><i class="fas fa-check-circle"></i> IFRS Updates, Revisions, & Compliance</li>
                    <li><i class="fas fa-check-circle"></i> Cash to Accrual Accounting Transitions</li>
                    <li><i class="fas fa-check-circle"></i> Audit Readiness & Risk Mitigation</li>
                </ul>
                
                <a href="index.php#contact" class="btn btn-primary">Speak to our Consultants</a>
            </div>
        </div>
    </section>

    <?php echo $footerLinksHtml; ?>

    <script src="assets/js/main.js"></script>
</body>
</html>
