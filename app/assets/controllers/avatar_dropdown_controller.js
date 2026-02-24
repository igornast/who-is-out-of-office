import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu', 'trigger'];
    static values = {
        open: { type: Boolean, default: false }
    };

    connect() {
        this.boundClose = this.closeOnOutsideClick.bind(this);
        this.boundEscape = this.closeOnEscape.bind(this);
        this.initThemeSwitcher();
    }

    initThemeSwitcher() {
        const saved = localStorage.getItem('whosooo-theme');
        const activeTheme = saved || 'auto';
        this.menuTarget.querySelectorAll('.avatar-dropdown-theme-option').forEach(opt => {
            const isActive = opt.dataset.theme === activeTheme;
            opt.classList.toggle('active', isActive);
            opt.setAttribute('aria-checked', isActive ? 'true' : 'false');
        });
    }

    disconnect() {
        this.removeListeners();
    }

    toggle(event) {
        event.stopPropagation();
        if (this.openValue) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        this.openValue = true;
        this.positionMenu();
        this.menuTarget.classList.add('active');
        this.triggerTarget.setAttribute('aria-expanded', 'true');
        this.addListeners();
        const firstItem = this.menuTarget.querySelector('[role="menuitem"]');
        if (firstItem) {
            firstItem.focus();
        }
    }

    positionMenu() {
        const rect = this.triggerTarget.getBoundingClientRect();
        const menu = this.menuTarget;
        menu.style.top = (rect.bottom + 8) + 'px';
        menu.style.right = (window.innerWidth - rect.right) + 'px';
    }

    close() {
        this.openValue = false;
        this.menuTarget.classList.remove('active');
        this.triggerTarget.setAttribute('aria-expanded', 'false');
        this.removeListeners();
    }

    addListeners() {
        document.addEventListener('click', this.boundClose);
        document.addEventListener('keydown', this.boundEscape);
    }

    removeListeners() {
        document.removeEventListener('click', this.boundClose);
        document.removeEventListener('keydown', this.boundEscape);
    }

    closeOnOutsideClick(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }

    closeOnEscape(event) {
        if (event.key === 'Escape') {
            this.close();
            this.triggerTarget.focus();
        }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            this.navigateItems(event.key === 'ArrowDown' ? 1 : -1);
        }
    }

    navigateItems(direction) {
        const items = [...this.menuTarget.querySelectorAll('[role="menuitem"], [role="radio"]')];
        const active = document.activeElement;
        const idx = items.indexOf(active);
        let next = idx + direction;
        if (next < 0) { next = items.length - 1; }
        if (next >= items.length) { next = 0; }
        items[next].focus();
    }

    setTheme(event) {
        const theme = event.currentTarget.dataset.theme;

        this.menuTarget.querySelectorAll('.avatar-dropdown-theme-option').forEach(opt => {
            const isActive = opt.dataset.theme === theme;
            opt.classList.toggle('active', isActive);
            opt.setAttribute('aria-checked', isActive ? 'true' : 'false');
        });

        if (theme === 'auto') {
            localStorage.removeItem('whosooo-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            this.applyTheme(prefersDark ? 'dark' : 'light');
        } else {
            localStorage.setItem('whosooo-theme', theme);
            this.applyTheme(theme);
        }

        this.syncSidebarToggle(theme);
    }

    applyTheme(theme) {
        const html = document.documentElement;
        const isDark = theme === 'dark';
        html.classList.toggle('dark', isDark);
        html.classList.toggle('ea-dark-scheme', isDark);
        html.dataset.bsTheme = isDark ? 'dark' : 'light';
    }

    syncSidebarToggle(mode) {
        window.dispatchEvent(new CustomEvent('theme:changed', { detail: { mode } }));
    }
}
