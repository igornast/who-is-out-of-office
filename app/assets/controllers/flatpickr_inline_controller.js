import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';

export default class extends Controller {
  static targets = ['input', 'calendar'];

  connect() {
    const now = new Date();

    this.flatpickrInstance = flatpickr(this.inputTarget, {
      mode: 'range',
      dateFormat: 'Y-m-d',
      inline: true,
      appendTo: this.calendarTarget,
      minDate: new Date(now.getFullYear() - 1, 0, 1),
      maxDate: new Date(now.getFullYear() + 1, 11, 31),
      locale: { firstDayOfWeek: 1 },
      onChange: () => {
        this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
      },
    });
  }

  disconnect() {
    this.flatpickrInstance?.destroy();
  }
}
