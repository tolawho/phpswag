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
- **Trạng thái:** Done
- **Chủ sở hữu:** Fullstack Developer (User)
- **Tóm tắt:** Hoàn thiện công cụ dưới dạng CLI, hỗ trợ nhiều định dạng xuất bản và cơ chế bộ nhớ đệm (Caching).
- **Giả thuyết Lợi ích (Benefit Hypothesis):** Biến thư viện thành một công cụ dòng lệnh chuyên nghiệp dễ dàng tích hợp vào quy trình CI/CD, đồng thời đảm bảo tốc độ xử lý nhanh cho các dự án lớn.
- **Tiêu chí chấp nhận (Acceptance Criteria):**
  - Cung cấp lệnh `php-swag generate` dễ sử dụng.
  - Xuất ra định dạng YAML và JSON chuẩn OpenAPI 3.0/3.1.
  - Tích hợp cơ chế Caching dựa trên file hash để tăng tốc độ quét lần sau.
  - Có tài liệu hướng dẫn sử dụng (README) hoàn chỉnh cho cộng đồng.

### [Epic 4] Professional API Documentation & Advanced Controls
- **Trạng thái:** Done
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
- [x] **[F3.3] Performance Caching:** Lưu trữ kết quả phân tích để tăng tốc cho các lần chạy sau.
- [x] **[F3.4] README & Documentation:** Hướng dẫn cộng đồng cách sử dụng và đóng góp.

### Features cho [Epic 4] Professional API Documentation
- [x] **[F4.1] Global API Metadata Discovery:** Tự động trích xuất @title, @version, @description, @contact.*, @license.*, và @host từ toàn bộ project.
- [x] **[F4.2] Security & Authentication Support:** Định nghĩa @securityDefinitions (ApiKey/JWT) toàn cục và @security cho endpoint.
- [x] **[F4.3] Comprehensive Schema Validation:** Hỗ trợ các tag @minimum, @maximum, @minLength, @maxLength, @pattern, @format, @example cho Model properties và Route parameters.
- [x] **[F4.4] MIME Types & Response Alias:** Hỗ trợ @accept, @produce (mặc định application/json) và alias @success/@failure.
- [x] **[F4.5] Advanced Operation Metadata:** Hỗ trợ @operationId, @deprecated và OpenAPI Extensions (x-).

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
- [x] **[S4.2.1] Security Definitions Parser:** Phân tích các định nghĩa bảo mật (API Key, Bearer JWT) từ PHPDoc.
- [x] **[S4.2.2] Security Requirement Tag:** Áp dụng tag @security cho từng endpoint để chỉ định phương thức bảo mật cần thiết.

### Stories cho [F4.3] Comprehensive Schema Validation
- [x] **[S4.3.1] Validation Tag Extraction:** Trích xuất các ràng buộc (minimum, maxLength, pattern, format, etc.) từ mô tả PHPDoc hoặc tag riêng biệt.
- [x] **[S4.3.2] Validation Mapping to OpenAPI:** Chuyển đổi các ràng buộc kỹ thuật sang Schema Object tương ứng.
- [x] **[S4.3.3] Example Tag Support:** Hỗ trợ tag @example để hiển thị dữ liệu mẫu trong UI.

### Stories cho [F4.4] MIME Types & Response Alias
- [x] **[S4.4.1] MIME Type Tags (@accept, @produce):** Cho phép định nghĩa kiểu nội dung cho request/response.
- [x] **[S4.4.2] Success/Failure Aliases:** Xử lý @success và @failure như các alias của @response để tăng tính trực quan.

### Stories cho [F4.5] Advanced Operation Metadata
- [x] **[S4.5.1] Operation ID Support:** Cho phép đặt tên thủ công cho operation qua tag @operationId.
- [x] **[S4.5.2] Deprecation Support:** Đánh dấu operation lỗi thời thông qua tag @deprecated.
- [x] **[S4.5.3] x- Extension Support:** Hỗ trợ trích xuất và xuất các extension OpenAPI tùy chỉnh bắt đầu bằng "x-".

### [Epic 5] Developer Experience & Modern PHP Support
- **Trạng thái:** Done
- **Chủ sở hữu:** Fullstack Developer (User)
- **Tóm tắt:** Tập trung vào việc tối ưu hóa quy trình viết code, tận dụng các tính năng hiện đại của PHP (Enums, Attributes-like inference) và cung cấp thông báo lỗi minh bạch.
- **Giả thuyết Lợi ích (Benefit Hypothesis):** Giảm thiểu mã lặp lại, tận dụng tối đa sức mạnh của ngôn ngữ PHP hiện đại và giúp lập trình viên phát hiện lỗi cấu hình API ngay lập tức, từ đó tăng tốc độ phát triển.
- **Tiêu chí chấp nhận (Acceptance Criteria):**
  - Hỗ trợ khai báo metadata ở cấp Controller (Class).
  - Tự động suy luận các thuộc tính bắt buộc (required) mà không cần khai báo thủ công.
  - Tích hợp sâu với Native Enums (PHP 8.1+).
  - Cung cấp cơ chế mapping thông minh cho các kiểu dữ liệu phổ biến (DateTime, UUID).
  - Thông báo lỗi chi tiết kèm vị trí file/dòng code.

