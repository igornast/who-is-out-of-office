import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.element.querySelectorAll('input[type="radio"]').forEach(input => {
            input.addEventListener('change', () => this.applyPreview());
        });
    }

    applyPreview() {
        const themeInput = this.element.querySelector('input[name$="[theme]"]:checked');
        const paletteInput = this.element.querySelector('input[name$="[palette]"]:checked');
        const theme = themeInput?.value ?? 'auto';
        const palette = paletteInput?.value ?? 'teal';

        const isDark = theme === 'dark' || (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        const html = document.documentElement;
        html.classList.toggle('dark', isDark);
        html.classList.toggle('ea-dark-scheme', isDark);
        html.dataset.bsTheme = isDark ? 'dark' : 'light';
        html.dataset.palette = palette;
    }
}
