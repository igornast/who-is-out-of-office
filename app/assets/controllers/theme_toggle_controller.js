import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        storageKey: { type: String, default: 'whosooo-theme' }
    }

    connect() {
        this.applyTheme(this.currentTheme());
    }

    toggle() {
        const isDark = document.documentElement.classList.contains('dark');
        const newTheme = isDark ? 'light' : 'dark';
        localStorage.setItem(this.storageKeyValue, newTheme);
        this.applyTheme(newTheme);
    }

    currentTheme() {
        const saved = localStorage.getItem(this.storageKeyValue);
        if (saved) {
            return saved;
        }
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    applyTheme(theme) {
        const html = document.documentElement;
        const isDark = theme === 'dark';

        html.classList.toggle('dark', isDark);
        html.dataset.bsTheme = isDark ? 'dark' : 'light';
    }
}
