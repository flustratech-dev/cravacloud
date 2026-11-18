import './bootstrap';
import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

window.Alpine = Alpine;
window.Swal = Swal;

const defaultSwalOptions = {
    background: '#F8F6F0',
    color: '#121212',
    confirmButtonColor: '#001AFF',
    cancelButtonColor: '#121212',
    confirmButtonText: 'Mengerti',
    customClass: {
        popup: 'rounded-none border-2 border-[#121212] font-mono shadow-xl',
        title: 'font-display font-bold uppercase tracking-tight text-[#121212]',
        htmlContainer: 'text-sm text-[#686763]',
        confirmButton: 'rounded-none px-5 py-2.5 font-mono text-xs font-bold uppercase tracking-wider',
        cancelButton: 'rounded-none border border-[#121212] px-5 py-2.5 font-mono text-xs font-bold uppercase tracking-wider',
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

Alpine.start();
