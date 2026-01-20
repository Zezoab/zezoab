// BookingPro - Booking Page JavaScript
// Multi-step booking form with availability checking

let currentStep = 1;
const totalSteps = 4;

let selectedService = null;
let selectedStaff = null;
let selectedDate = null;
let selectedTime = null;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeBookingForm();
});

function initializeBookingForm() {
    // Service selection
    const serviceRadios = document.querySelectorAll('input[name="service_id"]');
    serviceRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            selectedService = {
                id: this.value,
                duration: this.dataset.duration,
                price: this.dataset.price
            };
        });
    });

    // Staff selection
    const staffRadios = document.querySelectorAll('input[name="staff_id"]');
    staffRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            selectedStaff = this.value;
        });
    });

    // Date selection - load available times when date changes
    const dateInput = document.getElementById('appointment_date');
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            selectedDate = this.value;
            loadAvailableSlots();
        });
    }
}

function nextStep() {
    // Validate current step
    if (!validateStep(currentStep)) {
        return;
    }

    // Hide current step
    const currentStepEl = document.querySelector(`.form-step[data-step="${currentStep}"]`);
    currentStepEl.classList.remove('active');

    // Show next step
    currentStep++;
    const nextStepEl = document.querySelector(`.form-step[data-step="${currentStep}"]`);
    nextStepEl.classList.add('active');

    // Update summary if on final step
    if (currentStep === totalSteps) {
        updateBookingSummary();
    }

    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function prevStep() {
    // Hide current step
    const currentStepEl = document.querySelector(`.form-step[data-step="${currentStep}"]`);
    currentStepEl.classList.remove('active');

    // Show previous step
    currentStep--;
    const prevStepEl = document.querySelector(`.form-step[data-step="${currentStep}"]`);
    prevStepEl.classList.add('active');

    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function validateStep(step) {
    switch(step) {
        case 1:
            if (!selectedService) {
                alert('Please select a service');
                return false;
            }
            return true;

        case 2:
            if (!selectedStaff) {
                alert('Please select a staff member');
                return false;
            }
            return true;

        case 3:
            if (!selectedDate) {
                alert('Please select a date');
                return false;
            }
            if (!selectedTime) {
                alert('Please select a time');
                return false;
            }
            return true;

        default:
            return true;
    }
}

async function loadAvailableSlots() {
    if (!selectedStaff || !selectedDate || !selectedService) {
        return;
    }

    const container = document.getElementById('timeSlotsContainer');
    container.innerHTML = '<div class="loading">Loading available times...</div>';

    try {
        const response = await fetch(
            `${SITE_URL}/api/get-slots.php?staff_id=${selectedStaff}&date=${selectedDate}&duration=${selectedService.duration}&business=${businessSlug}`
        );

        const data = await response.json();

        if (!data.success) {
            container.innerHTML = '<p class="text-muted">Unable to load available times. Please try again.</p>';
            return;
        }

        if (data.slots.length === 0) {
            container.innerHTML = '<p class="text-muted">No available times for this date. Please select another date.</p>';
            return;
        }

        // Create time slot buttons
        container.innerHTML = '';
        data.slots.forEach(slot => {
            const slotBtn = document.createElement('div');
            slotBtn.className = 'time-slot';
            slotBtn.textContent = formatTime(slot);
            slotBtn.dataset.time = slot;

            slotBtn.addEventListener('click', function() {
                // Remove selected class from all slots
                document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));

                // Add selected class to clicked slot
                this.classList.add('selected');

                // Update selected time
                selectedTime = this.dataset.time;

                // Update hidden input
                let timeInput = document.querySelector('input[name="start_time"]');
                if (!timeInput) {
                    timeInput = document.createElement('input');
                    timeInput.type = 'hidden';
                    timeInput.name = 'start_time';
                    document.querySelector('.booking-form').appendChild(timeInput);
                }
                timeInput.value = selectedTime;
            });

            container.appendChild(slotBtn);
        });

    } catch (error) {
        console.error('Error loading slots:', error);
        container.innerHTML = '<p class="text-muted">Error loading available times. Please try again.</p>';
    }
}

function formatTime(time24) {
    // Convert 24-hour time to 12-hour format
    const [hours, minutes] = time24.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
}

function formatDate(dateStr) {
    const date = new Date(dateStr + 'T00:00:00');
    const options = { month: 'short', day: 'numeric', year: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

function updateBookingSummary() {
    const summaryDiv = document.getElementById('bookingSummary');

    if (!selectedService || !selectedStaff || !selectedDate || !selectedTime) {
        summaryDiv.innerHTML = '<p>Please complete all steps</p>';
        return;
    }

    // Get service name
    const serviceRadio = document.querySelector(`input[name="service_id"][value="${selectedService.id}"]`);
    const serviceName = serviceRadio ? serviceRadio.closest('.service-card').querySelector('h3').textContent : 'Unknown Service';

    // Get staff name
    const staffRadio = document.querySelector(`input[name="staff_id"][value="${selectedStaff}"]`);
    const staffName = staffRadio ? staffRadio.closest('.staff-card').querySelector('h3').textContent : 'Unknown Staff';

    // Build summary HTML
    const summary = `
        <div style="display: grid; gap: 0.75rem;">
            <div style="display: flex; justify-content: space-between;">
                <strong>Service:</strong>
                <span>${serviceName}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <strong>Staff:</strong>
                <span>${staffName}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <strong>Date:</strong>
                <span>${formatDate(selectedDate)}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <strong>Time:</strong>
                <span>${formatTime(selectedTime)}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <strong>Duration:</strong>
                <span>${selectedService.duration} minutes</span>
            </div>
            <hr style="border: none; border-top: 1px solid #E5E7EB; margin: 0.5rem 0;">
            <div style="display: flex; justify-content: space-between; font-size: 1.125rem;">
                <strong>Total:</strong>
                <strong style="color: var(--primary-color);">$${parseFloat(selectedService.price).toFixed(2)}</strong>
            </div>
        </div>
    `;

    summaryDiv.innerHTML = summary;
}

// Form validation before submission
document.addEventListener('DOMContentLoaded', function() {
    const bookingForm = document.getElementById('bookingForm');

    if (bookingForm) {
        bookingForm.addEventListener('submit', function(e) {
            // Validate all required fields
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();

            if (!firstName || !lastName || !email) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return false;
            }

            if (!selectedService || !selectedStaff || !selectedDate || !selectedTime) {
                e.preventDefault();
                alert('Please complete all booking steps');
                return false;
            }

            // Form is valid, allow submission
            return true;
        });
    }
});

// Copy booking link function (for dashboard)
function copyBookingLink() {
    const input = document.getElementById('bookingLink');
    if (input) {
        input.select();
        input.setSelectionRange(0, 99999); // For mobile devices

        try {
            document.execCommand('copy');
            alert('Booking link copied to clipboard!');
        } catch (err) {
            console.error('Failed to copy:', err);
            alert('Failed to copy link. Please copy manually.');
        }
    }
}

// Export functions for global access
window.nextStep = nextStep;
window.prevStep = prevStep;
window.copyBookingLink = copyBookingLink;
