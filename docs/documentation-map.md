# Bản đồ tài liệu AI+

Tài liệu trong repo có các vai trò khác nhau. Khi thông tin xung đột, xác định **loại thông tin** trước khi chọn nguồn; không tự biến một mục kế hoạch thành chức năng đã có.

| Loại thông tin | Nguồn cần đọc/cập nhật | Lưu ý |
|---|---|---|
| Quy tắc làm việc của coding agent | `AGENTS.md`, sau đó `CLAUDE.md` | `CLAUDE.md` chứa bối cảnh và ràng buộc kiến trúc đã chốt. |
| Yêu cầu và phạm vi sản phẩm | `PROJECT_SPEC.md` và `PROJECT_SPEC_EN.md` | Hai bản Markdown là cặp đặc tả làm việc; thay đổi phạm vi đã phê duyệt phải cập nhật cả hai. |
| Hành vi đang được cài đặt | Mã nguồn, cấu hình, migration và test | Spec mô tả yêu cầu; code phản ánh hiện trạng, nhưng vẫn cần kiểm chứng. Ghi khác biệt thành gap. |
| Chi tiết tích hợp OpenAI | `docs/openai-integration.md` | Đối chiếu với code và spec khi thay đổi hành vi. |
| Điều kiện và bước triển khai | `docs/DEPLOYMENT_CHECKLIST_VI.md` | Checklist vận hành, không phải bằng chứng đã triển khai. |
| Trạng thái task và bàn giao | `plans/active.md`, `docs/session-lifecycle.md` | Chỉ ghi việc đang làm và kiểm chứng thực tế. |
| Quyết định mới đã chốt | `docs/decisions/` | Cần người quyết định và nguồn xác nhận. |

## Các bản DOCX hiện có

Repo đang có `PROJECT_SPEC.docx`, `PROJECT_SPEC_EN.docx` và các file `docs/AI_Plus_Project_Specification*.docx`. **Chưa xác minh** file nào được sinh từ Markdown nào, cũng chưa xác minh chúng còn đồng bộ. Vì vậy, không dùng một bản DOCX để tự ghi đè spec Markdown hoặc tuyên bố nó là bản chuẩn.

Với các bản xuất bản sau này: biên tập yêu cầu trong cặp Markdown trước, đối chiếu hai ngôn ngữ, rồi tạo DOCX từ nội dung đã duyệt; ghi rõ nguồn, ngày tạo và phiên bản. Chỉ sau khi đối chiếu nội dung và xác nhận quan hệ nguồn–bản xuất mới đánh dấu một DOCX hiện có là artifact được sinh từ Markdown. Không xóa hay thay thế các DOCX cũ chỉ vì có bản Markdown tương ứng.
