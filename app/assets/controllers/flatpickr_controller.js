import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';

export default class FlatpickrController extends Controller {

  connect() {
    flatpickr(this.element, {
      mode: "range",
      dateFormat: "Y-m-d",
      locale: {
        firstDayOfWeek: 1
      },
      onClose: () => {
        this.element.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }
}
