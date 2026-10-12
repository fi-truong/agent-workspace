# Quy trình phiên làm việc của agent

Quy trình này dùng cho các task có sửa repository. `AGENTS.md` và `CLAUDE.md` vẫn là hướng dẫn chung; file này mô tả cách bắt đầu, kiểm chứng và bàn giao một phiên cụ thể.

## Bắt đầu

1. Đọc `AGENTS.md`, `CLAUDE.md`, phần liên quan trong spec và `plans/active.md`. Nếu có `.ai/rules/`, đọc index và các rule khớp phạm vi.
2. Kiểm tra `git status` và branch/worktree hiện tại. Xác định thay đổi sẵn có thuộc người dùng hoặc task khác; giữ nguyên chúng.
3. Xác định **một task** với mục tiêu, tiêu chí hoàn thành và phạm vi file rõ ràng. Cập nhật phần “Task hiện tại” trong `plans/active.md`.
4. Với thay đổi độc lập, áp dụng quy ước cô lập trong `docs/git-isolation.md` trước khi sửa code.

## Trong phiên

1. Chỉ thay đổi phạm vi đã thống nhất. Nếu phát hiện quyết định `TBD` hoặc xung đột giữa spec, code và yêu cầu, ghi vào trạng thái task và hỏi người dùng trước khi thực hiện thay đổi có hệ quả.
2. Ghi lại kiểm chứng thực sự đã chạy và kết quả trong `plans/active.md`. Dùng test/check phù hợp với khu vực thay đổi theo `AGENTS.md` và script sẵn có của repo.
3. Nếu task kéo dài qua nhiều phiên, cập nhật trạng thái và bước tiếp theo trước khi rời phiên.

## Kết thúc và bàn giao

1. Kiểm tra diff và `git status` để phát hiện file ngoài phạm vi hoặc thay đổi chưa được nhận diện.
2. Chạy kiểm chứng cần thiết; nêu rõ lệnh không chạy được và lý do. Không tuyên bố đã kiểm chứng production nếu chưa thực hiện tại môi trường đó.
3. Cập nhật `plans/active.md`: kết quả, file đổi, kiểm chứng, rủi ro/quyết định còn chờ và bước tiếp theo. Khi task hoàn thành, xóa nội dung “Task hiện tại” và giữ một bản tóm tắt trong “Bàn giao gần nhất”.
4. Chỉ ghi quyết định đã chốt vào `docs/decisions/`; cập nhật spec song ngữ khi phạm vi sản phẩm được phê duyệt thay đổi.
5. Báo lại người dùng theo mục “Completion and handoff” trong `AGENTS.md`.
