import './bootstrap';

const navigationToggle = document.querySelector('.nav-toggle');
const primaryNavigation = document.querySelector('#primary-navigation');

if (navigationToggle && primaryNavigation) {
    navigationToggle.addEventListener('click', () => {
        const isExpanded = navigationToggle.getAttribute('aria-expanded') === 'true';
        navigationToggle.setAttribute('aria-expanded', String(!isExpanded));
        navigationToggle.querySelector('.nav-toggle__label').textContent = isExpanded ? 'Buka menu' : 'Tutup menu';
        primaryNavigation.classList.toggle('site-nav--open', !isExpanded);
    });
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
    });
});
