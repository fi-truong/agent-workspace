# CLAUDE.md — Agent Workspace (CIEC AI+ / LSTS)

> File này để Claude Code đọc tự động khi mở project. Giữ ngắn gọn, cập nhật khi có quyết định mới.

## Bối cảnh dự án

**Agent Workspace** là module cốt lõi của **AI+ (Internal AI Environment)** — sáng kiến do
**CIEC (Center of Innovation, Entrepreneurship and Creativity)** dẫn dắt tại
**Lawrence S. Ting School (LSTS)**, trường K-12 song ngữ Việt–Anh tại Việt Nam.

- **Người phụ trách:** Trương Minh Fi (Fi) — CIEC Coordinator 05, EdTech Focus. Build **solo**,
  dùng **Claude Code**.
- **Người phê duyệt cuối:** HOS Mr. Chen Wei-Hung — đã chỉ đạo dùng **Codex/OpenAI** (không dùng Claude)
  cho AI Training Program, và chốt **OpenAI API pay-as-you-go** (không dùng ChatGPT Business seats)
  làm hướng kỹ thuật cho Agent Workspace.
- Đây là **project MỚI HOÀN TOÀN**, KHÔNG dùng chung codebase với website trường (PHP 7.3 / Laravel 8.5,
  cả hai đều EOL, không có Git). Không copy code, không tham chiếu dependency từ project cũ.

## Kiến trúc đã chốt (không đổi trừ khi Fi yêu cầu)

Đã so sánh 3 phương án — **Option 1 (API-Based Integration, gọi trực tiếp OpenAI API)** được chọn
vì chi phí thấp, độ phức tạp vừa phải, phù hợp quy mô trường học. KHÔNG build model riêng,
KHÔNG dùng Enterprise AI Platform (Azure AI Foundry/Bedrock/Vertex) ở giai đoạn này.

## Tech Stack

| Layer | Công nghệ | Ghi chú |
|---|---|---|
| Frontend | React (nếu cần dashboard giàu UI — My Usage, charts) hoặc Blade + Alpine.js (nhẹ hơn) | Chọn theo từng phần, không bắt buộc 1 framework cho toàn bộ |
| Backend | **PHP — Laravel bản mới nhất được hỗ trợ chính thức** | Máy dev hiện chạy **PHP 8.5.9** → phải dùng **Laravel 13** (hỗ trợ PHP 8.3–8.5); Laravel 12 chỉ hỗ trợ đến PHP 8.4, sẽ lỗi composer dependency |
| Guardrail (PII filtering) | Regex pattern (PHP) + OpenAI Moderation API | Bắt buộc có **automated test** — không dựa vào test thủ công vì đụng dữ liệu HS/PH |
| Auth/SSO | Laravel Socialite + Microsoft/Azure provider (Entra ID, OAuth2/OIDC) | Cấu hình App Registration thật cần quyền admin Azure — làm sau khi có Global Admin M365 |
| Database | **MySQL** | Nhất quán với CRM4, dễ tích hợp sau này |
| Version control | **Git — bắt buộc từ commit đầu tiên** | Không lặp lại lỗi "quản lý qua Google Drive" của website cũ |
| CI/CD | GitHub Actions | Từ Sprint 1, không để dồn về sau |
| Hosting | Chia sẻ server website hoặc server riêng | Cần Huy/Ngọc đánh giá tải server hiện tại trước khi quyết |

**Nguyên tắc bắt buộc:**
1. Git từ commit #1, không "để sau".
2. CI/CD dựng từ Sprint 1.
3. Guardrail phải có automated test tối thiểu.
4. KHÔNG chia sẻ codebase với website cũ (PHP 7.3/Laravel 8.x, EOL).
5. Nếu sau này website được viết lại và cũng dùng Laravel → có thể gộp chung MySQL DB
   và Auth/SSO để giảm tải bảo trì cho team IT 2 người (Huy/Ngọc) — nhưng đó là quyết định tương lai,
   chưa áp dụng bây giờ.

## Cấu trúc AI+ (bối cảnh rộng hơn Agent Workspace)

AI+ có 7 mục, nằm trong staff portal (lsts.edu.vn): **Agent Workspace** (module này),
Sharing & Showcase, Prompt Library, Agent Templates, AI Policy & Guidelines, My Usage, Support.
Truy cập theo vai trò (role-based): Staff/Teacher xem được rộng nhất.

## Mô hình chi phí (tham khảo, không phải logic cần code cứng)

- Pricing basis: **GPT-5.6 Luna** — $0.20/1M input tokens, $1.20/1M output tokens (xác nhận 30/7/2026,
  cần kiểm tra lại giá khi triển khai thật vì giá API có thể đổi).
- Giả định input/output 60/40 — đây là **giả định làm việc, chưa có nguồn xác thực chính thức**,
  cần hiệu chỉnh lại sau khi có dữ liệu Pilot thật.
- 3 giai đoạn dân số dùng: CIEC nội bộ (~4 người) → Training/Pilot (~25 người) → Official (toàn trường,
  quy mô ~2,200 người nhưng active/day thấp hơn nhiều).

## Việc CHƯA làm / còn chờ

- Cấu hình App Registration Entra ID thật — chờ Fi có quyền M365 Global Admin.
- Governance model cho AI+ (ai quản trị hệ thống) — 4 phương án A/B/C/D đã đề xuất, **HOS chưa chốt**.
  Không tự giả định phương án nào khi code phần quản trị/phân quyền.
- Server hosting (chung hay riêng với website) — chờ Huy/Ngọc đánh giá tải.

## Quy ước làm việc với Claude Code trong project này

- Đây là session/CLAUDE.md **riêng biệt** với workspace `ciec-workspace` (nơi Fi làm các tài liệu
  CIEC khác). Không trộn ngữ cảnh.
- Khi không chắc một quyết định kỹ trúc/kỹ thuật đã được chốt hay chưa, hỏi lại Fi thay vì tự suy đoán —
  đặc biệt với phần đụng dữ liệu học sinh/phụ huynh (PII) và phần quản trị quyền (governance).
- Ưu tiên đúng tech stack đã chốt ở trên; nếu đề xuất khác đi (vd đổi ORM, đổi queue system),
  nêu rõ lý do và hỏi trước khi áp dụng.
- File naming pattern cho tài liệu liên quan (không phải code): `CIEC_[YY]_[MMDD]_[Ten_Mo_Ta].docx`.

## Lịch sử phiên bản
| Phiên bản | Ngày | Thay đổi |
|---|---|---|
| v1.0 | 2026-08-18 | Khởi tạo, dựa trên SKILL.md ciec-ai-plus-lsts |
