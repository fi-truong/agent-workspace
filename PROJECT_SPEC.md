# AI Plus — Đặc tả dự án

> **Phiên bản 2.3 · Đối chiếu mã nguồn: 27/10/2026.** Bản tiếng Anh tương ứng: `PROJECT_SPEC_EN.md`. Đây là đặc tả làm việc để rà soát/phê duyệt, không phải xác nhận đã triển khai production. “Hiện có” nghĩa là có mã nguồn/route; “Yêu cầu đích” là tiêu chí cần nghiệm thu; “Kế hoạch” chưa triển khai đầy đủ; “TBD” cần quyết định.

Các mục 1–11 là tóm tắt sản phẩm; mục 12–24 quy định yêu cầu chi tiết, tiêu chí nghiệm thu và các quyết định còn mở.

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
- Ô chat có prompt ở trên, đính kèm phía dưới bên trái, chọn model và nút gửi chỉ có biểu tượng mũi tên phía dưới bên phải.
- User chọn GPT-6 Luna (mặc định) hoặc GPT-6 Sol cho Agent điều phối; lựa chọn được nhớ theo cuộc chat và áp dụng cho việc điều phối, câu trả lời chính cùng thao tác tạo file. User chọn riêng GPT-6 Luna (mặc định) hoặc GPT-6 Sol cho tất cả Agent chuyên viên trong cuộc chat. Không tự đổi model khi provider từ chối truy cập.
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
- Knowledge được cắt đoạn, tạo embedding và truy xuất theo độ liên quan; có fallback keyword khi embedding không khả dụng theo cấu hình.
- Agent có thể được chia sẻ công khai trong trường qua Sharing & Showcase.
- Chủ Agent có thể kết nối tối đa 10 Agent trong workspace, kèm vai trò cho từng Agent. Agent điều phối dùng model user chọn để quyết định giao việc và tổng hợp; mỗi Agent chuyên viên được gọi tối đa một lần/lượt bằng model chuyên viên user chọn chung cho cuộc chat (mặc định GPT-6 Luna), dùng prompt và Knowledge riêng rồi trả kết quả về. Agent con không gọi tiếp Agent khác và không trực tiếp tạo file.
- Trong chat với Agent chính, các Agent kết nối mà người đang chat có quyền dùng được hiển thị trên một dòng ngay dưới tên Agent chính ở đầu trang; danh sách dài có thể cuộn ngang. Đây là danh sách kết nối khả dụng, không phải xác nhận các Agent đã được gọi trong lượt trả lời.
- Quyền dùng được kiểm tra cho người đang chat ở mỗi lần gọi; kết nối không cấp quyền dùng Agent riêng tư. Agent chia sẻ chỉ gọi được các Agent con mà người đang chat cũng có quyền dùng. Bản sao không tự sao chép cấu hình kết nối; người nhận thiết lập lại.
- Token của mọi lần gọi tính vào quota người đang chat, kể cả khi lượt tổng hợp sau đó lỗi. Giới hạn: 10 lần tham khảo, ngưỡng 32.000 token và 300 giây (5 phút) cho phần phối hợp/lượt. Từng lần gọi không vượt timeout model hiện hành hoặc thời gian phối hợp còn lại. Khi đạt ngưỡng, không gọi tiếp; usage thực tế từ request đang chạy có thể vượt ngưỡng token. Chỉ Agent chính dùng công cụ tạo file, qua bước xác nhận ý định hiện có.
- `AI_PLUS_AGENT_CONNECTIONS_ENABLED=false` đưa chat về luồng hiện hành, giữ cấu hình và lịch sử. Database chưa có bảng kết nối cũng chạy theo luồng hiện hành.

### 4.3 Sharing & Showcase

- Agent chọn `Share with school` xuất hiện tại Sharing & Showcase.
- Agent chia sẻ có hai chế độ `use_only` và `copy`; chế độ `copy` cho phép tạo bản sao và chỉ sao chép knowledge khi chủ Agent chọn chế độ này.
- Badge `New` cho nội dung mới và `Popular` cho Agent được sử dụng nhiều.

### 4.4 Admin Panel

- Quản lý user, role, department và trạng thái tài khoản.
- Quản lý prompt, template, showcase, FAQ và support ticket.
- Audit log cho các hành động quản trị.
- Shortcut từ AI Plus sang Admin Panel cho admin.

### 4.5 Usage quota

