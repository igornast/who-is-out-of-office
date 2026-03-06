import { Controller } from '@hotwired/stimulus';

const ACTION_CONFIG = {
    approve: { confirmClass: 'btn-success' },
    reject: { confirmClass: 'btn-danger' },
    withdraw: { confirmClass: 'btn-danger' },
};

export default class extends Controller {
    static targets = ['toast'];

    _pendingAction = null;

    connect() {
        this._handleClick = (e) => {
            const btn = e.target.closest('[data-lr-action]');
            if (btn) {
                e.preventDefault();
                this._open(btn);
            }
        };
        document.addEventListener('click', this._handleClick);
    }

    disconnect() {
        document.removeEventListener('click', this._handleClick);
    }

    _open(btn) {
        const actionName = btn.dataset.lrAction;
        const url = btn.dataset.lrUrl || btn.getAttribute('href');
        const token = btn.dataset.lrToken || null;
        const reload = btn.dataset.lrReload !== 'false';

        if (!actionName || !url || url === '#') return;

        this._pendingAction = { btn, actionName, url, token, reload };

        const config = ACTION_CONFIG[actionName] || ACTION_CONFIG.approve;

        const title = this.element.dataset[actionName + 'Title'] || 'Confirm';
        const body = this.element.dataset[actionName + 'Body'] || 'Are you sure?';
        const action = this.element.dataset[actionName + 'Action'] || 'Confirm';

        this.element.querySelector('[data-modal="title"]').textContent = title;
        this.element.querySelector('[data-modal="body"]').textContent = body;

        const confirmBtn = this.element.querySelector('[data-modal="confirm"]');
        confirmBtn.textContent = action;
        confirmBtn.className = 'btn ' + config.confirmClass;

        bootstrap.Modal.getOrCreateInstance(this.element).show();
    }

    confirm() {
        if (!this._pendingAction) return;

        const { btn, actionName, url, token, reload } = this._pendingAction;
        const row = btn.closest('[data-lr-row]');

        bootstrap.Modal.getInstance(this.element)?.hide();
        this._setLoading(btn, row, true);

        const headers = { 'Accept': 'application/json' };
        let fetchUrl = url;
        let body = null;

        if (token) {
            const formData = new FormData();
            formData.append('_token', token);
            body = formData;
        }

        fetch(fetchUrl, { method: 'POST', body, headers })
            .then(response => response.json().then(data => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (ok && data.success) {
                    if (reload) {
                        this._showToast(data.message, 'success');
                        setTimeout(() => window.location.reload(), 600);
                    } else {
                        this._updateRow(row, data.status);
                        this._showToast(data.message, 'success');
                    }
                } else {
                    this._setLoading(btn, row, false);
                    this._showToast(data.message || 'Something went wrong.', 'error');
                }
            })
            .catch(() => {
                this._setLoading(btn, row, false);
                this._showToast('Network error. Please try again.', 'error');
            });

        this._pendingAction = null;
    }

    _setLoading(btn, row, loading) {
        if (row) {
            row.querySelectorAll('.table-action-btn.approve, .table-action-btn.reject').forEach(b => {
                b.classList.toggle('loading', loading);
                b.disabled = loading;
            });
        } else {
            btn.classList.toggle('loading', loading);
        }
    }

    _updateRow(row, status) {
        if (!row) return;

        const badge = row.querySelector('.status-badge');
        if (badge) {
            badge.className = 'status-badge status-' + status;
            badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        }

        row.querySelectorAll('[data-lr-action]').forEach(el => el.remove());

        row.classList.add('row-flash-success');
        setTimeout(() => row.classList.remove('row-flash-success'), 1000);
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
