# Bảo mật

[Tiếng Việt](SECURITY.md) | [English](SECURITY.en.md)

Tài liệu này đưa ra cách cấu hình và báo cáo vấn đề bảo mật cho HiTechCloud Nhan Hoa Suite. Nó không phải cam kết rằng tích hợp, HostBill, máy chủ hay API của bên thứ ba đã được kiểm thử bảo mật độc lập.

## Cấu hình an toàn

- Chỉ dùng kết nối HTTPS tới API/portal được nhà cung cấp công bố. Không vô hiệu hóa xác minh chứng chỉ TLS để xử lý lỗi kết nối.
- Lưu `auth-user`, `auth-pwd`, `auth-id` trong trường kết nối Server/App được HostBill quản lý. Không đưa thông tin xác thực vào mã nguồn, repository, URL, ticket công khai, ảnh chụp hoặc log chia sẻ.
- Dùng tài khoản đại lý và quyền tối thiểu cần thiết; giới hạn quyền truy cập quản trị HostBill, máy chủ và cơ sở dữ liệu theo trách nhiệm công việc.
- Chỉ bật debug log trong khoảng thời gian cần chẩn đoán. Dù trường mật khẩu được che theo cơ chế của module, log có thể chứa domain, mã đơn, dữ liệu phản hồi hoặc thông tin cá nhân. Hạn chế quyền đọc, đặt thời hạn lưu giữ và xóa log an toàn khi hoàn tất.
- API transport có cache đọc ngắn hạn ở một số module. Bảo vệ thư mục tạm của hệ điều hành bằng quyền truy cập phù hợp và không chia sẻ cache/log chứa dữ liệu dịch vụ.
- Sao lưu cơ sở dữ liệu HostBill và các thiết lập cần thiết; mã hóa bản sao lưu, giới hạn người truy cập và kiểm tra khả năng khôi phục. Không lưu trữ dữ liệu xác thực trong bản dump dùng để gửi hỗ trợ.
- Các REST route được khai báo trong module yêu cầu xác thực và kiểm tra quyền sở hữu dịch vụ ở handler. Không bỏ qua cơ chế xác thực HostBill hoặc tự xuất bản handler ra ngoài nền tảng User API.
- Sau khi cập nhật module, xác nhận lại quyền file/thư mục, cấu hình Server/App, route manifest và cron có liên quan. Thử trên staging trước khi xử lý đơn thật.

## Dữ liệu cần bảo vệ

Tùy module, dữ liệu có thể bao gồm thông tin đăng nhập API, tên miền, thông tin chủ thể đăng ký, mã đơn, địa chỉ IP, trạng thái dịch vụ, dữ liệu DNS, CSR/SAN hoặc thông tin khách hàng. Chỉ thu thập/chia sẻ phần tối thiểu để xử lý sự cố; tuân thủ chính sách lưu giữ dữ liệu và nghĩa vụ pháp lý áp dụng cho đơn vị vận hành.

## Báo cáo lỗ hổng

Không đăng công khai thông tin khai thác, dữ liệu khách hàng, thông tin xác thực hoặc bước tái hiện có thể gây ảnh hưởng lên hệ thống thật. Hãy gửi báo cáo riêng cho đơn vị quản lý repository qua kênh bảo mật đã được đơn vị đó công bố. Nếu chưa thấy kênh tiếp nhận, liên hệ riêng quản trị viên repository để xin đầu mối phù hợp; không đính kèm bí mật vào issue/PR công khai.

Một báo cáo hữu ích nên nêu module/phiên bản, điều kiện ảnh hưởng, tác động, bước tái hiện tối thiểu trên môi trường được phép, log đã loại bỏ dữ liệu nhạy cảm và cách liên hệ phản hồi. Không thử nghiệm trên tài khoản, API hoặc dịch vụ của bên thứ ba nếu chưa có quyền rõ ràng. Lỗ hổng thuộc HostBill hoặc Nhân Hòa cần được báo cho chủ thể tương ứng qua kênh riêng của họ.

## Phạm vi

Phạm vi của repository là mã tích hợp và tài liệu đi kèm. Hạ tầng HostBill, máy chủ PHP, API/portal Nhân Hòa, thư viện bên thứ ba và cấu hình triển khai thuộc phạm vi quản lý riêng; việc tích hợp không thay thế quy trình vá lỗi và giám sát của các hệ thống đó.