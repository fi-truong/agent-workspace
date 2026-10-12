# Sổ quyết định AI+

Ghi tại đây **quyết định mới đã được người có thẩm quyền chốt** và có tác động lâu dài đến sản phẩm, kiến trúc, dữ liệu hoặc vận hành. Những quyết định đã có trong `CLAUDE.md` và hai bản `PROJECT_SPEC` vẫn có hiệu lực; không sao chép chúng thành quyết định mới. Tài liệu này không tự cấp quyền triển khai một mục đang `TBD`.

## Trạng thái

Chưa có quyết định mới nào được ghi tại thư mục này.

## Cách ghi

Tạo một file Markdown cho mỗi quyết định, tên dạng `YYYY-MM-DD-ten-ngan.md`. Mỗi file cần có:

```markdown
# Tên quyết định

- Ngày:
- Trạng thái: Đã chốt / Thay thế
- Người quyết định: (chỉ ghi khi đã xác nhận)
- Nguồn xác nhận: (liên kết tài liệu, ticket hoặc biên bản)
- Bối cảnh và phạm vi:
- Quyết định:
- Lý do và tác động:
- Tài liệu cần cập nhật:
- Quyết định bị thay thế: (nếu có)
```

Không điền người phê duyệt, deadline hay kết quả theo suy đoán. Nếu chưa được chốt, ghi câu hỏi và người cần quyết định vào `plans/active.md`; không đặt nó trong sổ này với trạng thái “Đã chốt”. Khi quyết định làm đổi phạm vi sản phẩm, cập nhật đồng bộ `PROJECT_SPEC.md` và `PROJECT_SPEC_EN.md` theo quy tắc hiện có.
