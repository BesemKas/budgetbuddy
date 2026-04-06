const storageKey = 'budgetbuddy-sidebar-collapsed';

function isCollapsed() {
    return document.documentElement.classList.contains('bb-sidebar-collapsed');
}

function syncToggleUi() {
    const btn = document.getElementById('bb-sidebar-toggle');
    if (!btn) {
        return;
    }

    const collapsed = isCollapsed();
    btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    const label = collapsed ? btn.getAttribute('data-label-expand') : btn.getAttribute('data-label-collapse');
    if (label) {
        btn.setAttribute('aria-label', label);
    }
}

/**
 * Restore collapsed/expanded state from localStorage (full page or after Livewire navigations).
 */
function applyCollapsedFromStorage() {
    try {
        const raw = localStorage.getItem(storageKey);
        if (raw === '1') {
            document.documentElement.classList.add('bb-sidebar-collapsed');
        } else {
            document.documentElement.classList.remove('bb-sidebar-collapsed');
        }
    } catch {
        //
    }
    syncToggleUi();
}

function urlsMatchForNavigate(a, b) {
    return (
        a.pathname.replace(/\/$/, '') === b.pathname.replace(/\/$/, '') &&
        a.search === b.search &&
        a.hash === b.hash
    );
}

/**
 * Normalize pathname for comparison (no trailing slash except root).
 */
function normalizePathname(pathname) {
    if (pathname.length > 1 && pathname.endsWith('/')) {
        return pathname.slice(0, -1);
    }

    return pathname;
}

/**
 * The sidebar lives in a Livewire @persist region, so Blade never re-runs on wire:navigate.
 * Mirror request()->routeIs(...) by marking the best-matching link using URL path (longest prefix wins).
 */
function syncSidebarActiveNav() {
    const aside = document.getElementById('bb-app-sidebar');
    if (!aside) {
        return;
    }

    const links = Array.from(aside.querySelectorAll('ul.menu a[href]'));
    const current = normalizePathname(new URL(window.location.href).pathname);

    const candidates = [];

    for (const link of links) {
        let path;
        try {
            path = normalizePathname(new URL(link.getAttribute('href') ?? '', window.location.href).pathname);
        } catch {
            continue;
        }

        if (path === '') {
            continue;
        }

        if (current === path || current.startsWith(`${path}/`)) {
            candidates.push({ link, path, len: path.length });
        }
    }

    for (const link of links) {
        link.classList.remove('menu-active');
        link.removeAttribute('aria-current');
    }

    if (candidates.length === 0) {
        return;
    }

    candidates.sort((a, b) => b.len - a.len);
    const best = candidates[0];
    best.link.classList.add('menu-active');
    best.link.setAttribute('aria-current', 'page');
}

/**
 * wire:navigate always fetches + morphs, even when the target is the current page.
 * That remorph strips `bb-sidebar-collapsed` from <html> for a frame and retriggers the width transition.
 * Skip SPA navigation when the href matches the current URL (common: Dashboard while already on /dashboard).
 */
function registerSkipRedundantNavigate() {
    document.addEventListener(
        'mousedown',
        (event) => {
            if (event.defaultPrevented) {
                return;
            }
            if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                return;
            }
            const link = event.target.closest('a[href]');
            if (!link) {
                return;
            }
            if (!link.hasAttribute('wire:navigate') && !link.hasAttribute('x-navigate')) {
                return;
            }
            try {
                const dest = new URL(link.getAttribute('href'), window.location.href);
                const cur = new URL(window.location.href);
                if (urlsMatchForNavigate(dest, cur)) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            } catch {
                //
            }
        },
        true,
    );
}

function onSidebarToggleClick(event) {
    const btn = event.target.closest('#bb-sidebar-toggle');
    if (!btn) {
        return;
    }
    document.documentElement.classList.toggle('bb-sidebar-collapsed');
    try {
        localStorage.setItem(storageKey, isCollapsed() ? '1' : '0');
    } catch {
        //
    }
    syncToggleUi();
}

function init() {
    applyCollapsedFromStorage();
    syncSidebarActiveNav();

    document.addEventListener('click', onSidebarToggleClick);

    document.addEventListener('alpine:navigating', () => {
        applyCollapsedFromStorage();
    });

    document.addEventListener('livewire:navigated', () => {
        applyCollapsedFromStorage();
        syncSidebarActiveNav();
    });
}

registerSkipRedundantNavigate();
document.addEventListener('DOMContentLoaded', init);
