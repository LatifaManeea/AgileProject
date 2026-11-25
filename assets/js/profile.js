// Form validation
const forms = document.querySelectorAll('.needs-validation')

// Loop over them and prevent submission
Array.from(forms).forEach(form => {
    form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
            event.preventDefault()
            event.stopPropagation()
        }

        form.classList.add('was-validated')
    }, false)
})

// Password matching validation
document.getElementById('confirm_password')?.addEventListener('input', function () {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;

    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// Appointment filter (My Profile): filter cards by status and search by service name
const statusFilter = document.getElementById('statusFilter');
const serviceSearch = document.getElementById('serviceSearch');

function applyAppointmentFilter() {
    const status = statusFilter.value;
    const query = serviceSearch.value.trim().toLowerCase();
    const items = document.querySelectorAll('.appointment-item');
    let visible = 0;

    items.forEach(item => {
        const matchesStatus = status === 'all' || item.dataset.status === status;
        const matchesService = item.dataset.service.includes(query);
        const show = matchesStatus && matchesService;
        item.classList.toggle('d-none', !show);
        if (show) visible++;
    });

    document.getElementById('noAppointmentMatch')?.classList.toggle('d-none', visible > 0);
}

if (statusFilter && serviceSearch) {
    statusFilter.addEventListener('change', applyAppointmentFilter);
    serviceSearch.addEventListener('input', applyAppointmentFilter);
}
