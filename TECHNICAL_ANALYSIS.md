# BÁO CÁO PHÂN TÍCH KỸ THUẬT: THƯ VIỆN PHP-SWAG (SWAGGO FOR PHP)

## 1. Đánh giá tính khả thi (Feasibility Analysis)

Việc xây dựng một thư viện tạo Swagger/OpenAPI bằng cách phân tích tĩnh (Static Analysis) PHPDocs là **hoàn toàn khả thi** trong hệ sinh thái PHP hiện nay, nhờ vào các công cụ mạnh mẽ sau:

### 1.1. Phân tích mã nguồn với `nikic/php-parser`
- **Khả thi:** Rất cao.
- **Vai trò:** Chuyển đổi mã nguồn PHP thành Cây cú pháp trừu tượng (AST). Giúp trích xuất cấu trúc Class, Method, Property và các thuộc tính liên quan mà không cần chạy code (Runtime).
- **Ưu điểm:** Hỗ trợ đầy đủ các phiên bản PHP mới nhất, có khả năng đọc được cả Comments và Attributes.

### 1.2. Phân tích PHPDoc với `phpstan/phpdoc-parser`
- **Khả thi:** Rất cao.
- **Vai trò:** Đây là thư viện tiêu chuẩn để parse các PHPDoc phức tạp. Nó không chỉ đọc text thô mà còn hiểu được cấu trúc của các kiểu dữ liệu nâng cao như Generics (`Collection<User>`), Union types (`User|Admin`), và Intersection types.
- **Ưu điểm:** Độ chính xác cực cao, được tin dùng bởi các công cụ lớn như PHPStan và Rector.

### 1.3. Khả năng suy luận kiểu (Type Inference)
- **Khả thi:** Trung bình - Cao.
- **Cơ chế:** Kết hợp thông tin từ Type-hint gốc của PHP (ví dụ: `public string $name`) và thông tin bổ sung từ PHPDoc (ví dụ: `@var array<int, User>`).
- **Thách thức:** Cần bộ giải mã (Resolver) để ánh xạ các Class Name ngắn (ví dụ: `User`) thành Full-Qualified Class Name (FQCN) (ví dụ: `App\Models\User`) dựa trên các câu lệnh `use` trong file.

## 2. Các khó khăn kỹ thuật trọng tâm (Technical Challenges)

### 2.1. Phân giải Namespace và Use Statements
Khi phân tích tĩnh, thư viện phải tự mình hiểu được ngữ cảnh của file để biết `User` thực sự là class nào.
- **Giải pháp:** Xây dựng `NameResolver` đi kèm với bộ quét AST để lưu trữ bản đồ các alias `use`.

### 2.2. Xử lý kiểu dữ liệu Generic và lồng nhau
Cú pháp `ApiResponse<List<User>>` không tồn tại trong PHP thuần nhưng lại phổ biến trong Swagger.
- **Khó khăn:** OpenAPI 3.0 không hỗ trợ Generics thực thụ.
- **Giải pháp:** Sử dụng cơ chế "Flattening" hoặc tạo các Schema trung gian (ví dụ: `UserListApiResponse`) trong quá trình sinh tài liệu.

### 2.3. Quét Route toàn cục (Global Route Discovery)
Quét toàn bộ thư mục để tìm `@route` yêu cầu hiệu năng tốt.
- **Khó khăn:** Project lớn có thể có hàng nghìn file.
- **Giải pháp:** Sử dụng `Symfony Finder` hoặc `RecursiveDirectoryIterator` kết hợp với việc lọc nhanh nội dung file (regex sơ bộ) trước khi đưa vào bộ Parse AST chính thức.

### 2.4. Tham chiếu vòng (Circular References)
Class `User` chứa `Post`, và `Post` lại chứa `User`.
- **Khó khăn:** Gây ra lặp vô tận khi xây dựng Schema.
- **Giải pháp:** Sử dụng `Schema Registry` để lưu trữ các model đã được xử lý và sử dụng `$ref` trong OpenAPI để trỏ đến nhau.


## 3. Thiết kế kiến trúc chi tiết (Architectural Design)

### 3.1. Mô hình Pipeline xử lý

Thư viện sẽ hoạt động theo quy trình 5 bước:

1.  **Scanner (Bộ quét):** Tìm kiếm tất cả các file `.php` trong thư mục cấu hình. Lọc nhanh các file có chứa từ khóa `@route`.
2.  **AST Parser (Phân tích cú pháp):** Sử dụng `nikic/php-parser` để bóc tách cấu trúc class, method và lấy khối PHPDoc tương ứng.
3.  **DocBlock Analyzer (Phân tích tài liệu):** Sử dụng `phpstan/phpdoc-parser` để chuyển đổi PHPDoc thô thành các Object định nghĩa kiểu (Nodes).
4.  **Type Resolver & Schema Registry (Giải mã kiểu):**
    - Giải mã tên class (FQCN).
    - Phân tích các thuộc tính (Properties) của Model để tạo ra các OpenAPI Schema.
    - Đưa vào Registry để quản lý trùng lặp và tham chiếu.
