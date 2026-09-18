# AI Plus — Project Specification

## 1. Tổng quan

AI Plus là nền tảng AI nội bộ của LSTS, hỗ trợ giáo viên và nhân sự làm việc với AI trong các tác vụ học thuật, vận hành và hành chính. Hệ thống kết hợp chat AI, Agent chuyên biệt, RAG theo knowledge file, quản trị tập trung và các khả năng tạo đầu ra công việc.

## 2. Mục tiêu

- Cung cấp AI an toàn trong môi trường trường học.
- Cho phép user trao đổi, phân tích tài liệu và tạo nội dung.
- Cho phép tạo Agent theo mục đích công việc và chia sẻ trong trường.
- Kiểm soát quyền truy cập, quota token và dữ liệu nhạy cảm.
- Mở rộng dần từ chat sang AI hỗ trợ tạo file và quy trình công việc.

## 3. Người dùng và quyền

| Nhóm | Quyền chính |
| --- | --- |
| User/Staff | Chat, tạo Agent cá nhân, upload knowledge, dùng showcase, xem quota và artifact cá nhân. |
| Admin | Quản lý user, prompt, template, showcase, support ticket, audit log và cấu hình nghiệp vụ. |

Mọi conversation, attachment, artifact và email draft thuộc về user tạo ra. User khác không thể đọc hoặc tải các tài nguyên này nếu không có cơ chế chia sẻ riêng.

## 4. Chức năng hiện có

### 4.1 AI Chat

- Chat riêng hoặc chat với Agent.
- Lưu conversation và message history.
- Streaming phản hồi theo thời gian thực.
- Tự tạo tiêu đề conversation.
- Sidebar sắp xếp theo thời điểm cập nhật gần nhất.
- Upload ảnh, Word, Excel, PDF, CSV và các định dạng hỗ trợ.
- PDF có text layer được trích xuất trực tiếp; PDF scan/image-only được render thành ảnh để AI vision đọc.

### 4.2 Agent Workspace và RAG

- Tạo, sửa, xóa Agent.
- Cấu hình tên, mô tả, system prompt và knowledge file.
- Gắn Agent với conversation.
- Knowledge được cắt đoạn và truy xuất bằng keyword RAG.
- Agent có thể được chia sẻ công khai trong trường qua Sharing & Showcase.

### 4.3 Sharing & Showcase

- Agent chọn `Share with school` xuất hiện tại Sharing & Showcase.
- User khác có thể dùng Agent để tạo bản sao thuộc sở hữu của chính họ.
- Badge `New` cho nội dung mới và `Popular` cho Agent được sử dụng nhiều.

### 4.4 Admin Panel

- Quản lý user, role, department và trạng thái tài khoản.
- Quản lý prompt, template, showcase, FAQ và support ticket.
- Audit log cho các hành động quản trị.
- Shortcut từ AI Plus sang Admin Panel cho admin.

### 4.5 Usage quota

- Theo dõi prompt, token usage và hoạt động gần đây.
- Quota reset vào ngày đầu tháng.
- Testing: 20 triệu tokens/user/tháng.
- Training: 10 triệu tokens/user/tháng.
- Conversation đã xóa không hiển thị trong Recent Activity.

## 5. AI Tool và Artifact — Giai đoạn 1

### 5.1 Tạo file

Khi user yêu cầu tạo hoặc xuất file Excel, Word hoặc PDF từ nội dung trao đổi, AI Plus tạo artifact private cho user.

| Loại | Định dạng | Mục đích |
| --- | --- | --- |
| Excel | `.xlsx` | Bảng tổng hợp, dữ liệu, báo cáo dạng sheet. |
| Word | `.docx` | Thông báo, kế hoạch, báo cáo, văn bản. |
| PDF | `.pdf` | Bản xuất để gửi hoặc lưu trữ. |

Luồng xử lý:

1. User trao đổi với AI hoặc upload tài liệu.
2. User yêu cầu tạo file, ví dụ: `Tạo file Word với nội dung trên`.
3. AI tạo nội dung phản hồi.
4. Backend tạo artifact private, gắn với user và conversation.
5. Chat hiển thị link tải file.
6. Artifact xuất hiện trong lịch sử file gần đây.

File gốc của user không bị ghi đè. Mỗi lần tạo tạo ra một artifact mới.

### 5.2 Email draft

Khi user yêu cầu `Soạn email nháp`, hệ thống lưu subject và body do AI tạo. Email draft chỉ là bản nháp; Giai đoạn 1 không gửi email, không kết nối mailbox và không gửi ra ngoài hệ thống.

