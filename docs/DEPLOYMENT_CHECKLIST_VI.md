# AI+ LSTS — Server Deployment Checklist

Checklist này dùng khi chuyển AI+ từ máy local lên server. Thực hiện theo thứ tự và đánh dấu từng mục sau khi xác nhận.

## 1. Hạ tầng và tên miền

- [ ] Chuẩn bị Linux server, Nginx hoặc Apache, PHP đúng phiên bản theo `composer.json`, Composer, Node.js LTS, MySQL/MariaDB và Git.
- [ ] Trỏ DNS domain/subdomain đến server.
- [ ] Cài HTTPS/TLS certificate và chuyển toàn bộ HTTP sang HTTPS.
- [ ] Đặt `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domain>` và `LOG_LEVEL=warning` trong `.env`.
- [ ] Tạo `APP_KEY` riêng trên server bằng `php artisan key:generate` nếu chưa có.
- [ ] Không đưa `.env`, API keys, database password hoặc certificate vào GitHub.

## 2. Database, files và quyền truy cập

- [ ] Tạo database production và user database có quyền tối thiểu cần thiết.
- [ ] Điền `DB_*`, `SESSION_DRIVER=database`, `CACHE_STORE=database` và `QUEUE_CONNECTION=database` trong `.env`.
- [ ] Chạy `php artisan migrate --force` và kiểm tra các bảng `sessions`, `jobs`, `cache`, `ai_safety_events`, cùng dữ liệu AI+ hiện có.
- [ ] Migration `2026_11_26_111200_expand_ai_content_columns.php` nới nội dung chat, Instructions/Knowledge Agent và subject email draft lên `LONGTEXT`; kiểm tra với dữ liệu dài trước khi mở rộng sử dụng. Rollback không thu nhỏ các cột để tránh cắt dữ liệu mới.
- [ ] Nếu cần chuyển dữ liệu local, export/import database có kiểm soát; không ghi đè dữ liệu production ngoài kế hoạch backup/rollback.
- [ ] Chạy `php artisan storage:link`.
- [ ] Cho web server quyền ghi giới hạn vào `storage/` và `bootstrap/cache/`; kiểm tra upload Knowledge, chat attachments và AI artifacts.
- [ ] Thiết lập backup database và thư mục `storage/app` định kỳ, đồng thời kiểm tra khả năng restore.

## 3. Build và tối ưu Laravel

- [ ] `composer install --no-dev --optimize-autoloader`.
- [ ] `npm ci` rồi `npm run build`.
- [ ] `php artisan optimize` sau khi `.env` đã hoàn chỉnh.
- [ ] Khi thay đổi `.env`, luôn chạy `php artisan config:clear` trước khi cache lại config.
- [ ] Đặt `upload_max_filesize`, `post_max_size` và Nginx `client_max_body_size` theo dung lượng thật mà server có thể nhận và lưu. AI+ không giới hạn số file, dung lượng mỗi file, tổng dung lượng hoặc số trang PDF; PHP/Nginx, storage và tài nguyên máy chủ vẫn giới hạn request thực tế. Test server trước đây được kiểm tra với PHP 15 MB/file, 55 MB/request và Nginx 60 MB.

## 4. Session, đăng nhập và HTTPS

- [ ] Đặt `SESSION_SECURE_COOKIE=true` sau khi HTTPS hoạt động.
- [ ] Giữ `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax` và `SESSION_DOMAIN=null` trừ khi cần chia session giữa các subdomain.
- [ ] Kiểm tra login local, Microsoft SSO (nếu bật), logout, browser Back sau logout và đăng nhập đồng thời nhiều thiết bị.
- [ ] Kiểm tra flow AI+ policy acceptance: user mới/chưa đồng ý phải xác nhận policy trước khi vào AI+ hoặc Admin.
- [ ] Cấu hình Microsoft redirect URI HTTPS chính xác nếu dùng SSO.

## 5. OpenAI và usage

