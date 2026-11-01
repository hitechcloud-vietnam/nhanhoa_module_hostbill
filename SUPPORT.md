# Hỗ trợ

## Phạm vi

Hỗ trợ repository tập trung vào cài đặt, cấu hình và hành vi của sáu module được liệt kê trong [README](README.md). Hỗ trợ không mặc nhiên bao gồm tài khoản/hoá đơn Nhân Hòa, tình trạng hạ tầng hay API của nhà cung cấp, lỗi lõi HostBill, cấu hình máy chủ PHP hoặc dịch vụ bên ngoài.

Với dịch vụ chưa được API đại lý hỗ trợ (ví dụ thao tác control panel, mailbox, CSR/DCV hoặc DNSSEC tùy module), cần xử lý tại portal/kênh hỗ trợ Nhân Hòa theo README module. Không coi thông báo hoặc trạng thái HostBill là xác nhận thao tác đã được thực thi tại nhà cung cấp.

## Trước khi gửi yêu cầu

1. Đọc README của module và kiểm tra mục giới hạn API, yêu cầu cấu hình và hướng dẫn xử lý sự cố.
2. Xác nhận module, sản phẩm, Server/App và mã dịch vụ đang dùng đúng cấu hình; kiểm tra kết quả Test connection nếu module có hỗ trợ.
3. Ghi nhận thời điểm xảy ra lỗi, múi giờ, thao tác ngay trước lỗi và kết quả mong đợi/thực tế.
4. Chỉ bật debug log trong thời gian chẩn đoán và rà soát/ẩn dữ liệu nhạy cảm trước khi chia sẻ.

## Mẫu thông tin yêu cầu

```text
Module và phiên bản:
HostBill và PHP version:
Môi trường: staging / production
Thời điểm và múi giờ:
Thao tác hoặc endpoint (không kèm token/secret):
Kết quả mong đợi:
Kết quả thực tế / thông báo lỗi đã ẩn dữ liệu:
Các bước tái hiện trên dịch vụ thử nghiệm:
Đã thử kiểm tra gì:
Ảnh hưởng tới khách hàng hoặc đơn hàng:
```

Không gửi mật khẩu, `auth-pwd`, API token, cookie/session, mã xác thực tên miền, CSR có private key, dữ liệu cá nhân không cần thiết, bản dump cơ sở dữ liệu hoặc log thô. Nếu cần đối chiếu với nhà cung cấp, gửi riêng qua kênh bảo mật của họ.

## Phân loại chuyển tiếp

- Lỗi trong luồng của module hoặc tài liệu: báo đơn vị quản lý repository qua kênh hỗ trợ chính thức của dự án.
- Đơn hàng, số dư, trạng thái dịch vụ, dữ liệu portal hoặc API Nhân Hòa: liên hệ Nhân Hòa; cung cấp mã yêu cầu/đơn đã được phép chia sẻ.
- Lỗi HostBill core, cron nền tảng hoặc API User API: liên hệ nhà cung cấp HostBill.
- Nghi vấn lỗ hổng: làm theo [SECURITY.md](SECURITY.md), không mở issue công khai.

Repository hiện không công bố địa chỉ email, SLA hoặc thời gian phản hồi. Không giả định yêu cầu đã được tiếp nhận cho tới khi nhận xác nhận qua kênh của đơn vị duy trì.