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
- **Trạng thái:** Done
- **Chủ sở hữu:** Fullstack Developer (User)
- **Tóm tắt:** Mở rộng khả năng phân tích kiểu dữ liệu phức tạp bao gồm Generics, Union types và xử lý thừa kế.
- **Giả thuyết Lợi ích (Benefit Hypothesis):** Cho phép thư viện hỗ trợ các dự án PHP hiện đại sử dụng cấu trúc dữ liệu phức tạp (như Collection<T>, DTO kế thừa), tăng tính chính xác và độ phủ của tài liệu API được sinh ra.
- **Tiêu chí chấp nhận (Acceptance Criteria):**
  - Hỗ trợ cú pháp Generics trong PHPDoc (ví dụ: `ApiResponse<User[]>`).
  - Xử lý được Union types (`string|null`, `User|Admin`).
  - Có cơ chế Recursive Parsing để thu thập thuộc tính từ class cha và Trait.
  - Xử lý được tham chiếu vòng (Circular References).

### [Epic 3] Tích hợp CLI và Tối ưu hóa Hiệu năng
- **Trạng thái:** In Progress
- **Chủ sở hữu:** Fullstack Developer (User)
- **Tóm tắt:** Hoàn thiện công cụ dưới dạng CLI, hỗ trợ nhiều định dạng xuất bản và cơ chế bộ nhớ đệm (Caching).
- **Giả thuyết Lợi ích (Benefit Hypothesis):** Biến thư viện thành một công cụ dòng lệnh chuyên nghiệp dễ dàng tích hợp vào quy trình CI/CD, đồng thời đảm bảo tốc độ xử lý nhanh cho các dự án lớn.
- **Tiêu chí chấp nhận (Acceptance Criteria):**
  - Cung cấp lệnh `php-swag generate` dễ sử dụng.
  - Xuất ra định dạng YAML và JSON chuẩn OpenAPI 3.0/3.1.
  - Tích hợp cơ chế Caching dựa trên file hash để tăng tốc độ quét lần sau.
  - Có tài liệu hướng dẫn sử dụng (README) hoàn chỉnh cho cộng đồng.

### [Epic 4] Professional API Documentation & Advanced Controls
- **Trạng thái:** To Do
- **Chủ sở hữu:** Fullstack Developer (User)
- **Tóm tắt:** Mở rộng các tính năng lấy cảm hứng từ swaggo để hoàn thiện tài liệu API chuyên nghiệp.
- **Giả thuyết Lợi ích (Benefit Hypothesis):** Giúp tạo ra tài liệu OpenAPI đầy đủ thông tin nhất, hỗ trợ bảo mật, validation và các tùy chỉnh nâng cao, giúp frontend và các bên liên quan dễ dàng tích hợp.
- **Tiêu chí chấp nhận (Acceptance Criteria):**
  - Trích xuất tự động thông tin Global API (Title, Version, Host, etc.) từ PHPDoc.
  - Hỗ trợ định nghĩa Security và áp dụng cho từng endpoint.
  - Hỗ trợ đầy đủ các thẻ Validation cho thuộc tính và tham số.
  - Cho phép tùy chỉnh MIME Types và OpenAPI Extensions (x-).
  - Hỗ trợ alias @success, @failure cho tính rõ ràng.

## 2. Program Backlog (Features)

### Features cho [Epic 1] Core Engine
- [x] **[F1.1] File Scanner & Finder:** Tìm kiếm đệ quy tất cả các file .php trong các thư mục được cấu hình.
- [x] **[F1.2] AST Parser Integration:** Tích hợp `nikic/php-parser` để đọc cấu trúc code.
- [x] **[F1.3] Namespace Resolver:** Xác định chính xác FQCN dựa trên `namespace` và `use` statements.
- [x] **[F1.4] Basic DocBlock Collector:** Thu thập và phân tích các tag đơn giản (@route, @summary, @property).

### Features cho [Epic 2] Type System Pro
- [x] **[F2.1] Advanced Type Resolver:** Hỗ trợ các kiểu dữ liệu phức tạp (Union, Nullable, Arrays).
- [x] **[F2.2] Generics Support:** Phân tích cú pháp template cho các kiểu dữ liệu generic.
- [x] **[F2.3] Inheritance & Trait Merger:** Gộp các thuộc tính từ các class cha và traits.
- [x] **[F2.4] Schema Registry:** Quản lý tập trung các định nghĩa Model và xử lý tham chiếu vòng.
- [x] **[F2.5] Advanced Route Parameter Handling:** Hỗ trợ tag riêng biệt (@path, @query, @header, @cookie, @body) và tự động suy luận (Auto-inference).

