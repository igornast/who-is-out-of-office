import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.contentBody = this.element.querySelector('.content-body');
        if (!this.contentBody || !this.contentBody.querySelector('.datagrid')) {
            return;
        }

        this.fade = null;
        this._onScroll = this._onScroll.bind(this);
        this._onResize = this._onResize.bind(this);

        this._check();
        this.contentBody.addEventListener('scroll', this._onScroll, { passive: true });
        window.addEventListener('resize', this._onResize, { passive: true });
    }

    disconnect() {
        if (this.contentBody) {
            this.contentBody.removeEventListener('scroll', this._onScroll);
        }
        window.removeEventListener('resize', this._onResize);
        if (this.fade) {
            this.fade.remove();
            this.fade = null;
        }
        if (this.contentBody) {
            this.contentBody.classList.remove('is-overflowing');
        }
    }

    _check() {
        const overflows = this.contentBody.scrollWidth > this.contentBody.clientWidth;

        if (overflows && !this.fade) {
            this._createFade();
        }

        if (overflows) {
            this.contentBody.classList.add('is-overflowing');
            this.fade.classList.remove('is-hidden');
            this._onScroll();
        } else {
            this.contentBody.classList.remove('is-overflowing');
            if (this.fade) {
                this.fade.classList.add('is-hidden');
            }
        }
    }

    _createFade() {
        this.fade = document.createElement('div');
        this.fade.className = 'table-scroll-fade';
        this.element.style.position = 'relative';

        const bodyRect = this.contentBody.getBoundingClientRect();
        const parentRect = this.element.getBoundingClientRect();
        this.fade.style.top = (bodyRect.top - parentRect.top) + 'px';

        this.element.appendChild(this.fade);
    }

    _onScroll() {
        if (!this.fade) return;

        const { scrollLeft, scrollWidth, clientWidth } = this.contentBody;
        const maxScroll = scrollWidth - clientWidth;
        const atEnd = maxScroll - scrollLeft < 2;

        this.fade.classList.toggle('is-hidden', atEnd);
    }

    _onResize() {
        this._check();

        if (this.fade) {
            const bodyRect = this.contentBody.getBoundingClientRect();
            const parentRect = this.element.getBoundingClientRect();
            this.fade.style.top = (bodyRect.top - parentRect.top) + 'px';
        }
    }
}