### 5.3 Streaming và audit

- SSE thông báo các trạng thái AI trả lời và đang tạo file/email nháp.
- Các hành động `ai_artifact.created` và `email_draft.created` được audit log.
- Artifact download kiểm tra ownership trước khi trả file.

## 6. Mô hình dữ liệu chính

| Entity | Vai trò |
| --- | --- |
| `users` | Tài khoản, role, department, trạng thái hoạt động. |
| `conversations` | Cuộc hội thoại; có thể gắn với Agent. |
| `messages` | Tin nhắn user/assistant, token usage. |
| `agents` | Agent của user, system prompt, knowledge. |
| `knowledge_chunks` | Các đoạn knowledge để RAG. |
| `usage_logs` | Token usage và activity. |
| `ai_artifacts` | File AI tạo: owner, conversation, path, MIME type, size. |
| `email_drafts` | Email nháp: owner, conversation, recipient, subject, body, attachment metadata. |
| `admin_audit_logs` | Nhật ký hành động quản trị và AI tool actions. |

## 7. API và route quan trọng

| Method | Route | Mục đích |
| --- | --- | --- |
| POST | `/ai-plus/agent-workspace/send` | Chat không streaming. |
| POST | `/ai-plus/agent-workspace/send-stream` | Chat streaming qua SSE. |
| GET | `/ai-plus/artifacts/{artifact}/download` | Tải artifact khi đúng owner. |
| GET | `/ai-plus/agent-workspace/attachments/{conversation}/{filename}` | Tải attachment chat khi đúng owner. |

## 8. Bảo mật và quyền riêng tư

- Authentication bắt buộc cho các route AI chức năng.
- Kiểm tra ownership cho conversation, attachment và artifact.
- Attachment và artifact dùng storage private, không public trực tiếp.
- Rate limit cho login, chat, upload Agent và support request.
- Markdown AI được sanitize trước khi render; nội dung admin được escape để tránh XSS.
- Không để model chạy lệnh hệ thống tùy ý; mọi hành động phải qua backend tool xác định trước.

### 8.1 PII filtering

PII được lọc trước khi gửi sang AI, gồm:

- Email ngoài `@lsts.edu.vn`.
- Số điện thoại Việt Nam.
- Mã học sinh/sinh viên.
- CCCD/CMND.
- Địa chỉ cụ thể.
- Số tài khoản, hộ chiếu và biển số xe.

Email đúng domain `@lsts.edu.vn` được cho phép cho mục đích công việc nội bộ. Không log raw PII trong audit log.

## 9. Giới hạn hiện tại

- Chưa gửi email thật.
- Chưa kết nối Microsoft 365, OneDrive, Google Drive hoặc Gmail.
- Chưa sửa trực tiếp file gốc của user.
- Artifact hiện được tạo từ nội dung phản hồi AI; template Word/Excel nâng cao là bước tiếp theo.
- PDF scan giới hạn số trang render theo cấu hình để kiểm soát thời gian và chi phí.
- Tác vụ file lớn hiện chạy trong request chat; production nên chuyển sang queue.

## 10. Roadmap

### Giai đoạn 1 — hiện tại

- Tạo Excel, Word, PDF.
- Email nháp.
- Artifact private, download, lịch sử và audit log.
- Streaming trạng thái xử lý.
- PDF scan fallback.

### Giai đoạn 2 — khi deploy server

- Kết nối Microsoft 365/OneDrive.
- Lưu artifact vào OneDrive.
- Tạo Outlook email draft.
- User xác nhận trước khi gửi email.
- Queue worker, Redis/object storage và virus scanning.

### Giai đoạn 3

- Gửi email thật sau xác nhận.
- Workflow nhiều bước: đọc file → tạo báo cáo → tạo email → user xác nhận.
- Template Word/Excel chính thức của trường.
- Phân quyền tool theo role/phòng ban.
- Dashboard tool usage, chi phí và audit nâng cao.

## 11. Yêu cầu deploy server

- HTTPS và reverse proxy cấu hình đúng SSE streaming.
- Laravel queue worker và scheduler.
- Database production, backup database và file storage.
- Private object storage hoặc storage server.
- Poppler (`pdftoppm`) cho PDF scan.
- PHP/Nginx upload limit phù hợp.
- Cấu hình `.env`, cache/config/route optimization và monitoring log.
- Chính sách retention/xóa file tạm, backup và xử lý lỗi.
