import './bootstrap';
import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

window.Alpine = Alpine;
window.Swal = Swal;

const defaultSwalOptions = {
    confirmButtonColor: '#111827',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Mengerti',
    customClass: {
        popup: 'rounded-2xl',
        confirmButton: 'rounded-lg px-5 py-2.5 font-semibold',
        cancelButton: 'rounded-lg px-5 py-2.5 font-semibold',
    },
};

window.showAlert = (message, options = {}) => Swal.fire({
    ...defaultSwalOptions,
    icon: 'warning',
    title: 'Perhatian',
    text: String(message),
    ...options,
});

window.showSuccess = (message, options = {}) => Swal.fire({
    ...defaultSwalOptions,
    icon: 'success',
    title: 'Berhasil',
    text: String(message),
    timer: 3500,
    timerProgressBar: true,
    ...options,
});

window.showError = (message, options = {}) => Swal.fire({
    ...defaultSwalOptions,
    icon: 'error',
    title: 'Terjadi Kesalahan',
    text: String(message),
    ...options,
});

window.showInfo = (message, options = {}) => Swal.fire({
    ...defaultSwalOptions,
    icon: 'info',
    title: 'Informasi',
    text: String(message),
    timer: 4000,
    timerProgressBar: true,
    ...options,
});

// Keep any legacy inline alert() calls on the SweetAlert2 UI as well.
window.alert = (message) => window.showAlert(message);

// Replace synchronous browser confirm() prompts with an accessible SweetAlert2
// confirmation. Forms continue only after the user explicitly confirms.
document.addEventListener('submit', (event) => {
    const form = event.target instanceof HTMLFormElement
        ? event.target
        : event.target.closest?.('form[data-confirm]');

    if (!form?.dataset.confirm || form.dataset.confirmPending === 'true') {
        return;
    }

    event.preventDefault();
    form.dataset.confirmPending = 'true';

    Swal.fire({
        ...defaultSwalOptions,
        icon: 'question',
        title: 'Konfirmasi tindakan',
        text: form.dataset.confirm,
        showCancelButton: true,
        confirmButtonText: 'Ya, lanjutkan',
        cancelButtonText: 'Batal',
    }).then(({ isConfirmed }) => {
        delete form.dataset.confirmPending;

        if (isConfirmed) {
            HTMLFormElement.prototype.submit.call(form);
        }
    });
}, true);

// Global Theme Toggler
window.toggleTheme = function() {
    const isDark = document.documentElement.classList.toggle('dark');
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: { isDark } }));
};

document.addEventListener('alpine:init', () => {
    Alpine.store('theme', {
        dark: document.documentElement.classList.contains('dark'),
        toggle() {
            window.toggleTheme();
            this.dark = document.documentElement.classList.contains('dark');
        }
    });
});

window.addEventListener('theme-changed', (e) => {
    if (window.Alpine?.store('theme')) {
        Alpine.store('theme').dark = e.detail.isDark;
    }
});

Alpine.start();

