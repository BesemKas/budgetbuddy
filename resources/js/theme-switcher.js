const storageKey = 'budgetbuddy-theme';

let ignoreControllerChange = false;

function isValidTheme(value) {
    return value === 'dracula' || value === 'cupcake';
}

function readStoredTheme() {
    let fromLocal = null;
    let fromSession = null;

    try {
        fromLocal = localStorage.getItem(storageKey);
    } catch {
        //
    }

    try {
        fromSession = sessionStorage.getItem(storageKey);
    } catch {
        //
    }

    if (isValidTheme(fromLocal)) {
        return fromLocal;
    }

    if (isValidTheme(fromSession)) {
        return fromSession;
    }

    return null;
}

function persistToStorage(theme) {
    try {
        localStorage.setItem(storageKey, theme);
    } catch {
        try {
            localStorage.removeItem(storageKey);
        } catch {
            //
        }
    }

    try {
        sessionStorage.setItem(storageKey, theme);
    } catch {
        //
    }
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    persistToStorage(theme);
}

function rehydrateFromStorage() {
    const stored = readStoredTheme();

    if (stored) {
        document.documentElement.setAttribute('data-theme', stored);
    }
}

function syncControllerFromDocument() {
    const input = document.querySelector('#bb-theme-controller');
    if (!input) {
        return;
    }

    ignoreControllerChange = true;

    try {
        const stored = readStoredTheme();
        const theme = stored ?? document.documentElement.getAttribute('data-theme') ?? 'cupcake';

        input.checked = theme === 'dracula';

        if (stored) {
            document.documentElement.setAttribute('data-theme', stored);
        }
    } finally {
        ignoreControllerChange = false;
    }
}

function persistThemeFromController() {
    const input = document.querySelector('#bb-theme-controller');
    if (!input) {
        return;
    }

    input.addEventListener('change', () => {
        if (ignoreControllerChange) {
            return;
        }

        const theme = input.checked ? input.value : 'cupcake';

        applyTheme(theme);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    rehydrateFromStorage();
    syncControllerFromDocument();
    persistThemeFromController();
});

document.addEventListener('livewire:navigated', () => {
    rehydrateFromStorage();
    syncControllerFromDocument();
});

window.addEventListener('storage', (event) => {
    if (event.key !== storageKey || event.newValue === null) {
        return;
    }

    if (isValidTheme(event.newValue)) {
        try {
            sessionStorage.setItem(storageKey, event.newValue);
        } catch {
            //
        }

        document.documentElement.setAttribute('data-theme', event.newValue);
        syncControllerFromDocument();
    }
});
