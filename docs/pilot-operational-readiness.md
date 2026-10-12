# AI+ — Các quyết định vận hành cần chốt trước pilot

**Trạng thái tài liệu:** Danh sách theo dõi quyết định; không xác nhận pilot đã sẵn sàng hoặc mục nào đã được phê duyệt. `TBD` nghĩa là chưa có quyết định/nguồn xác nhận được ghi trong các tài liệu đã đối chiếu. Không suy ra người phụ trách hay thời hạn từ chức danh, đơn vị chủ trì hoặc checklist.

**Nguồn đối chiếu:** [Đặc tả tiếng Việt](../PROJECT_SPEC.md) (mục 12, 14–21), [đặc tả tiếng Anh](../PROJECT_SPEC_EN.md) (mục 12, 14–21), [sổ quyết định](decisions/README.md) và [checklist triển khai](DEPLOYMENT_CHECKLIST_VI.md). Khi một quyết định được chốt, lưu nguồn xác nhận trong `docs/decisions/`, cập nhật hai bản spec nếu phạm vi sản phẩm thay đổi, rồi bổ sung bằng chứng vào file này. Đánh dấu hoàn tất chỉ khi có bằng chứng thực tế; một ô `decision needed` được điền chưa đủ để xác nhận vận hành đã hoạt động.

## 1. Trách nhiệm và quyền phê duyệt

- **Current state:** CIEC được ghi là đơn vị chủ trì. Product owner, data owner, governance cuối cùng và người phê duyệt thay đổi chưa được chốt. Spec yêu cầu thay đổi model, giới hạn dữ liệu, quyền chia sẻ, retention, PII policy hoặc integration có đánh giá tác động, regression test, kế hoạch rollback và approver được chỉ định.
- **Decision needed:** Chỉ định product owner, data owner, người phê duyệt thay đổi và phạm vi quyền của từng vai trò; chốt cách ghi nhận phê duyệt.
- **Decision owner:** TBD.
- **Due date:** TBD.
- **Evidence of completion:** RACI/quyền được phê duyệt và bản ghi quyết định có tên người nhận trách nhiệm, phạm vi quyền, nguồn xác nhận; cập nhật hai bản spec nếu cần.
- **Risk if unresolved:** Không rõ ai được phép chốt chính sách dữ liệu, thay đổi phạm vi và quyết định go-live.

## 2. Chỉ số và tiêu chí thành công của pilot

- **Current state:** Hai bản spec yêu cầu xác định chỉ số thành công trước pilot nhưng owner, cách đo và ngưỡng mục tiêu đều `TBD`. My Usage/quota và luồng support ticket được mô tả là hiện có; sự tồn tại của chúng chưa chứng minh các chỉ số pilot đã được định nghĩa hay đo kiểm.
- **Decision needed:** Chốt định nghĩa, cách tính, kỳ đo, mục tiêu và cách phân tách tối thiểu cho:
  - Số người dùng được kích hoạt và số người dùng hoạt động (bao gồm weekly active users).
  - Tỷ lệ chat hoàn tất và tỷ lệ export hoàn tất; định nghĩa rõ mẫu số và trường hợp lỗi.
  - Số lần PII bị chặn đúng/sai (true/false blocks) và cách kiểm chứng bằng mẫu an toàn.
  - Token và chi phí API so với ngân sách đã duyệt.
  - Số support ticket và thời gian xử lý/đóng ticket.
- **Decision owner:** TBD.
- **Due date:** TBD.
- **Evidence of completion:** Bộ định nghĩa chỉ số và ngưỡng mục tiêu được phê duyệt; báo cáo thử trên dữ liệu pilot/test cho thấy từng chỉ số có thể tính và kiểm tra lại.
- **Risk if unresolved:** Không thể kết luận pilot đạt mục tiêu hay phát hiện sớm xu hướng lỗi, chặn PII sai và chi phí vượt dự kiến.

## 3. Nguồn đo, cảnh báo và người xử lý

