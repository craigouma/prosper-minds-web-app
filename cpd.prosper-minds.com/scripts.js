// Modal functionality
let currentEventId = null;
let currentEventTitle = null;

// Open modal with event details
function openModal(eventId, eventTitle) {
    currentEventId = eventId;
    currentEventTitle = eventTitle;

    document.getElementById('eventId').value = eventId;
    document.getElementById('modalEventTitle').textContent = 'Register for: ' + eventTitle;
    document.getElementById('registrationModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

// Close modal
function closeModal() {
    document.getElementById('registrationModal').style.display = 'none';
    document.body.style.overflow = 'auto';

    // Reset form
    document.getElementById('registrationForm').reset();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('registrationModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Form validation
const registrationForm = document.getElementById('registrationForm');
if (registrationForm) {
    registrationForm.addEventListener('submit', function(e) {
        let isValid = true;

        // Simple validation
        const email = document.getElementById('email').value;
        if (!validateEmail(email)) {
            alert('Please enter a valid email address');
            isValid = false;
        }

        if (!isValid) {
            e.preventDefault();
        }
    });
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Smooth scrolling for better UX
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scroll behavior
    document.documentElement.style.scrollBehavior = 'smooth';

    // Add event listeners to all register buttons
    const registerButtons = document.querySelectorAll('.register-btn');
    registerButtons.forEach(button => {
        button.addEventListener('click', function() {
            // You could add analytics tracking here
            console.log('Register button clicked for event');
        });
    });
});