- Theo dõi prompt, token thực tế, AI+ Credits và hoạt động gần đây.
- Quota được tính theo chu kỳ tháng bắt đầu từ ngày đầu tháng hoặc ngày bắt đầu phase muộn hơn; không cần xóa usage log để “reset”.
- Testing: 20.000 credits/user/tháng (quy đổi từ quota 20 triệu token cũ).
- Training: 10.000 credits/user/tháng (quy đổi từ quota 10 triệu token cũ).
- 1.000 token Luna = 1 credit; 1.000 token GPT-6 Sol = 20 credits, tính cả input và output. Đây là đơn vị quota nội bộ, không phải tiền hay OpenAI credits. Lưu bằng đơn vị nguyên 0,001 credit, không làm tròn lên mỗi lượt. Token ảnh giữ giá trị quota cũ, không áp bảng giá tạo ảnh vào hệ số Sol.
- Lịch sử cũ không bị tính lại; log cũ chưa có `quota_units` dùng tổng token cũ. Log mới lưu model và snapshot `quota_units`; tắt Sol không thay đổi usage đã ghi. Quota override và biến môi trường token cũ giữ đơn vị lưu trữ để bảo toàn số dư. Admin nhập/hiển thị credits; thống kê token thật vẫn được giữ riêng.
- Conversation đã xóa không hiển thị trong Recent Activity.

### 4.6 Image Workspace

- Tạo và chỉnh ảnh từ prompt/ảnh tham chiếu qua model ảnh được cấu hình riêng.
- Ảnh kết quả gắn conversation, có route tải và xóa với kiểm tra owner.

### 4.7 School Knowledge và an toàn sử dụng

- Admin nạp tài liệu/trang web công khai của trường, index và theo dõi trạng thái nguồn tri thức cấp trường.
- AI Policy yêu cầu user chấp nhận phiên bản hiện hành; work-use monitoring ghi metadata giới hạn cho trường hợp cần xem xét.
- Đăng nhập Microsoft có route triển khai; khả năng sử dụng phụ thuộc cấu hình tenant thật.

## 5. AI Tool và Artifact — Giai đoạn 1

### 5.1 Tạo file

Khi user yêu cầu tạo hoặc xuất file Excel, Word, PowerPoint hoặc PDF từ nội dung trao đổi, AI Plus tạo artifact private cho user.

| Loại | Định dạng | Mục đích |
| --- | --- | --- |
| Excel | `.xlsx` | Bảng tổng hợp, dữ liệu, báo cáo dạng sheet. |
| Word | `.docx` | Thông báo, kế hoạch, báo cáo, văn bản. |
| PowerPoint | `.pptx` | Bài trình bày theo slide, tiêu đề và gạch đầu dòng. |
| PDF | `.pdf` | Bản xuất để gửi hoặc lưu trữ. |

Chat cũng nhận file mã nguồn dạng văn bản đơn lẻ (`.js`, `.css`, `.ts`, `.json`, `.xml`, `.md`, `.php`, `.py`, `.java`, `.sql` và một số định dạng phổ biến khác). AI có thể phân tích/sửa và trả file mới cùng phần mở rộng; file nguồn không bị ghi đè. Mã nguồn được xử lý như văn bản, không thực thi. Mỗi file chat tối đa 5 MiB; tối đa 20.000 ký tự được đưa vào ngữ cảnh AI. Khi sửa code, AI chỉ trả các thay đổi nhỏ theo dạng patch; server áp dụng patch duy nhất, khớp chính xác vào bản gốc riêng tư rồi tạo file hoàn chỉnh, giữ nguyên phần không đổi. Nếu patch không khớp chính xác một vị trí, hệ thống không tạo file và yêu cầu user làm rõ. File dài hơn phần ngữ cảnh không được xem như đã được AI rà soát toàn bộ.

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
- Luồng xuất conversation còn hỗ trợ HTML, lựa chọn ngôn ngữ và kiểu tài liệu; các template này là cấu trúc đầu ra, chưa phải mẫu Word/Excel chính thức của trường.
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
- Laravel scheduler; queue worker khi tác vụ nền đã được đưa vào queue.
- Database production, backup database và file storage.
- Private object storage hoặc storage server.
- Poppler (`pdftoppm`) cho PDF scan.
- PHP/Nginx upload limit phù hợp.
- Cấu hình `.env`, cache/config/route optimization và monitoring log.
- Chính sách retention/xóa file tạm, backup và xử lý lỗi.

## 12. Quản lý tài liệu và ranh giới phạm vi