- **Current state:** Spec nêu yêu cầu theo dõi lỗi, latency, failed jobs, disk, usage và chi phí API nhưng dashboard, cảnh báo và người trực vẫn `TBD`. Checklist yêu cầu theo dõi log, failed jobs, API errors, token usage và phản hồi người dùng sau go-live; chưa có bằng chứng thiết lập cảnh báo hoặc phân công nhận/xử lý.
- **Decision needed:** Với từng chỉ số ở mục 2 và từng tín hiệu vận hành (lỗi, độ trễ, failed jobs, dung lượng, provider/API, usage/cost), chốt nguồn dữ liệu, tần suất đo, ngưỡng cảnh báo, kênh gửi, người nhận, người trực xử lý, thời gian phản hồi và cách chuyển cấp. Xác định cách tránh đưa PII/raw prompt/API key vào log và cảnh báo.
- **Decision owner:** TBD.
- **Due date:** TBD.
- **Evidence of completion:** Bảng mapping chỉ số → nguồn đo → ngưỡng → kênh → người nhận/người trực được duyệt; thử một cảnh báo và lưu kết quả nhận, phản hồi, chuyển cấp.
- **Risk if unresolved:** Sự cố, lỗi dữ liệu hoặc chi phí tăng có thể không được phát hiện và xử lý đúng lúc.

## 4. Ngân sách API và xử lý vượt ngưỡng

- **Current state:** Quota token theo user/phase đã được mô tả trong spec, nhưng quota token không phải giới hạn chi phí tiền tệ hay chi phí tạo ảnh. Ngân sách production/pilot, cost cap và cảnh báo chi phí đều `TBD`.
- **Decision needed:** Chốt ngân sách pilot, phạm vi chi phí được tính (chat, ảnh, embedding và phần liên quan), ngưỡng cảnh báo, cost limit, nguồn đối soát, người được quyền quyết định tiếp tục/tạm dừng và quy trình khi chạm hoặc vượt ngưỡng. Chốt cách thông báo người dùng và ghi nhận ngoại lệ nếu có.
- **Decision owner:** TBD.
- **Due date:** TBD.
- **Evidence of completion:** Quyết định ngân sách và quy trình vượt ngưỡng được duyệt; bằng chứng cấu hình/đối soát/cảnh báo và một lần kiểm tra luồng xử lý theo quyết định đó.
- **Risk if unresolved:** Chi phí có thể vượt mức chấp nhận được dù quota token của từng user chưa hết.

## 5. Backup và restore

- **Current state:** Spec yêu cầu backup database và private files, diễn tập restore và kiểm chứng tính toàn vẹn. RPO, RTO, lịch backup và retention là `TBD`. Checklist có mục thiết lập backup và kiểm tra restore trước go-live; dấu kiểm trong checklist không thay thế biên bản diễn tập.
- **Decision needed:** Chốt RPO, RTO, lịch và phạm vi backup (database cùng private files), nơi lưu/bảo vệ bản backup, thời hạn retention, người thực hiện, cách kiểm tra toàn vẹn và lịch diễn tập restore; xác định lần diễn tập cần hoàn tất trước pilot.
- **Decision owner:** TBD.
- **Due date:** TBD.
- **Evidence of completion:** Chính sách backup được duyệt; bản ghi backup thành công; biên bản restore tại môi trường phù hợp với thời điểm, dữ liệu kiểm tra, kết quả toàn vẹn và thời gian khôi phục thực tế.
- **Risk if unresolved:** Mất dữ liệu hoặc gián đoạn dịch vụ có thể vượt mức chấp nhận được mà chưa có khả năng phục hồi đã được chứng minh.

## 6. Retention và xóa dữ liệu theo loại

