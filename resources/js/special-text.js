/**
 * "Special text" decode / glitch animation (ported from React SpecialText).
 * Elements: [data-special-text] with data-text="…" (optional speed/delay in ms/sec).
 *
 * 칸마다 RANDOM_CHARS에서 한 글자만 소비(첫 표시 시 할당 후 고정).
 * 풀 소진 후에는 '_'로 채움.
 *
 * 시작·종료 시 opacity / transform / filter 전환으로 부드럽게 (prefers-reduced-motion 시 생략).
 * 정착 후: ShiningText 스타일 쉬머를 CSS로 4회 반복 후 멈춤(motion 없음).
 */

const SHINE_CLASS = 'special-text-shine';

/** @type {WeakMap<HTMLElement, (e: AnimationEvent) => void>} */
const shineEndHandlers = new WeakMap();

const RANDOM_CHARS = 'WELCOME TO NEW';

const FALLBACK_CHAR = '→';

function prefersReducedMotion() {
    if (typeof window === 'undefined' || !window.matchMedia) {
        return false;
    }
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * @param {HTMLElement} el
 */
function resetMotionStyles(el) {
    el.style.transition = '';
    el.style.opacity = '';
    el.style.transform = '';
    el.style.filter = '';
    el.style.willChange = '';
    const onEnd = shineEndHandlers.get(el);
    if (onEnd) {
        el.removeEventListener('animationend', onEnd);
        shineEndHandlers.delete(el);
    }
    el.classList.remove(SHINE_CLASS);
}

/**
 * 그라데이션 쉬머: CSS에서 4사이클 후 animationend → 클래스 제거로 일반 글자색으로 복귀
 * @param {HTMLElement} el
 */
function startShiningText(el) {
    if (prefersReducedMotion()) {
        return;
    }
    const onShineEnd = () => {
        el.classList.remove(SHINE_CLASS);
        shineEndHandlers.delete(el);
    };
    shineEndHandlers.set(el, onShineEnd);
    el.addEventListener('animationend', onShineEnd, { once: true });
    el.classList.add(SHINE_CLASS);
}

/**
 * 애니메이션 시작: 살짝 아래·흐림에서 제자리·선명으로
 * @param {HTMLElement} el
 */
function applySmoothIntro(el) {
    if (prefersReducedMotion()) {
        return;
    }
    el.style.willChange = 'opacity, transform, filter';
    el.style.opacity = '0';
    el.style.transform = 'translateY(0.35em)';
    el.style.filter = 'blur(3px)';
    el.style.transition = 'opacity 0.55s cubic-bezier(0.22, 1, 0.36, 1), transform 0.55s cubic-bezier(0.22, 1, 0.36, 1), filter 0.5s ease-out';
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
            el.style.filter = 'blur(0)';
        });
    });
    window.setTimeout(() => {
        el.style.willChange = '';
    }, 600);
}

/**
 * 최종 문구 고정: 아주 약한 블러에서 선명하게
 * @param {HTMLElement} el
 * @param {string} finalText
 */
function applySmoothSettle(el, finalText) {
    el.textContent = finalText;
    if (prefersReducedMotion()) {
        return;
    }
    el.style.willChange = 'filter, opacity';
    el.style.transition = 'filter 0.5s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.4s ease-out';
    el.style.filter = 'blur(2px)';
    el.style.opacity = '0.94';
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            el.style.filter = 'blur(0)';
            el.style.opacity = '1';
        });
    });
    window.setTimeout(() => {
        el.style.willChange = '';
        el.style.transition = '';
        el.style.filter = '';
        el.style.opacity = '';
        startShiningText(el);
    }, 550);
}

/**
 * @returns {() => string}
 */
function createNoiseCharSource() {
    let poolIndex = 0;
    return () => {
        if (poolIndex < RANDOM_CHARS.length) {
            return RANDOM_CHARS[poolIndex++];
        }
        return FALLBACK_CHAR;
    };
}

/**
 * @param {(string | null)[]} columnNoise
 * @param {() => string} nextNoiseChar
 * @param {number} index
 */
function ensureColumnNoise(columnNoise, nextNoiseChar, index) {
    if (columnNoise[index] == null) {
        columnNoise[index] = nextNoiseChar();
    }
    return columnNoise[index];
}

