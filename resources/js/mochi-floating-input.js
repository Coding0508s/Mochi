/**
 * 로그인용 플로팅 라벨: 포커스 또는 값이 있을 때 글자 단위로 위로 이동 (motion/react 포팅)
 * 브라우저 자동완성은 input 전까지 지연되는 경우가 있어 보조 타이머·:-webkit-autofill로 동기화
 */
function isWebkitAutofill(el) {
    try {
        return el.matches(':-webkit-autofill');
    } catch {
        return false;
    }
}

export function initMochiFloatingInputs() {
    document.querySelectorAll('[data-mochi-floating-input]').forEach((root) => {
        if (!(root instanceof HTMLElement)) {
            return;
        }
        const input = root.querySelector('[data-mochi-floating-input-control]');
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const sync = () => {
            const hasValue = input.value != null && input.value.length > 0;
            const focused = document.activeElement === input;
            const autofilled = isWebkitAutofill(input);
            root.classList.toggle('is-active', focused || hasValue || autofilled);
        };

        const syncAfterAutofillDelay = () => {
            [0, 50, 150, 400, 800, 1600].forEach((ms) => {
                window.setTimeout(sync, ms);
            });
        };

        input.addEventListener('focus', () => {
            sync();
            syncAfterAutofillDelay();
        });
        input.addEventListener('blur', sync);
        input.addEventListener('input', sync);
        input.addEventListener('change', sync);
        input.addEventListener('animationstart', (e) => {
            if (e.animationName === 'mochi-autofill-detect') {
                sync();
                syncAfterAutofillDelay();
            }
        });
        sync();
        /* 첫 페인트 직후 늦게 들어오는 자동완성 */
        syncAfterAutofillDelay();
    });
}
