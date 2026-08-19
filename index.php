<?php require_once 'includes/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prosperminds | Public Finance, IPSAS, AI & Sustainability Training</title>
    <meta name="description" content="Prosperminds delivers executive public finance, IPSAS, AI automation and sustainability reporting training for African governments and finance leaders.">
    <meta name="keywords" content="IPSAS training Africa, Public Finance Management, Government finance leadership, AI automation for governments, Sustainability reporting Africa, IFRS training, Executive CPD finance courses">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://prosper-minds.com/">
    <meta property="og:title" content="Prosperminds | Public Finance, IPSAS, AI & Sustainability Training">
    <meta property="og:description" content="Prosperminds delivers executive public finance, IPSAS, AI automation and sustainability reporting training for African governments and finance leaders.">
    <meta property="og:image" content="https://prosper-minds.com/assets/images/fisrt-logo.png">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://prosper-minds.com/">
    <meta property="twitter:title" content="Prosperminds | Public Finance, IPSAS, AI & Sustainability Training">
    <meta property="twitter:description" content="Prosperminds delivers executive public finance, IPSAS, AI automation and sustainability reporting training for African governments and finance leaders.">
    <meta property="twitter:image" content="https://prosper-minds.com/assets/images/fisrt-logo.png">

    <!-- Fonts and Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <?php include __DIR__ . '/includes/google-tag.php'; ?>