| Thuộc tính | Nội dung |
| --- | --- |
| Sản phẩm | AI Plus (AI+), nền tảng AI nội bộ của Lawrence S. Ting School (LSTS) |
| Đơn vị chủ trì | CIEC; product owner, data owner và governance cuối cùng: **TBD** |
| Người đọc | Lãnh đạo trường, product owner, IT, phát triển, QA, admin và quản trị dữ liệu |
| Tài liệu liên quan | `PROJECT_SPEC_EN.md`, `docs/DEPLOYMENT_CHECKLIST_VI.md`, `docs/openai-integration.md`, mã nguồn và migration |
| Thứ tự sử dụng | Spec mô tả yêu cầu; mã nguồn phản ánh hiện trạng; checklist triển khai mô tả thao tác vận hành. Khác biệt phải được ghi thành gap và giải quyết. |

Mỗi thay đổi phạm vi cần cập nhật cả hai ngôn ngữ, ngày sửa, quyết định liên quan và tiêu chí nghiệm thu. Không xem roadmap là lịch cam kết. Các chỉ số thành công cần chốt trước pilot: số user kích hoạt/hoạt động hàng tuần, tỷ lệ chat và export hoàn tất, số lần PII bị chặn đúng/sai, token/chi phí so với ngân sách, ticket và thời gian xử lý. Owner, cách đo và ngưỡng mục tiêu: **TBD**.

### 12.1 Phạm vi và ngoài phạm vi

- **Trong phạm vi hiện tại:** chat, Agent/RAG, School Knowledge, Prompt Library, Agent Templates, Sharing & Showcase, Image Workspace, quota/My Usage, support, admin, xuất file và email draft.
- **Ngoài phạm vi hiện tại:** gửi email thật; truy cập mailbox; đồng bộ OneDrive/Google Drive/Gmail; sửa đè file gốc; quy trình nhiều bước tự động; cam kết nội dung AI chính xác tuyệt đối hoặc giữ nguyên bố cục phức tạp của file nguồn.
- **Phụ thuộc môi trường:** Microsoft SSO cần App Registration/secret; PDF scan cần Poppler; OCR cần binary phù hợp; đọc trang JavaScript cần Playwright/Chromium; AI cần model và API entitlement tương ứng. Có route/mã nguồn không bảo đảm feature đã sẵn sàng trên production.

## 13. Kiến trúc, luồng xử lý và công nghệ

| Lớp | Thiết kế hiện có |
| --- | --- |
| Frontend | Laravel Blade và React/Inertia, Vite |
| Backend | PHP `^8.3`, Laravel `^13.17`, controller/service/middleware; queue và scheduler Laravel |
| AI | OpenAI API cho chat, embeddings, moderation theo cấu hình và tạo ảnh; model/timeout giới hạn qua environment |
| Dữ liệu | Eloquent/migrations; MySQL là mục tiêu production, local/test có thể dùng cấu hình khác |
| Tệp | Disk riêng `knowledge`, `chat-attachments`, `agent-avatars`, `ai-artifacts` với storage private |
| Xuất tài liệu | PhpWord, PhpSpreadsheet, DomPDF, PDF parser; Poppler cho PDF scan |

**Luồng chat:** browser → route `auth`/AI policy/throttle → validate input/PII → safety và quota → chuẩn bị history, attachment, Agent/School Knowledge, web page công khai nếu user đưa URL → OpenAI → lưu message/usage → tạo artifact/draft khi được yêu cầu → trả JSON hoặc SSE. Nội dung từ file và web là dữ liệu không tin cậy, không phải chỉ dẫn điều khiển hệ thống.

## 14. Yêu cầu chức năng có thể nghiệm thu

Các ID dưới đây bổ sung phần mô tả chức năng ở mục 4–7. “Hiện có” dựa trên mã nguồn; QA vẫn phải kiểm chứng trên staging.

