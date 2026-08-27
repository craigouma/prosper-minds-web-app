<?php require_once 'includes/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sponsorship Appeals 2026 – Prosperminds</title>
    <meta name="description" content="Partner with Prosperminds at Africa's premier public finance events in 2026. Sponsorship opportunities across Cape Town, Kuala Lumpur and Bali.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* ── Sponsorship page specific styles ─────────────── */

        /* Hero */
        .sp-hero {
            background: linear-gradient(135deg, #0a0a0a 0%, #0f2a14 50%, #111 100%);
            padding: 90px 0 70px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }
        .sp-hero::before {
            content: '';
            position: absolute;
            top: -50%; left: 50%;
            transform: translateX(-50%);
            width: 700px; height: 700px;
            background: radial-gradient(circle, rgba(0,177,64,0.12) 0%, transparent 65%);
            pointer-events: none;
        }
        .sp-hero .container { position: relative; z-index: 1; }
        .sp-hero-label {
            display: inline-block;
            background: var(--primary-green);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            padding: 5px 14px;
            border-radius: 3px;
            margin-bottom: 20px;
        }
        .sp-hero h1 {
            color: #fff;
            font-size: clamp(2.2rem, 5vw, 3.8rem);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 6px;
        }
        .sp-hero h1 span { color: var(--primary-green); }
        .sp-hero-sub {
            color: #aaa;
            font-size: 1.05rem;
            margin: 16px auto 32px;
            max-width: 580px;
            line-height: 1.7;
        }
        .sp-events-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 36px;
            justify-content: center;
        }
        .sp-event-pill {
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 50px;
            padding: 8px 18px;
            color: #ddd;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sp-event-pill i { color: var(--primary-green); font-size: 12px; }
        .sp-hero-btns { display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; }

        /* Why partner */
        .sp-why { padding: 70px 0; background: #fff; }
        .sp-why-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 30px; margin-top: 50px; }
        .sp-why-card {
            text-align: center;
            padding: 36px 28px;
            border-radius: 12px;
            background: var(--light-gray);
        }
        .sp-why-card .icon {
            width: 64px; height: 64px;
            background: rgba(0,177,64,.1);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 18px;
            font-size: 26px;
            color: var(--primary-green);
        }
        .sp-why-card h3 { font-size: 1.1rem; margin-bottom: 10px; }
        .sp-why-card p { color: #555; font-size: .93rem; line-height: 1.7; }

        /* Events section */
        .sp-events { padding: 70px 0; background: #f8faf8; }
        .sp-event-cards { display: grid; grid-template-columns: repeat(3,1fr); gap: 24px; margin-top: 40px; }
        .sp-event-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,.07);
            border: 2px solid transparent;
            transition: border-color .2s, transform .2s;
        }
        .sp-event-card:hover { border-color: var(--primary-green); transform: translateY(-4px); }
        .sp-event-card-header {
            background: #111;
            padding: 22px 24px;
            position: relative;
            overflow: hidden;
        }
        .sp-event-card-header::after {
            content: '';
            position: absolute;
            bottom: 0; right: 0;
            width: 100px; height: 100px;
            background: radial-gradient(circle, rgba(0,177,64,.25), transparent 70%);
        }
        .sp-event-card-header .event-num {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            color: var(--primary-green);
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .sp-event-card-header h3 {
            color: #fff;
            font-size: .95rem;
            line-height: 1.4;
            font-weight: 700;
        }
        .sp-event-card-body { padding: 20px 24px; }
        .sp-event-meta { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
        .sp-event-meta-item {
            display: flex; align-items: center; gap: 8px;
            font-size: .88rem; color: #444;
        }
        .sp-event-meta-item i { color: var(--primary-green); width: 16px; }
        .sp-event-card a { display: block; margin-top: 4px; }

        /* Gains + Audience */
        .sp-gains { padding: 70px 0; background: #fff; }
        .sp-gains-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: stretch; margin-top: 40px; }
        .sp-gains-left { display: flex; flex-direction: column; }
        .sp-gains-list { list-style: none; flex: 1; }
        .sp-gains-list li {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: .93rem; color: #333; line-height: 1.5;
        }
        .sp-gains-list li i { color: var(--primary-green); margin-top: 3px; flex-shrink: 0; }
        .sp-audience {
            background: #111;
            border-radius: 12px;
            padding: 32px;
            color: #fff;
            display: flex;
            flex-direction: column;
        }
        .sp-audience h3 { color: var(--primary-green); margin-bottom: 18px; font-size: 1.05rem; letter-spacing: .03em; }
        .sp-audience-tags { display: flex; flex-wrap: wrap; gap: 10px; }
        .sp-audience-tag {
            background: rgba(0,177,64,.15);
            border: 1px solid rgba(0,177,64,.3);
            color: #ccc;
            padding: 7px 14px;
            border-radius: 6px;
            font-size: .85rem;
        }

        /* Packages */
        .sp-packages { padding: 70px 0; background: #f0f7f2; }
        .sp-tier-cards { display: grid; grid-template-columns: repeat(2,1fr); gap: 24px; margin-top: 40px; }

        .sp-tier-cards { align-items: stretch; }
        .tier-card {
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,.09);
            display: flex;
            flex-direction: column;
        }
        .tier-card-platinum .tier-head { background: linear-gradient(135deg, #111 0%, #1a1a1a 100%); border-bottom: 3px solid #c9a800; }
        .tier-card-gold      .tier-head { background: linear-gradient(135deg, #1a1200 0%, #2a1e00 100%); border-bottom: 3px solid #d4a017; }
        .tier-card-silver    .tier-head { background: linear-gradient(135deg, #111 0%, #1c1c1c 100%); border-bottom: 3px solid #94a3b8; }
        .tier-card-bronze    .tier-head { background: linear-gradient(135deg, #150a00 0%, #1e1000 100%); border-bottom: 3px solid #cd7f32; }

        .tier-head { padding: 24px 28px 20px; position: relative; }
        .tier-badge {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 11px; font-weight: 700; letter-spacing: .1em;
            text-transform: uppercase; padding: 3px 10px; border-radius: 3px; margin-bottom: 12px;
        }
        .tier-card-platinum .tier-badge { background: rgba(201,168,0,.2); color: #c9a800; }
        .tier-card-gold      .tier-badge { background: rgba(212,160,23,.2); color: #d4a017; }
        .tier-card-silver    .tier-badge { background: rgba(148,163,184,.15); color: #94a3b8; }
        .tier-card-bronze    .tier-badge { background: rgba(205,127,50,.2); color: #cd7f32; }

        .tier-price { display: flex; align-items: baseline; gap: 6px; }
        .tier-price .amount { font-size: 2.4rem; font-weight: 800; color: #fff; }
        .tier-price .slots {
            font-size: .8rem; color: #888;
            background: rgba(255,255,255,.08);
            padding: 3px 8px; border-radius: 4px; margin-left: 8px;
        }
        .tier-body { background: #fff; padding: 24px 28px; flex: 1; display: flex; flex-direction: column; }
        .tier-benefits { list-style: none; flex: 1; }
        .tier-body > .btn { margin-top: auto; }
        .tier-benefits li {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 7px 0; font-size: .88rem; color: #333;
            border-bottom: 1px solid #f5f5f5;
        }
        .tier-benefits li:last-child { border: none; }
        .tier-benefits li i { color: var(--primary-green); flex-shrink: 0; margin-top: 3px; font-size: 12px; }

        /* Other tiers table */
        .sp-other-tiers { margin-top: 30px; }
        .other-tiers-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; margin-top: 24px; }
        .other-tier-card {
            background: #fff;
            border-radius: 10px;
            padding: 22px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
            border-top: 3px solid var(--primary-green);
        }
        .other-tier-card .ot-name { font-weight: 700; font-size: .95rem; color: #111; margin-bottom: 4px; }
        .other-tier-card .ot-price { font-size: 1.4rem; font-weight: 800; color: var(--primary-green); margin-bottom: 4px; }
        .other-tier-card .ot-slots { font-size: .78rem; color: #999; margin-bottom: 12px; }
        .other-tier-card ul { list-style: none; }
        .other-tier-card ul li { font-size: .82rem; color: #555; padding: 4px 0; border-bottom: 1px solid #f0f0f0; }
        .other-tier-card ul li::before { content: "✓ "; color: var(--primary-green); font-weight: 700; }

        /* Add-ons */
        .sp-addons { padding: 60px 0; background: #fff; }
        .addons-grid { display: grid; grid-template-columns: repeat(5,1fr); gap: 14px; margin-top: 30px; }
        .addon-card {
            background: #f8faf8;
            border: 1.5px solid #e0f0e8;
            border-radius: 10px;
            padding: 20px 16px;
            text-align: center;
        }
        .addon-card i { font-size: 24px; color: var(--primary-green); margin-bottom: 10px; display: block; }
        .addon-card .addon-name { font-weight: 700; font-size: .82rem; color: #111; margin-bottom: 4px; }
        .addon-card .addon-price { font-size: 1.1rem; font-weight: 800; color: var(--primary-green); }
        .addon-card .addon-slots { font-size: .75rem; color: #999; }
        .addon-card .addon-benefit { font-size: .78rem; color: #666; margin-top: 6px; line-height: 1.4; }

        /* Banner */
        .sp-banner {
            background: var(--primary-green);
            padding: 40px 0;
            text-align: center;
        }
        .sp-banner h2 { color: #fff; font-size: 1.8rem; margin-bottom: 6px; }
        .sp-banner p { color: rgba(255,255,255,.85); font-size: 1rem; }

        /* Form */
        .sp-form { padding: 80px 0; background: #f8faf8; }
        .sp-form-grid { display: grid; grid-template-columns: 1fr 480px; gap: 50px; align-items: stretch; }
        .sp-form-intro {
            display: flex;
            flex-direction: column;
            background: #fff;
            border-radius: 14px;
            padding: 40px;
            box-shadow: 0 8px 40px rgba(0,0,0,.07);
        }
        .sp-form-intro h2 { font-size: 2rem; font-weight: 800; margin-bottom: 16px; }
        .sp-form-intro h2 span { color: var(--primary-green); }
        .sp-form-intro p { color: #555; font-size: .95rem; line-height: 1.8; margin-bottom: 16px; }
        .sp-contact-items { display: flex; flex-direction: column; gap: 14px; margin-top: 28px; }
        .sp-contact-item { display: flex; align-items: center; gap: 12px; color: #333; font-size: .92rem; }
        .sp-contact-item i { width: 36px; height: 36px; background: rgba(0,177,64,.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary-green); flex-shrink: 0; }
        .sp-form-card {
            background: #fff;
            border-radius: 14px;
            padding: 36px;
            box-shadow: 0 8px 40px rgba(0,0,0,.1);
        }

        /* Section headers */
        .sp-section-tag {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 12px; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: var(--primary-green);
            margin-bottom: 12px;
        }
        .sp-section-tag::before {
            content: '';
            width: 24px; height: 2px;
            background: var(--primary-green);
        }
        .sp-section-h2 { font-size: clamp(1.6rem,3vw,2.2rem); font-weight: 800; color: #111; line-height: 1.2; }
        .sp-section-sub { color: #666; font-size: .95rem; margin-top: 10px; max-width: 560px; line-height: 1.7; }

        /* Responsive */
        @media (max-width: 900px) {
            .sp-why-grid, .sp-event-cards, .sp-tier-cards { grid-template-columns: 1fr; }
            .sp-gains-grid, .sp-form-grid { grid-template-columns: 1fr; }
            .other-tiers-grid, .addons-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 600px) {
            .other-tiers-grid, .addons-grid { grid-template-columns: 1fr; }
        }
    </style>
    <?php include __DIR__ . '/includes/google-tag.php'; ?>
</head>
<body>

    <!-- Header (reuse from main site) -->
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
                <a href="sponsorship.php" style="color:var(--primary-green);font-weight:600;">Sponsorship</a>
                <a href="index.php#contact">Contact</a>
            </nav>
            <div class="mobile-menu-btn"><i class="fas fa-bars"></i></div>
        </div>
    </header>

    <!-- ── HERO ─────────────────────────────────────────── -->
    <section class="sp-hero">
        <div class="container">
            <div class="sp-hero-label">Sponsorship Appeal 2026</div>
            <h1>Co-Author Africa's<br><span>Public Finance Future</span></h1>
            <p class="sp-hero-sub">
                Join Africa's most influential public finance events as a strategic partner.
                Three landmark conferences. Three cities. One transformational mission.
            </p>

            <div class="sp-events-bar">
                <div class="sp-event-pill">
                    <i class="fas fa-map-marker-alt"></i>
                    Oct 2026 · Cape Town, South Africa
                </div>
                <div class="sp-event-pill">
                    <i class="fas fa-map-marker-alt"></i>
                    Nov 2026 · Kuala Lumpur, Malaysia
                </div>
                <div class="sp-event-pill">
                    <i class="fas fa-map-marker-alt"></i>
                    Dec 2026 · Bali, Indonesia
                </div>
            </div>

            <div class="sp-hero-btns">
                <a href="#apply" class="btn btn-primary" style="padding:14px 32px;font-size:1rem;">
                    <i class="fas fa-handshake"></i> Become a Partner
                </a>
                <a href="#packages" class="btn btn-outline" style="padding:14px 32px;font-size:1rem;border-color:rgba(255,255,255,.3);color:#fff;">
                    <i class="fas fa-tags"></i> View Packages
                </a>
            </div>
        </div>
    </section>

    <!-- ── WHY PARTNER ───────────────────────────────────── -->
    <section class="sp-why">
        <div class="container">
            <div class="sp-section-tag">Why This Moment Matters</div>
            <h2 class="sp-section-h2">Traditional marketing won't<br>get you into that room. <span style="color:var(--primary-green);">This event will.</span></h2>
            <p class="sp-section-sub">Africa's public sector is transforming faster than ever. The professionals in the room are not looking for service providers — they are looking for trusted partners who can support real implementation.</p>

            <div class="sp-why-grid">
                <div class="sp-why-card">
                    <div class="icon"><i class="fas fa-bullhorn"></i></div>
                    <h3>While others advertise…</h3>
                    <p>You will be <strong>remembered</strong>. Your brand will be embedded into the experience of Africa's most influential finance leaders.</p>
                </div>
                <div class="sp-why-card">
                    <div class="icon"><i class="fas fa-handshake"></i></div>
                    <h3>While others pitch…</h3>
                    <p>You will be <strong>partnering</strong>. Direct B2G engagement with decision-makers who implement policy and control budgets.</p>
                </div>
                <div class="sp-why-card">
                    <div class="icon"><i class="fas fa-globe-africa"></i></div>
                    <h3>While others wait…</h3>
                    <p>You will already be <strong>part of Africa's real system change</strong> — shaping the conversation, not watching it from the outside.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ── THE THREE EVENTS ───────────────────────────────── -->
    <section class="sp-events" id="events">
        <div class="container">
            <div class="sp-section-tag">Three Events · 2026</div>
            <h2 class="sp-section-h2">Choose Your Conference<br><span style="color:var(--primary-green);">or Sponsor All Three</span></h2>
            <p class="sp-section-sub">Each event draws hundreds of the continent's most senior public finance officials. Sponsor one or all three for maximum reach across the year.</p>

            <div class="sp-event-cards">
                <!-- Event 1 -->
                <div class="sp-event-card">
                    <div class="sp-event-card-header">
                        <div class="event-num">Event 01 · October 2026</div>
                        <h3>Smart PFM & IPSAS Future Ready Finance Leaders Course</h3>
                    </div>
                    <div class="sp-event-card-body">
                        <div class="sp-event-meta">
                            <div class="sp-event-meta-item"><i class="far fa-calendar-alt"></i> 19–23 October 2026</div>
                            <div class="sp-event-meta-item"><i class="fas fa-map-marker-alt"></i> Cape Town, South Africa</div>
                            <div class="sp-event-meta-item"><i class="fas fa-users"></i> Smart PFM, IPSAS, Data Analytics, AI</div>
                        </div>
                        <a href="#apply" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;">
                            <i class="fas fa-star"></i> Sponsor This Event
                        </a>
                    </div>
                </div>

                <!-- Event 2 -->
                <div class="sp-event-card">
                    <div class="sp-event-card-header">
                        <div class="event-num">Event 02 · November 2026</div>
                        <h3>IPSAS Success & Clean Audit Compliance Training</h3>
                    </div>
                    <div class="sp-event-card-body">
                        <div class="sp-event-meta">
                            <div class="sp-event-meta-item"><i class="far fa-calendar-alt"></i> 16–20 November 2026</div>
                            <div class="sp-event-meta-item"><i class="fas fa-map-marker-alt"></i> Kuala Lumpur, Malaysia</div>
                            <div class="sp-event-meta-item"><i class="fas fa-users"></i> IPSAS, Zero-Failure Reporting, Clean Audits</div>
                        </div>
                        <a href="#apply" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;">
                            <i class="fas fa-star"></i> Sponsor This Event
                        </a>
                    </div>
                </div>

                <!-- Event 3 -->
                <div class="sp-event-card">
                    <div class="sp-event-card-header">
                        <div class="event-num">Event 03 · December 2026</div>
                        <h3>Budget Control, Revenue Growth & PFM Funding Breakthrough Conference</h3>
                    </div>
                    <div class="sp-event-card-body">
                        <div class="sp-event-meta">
                            <div class="sp-event-meta-item"><i class="far fa-calendar-alt"></i> 7–11 December 2026</div>
                            <div class="sp-event-meta-item"><i class="fas fa-map-marker-alt"></i> Bali, Indonesia</div>
                            <div class="sp-event-meta-item"><i class="fas fa-users"></i> Cash Control, IPSAS, Funding Strategies</div>
                        </div>
                        <a href="#apply" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;">
                            <i class="fas fa-star"></i> Sponsor This Event
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ── WHAT YOU GAIN + WHO ATTENDS ────────────────────── -->
    <section class="sp-gains">
        <div class="container">
            <div class="sp-gains-grid">
                <div class="sp-gains-left">
                    <div class="sp-section-tag">What You'll Gain</div>
                    <h2 class="sp-section-h2">More Than a Sponsorship —<br><span style="color:var(--primary-green);">a Partnership</span></h2>
                    <p style="color:#555;margin:14px 0 24px;line-height:1.8;">At Prosperminds, we do not sell packages. We build partnerships. We will align this platform to your goals.</p>
                    <ul class="sp-gains-list">
                        <li><i class="fas fa-check-circle"></i> Strong visibility before, during, and after the event</li>
                        <li><i class="fas fa-check-circle"></i> Direct access to public finance practitioners and decision influencers</li>
                        <li><i class="fas fa-check-circle"></i> Business-to-Government (B2G) engagement opportunities</li>
                        <li><i class="fas fa-check-circle"></i> Thought leadership through sessions and workshops</li>
                        <li><i class="fas fa-check-circle"></i> Brand positioning as a trusted implementation partner</li>
                        <li><i class="fas fa-check-circle"></i> Enter new African government markets</li>
                        <li><i class="fas fa-check-circle"></i> Showcase your solutions to the leaders who implement policy</li>
                        <li><i class="fas fa-check-circle"></i> A clear message: you are part of Africa's transformation</li>
                    </ul>
                </div>
                <div class="sp-audience">
                    <h3><i class="fas fa-users" style="margin-right:8px;"></i>WHO WILL BE IN THE ROOM</h3>
                    <p style="color:#aaa;font-size:.88rem;margin-bottom:20px;line-height:1.7;">Hundreds of the world's most influential public sector and government leaders:</p>
                    <div class="sp-audience-tags">
                        <span class="sp-audience-tag">Finance Officers</span>
                        <span class="sp-audience-tag">Accountants</span>
                        <span class="sp-audience-tag">Auditors</span>
                        <span class="sp-audience-tag">Budget Controllers</span>
                        <span class="sp-audience-tag">Treasury Leaders</span>
                        <span class="sp-audience-tag">Revenue Managers</span>
                        <span class="sp-audience-tag">Strategy Directors</span>
                        <span class="sp-audience-tag">Decision Makers</span>
                        <span class="sp-audience-tag">Policy Implementers</span>
                    </div>
                    <div style="margin-top:28px;padding-top:22px;border-top:1px solid rgba(255,255,255,.1);">
                        <p style="font-size:.8rem;color:var(--primary-green);font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Our promise to you</p>
                        <p style="color:#fff;font-size:1.15rem;font-weight:700;font-style:italic;">"Relevant. Reliable. Convenient."</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ── SPONSORSHIP PACKAGES ───────────────────────────── -->
    <section class="sp-packages" id="packages">
        <div class="container">
            <div style="text-align:center;margin-bottom:10px;">
                <div class="sp-section-tag" style="justify-content:center;">Sponsorship Packages Matrix</div>
                <h2 class="sp-section-h2" style="text-align:center;">Choose Your Tier</h2>
                <p class="sp-section-sub" style="margin:10px auto 0;text-align:center;">Investment levels to match your goals and budget. All packages are available across all three events.</p>
            </div>

            <!-- Premium Tiers -->
            <div class="sp-tier-cards">
                <!-- PLATINUM -->
                <div class="tier-card tier-card-platinum">
                    <div class="tier-head">
                        <div class="tier-badge"><i class="fas fa-crown"></i> Platinum</div>
                        <div class="tier-price">
                            <span class="amount">$15,000</span>
                            <span class="slots">3 slots available</span>
                        </div>
                    </div>
                    <div class="tier-body">
                        <ul class="tier-benefits">
                            <li><i class="fas fa-check"></i> Keynote + plenary speaking slot</li>
                            <li><i class="fas fa-check"></i> Branding across all platforms (digital, print, press)</li>
                            <li><i class="fas fa-check"></i> Sponsor video aired daily</li>
                            <li><i class="fas fa-check"></i> 5 VIP passes</li>
                            <li><i class="fas fa-check"></i> Logo on delegate lanyards &amp; bags</li>
                            <li><i class="fas fa-check"></i> VIP roundtable with government leaders</li>
                            <li><i class="fas fa-check"></i> Full-page ad in programme &amp; post-event report</li>
                            <li><i class="fas fa-check"></i> Prime exhibition space</li>
                        </ul>
                        <a href="#apply" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:20px;">Apply Now</a>
                    </div>
                </div>

                <!-- GOLD -->
                <div class="tier-card tier-card-gold">
                    <div class="tier-head">
                        <div class="tier-badge"><i class="fas fa-medal"></i> Gold</div>
                        <div class="tier-price">
                            <span class="amount" style="color:#d4a017;">$7,500</span>
                            <span class="slots">5 slots available</span>
                        </div>
                    </div>
                    <div class="tier-body">
                        <ul class="tier-benefits">
                            <li><i class="fas fa-check"></i> Host &amp; brand Data / IPSAS theme session</li>
                            <li><i class="fas fa-check"></i> 3 VIP passes</li>
                            <li><i class="fas fa-check"></i> Branding on event app &amp; session screens</li>
                            <li><i class="fas fa-check"></i> Mid-tier exhibition space</li>
                            <li><i class="fas fa-check"></i> Half-page ad in programme</li>
                            <li><i class="fas fa-check"></i> Joint press feature with Prosperminds</li>
                            <li><i class="fas fa-check"></i> Speaking role</li>
                            <li><i class="fas fa-check"></i> Social media spotlight campaign</li>
                        </ul>
                        <a href="#apply" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:20px;">Apply Now</a>
                    </div>
                </div>

                <!-- SILVER -->
                <div class="tier-card tier-card-silver">
                    <div class="tier-head">
                        <div class="tier-badge"><i class="fas fa-award"></i> Silver</div>
                        <div class="tier-price">
                            <span class="amount">$5,000</span>
                            <span class="slots">10 slots available</span>
                        </div>
                    </div>
                    <div class="tier-body">
                        <ul class="tier-benefits">
                            <li><i class="fas fa-check"></i> Speaking role</li>
                            <li><i class="fas fa-check"></i> 3 passes</li>
                            <li><i class="fas fa-check"></i> Logo on key branding points</li>
                            <li><i class="fas fa-check"></i> Half-page ad in programme</li>
                            <li><i class="fas fa-check"></i> Featured in Prosperminds publications</li>
                            <li><i class="fas fa-check"></i> Website branding</li>
                            <li><i class="fas fa-check"></i> Social media spotlight</li>
                            <li><i class="fas fa-check"></i> Exhibition space</li>
                        </ul>
                        <a href="#apply" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:20px;">Apply Now</a>
                    </div>
                </div>

                <!-- BRONZE -->
                <div class="tier-card tier-card-bronze">
                    <div class="tier-head">
                        <div class="tier-badge"><i class="fas fa-ribbon"></i> Bronze</div>
                        <div class="tier-price">
                            <span class="amount" style="color:#cd7f32;">$2,500</span>
                            <span class="slots">10 slots available</span>
                        </div>
                    </div>
                    <div class="tier-body">
                        <ul class="tier-benefits">
                            <li><i class="fas fa-check"></i> Speaker / moderator role</li>
                            <li><i class="fas fa-check"></i> 3 passes</li>
                            <li><i class="fas fa-check"></i> Logo in programme</li>
                            <li><i class="fas fa-check"></i> Website branding</li>
                            <li><i class="fas fa-check"></i> Social media spotlight</li>
                            <li><i class="fas fa-check"></i> Exhibition space</li>
                        </ul>
                        <a href="#apply" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:20px;">Apply Now</a>
                    </div>
                </div>
            </div>

            <!-- Other tiers -->
            <div class="sp-other-tiers">
                <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:4px;color:#111;">Specialised & Entry Packages</h3>
                <p style="color:#666;font-size:.88rem;margin-bottom:0;">Focused sponsorship opportunities at $1,000 each.</p>
                <div class="other-tiers-grid">
                    <div class="other-tier-card">
                        <div class="ot-name"><i class="fas fa-utensils" style="color:var(--primary-green);margin-right:6px;"></i>Gala Dinner</div>
                        <div class="ot-price">$1,000</div>
                        <div class="ot-slots">2 slots available</div>
                        <ul>
                            <li>Exclusive gala dinner branding</li>
                            <li>Address guests at dinner</li>
                            <li>Logo in entertainment zones</li>
                            <li>2 passes · Website branding</li>
                        </ul>
                    </div>
                    <div class="other-tier-card">
                        <div class="ot-name"><i class="fas fa-mobile-alt" style="color:var(--primary-green);margin-right:6px;"></i>Digital Experience</div>
                        <div class="ot-price">$1,000</div>
                        <div class="ot-slots">2 slots available</div>
                        <ul>
                            <li>Sponsored push notifications</li>
                            <li>Logo on session screens</li>
                            <li>1 pass · Website branding</li>
                            <li>Exhibition space</li>
                        </ul>
                    </div>
                    <div class="other-tier-card">
                        <div class="ot-name"><i class="fas fa-store" style="color:var(--primary-green);margin-right:6px;"></i>Exhibitor</div>
                        <div class="ot-price">$1,000</div>
                        <div class="ot-slots">10 slots available</div>
                        <ul>
                            <li>Name in event materials</li>
                            <li>1 pass · Website branding</li>
                            <li>Exhibition space</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ── OPTIONAL ADD-ONS ───────────────────────────────── -->
    <section class="sp-addons">
        <div class="container">
            <div class="sp-section-tag">Optional Add-Ons</div>
            <h2 class="sp-section-h2">Enhance Your Presence</h2>
            <p class="sp-section-sub">Add targeted visibility touchpoints to any sponsorship package for just $500 each.</p>
            <div class="addons-grid">
                <div class="addon-card">
                    <i class="fas fa-id-badge"></i>
                    <div class="addon-name">Lanyard Sponsor</div>
                    <div class="addon-price">$500</div>
                    <div class="addon-slots">3 slots</div>
                    <div class="addon-benefit">Logo on all delegate lanyards</div>
                </div>
                <div class="addon-card">
                    <i class="fas fa-shopping-bag"></i>
                    <div class="addon-name">Delegate Bag</div>
                    <div class="addon-price">$500</div>
                    <div class="addon-slots">4 slots</div>
                    <div class="addon-benefit">Logo on all delegate bags</div>
                </div>
                <div class="addon-card">
                    <i class="fas fa-wifi"></i>
                    <div class="addon-name">Wi-Fi Sponsor</div>
                    <div class="addon-price">$500</div>
                    <div class="addon-slots">2 slots</div>
                    <div class="addon-benefit">Splash page branding + custom password</div>
                </div>
                <div class="addon-card">
                    <i class="fas fa-tablet-alt"></i>
                    <div class="addon-name">Mobile App</div>
                    <div class="addon-price">$500</div>
                    <div class="addon-slots">2 slots</div>
                    <div class="addon-benefit">Exclusive branding + push alerts</div>
                </div>
                <div class="addon-card">
                    <i class="fas fa-tint"></i>
                    <div class="addon-name">Water Station</div>
                    <div class="addon-price">$500</div>
                    <div class="addon-slots">2 slots</div>
                    <div class="addon-benefit">Branded eco-stations throughout venue</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ── BANNER ─────────────────────────────────────────── -->
    <div class="sp-banner">
        <div class="container">
            <h2>Ready to become Africa's partner in transformation?</h2>
            <p>Let's schedule a short call to explore how we can partner. Every day of delay is an opportunity lost.</p>
            <a href="#apply" class="btn" style="background:#fff;color:var(--primary-green);font-weight:700;padding:12px 32px;margin-top:18px;display:inline-flex;gap:8px;align-items:center;">
                <i class="fas fa-paper-plane"></i> Send Sponsorship Appeal
            </a>
        </div>
    </div>

    <!-- ── SPONSORSHIP APPLICATION FORM ──────────────────── -->
    <section class="sp-form" id="apply">
        <div class="container">
            <div class="sp-form-grid">
                <div class="sp-form-intro">
                    <div class="sp-section-tag">Apply Now</div>
                    <h2>Let's Build This<br><span>Partnership Together</span></h2>
                    <p>Send us your sponsorship appeal and our team will respond within 48 hours to schedule a discovery call. We do not sell packages — we build partnerships tailored to your organisation's goals.</p>
                    <p>Once confirmed, sponsors will be connected to the official sponsors management team for preparations and setup coordination.</p>

                    <div class="sp-contact-items">
                        <div class="sp-contact-item">
                            <i class="fas fa-envelope"></i>
                            <div><strong>Email us directly</strong><br>info@prosper-minds.com</div>
                        </div>
                        <div class="sp-contact-item">
                            <i class="fas fa-phone-alt"></i>
                            <div><strong>Call us</strong><br>+254 740 582302 &nbsp;|&nbsp; +254 741 174909</div>
                        </div>
                        <div class="sp-contact-item">
                            <i class="fas fa-globe"></i>
                            <div><strong>Website</strong><br>www.prosper-minds.com</div>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <div class="sp-form-card">
                    <h3 style="font-size:1.2rem;font-weight:700;margin-bottom:6px;">Sponsorship Enquiry</h3>
                    <p style="color:#777;font-size:.88rem;margin-bottom:24px;">Fill in the form and we'll be in touch within 48 hours.</p>

                    <div id="sp-form-success" style="display:none;" class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        Thank you! Your enquiry has been sent. We will contact you within 48 hours.
                    </div>
                    <div id="sp-form-error" style="display:none;" class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span id="sp-error-msg">Something went wrong. Please try again.</span>
                    </div>

                    <form id="sponsorshipForm">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Organisation / Company *</label>
                            <input type="text" name="organisation" class="form-control" required>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                            <div class="form-group">
                                <label>Email Address *</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" class="form-control" placeholder="+1 234 567 8900">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Country</label>
                            <input type="text" name="country" class="form-control" placeholder="Your country">
                        </div>

                        <div class="form-group">
                            <label>Which event(s) are you interested in? *</label>
                            <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px;">
                                <label style="display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer;">
                                    <input type="checkbox" name="events[]" value="Cape Town – Oct 2026" style="accent-color:var(--primary-green);">
                                    Cape Town, South Africa · Oct 2026
                                </label>
                                <label style="display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer;">
                                    <input type="checkbox" name="events[]" value="Kuala Lumpur – Nov 2026" style="accent-color:var(--primary-green);">
                                    Kuala Lumpur, Malaysia · Nov 2026
                                </label>
                                <label style="display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer;">
                                    <input type="checkbox" name="events[]" value="Bali – Dec 2026" style="accent-color:var(--primary-green);">
                                    Bali, Indonesia · Dec 2026
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Sponsorship Tier of Interest</label>
                            <select name="tier" class="form-control">
                                <option value="">Select a tier…</option>
                                <option>Platinum – $15,000</option>
                                <option>Gold – $7,500</option>
                                <option>Silver – $5,000</option>
                                <option>Bronze – $2,500</option>
                                <option>Gala Dinner – $1,000</option>
                                <option>Digital Experience – $1,000</option>
                                <option>Exhibitor – $1,000</option>
                                <option>Optional Add-On only – $500</option>
                                <option>Not sure yet – please advise</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom:24px;">
                            <label>Message / Goals</label>
                            <textarea name="message" class="form-control" style="height:100px;"
                                placeholder="Tell us about your organisation's goals and what you hope to achieve through this partnership…"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px;font-size:1rem;">
                            <i class="fas fa-paper-plane"></i> Send Sponsorship Appeal
                        </button>
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
                    <div class="footer-logo"><img src="assets/images/fisrt-logo.png" alt="Prosperminds"></div>
                    <p class="footer-desc">Co-authoring Africa's Public Finance Future through executive training, IPSAS mastery, AI automation and sustainability reporting.</p>
                </div>
                <div class="footer-col">
                    <h4 class="footer-heading">Events 2026</h4>
                    <ul class="footer-links">
                        <li><a href="#events">Cape Town · Oct 2026</a></li>
                        <li><a href="#events">Kuala Lumpur · Nov 2026</a></li>
                        <li><a href="#events">Bali · Dec 2026</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4 class="footer-heading">Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="index.php#events">Upcoming Events</a></li>
                        <li><a href="sponsorship.php">Sponsorship</a></li>
                        <li><a href="index.php#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4 class="footer-heading">Contact</h4>
                    <ul class="footer-links">
                        <li><a href="mailto:info@prosper-minds.com">info@prosper-minds.com</a></li>
                        <li><a href="tel:+254740582302">+254 740 582302</a></li>
                        <li><a href="tel:+254741174909">+254 741 174909</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Prosperminds. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
    <script>
    document.getElementById('sponsorshipForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const events = [...document.querySelectorAll('input[name="events[]"]:checked')];
        if (events.length === 0) {
            document.getElementById('sp-form-error').style.display = 'flex';
            document.getElementById('sp-error-msg').textContent = 'Please select at least one event.';
            return;
        }

        document.getElementById('sp-form-success').style.display = 'none';
        document.getElementById('sp-form-error').style.display   = 'none';

        const btn = this.querySelector('button[type="submit"]');
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
        btn.disabled  = true;

        const data = new FormData(this);

        fetch('process-sponsorship.php', { method: 'POST', body: data })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    document.getElementById('sp-form-success').style.display = 'flex';
                    this.reset();
                } else {
                    document.getElementById('sp-form-error').style.display = 'flex';
                    document.getElementById('sp-error-msg').textContent = d.message || 'Something went wrong.';
                }
            })
            .catch(() => {
                document.getElementById('sp-form-error').style.display = 'flex';
                document.getElementById('sp-error-msg').textContent = 'Network error. Please try again.';
            })
            .finally(() => { btn.innerHTML = orig; btn.disabled = false; });
    });
    </script>
</body>
</html>