function runPhase1(text, animationStep, columnNoise, nextNoiseChar) {
    const maxSteps = text.length * 2;
    const currentLength = Math.min(animationStep + 1, text.length);
    const chars = [];
    for (let i = 0; i < currentLength; i++) {
        chars.push(ensureColumnNoise(columnNoise, nextNoiseChar, i));
    }
    for (let i = currentLength; i < text.length; i++) {
        chars.push('\u00A0');
    }
    const display = chars.join('');
    if (animationStep < maxSteps - 1) {
        return { display, phase: 'phase1', step: animationStep + 1 };
    }
    return { display, phase: 'phase2', step: 0 };
}

function runPhase2(text, animationStep, columnNoise, nextNoiseChar) {
    const revealedCount = Math.floor(animationStep / 2);
    const chars = [];
    for (let i = 0; i < revealedCount && i < text.length; i++) {
        chars.push(text[i]);
    }
    if (revealedCount < text.length) {
        if (animationStep % 2 === 0) {
            chars.push('_');
        } else {
            chars.push(ensureColumnNoise(columnNoise, nextNoiseChar, revealedCount));
        }
    }
    for (let i = chars.length; i < text.length; i++) {
        chars.push(ensureColumnNoise(columnNoise, nextNoiseChar, i));
    }
    const display = chars.join('');
    if (animationStep < text.length * 2 - 1) {
        return { display, phase: 'phase2', step: animationStep + 1 };
    }
    return { display: text, phase: 'done', step: animationStep };
}

/**
 * @param {HTMLElement} el
 * @param {{ speed?: number, delay?: number }} [options]
 * @returns {() => void} cleanup
 */
export function initSpecialText(el, options = {}) {
    const text = el.dataset.text?.trim() || el.textContent?.trim() || '';
    if (!text.length) {
        return () => {};
    }

    const speed = Number(options.speed ?? el.dataset.speed ?? 20);
    const delaySec = Number(options.delay ?? el.dataset.delay ?? 0);

    el.textContent = ' '.repeat(text.length);

    let animationStep = 0;
    let currentPhase = 'phase1';
    /** @type {() => string} */
    let nextNoiseChar = createNoiseCharSource();
    /** @type {(string | null)[]} */
    let columnNoise = new Array(text.length).fill(null);
    /** @type {ReturnType<typeof setInterval> | null} */
    let intervalId = null;
    /** @type {number | null} */
    let startTimeoutId = null;

    function clearTimers() {
        if (intervalId !== null) {
            clearInterval(intervalId);
            intervalId = null;
        }
        if (startTimeoutId !== null) {
            window.clearTimeout(startTimeoutId);
            startTimeoutId = null;
        }
    }

    function tick() {
        if (currentPhase === 'phase1') {
            const out = runPhase1(text, animationStep, columnNoise, nextNoiseChar);
            el.textContent = out.display;
            if (out.phase === 'phase2') {
                currentPhase = 'phase2';
                animationStep = out.step;
            } else {
                animationStep = out.step;
            }
            return;
        }
        if (currentPhase === 'phase2') {
            const out = runPhase2(text, animationStep, columnNoise, nextNoiseChar);
            el.textContent = out.display;
            if (out.phase === 'done') {
                clearTimers();
                applySmoothSettle(el, out.display);
                return;
            }
            animationStep = out.step;
        }
    }

    function start() {
        animationStep = 0;
        currentPhase = 'phase1';
        nextNoiseChar = createNoiseCharSource();
        columnNoise = new Array(text.length).fill(null);
        resetMotionStyles(el);
        el.textContent = ' '.repeat(text.length);
        clearTimers();
        applySmoothIntro(el);
        intervalId = window.setInterval(tick, speed);
    }

    if (delaySec <= 0) {
        start();
    } else {
        startTimeoutId = window.setTimeout(() => {
            startTimeoutId = null;
            start();
        }, delaySec * 1000);
    }

    return () => {
        clearTimers();
        resetMotionStyles(el);
    };
}

export function mountSpecialText() {
    document.querySelectorAll('[data-special-text]').forEach((el) => {
        if (el instanceof HTMLElement) {
            initSpecialText(el);
        }
    });
}