</head>
<body>

    <!-- Header -->
    <header>
        <div class="container navbar">
            <a href="index.php" class="logo">
                <img src="assets/images/fisrt-logo.png" alt="Prosperminds Logo">
            </a>
            
            <nav class="nav-links">
                <a href="#home">Home</a>
                <a href="#events">Events</a>
                <a href="#about">About</a>
                <a href="#services">Services</a>
                <a href="#contact">Contact</a>
            </nav>

            <a href="admin/login.php" class="btn-admin-login">
                <i class="fas fa-lock"></i> Admin
            </a>

            <div class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </header>

    <!-- Home (Hero Section) - Background Faded Green -->
    <section id="home" class="hero">
        <div class="container hero-grid">
            <div class="hero-content reveal">
                <h1>Future Ready Public Finance & Government Leadership</h1>
                <p>Empowering African governments with practical training, IPSAS mastery, AI automation and sustainability reporting.</p>
                <div class="hero-buttons">
                    <a href="#events" class="btn btn-primary">View Events</a>
                    <a href="#contact" class="btn btn-outline">Contact Us</a>
                    <a href="sponsorship.php" class="btn btn-sponsorship">
                        <i class="fas fa-handshake"></i> Become a Sponsor
                    </a>
                </div>
            </div>
            <div class="hero-image reveal">
                <img src="assets/images/Event Home Page header 1 update.jpeg" alt="Government Executive Results CPD Series 2026 - Three Global Events">
            </div>
        </div>
    </section>

    <!-- Events Section - Background White -->
    <section id="events" class="events">
        <div class="container">
            <div class="section-header reveal">
                <h2>Government Executive | <span style="color: var(--primary-green);">Executive Results CPD Series 2026</span></h2>
            </div>

            <?php
            $siteEvents = [];
            try {
                $siteEvents = $pdo->query(
                    "SELECT * FROM events WHERE is_active = 1 ORDER BY sort_order, event_start_date, id"
                )->fetchAll();
            } catch (PDOException $e) {
                // Table not yet created — run setup_db.php
            }
            ?>
            <div class="events-grid">
            <?php foreach ($siteEvents as $idx => $ev): ?>
                <?php $delay = $idx > 0 ? 'transition-delay:' . ($idx * 0.1) . 's;' : ''; ?>
                <div class="event-card reveal" style="<?php echo $delay; ?>">
                    <div class="event-banner">
                        <?php if ($ev['image_path']): ?>
                            <img src="<?php echo htmlspecialchars($ev['image_path']); ?>"
                                 alt="<?php echo htmlspecialchars($ev['title']); ?>">
                        <?php else: ?>
                            <img src="assets/images/all cources.jpeg"
                                 alt="<?php echo htmlspecialchars($ev['title']); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="event-content">
                        <?php if ($ev['tagline']): ?>
                            <div class="event-tagline"><?php echo htmlspecialchars($ev['tagline']); ?></div>
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars($ev['title']); ?></h3>

                        <div class="event-details">
                            <div class="event-detail-item">
                                <i class="far fa-calendar-alt"></i>
                                <span><?php echo htmlspecialchars($ev['date_display']); ?></span>
                            </div>
                            <div class="event-detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($ev['location']); ?></span>
                            </div>
                        </div>

                        <?php
                        $earlyBirds = [];
                        if ($ev['early_bird_1_date'] && $ev['early_bird_1_pct']) {
                            $earlyBirds[] = ['pct' => $ev['early_bird_1_pct'], 'date' => $ev['early_bird_1_date']];
                        }
                        if ($ev['early_bird_2_date'] && $ev['early_bird_2_pct']) {
                            $earlyBirds[] = ['pct' => $ev['early_bird_2_pct'], 'date' => $ev['early_bird_2_date']];
                        }
                        if ($ev['early_bird_3_date'] && $ev['early_bird_3_pct']) {
                            $earlyBirds[] = ['pct' => $ev['early_bird_3_pct'], 'date' => $ev['early_bird_3_date']];
                        }
                        if ($earlyBirds):
                        ?>
                        <div class="early-bird">
                            <h4>Early Bird Savings</h4>
                            <ul>
                            <?php foreach ($earlyBirds as $eb): ?>
                                <li>Save <?php echo (int)$eb['pct']; ?>% — Register by <?php echo date('j F Y', strtotime($eb['date'])); ?></li>
                            <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <div class="event-price"><?php echo htmlspecialchars($ev['price']); ?></div>
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <a href="event.php?id=<?php echo $ev['id']; ?>" class="btn btn-outline" style="flex: 1; padding: 10px 0; text-align: center; border: 2px solid var(--primary-green); color: var(--primary-green); border-radius: 4px; text-decoration: none; font-weight: 600;">View Details</a>
                            <a href="event-registration.php?id=<?php echo $ev['id']; ?>" class="btn btn-primary btn-register" style="flex: 1; padding: 10px 0; text-align: center;" data-event="<?php echo htmlspecialchars($ev['title']); ?>" data-registration-url="event-registration.php?id=<?php echo $ev['id']; ?>">Register</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($siteEvents)): ?>
                <div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:#666;">
                    <i class="fas fa-calendar-times" style="font-size:48px;margin-bottom:16px;display:block;color:#ccc;"></i>
                    <p>No upcoming events at this time. Check back soon!</p>
                </div>
            <?php endif; ?>

            </div>
        </div>
    </section>

    <!-- About Section - Background Black -->
    <section id="about" class="about">
        <div class="container about-grid">
            <div class="about-text reveal">
                <h2>Decades of collective experience in public sector training</h2>
                <p>African governments face unprecedented pressure. Corruption drains resources. Fragmented systems create inefficiency. New technologies like AI arrive faster than skills can adapt. Sustainability standards and debt transparency demands are reshaping how the world evaluates public sector performance.</p>
                <p>Prosperminds exists to close these gaps. We deliver expert training and hands-on consultancy in Public Finance Management, international accounting standards, data analytics, AI automation, and sustainability reporting. Our programs are built for the realities of Africa's public sector—practical, accessible, and designed to create lasting institutional change.</p>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number" data-target="25">0</div>
                        <div class="stat-label">Years Experience</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" data-target="5000">0</div>
                        <div class="stat-label">Leaders Trained</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" data-target="15">0</div>
                        <div class="stat-label">Countries Reached</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" data-target="120">0</div>
                        <div class="stat-label">Courses Delivered</div>
                    </div>
                </div>
            </div>
            <div class="about-image reveal">
                <!-- Using hero2.jpg as requested -->
                <img src="assets/images/hero2.jpg" alt="Prosperminds Training Experience">
            </div>
        </div>
    </section>

    <!-- Services Section - Background Faded Green -->
    <section id="services" class="services">
        <div class="container">
            <div class="section-header reveal">
                <h2>We specialize in three <span style="color: var(--primary-green);">critical areas</span></h2>
            </div>
            
            <div class="services-grid">
                <!-- Service 1 -->
                <div class="service-card reveal">
                    <div class="service-image">
                        <img src="assets/images/service_pfm_africa.png" alt="PFM Mastery">
                    </div>
                    <div class="service-content">
                        <h3>PFM, IPSAS & IFRS Mastery</h3>
                        <p>Build the technical foundation your finance teams need.</p>
                        <a href="service-pfm.php" class="service-link">Read More <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>

                <!-- Service 2 -->
                <div class="service-card reveal" style="transition-delay: 0.1s;">
                    <div class="service-image">
                        <img src="assets/images/service_data_africa.png" alt="Data Analytics">
                    </div>
                    <div class="service-content">
                        <h3>Data Analytics & AI Automation</h3>
                        <p>Transform reporting from burden to strategic advantage.</p>
                        <a href="service-data.php" class="service-link">Read More <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>

                <!-- Service 3 -->
                <div class="service-card reveal" style="transition-delay: 0.2s;">
                    <div class="service-image">
                        <img src="assets/images/service_sustainability.png" alt="Sustainability Reporting">
                    </div>
                    <div class="service-content">
                        <h3>Sustainability Reporting</h3>
                        <p>Meet global standards while strengthening transparency.</p>
                        <a href="service-sustainability.php" class="service-link">Read More <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section - Background White -->
    <section id="testimonials" class="testimonials">
        <div class="container">
            <div class="section-header reveal">
                <h2>What Public Sector Leaders Say</h2>
            </div>
        </div>
        <div class="testimonials-slider">
            <div class="testimonials-track">
                <!-- Testimonial 1 -->
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <i class="fas fa-quote-left"></i>
                        <p>"Prosperminds transformed our approach to IPSAS. Their practical training bridged the gap between theory and execution for our entire finance department."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://flagcdn.com/w40/za.png" alt="South Africa" class="flag-icon">
                        <div>
                            <h4>David M.</h4>
                            <span>Director of Finance, South Africa</span>
                        </div>
                    </div>
                </div>
                <!-- Testimonial 2 -->
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <i class="fas fa-quote-left"></i>
                        <p>"The Data Analytics & AI Automation course was a game-changer. We have drastically reduced our reporting time and improved accuracy."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://flagcdn.com/w40/ke.png" alt="Kenya" class="flag-icon">
                        <div>
                            <h4>Sarah K.</h4>
                            <span>Chief Accountant, Kenya</span>
                        </div>
                    </div>
                </div>
                <!-- Testimonial 3 -->
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <i class="fas fa-quote-left"></i>
                        <p>"A truly executive-level experience. The strategies discussed for Budget Control have directly impacted our funding approach this fiscal year."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://flagcdn.com/w40/ng.png" alt="Nigeria" class="flag-icon">
                        <div>
                            <h4>Emmanuel O.</h4>
                            <span>Treasury Leader, Nigeria</span>
                        </div>
                    </div>
                </div>
                <!-- Testimonial 4 -->
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <i class="fas fa-quote-left"></i>
                        <p>"Their Sustainability Reporting modules are world-class. It helped our ministry align perfectly with new global environmental standards."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://flagcdn.com/w40/gh.png" alt="Ghana" class="flag-icon">
                        <div>
                            <h4>Grace A.</h4>
                            <span>Strategy Director, Ghana</span>
                        </div>
                    </div>
                </div>
                <!-- Testimonial 5 -->
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <i class="fas fa-quote-left"></i>
                        <p>"Exceptional facilitators with deep knowledge of African public sector challenges. The PFM Mastery program is highly recommended."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://flagcdn.com/w40/rw.png" alt="Rwanda" class="flag-icon">
                        <div>
                            <h4>Jean P.</h4>
                            <span>Auditor General, Rwanda</span>
                        </div>
                    </div>
                </div>
                <!-- Testimonial 6 -->
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <i class="fas fa-quote-left"></i>
                        <p>"We sent our entire senior team to the Clean Audit Compliance training, and the results have been immediate and profoundly impactful."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://flagcdn.com/w40/bw.png" alt="Botswana" class="flag-icon">
                        <div>
                            <h4>Thabo M.</h4>
                            <span>Revenue Manager, Botswana</span>
                        </div>
                    </div>
                </div>
                
                <!-- Duplicate for Infinite Scroll -->
                <!-- Testimonial 1 -->
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <i class="fas fa-quote-left"></i>
                        <p>"Prosperminds transformed our approach to IPSAS. Their practical training bridged the gap between theory and execution for our entire finance department."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://flagcdn.com/w40/za.png" alt="South Africa" class="flag-icon">
                        <div>
                            <h4>David M.</h4>
                            <span>Director of Finance, South Africa</span>
                        </div>
                    </div>
                </div>
                <!-- Testimonial 2 -->
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <i class="fas fa-quote-left"></i>
                        <p>"The Data Analytics & AI Automation course was a game-changer. We have drastically reduced our reporting time and improved accuracy."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://flagcdn.com/w40/ke.png" alt="Kenya" class="flag-icon">
                        <div>
                            <h4>Sarah K.</h4>
                            <span>Chief Accountant, Kenya</span>
                        </div>
                    </div>
                </div>
                <!-- Testimonial 3 -->
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <i class="fas fa-quote-left"></i>
                        <p>"A truly executive-level experience. The strategies discussed for Budget Control have directly impacted our funding approach this fiscal year."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://flagcdn.com/w40/ng.png" alt="Nigeria" class="flag-icon">
                        <div>
                            <h4>Emmanuel O.</h4>
                            <span>Treasury Leader, Nigeria</span>
                        </div>
                    </div>
                </div>
                <!-- Testimonial 4 -->
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <i class="fas fa-quote-left"></i>
                        <p>"Their Sustainability Reporting modules are world-class. It helped our ministry align perfectly with new global environmental standards."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://flagcdn.com/w40/gh.png" alt="Ghana" class="flag-icon">
                        <div>
                            <h4>Grace A.</h4>
                            <span>Strategy Director, Ghana</span>
                        </div>
                    </div>
                </div>
                <!-- Testimonial 5 -->
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <i class="fas fa-quote-left"></i>
                        <p>"Exceptional facilitators with deep knowledge of African public sector challenges. The PFM Mastery program is highly recommended."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://flagcdn.com/w40/rw.png" alt="Rwanda" class="flag-icon">
                        <div>
                            <h4>Jean P.</h4>
                            <span>Auditor General, Rwanda</span>
                        </div>
                    </div>
                </div>
                <!-- Testimonial 6 -->
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <i class="fas fa-quote-left"></i>
                        <p>"We sent our entire senior team to the Clean Audit Compliance training, and the results have been immediate and profoundly impactful."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://flagcdn.com/w40/bw.png" alt="Botswana" class="flag-icon">
                        <div>
                            <h4>Thabo M.</h4>
                            <span>Revenue Manager, Botswana</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section - Light Gray Background -->
    <section id="contact" class="contact">
        <div class="container">
            <div class="section-header reveal">
                <h2>Contact Us</h2>
            </div>
            
            <div class="contact-grid">
                <div class="contact-info reveal">
                    <div class="contact-info-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <h4>Email Us</h4>
                            <p>info@prosper-minds.com</p>
                        </div>
                    </div>
                    <div class="contact-info-item">
                        <i class="fas fa-phone-alt"></i>
                        <div>
                            <h4>Call Us</h4>
                            <p>+254 740 582302</p>
                            <p>+254 741 174909</p>
                        </div>
                    </div>
                    <div class="contact-info-item">
                        <i class="far fa-clock"></i>
                        <div>
                            <h4>Business Hours</h4>
                            <p>Mon - Fri: 8:00 AM - 5:00 PM</p>
                        </div>
                    </div>
                    
                    <!-- Google Map -->
                    <div style="margin-top: 30px; border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color);">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3988.8213506637794!2d36.8210917!3d-1.2808878!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x182f10d4ebab9d2f%3A0xdbeb6499f1afcd15!2sTwiga%20Towers!5e0!3m2!1sen!2ske!4v1778513207001!5m2!1sen!2ske" width="100%" height="250" style="border:0; display: block;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                </div>

                <div class="contact-form-wrapper reveal">
                    <form class="contact-form" action="#" method="POST">
                        <div class="form-group">
                            <input type="text" class="form-control" placeholder="Your Name" required>
                        </div>
                        <div class="form-group">
                            <input type="email" class="form-control" placeholder="Your Email" required>
                        </div>
                        <div class="form-group">
                            <input type="text" class="form-control" placeholder="Subject" required>
                        </div>
                        <div class="form-group">
                            <textarea class="form-control" placeholder="Your Message" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
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
                        <li><a href="#home">Home</a></li>
                        <li><a href="#events">Upcoming Events</a></li>
                        <li><a href="sponsorship.php">Sponsorship</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#services">Services</a></li>
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
                <p>&copy; <?php echo date('Y'); ?> Prosperminds. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Registration Modal -->
    <div class="modal-overlay" id="registrationModal">
        <div class="modal-content">
            <span class="modal-close"><i class="fas fa-times"></i></span>
            <div class="modal-header">
                <h2>Event Registration</h2>
                <p>Use the dedicated registration page to complete billing and delegate details.</p>
            </div>
            
            <div class="event-selected-info">
                <h4>Selected Event:</h4>
                <p id="display_event_name" style="color: var(--dark-green); font-weight: 600;">Event Name Here</p>
            </div>

            <div style="padding: 10px 0 4px;">
                <p style="color: #475569; line-height: 1.7; margin-bottom: 18px;">
                    Registration now includes billing address, invoice generation, and multiple delegates.
                    Please continue on the full registration page.
                </p>
                <a id="modalRegistrationLink" href="index.php#events" class="btn btn-primary" style="width: 100%; text-align: center;">
                    Continue To Registration Page
                </a>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>
