document.addEventListener('submit', event => {
    const confirmation = event.target.dataset.confirm;
    if (confirmation && !window.confirm(confirmation)) event.preventDefault();
});

const navigationToggle = document.getElementById('workspace-nav-toggle');
const workspaceNavigation = document.getElementById('workspace-navigation');
const navigationBackdrop = document.getElementById('workspace-nav-backdrop');
if (navigationToggle && workspaceNavigation && navigationBackdrop) {
    const mobileNavigation = window.matchMedia('(max-width: 1000px)');
    const setNavigationOpen = (open, returnFocus = false) => {
        const expanded = mobileNavigation.matches && open;
        navigationToggle.setAttribute('aria-expanded', String(expanded));
        document.body.classList.toggle('navigation-open', expanded);
        navigationBackdrop.hidden = !expanded;
        workspaceNavigation.inert = mobileNavigation.matches && !expanded;
        if (expanded) (workspaceNavigation.querySelector('a[aria-current="page"]') || workspaceNavigation.querySelector('a'))?.focus();
        else if (returnFocus) navigationToggle.focus();
    };
    navigationToggle.addEventListener('click', () => setNavigationOpen(navigationToggle.getAttribute('aria-expanded') !== 'true'));
    navigationBackdrop.addEventListener('click', () => setNavigationOpen(false, true));
    workspaceNavigation.addEventListener('click', event => {
        if (event.target.closest('a') && mobileNavigation.matches) setNavigationOpen(false);
    });
    document.addEventListener('keydown', event => {
        if (navigationToggle.getAttribute('aria-expanded') !== 'true') return;
        if (event.key === 'Escape') setNavigationOpen(false, true);
        if (event.key === 'Tab') {
            const links = [...workspaceNavigation.querySelectorAll('a[href]')];
            const first = links[0];
            const last = links[links.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    });
    mobileNavigation.addEventListener('change', () => setNavigationOpen(false));
    setNavigationOpen(false);
}
