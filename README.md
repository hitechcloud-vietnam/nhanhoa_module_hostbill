# Bộ tích hợp Nhân Hòa cho HostBill

[English](README.en.md) | Tiếng Việt

Bộ module kết nối HostBill với các dịch vụ của Nhân Hòa, được phát hành dưới tên **HiTechCloud Nhan Hoa Suite**. Các module giao tiếp với API đại lý của nhà cung cấp; HostBill không thay thế control panel, hệ thống DNS authoritative, nền tảng email, hạ tầng VPS hoặc quy trình nghiệp vụ của Nhân Hòa.

## Danh mục module

| Module | Phạm vi | Tài liệu triển khai |
|---|---|---|
| `nhanhoa_cloud` | Đặt hàng, đồng bộ và gia hạn Cloud VPS | [English](nhanhoa_cloud/README.md) · [Tiếng Việt](nhanhoa_cloud/README.vi.md) · [MIT](nhanhoa_cloud/LICENSE) |
| `nhanhoa_dns` | Quản lý bản ghi ZoneDNS | [English](nhanhoa_dns/README.md) · [Tiếng Việt](nhanhoa_dns/README.vi.md) · [MIT](nhanhoa_dns/LICENSE) |
| `nhanhoa_domain` | Đăng ký và quản lý tên miền, thông tin chủ thể và DNS | [English](nhanhoa_domain/README.md) · [Tiếng Việt](nhanhoa_domain/README.vi.md) · [MIT](nhanhoa_domain/LICENSE) |
| `nhanhoa_email` | Đặt hàng, đồng bộ và gia hạn Email Hosting | [English](nhanhoa_email/README.md) · [Tiếng Việt](nhanhoa_email/README.vi.md) · [MIT](nhanhoa_email/LICENSE) |
| `nhanhoa_hosting` | Đặt hàng, đồng bộ và gia hạn Hosting | [English](nhanhoa_hosting/README.md) · [Tiếng Việt](nhanhoa_hosting/README.vi.md) · [MIT](nhanhoa_hosting/LICENSE) |
| `nhanhoa_ssl` | Đặt hàng, đồng bộ và gia hạn chứng chỉ SSL | [English](nhanhoa_ssl/README.md) · [Tiếng Việt](nhanhoa_ssl/README.vi.md) · [MIT](nhanhoa_ssl/LICENSE) |

Mỗi module có hướng dẫn cài đặt, cấu hình API, giới hạn dịch vụ và giấy phép riêng. Thực hiện theo README của đúng module; không suy luận rằng các module có cùng danh mục HostBill, cùng trường cấu hình hoặc cùng khả năng của API.

## Bắt đầu

1. Xác nhận phiên bản HostBill, PHP và yêu cầu tương thích của môi trường đang sử dụng. Tài liệu hiện tại không tuyên bố một ma trận phiên bản đã được kiểm thử cho mọi bản HostBill/PHP.
2. Sao lưu mã nguồn HostBill và cơ sở dữ liệu trước khi triển khai. Thử cài đặt/nâng cấp trên môi trường staging trước khi áp dụng cho dịch vụ đang hoạt động.
3. Chép module vào đường dẫn được README của module chỉ định, kích hoạt module trong HostBill và khai báo thông tin kết nối đại lý trong Server/App.
4. Dùng chức năng **Test connection** nếu có; sau đó cấu hình sản phẩm và kiểm tra một luồng nghiệp vụ bằng tài khoản/dịch vụ thử nghiệm.
5. Xác nhận cron của HostBill đang chạy đối với module Email Hosting; những module khác chỉ cần cron nếu README phiên bản đó nêu rõ.
6. Ghi lại cách đồng bộ, gia hạn và xử lý thủ công tại portal Nhân Hòa trước khi mở bán.

Một số thao tác được API đại lý hỗ trợ bất đồng bộ hoặc không hỗ trợ. Trạng thái đơn hàng cần được đối chiếu với portal Nhân Hòa; trạng thái trong HostBill không phải bằng chứng rằng một thao tác không được API hỗ trợ đã thực hiện ở hệ thống nhà cung cấp.

## Tài liệu dùng chung

- [Lịch sử phát hành](CHANGELOG.md)
- [Thương hiệu và quy tắc ghi nhận](BRAND.md) · [English](BRAND.en.md)
- [Hướng dẫn bảo mật và báo cáo lỗ hổng](SECURITY.md) · [English](SECURITY.en.md)
- [Hỗ trợ và thông tin cần cung cấp](SUPPORT.md) · [English](SUPPORT.en.md)
- [Giấy phép MIT của repository](LICENSE)

## Đóng gói và phát hành

Mỗi module được phát hành độc lập dưới dạng ZIP trên GitHub Releases. Bên trong ZIP đã có đường dẫn cài đặt đầy đủ, vì vậy hãy giải nén từ thư mục gốc HostBill:

```bash
unzip nhanhoa_cloud-1.1.1.zip -d /path/to/hostbill
```

Các file sẽ vào `includes/modules/Hosting/nhanhoa_cloud/`. Riêng `nhanhoa_domain` được đặt trong `includes/modules/Domain/nhanhoa_domain/`. Sau đó kích hoạt module trong HostBill như hướng dẫn của từng module.

Tag theo mẫu `nhanhoa_<module>-v<version>` sẽ tự chạy workflow tạo ZIP, checksum SHA-256 và GitHub Release chỉ cho module tương ứng. Có thể chạy workflow **Publish module packages** thủ công trong tab Actions để phát hành một module/version trên commit được chọn. Danh sách module và quy tắc thư mục nằm trong [`scripts/package-module.sh`](scripts/package-module.sh) và [workflow](.github/workflows/publish-modules.yml).

## Thương hiệu và phạm vi hỗ trợ

**HiTechCloud Nhan Hoa Suite** là tên bộ tích hợp. Nhân Hòa/Nhan Hoa và HostBill là tên/nhãn hiệu của các chủ thể tương ứng; việc nhắc tên chỉ nhằm nhận diện dịch vụ tương thích. Không tài liệu nào ở đây khẳng định sự chứng thực, sở hữu hoặc quan hệ đối tác chính thức nếu chưa có xác nhận riêng.

Hỗ trợ mã nguồn tích hợp được giới hạn ở hành vi của các module trong repository. Tài khoản đại lý, hóa đơn, dịch vụ thực tế, sự cố API hoặc thao tác trên portal cần được xác minh với Nhân Hòa; lỗi nền tảng HostBill cần được chuyển tới đơn vị cung cấp HostBill.

## Giấy phép

Repository và từng module được cung cấp theo MIT License. Xem [LICENSE](LICENSE) và LICENSE trong thư mục module để biết đầy đủ điều khoản và thông báo bản quyền áp dụng.
