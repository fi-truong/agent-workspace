# AI+ — OpenAI Integration (Agent Workspace)

Tài liệu này hướng dẫn cấu hình và test phần gọi OpenAI API trong Agent Workspace (module AI+).

## 1. Cấu hình key

Mở file `.env` (không commit) và thêm:

```
OPENAI_API_KEY=sk-...
OPENAI_ORGANIZATION=                          # bỏ trống nếu không dùng organization
OPENAI_MODEL=gpt-6-luna
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

- `app/Services/OpenAIClient.php` — wrapper gọi `POST /v1/chat/completions`, có xử lý lỗi và retry lỗi mạng/provider. Luồng sử dụng AI không đặt timeout HTTP hoặc giới hạn token đầu ra riêng; PHP, proxy, OpenAI và hạ tầng vẫn áp dụng giới hạn của họ.
- `app/Services/OpenAIErrorMapper.php` — map lỗi (401/403/404/429/insufficient_quota/timeout) sang message tiếng Việt thân thiện.
- `app/Services/ChatCompletionService.php` — `complete(array $messages, ?string $systemPrompt = null): array`. Filter PII từng content role=user (dùng `RegexPiiFilter`), prepend system prompt nếu có, gọi `OpenAIClient`. Nếu chưa có key → fallback mock.
- `app/Console/Commands/TestOpenAIKey.php` — command test key.
- `app/Services/KnowledgeService.php` — đọc text từ knowledge files (txt/csv/pdf/docx/xlsx), lọc PII và ghép nội dung đã đọc vào system prompt khi chat với Agent; không cắt theo ngưỡng ký tự do AI+ đặt.

### Tính năng mới (Agent Workspace)
- **Chat nhớ lịch sử**: `ChatMessageController` gửi toàn bộ lịch sử conversation đã lưu; giới hạn context/token của OpenAI vẫn áp dụng.
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
- Khi vào production, cấp quyền tối thiểu cho key và xác nhận reverse proxy, process manager, PHP cùng tài nguyên máy chủ có thể phục vụ request dài.
- `ChatMessageController` giữ nguyên — không cần sửa khi bật API.
- Nếu key có nhưng gọi thất bại (vd 429 do chưa có credit), controller trả `{ "error": "<message thân thiện>", "retryable": true }` với HTTP 502 thay vì 500 — UI không vỡ.

## 6. Connected Agents

`AgentConnectionService` quản lý các kết nối do chủ Agent chọn; cấu hình được lưu riêng trong `agent_connections`. Chạy migration khi triển khai tính năng. Chưa có bảng này hoặc tắt `AI_PLUS_AGENT_CONNECTIONS_ENABLED` thì chat tiếp tục theo luồng cũ.

`AgentOrchestrationService` bổ sung tool `consult_connected_agent` chỉ khi Agent có kết nối dùng được. Model chọn Agent từ danh sách được phép và danh mục mã nguồn thuộc chat; backend kiểm tra lại quyền dùng Agent cha, kết nối và quyền của Agent con tại thời điểm thực thi. Người nhận một coordinator `use_only` được gọi các Agent con do chủ coordinator sở hữu thông qua coordinator đó; các Agent con riêng tư không được thêm vào danh sách Agent của người nhận hoặc cho phép mở chat trực tiếp. Agent con thuộc người khác chỉ được gọi nếu người đang chat có quyền dùng riêng. Thu hồi quyền dùng coordinator chặn các lượt gọi tiếp theo. Tool có `source_ids` (null để giữ cơ chế nguồn cũ) và `include_previous_results` để loại kết quả cũ khỏi bước kiểm chứng độc lập. Lịch sử assistant/tool được trả lại theo `tool_call_id` để Agent chính tổng hợp. Agent con chỉ nhận nhiệm vụ và phần nguồn được chọn, cùng prompt/Knowledge riêng; không nhận toàn bộ lịch sử hoặc công cụ tạo file. AI+ không áp quota credits, giới hạn số Agent con, số lần gọi, số ký tự bàn giao, số trang PDF hay thời gian xử lý trong luồng này. Các request vẫn chịu giới hạn context/token/model/API của OpenAI và giới hạn upload/tài nguyên của PHP, web server cùng hạ tầng triển khai.

Lượt tạo file từ bản nháp đã duyệt vẫn qua xác nhận ý định độc lập. Với bản nháp đã lưu và không có tệp mới ở lượt đó, bước này dùng `classify_file_request` với schema strict, xác định đồng thời yêu cầu tạo file, nguồn (`saved_chat_draft`, `other`, `not_requested`) và định dạng. Tool phân loại chỉ nhận yêu cầu hiện tại, không gửi lại bản nháp hoặc tài liệu và không có tác dụng tạo file. Khi xác nhận `saved_chat_draft`, server dùng nguyên bản nháp đã lưu để tạo artifact; không yêu cầu model viết lại nội dung và không xác nhận nguồn thêm lần nữa. Cách này cũng áp dụng khi phản hồi chính từ chối công cụ hoặc phần arguments tạo file bị cắt. Nếu quyết định phân loại thiếu, sai schema, bị cắt hoặc gặp lỗi API thì không tạo file và báo lỗi kỹ thuật. Luồng khác tiếp tục dùng bước xác nhận hiện có.

Khi yêu cầu xuất bản nháp trong chat, phản hồi trước chuỗi yêu cầu xuất file được đưa đầy đủ vào ngữ cảnh. Việc tạo file không chủ động cắt nội dung theo ngưỡng ký tự AI+; model vẫn có thể từ chối hoặc không xử lý hết nếu nội dung vượt context, token hoặc khả năng của provider.

Với quyết định `saved_chat_draft`, cảnh báo tài liệu đính kèm cũ bị cắt không chặn xuất nguyên bản nháp. Yêu cầu cần thêm nội dung tệp gốc vẫn giữ bước chặn khi nguồn không đầy đủ. Việc tạo file không xác nhận nội dung bản nháp đúng về chuyên môn.

Xác nhận xuất bản nháp phân biệt yêu cầu giữ nguyên các phần đã tách với yêu cầu bổ sung tài liệu, dịch hoặc sửa nội dung. Quyết định `other` không được thay nội dung đầu ra bằng bản nháp cũ. Câu hỏi về khả năng hoặc yêu cầu chưa rõ trả `not_requested`. Log `Draft export intent classified` chỉ lưu mã chat, loại nguồn và định dạng, không lưu nội dung chat. Kiểm thử giả lập chứng minh các nhánh xử lý và nội dung DOCX; chưa thay thế xác minh model thật trên server.

`ConversationSourceContextService` chỉ lưu snapshot trích xuất đã qua PII filter vào `conversation_attachments` khi chat gắn với Agent điều phối có ít nhất một chuyên viên người dùng hiện được phép gọi; snapshot gắn với conversation/message/user. Ảnh vẫn ở private disk `chat-attachments` theo cơ chế chat hiện có. Mỗi lần giao việc, backend xác nhận conversation thuộc người đang chat, chuyển đầy đủ nội dung trích xuất của nguồn được chọn và kết quả Agent trước, đồng thời giữ tiêu đề cột của bảng. Không áp đặt ngân sách ký tự, số ảnh hoặc số nguồn ở tầng AI+; model vẫn chịu giới hạn context/token của OpenAI và request có thể thất bại nếu vượt giới hạn đó. Không dùng cụm từ trong prompt để quyết định lưu hoặc giao toàn bộ tài liệu. Agent con không nhận lịch sử chat chung; kết quả hoàn tất, trạng thái trích xuất và hạn chế nguồn được lưu trong `messages.internal_context` để bước sau dùng lại. Nguồn cũ không có snapshot chỉ dùng được dưới dạng excerpt với trạng thái không xác định; ảnh/file mất, định dạng chưa hỗ trợ, trích xuất rỗng hoặc quyền không còn được báo là không khả dụng. Phản hồi cuối phân biệt Agent đã trả kết quả, kết quả thiếu một phần và Agent không trả kết quả. Snapshot xóa cùng conversation; thời hạn retention chung vẫn TBD theo chính sách trường.

Với bảng TSV đủ cấu trúc và xác định được nguồn duy nhất, AI+ kiểm tra các trường cùng tên được sao chép giữa nguồn đầy đủ và kết quả chuyên viên; số lượng nêu dạng nhãn=số hoặc nhãn: số ở trước hoặc sau bảng cũng được so với dòng bảng khi nhận diện được. Nếu thấy khác biệt, cảnh báo được hiển thị ngay cả khi Agent cha bỏ sót và được chuyển kèm các kết quả trước ở bước tiếp theo. Đây là kiểm tra hạn chế: các thay đổi có thể là chủ ý, và không có cảnh báo không chứng minh kết luận, phép tính hoặc phần văn xuôi đã đúng.

Khi tổng hợp ở lượt sau, kết quả Agent trước được chuyển đầy đủ trong context. Nếu nội dung vượt context/token mà OpenAI chấp nhận, provider có thể từ chối request; AI+ không tự cắt rồi báo đã chuyển đầy đủ.

Nếu yêu cầu mới nêu rõ chuyên viên có kết quả gần nhất bị cắt, backend dừng trước bước tổng hợp để không tự dựng phần thiếu. Yêu cầu gọi lại hoặc loại trừ chuyên viên vẫn tiếp tục. Với lệnh xuất bản nháp, backend kiểm tra yêu cầu đã sinh bản nháp và trạng thái kết quả tại thời điểm đó; một bản nháp cũ không tự trở nên đầy đủ chỉ vì sau này có kết quả chuyên viên mới. Nhận diện này dựa trên tên hoặc mã Agent được nêu trong yêu cầu, không phát hiện mọi tham chiếu gián tiếp.

Migration bổ sung `conversation_attachments` là `2026_11_04_100331_create_conversation_attachments_table.php`. Xóa conversation sẽ cascade các metadata/trích xuất; ảnh private nằm trong thư mục theo conversation và được xóa khi xóa chat. Việc trích xuất và handoff không còn ngưỡng số trang, số file, số ảnh hoặc số ký tự do AI+ đặt ra. Nếu PHP, storage, web server hoặc provider không xử lý được dữ liệu, hệ thống phải báo lỗi/nguồn chưa đủ thay vì tuyên bố đã đọc hoàn chỉnh.

`config/agent_connections.php` giữ công tắc bật/tắt tính năng. AI+ không đặt quota credits, giới hạn lượt gọi Agent, token đầu ra, thời gian điều phối hoặc kích thước handoff cho người dùng. Request vẫn chịu giới hạn model/context/token/API của OpenAI và giới hạn hạ tầng. Nếu provider đánh dấu phản hồi bị cắt (`finish_reason=length`), backend tiếp tục cùng nhiệm vụ cho tới khi provider hoàn tất hoặc continuation gặp lỗi. Kết quả rỗng/lỗi được báo là chưa hoàn tất; không tự nhận là đã xong. Tổng token trên message gồm mọi lần gọi, còn usage log chỉ tính mỗi request một lần.

Tắt khẩn cấp trên server: đặt `AI_PLUS_AGENT_CONNECTIONS_ENABLED=false` trong `.env`, chạy `php artisan config:clear` rồi reload PHP-FPM. Giữ bảng kết nối khi quay về release cũ để không mất cấu hình; không chạy migration rollback để tắt tính năng.

Kiểm thử không gọi API thật: `php artisan test --compact tests/Feature/AgentConnectionTest.php tests/Feature/AgentOrchestrationTest.php`.

Kiểm thử giao diện chọn Agent trong Chromium đã cài: `node --test tests/Unit/agent-modal-browser.test.mjs`.

## 7. Luna / Sol và AI+ Credits

User chọn `gpt-6-luna` hoặc `gpt-6-sol` cho Agent điều phối ngay trong ô chat, cạnh nút gửi mũi tên ở phía dưới bên phải; đính kèm ở phía dưới bên trái. Với Agent có kết nối, user chọn riêng model dùng chung cho mọi Agent chuyên viên trong dòng `Specialists use`. Lựa chọn của điều phối lưu ở `conversations.chat_model`; lựa chọn chuyên viên lưu ở `conversations.specialist_model` (mặc định Luna). Hai lựa chọn không đổi model toàn cục hoặc model tạo ảnh/embeddings. Agent điều phối và thao tác tạo/xác nhận file dùng model điều phối; Agent chuyên viên dùng model chuyên viên. Credits được tính theo model thực tế của từng lời gọi. Chat Completions có tool calling dùng `reasoning_effort=none`, theo [tài liệu GPT-6 Sol](https://developers.openai.com/api/docs/models/gpt-6-sol). Chưa hỗ trợ `gpt-6.1-sol` ở luồng này.

Cho phép `gpt-6-sol` trong model permissions của đúng Project đang cấp API key, đồng thời giữ quyền Chat Completions. Không cần key mới nếu key hiện tại có quyền. Không gửi key vào chat/log/Git. AI+ Credits chỉ là số liệu usage theo model, không phải số dư hoặc hạn mức. Không tự chuyển Luna nếu Sol bị 403; user nhận lỗi provider và có thể chủ động chọn lại.

Migration `2026_10_27_131210_add_chat_model_and_quota_units.php` thêm model và đơn vị usage cho log; dữ liệu chat và usage lịch sử được giữ nguyên. `usage_logs.quota_units` lưu snapshot nguyên: tổng token Luna nhân 1, Sol nhân 20; 1.000 units = 1 credit. Null ở log cũ được tính bằng tổng token cũ. Ảnh giữ hệ số ghi nhận cũ; credits không được quy thành tiền. Các giá trị `users.token_quota_limit` cũ và biến môi trường quota không còn được đọc để chặn request; không cần sửa/xóa chúng khỏi DB. API giữ `token_quota.used/limit/remaining` cùng các trường credits để tương thích, nhưng limit/remaining là null và usage không chặn quyền dùng AI+. Token thật vẫn lưu riêng.

Tỷ lệ 20× là hệ số báo cáo usage theo model, không phải quota, số tiền hay mức chi tiêu tối đa. Nó dựa trên giá Standard Luna/Sol tại ngày 27/10/2026 và không tự thay đổi khi bảng giá OpenAI đổi. Giá input/output, cache, tier, model ảnh và các phí khác vẫn khác nhau.

Kiểm tra quyền inference thật bằng `php artisan openai:test-key --live --model=gpt-6-sol`. Khác với lệnh mặc định chỉ GET `/models`, `--live` gửi ba request nhỏ có tính phí (chat, streaming, function calling với `reasoning_effort=none`), chỉ dùng marker giả và không đọc/ghi dữ liệu user. Mỗi request giới hạn 256 output token và 40 giây, không tự retry. Kết quả kiểm tra metadata model/usage cùng marker, không dựa vào lời tự nhận của AI. Credits tương đương chỉ là số liệu chẩn đoán, không bị trừ vào quota user. Không in key hoặc nội dung lỗi provider ra terminal. Test trên Mac ngày 27/10/2026 đã qua cả ba bước với `gpt-6-sol`, tổng 282 token; chưa phải xác nhận end-to-end trên test server.

Rollback tính năng chọn Sol: đặt `AI_PLUS_SOL_ENABLED=false`, clear config rồi restart service đang phục vụ AI+. Picker chỉ còn Luna, không xóa lịch sử và vẫn tính snapshot Sol đã ghi. Ưu tiên giữ code ledger khi tắt Sol; rollback toàn bộ code về bản cũ sẽ không hiểu khoản credits Sol, cần kế hoạch riêng để tránh bỏ sót quota. Không rollback migration để tắt tính năng.

© 2026 CIEC — Lawrence S. Ting School | AI+ Program

## 8. Hiệu năng đọc PDF, embeddings và đo thời gian

- PDF chat và Agent Knowledge dùng cùng bộ đọc text qua PHP CLI trong tiến trình riêng, không đặt timeout hoặc memory limit riêng của AI+. Dữ liệu PDF đi qua stdin, không nằm trong command arguments. Nếu parser hoặc hệ điều hành dừng do lỗi/tài nguyên, hệ thống không dùng phần text dở dang. PDF scan được đọc/render toàn bộ trang cần thiết theo luồng OCR/vision hiện có. Server cần PHP CLI và khả năng chạy subprocess.
- Text PDF được dùng lại theo SHA-256 trong request hiện tại, bao gồm các bước quét PII và dựng context. Quét PII vẫn chạy; không lưu cache này xuống DB/file hoặc dùng chung giữa các request/người dùng.
- Kho trường và kho Agent chỉ tạo embedding câu hỏi khi kho tương ứng có vector. Các lời gọi embedding có cùng endpoint, model, key và input trong một request dùng chung kết quả, kể cả kết quả thất bại; lượt sau thử lại bình thường. Batch lập chỉ mục vẫn được thực hiện như trước. HTTP 403 được log bằng status, lỗi kết nối bằng exception class, không ghi nội dung phản hồi API hoặc câu hỏi. Thay đổi này không cấp quyền API và không thay đổi moderation.
- Hai route gửi chat JSON/SSE ghi một event INFO `AI+ chat performance` sau khi hoàn tất. SSE được đo đến cuối callback; lỗi SSE được đánh dấu failed dù HTTP đã là 200. Log gồm mã request ngẫu nhiên, tên route, HTTP status, trạng thái thất bại, `total_ms`, `stages_ms` và `peak_memory_mb`; không chứa user ID, prompt, tên/nội dung file hoặc secret.
- Các stage gồm validation/PII, đọc tài liệu/PDF, moderation, chuẩn bị lượt, truy xuất Knowledge, embedding, phản hồi AI, tiếp nối phản hồi và xử lý đầu ra. Một số stage nằm trong stage khác; dùng `total_ms` làm tổng, không cộng các stage. Tổng này bắt đầu ở middleware của route chat, không đo thời gian xếp hàng trước ứng dụng hay mạng trình duyệt.
- Event thời gian dùng mức INFO của logger hiện có. Nếu server đang đặt `LOG_LEVEL=warning`, event sẽ bị lọc; cần IT cho phép ghi INFO khi đo hiệu năng. Không đổi `.env`, cấu hình logging hoặc chính sách lưu log tự động.