| ID | Yêu cầu và tiêu chí nghiệm thu | Trạng thái |
| --- | --- | --- |
| FR-01 | User đã đăng nhập, tài khoản active và đã chấp nhận phiên bản AI Policy hiện hành mới dùng được module AI+; trang xác nhận lần đầu và trang AI Policy & Guidelines bên trong cho phép chuyển đổi nội dung English/Tiếng Việt; truy cập trực tiếp route bảo vệ khi thiếu điều kiện bị từ chối/chuyển hướng đúng. | Hiện có |
| FR-02 | Admin route chỉ dành cho admin; thao tác quản trị quan trọng ghi actor, action, target và thời gian. Quyền admin không mặc nhiên cho phép đọc chat/file riêng của user. | Hiện có một phần; cần ma trận audit |
| FR-03 | User tạo chat thường/Agent, xem lịch sử, đổi tên, di chuyển, xóa; user khác không thể truy cập bằng ID đoán được. Recent Activity không hiển thị conversation đã xóa. | Hiện có |
| FR-04 | Chat hỗ trợ JSON và SSE; client hiển thị nội dung tăng dần, lỗi và kết quả hoàn tất. Stream đứt giữa chừng không được xem là câu trả lời hoàn tất. | Hiện có; kiểm tra qua proxy thật |
| FR-05 | Chat nhận tối đa 5 attachment/lượt, tổng 15 MiB; tối đa 4 ảnh, 4 MiB/ảnh; tài liệu và mã nguồn văn bản tối đa 5 MiB/file; code đưa vào context tối đa 20.000 ký tự; `message` tối đa 5.000 ký tự. Mã nguồn không được thực thi; file code quá dài không được xuất một phần. Lỗi giới hạn trả thông báo dễ hiểu. | Hiện có |
| FR-06 | Agent có owner, tên, mô tả, system prompt, avatar, knowledge; chỉ owner sửa/xóa. Danh sách My Agents có chiều cao thẻ mặc định đồng nhất; vùng mô tả dành ba dòng, rút gọn mô tả dài và có nút More/Less để chỉ mở rộng thẻ được chọn. Knowledge tối đa 10 file, 15 MiB/file và 50 MiB/Agent. | Hiện có |
| FR-07 | Knowledge được index/reindex và truy xuất theo độ liên quan; embedding lỗi có thể fallback keyword. Khi không có đoạn phù hợp, AI không được khẳng định có căn cứ từ knowledge. | Hiện có một phần; đánh giá chất lượng TBD |
| FR-08 | Admin có thể nạp file hỗ trợ/trang công khai `lsts.edu.vn` vào School Knowledge, xem trạng thái `processing/ready/failed`, sync, xóa và bật/tắt sử dụng. | Hiện có |
| FR-09 | Agent chia sẻ theo `use_only` hoặc `copy`; copy knowledge chỉ xảy ra khi owner đã cho phép. Thu hồi chia sẻ chặn lượt dùng mới; quyền đối với bản sao đã tạo phải được kiểm thử. | Hiện có; cần kiểm thử quyền |
| FR-10 | Prompt Library/Agent Templates chỉ hiển thị nội dung `published`; mỗi mục được phân loại theo nhóm cấp 1 `Teaching` hoặc `School Operations` và danh mục cấp 2 phù hợp. Thư viện mặc định chỉ xuất bản một bộ chọn lọc gồm prompt đầy đủ, có ngữ cảnh/đầu ra mong muốn và Agent Template có system prompt dùng được; nội dung cũ không chọn giữ ở trạng thái draft/archive, không xóa lịch sử hay lượt dùng. Admin quản lý trạng thái, nhóm/danh mục; bộ lọc công khai hỗ trợ cả hai tầng. Showcase có tìm kiếm/lọc, lượt xem/sử dụng, bình luận và quyền xóa theo owner/admin. | Hiện có sau migration; cần QA |
| FR-11 | Image Workspace tạo/chỉnh ảnh từ prompt và tối đa 4 ảnh tham chiếu; ảnh gắn conversation và chỉ owner tải/xóa. | Hiện có |
| FR-12 | Tạo file tạo artifact mới trên disk private, gắn user/conversation, không ghi đè file nguồn; hỗ trợ DOCX/XLSX/PPTX/PDF/HTML và file mã nguồn văn bản giữ phần mở rộng. PowerPoint phải là gói `.pptx` hợp lệ, chia nội dung thành slide và hiển thị tiêu đề/gạch đầu dòng; không chỉ trả văn bản trong chat. Yêu cầu tạo trang HTML phải tạo tài liệu HTML độc lập đầy đủ; không lưu câu trả lời dạng văn xuôi thành file HTML. Export conversation hỗ trợ PPTX/HTML, ngôn ngữ nguồn/VI/EN và kiểu standard/report/lesson plan/meeting minutes/budget. | Hiện có |
| FR-13 | Download/xóa artifact và xem attachment/ảnh kiểm tra ownership ở backend; file thiếu trả 404, truy cập chéo bị từ chối. | Hiện có |
| FR-14 | “Soạn email nháp” chỉ lưu subject/body gắn user/conversation; không gửi, không đọc mailbox; recipient/attachment chưa được xác nhận tự động. | Hiện có |
| FR-15 | My Usage chỉ hiển thị dữ liệu của user; quota Credits dùng `SUM(COALESCE(quota_units, prompt_tokens + completion_tokens))/1000` trong chu kỳ tháng/phase, có override từng user; hết quota trả 429. | Hiện có |
| FR-16 | User gửi/theo dõi support ticket, có thể đính kèm tối đa 5 ảnh/tệp được hỗ trợ (10 MiB/tệp, 15 MiB tổng); chỉ user sở hữu ticket và admin được tải tệp từ private storage. Admin có thể đăng public reply kèm tối đa 4 ảnh JPG/PNG/WebP (4 MiB/ảnh, 16 MiB tổng), dán ảnh vào ô trả lời hoặc chọn tệp; ảnh chỉ hiển thị cho user sở hữu ticket và admin, không gửi email, và bị xóa khi ticket bị xóa. Tệp yêu cầu ban đầu không gửi qua email; khi xóa ticket, tệp đính kèm cũng bị xóa. Admin thấy badge “New” tự cập nhật cho ticket Pending; badge hết nhấp nháy khi ticket được chuyển sang trạng thái khác Pending. Phản hồi của admin là public reply trong lịch sử ticket, không gửi email. | Hiện có một phần; cần QA trên local |

