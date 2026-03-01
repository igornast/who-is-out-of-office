import { Controller } from '@hotwired/stimulus';
import { generateCsrfToken } from './csrf_protection_controller.js';

export default class extends Controller {
    static targets = ['row', 'toast'];

    submitAction(event) {
        event.preventDefault();

        const form = event.target.closest('form');
        if (!form) return;

        const row = form.closest('[data-recent-requests-target="row"]');
        const button = form.querySelector('button[type="submit"]');
        const buttons = row ? row.querySelectorAll('.table-action-btn.approve, .table-action-btn.reject') : [];

        buttons.forEach(btn => {
            btn.classList.add('loading');
            btn.disabled = true;
        });

        generateCsrfToken(form);

        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' },
        })
            .then(response => response.json().then(data => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (ok && data.success) {
                    this._updateRow(row, data.status);
                    this._showToast(data.message, 'success');
                } else {
                    this._enableButtons(buttons);
                    this._showToast(data.message || 'Something went wrong.', 'error');
                }
            })
            .catch(() => {
                this._enableButtons(buttons);
                this._showToast('Network error. Please try again.', 'error');
            });
    }

    _updateRow(row, status) {
        if (!row) return;

        const badge = row.querySelector('.status-badge');
        if (badge) {
            badge.className = 'status-badge status-' + status;
            badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        }

        const actionForms = row.querySelectorAll('.inline-action-form');
        actionForms.forEach(form => form.remove());

        row.classList.add('row-flash-success');
        setTimeout(() => row.classList.remove('row-flash-success'), 1000);
    }

    _enableButtons(buttons) {
        buttons.forEach(btn => {
            btn.classList.remove('loading');
            btn.disabled = false;
        });
    }

    _showToast(message, type) {
        if (!this.hasToastTarget) return;

        const toast = this.toastTarget;
        toast.textContent = message;
        toast.className = 'widget-toast widget-toast--' + type + ' widget-toast--visible';

        clearTimeout(this._toastTimer);
        this._toastTimer = setTimeout(() => {
            toast.classList.remove('widget-toast--visible');
        }, 3000);
    }
}
