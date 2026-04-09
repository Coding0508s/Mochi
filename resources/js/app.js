import './bootstrap';
import { mountSpecialText } from './special-text';
import { initMochiFloatingInputs } from './mochi-floating-input';

document.addEventListener('DOMContentLoaded', () => {
    mountSpecialText();
    initMochiFloatingInputs();
});
