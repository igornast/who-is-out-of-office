import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        storageKey: { type: String, default: 'whosooo-sidebar' }
    }

    connect() {
        this.wrapper = this.element.closest('.sidebar-wrapper');
        if (!this.wrapper) {
            return;
        }
        this.createOverlay();
        this.mediaQuery = window.matchMedia('(max-width: 768px)');
        this.boundHandleResize = this.handleResize.bind(this);
        this.boundToggleFromEvent = this.toggle.bind(this);
        this.mediaQuery.addEventListener('change', this.boundHandleResize);
        window.addEventListener('sidebar:toggle', this.boundToggleFromEvent);
    }

    disconnect() {
        if (this.mediaQuery) {
            this.mediaQuery.removeEventListener('change', this.boundHandleResize);
        }
        window.removeEventListener('sidebar:toggle', this.boundToggleFromEvent);
        if (this.overlay && this.overlay.parentNode) {
            this.overlay.parentNode.removeChild(this.overlay);
        }
    }

    createOverlay() {
        this.overlay = document.createElement('div');
        this.overlay.className = 'ooo-sidebar-overlay';
        this.overlay.addEventListener('click', () => this.closeMobile());
        document.body.appendChild(this.overlay);
    }

    toggle() {
        if (window.innerWidth <= 768) {
            this.toggleMobile();
        } else {
            this.toggleDesktop();
        }
    }

    toggleDesktop() {
        const collapsed = !this.isCollapsed();
        localStorage.setItem(this.storageKeyValue, collapsed ? 'collapsed' : 'expanded');
        this.applyState(collapsed);
    }

    toggleMobile() {
        if (this.wrapper.classList.contains('sidebar-mobile-open')) {
            this.closeMobile();
        } else {
            this.openMobile();
        }
    }

    openMobile() {
        this.wrapper.classList.add('sidebar-mobile-open');
        this.overlay.classList.add('active');
    }

    closeMobile() {
        this.wrapper.classList.remove('sidebar-mobile-open');
        this.overlay.classList.remove('active');
    }

    handleResize() {
        if (this.mediaQuery.matches) {
            document.documentElement.classList.remove('sidebar-collapsed');
            this.closeMobile();
        } else {
            this.wrapper.classList.remove('sidebar-mobile-open');
            this.overlay.classList.remove('active');
            this.applyState(this.isCollapsed());
        }
    }

    isCollapsed() {
        if (window.innerWidth <= 768) {
            return false;
        }
        return localStorage.getItem(this.storageKeyValue) === 'collapsed';
    }

    applyState(collapsed) {
        if (collapsed) {
            document.documentElement.classList.add('sidebar-collapsed');
        } else {
            document.documentElement.classList.remove('sidebar-collapsed');
        }
    }
}