5.  **OpenAPI Generator (Sinh tài liệu):** Chuyển đổi dữ liệu trung gian thành định dạng YAML hoặc JSON tuân thủ chuẩn OpenAPI 3.0/3.1.

### 3.2. Cấu trúc dữ liệu trung gian (Intermediate Representation - IR)

Để tránh phụ thuộc quá nhiều vào cấu trúc của OpenAPI ngay từ đầu, dữ liệu sau khi parse sẽ được lưu vào một cấu trúc IR thuần túy:
- `RouteDefinition`: path, method, summary, parameters, response_ref.
- `SchemaDefinition`: name, type, properties (array of PropertyDefinition).
- `PropertyDefinition`: name, type, is_nullable, description.

## 4. Đặc tả các PHPDoc Tags hỗ trợ

### 4.1. Endpoint Annotations (Dành cho Controller Method)
- `@route [METHOD] [PATH]` (Bắt buộc): Định nghĩa endpoint.
- `@summary [TEXT]`: Mô tả ngắn gọn.
- `@description [TEXT]`: Mô tả chi tiết.
- `@tag [NAME]`: Nhóm các API.
- `@request [CLASS_NAME]`: Định nghĩa Body request (suy luận từ class).
- `@response [CODE] [CLASS_NAME]`: Định nghĩa response.
- `@query [NAME] [TYPE] [DESCRIPTION]`: Tham số URL.

### 4.2. Schema Annotations (Dành cho Model/DTO)
- `@property [TYPE] $[NAME] [DESCRIPTION]`: Định nghĩa thuộc tính.
- `@var [TYPE]`: Dùng cho thuộc tính trong class.


## 5. Ví dụ minh họa (The "Magic" Experience)

**Code người dùng viết:**

```php
namespace App\Controllers;

use App\Resources\UserResource;

class UserController {
    /**
     * @route GET /api/users/{id}
     * @summary Lấy thông tin chi tiết người dùng
     * @response 200 UserResource
     */
    public function show(int $id) { ... }
}

namespace App\Resources;

/**
 * @property int $id ID người dùng
 * @property string $name Tên hiển thị
 * @property string|null $email
 */
class UserResource { }
```

**Thư viện tự suy luận:**
- Path parameter `id` có kiểu `integer` (từ type-hint của hàm `show`).
- Response 200 sử dụng Schema `UserResource`.
- Schema `UserResource` có 3 trường, trong đó `email` là `nullable`.

## 6. Kết luận & Đề xuất Lộ trình (Roadmap)

Dự án này mang tính thực tiễn cao, giúp giảm thiểu sự trùng lặp code (DRY) và giữ tài liệu luôn đi kèm với code.

**Giai đoạn 1: Core Engine**
- Xây dựng bộ quét AST và giải mã Namespace.
- Hỗ trợ các Tag cơ bản: `@route`, `@summary`, `@property`.

**Giai đoạn 2: Type System Pro**
- Xử lý Generics (`Collection<T>`) và Union types.
- Tự động tìm kiếm Class định nghĩa trong toàn bộ project.

**Giai đoạn 3: Integration & CLI**
- Xây dựng CLI tool `php-swag`.
- Xuất file `swagger.yaml` hoặc `swagger.json`.


## 7. Phân tích sâu các khó khăn kỹ thuật (Deep Dive into Technical Challenges)

### 7.1. Dependency Resolution & Autoloading Mapping
Khi gặp một class `UserResource`, bộ phân tích tĩnh cần biết file vật lý của nó ở đâu để đọc PHPDoc.
- **Vấn đề:** PHP không có cấu trúc file cố định cho namespace (dù PSR-4 là phổ biến).
- **Giải pháp:**
    - Đọc file `composer.json` để lấy thông tin `autoload` (PSR-4 mapping).
    - Xây dựng một `ClassIndex` (bản đồ Class FQCN -> File Path) trước khi bắt đầu parse chi tiết.

### 7.2. Hiệu năng & Bộ nhớ (Performance)
Việc parse AST là một tiến trình tiêu tốn CPU và RAM.
- **Vấn đề:** Project lớn có thể làm treo quá trình generate.
- **Giải pháp:**
    - **Caching:** Lưu trữ kết quả parse của từng file (hash của nội dung file). Chỉ parse lại những file có thay đổi.
    - **Lazy Loading:** Chỉ parse các Model Schema khi chúng thực sự được tham chiếu bởi một `@route`.

### 7.3. Xử lý Thừa kế (Inheritance & Traits)
Một Model có thể kế thừa từ một Base Model hoặc sử dụng Traits chứa các `@property`.
- **Vấn đề:** Nếu chỉ parse class hiện tại, ta sẽ mất các trường dữ liệu từ class cha.
- **Giải pháp:** Cần một cơ chế "Recursive Parsing" để duyệt ngược lên các class cha và gộp (merge) các định nghĩa thuộc tính.

