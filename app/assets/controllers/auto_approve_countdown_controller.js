import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        target: String,
        approving: String,
        days: String,
        hours: String,
        minutes: String,
        seconds: String,
    }

    connect() {
        this.targetTime = new Date(this.targetValue).getTime();

        if (Number.isNaN(this.targetTime)) {
            return;
        }

        this.render();
        this.timer = window.setInterval(() => this.render(), 1000);
    }

    disconnect() {
        this.stop();
    }

    render() {
        const remaining = Math.floor((this.targetTime - Date.now()) / 1000);

        if (remaining <= 0) {
            this.element.textContent = this.approvingValue;
            this.stop();

            return;
        }

        const days = Math.floor(remaining / 86400);
        const hours = Math.floor((remaining % 86400) / 3600);
        const minutes = Math.floor((remaining % 3600) / 60);
        const seconds = remaining % 60;

        const parts = [];

        if (days > 0) {
            parts.push(`${this.pad(days)}${this.daysValue}`);
        }

        parts.push(`${this.pad(hours)}${this.hoursValue}`);
        parts.push(`${this.pad(minutes)}${this.minutesValue}`);
        parts.push(`${this.pad(seconds)}${this.secondsValue}`);

        this.element.textContent = parts.join(' ');
    }

    stop() {
        if (this.timer) {
            window.clearInterval(this.timer);
            this.timer = null;
        }
    }

    pad(value) {
        return String(value).padStart(2, '0');
    }
}
