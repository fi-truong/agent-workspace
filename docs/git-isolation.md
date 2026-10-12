# Quy ước branch và worktree cho agent

Mỗi thay đổi độc lập nên có branch riêng, ưu tiên worktree riêng khi có nhiều task hoặc agent cùng làm. Mục tiêu là giữ `main` dễ kiểm tra và tránh sửa lẫn thay đổi đang dở của người khác.

## Trước khi sửa

1. Xem `git status`, branch hiện tại và danh sách worktree. Xác định file nào đã sửa/chưa theo dõi và ai đang dùng checkout đó.
2. Nếu đã có worktree phù hợp, không còn task/process dùng nó và các thay đổi trong đó đã được tính đến, dùng lại worktree ấy. Nếu cần cô lập thêm, tạo worktree mới bằng công cụ quản lý worktree của Codex khi khả dụng.
3. Đặt branch mới theo mẫu `codex/<ten-task-ngan>`, trừ khi người dùng yêu cầu tên khác. Không chuyển branch hoặc di chuyển thay đổi dở dang của người khác để lấy một checkout “sạch”.
4. Nếu checkout hiện tại có thay đổi ngoài phạm vi, giữ nguyên chúng. Nếu không có checkout phù hợp hoặc việc tạo worktree bị chặn, báo rõ tình trạng trước khi sửa file trùng phạm vi.

## Khi làm và khi kết thúc

- Giữ diff trong phạm vi task; không gộp refactor không liên quan.
- Kiểm tra `git diff` và `git status` trước khi commit hoặc bàn giao. Chỉ stage/commit file thuộc task khi được yêu cầu hoặc khi đó là bước bình thường của workflow đã được giao.
- Không xóa, reset, stash, ghi đè hoặc archive worktree đang chứa công việc chưa được tính đến. Không merge vào `main` hay deploy khi task chưa cho phép.
- Ghi branch/worktree và trạng thái thay đổi còn lại vào `plans/active.md` để phiên sau tiếp tục đúng chỗ.
