import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';

export default class FlatpickrController extends Controller {

  connect() {
    const now = new Date();

    flatpickr(this.element, {
      mode: "range",
      dateFormat: "Y-m-d",
      minDate: new Date(now.getFullYear() - 1, 0, 1),
      maxDate: new Date(now.getFullYear() + 1, 11, 31),
      locale: {
        firstDayOfWeek: 1
      },
      onClose: () => {
        this.element.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }
}