### 7.4. Mâu thuẫn giữa Type-hint và PHPDoc
```php
public int $status; // PHP Type-hint
/** @var string */ // PHPDoc mâu thuẫn
public $status;
```
- **Nguyên tắc xử lý:** PHPDoc luôn có độ ưu tiên cao hơn (vì nó cho phép mô tả chi tiết hơn như `string|null`, `regex`, v.v.), nhưng nếu PHPDoc không có, sẽ lấy Type-hint làm fallback.


## 8. Thiết kế kiến trúc chi tiết (Architectural Design)

### 8.1. Sơ đồ thành phần (Component Diagram)

```text
+----------------+      +-------------------+      +---------------------+
|   CLI / Core   |----->|      Scanner      |----->|      Finder         |
+----------------+      +-------------------+      +---------------------+
       |                         |
       v                         v
+----------------+      +-------------------+      +---------------------+
|   Registry     |<-----|   AST Collector   |----->|   nikic/php-parser  |
+----------------+      +-------------------+      +---------------------+
       |                         |
       v                         v
+----------------+      +-------------------+      +---------------------+
| Type Resolver  |<-----|  DocBlock Parser  |----->| phpstan/doc-parser  |
+----------------+      +-------------------+      +---------------------+
       |
       v
+----------------+      +-------------------+
|  Generator     |----->| OpenAPI Spec (YAML)|
+----------------+      +-------------------+
```

### 8.2. Các Interface quan trọng (Internal API Design)

#### a. `CollectorInterface`
Chịu trách nhiệm duyệt qua AST và tìm kiếm các thông tin liên quan.
```php
interface Collector {
    public function collect(Node $node): void;
    public function getResults(): array;
}
```

#### b. `TypeResolverInterface`
Chịu trách nhiệm chuyển đổi một chuỗi tên type (ví dụ: `User[]`) thành một Object định nghĩa kiểu.
```php
interface TypeResolver {
    public function resolve(string $type, Context $context): TypeDefinition;
}
```

#### c. `SchemaRegistry`
Nơi lưu trữ tập trung các Model. Đảm bảo mỗi model chỉ được parse một lần.
```php
class SchemaRegistry {
    private array $schemas = [];
    public function register(string $fqcn): Reference;
    public function getDefinitions(): array;
}
```

### 8.3. Luồng dữ liệu (Data Flow)

1.  **Giai đoạn Thu thập (Collection Phase):**
    - `Scanner` tìm file -> `AST Collector` tìm các class có `@route`.
    - `AST Collector` cũng thu thập thông tin về `use` statements để tạo `Context`.
2.  **Giai đoạn Phân giải (Resolution Phase):**
    - Khi gặp một Class trong `@response`, `TypeResolver` sẽ tra cứu FQCN.
    - Nếu Class đó chưa có trong `Registry`, tiến trình Parse Model sẽ được kích hoạt cho file chứa Class đó.
3.  **Giai đoạn Sinh mã (Generation Phase):**
    - `Generator` duyệt qua danh sách Route đã thu thập.
    - Map các `TypeDefinition` sang cấu trúc `components/schemas` của OpenAPI.
    - Xuất file kết quả.


## 9. Định nghĩa bộ đặc tả PHPDoc (PHPDoc Specification)

### 9.1. Tags cho Controller (Endpoints)

| Tag | Tham số | Ví dụ | OpenAPI Mapping |
|:---|:---|:---|:---|
| `@route` | `[METHOD] [PATH]` | `@route POST /users` | `paths -> /users -> post` |
| `@summary` | `[STRING]` | `@summary Tạo user mới` | `summary` |
| `@description`| `[STRING]` | `@description Mô tả chi tiết` | `description` |
| `@tag` | `[STRING]` | `@tag User Management` | `tags` |
| `@request` | `[CLASS]` | `@request CreateUserDto` | `requestBody` |
| `@response` | `[CODE] [CLASS]` | `@response 200 UserDto` | `responses -> 200` |
| `@query` | `[NAME] [TYPE] [DESC]`| `@query page int Số trang` | `parameters (in: query)` |
| `@path` | `[NAME] [TYPE] [DESC]`| `@path id string ID User` | `parameters (in: path)` |

### 9.2. Tags cho Model (Schemas)

| Tag | Cú pháp | Ví dụ |
|:---|:---|:---|
| `@property` | `[TYPE] $[NAME] [DESC]`| `@property string $name Tên` |
| `@var` | `[TYPE]` | `@var int` |
| `@template` | `[NAME]` | `@template T` (Dùng cho Generics) |

### 9.3. Xử lý Kiểu dữ liệu đặc biệt

- `array<User>` hoặc `User[]` -> `type: array, items: { $ref: '#/components/schemas/User' }`
- `string|null` -> `type: string, nullable: true` (OpenAPI 3.0) hoặc `type: [string, null]` (OpenAPI 3.1)
- `User|Admin` -> `oneOf: [ { $ref: 'User' }, { $ref: 'Admin' } ]`