**Quota mặc định hiện có:** Testing 20.000, Training 10.000 credits/user/tháng; chu kỳ từ đầu tháng hoặc ngày bắt đầu phase nếu muộn hơn. Đây là quota nội bộ, **không phải** giới hạn chi phí tiền tệ hay số ảnh. Input/output và ảnh có giá khác nhau; một request đang chạy có thể vượt số dư, không có reservation ngân sách đồng thời. Phase production, ngân sách và cảnh báo chi phí: **TBD**.

## 15. Dữ liệu, sở hữu và vòng đời

| Nhóm | Nội dung | Quy tắc cần kiểm soát |
| --- | --- | --- |
| `users`, `teams` | Tài khoản, role, trạng thái, team, quota, chấp nhận policy | Khóa user không làm lộ dữ liệu; xóa user cần quy trình |
| `conversations`, `messages` | Chat/ảnh, tin nhắn, Agent liên kết | Owner và lịch sử; xóa chat/attachment/ảnh nhất quán |
| `agents`, `knowledge_chunks` | Agent, file knowledge và index | Owner, chia sẻ theo chế độ, reindex khi đổi file |
| `school_knowledge_sources/chunks` | Nguồn tri thức cấp trường và index | Admin quản lý, sync, trạng thái lỗi, xóa nguồn/chunk |
| `usage_logs`, `ai_safety_events` | Token/activity, phân loại work-use và moderation metadata | Usage dùng cho quota; safety events được prune sau 180 ngày |
| `ai_artifacts`, `email_drafts` | Tệp và email draft private | FK tới user, conversation có thể null khi chat bị xóa; không suy diễn artifact cũng bị xóa |
| Showcase, support, `admin_audit_logs` | Bài đăng/tương tác, ticket/reply, kiểm toán | Chỉ post published hiển thị; retention TBD |

**Yêu cầu đích:** chốt retention riêng cho conversation, attachment, image, knowledge, artifact, draft, usage, audit và backup; cleanup cả DB lẫn file/object storage; xuất/xóa dữ liệu theo yêu cầu hợp lệ; backup mã hóa và diễn tập restore. Hiện chỉ có thời hạn 180 ngày được xác định rõ cho `ai_safety_events`.

## 16. Bảo mật, PII và an toàn AI

| ID | Yêu cầu và tiêu chí nghiệm thu | Trạng thái |
| --- | --- | --- |
| SEC-01 | Kiểm tra auth, active account, policy và owner ở backend; thử truy cập chéo bằng ID/URL trực tiếp phải bị từ chối. | Hiện có; regression bắt buộc |
| SEC-02 | Quét PII ở input có thể gửi AI/lưu: email ngoài whitelist, số điện thoại VN, mã học sinh, CCCD/CMND, địa chỉ, tài khoản, hộ chiếu, biển số. Một số luồng che nhãn, chat có thể chặn 422. Không tuyên bố phát hiện tuyệt đối. | Hiện có; cần kiểm thử false positive/negative |
| SEC-03 | Whitelist mặc định `@lsts.edu.vn` là cấu hình hiện tại, không chứng minh mọi mailbox thuộc domain đều không định danh cá nhân. Data owner/IT phải xác nhận trước go-live. | TBD |
| SEC-04 | Audit/monitoring không lưu raw PII, prompt nhạy cảm hoặc API key; log production được kiểm tra định kỳ. | Yêu cầu đích; audit thực tế |
| SEC-05 | Work-use classifier phục vụ theo dõi; trong chế độ hiện tại chỉ moderation/local safety rule chặn nội dung unsafe, còn phân loại “personal/ambiguous” không tự chặn. Event lưu metadata giới hạn, prune sau 180 ngày. | Hiện có; Moderation API phụ thuộc quyền key |
| SEC-06 | Sanitize Markdown/HTML AI, kiểm tra upload, chống SSRF khi đọc URL, tệp private, CSRF/session/TLS và rate limit. | Hiện có một phần; hardening go-live |
| SEC-07 | Model không được tự chạy lệnh hệ thống hoặc gọi tool ngoài backend action đã định nghĩa; prompt trong file/web/knowledge không được ghi đè chỉ dẫn hệ thống. | Yêu cầu đích; test prompt injection |

