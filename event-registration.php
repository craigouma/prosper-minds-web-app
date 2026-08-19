<?php
require_once 'includes/config.php';
require_once 'includes/csrf.php';

// Before any output, so PHP can still send the session cookie that the CSRF
// token is bound to.
csrfEnsureSession();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND is_active = 1");
$stmt->execute([$id]);
$event = $stmt->fetch();

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background-color: #f4f7f6; }
        .reg-header {
            background: linear-gradient(135deg, var(--dark-green), var(--primary-green));
            color: white;
            padding: 60px 20px;
            text-align: center;
        }
        .reg-container {
            max-width: 800px;
            margin: -30px auto 60px;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; display: none; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .success-panel {
            display: none;
            background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%);
            border: 1px solid #bbf7d0;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
        }
        .success-panel h3 {
            color: var(--dark-green);
            font-size: 1.6rem;
            margin-bottom: 12px;
        }
        .success-panel p {
            color: #475569;
            margin-bottom: 12px;
            line-height: 1.7;
        }
        .success-contact {
            margin-top: 18px;
            padding: 18px;
            background: #ffffff;
            border: 1px solid #dcfce7;
            border-radius: 12px;
        }
        .success-contact strong {
            color: var(--dark-green);
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
            </nav>
        </div>
    </header>

    <div class="reg-header">
        <div class="container">
            <h1 style="font-size: 2.5rem; margin-bottom: 10px;">Event Registration</h1>
            <p>Reserve your seat for this exclusive event</p>
        </div>
    </div>

    <div class="container">
        <div class="reg-container">
            
            <div style="background: #f8fafc; padding: 20px; border-radius: 8px; border-left: 4px solid var(--primary-green); margin-bottom: 30px;">
                <h4 style="color: #64748b; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Selected Event</h4>
                <h2 style="color: var(--dark-green); font-size: 1.5rem;"><?php echo htmlspecialchars($event['title']); ?></h2>
                <div style="margin-top: 10px; color: #475569; font-size: 0.95rem;">
                    <i class="far fa-calendar-alt"></i> <?php echo htmlspecialchars($event['date_display']); ?> &nbsp;|&nbsp;
                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?>
                </div>
                <div style="margin-top: 12px; display: inline-flex; align-items: center; gap: 8px; background: #ecfdf5; color: #065f46; padding: 8px 12px; border-radius: 999px; font-size: 0.92rem; font-weight: 600;">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Invoice will be emailed automatically after registration
                </div>
            </div>

            <div id="formMsg" class="alert"></div>

            <form id="standaloneRegForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="event_id" value="<?php echo (int) $event['id']; ?>">
                <input type="hidden" name="event_name" value="<?php echo htmlspecialchars($event['title']); ?>">
                
                <h3 style="margin: 0 0 18px; color: var(--dark-green);">Billing Contact</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Contact First Name *</label>
                        <input type="text" name="first_name" id="billing_first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Last Name *</label>
                        <input type="text" name="last_name" id="billing_last_name" class="form-control" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="phone" class="form-control" pattern="[\d\+\-\s\(\)]{8,20}" title="Valid phone number (8-20 digits/characters)" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address *</label>
                        <input type="email" name="email" id="billing_email" class="form-control" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Company / Organization *</label>
                        <input type="text" name="organization" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Country *</label>
                        <input type="text" name="country" class="form-control" required placeholder="e.g. South Africa, UAE...">
                    </div>
                </div>

                <div class="form-group">
                    <label>Billing Address *</label>
                    <textarea name="address" class="form-control" style="height: 90px;" required placeholder="Street, city, postal code, country"></textarea>
                </div>

                <h3 style="margin: 30px 0 18px; color: var(--dark-green);">Attendees</h3>
                <p style="margin-top: -5px; color: #64748b; font-size: 0.95rem;">Add every delegate being registered under this invoice. The total invoice amount will update automatically.</p>

                <div id="attendeesSummary" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 18px; margin-bottom: 20px; display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                    <div>
                        <div style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b;">Per Delegate</div>
                        <div style="font-size: 1.1rem; font-weight: 700; color: var(--dark-green);"><?php echo htmlspecialchars($event['price']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b;">Delegates</div>
                        <div id="delegateCount" style="font-size: 1.1rem; font-weight: 700; color: var(--dark-green);">1</div>
                    </div>
                    <div>
                        <div style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b;">Invoice Total</div>
                        <div id="invoiceTotal" style="font-size: 1.1rem; font-weight: 700; color: var(--dark-green);"><?php echo htmlspecialchars($event['price']); ?></div>
                    </div>
                </div>

                <div id="attendeesContainer"></div>

                <button type="button" id="addAttendeeBtn" class="btn btn-outline" style="margin-bottom: 26px;">
                    <i class="fas fa-user-plus"></i> Add Another Attendee
                </button>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" class="form-control">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Meal Preference</label>
                        <select name="meal_preference" class="form-control">
                            <option value="">Select Preference</option>
                            <option value="Standard">Standard</option>
                            <option value="Vegetarian">Vegetarian</option>
                            <option value="Vegan">Vegan</option>
                            <option value="Halal">Halal</option>
                            <option value="Gluten-Free">Gluten-Free</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Topics Interested in Future</label>
                    <textarea name="future_topics" class="form-control" style="height: 80px;" placeholder="e.g. AI in Finance, Leadership..."></textarea>
                </div>

                <div class="form-group" style="display: flex; align-items: flex-start; gap: 10px; margin-top: 20px;">
                    <input type="checkbox" name="consent" id="consent" required style="margin-top: 5px;">
                    <label for="consent" style="font-size: 0.95rem; color: #555; font-weight: 400;">I consent to Prosperminds processing my data for event registration and to receive related communications.</label>
                </div>

                <button type="submit" class="btn btn-primary" id="btnSubmit" style="width: 100%; padding: 15px; font-size: 1.1rem; margin-top: 10px;">
                    Submit Registration
                </button>
            </form>

            <div id="registrationSuccessPanel" class="success-panel">
                <h3>Registration Received</h3>
                <p>Thank you for registering. Your seat request has been received successfully.</p>
                <p>Our customer service team or a Prosperminds representative will get in touch with you shortly to support you with the next steps.</p>
                <div class="success-contact">
                    <p style="margin-bottom:8px;"><strong>Need assistance right away?</strong></p>
                    <p style="margin-bottom:6px;">Call: <strong>+254 740 582302</strong> or <strong>+254 741 174909</strong></p>
                    <p style="margin-bottom:0;">Email: <a href="mailto:info@prosper-minds.com" style="color:var(--primary-green);font-weight:600;">info@prosper-minds.com</a></p>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="container" style="text-align: center; padding: 20px;">
            <p style="color: #64748b;">&copy; <?php echo date('Y'); ?> Prosperminds. All rights reserved.</p>
        </div>
    </footer>

    <script>
        document.getElementById('standaloneRegForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('btnSubmit');
            const msgBox = document.getElementById('formMsg');
            const successPanel = document.getElementById('registrationSuccessPanel');
            const formData = new FormData(this);
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            btn.disabled = true;
            msgBox.style.display = 'none';
            msgBox.className = 'alert';
            
            fetch('process-registration.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.innerHTML = 'Submit Registration';
                btn.disabled = false;
                
                msgBox.style.display = 'block';
                if (data.success) {
                    msgBox.className = 'alert alert-success';
                    msgBox.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                    this.style.display = 'none';
                    msgBox.style.display = 'none';
                    successPanel.style.display = 'block';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    msgBox.className = 'alert alert-danger';
                    msgBox.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                }
            })
            .catch(err => {
                btn.innerHTML = 'Submit Registration';
                btn.disabled = false;
                
                msgBox.style.display = 'block';
                msgBox.className = 'alert alert-danger';
                msgBox.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error. Please try again.';
            });
        });

        const attendeesContainer = document.getElementById('attendeesContainer');
        const addAttendeeBtn = document.getElementById('addAttendeeBtn');
        const delegateCount = document.getElementById('delegateCount');
        const invoiceTotal = document.getElementById('invoiceTotal');
        const billingFirstName = document.getElementById('billing_first_name');
        const billingLastName = document.getElementById('billing_last_name');
        const billingEmail = document.getElementById('billing_email');
        const unitPriceText = <?php echo json_encode($event['price']); ?>;
        const unitAmountMatch = unitPriceText.match(/(\d[\d,]*(?:\.\d{1,2})?)/);
        const unitAmount = unitAmountMatch ? parseFloat(unitAmountMatch[1].replace(/,/g, '')) : 0;
        const unitCurrencyMatch = unitPriceText.match(/([A-Z]{3})/i);
        const unitCurrency = unitCurrencyMatch ? unitCurrencyMatch[1].toUpperCase() : 'USD';

        function renderInvoiceSummary() {
            const cards = attendeesContainer.querySelectorAll('.attendee-card');
            const count = cards.length || 1;
            delegateCount.textContent = String(count);
            invoiceTotal.textContent = `${unitCurrency} ${Number(unitAmount * count).toFixed(2)}`;
        }

        function attendeeCardTemplate(index) {
            const card = document.createElement('div');
            card.className = 'attendee-card';
            card.style.cssText = 'border: 1px solid #dbe4ea; border-radius: 12px; padding: 20px; margin-bottom: 16px; background: #fcfdfd;';
            card.innerHTML = `
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;">
                    <h4 style="margin:0;color:var(--dark-green);">Attendee ${index + 1}</h4>
                    ${index > 0 ? '<button type="button" class="remove-attendee btn btn-outline" style="padding:8px 12px;">Remove</button>' : ''}
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="attendees[first_name][]" class="form-control attendee-first-name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="attendees[last_name][]" class="form-control attendee-last-name" required>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="attendees[email][]" class="form-control attendee-email">
                    </div>
                    <div class="form-group">
                        <label>Job Title / Role</label>
                        <input type="text" name="attendees[title][]" class="form-control" placeholder="Optional">
                    </div>
                </div>
            `;

            const removeBtn = card.querySelector('.remove-attendee');
            if (removeBtn) {
                removeBtn.addEventListener('click', () => {
                    card.remove();
                    refreshAttendeeHeadings();
                    renderInvoiceSummary();
                });
            }

            attendeesContainer.appendChild(card);
            if (index === 0) {
                syncPrimaryAttendee();
            }
            renderInvoiceSummary();
        }

        function syncPrimaryAttendee() {
            const firstCard = attendeesContainer.querySelector('.attendee-card');
            if (!firstCard) {
                return;
            }

            const attendeeFirstName = firstCard.querySelector('.attendee-first-name');
            const attendeeLastName = firstCard.querySelector('.attendee-last-name');
            const attendeeEmail = firstCard.querySelector('.attendee-email');

            if (attendeeFirstName) {
                attendeeFirstName.value = billingFirstName.value;
            }
            if (attendeeLastName) {
                attendeeLastName.value = billingLastName.value;
            }
            if (attendeeEmail) {
                attendeeEmail.value = billingEmail.value;
            }
        }

        function refreshAttendeeHeadings() {
            attendeesContainer.querySelectorAll('.attendee-card').forEach((card, index) => {
                const heading = card.querySelector('h4');
                if (heading) {
                    heading.textContent = `Attendee ${index + 1}`;
                }
            });
        }

        addAttendeeBtn.addEventListener('click', () => {
            renderAttendeeCard(attendeesContainer.querySelectorAll('.attendee-card').length);
        });

        function renderAttendeeCard(index) {
            attendeeCardTemplate(index);
            refreshAttendeeHeadings();
        }

        [billingFirstName, billingLastName, billingEmail].forEach(input => {
            input.addEventListener('input', syncPrimaryAttendee);
        });

        renderAttendeeCard(0);
    </script>
</body>
</html>
