import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';

export default class extends Controller {
  static targets = ['input', 'calendar'];
  static values = { existingLeaves: Array };

  #boundHideTooltip = null;

  connect() {
    const now = new Date();
    this.leaveMap = this.#buildLeaveMap();
    this.activeTooltip = null;

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
      onDayCreate: (_dObj, _dStr, _fp, dayElem) => {
        this.#markLeaveDay(dayElem);
      },
    });

    this.#boundHideTooltip = () => this.#hideTooltip();
    document.addEventListener('click', this.#boundHideTooltip);
  }

  disconnect() {
    this.flatpickrInstance?.destroy();
    this.#hideTooltip();
    document.removeEventListener('click', this.#boundHideTooltip);
  }

  existingLeavesValueChanged() {
    this.leaveMap = this.#buildLeaveMap();
    this.flatpickrInstance?.redraw();
  }

  #buildLeaveMap() {
    const map = new Map();

    for (const leave of this.existingLeavesValue) {
      const start = this.#parseDate(leave.start);
      const end = this.#parseDate(leave.end);
      const current = new Date(start);
      const isSingleDay = leave.start === leave.end;

      while (current <= end) {
        const key = this.#formatDate(current);
        if (!map.has(key)) {
          map.set(key, []);
        }

        let position;
        if (isSingleDay) {
          position = 'single';
        } else if (key === leave.start) {
          position = 'start';
        } else if (key === leave.end) {
          position = 'end';
        } else {
          position = 'middle';
        }

        map.get(key).push({ ...leave, position });
        current.setDate(current.getDate() + 1);
      }
    }

    return map;
  }

  #markLeaveDay(dayElem) {
    const key = this.#formatDate(dayElem.dateObj);
    const entries = this.leaveMap.get(key);

    if (!entries) return;

    const uniqueTypes = [];
    const seen = new Set();
    for (const entry of entries) {
      if (!seen.has(entry.type)) {
        seen.add(entry.type);
        uniqueTypes.push(entry);
      }
    }

    const prevDay = new Date(dayElem.dateObj);
    prevDay.setDate(prevDay.getDate() - 1);
    const nextDay = new Date(dayElem.dateObj);
    nextDay.setDate(nextDay.getDate() + 1);
    const hasPrev = this.leaveMap.has(this.#formatDate(prevDay));
    const hasNext = this.leaveMap.has(this.#formatDate(nextDay));

    let position;
    if (hasPrev && hasNext) {
      position = 'middle';
    } else if (hasNext) {
      position = 'start';
    } else if (hasPrev) {
      position = 'end';
    } else {
      position = 'single';
    }

    const hasPending = entries.some((e) => e.status === 'pending');
    const bar = document.createElement('span');
    bar.classList.add('flatpickr-leave-bar', `flatpickr-leave-bar--${position}`);
    bar.style.bottom = '2px';

    if (uniqueTypes.length === 1) {
      bar.style.backgroundColor = uniqueTypes[0].color;
    } else {
      const stops = uniqueTypes.map((e, i) => {
        const pct = (i / uniqueTypes.length) * 100;
        const nextPct = ((i + 1) / uniqueTypes.length) * 100;
        return `${e.color} ${pct}%, ${e.color} ${nextPct}%`;
      });
      bar.style.background = `linear-gradient(to right, ${stops.join(', ')})`;
    }

    if (hasPending) {
      bar.classList.add('flatpickr-leave-bar--pending');
    }

    bar.addEventListener('mouseenter', (e) => {
      this.#showTooltip(e, uniqueTypes);
    });
    bar.addEventListener('mouseleave', () => {
      this.#hideTooltip();
    });

    dayElem.appendChild(bar);
  }

  #showTooltip(event, types) {
    this.#hideTooltip();

    const tooltip = document.createElement('div');
    tooltip.classList.add('flatpickr-leave-tooltip');

    for (const t of types) {
      const row = document.createElement('div');
      row.classList.add('flatpickr-leave-tooltip__row');

      const swatch = document.createElement('span');
      swatch.classList.add('flatpickr-leave-tooltip__swatch');
      swatch.style.backgroundColor = t.color;

      const label = document.createElement('span');
      label.textContent = t.type;

      row.appendChild(swatch);
      row.appendChild(label);
      tooltip.appendChild(row);
    }

    document.body.appendChild(tooltip);
    this.activeTooltip = tooltip;

    const barRect = event.target.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();

    tooltip.style.left = `${barRect.left + barRect.width / 2 - tooltipRect.width / 2}px`;
    tooltip.style.top = `${barRect.top - tooltipRect.height - 6}px`;

    requestAnimationFrame(() => {
      tooltip.classList.add('flatpickr-leave-tooltip--visible');
    });
  }

  #hideTooltip() {
    if (!this.activeTooltip) return;

    const tooltip = this.activeTooltip;
    this.activeTooltip = null;
    tooltip.classList.remove('flatpickr-leave-tooltip--visible');
    tooltip.addEventListener('transitionend', () => tooltip.remove(), { once: true });

    setTimeout(() => tooltip.remove(), 200);
  }

  #parseDate(dateStr) {
    const [y, m, d] = dateStr.split('-').map(Number);
    return new Date(y, m - 1, d);
  }

  #formatDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }
}
