import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['status'];

    static values = {
        updateUrl: String,
        disconnectUrl: String,
        csrfUpdate: String,
        csrfDisconnect: String,
        memberId: { type: String, default: '' },
        statusSyncEnabled: { type: Boolean, default: true },
        featureEnabled: { type: Boolean, default: false },
        labelSaving: String,
        labelSave: String,
        labelConnected: String,
        labelMemberId: String,
        labelNotConnected: String,
    };

    _bsModal = null;
    _modalEl = null;
    _memberIdInput = null;
    _saveBtn = null;
    _disconnectBtn = null;
    _syncWrapper = null;
    _syncCheckbox = null;

    connect() {
        this._modalEl = document.getElementById('slackConfigModal');
        if (!this._modalEl) return;

        this._bsModal = bootstrap.Modal.getOrCreateInstance(this._modalEl);
        this._memberIdInput = this._modalEl.querySelector('#slack-member-id-input');
        this._saveBtn = this._modalEl.querySelector('#slackSaveBtn');
        this._disconnectBtn = this._modalEl.querySelector('#slackDisconnectBtn');
        this._syncWrapper = this._modalEl.querySelector('#slack-status-sync-wrapper');
        this._syncCheckbox = this._modalEl.querySelector('#slack-status-sync-checkbox');

        this._boundSave = this.save.bind(this);
        this._boundDisconnect = this.disconnect_slack.bind(this);
        this._saveBtn.addEventListener('click', this._boundSave);
        this._disconnectBtn.addEventListener('click', this._boundDisconnect);
    }

    disconnect() {
        if (this._saveBtn) this._saveBtn.removeEventListener('click', this._boundSave);
        if (this._disconnectBtn) this._disconnectBtn.removeEventListener('click', this._boundDisconnect);
    }

    open() {
        if (!this._modalEl) return;

        this._memberIdInput.value = this.memberIdValue;
        this._disconnectBtn.style.display = this.memberIdValue ? '' : 'none';

        if (this.featureEnabledValue && this._syncWrapper) {
            this._syncWrapper.style.display = '';
            this._syncCheckbox.checked = this.statusSyncEnabledValue;
        } else if (this._syncWrapper) {
            this._syncWrapper.style.display = 'none';
        }

        this._bsModal.show();
    }

    save() {
        var memberId = this._memberIdInput.value.trim();
        if (!memberId) return;

        this._saveBtn.disabled = true;
        this._saveBtn.textContent = this.labelSavingValue;

        var formData = new FormData();
        formData.append('_token', this.csrfUpdateValue);
        formData.append('slack_member_id', memberId);
        if (this.featureEnabledValue && this._syncCheckbox) {
            formData.append('slack_status_sync_enabled', this._syncCheckbox.checked ? '1' : '0');
        }

        fetch(this.updateUrlValue, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.memberIdValue = data.memberId;
                    this.statusSyncEnabledValue = !!data.statusSyncEnabled;
                    this._renderConnectedStatus(data.memberId);
                    this._bsModal.hide();
                }
                this._resetSaveBtn();
            })
            .catch(() => this._resetSaveBtn());
    }

    disconnect_slack() {
        this._disconnectBtn.disabled = true;

        var formData = new FormData();
        formData.append('_token', this.csrfDisconnectValue);

        fetch(this.disconnectUrlValue, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.memberIdValue = '';
                    this._renderDisconnectedStatus();
                    this._bsModal.hide();
                }
                this._disconnectBtn.disabled = false;
            })
            .catch(() => {
                this._disconnectBtn.disabled = false;
            });
    }

    _renderConnectedStatus(memberId) {
        this.statusTarget.innerHTML =
            '<span class="connected-service-badge connected-service-badge-success">' +
            '<i data-lucide="check-circle-2"></i> ' + this.labelConnectedValue +
            '</span>' +
            '<span class="connected-service-meta">' + this.labelMemberIdValue.replace('%id%', memberId) + '</span>';
        if (typeof lucide !== 'undefined') { lucide.createIcons(); }
    }

    _renderDisconnectedStatus() {
        this.statusTarget.innerHTML =
            '<span class="connected-service-badge connected-service-badge-muted">' +
            this.labelNotConnectedValue +
            '</span>';
    }

    _resetSaveBtn() {
        this._saveBtn.disabled = false;
        this._saveBtn.textContent = this.labelSaveValue;
    }
}