**Phân loại dữ liệu đề xuất:** công khai (trang trường), nội bộ (prompt/template), hạn chế (chat, knowledge, attachment, draft/artifact) và đặc biệt nhạy cảm (học sinh, phụ huynh, nhân sự). Data owner phải phê duyệt danh mục được gửi nhà cung cấp AI, căn cứ xử lý, thời gian lưu và quy trình xóa. Không tự giả định toàn bộ tài liệu nội bộ đều được phép gửi ra API ngoài trường.

## 17. Giao diện và quy ước lỗi

Các route dưới đây là route nội bộ web app, **không phải public API có cam kết tương thích**. Request/response đầy đủ cần đối chiếu controller/Form Request/test trước tích hợp bên ngoài.

| Method | Route | Kết quả/chú ý |
| --- | --- | --- |
| POST | `/ai-plus/agent-workspace/generate-image` | auth + policy + image throttle; trả URL ảnh/ID |
| POST | `/ai-plus/agent-workspace/conversations/{conversation}/export` | Chỉ owner; format/language/template; trả download URL |
| DELETE | `/ai-plus/artifacts/{artifact}` | Chỉ owner; xóa file private và metadata |
| PATCH/DELETE | `/ai-plus/agent-workspace/conversations/{conversation}` | Owner đổi tên/xóa; route `/move` để di chuyển |
| CRUD | `/ai-plus/agent-workspace/agents...` | Owner quản lý Agent; chế độ chia sẻ xác định quyền dùng/copy |
| GET/POST | `/ai-plus/sharing-showcase...`, `/ai-plus/support...` | Published content và ticket theo quyền |
| CRUD | `/admin/...` | auth + policy + admin middleware |

Quy ước lỗi đích: 401/chuyển login cho chưa xác thực; 403/404 cho thiếu quyền tùy route; 422 cho validation/PII; 429 cho quota/rate limit; 502 cho provider. Client phải phân biệt lỗi retryable và lỗi cần sửa đầu vào. SSE có thể trả JSON trước khi mở stream khi validation thất bại; chỉ event `done` mới xác nhận hoàn tất.

## 18. Yêu cầu phi chức năng

| ID | Yêu cầu | Cách xác nhận / trạng thái |
| --- | --- | --- |
| NFR-01 | HTTPS, secret ngoài Git, quyền tối thiểu, private disk không public qua web server. | Kiểm tra production và URL trực tiếp |
| NFR-02 | UI báo trạng thái khi chat/tệp đang xử lý; reverse proxy không buffer SSE. Chat thường giữ mốc PHP 120 giây; Agent có kết nối có 300 giây phối hợp và thêm một timeout model để hoàn tất đầu ra (mặc định mốc PHP 420 giây). PHP-FPM/proxy cần giới hạn ngoài phù hợp, mặc định đề xuất 450 giây. | Smoke test qua domain thật, mạng chậm |
| NFR-03 | Báo lỗi rõ khi OpenAI/Poppler/OCR/Playwright không sẵn sàng; fallback chỉ khi đúng ngữ nghĩa. | Dependency-failure test; uptime/SLO TBD |
| NFR-04 | Tác vụ nặng chuyển queue, retry và idempotency trước khi tăng tải; trạng thái hiện tại vẫn có tác vụ chạy trong chat request. | Đo tải pilot; ngưỡng kích hoạt TBD |
| NFR-05 | Theo dõi lỗi, latency, failed jobs, disk, usage và chi phí API, không log dữ liệu nhạy cảm. | Dashboard/alert/owner trực TBD |
| NFR-06 | Backup DB + private files, diễn tập restore, kiểm chứng tính toàn vẹn. | RPO/RTO/lịch backup/retention TBD |
| NFR-07 | UI rõ quota, upload limit, lý do chặn và trạng thái; hỗ trợ VI/EN, keyboard navigation. | UAT; chuẩn accessibility TBD |
| NFR-08 | Tương thích browser/OS và Word/Excel/PDF reader được trường hỗ trợ. | Ma trận phiên bản TBD |

