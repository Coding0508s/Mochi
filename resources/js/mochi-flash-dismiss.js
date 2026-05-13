/**
 * [data-mochi-flash-dismiss] 플래시 알림을 지정 ms 후 숨김 (기본 3000).
 * Livewire DOM 갱신은 MutationObserver로 감지한다.
 */
const DEFAULT_MS = 3000;

function parseDismissMs(el) {
    const raw = el.getAttribute('data-mochi-flash-dismiss');
    if (raw === null || raw === '') {
        return DEFAULT_MS;
    }
    const n = parseInt(raw, 10);

    return Number.isFinite(n) && n > 0 ? n : DEFAULT_MS;
}

export function initMochiFlashDismiss() {
    document.querySelectorAll('[data-mochi-flash-dismiss]').forEach((el) => {
        if (!(el instanceof HTMLElement)) {
            return;
        }
        if (el.dataset.mochiFlashDismissScheduled === '1') {
            return;
        }
        el.dataset.mochiFlashDismissScheduled = '1';
        const ms = parseDismissMs(el);
        window.setTimeout(() => {
            el.classList.add('hidden');
            el.setAttribute('aria-hidden', 'true');
        }, ms);
    });
}

let observerRaf = null;

function scheduleInitMochiFlashDismiss() {
    if (observerRaf !== null) {
        return;
    }
    observerRaf = window.requestAnimationFrame(() => {
        observerRaf = null;
        initMochiFlashDismiss();
    });
}

export function registerMochiFlashDismissObserver() {
    if (typeof MutationObserver === 'undefined') {
        return;
    }
    const observer = new MutationObserver(() => scheduleInitMochiFlashDismiss());
    observer.observe(document.documentElement, { childList: true, subtree: true });
}
