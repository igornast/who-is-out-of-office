import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['container', 'day', 'dot'];

    connect() {
        this._onScroll = this.updateDots.bind(this);
        this.scrollToToday();
        this.containerTarget.addEventListener('scroll', this._onScroll, { passive: true });
    }

    disconnect() {
        if (this.hasContainerTarget) {
            this.containerTarget.removeEventListener('scroll', this._onScroll);
        }
    }

    scrollToToday() {
        const today = this.containerTarget.querySelector('.week-day.today');
        if (!today) {
            return;
        }
        const containerRect = this.containerTarget.getBoundingClientRect();
        const dayRect = today.getBoundingClientRect();
        const scrollLeft = today.offsetLeft - (containerRect.width / 2) + (dayRect.width / 2);
        this.containerTarget.scrollTo({ left: Math.max(0, scrollLeft), behavior: 'instant' });
    }

    updateDots() {
        if (!this.hasDotTarget) {
            return;
        }
        const container = this.containerTarget;
        const scrollLeft = container.scrollLeft;
        const dayWidth = this.dayTargets[0]?.offsetWidth || 1;
        const visibleCenter = scrollLeft + container.clientWidth / 2;
        let closestIdx = 0;
        let closestDist = Infinity;

        this.dayTargets.forEach((day, idx) => {
            const dayCenter = day.offsetLeft + dayWidth / 2;
            const dist = Math.abs(dayCenter - visibleCenter);
            if (dist < closestDist) {
                closestDist = dist;
                closestIdx = idx;
            }
        });

        this.dotTargets.forEach((dot, idx) => {
            dot.classList.toggle('active', idx === closestIdx);
        });
    }
}
