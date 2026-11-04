# Nhân Hòa Cloud VPS (`nhanhoa_cloud`)

[English](README.md) | Tiếng Việt

Module HostBill loại **Hosting** dùng API đại lý Nhân Hòa (`https://api.nhanhoa.com/`) để đặt, đồng bộ và gia hạn đơn Cloud VPS. HostBill không vận hành hypervisor; các thao tác quản trị VPS không được API hỗ trợ cần thực hiện tại portal Nhân Hòa.

## Dịch vụ và cấu hình

Mỗi dịch vụ HostBill tương ứng một đơn Cloud VPS. Module dùng `service_code` nhóm 3 để lấy gói, `template_code` để lấy mẫu hệ điều hành và yêu cầu thời hạn tối thiểu 3 tháng.

| Trường HostBill | Giá trị |
|---|---|
| Username | `auth-user` |
| Password | `auth-pwd` |
| Field 1 | `auth-id` (ID tài khoản đại lý) |
| Custom → Debug log | Ghi request/response vào `logs/nhanhoa_cloud.log` |

Trong cấu hình sản phẩm, đặt `vpscode`, `ostype` (0 Linux / 1 Windows), `ostemplate` và `vpsmonth`. Thông tin dịch vụ được lưu gồm mã đơn/Cloud, gói, hostname, địa chỉ IP, email và trạng thái.

## Nghiệp vụ và trạng thái

| Nghiệp vụ | API |
|---|---|
| Kiểm tra kết nối | `get_balance` |
| Đăng ký và đọc lại đơn | `register_cloud`, `list_cloud`, `get_infocloud` |
| Gia hạn | `renew_cloud` (cần `ord_id`, tối thiểu 3 tháng) |
| Đồng bộ trạng thái/hạn | `get_infocloud` |
| Danh sách gói/mẫu | `service_code`, `template_code` |

`register_cloud` chỉ trả trạng thái/thông báo, chưa trả ID; module dò đơn theo email đại lý rồi lưu `cloud_id`. Việc dò là best-effort, đơn vẫn được tạo nếu chưa tìm thấy ID ngay; có thể chạy **Sync status** sau. Trạng thái nội dung tiếng Việt được ánh xạ sang Active, Suspended, Terminated hoặc Pending khi nhận diện được.

## Giao diện và API client

Trang quản trị có trạng thái, gói, mã đơn, IP, hạn dùng, **Sync status**, **Renew** và liên kết portal. Khu vực client hiển thị tóm tắt và thông báo/link portal cho chức năng không được API hỗ trợ. Widget hiển thị thông tin đã lưu và các tra cứu có cache.

REST route trong `api/nhanhoa_cloud_apiroutes.json` yêu cầu xác thực và kiểm tra chủ sở hữu dịch vụ: `info`, `status`, `power`, `reinstall`, `console`. Chỉ trả các định danh được cho phép, không trả thông tin đăng nhập đại lý.

## Cài đặt

1. Chép module vào `includes/modules/Hosting/nhanhoa_cloud/` và kích hoạt trong **Settings → Modules**.
2. Tạo Server/App với `auth-user`, `auth-pwd`, `auth-id`; chạy **Test connection**.
3. Kết nối sản phẩm với app và cấu hình các lựa chọn sản phẩm nói trên.
4. Không cần cron; đồng bộ trạng thái theo yêu cầu.

## Giới hạn API

API Cloud VPS chỉ hỗ trợ `register_cloud`, `renew_cloud`, `list_cloud`, `get_infocloud` cùng các tra cứu dùng chung. Không có API suspend/unsuspend, terminate, đổi gói/mật khẩu, bật/tắt nguồn, cài lại, console hoặc SSO. Các thao tác đó cần thực hiện tại portal Nhân Hòa (`https://cloud.nhanhoa.com` hoặc `customer.nhanhoa.com`); trạng thái HostBill không xác nhận thao tác đã được thực thi ở nhà cung cấp.

## Bảo mật và hỗ trợ

Không chia sẻ thông tin đại lý, dữ liệu khách hàng hoặc log chưa lọc. Xem [hướng dẫn bảo mật](../SECURITY.md), [hỗ trợ](../SUPPORT.md) và [quy tắc thương hiệu](../BRAND.md).

## Phiên bản và giấy phép

Phiên bản module hiện tại: **1.1.1**. Xem [changelog](../CHANGELOG.md). Giấy phép MIT; © 2026 **HiTechCloud by Pho Tue SoftWare Solutions JSC**.
