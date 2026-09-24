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
- [ ] Nếu cần chuyển dữ liệu local, export/import database có kiểm soát; không ghi đè dữ liệu production ngoài kế hoạch backup/rollback.
- [ ] Chạy `php artisan storage:link`.
- [ ] Cho web server quyền ghi giới hạn vào `storage/` và `bootstrap/cache/`; kiểm tra upload Knowledge, chat attachments và AI artifacts.
- [ ] Thiết lập backup database và thư mục `storage/app` định kỳ, đồng thời kiểm tra khả năng restore.

## 3. Build và tối ưu Laravel

- [ ] `composer install --no-dev --optimize-autoloader`.
- [ ] `npm ci` rồi `npm run build`.
- [ ] `php artisan optimize` sau khi `.env` đã hoàn chỉnh.
- [ ] Khi thay đổi `.env`, luôn chạy `php artisan config:clear` trước khi cache lại config.
- [ ] Thiết lập PHP `upload_max_filesize` và `post_max_size` lớn hơn 15 MB (khuyến nghị tối thiểu 25 MB) để hỗ trợ upload file chat.
- [ ] Thiết lập timeout phù hợp cho PHP-FPM/Nginx vì chat streaming và đọc file có thể kéo dài đến 120 giây.

## 4. Session, đăng nhập và HTTPS

- [ ] Đặt `SESSION_SECURE_COOKIE=true` sau khi HTTPS hoạt động.
- [ ] Giữ `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax` và `SESSION_DOMAIN=null` trừ khi cần chia session giữa các subdomain.
- [ ] Kiểm tra login local, Microsoft SSO (nếu bật), logout, browser Back sau logout và đăng nhập đồng thời nhiều thiết bị.
- [ ] Kiểm tra flow AI+ policy acceptance: user mới/chưa đồng ý phải xác nhận policy trước khi vào AI+ hoặc Admin.
- [ ] Cấu hình Microsoft redirect URI HTTPS chính xác nếu dùng SSO.

## 5. OpenAI và quota

- [ ] Đặt `OPENAI_API_KEY` production bằng secret an toàn; xác nhận bằng `php artisan openai:test-key`.
- [ ] Kiểm tra model chat, embeddings/RAG và các image model được cấp quyền trong OpenAI Project.
- [ ] Xác nhận các quota: `AI_USAGE_PHASE`, `AI_TESTING_TOKEN_LIMIT`, `AI_TRAINING_TOKEN_LIMIT`, và `AI_USAGE_PHASE_STARTED_AT`.
- [ ] Kiểm tra token usage, reset theo tháng, Image Studio và giới hạn quota với một user test.
- [ ] Gửi `safety_identifier` dạng hash theo user như code hiện tại; không gửi email thô.
- [ ] **Moderation API hiện tạm để `OPENAI_MODERATION_ENABLED=false`** do project trả `403 Forbidden`. Chỉ bật lại sau khi endpoint `/v1/moderations` được xác nhận hoạt động với OpenAI Project/API key trên server.

## 6. File, RAG, PDF và web reader

- [ ] Cài Poppler (`pdftoppm`) và đặt `PDFTOPPM_BINARY` đúng đường dẫn tuyệt đối, thường là `/usr/bin/pdftoppm` trên Linux.
- [ ] Giữ `PDF_SCAN_MAX_PAGES=10`; PDF scan dài cần user chỉ định trang, ví dụ `pages 12–15`.
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
- [ ] Kiểm tra PII filter, policy acceptance, local safety fallback, token quota và Support ticket flow.
- [ ] Kiểm tra backup/restore một lần trước go-live.
- [ ] Tạo tài khoản test không phải admin và thực hiện smoke test từ mạng bên ngoài.
- [ ] Theo dõi logs, failed jobs, API errors, token usage và phản hồi user trong tuần đầu.

## 10. Kế hoạch rollback

- [ ] Backup database và release hiện tại trước mỗi deploy.
- [ ] Lưu release trước đó để có thể rollback code nhanh.
- [ ] Không rollback migration phá hủy dữ liệu khi chưa xác nhận backup/khả năng phục hồi.
