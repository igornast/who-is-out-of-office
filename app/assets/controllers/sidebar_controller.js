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
        this.mediaQuery = window.matchMedia('(max-width: 991px)');
        this.boundHandleResize = this.handleResize.bind(this);
        this.boundToggleFromEvent = this.toggle.bind(this);
        this.boundCloseMobile = this.closeMobile.bind(this);
        this.boundHandleMobileKeydown = this.handleMobileKeydown.bind(this);
        this.mobileToggle = document.querySelector('.ooo-mobile-toggle');
        this.mobileStateObserver = new MutationObserver(() => this.syncMobileState());
        this.mobileStateObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
        this.mediaQuery.addEventListener('change', this.boundHandleResize);
        window.addEventListener('sidebar:toggle', this.boundToggleFromEvent);
        window.addEventListener('keydown', this.boundHandleMobileKeydown, true);
        this.element.querySelectorAll('.ooo-sidebar-item, .ooo-sidebar-footer-link').forEach((link) => {
            link.addEventListener('click', this.boundCloseMobile);
        });
        this.handleResize();
        this.syncMobileState();
    }

    disconnect() {
        if (this.mediaQuery) {
            this.mediaQuery.removeEventListener('change', this.boundHandleResize);
        }
        window.removeEventListener('sidebar:toggle', this.boundToggleFromEvent);
        window.removeEventListener('keydown', this.boundHandleMobileKeydown, true);
        this.mobileStateObserver?.disconnect();
        if (this.element) {
            this.element.querySelectorAll('.ooo-sidebar-item, .ooo-sidebar-footer-link').forEach((link) => {
                link.removeEventListener('click', this.boundCloseMobile);
            });
        }
    }

    toggle() {
        if (window.innerWidth <= 991) {
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
        const toggler = document.querySelector('#navigation-toggler');
        if (toggler) {
            toggler.click();
            this.syncMobileState();
        } else if (document.body.classList.contains('ea-mobile-sidebar-visible')) {
            this.closeMobile();
        } else {
            this.openMobile();
        }
    }

    openMobile() {
        document.body.classList.add('ea-mobile-sidebar-visible');
        this.syncMobileState();
    }

    closeMobile() {
        const toggler = document.querySelector('#navigation-toggler');
        if (toggler && document.body.classList.contains('ea-mobile-sidebar-visible')) {
            toggler.click();
        } else {
            document.body.classList.remove('ea-mobile-sidebar-visible');
        }
    }

    handleMobileKeydown(event) {
        if ('Escape' !== event.key || !document.body.classList.contains('ea-mobile-sidebar-visible')) {
            return;
        }

        window.setTimeout(() => {
            if (!document.body.classList.contains('ea-mobile-sidebar-visible')) {
                this.mobileToggle?.focus();
            }
        }, 0);
    }

    syncMobileState() {
        const isOpen = document.body.classList.contains('ea-mobile-sidebar-visible');
        this.mobileToggle?.setAttribute('aria-expanded', String(isOpen));

        if (isOpen && !this.element.contains(document.activeElement)) {
            window.setTimeout(() => {
                if (!document.body.classList.contains('ea-mobile-sidebar-visible')) {
                    return;
                }

                this.element.querySelector('.ooo-sidebar-item, .ooo-sidebar-footer-link')?.focus({ preventScroll: true });
            }, 50);
        }
    }

    handleResize() {
        if (this.mediaQuery.matches) {
            document.documentElement.classList.remove('sidebar-collapsed');
            this.closeMobile();
        } else {
            this.closeMobile();
            this.applyState(this.isCollapsed());
        }
    }

    isCollapsed() {
        if (window.innerWidth <= 991) {
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
