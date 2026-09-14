# AI+ — OpenAI Integration (Agent Workspace)

Tài liệu này hướng dẫn cấu hình và test phần gọi OpenAI API trong Agent Workspace (module AI+).

## 1. Cấu hình key

Mở file `.env` (không commit) và thêm:

```
OPENAI_API_KEY=sk-...
OPENAI_ORGANIZATION=                          # bỏ trống nếu không dùng organization
OPENAI_MODEL=gpt-5.6-luna
OPENAI_MAX_TOKENS=2048
OPENAI_TEMPERATURE=0.7
OPENAI_TIMEOUT=30
OPENAI_RETRY_TIMES=2
OPENAI_RETRY_DELAY_MS=300
```

Quan trọng:

- `.env` đã nằm trong `.gitignore` — **không** commit file này.
- `.env.example` chỉ giữ biến rỗng, an toàn để commit.
- Nếu đã có `OPENAI_API_KEY` cũ dùng thử bên ngoài, **đừng** đưa giá trị thật vào code hoặc log.

Lấy key tại <https://platform.openai.com/api-keys>, quyền tối thiểu chỉ cần `model.read`/gọi completion.

## 2. Kiểm tra key không tốn token

```bash
php artisan openai:test-key
```

Lệnh này gọi `GET /v1/models` (không tính phí) để xác nhận key hợp lệ và trả về số model truy cập được.

Nếu chưa đặt key, bạn sẽ thấy thông báo cấu hình thiếu — đúng như mong đợi.

## 3. Kiến trúc các lớp

- `app/Services/OpenAIClient.php` — wrapper gọi `POST /v1/chat/completions`, có timeout/retry/error handling.
- `app/Services/OpenAIErrorMapper.php` — map lỗi (401/403/404/429/insufficient_quota/timeout) sang message tiếng Việt thân thiện.
- `app/Services/ChatCompletionService.php` — `complete(array $messages, ?string $systemPrompt = null): array`. Filter PII từng content role=user (dùng `RegexPiiFilter`), prepend system prompt nếu có, gọi `OpenAIClient`. Nếu chưa có key → fallback mock.
- `app/Console/Commands/TestOpenAIKey.php` — command test key.
- `app/Services/KnowledgeService.php` — đọc text từ knowledge files (txt/csv/pdf/docx/xlsx), filter PII, cap ký tự, ghép vào system prompt khi chat với agent.

### Tính năng mới (Agent Workspace)
- **Chat nhớ lịch sử**: `ChatMessageController` gom tối đa **20 tin gần nhất** từ conversation (đã lưu trong DB) gửi kèm mỗi lượt.
- **Agent override**: conversation có thể được gắn `agent_id`; khi chat, system prompt của agent (kèm knowledge text nếu có) thay default.
- **Knowledge upload thật**: file lưu tại `storage/app/knowledge/{user_id}/{agent_id}/`, path JSON trong `agents.knowledge`. Đọc nội dung (pdf/docx/xlsx qua phpoffice/smalot) và đưa vào context, có PII filter.
- **Save as Agent**: nút 🤖 ở topbar chat mở modal tạo agent dùng chung (partial `_agent-form-modal`).

## 4. Chạy test (không cần key, không tốn token)

```bash
php artisan test --filter=OpenAI
```

Danh sách test:

- `tests/Unit/OpenAIClientTest.php` — kiểm tra payload/URI/header/retry trên 5xx, throw khi thiếu key.
- `tests/Unit/ChatCompletionServiceTest.php` — gọi thật qua `Http::fake()` khi có key, lọc PII trước khi gửi, fallback mock khi chưa có key.
- Guardrail middleware/unit tests có sẵn (`--filter=guardrail`) tiếp tục chạy.

Đảm bảo lint + typecheck vẫn pass:

```bash
composer lint
composer types:check
```

## 5. Lưu ý khi bật thật

- Kiểm tra lại `OPENAI_MODEL` (giá GPT-5.6 Luna là tham khảo 30/07/2026 — có thể đổi).
- Khi vào production, quyền tối thiểu cho key, sign request chính xác, và đặt timeout hợp lý.
- `ChatMessageController` giữ nguyên — không cần sửa khi bật API.
- Nếu key có nhưng gọi thất bại (vd 429 do chưa có credit), controller trả `{ "error": "<message thân thiện>", "retryable": true }` với HTTP 502 thay vì 500 — UI không vỡ.

© 2026 CIEC — Lawrence S. Ting School | AI+ Program