- [ ] Đặt `OPENAI_API_KEY` production bằng secret an toàn; xác nhận bằng `php artisan openai:test-key`.
- [ ] Model/usage ledger: backup DB, pull code rồi chạy `php artisan migrate --force --path=database/migrations/2026_10_27_131210_add_chat_model_and_quota_units.php` nếu migration chưa chạy. Migration giữ nguyên log và chỉ thêm trường model/usage. Giá trị quota cũ trong DB/env không còn chặn request và không cần sửa thủ công. Clear config/views và restart `ai-plus.service` nếu domain đi qua port 8000; reload PHP-FPM nếu dùng FPM.
- [ ] Cho phép `gpt-6-sol` trong đúng OpenAI Project; giữ Luna mặc định. Test JSON/SSE, đổi Luna/Sol, file Word/Excel/PPTX, Agent con và cách hiển thị usage trong admin/My Usage. Tắt riêng Sol bằng `AI_PLUS_SOL_ENABLED=false` khi cần; dữ liệu usage Sol đã ghi vẫn được giữ. Chưa có kiểm tra entitlement thật nếu chỉ chạy test giả lập.
- [ ] Sau khi pull, chạy migration `2026_11_04_100331_create_conversation_attachments_table.php` để bật lưu snapshot tài liệu đính kèm cho điều phối Agent. Kiểm tra quyền ghi private disk `chat-attachments`, ownership conversation, handoff file Word/PDF/XLSX/PPTX/CSV/code và ảnh, dữ liệu trích xuất dài, nguồn thiếu/không đọc được, thu hồi quyền Agent, xóa chat và tiếp tục dùng nguồn/kết quả ở lượt sau. Không chạy migration rollback để tắt tính năng; dùng `AI_PLUS_AGENT_CONNECTIONS_ENABLED=false` nếu cần tắt điều phối.
- [ ] Với chat nhiều tài liệu, xác nhận Agent cha chọn đúng mã nguồn cho từng chuyên viên; nguồn không được chọn và kết quả Agent cũ không lọt vào lượt kiểm chứng độc lập. Dùng prompt và bảng dài để xác nhận AI+ không cắt nội dung hoặc áp quota số lượt tiếp nối; context/token của OpenAI, PHP, proxy và tài nguyên máy chủ vẫn áp dụng. Chỉ hiện hoàn tất khi phần cuối về đủ; nếu provider hoặc hạ tầng dừng request thì hiển thị đúng phần đã nhận và trạng thái chưa hoàn tất.
- [ ] Trong cùng chat, duyệt một bản nháp rồi yêu cầu tạo Word tải xuống. Kiểm tra file thực sự có đủ nội dung bản nháp; nếu model nói sai là không có công cụ, bước khôi phục chỉ chạy sau xác nhận yêu cầu rõ. Câu hỏi về khả năng tạo file không được tự tạo file. Khi xác nhận lỗi, phản hồi nêu lỗi kỹ thuật, không yêu cầu giáo viên diễn đạt lại một lệnh đã rõ.
- [ ] Sau deploy, có thể chạy `php artisan openai:test-key --live --model=gpt-6-sol` để xác nhận inference, streaming và function calling trên chính server. Cờ `--live` có tiêu token API; chỉ dùng nội dung giả và không ghi vào tài khoản user. Lệnh mặc định không có cờ vẫn chỉ kiểm tra key bằng GET `/models`.
- [ ] Kiểm tra model chat, embeddings/RAG và các image model được cấp quyền trong OpenAI Project. Với Agent kết nối, xác nhận điều phối và chuyên viên có thể chọn model riêng, model chuyên viên được nhớ theo chat và usage được ghi theo model thực tế.
- [ ] Chạy migration `2026_10_27_141302_add_specialist_model_to_conversations_table.php` sau khi pull code để lưu lựa chọn model chuyên viên; không rollback migration để tắt Sol.
- [ ] Nếu có cấu hình kỳ báo cáo, xác nhận `AI_USAGE_PHASE` và `AI_USAGE_PHASE_STARTED_AT`; các giá trị này chỉ dùng để phân tích usage và không giới hạn user.
- [ ] Kiểm tra token/credits usage theo tháng, Image Studio và đảm bảo usage không chặn request.
- [ ] Gửi `safety_identifier` dạng hash theo user như code hiện tại; không gửi email thô.
- [ ] **Moderation API hiện tạm để `OPENAI_MODERATION_ENABLED=false`** do project trả `403 Forbidden`. Chỉ bật lại sau khi endpoint `/v1/moderations` được xác nhận hoạt động với OpenAI Project/API key trên server.

## 6. File, RAG, PDF và web reader

