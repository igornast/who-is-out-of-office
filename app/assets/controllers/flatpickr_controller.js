import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';

export default class FlatpickrController extends Controller {

  connect() {
    flatpickr(this.element, {
      mode: "range",
      dateFormat: "Y-m-d",
      onClose: () => {
        this.element.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }
}
