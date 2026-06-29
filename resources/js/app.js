import './bootstrap';
import { mountSpecialText } from './special-text';
import { initMochiFloatingInputs } from './mochi-floating-input';
import { initMochiFlashDismiss, registerMochiFlashDismissObserver } from './mochi-flash-dismiss';

/**
 * 기관 지원 보고서 textarea: Enter 시 새 줄에 "▶ " 삽입 (Livewire input 동기화)
 */
window.mochiSupportEnterTriangle = function (event) {
    const el = event.target;
    if (!(el instanceof HTMLTextAreaElement) || el.disabled) {
        return;
    }
    //Shift+Enter 시 줄바꿈. "▶ " 삽입안함
    if(event.shiftKey){
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
    initMochiFlashDismiss();
    registerMochiFlashDismissObserver();
});

/**
 * 세션/CSRF 토큰 만료(419) 처리: Livewire 기본 영어 안내 대신 한국어로 안내 후 새로고침.
 * keep-alive 폴링으로 419 자체를 예방하지만, 그래도 발생하면 작성 화면이 멈추지 않도록 한다.
 */
document.addEventListener('livewire:init', () => {
    Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status === 419) {
                preventDefault();
                alert('페이지가 만료되었습니다. 새로 고침 후 다시 시도해 주세요.');
                window.location.reload();
            }
        });
    });
});
