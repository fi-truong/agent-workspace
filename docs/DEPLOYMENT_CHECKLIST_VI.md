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
- [ ] Thiết lập PHP-FPM `upload_max_filesize` tối thiểu 15 MB và `post_max_size` tối thiểu 55 MB để hỗ trợ Agent Knowledge tối đa 15 MB/file, 50 MB/Agent (cộng overhead multipart form).
- [ ] Thiết lập Nginx `client_max_body_size` tối thiểu 60 MB trong virtual host AI+; giới hạn request body phải cao hơn tổng dung lượng Knowledge được phép tải lên.
- [ ] Chat attachment có giới hạn riêng tối đa 5 MB/file và 15 MB tổng; kiểm tra PHP/Nginx vẫn cho phép request ở mức phù hợp.
- [ ] Chat thường giữ mốc PHP 120 giây. Agent có kết nối có 300 giây phối hợp; controller đặt mốc PHP bằng thời gian đó cộng một timeout model để hoàn tất đầu ra (mặc định 420 giây). Cấu hình PHP-FPM/Nginx bên ngoài tối thiểu 450 giây cho mặc định này; xem mục 8.1.

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

### 8.1 Timeout cho Connected Agents (Ubuntu / PHP 8.3)

Phần phối hợp có ngân sách tổng 300 giây; từng lần gọi model dùng giá trị nhỏ hơn giữa `OPENAI_TIMEOUT` và thời gian còn lại. Với `OPENAI_TIMEOUT=120`, controller đặt mốc PHP 420 giây cho request có kết nối; request thường giữ 120 giây. Giới hạn ngoài đề xuất 450 giây chừa khoảng hoàn tất request. Nếu thay đổi các giá trị này, tính lại giới hạn ngoài.

Kiểm tra cấu hình thực tế trên test server trước khi sửa:

```bash
cd /var/www/ai-plus
php artisan config:show agent_connections
php artisan config:show openai.timeout
sudo nginx -T 2>/dev/null | grep -nE '^# configuration file|server_name|fastcgi_pass|proxy_pass|read_timeout'
sudo php-fpm8.3 -tt 2>&1 | grep -E 'request_terminate_timeout|max_execution_time'
```

Với vhost AI+ đang dùng `/etc/nginx/sites-available/default` và pool `/etc/php/8.3/fpm/pool.d/www.conf`, sao lưu trước khi chỉnh:

```bash
sudo cp -n /etc/nginx/sites-available/default /etc/nginx/sites-available/default.before-agent-timeouts
sudo cp -n /etc/php/8.3/fpm/pool.d/www.conf /etc/php/8.3/fpm/pool.d/www.conf.before-agent-timeouts
sudo nano /etc/nginx/sites-available/default
```

Trong `server { ... }` của AI+, thêm hoặc cập nhật các directive sau. Nếu đã có giá trị ở `location` xử lý PHP/proxy thì cập nhật tại location đó vì giá trị con có thể ghi đè giá trị server:

```nginx
fastcgi_read_timeout 450s;
proxy_read_timeout 450s;
```

Sửa pool FPM đang phục vụ AI+:

```bash
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
```

Trong section `[www]`, bỏ dấu `;` và đặt:

```ini
request_terminate_timeout = 450s
```

Nếu pool khóa `max_execution_time` bằng `php_admin_value[max_execution_time]`, bỏ dòng ép đó cho pool AI+ để controller được chọn 120 giây cho chat thường hoặc 420 giây cho Agent có kết nối. `request_terminate_timeout=450s` vẫn là giới hạn ngoài của pool. Không cần tăng `OPENAI_TIMEOUT`. Nếu AI+ dùng pool/vhost riêng thì chỉ sửa file đang phục vụ AI+, không sửa pool của ứng dụng khác.

Kiểm tra và áp dụng sau khi pull code:

```bash
sudo nginx -t &&
sudo php-fpm8.3 -t &&
php artisan config:clear &&
php artisan view:clear &&
sudo systemctl reload nginx &&
sudo systemctl reload php8.3-fpm
```

Các mốc PHP/Nginx là các lớp khác nhau: `set_time_limit` không tính mọi thời gian chờ I/O trên Unix, còn Nginx read timeout là khoảng chờ giữa các lần đọc upstream. Kiểm tra qua domain thật bằng tác vụ nhiều Agent dài hơn 90 giây; xác nhận SSE có `done`, kết quả được lưu và quota tăng đúng. Nếu ngrok đi thẳng đến `127.0.0.1:8000` thì request không qua Nginx/FPM; cần kiểm tra service PHP và giới hạn tunnel/CDN tương ứng. Lượt này vẫn chạy trong request, chưa có job nền/lưu tiến độ từng bước.

Tài liệu tham chiếu: [PHP set_time_limit](https://www.php.net/manual/en/function.set-time-limit.php), [PHP-FPM](https://www.php.net/manual/en/install.fpm.configuration.php), [Nginx FastCGI read timeout](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html#fastcgi_read_timeout), [Nginx proxy read timeout](https://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_read_timeout).

Rollback cấu hình server: khôi phục các giá trị timeout đã ghi lại trong bản sao lưu, kiểm tra `nginx -t`/`php-fpm8.3 -t` rồi reload. Không hạ timeout server trong lúc vẫn còn request phối hợp dài đang chạy. Công tắc `AI_PLUS_AGENT_CONNECTIONS_ENABLED=false` vẫn tắt riêng tính năng mà giữ cấu hình kết nối và lịch sử.

## 9. Bảo mật và kiểm thử go-live

- [ ] Chạy `php artisan test`, `npm run types:check`, và kiểm tra package security audit trong môi trường có Internet.
- [ ] Kiểm tra quyền user/staff/admin, agent ownership, shared/use-only agent, knowledge sharing và truy cập URL trực tiếp.
- [ ] Kiểm tra PII filter, policy acceptance, local safety fallback, token quota và Support ticket flow.
- [ ] Kiểm tra backup/restore một lần trước go-live.
- [ ] Tạo tài khoản test không phải admin và thực hiện smoke test từ mạng bên ngoài.
- [ ] Theo dõi logs, failed jobs, API errors, token usage và phản hồi user trong tuần đầu.

## 10. Kế hoạch rollback

- [ ] Connected Agents: ghi nhận commit release đang chạy. Migration `2026_10_23_135428_create_agent_connections_table.php` chỉ thêm bảng kết nối, giữ nguyên dữ liệu Agent/chat/Knowledge cũ. Kiểm thử Agent có kết nối, Agent thường, sharing, tạo file và usage trước khi mở rộng test.
- [ ] Tắt Connected Agents khi có lỗi: đặt `AI_PLUS_AGENT_CONNECTIONS_ENABLED=false` trong `.env`, chạy `php artisan config:clear`, rồi reload PHP-FPM. Cấu hình và lịch sử được giữ lại. Nếu rollback code về release trước, giữ bảng kết nối; không chạy migration rollback phá hủy cấu hình.
- [ ] Backup database và release hiện tại trước mỗi deploy.
- [ ] Lưu release trước đó để có thể rollback code nhanh.
- [ ] Không rollback migration phá hủy dữ liệu khi chưa xác nhận backup/khả năng phục hồi.
