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
  - Hỗ trợ cú pháp Generics trong PHPDoc (ví dụ: `ApiResponse<User[]>`).
  - Xử lý được Union types (`string|null`, `User|Admin`).
  - Có cơ chế Recursive Parsing để thu thập thuộc tính từ class cha và Trait.
  - Xử lý được tham chiếu vòng (Circular References).

### [Epic 3] Tích hợp CLI và Tối ưu hóa Hiệu năng
- **Trạng thái:** To Do
- **Chủ sở hữu:** Fullstack Developer (User)
- **Tóm tắt:** Hoàn thiện công cụ dưới dạng CLI, hỗ trợ nhiều định dạng xuất bản và cơ chế bộ nhớ đệm (Caching).
- **Giả thuyết Lợi ích (Benefit Hypothesis):** Biến thư viện thành một công cụ dòng lệnh chuyên nghiệp dễ dàng tích hợp vào quy trình CI/CD, đồng thời đảm bảo tốc độ xử lý nhanh cho các dự án lớn.
- **Tiêu chí chấp nhận (Acceptance Criteria):**
  - Cung cấp lệnh `php-swag generate` dễ sử dụng.
  - Xuất ra định dạng YAML và JSON chuẩn OpenAPI 3.0/3.1.
  - Tích hợp cơ chế Caching dựa trên file hash để tăng tốc độ quét lần sau.
  - Có tài liệu hướng dẫn sử dụng (README) hoàn chỉnh cho cộng đồng.


## 2. Program Backlog (Features)

### Features cho [Epic 1] Core Engine
- [x] **[F1.1] File Scanner & Finder:** Tìm kiếm đệ quy tất cả các file .php trong các thư mục được cấu hình.
  - *AC:* Trả về danh sách đường dẫn file hợp lệ; bỏ qua các file trong vendor hoặc thư mục bị loại trừ.
- [x] **[F1.2] AST Parser Integration:** Tích hợp `nikic/php-parser` để đọc cấu trúc code.
  - *AC:* Chuyển đổi mã nguồn thành cây AST; trích xuất được các Class Node và Method Node.
- [x] **[F1.3] Namespace Resolver:** Xác định chính xác FQCN (Fully Qualified Class Name) dựa trên `namespace` và `use` statements.
  - *AC:* Trả về tên class đầy đủ ngay cả khi sử dụng alias.
- [x] **[F1.4] Basic DocBlock Collector:** Thu thập và phân tích các tag đơn giản (@route, @summary, @property).
  - *AC:* Chuyển đổi PHPDoc thô thành các Object thuộc tính tương ứng.

### Features cho [Epic 2] Type System Pro
- [x] **[F2.1] Advanced Type Resolver:** Hỗ trợ các kiểu dữ liệu phức tạp của PHP hiện đại.
  - *AC:* Xử lý được union types (A|B), nullable (?A), và các kiểu nguyên thủy.
- [x] **[F2.2] Generics Support:** Phân tích cú pháp template cho các kiểu dữ liệu generic.
  - *AC:* Hiểu được `Collection<User>` hoặc `ApiResponse<T>`.
- [x] **[F2.3] Inheritance & Trait Merger:** Gộp các thuộc tính từ các class cha và traits.
  - *AC:* Schema của class con phải bao gồm đầy đủ thuộc tính từ cây kế thừa.
- [x] **[F2.4] Schema Registry:** Quản lý tập trung các định nghĩa Model để tránh trùng lặp và xử lý tham chiếu vòng.
  - *AC:* Không bị lỗi vòng lặp vô tận khi Class A chứa Class B và ngược lại.

### Features cho [Epic 3] Integration & CLI
- [ ] **[F3.1] CLI Command Interface:** Cung cấp giao diện dòng lệnh cho người dùng.
  - *AC:* Chạy được lệnh `php-swag generate --path=src`.
- [x] **[F3.2] OpenAPI Spec Generator:** Chuyển đổi dữ liệu IR thành file chuẩn OpenAPI.
  - *AC:* Xuất ra file `swagger.yaml` hoặc `swagger.json` hợp lệ (v3.0/3.1).
- [ ] **[F3.3] Performance Caching:** Lưu trữ kết quả phân tích để tăng tốc cho các lần chạy sau.
  - *AC:* Tốc độ generate lần 2 phải nhanh hơn ít nhất 50% so với lần đầu.
- [ ] **[F3.4] README & Documentation:** Hướng dẫn cộng đồng cách sử dụng và đóng góp.
  - *AC:* Có file README.md chi tiết với ví dụ minh họa rõ ràng.

## 3. Team Backlog (User Stories - Examples for PI-1)

### Stories cho [F1.3] Namespace Resolver
- [x] **[S1.3.1] Parse Use Statements:**
  - *Câu chuyện:* Là một hệ thống phân tích, tôi muốn đọc và lưu trữ các alias trong phần `use` của file PHP, để tôi biết chính xác tên class được tham chiếu trong code.
  - *AC:* Xử lý được các trường hợp: `use App\User;`, `use App\Resource as Res;`, `use Group\{ClassA, ClassB};`.
- [x] **[S1.3.2] Contextual Class Resolution:**
  - *Câu chuyện:* Là một hệ thống phân tích, tôi muốn tìm được FQCN của một class dựa trên context hiện tại (Namespace + Use statements), để tạo tham chiếu chính xác trong OpenAPI.
  - *AC:* Trả về `App\Resources\UserResource` khi gặp code sử dụng `UserResource` trong namespace `App\Controllers` có `use App\Resources\UserResource`.

