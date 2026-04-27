/*
 * Posts to app_whats_new_mark_read on connect, after the global csrf_protection_controller
 * rotates the stateless CSRF token in the hidden form. We dispatch the form's submit
 * event so csrf_protection_controller's capture-phase listener fires first (it sets the
 * cookie and rotates the input value). Our bubble-phase listener then prevents the
 * default navigation and POSTs via fetch with the now-rotated FormData.
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        const form = this.element;
        form.addEventListener('submit', this._submit, { once: true });
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        }
    }

    _submit = (event) => {
        event.preventDefault();
        fetch(event.target.action, {
            method: 'POST',
            credentials: 'same-origin',
            body: new FormData(event.target),
        }).catch(() => { /* fire-and-forget */ });
    }
}