## 19. Kiểm thử, nghiệm thu và phát hành

1. Unit/feature test auth, policy acceptance, role và ownership cho conversation, Agent, attachment, image, artifact, Showcase và support.
2. PII test bằng mẫu VI/EN: dữ liệu hợp lệ/nhạy cảm, mã học sinh theo lô, file đính kèm, false positive và whitelist; xác nhận không có raw PII trong log.
3. Chat JSON/SSE: provider timeout/lỗi, stream ngắt, quota gần/hết mức, ghi usage và không tạo artifact trùng ngoài ý muốn.
4. Import/index/reindex/rút nguồn Agent và School Knowledge; PDF text/scan, OCR có/không có runtime, embedding fallback.
5. DOCX/XLSX/PPTX/PDF/HTML mở được, tiếng Việt có dấu, tên file an toàn, quyền tải/xóa, draft không gửi được.
6. Login local/Microsoft theo môi trường pilot, logout/session, rate limit, CSRF, SSRF và prompt injection.
7. Chạy test suite, lint/type check, migration trên staging; smoke test qua HTTPS/proxy production tương đương. Diễn tập backup/restore.

**Điều kiện nghiệm thu đề xuất:** không còn lỗi P0/P1; mọi đường truy cập chéo bị từ chối; backup/restore đã kiểm chứng; product/data owner ký duyệt UAT, policy dữ liệu và quyết định go-live. Ngưỡng định lượng cho hiệu năng, chất lượng RAG, SLA và mức chấp nhận lỗi phải chốt riêng trước pilot.

## 20. Quy trình triển khai và vận hành

Chuẩn bị Linux, PHP/Composer, Node để build, database production, HTTPS/reverse proxy và private storage. Cài Poppler; chỉ bật OCR/Chromium khi đã kiểm thử. Cấu hình `.env`/secret, `APP_DEBUG=false`, cookie bảo mật, callback SSO, OpenAI key/model, phase/quota, upload và timeout. Chạy migration, build asset, tối ưu Laravel và scheduler mỗi phút. Queue worker cần khi đưa job nặng vào queue. Kiểm tra SSE qua proxy, quyền storage, user thường/admin và cấm truy cập trực tiếp disk private. Thiết lập backup, alert và runbook cho provider outage, hết quota, disk full, lỗi index, rollback. Thao tác chi tiết ở `docs/DEPLOYMENT_CHECKLIST_VI.md`.

## 21. Rủi ro và quyết định còn mở

| ID | Vấn đề/tác động | Quyết định cần có |
| --- | --- | --- |
| D-01 | Governance, product owner, data owner và admin cuối chưa chốt | Lãnh đạo trường phê duyệt RACI/quyền |
| D-02 | Whitelist domain có thể chứa mailbox cá nhân | Data owner/IT xác minh và sửa rule |
| D-03 | Retention ngoài safety events, xóa/export và backup chưa chốt | Chính sách theo loại dữ liệu |
| D-04 | SSO/Outlook/OneDrive cần quyền tenant và cấu hình | IT cấp quyền, test App Registration |
| D-05 | Moderation API có thể bị 403; local fallback chỉ bắt mẫu hẹp | Xác nhận entitlement và fail behavior |
| D-06 | File lớn xử lý trong request, phụ thuộc binary | Đo tải và chuyển queue |
| D-07 | Quota token không tương đương giới hạn tiền/tạo ảnh | Budget cap, giá model, cảnh báo |
| D-08 | RAG/AI có thể sai, nguồn có prompt injection | Bộ đánh giá, human review, red-team |
| D-09 | SLO, RPO/RTO, browser/accessibility chưa định lượng | Chốt theo pilot và năng lực IT |

## 22. Lịch sử phiên bản

| Phiên bản | Ngày | Thay đổi |
| --- | --- | --- |
| 1.x | 18/09/2026 | Bản mô tả ngắn 11 mục |
| 2.0 | 30/09/2026 | Bổ sung phạm vi, trạng thái, FR/NFR, dữ liệu, tiêu chí nghiệm thu, vận hành, rủi ro và quyết định mở; đồng bộ bản Anh–Việt |
| 2.1 | 16/10/2026 | Bổ sung tạo và xuất PowerPoint (`.pptx`) |
| 2.2 | 23/10/2026 | Bổ sung kết nối Agent theo cấu hình chủ sở hữu, giới hạn thực thi, kiểm tra quyền, quota và công tắc rollback |
| 2.3 | 27/10/2026 | Thêm chọn Luna/Sol, AI+ Credits, giữ số dư/lịch sử cũ và lưu snapshot mức trừ quota |