### Stories cho [F1.4] Basic DocBlock Collector
- [x] **[S1.4.1] Extract @route tag:**
  - *Câu chuyện:* Là một lập trình viên, tôi muốn dùng tag `@route` để định nghĩa endpoint, để tôi không phải viết cấu trúc path phức tạp trong file cấu hình.
  - *AC:* Bóc tách được `METHOD` (GET, POST, ...) và `PATH` từ chuỗi `@route GET /users`.
- [x] **[S1.4.2] Extract @property tag:**
  - *Câu chuyện:* Là một lập trình viên, tôi muốn dùng tag `@property` trong Model, để mô tả cấu trúc JSON của API.
  - *AC:* Trích xuất được kiểu dữ liệu (`string`, `int`), tên biến (`$name`) và mô tả kèm theo.

### Stories cho [F2.1] Advanced Type Resolver
- [x] **[S2.1.1] Handle Nullable Types:**
  - *Câu chuyện:* Là một lập trình viên, tôi muốn hỗ trợ kiểu nullable, để tài liệu API phản ánh đúng tính chất dữ liệu (có thể null).
  - *AC:* Nhận diện `?string`, `string|null` và ánh xạ sang `nullable: true` trong OpenAPI.
- [x] **[S2.1.2] Array Type Support:**
  - *Câu chuyện:* Là một lập trình viên, tôi muốn hỗ trợ kiểu mảng (User[] hoặc array<User>), để mô tả chính xác các collection trong API.
  - *AC:* Ánh xạ chính xác sang `type: array` với `items` tương ứng trong OpenAPI.


## 4. Ưu tiên hóa bằng WSJF (Weighted Shortest Job First)

Chúng ta sẽ tính toán cho các Feature chính trong PI-1 (dự kiến tập trung vào Epic 1 và một phần Epic 2).
Thang điểm Fibonacci: 1, 2, 3, 5, 8, 13, 20.

| Feature | Business Value | Time Criticality | RR \| OE | Cost of Delay (CoD) | Job Size | **WSJF** |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| [F1.2] AST Parser | 13 | 5 | 20 | 38 | 8 | **DONE** |
| [F1.3] Namespace Resolver | 8 | 3 | 13 | 24 | 5 | **DONE** |
| [F1.1] File Scanner | 5 | 2 | 5 | 12 | 3 | **DONE** |
| [F1.4] Basic DocBlock | 13 | 8 | 8 | 29 | 5 | **DONE** |
| [F2.1] Adv. Type Resolver | 8 | 3 | 8 | 19 | 5 | **DONE** |
| [F2.4] Schema Registry | 5 | 2 | 13 | 20 | 3 | **DONE** |

**Phân tích:**
- **[F2.4] Schema Registry** đã hoàn thành, giải quyết rủi ro kỹ thuật lớn về tham chiếu vòng.
- **[F2.1] Advanced Type Resolver** đã hoàn thành, hỗ trợ nullable và array types.


## 5. Transformation Roadmap & PI Objectives

### Lộ trình (Roadmap)
- **PI-1 (Foundation):** Thiết lập Core Engine, giải quyết Namespace, và hỗ trợ các Tag cơ bản. Kết thúc PI-1 với một bản MVP có thể quét được các dự án PHP đơn giản.
- **PI-2 (Advanced Logic):** Tập trung vào Type System (Generics, Inheritance). Xử lý các trường hợp phức tạp để thư viện có thể dùng cho các Framework như Laravel/Symfony.
- **PI-3 (Productization):** Hoàn thiện CLI, Caching và đóng gói để phát hành phiên bản 1.0.0 cho cộng đồng.

### PI-1 Objectives (Mục tiêu PI-1)
1. **Mục tiêu kỹ thuật (Committed):**
   - Hoàn thành bộ quét AST có độ chính xác > 95% với các project PSR-4.
   - Hỗ trợ đầy đủ các Tag `@route`, `@summary`, `@property`.
   - Xuất được file YAML hợp lệ có thể mở bằng Swagger UI.
2. **Mục tiêu phi kỹ thuật (Uncommitted):**
   - Thiết lập CI/CD cơ bản (Github Actions) để tự động kiểm tra code.
   - Viết bài blog giới thiệu ý tưởng dự án lên cộng đồng PHP Việt Nam.


## 6. Lean Governance & Improvement Backlog

### Lean Governance (Quản trị tinh gọn)
Vì đây là dự án cá nhân, cơ chế quản trị sẽ tập trung vào sự kỷ luật tự thân:
- **Lấy giá trị làm trọng tâm:** Mỗi Story được viết ra phải chứng minh được giá trị cho người dùng cuối (Cộng đồng PHP).
- **Phê duyệt Epic:** User đóng vai trò Epic Owner, tự đánh giá tính khả thi và Business Value trước khi bắt đầu một Epic mới.
- **Minh bạch:** Sử dụng Kanban Board (giả định) để theo dõi luồng công việc từ To Do -> In Progress -> Done.

### Improvement Backlog (Kế hoạch cải tiến)
- **[IMP-1] Automation:** Tự động hóa việc sinh tài liệu cho chính dự án này (Self-documenting).
- **[IMP-2] Feedback Loop:** Sau PI-1, gửi bản MVP cho 3-5 đồng nghiệp để lấy feedback sớm, thay vì đợi đến khi hoàn thiện 100%.
- **[IMP-3] Quality Gate:** Thiết lập ngưỡng coverage cho unit test tối thiểu 80% trước khi merge Feature vào nhánh chính.
