# 작업 로그 — Mochi UI (2026-04-09)

로그인·앱 셸·공통 컴포넌트를 모키(Mochi) 브랜드 톤과 liquid-glass 스타일에 맞추는 작업을 정리한 문서입니다.

---

## 요약

- **로그인 화면**: 플로팅 라벨 입력(`mochi-floating-input`), Sign In 버튼에 `liquid-glass-button`의 **`mochi-blue`** 변형 적용.
- **스페셜 텍스트**: 정착 후 CSS 기반 **쉬머(4회)** → 종료 시 일반 색으로 복귀.
- **앱 레이아웃**: 탑바 네비·프로필·로그아웃에 **SVG displacement** 기반 글라스 효과.
- **사이드바**: 호버/포커스 시 밝은 면용 liquid-glass(하이라이트 + `backdrop-filter`).
- **자동완성**: Chrome 등에서 플로팅 라벨·배경이 어긋나지 않도록 CSS(`~` 형제 선택자, 비레이어 `!important` 블록)와 JS 보조 동기화.

---

## 1. 로그인 (`resources/views/auth/login.blade.php`)

| 항목 | 내용 |
|------|------|
| 이메일 / 비밀번호 | `<x-ui.mochi-floating-input>` + 아이콘 슬롯, 스크린리더용 `sr-only` 라벨 |
| 비밀번호 찾기 | 라벨 옆이 아니라 필드 위 우측 정렬로 재배치 |
| Sign In | `<x-ui.liquid-glass-button pill filter-id="login-glass-filter" variant="mochi-blue" class="w-full">` |

---

## 2. Liquid Glass 버튼 (`resources/views/components/ui/liquid-glass-button.blade.php`)

- **Props**: `type`, `filterId`, `pill`, **`variant`** (`neutral` | `mochi-blue`).
- **구조**: 깊이용 `box-shadow` 레이어 + `backdrop-filter: url(#filterId)`(및 `-webkit-`) + 인라인 SVG `feTurbulence` / `feDisplacementMap` 필터.
- **`mochi-blue`**: 모키 블루 세로 그라데이션, 흰 텍스트, 호버 스케일·그림자, 포커스 링(`mochi-excel`).
- **접근성**: `prefers-reduced-motion` 대응(`motion-reduce:hover:scale-100` 등).
- **튜닝 이력**: 첫 적용 후 “너무 흐리다” 피드백에 따라 **그라데이션을 더 진하게**, **글라스 오버레이 불투명도를 낮춰** 베이스 색이 더 드러나도록 조정한 버전이 있음(로컬/브랜치에 따라 hex·opacity 문자열이 다를 수 있음).

---

## 3. 플로팅 입력 (`mochi-floating-input`)

### Blade (`resources/views/components/ui/mochi-floating-input.blade.php`)

- input을 라벨(글자 단위 span)보다 **먼저** 두어 `~` 형제 선택자로 라벨 상승 상태를 CSS만으로도 맞춤.
- placeholder가 있을 때 `data-mochi-floating-ph` 등으로 `:not(:placeholder-shown)` 분기.

### JS (`resources/js/mochi-floating-input.js`)

- 포커스/값에 따른 `is-active` 클래스 동기화.
- 자동완성이 이벤트 없이 들어오는 경우를 대비한 **지연 sync / 폴링**, `animationstart`(autofill 감지 키프레임) 등.

### 엔트리 (`resources/js/app.js`)

- `DOMContentLoaded`에서 `initMochiFloatingInputs()` 호출.

### CSS (`resources/css/app.css`)

- `@layer components`: `.mochi-floating-input__letters` / `__char`, 아이콘 있을 때 글자 들여쓰기, `:-webkit-autofill`·`:autofill`·`:valid`·`:user-valid`·placeholder 조합.
- **파일 하단 비레이어 블록**: Tailwind utilities보다 강하게 Chrome autofill 배경·텍스트색 덮기(`!important`, 긴 `background-color` transition 트릭, `mochi-autofill-detect` 키프레임).
- `.mochi-auth-field`: 게스트 폼 필드 포커스 링·대비(참고용/연계 스타일).

---

## 4. 스페셜 텍스트 (`resources/js/special-text.js` + `app.css`)

- 정착 애니메이션 후 **`special-text-shine`** 클래스 부착 → 그라데이션 텍스트 쉬머 **4사이클** 후 `animationend`로 제거.
- `prefers-reduced-motion: reduce` 시 쉬머 생략.

---

## 5. 앱 레이아웃 탑바 (`resources/views/components/layouts/app.blade.php`)

- 숨김 SVG에 `id="mochi-topbar-glass-filter"` 정의(liquid-glass와 동일 계열 displacement).
- 네비 링크: `.mochi-topbar-glass-link` + depth/blur 자식.
- 프로필: `route('profile.edit')` 링크(`.mochi-topbar-profile`).
- 로그아웃: 별도 필(`.mochi-topbar-logout`) 안에 폼+버튼.

---

## 6. 사이드바 (`resources/css/app.css`)

- `.sidebar-item` / `.sidebar-subitem`: `::before` 하이라이트, `::after`에 `backdrop-filter: url('#mochi-topbar-glass-filter')`.
- 호버·활성·포커스 시 배경·그림자·약한 스케일, `prefers-reduced-motion` 시 transform 완화.

---

## 빌드

- 프론트: `npm run build` (Vite) 성공 기준으로 검증.

---

## 변경 파일 목록(참고)

| 경로 | 역할 |
|------|------|
| `resources/css/app.css` | 스페셜 텍스트 쉬머, auth/floating-input, 탑바·사이드바 글라스, autofill 오버라이드 |
| `resources/js/app.js` | 플로팅 입력 초기화 |
| `resources/js/mochi-floating-input.js` | 플로팅 입력 동작 |
| `resources/js/special-text.js` | 쉬머 후처리 |
| `resources/views/auth/login.blade.php` | 플로팅 입력 + glass 버튼 |
| `resources/views/components/layouts/app.blade.php` | 탑바 글라스·프로필/로그아웃 분리 |
| `resources/views/components/ui/liquid-glass-button.blade.php` | variant·깊이·블러 레이어 |
| `resources/views/components/ui/mochi-floating-input.blade.php` | 플로팅 라벨 마크업 |
| `resources/views/layouts/guest.blade.php` | (브랜치에 따라) 게스트 배경·토큰 정렬 |

---

## 후속 아이디어

- 다른 화면의 주요 CTA에 `variant="mochi-blue"` 재사용.
- 글라스 필터 ID가 페이지당 중복되지 않도록 `filter-id` 네이밍 규칙 문서화.