### Features cho [Epic 3] Integration & CLI
- [x] **[F3.1] CLI Command Interface:** Cung cấp giao diện dòng lệnh cho người dùng.
- [x] **[F3.2] OpenAPI Spec Generator:** Chuyển đổi dữ liệu IR thành file chuẩn OpenAPI.
- [ ] **[F3.3] Performance Caching:** Lưu trữ kết quả phân tích để tăng tốc cho các lần chạy sau.
- [ ] **[F3.4] README & Documentation:** Hướng dẫn cộng đồng cách sử dụng và đóng góp.

### Features cho [Epic 4] Professional API Documentation
- [x] **[F4.1] Global API Metadata Discovery:** Tự động trích xuất @title, @version, @description, @contact.*, @license.*, và @host từ toàn bộ project.
- [ ] **[F4.2] Security & Authentication Support:** Định nghĩa @securityDefinitions (ApiKey/JWT) toàn cục và @security cho endpoint.
- [ ] **[F4.3] Comprehensive Schema Validation:** Hỗ trợ các tag @minimum, @maximum, @minLength, @maxLength, @pattern, @format, @example cho Model properties và Route parameters.
- [ ] **[F4.4] MIME Types & Response Alias:** Hỗ trợ @accept, @produce (mặc định application/json) và alias @success/@failure.
- [ ] **[F4.5] Advanced Operation Metadata:** Hỗ trợ @operationId, @deprecated và OpenAPI Extensions (x-).

## 3. Team Backlog (User Stories)

### Stories cho [F2.5] Advanced Route Parameter Handling
- [x] **[S2.5.1] Explicit Parameter Tags:** Hỗ trợ @path, @query, @header, @cookie với cú pháp chuyên sâu.
- [x] **[S2.5.2] Request Body Tag:** Hỗ trợ tag @body để định nghĩa body request một cách tường minh.
- [x] **[S2.5.3] Auto-inference from Signature:** Tự động nhận diện tham số path/query và body từ type-hint của method.
- [x] **[S2.5.4] Extra Metadata Parsing:** Trích xuất enum() và default() ngay từ chuỗi mô tả trong PHPDoc.

### Stories cho [F4.1] Global API Metadata
- [x] **[S4.1.1] Global DocBlock Scanner:** Cơ chế quét và tìm kiếm khối thông tin chung của API trong toàn bộ project.
- [x] **[S4.1.2] Info Object Mapping:** Ánh xạ các tag @title, @version, @description, @contact, @license vào Info Object của OpenAPI.
- [x] **[S4.1.3] Host & BasePath Support:** Xử lý tag @host để xác định URL cơ sở.

### Stories cho [F4.2] Security & Authentication
- [ ] **[S4.2.1] Security Definitions Parser:** Phân tích các định nghĩa bảo mật (API Key, Bearer JWT) từ PHPDoc.
- [ ] **[S4.2.2] Security Requirement Tag:** Áp dụng tag @security cho từng endpoint để chỉ định phương thức bảo mật cần thiết.

### Stories cho [F4.3] Comprehensive Schema Validation
- [ ] **[S4.3.1] Validation Tag Extraction:** Trích xuất các ràng buộc (minimum, maxLength, pattern, format, etc.) từ mô tả PHPDoc hoặc tag riêng biệt.
- [ ] **[S4.3.2] Validation Mapping to OpenAPI:** Chuyển đổi các ràng buộc kỹ thuật sang Schema Object tương ứng.
- [ ] **[S4.3.3] Example Tag Support:** Hỗ trợ tag @example để hiển thị dữ liệu mẫu trong UI.

### Stories cho [F4.4] MIME Types & Response Alias
- [ ] **[S4.4.1] MIME Type Tags (@accept, @produce):** Cho phép định nghĩa kiểu nội dung cho request/response.
- [ ] **[S4.4.2] Success/Failure Aliases:** Xử lý @success và @failure như các alias của @response để tăng tính trực quan.

### Stories cho [F4.5] Advanced Operation Metadata
- [ ] **[S4.5.1] Operation ID Support:** Cho phép đặt tên thủ công cho operation qua tag @operationId.
- [ ] **[S4.5.2] Deprecation Support:** Đánh dấu operation lỗi thời thông qua tag @deprecated.
- [ ] **[S4.5.3] x- Extension Support:** Hỗ trợ trích xuất và xuất các extension OpenAPI tùy chỉnh bắt đầu bằng "x-".
