# AI+ — OpenAI Integration (Agent Workspace)

Tài liệu này hướng dẫn cấu hình và test phần gọi OpenAI API trong Agent Workspace (module AI+).

## 1. Cấu hình key

Mở file `.env` (không commit) và thêm:

```
OPENAI_API_KEY=sk-...
OPENAI_ORGANIZATION=                          # bỏ trống nếu không dùng organization
OPENAI_MODEL=gpt-6-luna
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

## 6. Connected Agents

`AgentConnectionService` quản lý các kết nối do chủ Agent chọn; cấu hình được lưu riêng trong `agent_connections`. Chạy migration khi triển khai tính năng. Chưa có bảng này hoặc tắt `AI_PLUS_AGENT_CONNECTIONS_ENABLED` thì chat tiếp tục theo luồng cũ.

`AgentOrchestrationService` bổ sung tool `consult_connected_agent` chỉ khi Agent có kết nối dùng được. Model chọn Agent từ danh sách được phép và danh mục mã nguồn thuộc chat; backend kiểm tra lại cấu hình, quyền truy cập Agent và mã nguồn tại thời điểm thực thi. Tool có `source_ids` (null để giữ cơ chế nguồn cũ) và `include_previous_results` để loại kết quả cũ khỏi bước kiểm chứng độc lập. Lịch sử assistant/tool được trả lại theo `tool_call_id` để Agent chính tổng hợp. Agent con chỉ nhận nhiệm vụ và phần nguồn được chọn, cùng prompt/Knowledge riêng; không nhận toàn bộ lịch sử hoặc công cụ tạo file.

Lượt tạo file từ bản nháp đã duyệt dùng `create_ai_artifact` của Agent chính. Bước xác nhận ý định chỉ nhận yêu cầu người dùng hiện tại và phần cuối câu trả lời ngay trước đó, không gửi lại toàn bộ lịch sử và tài liệu. Nếu model trả lời sai rằng không có công cụ trong khi người dùng yêu cầu tạo file từ bản nháp, backend xác nhận ý định độc lập rồi thử một lần với công cụ tạo file được yêu cầu rõ; nội dung bản nháp gần nhất được lấy từ chat. Không tạo file khi bước xác nhận lỗi, khi bản nháp thiếu/quá dài, hoặc khi tool không trả nội dung hợp lệ; báo lỗi kỹ thuật thay vì đòi người dùng diễn đạt lại yêu cầu đã rõ.

Khi yêu cầu xuất bản nháp trong chat, phản hồi trước chuỗi yêu cầu xuất file được đưa đầy đủ vào ngữ cảnh nếu không quá 40.000 ký tự; bước khôi phục dùng cùng phản hồi đó, thay vì chọn câu báo lỗi gần nhất theo độ dài. Bản nháp quá giới hạn không được xuất thành file có thể thiếu nội dung.

Nếu cảnh báo nguồn cũ bị cắt chặn bước xuất, AI+ xác nhận riêng người dùng chỉ muốn xuất nguyên bản nháp trong chat. Khi được xác nhận và không có tệp mới ở lượt đó, file được tạo từ chính bản nháp đã lưu; yêu cầu cần thêm nội dung tệp gốc vẫn giữ bước chặn. Việc tạo file không xác nhận nội dung bản nháp đúng về chuyên môn.

`ConversationSourceContextService` chỉ lưu snapshot trích xuất đã qua PII filter vào `conversation_attachments` khi chat gắn với Agent điều phối có ít nhất một chuyên viên người dùng hiện được phép gọi; snapshot gắn với conversation/message/user. Ảnh vẫn ở private disk `chat-attachments` theo cơ chế chat hiện có. Mỗi lần giao việc, backend xác nhận conversation thuộc người đang chat, chọn đoạn tài liệu liên quan theo task và các lượt user gần đây, giữ tiêu đề cột với bảng, và chuyển tối đa số ảnh được cấu hình. Khi tổng nguồn vượt ngân sách, file riêng lẻ vừa ngân sách được gửi trọn trước, rồi đến kết quả Agent hoàn chỉnh gần nhất; phần còn lại được chia đoạn, ưu tiên nội dung liên quan và đánh dấu phần không gửi. Hạn chế nguồn của lượt Agent cũ nằm trong ngữ cảnh của kết quả cũ, không trở thành cảnh báo bàn giao file ở lượt mới. Không dùng cụm từ trong prompt để quyết định lưu hoặc giao toàn bộ tài liệu. Agent con không nhận lịch sử chat chung; kết quả hoàn tất, trạng thái cắt và hạn chế nguồn được lưu trong `messages.internal_context` để bước sau dùng lại. Nguồn cũ không có snapshot chỉ dùng được dưới dạng excerpt với trạng thái không xác định; ảnh/file mất, định dạng chưa hỗ trợ, trích xuất rỗng hoặc quyền không còn được báo là không khả dụng. Phản hồi cuối phân biệt Agent đã trả kết quả, kết quả thiếu một phần và Agent không trả kết quả. Snapshot xóa cùng conversation; thời hạn retention chung vẫn TBD theo chính sách trường.

Với bảng TSV đủ cấu trúc và xác định được nguồn duy nhất, AI+ kiểm tra các trường cùng tên được sao chép giữa nguồn đầy đủ và kết quả chuyên viên; số lượng nêu dạng nhãn=số hoặc nhãn: số ở trước hoặc sau bảng cũng được so với dòng bảng khi nhận diện được. Nếu thấy khác biệt, cảnh báo được hiển thị ngay cả khi Agent cha bỏ sót và được chuyển kèm các kết quả trước ở bước tiếp theo. Đây là kiểm tra hạn chế: các thay đổi có thể là chủ ý, và không có cảnh báo không chứng minh kết luận, phép tính hoặc phần văn xuôi đã đúng.

Khi tổng hợp ở lượt sau, hai tin nhắn gần nhất có kết quả chuyên viên được cấp chung tối đa 40.000 ký tự, ưu tiên tin mới hơn và dành ít nhất 4.000 ký tự cho tin còn lại. Nội dung vượt giới hạn vẫn bị cắt và có nhãn trong ngữ cảnh gửi cho Agent cha; không coi nhãn này là bằng chứng mọi dữ liệu khác đã được kiểm chứng.

Migration bổ sung `conversation_attachments` là `2026_11_04_100331_create_conversation_attachments_table.php`. Xóa conversation sẽ cascade các metadata/trích xuất; ảnh private nằm trong thư mục theo conversation và được xóa khi xóa chat. Giới hạn mặc định cho handoff text là 24.000 ký tự, mỗi chunk 2.000 ký tự, tối đa 4 ảnh và tối đa 1.000.000 ký tự được giữ cho mỗi tài liệu; có thể chỉnh trong `config/agent_connections.php`. Quá các giới hạn này, hệ thống đánh dấu partial thay vì khẳng định đã gửi đủ.

Giới hạn và công tắc nằm trong `config/agent_connections.php`: tối đa 10 Agent, mỗi Agent nhận một nhiệm vụ/lượt, tối đa 10 lần tham khảo, 300 giây (5 phút) cho phần phối hợp và ngưỡng 32.000 token. Mỗi request dùng giá trị nhỏ hơn giữa `openai.timeout` và thời gian phối hợp còn lại; chat thường giữ cách retry cũ. Agent chuyên viên dùng `reasoning_effort=none` để dành ngân sách đầu ra cho phần trả lời hiển thị trong giới hạn 8.192 token mỗi request mặc định (vẫn chịu giới hạn `OPENAI_MAX_TOKENS` nếu thấp hơn). Nếu câu trả lời bị cắt do `finish_reason=length`, backend tiếp tục cùng nhiệm vụ tối đa bốn phần, nhưng chỉ khi còn một timeout model và 4.096 token để Agent điều phối tổng hợp; từng phần được ghi usage riêng. Chỉ phần cuối không bị cắt mới đánh dấu kết quả hoàn tất. Phản hồi rỗng hoặc phần nối tiếp lỗi được báo là thiếu/chưa hoàn tất; không tự gọi lại một nhiệm vụ mới. Khi hết giới hạn phối hợp sau khi đã nhận kết quả, AI+ hiện chính kết quả chuyên viên đã nhận cùng trạng thái hoàn tất/chưa hoàn tất, thay vì chỉ báo lỗi chung; chưa tự tạo file cuối. Tổng token trên message gồm mọi lần gọi, còn usage log chỉ tính mỗi request một lần.

Controller chỉ tăng mốc PHP cho chat có Agent kết nối: thời gian phối hợp cộng một timeout model để hoàn tất đầu ra, mặc định `300 + 120 = 420` giây. Chat thường vẫn đặt 120 giây. Trình duyệt không đặt timeout hủy request chat; PHP-FPM và Nginx bên ngoài cần cấu hình tương ứng (đề xuất 450 giây với mặc định trên; xem Deployment Checklist). Nếu có ngrok/CDN/proxy khác ở phía trước, phải kiểm tra giới hạn của lớp đó qua domain thật. Đây vẫn là xử lý trong request, chưa chuyển sang job chạy nền.

Tắt khẩn cấp trên server: đặt `AI_PLUS_AGENT_CONNECTIONS_ENABLED=false` trong `.env`, chạy `php artisan config:clear` rồi reload PHP-FPM. Giữ bảng kết nối khi quay về release cũ để không mất cấu hình; không chạy migration rollback để tắt tính năng.

Kiểm thử không gọi API thật: `php artisan test --compact tests/Feature/AgentConnectionTest.php tests/Feature/AgentOrchestrationTest.php`.

Kiểm thử giao diện chọn Agent trong Chromium đã cài: `node --test tests/Unit/agent-modal-browser.test.mjs`.

## 7. Luna / Sol và AI+ Credits

User chọn `gpt-6-luna` hoặc `gpt-6-sol` cho Agent điều phối ngay trong ô chat, cạnh nút gửi mũi tên ở phía dưới bên phải; đính kèm ở phía dưới bên trái. Với Agent có kết nối, user chọn riêng model dùng chung cho mọi Agent chuyên viên trong dòng `Specialists use`. Lựa chọn của điều phối lưu ở `conversations.chat_model`; lựa chọn chuyên viên lưu ở `conversations.specialist_model` (mặc định Luna). Hai lựa chọn không đổi model toàn cục hoặc model tạo ảnh/embeddings. Agent điều phối và thao tác tạo/xác nhận file dùng model điều phối; Agent chuyên viên dùng model chuyên viên. Credits được tính theo model thực tế của từng lời gọi. Chat Completions có tool calling dùng `reasoning_effort=none`, theo [tài liệu GPT-6 Sol](https://developers.openai.com/api/docs/models/gpt-6-sol). Chưa hỗ trợ `gpt-6.1-sol` ở luồng này.

Cho phép `gpt-6-sol` trong model permissions của đúng Project đang cấp API key, đồng thời giữ quyền Chat Completions. Không cần key mới nếu key hiện tại có quyền. Không gửi key vào chat/log/Git. Credits do AI+ quản lý, không cấu hình trên OpenAI Platform. Không tự chuyển Luna nếu Sol bị 403; user nhận lỗi provider và có thể chủ động chọn lại.

Migration `2026_10_27_131210_add_chat_model_and_quota_units.php` chỉ thêm hai cột nullable, không sửa lịch sử, quota override hoặc nội dung chat. `usage_logs.quota_units` lưu snapshot nguyên: tổng token Luna nhân 1, Sol nhân 20; 1.000 units = 1 credit. Null ở log cũ được tính bằng tổng token cũ. Các nguồn khác, bao gồm ảnh, giữ tỷ lệ quota cũ (1.000 token = 1 credit), không quy thành tiền. Mức 20M/10M token và override cũ hiển thị thành 20K/10K credits; giữ nguyên tên/đơn vị env token để tránh giảm quota 1.000 lần. Admin nhập credits với tối đa ba chữ số thập phân. JSON `token_quota.used/limit/remaining` vẫn là integer units để tương thích; thêm các trường `credits_used/credits_limit/credits_remaining`. Token thật vẫn lưu riêng.

Tỷ lệ 20× là chính sách quy đổi quota được chấp thuận dựa trên giá Standard Luna/Sol tại ngày 27/10/2026, không tự thay đổi khi bảng giá OpenAI đổi. Không phải hard spending cap: một request đang chạy có thể vượt quota; chưa có reservation ngân sách cho các request đồng thời. Giá input/output, cache, tier, model ảnh và các phí khác vẫn khác nhau.

Kiểm tra quyền inference thật bằng `php artisan openai:test-key --live --model=gpt-6-sol`. Khác với lệnh mặc định chỉ GET `/models`, `--live` gửi ba request nhỏ có tính phí (chat, streaming, function calling với `reasoning_effort=none`), chỉ dùng marker giả và không đọc/ghi dữ liệu user. Mỗi request giới hạn 256 output token và 40 giây, không tự retry. Kết quả kiểm tra metadata model/usage cùng marker, không dựa vào lời tự nhận của AI. Credits tương đương chỉ là số liệu chẩn đoán, không bị trừ vào quota user. Không in key hoặc nội dung lỗi provider ra terminal. Test trên Mac ngày 27/10/2026 đã qua cả ba bước với `gpt-6-sol`, tổng 282 token; chưa phải xác nhận end-to-end trên test server.

Rollback tính năng chọn Sol: đặt `AI_PLUS_SOL_ENABLED=false`, clear config rồi restart service đang phục vụ AI+. Picker chỉ còn Luna, không xóa lịch sử và vẫn tính snapshot Sol đã ghi. Ưu tiên giữ code ledger khi tắt Sol; rollback toàn bộ code về bản cũ sẽ không hiểu khoản credits Sol, cần kế hoạch riêng để tránh bỏ sót quota. Không rollback migration để tắt tính năng.

© 2026 CIEC — Lawrence S. Ting School | AI+ Program
