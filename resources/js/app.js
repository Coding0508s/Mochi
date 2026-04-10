import './bootstrap';
import { mountSpecialText } from './special-text';
import { initMochiFloatingInputs } from './mochi-floating-input';

/**
 * 기관 지원 보고서 textarea: Enter 시 새 줄에 "▶ " 삽입 (Livewire input 동기화)
 */
window.mochiSupportEnterTriangle = function (event) {
    const el = event.target;
    if (!(el instanceof HTMLTextAreaElement) || el.disabled) {
        return;
    }
    event.preventDefault();
    const start = el.selectionStart;
    const end = el.selectionEnd;
    const val = el.value;
    const insert = val === '' ? '▶ ' : '\n▶ ';
    el.value = val.slice(0, start) + insert + val.slice(end);
    const pos = start + insert.length;
    el.setSelectionRange(pos, pos);
    el.dispatchEvent(new Event('input', { bubbles: true }));
};

document.addEventListener('DOMContentLoaded', () => {
    mountSpecialText();
    initMochiFloatingInputs();
});
