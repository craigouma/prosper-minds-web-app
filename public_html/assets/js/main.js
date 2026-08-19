document.addEventListener('DOMContentLoaded', function() {
    // Mobile Menu Toggle
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');
    const navItems = document.querySelectorAll('.nav-links a');

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', () => {
            navLinks.classList.toggle('active');
            const icon = mobileMenuBtn.querySelector('i');
            if (navLinks.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    }

    // Close menu when a link is clicked
    navItems.forEach(item => {
        item.addEventListener('click', () => {
            if(navLinks.classList.contains('active')) {
                navLinks.classList.remove('active');
                const icon = mobileMenuBtn.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    });

    // Scroll Reveal Animation
    function reveal() {
        var reveals = document.querySelectorAll('.reveal');
        for (var i = 0; i < reveals.length; i++) {
            var windowHeight = window.innerHeight;
            var elementTop = reveals[i].getBoundingClientRect().top;
            var elementVisible = 100;
            if (elementTop < windowHeight - elementVisible) {
                reveals[i].classList.add('active');
            }
        }
    }
    window.addEventListener('scroll', reveal);
    reveal(); // Trigger on load

    // Animated Counters
    const counters = document.querySelectorAll('.stat-number');
    const speed = 200;

    const animateCounters = () => {
        counters.forEach(counter => {
            const updateCount = () => {
                const target = +counter.getAttribute('data-target');
                const count = +counter.innerText;
                const inc = target / speed;

                if (count < target) {
                    counter.innerText = Math.ceil(count + inc);
                    setTimeout(updateCount, 10);
                } else {
                    counter.innerText = target;
                }
            };
            
            // Only animate when in view
            const rect = counter.getBoundingClientRect();
            if(rect.top < window.innerHeight && counter.innerText == '0') {
                updateCount();
            }
        });
    }

    window.addEventListener('scroll', animateCounters);

    // Modal Logic
    const modal = document.getElementById('registrationModal');
    const modalClose = document.querySelector('.modal-close');
    const registerButtons = document.querySelectorAll('.btn-register');
    const eventNameInput = document.getElementById('event_name_input');
    const displayEventName = document.getElementById('display_event_name');
    const modalRegistrationLink = document.getElementById('modalRegistrationLink');

    registerButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            const registrationUrl = this.getAttribute('data-registration-url');
            const href = this.getAttribute('href');
            if (registrationUrl || (href && href !== '#')) {
                e.preventDefault();
                window.location.href = registrationUrl || href;
                return;
            }

            e.preventDefault();
            const eventName = this.getAttribute('data-event');
            eventNameInput.value = eventName;
            displayEventName.textContent = eventName;
            if (modalRegistrationLink && href) {
                modalRegistrationLink.setAttribute('href', href);
            }
            modal.classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        });
    });

    if (modalClose) {
        modalClose.addEventListener('click', () => {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        });
    }

    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    });

    // Form Submission using AJAX with Validation
    const regForm = document.getElementById('registrationForm');
    if (regForm) {
        regForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Basic Client Side Validation
            const email = this.querySelector('input[name="email"]').value;
            const phone = this.querySelector('input[name="phone"]').value;
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address.');
                return;
            }

            // Phone validation (simple check for mostly numbers and length)
            const phoneRegex = /^[\d\+\-\s\(\)]{8,20}$/;
            if (!phoneRegex.test(phone)) {
                alert('Please enter a valid phone number (min 8 digits).');
                return;
            }

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Processing...';
            submitBtn.disabled = true;

            const formData = new FormData(this);

            fetch('process-registration.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    regForm.reset();
                    modal.classList.remove('active');
                    document.body.style.overflow = 'auto';
                } else {
                    alert(data.message || 'An error occurred. Please check your inputs.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again later.');
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});