- [ ] Cài Poppler (`pdftotext` và `pdftoppm`); đặt `PDFTOTEXT_BINARY` và `PDFTOPPM_BINARY` đúng đường dẫn tuyệt đối khi cần, thường là `/usr/bin/pdftotext` và `/usr/bin/pdftoppm` trên Linux. `pdftotext` trích xuất trang được chỉ định; `pdftoppm` là phương án đọc PDF scan.
- [ ] PDF text và PDF scan được xử lý trên các trang cần thiết; không cấu hình `PDF_SCAN_MAX_PAGES`. Kiểm tra tài nguyên Poppler/OCR và giới hạn OpenAI khi tài liệu rất dài.
- [ ] Test PDF có text, PDF scan, DOCX, XLSX, PPTX, TXT, CSV và HTML trong cả Chat lẫn Agent Knowledge.
- [ ] Cài Chromium cho Playwright: `npx playwright install chromium` sau khi cài Node dependencies.
- [ ] Bật `AI_PLUS_WEB_READING_ENABLED=true` và chỉ bật `AI_PLUS_WEB_BROWSER_RENDERING_ENABLED=true` khi Chromium/Playwright đã test thành công.
- [ ] Test đọc link HTTP/HTTPS, trang JavaScript, redirect, và xác nhận SSRF guard vẫn chặn IP/private network.

## 7. Scheduler, queue và vận hành nền

- [ ] Thiết lập cron chạy mỗi phút:

  ```cron
  * * * * * cd /var/www/ai-plus && php artisan schedule:run >> /dev/null 2>&1
  ```

- [ ] Scheduler sẽ xóa Team Invitations hết hạn và Work-Use Monitoring events quá 180 ngày.
- [ ] Nếu production bắt đầu dùng jobs, chạy `php artisan queue:work` dưới Supervisor/systemd và cấu hình restart/deploy phù hợp.
- [ ] Kiểm tra `failed_jobs`, Laravel logs và dung lượng disk định kỳ.

## 8. Nginx / streaming / network

- [ ] Tắt response buffering cho endpoint streaming `/ai-plus/agent-workspace/send-stream` (`X-Accel-Buffering: no` đã được app gửi, nhưng Nginx vẫn cần được kiểm tra).
- [ ] Cấu hình proxy/FastCGI read timeout lớn hơn thời gian OpenAI response.
- [ ] Giới hạn request body theo upload policy và bật rate limiting ở Nginx/WAF nếu hạ tầng hỗ trợ.
- [ ] Cấu hình trusted proxy đúng khi HTTPS terminate ở load balancer/reverse proxy.

## 9. Bảo mật và kiểm thử go-live

- [ ] Chạy `php artisan test`, `npm run types:check`, và kiểm tra package security audit trong môi trường có Internet.
- [ ] Kiểm tra quyền user/staff/admin, agent ownership, shared/use-only agent, knowledge sharing và truy cập URL trực tiếp.
- [ ] Kiểm tra PII filter, policy acceptance, local safety fallback, usage reporting và Support ticket flow.
- [ ] Kiểm tra backup/restore một lần trước go-live.
- [ ] Tạo tài khoản test không phải admin và thực hiện smoke test từ mạng bên ngoài.
- [ ] Theo dõi logs, failed jobs, API errors, token usage và phản hồi user trong tuần đầu.

## 10. Kế hoạch rollback

- [ ] Connected Agents: ghi nhận commit release đang chạy. Migration `2026_10_23_135428_create_agent_connections_table.php` chỉ thêm bảng kết nối, giữ nguyên dữ liệu Agent/chat/Knowledge cũ. Kiểm thử Agent có kết nối, Agent thường, sharing, tạo file và usage trước khi mở rộng test.
- [ ] Tắt Connected Agents khi có lỗi: đặt `AI_PLUS_AGENT_CONNECTIONS_ENABLED=false` trong `.env`, chạy `php artisan config:clear`, rồi reload PHP-FPM. Cấu hình và lịch sử được giữ lại. Nếu rollback code về release trước, giữ bảng kết nối; không chạy migration rollback phá hủy cấu hình.
- [ ] Backup database và release hiện tại trước mỗi deploy.
- [ ] Lưu release trước đó để có thể rollback code nhanh.
- [ ] Không rollback migration phá hủy dữ liệu khi chưa xác nhận backup/khả năng phục hồi.