- **Current state:** Spec yêu cầu chính sách riêng cho từng loại dữ liệu, cleanup cả database lẫn file/object storage và xử lý yêu cầu xuất/xóa hợp lệ. Hiện chỉ `ai_safety_events` có thời hạn prune 180 ngày được ghi rõ; thời hạn này **không áp dụng mặc định** cho các loại dưới đây.
- **Decision needed:** Chốt thời hạn lưu, thời điểm bắt đầu tính, điều kiện xóa, phạm vi xóa (DB, file/index, bản sao lưu khi phù hợp), ngoại lệ pháp lý và cách xác minh xóa cho từng loại:

  | Loại dữ liệu | Hiện trạng trong spec | Quyết định còn thiếu |
  |---|---|---|
  | Conversation và messages | Có dữ liệu chat/lịch sử; retention `TBD` | Thời hạn, quyền yêu cầu xóa, quan hệ với usage/audit và bản sao lưu. |
  | Attachment | File gắn với chat/knowledge; retention `TBD` | Thời hạn, xóa file private cùng metadata và xử lý khi chat bị xóa. |
  | Knowledge | Agent/School Knowledge có nguồn, file và index; retention `TBD` | Thời hạn, xóa nguồn/file/chunk/index và quyền giữ lại khi chia sẻ. |
  | Artifact | File tạo ra lưu riêng; chat bị xóa không có nghĩa artifact cũng bị xóa; retention `TBD` | Thời hạn, xóa file/metadata và quan hệ với conversation. |
  | Ảnh | Ảnh có thể thuộc chat hoặc nội dung tạo bởi hệ thống; retention `TBD` | Thời hạn, quan hệ với conversation/artifact, xóa file/metadata và các bản sao liên quan. |
  | Email nháp | Email nháp lưu subject/body, liên kết user/conversation; retention `TBD` | Thời hạn, quyền yêu cầu xóa, quan hệ với conversation và cách xóa dữ liệu liên quan. |
  | Audit | Admin audit log được nêu trong spec; retention `TBD` | Thời hạn, quyền truy cập, ngoại lệ giữ bằng chứng và quy trình xóa. |
  | Usage log | Dùng cho quota/hoạt động; retention `TBD` | Thời hạn, mức chi tiết cần giữ, đối soát chi phí và quy trình xóa. |
  | Bản sao lưu | Backup database và private files được yêu cầu; retention `TBD` | Thời hạn, nơi lưu/bảo vệ, mã hóa, cách hết hạn/xóa và xử lý dữ liệu bị xóa trong bản sao lưu. |

- **Decision owner:** TBD.
- **Due date:** TBD.
- **Evidence of completion:** Chính sách theo từng loại được data owner/người có thẩm quyền phê duyệt; ma trận dữ liệu–thời hạn–cách xóa được đối chiếu với hệ thống và kết quả kiểm thử xóa/khôi phục phù hợp.
- **Risk if unresolved:** Dữ liệu nhạy cảm có thể bị giữ quá lâu, xóa không đầy đủ hoặc không còn đủ dữ liệu để điều tra/đối soát theo chính sách được duyệt.

## 7. Phê duyệt phát hành, rollback và điều kiện go-live

- **Current state:** Spec đưa ra **điều kiện nghiệm thu đề xuất**: không còn lỗi P0/P1, truy cập chéo bị từ chối, backup/restore được kiểm chứng, product/data owner ký duyệt UAT, policy dữ liệu và go-live. Ngưỡng định lượng, approver, ticket và versioning còn `TBD`. Checklist có bước test, smoke test, backup trước deploy và giữ release cũ để rollback; chưa phải bằng chứng đã hoàn tất.
- **Decision needed:** Chốt người phê duyệt release/go-live, người chịu trách nhiệm rollback, hồ sơ release cần nộp, điều kiện đạt/không đạt cho pilot, ngưỡng lỗi và hiệu năng, điều kiện dừng hoặc rollback, cách thông báo và xác nhận sau rollback. Phân biệt quyết định cho pilot với production chính thức.
- **Decision owner:** TBD.
- **Due date:** TBD.
- **Evidence of completion:** Tiêu chí go-live và rollback runbook được phê duyệt; hồ sơ test/UAT/privacy/backup-restore/smoke test có kết quả; quyết định phát hành ghi tên người phê duyệt và phương án rollback được kiểm tra.
- **Risk if unresolved:** Có thể đưa pilot vào dùng khi chưa rõ ai chấp nhận rủi ro, khi nào phải dừng, hoặc ai khôi phục hệ thống nếu phát hành lỗi.

## Cách cập nhật

Khi một mục được chốt, thay `TBD` bằng thông tin có nguồn xác nhận và liên kết tới quyết định trong [sổ quyết định](decisions/README.md). Ghi ngày hoàn tất và bằng chứng cụ thể ngay tại mục tương ứng. Nếu việc chốt thay đổi yêu cầu sản phẩm hoặc điều kiện nghiệm thu, đồng bộ [spec tiếng Việt](../PROJECT_SPEC.md) và [spec tiếng Anh](../PROJECT_SPEC_EN.md). Với việc triển khai thực tế, đánh dấu [checklist triển khai](DEPLOYMENT_CHECKLIST_VI.md) chỉ sau khi bước đó đã được kiểm chứng.
