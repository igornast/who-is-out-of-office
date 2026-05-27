import { generateCsrfToken } from './controllers/csrf_protection_controller.js';

/*
 * Rotates a stateless CSRF token for AJAX endpoints.
 *
 * For token IDs listed in framework.csrf_protection.stateless_token_ids, Twig's
 * csrf_token() renders only the cookie-name placeholder (e.g. "csrf-token"); the
 * real token is produced by csrf_protection_controller on form submit. Raw fetch()
 * calls bypass form submission, so they must rotate the token themselves or the
 * server rejects the request with "Invalid CSRF token".
 *
 * This wraps the framework's generateCsrfToken(): it builds a throwaway form field
 * holding the placeholder, lets generateCsrfToken set the matching __Host-/SameSite
 * double-submit cookie, and returns the rotated token to send as _token.
 *
 * The value is set via setAttribute (not .value) so the input's dirty flag stays
 * false — only then does generateCsrfToken's defaultValue assignment surface through
 * input.value.
 */
export function rotateStatelessCsrfToken(placeholder) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = '_csrf_token';
    input.setAttribute('value', placeholder);

    const form = document.createElement('form');
    form.appendChild(input);

    generateCsrfToken(form);

    return input.value;
}
