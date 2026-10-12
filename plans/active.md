# Trạng thái công việc AI+

File này ghi **task đang thực hiện** và điểm bàn giao gần nhất. Cập nhật khi bắt đầu task, khi phạm vi hoặc trạng thái đổi, và trước khi kết thúc phiên. Không chép dữ liệu nhạy cảm, secret hoặc nội dung chat riêng vào đây.

## Task hiện tại

Chưa có task đang thực hiện được ghi trong file này.

Khi nhận một task mới, thay dòng trên bằng các mục sau:

- **Tên / liên kết task:**
- **Mục tiêu và tiêu chí hoàn thành:**
- **Phạm vi file / hành vi được phép đổi:**
- **Trạng thái:** `chưa bắt đầu` / `đang làm` / `chờ quyết định` / `hoàn thành`
- **Branch hoặc worktree:**
- **Kiểm chứng:** lệnh đã chạy, kết quả; ghi rõ phần chưa chạy và lý do
- **Rủi ro / quyết định còn chờ:** người cần quyết định, nếu đã xác định
- **Bước tiếp theo:** một hành động cụ thể để tiếp tục

Nếu có nhiều việc độc lập, chỉ ghi một task đang làm ở đây; các việc khác giữ trong hệ thống theo dõi công việc của dự án. Không coi roadmap hoặc mục `TBD` trong spec là task đã được phê duyệt.

## Bàn giao gần nhất

- **Ngày:** 2026-10-06
- **Việc:** Thiết lập tài liệu harness mục 2–5.
- **Kết quả:** Tạo mẫu trạng thái task, sổ quyết định, quy trình phiên, quy ước Git và bản đồ tài liệu.
- **Kiểm chứng:** Kiểm tra liên kết và định dạng Markdown; không chạy test ứng dụng vì không thay đổi code.
- **Còn chờ:** Chưa xác minh nguồn sinh của các bản DOCX hiện có; chưa có quyết định sản phẩm hoặc vận hành mới.
- **Bước tiếp theo:** Khi bắt đầu task phát triển tiếp theo, điền phần “Task hiện tại” và làm theo `docs/session-lifecycle.md`.
