import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        name: String,
        morning: String,
        afternoon: String,
        evening: String,
    }

    connect() {
        const hour = new Date().getHours();
        let greeting;

        if (hour < 12) {
            greeting = this.morningValue;
        } else if (hour < 18) {
            greeting = this.afternoonValue;
        } else {
            greeting = this.eveningValue;
        }

        this.element.textContent = `${greeting}, ${this.nameValue}`;
    }
}
