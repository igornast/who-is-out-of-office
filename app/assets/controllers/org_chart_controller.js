import { Controller } from '@hotwired/stimulus';

const BRANCH_PALETTE = ['#E8876F', '#D4A843', '#8B7BAE', '#5B8A72'];

export default class extends Controller {
    static targets = ['tree'];

    connect() {
        if (!this.hasTreeTarget) {
            return;
        }
        let index = 0;
        const paint = (node, isRoot) => {
            const children = node.querySelector(':scope > .org-children');
            if (!children) {
                return;
            }
            const color = isRoot ? 'var(--primary)' : BRANCH_PALETTE[index++ % BRANCH_PALETTE.length];
            children.style.setProperty('--connector', color);
            node.style.setProperty('--own', color);
            children.querySelectorAll(':scope > .org-node').forEach((child) => paint(child, false));
        };
        this.treeTarget.querySelectorAll(':scope > .org-node').forEach((root) => paint(root, true));
    }

    toggle(event) {
        const node = event.currentTarget.closest('.org-node');
        this.setCollapsed(node, !node.classList.contains('is-collapsed'));
    }

    expandAll() {
        this.treeTarget.querySelectorAll('.org-node--has-children')
            .forEach((node) => this.setCollapsed(node, false));
    }

    firstLevel() {
        this.treeTarget.querySelectorAll('.org-node--has-children')
            .forEach((node) => this.setCollapsed(node, node.parentElement !== this.treeTarget));
    }

    setCollapsed(node, collapsed) {
        node.classList.toggle('is-collapsed', collapsed);
        const toggle = node.querySelector(':scope > .org-node-content > .org-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
    }
}