## 2. Program Backlog (Features) (Tiếp theo)

### Features cho [Epic 5] Developer Experience
- [x] **[F5.1] Controller-level Metadata Support:** Hỗ trợ @tag, @security, @accept, @produce ở cấp Class.
- [x] **[F5.2] Enhanced Diagnostics & Error Reporting:** Cải thiện thông báo lỗi với đầy đủ thông tin ngữ cảnh (file, line).
- [x] **[F5.3] Intelligent Schema Inference:** Tự động xác định `required` fields dựa trên type-hint và giá trị mặc định.
- [x] **[F5.4] Native PHP Enum Support:** Tự động trích xuất các case từ PHP 8.1+ Enums.
- [x] **[F5.5] Smart Type Mapping Registry:** Map các class phổ biến (DateTime, Uuid, UploadedFile) sang kiểu dữ liệu OpenAPI tương ứng.

## 3. Team Backlog (User Stories) (Tiếp theo)

### Stories cho [F5.1] Controller-level Metadata
- [x] **[S5.1.1] Class-level Tag Collection:** Thu thập @tag từ class docblock và gộp với tags ở method.
- [x] **[S5.1.2] Class-level Security & Content-Type:** Áp dụng @security, @accept, @produce từ class làm mặc định cho tất cả method bên trong, cho phép method ghi đè.

### Stories cho [F5.2] Enhanced Diagnostics
- [x] **[S5.2.1] Source Location Tracking:** Lưu trữ thông tin file và dòng code trong quá trình parse.
- [x] **[S5.2.2] User-Friendly Error Messages:** Hiển thị lỗi chi tiết khi không phân giải được class hoặc tag sai cú pháp.

### Stories cho [F5.3] Intelligent Schema Inference
- [x] **[S5.3.1] Nullable-based Required Detection:** Tự động đánh dấu `required: true` nếu type-hint không nullable.
- [x] **[S5.3.2] Default Value Inference:** Thuộc tính có giá trị mặc định được coi là optional.
- [x] **[S5.3.3] Explicit @required Tag:** Hỗ trợ tag @required để ghi đè logic suy luận.

### Stories cho [F5.4] Native PHP Enum Support
- [x] **[S5.4.1] Enum Detection logic:** Nhận diện class là Enum thông qua Reflection.
- [x] **[S5.4.2] BackedEnum Value Extraction:** Tự động lấy `value` cho BackedEnum (string/int).
- [x] **[S5.4.3] UnitEnum Name Extraction:** Tự động lấy `name` cho UnitEnum.

### Stories cho [F5.5] Smart Type Mapping
- [x] **[S5.5.1] Built-in Date/Time Mapping:** Map `DateTimeInterface` sang `string/date-time`.
- [x] **[S5.5.2] External Library Support (Optional):** Hỗ trợ mapping cho Uuid (Ramsey/Symfony) nếu class tồn tại.
- [x] **[S5.5.3] Binary/File Mapping:** Map các class UploadedFile phổ biến sang `string/binary`.

### [Epic 6] Hỗ trợ PHP 8+ Attributes (Attributes-based Annotation)
- **Trạng thái:** Done
- **Chủ sở hữu:** Fullstack Developer (User)
- **Tóm tắt:** Bổ sung cơ chế khai báo metadata sử dụng PHP 8 Attributes song hành cùng PHPDoc.
- **Giả thuyết Lợi ích (Benefit Hypothesis):** Giúp lập trình viên viết code chuẩn PHP hiện đại, tận dụng tính năng tự động gợi ý (autocomplete) của IDE, phát hiện sớm lỗi chính tả và loại bỏ sự phụ thuộc quá lớn vào việc phân tích chuỗi text trong DocBlock.
- **Tiêu chí chấp nhận (Acceptance Criteria):**
  - Định nghĩa đầy đủ các class Attributes tương đương với các PHPDoc tags hiện tại.
  - Bộ phân tích AST thu thập chính xác Attributes từ Class, Method, Property và Method Parameters.
  - Thực hiện đúng chiến lược gộp thông minh (Smart Merge) khi khai báo song song.
  - Toàn bộ unit tests viết cho Attributes và cơ chế gộp đều pass.

## 2. Program Backlog (Features) (Tiếp theo)

### Features cho [Epic 6] PHP 8+ Attributes Support
- [x] **[F6.1] Attribute Definitions:** Định nghĩa các class Attribute tương ứng trong `src/Attributes/`.
- [x] **[F6.2] AST Attribute Extraction:** Trích xuất các Attribute từ Node AST thông qua PHP-Parser.
- [x] **[F6.3] Smart Merge Engine:** Engine gộp dữ liệu từ PHPDoc và Attributes theo thứ tự ưu tiên.

## 3. Team Backlog (User Stories) (Tiếp theo)

