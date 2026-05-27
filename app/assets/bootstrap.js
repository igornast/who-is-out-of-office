import { startStimulusApp} from "@symfony/stimulus-bundle";
import { rotateStatelessCsrfToken } from './csrf.js';

const app = startStimulusApp();

// Bridge for inline <script> blocks (calendar URL regenerate/customize) that POST
// via fetch and need a rotated stateless CSRF token. Stimulus controllers should
// import rotateStatelessCsrfToken from './csrf.js' directly instead.
window.AppCsrf = { rotateStatelessCsrfToken };