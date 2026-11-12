import './bootstrap';

const navigationToggle = document.querySelector('.nav-toggle');
const primaryNavigation = document.querySelector('#primary-navigation');
const siteHeader = document.querySelector('.site-header');
const navigationBackdrop = document.querySelector('.nav-backdrop');

if (navigationToggle && primaryNavigation && siteHeader) {
    const setNavigationState = (isOpen, moveFocus = false) => {
        navigationToggle.setAttribute('aria-expanded', String(isOpen));
        navigationToggle.setAttribute('aria-label', isOpen ? 'Tutup navigasi' : 'Buka navigasi');
        navigationToggle.querySelector('.sr-only').textContent = isOpen ? 'Tutup navigasi' : 'Buka navigasi';
        siteHeader.classList.toggle('is-expanded', isOpen);
        if (navigationBackdrop) {
            navigationBackdrop.hidden = !isOpen;
        }

        if (moveFocus) {
            primaryNavigation.querySelector('a')?.focus();
        }
    };

    navigationToggle.addEventListener('click', () => {
        const isExpanded = navigationToggle.getAttribute('aria-expanded') === 'true';
        setNavigationState(!isExpanded, !isExpanded);
    });

    navigationToggle.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setNavigationState(false);
            navigationToggle.focus();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setNavigationState(false);
            navigationToggle.focus();
        }
    });

    navigationBackdrop?.addEventListener('click', () => setNavigationState(false));
}

document.querySelectorAll('.report-toggle').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const form = document.getElementById(toggle.getAttribute('aria-controls'));
        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';

        if (!form) {
            return;
        }

        toggle.setAttribute('aria-expanded', String(!isExpanded));
        form.hidden = isExpanded;

        if (!isExpanded) {
            form.querySelector('input, select, textarea')?.focus();
        }
    });
});

document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', () => {
        const submit = form.querySelector('button[type="submit"]');

        if (!submit) {
            return;
        }

        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        if (submit.dataset.loadingLabel) {
            submit.textContent = submit.dataset.loadingLabel;
        }
    }, { once: true });
});