### Stories cho [F6.1] Attribute Definitions
- [x] **[S6.1.1] Core Routing Attributes:** Định nghĩa `#[Route]`, `#[Tag]`, `#[OperationId]`, `#[Deprecated]`.
- [x] **[S6.1.2] Parameter Attributes:** Định nghĩa `#[QueryParam]`, `#[PathParam]`, `#[HeaderParam]`, `#[CookieParam]`, `#[RequestBody]`.
- [x] **[S6.1.3] Response & Schema Attributes:** Định nghĩa `#[Response]`, `#[Property]`, `#[Schema]`.


### Stories cho [F6.2] AST Attribute Extraction
- [x] **[S6.2.1] Class & Property Attribute Parser:** Quét và phân tích Attribute ở cấp Class (Controller/Model) và Class Property.
- [x] **[S6.2.2] Method & Parameter Attribute Parser:** Quét và phân tích Attribute ở cấp Method và Parameter của Method.

### Stories cho [F6.3] Smart Merge Engine
- [x] **[S6.3.1] Override Engine for Single Values:** Ghi đè các trường đơn (summary, description, v.v.).
- [x] **[S6.3.2] Merge Engine for Collections:** Gộp các tag, security schemes.
- [x] **[S6.3.3] Keyed Collection Override:** Ghi đè các phần tử trùng lặp (trùng mã code 200, trùng tên tham số).

### [Epic 7] Tạo các Framework Bridge (Laravel & Symfony Integration)
- **Trạng thái:** Done
- **Chủ sở hữu:** Fullstack Developer (User)
- **Tóm tắt:** Xây dựng cầu nối tích hợp với Laravel và Symfony giúp lập trình viên chạy phpswag mượt mà trên framework của họ.
- **Giả thuyết Lợi ích (Benefit Hypothesis):** Giúp giảm thiểu cấu hình thủ công cho dự án dùng Laravel/Symfony, tự động đăng ký route xem tài liệu (Swagger UI) và lệnh command line tích hợp.
- **Tiêu chí chấp nhận (Acceptance Criteria):**
  - Laravel Bridge hỗ trợ Artisan command, config file và Route UI render `/api/docs`.
  - Symfony Bridge hỗ trợ Console command và Bundle DI.

### [Epic 8] Tích hợp Linter & OpenAPI Validator
- **Trạng thái:** Done
- **Chủ sở hữu:** Fullstack Developer (User)
- **Tóm tắt:** Bổ sung cơ chế validate đặc tả OpenAPI sinh ra để phát hiện sớm các lỗi cấu trúc nghiêm trọng.
- **Giả thuyết Lợi ích (Benefit Hypothesis):** Đảm bảo spec sinh ra luôn đúng chuẩn OpenAPI 3.0/3.1 trước khi xuất bản hoặc tích hợp vào hệ thống khác.
- **Tiêu chí chấp nhận (Acceptance Criteria):**
  - Có cờ `--validate` tích hợp vào lệnh `generate`.
  - Phát hiện lỗi logic (trùng route path, tham chiếu vòng sai cách, thiếu info bắt buộc) và hiển thị thông tin lỗi chi tiết.

## 2. Program Backlog (Features) (Tiếp theo)

### Features cho [Epic 7] Framework Bridges
- [x] **[F7.1] Laravel Integration:** Tích hợp ServiceProvider, config, Artisan command và route Swagger UI.
- [x] **[F7.2] Symfony Bundle:** Tích hợp Bundle, console command và DI container setup.

### Features cho [Epic 8] Linter & Validator
- [x] **[F8.1] Native Structural Validator:** Tự kiểm tra các lỗi logic cấu trúc spec trong quá trình tạo.
- [x] **[F8.2] CLI Linter Integration:** Thêm cờ `--validate` trong CLI để validate đặc tả OpenAPI.

## 3. Team Backlog (User Stories) (Tiếp theo)

### Stories cho [F7.1] Laravel Integration
- [x] **[S7.1.1] Service Provider & Config:** Tạo `PhpSwagServiceProvider` và file cấu hình mẫu `phpswag.php`.
- [x] **[S7.1.2] Artisan Commands:** Đăng ký các command `phpswag:generate` và `phpswag:watch`.
- [x] **[S7.1.3] Swagger UI Controller:** Đăng ký route và render giao diện Swagger UI HTML trực tiếp.

### Stories cho [F7.2] Symfony Bundle
- [x] **[S7.2.1] Symfony Bundle Setup:** Tạo class `PhpSwagBundle` và cấu hình DI extension.
- [x] **[S7.2.2] Symfony Console Command:** Tạo command tương đương `phpswag:generate` trong Symfony Console.

### Stories cho [F8.1] Native Structural Validator
- [x] **[S8.1.1] Spec Integrity Check:** Kiểm tra tính toàn vẹn của YAML/JSON sinh ra (thiếu title, trùng endpoint).
- [x] **[S8.1.2] Class Reference Verification:** Kiểm tra xem các class DTO/Resource được dùng làm ref có thực sự tồn tại trong registry hay không.

### Stories cho [F8.2] CLI Linter Integration
- [x] **[S8.2.1] Validate CLI Flag:** Cài đặt cờ `--validate` trong Console Command của phpswag.
- [x] **[S8.2.2] Diagnostic Output:** Định dạng và hiển thị kết quả kiểm lỗi rõ ràng cho lập trình viên.


