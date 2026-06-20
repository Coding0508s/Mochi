# MOCHI Platform Presentation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a 23-slide training presentation for the MOCHI platform in Figma Slides.

**Architecture:** Use the `create_new_file` tool to initialize a Slides file, then use `use_figma` (with `skillNames: "figma-use-slides"`) to generate the slides row by row in the slide grid. Each section from the design doc maps to a `SLIDE_ROW`. The slides will be populated with text and basic layout, with speaker notes added.

**Tech Stack:** Figma MCP (`create_new_file`, `use_figma`), Figma Plugin API

---

### Task 1: Create File & Section 1: Introduction (Slides 1-4)

**Files:**
- N/A (Figma File)

- [ ] **Step 1: Create the Figma Slides File**

Invoke `/figma-create-new-file slides "MOCHI Platform Training"`.
Run the MCP tool `create_new_file` with `fileName`: "MOCHI Platform Training", `editorType`: "slides". Note the returned `file_key`.

- [ ] **Step 2: Create Section 1 Slides (Introduction)**

Run `use_figma` with `fileKey` from Step 1 and `skillNames: "figma-use-slides"`.
```javascript
const grid = figma.currentPage.children.find(c => c.type === "SLIDE_GRID");

// Ensure fonts are loaded
await figma.loadFontAsync({ family: "Inter", style: "Regular" });
await figma.loadFontAsync({ family: "Inter", style: "Bold" });

// Create Row 1
const row1 = figma.createSlideRow();
grid.appendChild(row1);
row1.name = "Introduction";

// Slide 1: Title
const slide1 = figma.createSlide();
row1.appendChild(slide1);
const titleText1 = figma.createText();
slide1.appendChild(titleText1);
titleText1.fontName = { family: "Inter", style: "Bold" };
titleText1.characters = "GrapeSEED MOCHI 플랫폼 사용 가이드";
titleText1.fontSize = 64;
titleText1.x = 100; titleText1.y = 400;

// Slide 2: What is MOCHI?
const slide2 = figma.createSlide();
row1.appendChild(slide2);
const titleText2 = figma.createText();
slide2.appendChild(titleText2);
titleText2.fontName = { family: "Inter", style: "Bold" };
titleText2.characters = "MOCHI 플랫폼이란?";
titleText2.fontSize = 48;
titleText2.x = 100; titleText2.y = 100;

// Slide 3: Getting Started
const slide3 = figma.createSlide();
row1.appendChild(slide3);
const titleText3 = figma.createText();
slide3.appendChild(titleText3);
titleText3.fontName = { family: "Inter", style: "Bold" };
titleText3.characters = "접속 및 기본 화면 안내";
titleText3.fontSize = 48;
titleText3.x = 100; titleText3.y = 100;

// Slide 4: Permissions
const slide4 = figma.createSlide();
row1.appendChild(slide4);
const titleText4 = figma.createText();
slide4.appendChild(titleText4);
titleText4.fontName = { family: "Inter", style: "Bold" };
titleText4.characters = "권한별 차이점";
titleText4.fontSize = 48;
titleText4.x = 100; titleText4.y = 100;

return { success: true, slidesCreated: 4 };
```

- [ ] **Step 3: Verify Creation**

No command needed, verify the output from Step 2 returned success.

### Task 2: Section 2: Discovery & Contract (Slides 5-6)

**Files:**
- N/A (Figma File)

- [ ] **Step 1: Create Section 2 Slides**

Run `use_figma` with `fileKey`.
```javascript
const grid = figma.currentPage.children.find(c => c.type === "SLIDE_GRID");

await figma.loadFontAsync({ family: "Inter", style: "Regular" });
await figma.loadFontAsync({ family: "Inter", style: "Bold" });

// Create Row 2
const row2 = figma.createSlideRow();
grid.appendChild(row2);
row2.name = "Discovery & Contract";

// Slide 5
const slide5 = figma.createSlide();
row2.appendChild(slide5);
const titleText5 = figma.createText();
slide5.appendChild(titleText5);
titleText5.fontName = { family: "Inter", style: "Bold" };
titleText5.characters = "잠재기관 등록 및 관리";
titleText5.fontSize = 48;
titleText5.x = 100; titleText5.y = 100;

// Slide 6
const slide6 = figma.createSlide();
row2.appendChild(slide6);
const titleText6 = figma.createText();
slide6.appendChild(titleText6);
titleText6.fontName = { family: "Inter", style: "Bold" };
titleText6.characters = "계약 전환 프로세스";
titleText6.fontSize = 48;
titleText6.x = 100; titleText6.y = 100;

return { success: true, slidesCreated: 2 };
```

### Task 3: Section 3: Setup & Contacts (Slides 7-8)

**Files:**
- N/A (Figma File)

- [ ] **Step 1: Create Section 3 Slides**

