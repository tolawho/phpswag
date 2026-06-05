# SAFe Backlog - PHP Swagger Generator Project

## 1. Portfolio Backlog (Epics)

### [Epic 1] Xây dựng Core Engine cho PHP Swagger Generator
- **Trạng thái:** Done
- **Chủ sở hữu:** Fullstack Developer (User)
- **Tóm tắt:** Xây dựng bộ khung cơ bản có khả năng quét mã nguồn PHP và trích xuất các thông tin route cơ bản thông qua AST.
- **Giả thuyết Lợi ích (Benefit Hypothesis):** Cung cấp một công cụ mã nguồn mở giúp lập trình viên PHP tự động hóa việc tạo tài liệu Swagger từ mã nguồn mà không cần cấu hình thủ công phức tạp, từ đó giảm sai sót và tiết kiệm thời gian bảo trì tài liệu.
- **Tiêu chí chấp nhận (Acceptance Criteria):**
  - Có khả năng quét thư mục và tìm kiếm file .php.
  - Phân tích được cấu trúc AST và giải quyết được Namespace/Use statements.
  - Nhận diện và trích xuất được các tag cơ bản: @route, @summary, @property.
  - Xuất ra cấu trúc dữ liệu trung gian (IR).

### [Epic 2] Nâng cấp Hệ thống Type System (Pro)
- **Trạng thái:** In Progress
- **Chủ sở hữu:** Fullstack Developer (User)
- **Tóm tắt:** Mở rộng khả năng phân tích kiểu dữ liệu phức tạp bao gồm Generics, Union types và xử lý thừa kế.
- **Giả thuyết Lợi ích (Benefit Hypothesis):** Cho phép thư viện hỗ trợ các dự án PHP hiện đại sử dụng cấu trúc dữ liệu phức tạp (như Collection<T>, DTO kế thừa), tăng tính chính xác và độ phủ của tài liệu API được sinh ra.
- **Tiêu chí chấp nhận (Acceptance Criteria):**
  - Hỗ trợ cú pháp Generics trong PHPDoc (ví dụ: ApiResponse<User[]>).
  - Xử lý được Union types (string|null, User|Admin).
  - Có cơ chế Recursive Parsing để thu thập thuộc tính từ class cha và Trait.
  - Xử lý được tham chiếu vòng (Circular References).

### [Epic 3] Tích hợp CLI và Tối ưu hóa Hiệu năng
- **Trạng thái:** To Do
- **Chủ sở hữu:** Fullstack Developer (User)
- **Tóm tắt:** Hoàn thiện công cụ dưới dạng CLI, hỗ trợ nhiều định dạng xuất bản và cơ chế bộ nhớ đệm (Caching).
- **Giả thuyết Lợi ích (Benefit Hypothesis):** Biến thư viện thành một công cụ dòng lệnh chuyên nghiệp dễ dàng tích hợp vào quy trình CI/CD, đồng thời đảm bảo tốc độ xử lý nhanh cho các dự án lớn.
- **Tiêu chí chấp nhận (Acceptance Criteria):**
  - Cung cấp lệnh php-swag generate dễ sử dụng.
  - Xuất ra định dạng YAML và JSON chuẩn OpenAPI 3.0/3.1.
  - Tích hợp cơ chế Caching dựa trên file hash để tăng tốc độ quét lần sau.
  - Có tài liệu hướng dẫn sử dụng (README) hoàn chỉnh cho cộng đồng.


## 2. Program Backlog (Features)

### Features cho [Epic 1] Core Engine
- [x] **[F1.1] File Scanner & Finder:** Tìm kiếm đệ quy tất cả các file .php trong các thư mục được cấu hình.
- [x] **[F1.2] AST Parser Integration:** Tích hợp nikic/php-parser để đọc cấu trúc code.
- [x] **[F1.3] Namespace Resolver:** Xác định chính xác FQCN (Fully Qualified Class Name) dựa trên namespace và use statements.
- [x] **[F1.4] Basic DocBlock Collector:** Thu thập và phân tích các tag đơn giản (@route, @summary, @property).

### Features cho [Epic 2] Type System Pro
- [x] **[F2.1] Advanced Type Resolver:** Hỗ trợ các kiểu dữ liệu phức tạp của PHP hiện đại.
- [ ] **[F2.2] Generics Support:** Phân tích cú pháp template cho các kiểu dữ liệu generic.
- [ ] **[F2.3] Inheritance & Trait Merger:** Gộp các thuộc tính từ các class cha và traits.
- [x] **[F2.4] Schema Registry:** Quản lý tập trung các định nghĩa Model để tránh trùng lặp và xử lý tham chiếu vòng.

### Features cho [Epic 3] Integration & CLI
- [ ] **[F3.1] CLI Command Interface:** Cung cấp giao diện dòng lệnh cho người dùng.
- [ ] **[F3.2] OpenAPI Spec Generator:** Chuyển đổi dữ liệu IR thành file chuẩn OpenAPI.
- [ ] **[F3.3] Performance Caching:** Lưu trữ kết quả phân tích để tăng tốc cho các lần chạy sau.
- [ ] **[F3.4] README & Documentation:** Hướng dẫn cộng đồng cách sử dụng và đóng góp.

## 3. Team Backlog (User Stories - Examples for PI-1)

### Stories cho [F2.1] Advanced Type Resolver
- [x] **[S2.1.1] Handle Nullable Types:** Nhận diện ?string, string|null và ánh xạ sang nullable: true trong OpenAPI.
- [x] **[S2.1.2] Array Type Support:** Hỗ trợ User[] và array<User>.

## 4. Ưu tiên hóa bằng WSJF (Weighted Shortest Job First)
(Đã cập nhật trạng thái các Feature hoàn thành)