## 23. Kịch bản nghiệp vụ đầu cuối

| Mã | Tiền điều kiện và thao tác | Kết quả chấp nhận; tình huống lỗi |
| --- | --- | --- |
| UC-01 Chat riêng | User active, đã chấp nhận policy và còn quota; tạo chat, gửi prompt, tiếp tục hội thoại, đổi tên, xóa. | Chỉ owner xem lịch sử; usage tăng sau lượt AI thành công; stream có `done`; sau xóa, chat không còn trong sidebar/Recent Activity. Prompt rỗng, PII hoặc hết quota nhận lỗi phù hợp. |
| UC-02 Agent với knowledge | Owner tạo Agent, upload knowledge hợp lệ, hỏi câu có/không có trong nguồn, sửa/xóa file. | Index cập nhật; câu trả lời dùng đoạn liên quan nếu có; không tuyên bố dựa trên nguồn khi truy xuất không phù hợp. File vượt giới hạn/hỏng có lỗi rõ và không làm mất nguồn đang dùng. |
| UC-03 Chia sẻ Agent | Owner chọn `use_only` hoặc `copy`; user khác tìm Agent và sử dụng. | `use_only` không trao quyền sở hữu knowledge; `copy` tạo Agent thuộc user nhận và chỉ sao chép file khi được cho phép. User không thể chỉnh sửa Agent gốc; thu hồi chia sẻ chặn lượt dùng mới. |
| UC-04 Xuất file | User trao đổi nội dung, chọn tạo Word/Excel/PDF hoặc export conversation; tải rồi xóa artifact. | File mở được và thuộc user; metadata/truy cập download nhất quán; không ghi đè nguồn. User khác dùng cùng ID bị từ chối; lỗi tạo file không trả link hỏng như thành công. |
| UC-05 Email draft | User yêu cầu soạn email nháp từ cuộc hội thoại. | Subject/body được lưu cho owner và có audit; không có hành động gửi email hoặc kết nối mailbox. Nội dung phải được user duyệt trước khi dùng ngoài hệ thống. |
| UC-06 Admin và support | User gửi ticket, admin xử lý; admin cập nhật School Knowledge và xem usage/safety events. | User chỉ xem ticket của mình; hành động admin được kiểm tra role/ghi audit; lỗi index có trạng thái thất bại để xử lý; safety event hết hạn được scheduler xóa. |

## 24. Cấu hình, phụ thuộc và điều kiện kiểm soát thay đổi

| Nhóm cấu hình | Giá trị/hành vi hiện có | Việc cần xác nhận trước production |
| --- | --- | --- |
| `AI_USAGE_PHASE`, `AI_*_TOKEN_LIMIT`, `AI_USAGE_PHASE_STARTED_AT` | Phase mặc định `testing`; mức 20M/10M token | Phase thật, override user, ngân sách và cảnh báo |
| `OPENAI_MODEL`, `OPENAI_IMAGE_MODEL`, `OPENAI_EMBEDDING_MODEL` | Model tách theo chat/ảnh/embedding | Entitlement, vùng dữ liệu, giá, chất lượng và fallback |
| `OPENAI_MODERATION_ENABLED` | Có thể bật/tắt; local safety fallback vẫn giới hạn | Xử lý khi API trả 403/timeout; người phê duyệt policy |
| `PDFTOPPM_BINARY`, `PDF_SCAN_MAX_PAGES` | PDF scan tối đa 10 trang mặc định | Binary, trang dài, timeout và thông báo người dùng |
| OCR và Playwright/Chromium | Phụ thuộc binary/runtime, có thể thiếu trên server | Kiểm thử cùng loại file/URL sẽ dùng trong pilot |
| `PII_EMAIL_DOMAIN_WHITELIST` | Mặc định `lsts.edu.vn` | Xác minh domain không bao gồm mailbox cá nhân cần bảo vệ |
| Storage và proxy | Private disks, SSE và upload phụ thuộc web server | Kiểm tra quyền file, backup, body limit, buffering và TLS |

**Kiểm soát thay đổi:** mọi thay đổi model, giới hạn dữ liệu, quyền chia sẻ, retention, PII policy hoặc integration phải có người đề xuất, đánh giá tác động đến quyền riêng tư/chi phí, test regression, phương án rollback và người phê duyệt được chỉ định. Cơ chế ticket, approver và version release: **TBD**.