Run `use_figma` with `fileKey`.
```javascript
const grid = figma.currentPage.children.find(c => c.type === "SLIDE_GRID");

await figma.loadFontAsync({ family: "Inter", style: "Regular" });
await figma.loadFontAsync({ family: "Inter", style: "Bold" });

// Create Row 3
const row3 = figma.createSlideRow();
grid.appendChild(row3);
row3.name = "Setup & Contacts";

// Slide 7
const slide7 = figma.createSlide();
row3.appendChild(slide7);
const titleText7 = figma.createText();
slide7.appendChild(titleText7);
titleText7.fontName = { family: "Inter", style: "Bold" };
titleText7.characters = "기관리스트 활용법";
titleText7.fontSize = 48;
titleText7.x = 100; titleText7.y = 100;

// Slide 8
const slide8 = figma.createSlide();
row3.appendChild(slide8);
const titleText8 = figma.createText();
slide8.appendChild(titleText8);
titleText8.fontName = { family: "Inter", style: "Bold" };
titleText8.characters = "교직원 연락처 관리";
titleText8.fontSize = 48;
titleText8.x = 100; titleText8.y = 100;

return { success: true, slidesCreated: 2 };
```

### Task 4: Section 4: Support & Scheduling (Slides 9-12)

**Files:**
- N/A (Figma File)

- [ ] **Step 1: Create Section 4 Slides**

Run `use_figma` with `fileKey`.
```javascript
const grid = figma.currentPage.children.find(c => c.type === "SLIDE_GRID");

await figma.loadFontAsync({ family: "Inter", style: "Regular" });
await figma.loadFontAsync({ family: "Inter", style: "Bold" });

const row4 = figma.createSlideRow();
grid.appendChild(row4);
row4.name = "Support & Scheduling";

const slideTitles = [
  "기관지원보고서 작성",
  "기관지원보고서 활용",
  "개인 및 팀 일정 관리",
  "일정 공유 범위의 이해"
];

let count = 0;
for (const title of slideTitles) {
  const slide = figma.createSlide();
  row4.appendChild(slide);
  const text = figma.createText();
  slide.appendChild(text);
  text.fontName = { family: "Inter", style: "Bold" };
  text.characters = title;
  text.fontSize = 48;
  text.x = 100; text.y = 100;
  count++;
}

return { success: true, slidesCreated: count };
```

### Task 5: Section 5: Logistics & Assets (Slides 13-17)

**Files:**
- N/A (Figma File)

- [ ] **Step 1: Create Section 5 Slides**

Run `use_figma` with `fileKey`.
```javascript
const grid = figma.currentPage.children.find(c => c.type === "SLIDE_GRID");

await figma.loadFontAsync({ family: "Inter", style: "Regular" });
await figma.loadFontAsync({ family: "Inter", style: "Bold" });

const row5 = figma.createSlideRow();
grid.appendChild(row5);
row5.name = "Logistics & Assets";

const slideTitles = [
  "Store 재고 현황 조회 및 수정",
  "Store 판매 내역 확인",
  "Salesforce 파일 연동 및 관리",
  "GS Brochure: 외부 신청 및 내부 처리",
  "GS Brochure: 전용 관리자 대시보드"
];

let count = 0;
for (const title of slideTitles) {
  const slide = figma.createSlide();
  row5.appendChild(slide);
  const text = figma.createText();
  slide.appendChild(text);
  text.fontName = { family: "Inter", style: "Bold" };
  text.characters = title;
  text.fontSize = 48;
  text.x = 100; text.y = 100;
  count++;
}

return { success: true, slidesCreated: count };
```

### Task 6: Section 6: Administration (Slides 18-20)

**Files:**
- N/A (Figma File)

- [ ] **Step 1: Create Section 6 Slides**

Run `use_figma` with `fileKey`.
```javascript
const grid = figma.currentPage.children.find(c => c.type === "SLIDE_GRID");

await figma.loadFontAsync({ family: "Inter", style: "Regular" });
await figma.loadFontAsync({ family: "Inter", style: "Bold" });

const row6 = figma.createSlideRow();
grid.appendChild(row6);
row6.name = "Administration";

const slideTitles = [
  "People: 전체 직원 정보 조회",
  "Setup: 부서 생성 및 공통코드 관리",
  "신규 직원 계정 등록 및 역할 부여"
];

let count = 0;
for (const title of slideTitles) {
  const slide = figma.createSlide();
  row6.appendChild(slide);
  const text = figma.createText();
  slide.appendChild(text);
  text.fontName = { family: "Inter", style: "Bold" };
  text.characters = title;
  text.fontSize = 48;
  text.x = 100; text.y = 100;
  count++;
}

return { success: true, slidesCreated: count };
```

### Task 7: Section 7: Conclusion & FAQ (Slides 21-23)

**Files:**
- N/A (Figma File)

- [ ] **Step 1: Create Section 7 Slides**

Run `use_figma` with `fileKey`.
```javascript
const grid = figma.currentPage.children.find(c => c.type === "SLIDE_GRID");

await figma.loadFontAsync({ family: "Inter", style: "Regular" });
await figma.loadFontAsync({ family: "Inter", style: "Bold" });

const row7 = figma.createSlideRow();
grid.appendChild(row7);
row7.name = "Conclusion & FAQ";

const slideTitles = [
  "자주 묻는 질문 1 (접속 및 권한)",
  "자주 묻는 질문 2 (화면 노출 범위)",
  "관련 정책 문서 안내 및 헬프데스크"
];

let count = 0;
for (const title of slideTitles) {
  const slide = figma.createSlide();
  row7.appendChild(slide);
  const text = figma.createText();
  slide.appendChild(text);
  text.fontName = { family: "Inter", style: "Bold" };
  text.characters = title;
  text.fontSize = 48;
  text.x = 100; text.y = 100;
  count++;
}

return { success: true, slidesCreated: count };
```
