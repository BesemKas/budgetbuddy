/**
 * In-app toasts for Livewire (no WebSockets). Called via $this->js() from components.
 */
window.budgetBuddyToast = function (message, variant = 'info') {
    if (typeof message !== 'string' || message === '') {
        return;
    }

    const host = document.getElementById('bb-toast-host');
    if (! host) {
        return;
    }

    const alertClass =
        variant === 'success'
            ? 'alert-success'
            : variant === 'warning'
              ? 'alert-warning'
              : variant === 'error'
                ? 'alert-error'
                : 'alert-info';

    const wrapper = document.createElement('div');
    wrapper.setAttribute('role', 'status');
    wrapper.className = '';

    const inner = document.createElement('div');
    inner.className = `alert ${alertClass} alert-soft max-w-sm shadow-lg`;
    inner.textContent = message;

    wrapper.appendChild(inner);
    host.appendChild(wrapper);

    const remove = () => {
        wrapper.classList.add('opacity-0', 'transition-opacity', 'duration-200');
        setTimeout(() => wrapper.remove(), 200);
    };

    setTimeout(remove, 5500);
};
