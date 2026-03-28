# **Kiến trúc và Chiến lược Tối ưu hóa Laravel dành cho Kỹ sư Cấp cao thông qua Hệ thống Rule Cursor và Skill Agent**

Sự trỗi dậy của các môi trường lập trình tích hợp trí tuệ nhân tạo, tiêu biểu là Cursor, đã thay đổi căn bản cách thức các kỹ sư phần mềm tiếp cận việc xây dựng hệ thống. Đối với một khung làm việc có tính định hướng cao như Laravel, việc áp dụng các quy tắc (rules) không chỉ đơn thuần là hướng dẫn định dạng mã nguồn mà còn là việc thiết lập một khung tư duy kiến trúc, đảm bảo AI hoạt động với tư cách là một kỹ sư cấp cao (Senior Developer) thay vì một người thực thi lệnh cơ bản.1 Sự chuyển dịch từ định dạng .cursorrules cũ sang hệ thống quy tắc dự án dựa trên .cursor/rules/\*.mdc trong các phiên bản Cursor hiện đại (0.45+) đánh dấu một bước tiến quan trọng trong việc quản lý ngữ cảnh và áp dụng các tiêu chuẩn kỹ thuật một cách tự động và chính xác theo phạm vi tệp tin.3 Bằng cách xây dựng một hệ thống quy tắc chặt chẽ, người phát triển có thể ép buộc AI ưu tiên tính an toàn kiểu dữ liệu, sự phân tách các mối quan tâm và các chiến lược triển khai có thể kiểm chứng được, thay vì chỉ tạo ra mã nguồn chạy được nhưng khó bảo trì.4

## **Nền tảng Kiến trúc Laravel Cấp cao và Tư duy Hệ thống**

Cách tiếp cận của một kỹ sư cấp cao đối với Laravel vượt xa các mẫu Model-View-Controller (MVC) cơ bản. Mục tiêu cốt lõi là giảm tải nhận thức cho người phát triển và AI agent, đồng thời tăng khả năng bảo trì và kiểm thử của mã nguồn thông qua việc áp dụng các tầng logic nghiệp vụ, truy cập dữ liệu và xử lý yêu cầu riêng biệt.6 Điều này đặc biệt quan trọng khi làm việc với AI, vì một cấu trúc rõ ràng giúp agent hiểu được "vị trí" chính xác của từng đoạn mã, từ đó giảm thiểu sự nhầm lẫn và lỗi logic.8

### **Mô hình Service-Action-Repository và Sự Phân tách Trách nhiệm**

Một trong những chiến lược kiến trúc hiệu quả nhất để mở rộng các ứng dụng Laravel là sự kết hợp giữa Service layer, Action classes và Repository pattern. Cách tiếp cận "Clean Architecture" này đảm bảo mỗi thành phần có một trách nhiệm duy nhất và được xác định rõ ràng, điều mà AI có thể dễ dàng tuân thủ nếu được hướng dẫn thông qua các rule cụ thể.6

| Thành phần | Trách nhiệm chính | Luồng giao tiếp kiến trúc |
| :---- | :---- | :---- |
| **Controller** | Xử lý yêu cầu/phản hồi HTTP, xác thực đầu vào, chuyển đổi tài nguyên API | Controller → Service |
| **Service** | Điều phối quy trình nghiệp vụ, quản lý giao dịch database, xử lý các mối quan tâm xuyên suốt (logging, caching) | Service → Action / Repository |
| **Action** | Thực thi một nhiệm vụ nghiệp vụ nguyên tử, tuân thủ nguyên tắc trách nhiệm đơn nhất | Action → Repository / Model |
| **Repository** | Trừu tượng hóa việc truy cập dữ liệu, xử lý các truy vấn phức tạp, tách biệt Eloquent khỏi logic nghiệp vụ | Repository → Model / Database |
| **Model** | Định nghĩa schema, quan hệ (relationships), scopes, accessors và mutators | Tầng dữ liệu (Data Layer) |

Trong cấu trúc này, các Controller vẫn giữ trạng thái "mỏng" (skinny), chỉ đóng vai trò điều hướng và giao phó toàn bộ logic nghiệp vụ cho các Service.6 Các Service đóng vai trò là người điều phối, kết hợp nhiều Action hoặc Repository để hoàn thành một yêu cầu nghiệp vụ phức tạp. Các Action lớp (Action classes) đóng gói một nhiệm vụ duy nhất, chẳng hạn như CreateUserAction hoặc ProcessPaymentAction, điều này giúp tăng khả năng tái sử dụng mã nguồn trên nhiều giao diện khác nhau như Controller, lệnh Console hoặc các Job hàng đợi.11 Tầng Repository giúp trừu tượng hóa kho lưu trữ dữ liệu thực tế, cho phép việc giả lập (mocking) trong các bài kiểm thử đơn vị trở nên dễ dàng hơn và có thể thay đổi nguồn dữ liệu mà không làm ảnh hưởng đến logic lõi của ứng dụng.9

### **Tối ưu hóa Controller và Chuyển đổi sang Action**

Việc tái cấu trúc các "fat controllers" là một dấu hiệu của kỹ năng kỹ sư cấp cao. Các controller truyền thống thường chứa logic xác thực, quy tắc nghiệp vụ và các thao tác cơ sở dữ liệu ngay trong một phương thức duy nhất, dẫn đến khó khăn trong việc kiểm thử và mở rộng.13 Một rule Cursor dành cho senior phải hướng dẫn AI agent trích xuất các thành phần này ra khỏi controller. Thay vì thực hiện các cuộc gọi trực tiếp như Mail::send() bên trong phương thức store của controller, AI cần được chỉ thị sử dụng Form Request để xác thực và Action class để xử lý logic tạo mới và gửi thông báo.10 Sự thay đổi này không chỉ giảm thiểu rủi ro của các tác dụng phụ (side effects) mà còn đảm bảo rằng logic cốt lõi có thể được truy cập từ các phần khác của hệ thống, chẳng hạn như các endpoint API hoặc các worker chạy ngầm.11

## **Quản lý Dữ liệu và Tiêu chuẩn An toàn Kiểu dữ liệu**

Phát triển Laravel hiện đại, đặc biệt với các tính năng của PHP 8.3+, nhấn mạnh vào việc sử dụng kiểu dữ liệu nghiêm ngặt và các cấu trúc dữ liệu tinh vi để đảm bảo tính toàn vẹn của hệ thống. Các kỹ sư cấp cao tránh việc truyền các mảng (arrays) thô giữa các tầng ứng dụng, vì điều này dẫn đến tình trạng "phát triển dựa trên mảng" (array-driven development), nơi cấu trúc của dữ liệu trở nên mơ hồ và dễ gây lỗi.16

### **Form Requests và Data Transfer Objects (DTO)**

Để duy trì một giao diện sạch sẽ giữa tầng HTTP và tầng dịch vụ nội bộ, việc sử dụng Form Requests để xác thực và Data Transfer Objects (DTO) để di chuyển dữ liệu là điều bắt buộc.18 Form Requests cung cấp một vị trí chuyên dụng cho các quy tắc ủy quyền và xác thực, giúp controller tập trung vào việc điều hướng.10 Sau khi được xác thực, dữ liệu được ánh xạ vào một DTO—một lớp PHP đơn giản với các thuộc tính được định kiểu rõ ràng—cung cấp khả năng tự động hoàn thành (autocompletion) và an toàn kiểu dữ liệu trong suốt các tầng service và action.17

| Đặc điểm | Form Request | Data Transfer Object (DTO) |
| :---- | :---- | :---- |
| **Mục đích chính** | Xác thực và ủy quyền HTTP | Vận chuyển dữ liệu giữa các tầng kiến trúc |
| **Loại dữ liệu** | Dựa trên mảng/Mixed | Các thuộc tính có kiểu dữ liệu nghiêm ngặt |
| **Tầng áp dụng** | HTTP (Controller) | Ứng dụng (Service/Action/Domain) |
| **Tính bất biến** | Có thể thay đổi trong request | Thường là bất biến (readonly) |

Việc tích hợp các thư viện như spatie/laravel-data có thể tối ưu hóa quy trình này hơn nữa bằng cách cho phép một lớp duy nhất đóng vai trò là cả Form Request và DTO, giảm thiểu mã nguồn lặp lại (boilerplate) trong khi vẫn duy trì được các ranh giới kiến trúc nghiêm ngặt.17

### **Kiểu dữ liệu Branded và Value Objects**

Để ngăn chặn việc vô tình trộn lẫn các ID thực thể khác nhau (ví dụ: sử dụng userId ở nơi yêu cầu orderId), các kỹ sư cấp cao sử dụng các kiểu dữ liệu Branded hoặc Value Objects.4 Bằng cách hướng dẫn agent Cursor sử dụng các kiểu dữ liệu như type UserId \= string & { readonly brand: unique symbol }, người phát triển tạo ra một mô hình có thể kiểm chứng được mà AI có thể tuân theo và thực thi.4 Mức độ chính xác này đảm bảo rằng các lỗi logic được phát hiện trong quá trình phân tích tĩnh (static analysis) thay vì trong thời gian chạy (runtime).

## **Tối ưu hóa Hiệu suất và Khả năng Mở rộng**

Tối ưu hóa không phải là một giai đoạn sau phát triển mà là một quá trình xem xét liên tục trong kỹ thuật cấp cao. Laravel cung cấp nhiều công cụ để giảm thiểu các nút thắt cổ chai phổ biến, đặc biệt là những lỗi liên quan đến tương tác cơ sở dữ liệu và các tác vụ chạy dài.21

### **Giải quyết Vấn đề Truy vấn N+1**

Vấn đề truy vấn N+1 vẫn là một trong những rủi ro hiệu suất thường gặp nhất trong các ứng dụng dựa trên Eloquent. Nó xảy ra khi một truy vấn lấy danh sách các bản ghi và sau đó thực hiện một truy vấn bổ sung cho mỗi bản ghi để lấy mối quan hệ liên quan.23 Một rule Cursor dành cho senior phải bắt buộc sử dụng kỹ thuật "eager loading" thông qua phương thức with() hoặc load() cho các bộ sưu tập đã tồn tại.24

Hơn nữa, đối với các tập dữ liệu lớn, việc sử dụng chunk(), eachById(), hoặc cursor() là cần thiết để quản lý mức tiêu thụ bộ nhớ một cách hiệu quả.23 Phương thức cursor(), đặc biệt, tận dụng PHP generators để hydrat hóa chỉ một model tại một thời điểm, giúp giảm đáng kể chi phí bộ nhớ khi xử lý hàng ngàn bản ghi.24

### **Chiến lược Caching và Quản lý Trạng thái**

Trong môi trường có lưu lượng truy cập cao, các chiến lược bộ nhớ đệm (caching) là yếu tố sống còn. Các kỹ sư cấp cao sử dụng Redis cho việc lưu trữ cache ứng dụng, quản lý session và làm driver cho hàng đợi (queue).21 Mô hình Cache::remember() là tiêu chuẩn để lấy dữ liệu không thay đổi thường xuyên, chẳng hạn như các thiết lập cấu hình hoặc nội dung xu hướng.21

| Cấp độ Cache | Lệnh/Cơ chế thực thi | Mục đích sử dụng |
| :---- | :---- | :---- |
| **Config Cache** | php artisan config:cache | Hợp nhất tất cả các tệp cấu hình thành một |
| **Route Cache** | php artisan route:cache | Tuần tự hóa cây đăng ký route |
| **View Cache** | php artisan view:cache | Biên dịch trước các template Blade |
| **Data Cache** | Cache::tags(\['posts'\])-\>remember() | Lưu trữ kết quả của các truy vấn đắt đỏ |

Đối với các ứng dụng được phân phối trên nhiều máy chủ, việc duy trì một kiến trúc không trạng thái (stateless) là bắt buộc. Điều này đòi hỏi phải di chuyển các session và cache ra khỏi hệ thống tệp cục bộ sang một kho lưu trữ tập trung như Redis hoặc cơ sở dữ liệu.22

### **Xử lý Hậu cảnh và Hàng đợi (Queues)**

Các tác vụ không yêu cầu phản hồi ngay lập tức—như gửi email, tạo tệp PDF hoặc tương tác với API của bên thứ ba—phải được đẩy vào hàng đợi chạy ngầm.21 Laravel Horizon là tiêu chuẩn công nghiệp để giám sát các hàng đợi dựa trên Redis, cung cấp bảng điều khiển thời gian thực cho lưu lượng, thời gian chạy và phân tích lỗi.22 Các rule Cursor nên đảm bảo rằng mọi thao tác "nặng" đều được tự động xem xét để thực thi dưới dạng Job.5

## **Tiêu chuẩn Bảo mật và Sẵn sàng Sản xuất**

Một kỹ sư Laravel cấp cao luôn giả định rằng mọi tầng của ứng dụng đều là một vectơ tấn công tiềm năng. Bảo mật được triển khai "theo thiết kế" (by design) bằng cách sử dụng các biện pháp bảo vệ tích hợp của Laravel kết hợp với logic tùy chỉnh nghiêm ngặt.26

### **Chiến lược Phòng thủ Chiều sâu**

| Loại lỗ hổng | Biện pháp giảm thiểu của Laravel | Thực hành tốt nhất của Senior |
| :---- | :---- | :---- |
| **SQL Injection** | Prepared statements qua Eloquent | Tránh truy vấn thô (raw queries); xác thực mọi đầu vào |
| **CSRF** | Blade directive @csrf | Bắt buộc trên tất cả các route POST/PUT/DELETE |
| **XSS** | Tự động escape với {{ $variable }} | Chỉ dùng {\!\! $var\!\!} cho HTML đã được tin tưởng |
| **Mass Assignment** | $fillable và $guarded | Ưu tiên sử dụng validated() từ Form Requests |
| **Path Traversal** | Trừu tượng hóa Storage | Lưu trữ các tệp tải lên bên ngoài thư mục public |

Việc thắt chặt bảo mật bao gồm việc vô hiệu hóa APP\_DEBUG trong môi trường sản xuất để ngăn chặn việc rò rỉ các stack trace nhạy cảm cho người dùng.22 Nó cũng bao gồm việc sử dụng các header bảo mật và nguyên tắc đặc quyền tối thiểu cho người dùng cơ sở dữ liệu.27

## **Hệ sinh thái Kiểm thử Hiện đại**

Kiểm thử là cơ chế chính để xác minh rằng đầu ra của AI agent đáp ứng các tiêu chuẩn yêu cầu. Trong hệ sinh thái Laravel, Pest đã nổi lên như là khung kiểm thử được ưa chuộng nhờ cú pháp hàm biểu cảm và bộ tính năng mạnh mẽ, bao gồm cả kiểm thử kiến trúc.29

### **Kiểm thử Unit, Feature và Architecture**

Các kỹ sư cấp cao phân biệt rõ ràng giữa kiểm thử đơn vị (unit tests \- kiểm thử logic bị cô lập) và kiểm thử tính năng (feature tests \- kiểm thử các endpoint HTTP và tích hợp cơ sở dữ liệu).7 Kiểm thử kiến trúc thông qua Pest cho phép các nhà phát triển xác định các quy tắc về cấu trúc của chính mã nguồn.30 Ví dụ, một bài kiểm thử có thể đảm bảo rằng tất cả các model đều kế thừa từ model Eloquent cơ sở, rằng tất cả các action đều có thể gọi được (invokable), và không có controller nào truy cập trực tiếp vào repository.30

### **Phát triển dựa trên Kiểm thử (TDD) với AI**

Quy trình làm việc Cursor cấp cao thường liên quan đến TDD, nơi agent được chỉ thị viết các bài kiểm thử dựa trên yêu cầu trước khi tạo ra bất kỳ mã nguồn triển khai nào.32 Điều này đảm bảo rằng việc triển khai dựa trên các kết quả có thể kiểm chứng được và ngăn chặn các chức năng "ảo giác". Agent nên chạy các bài kiểm thử này một cách tự động bằng lệnh php artisan test và lặp lại cho đến khi tất cả các xác nhận (assertions) đều vượt qua.32

## **Cơ chế Suy luận của AI Agent và Triển khai Skill**

Hiệu quả của Cursor trong môi trường Laravel được quyết định bởi chất lượng của các mô hình suy luận được nhúng trong các rule của nó. Những chỉ dẫn đơn giản, mơ hồ như "hãy viết mã sạch" thường bị các AI agent bỏ qua; thay vào đó, các ràng buộc cụ thể, có thể kiểm chứng được phải được cung cấp.4

### **Mô hình Chain-of-Thought và ReAct**

Để nâng tầm một AI agent lên mức độ "Kỹ sư Trưởng cấp cao", các rule phải thực thi các vòng lặp suy luận có cấu trúc. Mô hình "Chain-of-Thought" (CoT) khuyến khích agent chia nhỏ các vấn đề phức tạp thành các bước trung gian trước khi tạo mã.34 Mô hình "ReAct" (Reasoning \+ Acting) thêm một tầng sử dụng công cụ, nơi agent suy nghĩ về một nhiệm vụ, thực hiện một hành động (như tìm kiếm mã nguồn hoặc chạy một bài kiểm thử), quan sát kết quả và lặp lại quy trình cho đến khi hoàn thành nhiệm vụ.36

### **Khung làm việc SKILL.md**

Các Skill là một cách chuyên biệt để mở rộng khả năng của agent mà không làm quá tải ngữ cảnh toàn cục. Bằng cách định nghĩa các skill trong các tệp SKILL.md hoặc .cursor/rules/\*.mdc, nhà phát triển có thể cung cấp cho agent một "sổ tay hướng dẫn" cho các nhiệm vụ cụ thể.36 Một skill dành riêng cho Laravel có thể bao gồm:

1. **Tên:** optimize\_database\_query  
2. **Kích hoạt:** Bất cứ khi nào agent viết mệnh đề where hoặc join.  
3. **Chỉ dẫn:** Kiểm tra các index hiện có, đề xuất eager loading và xác minh truy vấn thông qua lệnh EXPLAIN.24

## **Xây dựng Tệp Rule Cursor Tối ưu cho Laravel**

Sự chuyển đổi sang các tệp .mdc trong Cursor 0.45+ cho phép tạo ra các quy tắc cực kỳ nhắm mục tiêu, được "Tự động đính kèm" (Auto Attached) dựa trên các mẫu tệp tin.2 Một tệp rule cấp cao cho Laravel nên được cấu trúc để cung cấp ngữ cảnh cấp cao, các yếu tố mã nguồn thiết yếu và các bước xác minh.40

### **Các Nguyên tắc Cốt lõi cho Rule Cursor**

1. **Tính cụ thể:** Mô tả các mẫu chính xác (ví dụ: "Luôn sử dụng readonly cho các thuộc tính DTO").4  
2. **Tính kiểm chứng:** Việc tuân thủ phải có thể kiểm tra được trong một lần xem xét nhanh (ví dụ: "Mọi service phải có một interface tương ứng").4  
3. **Tính bổ sung:** Các rule nên yêu cầu những thứ mà AI sẽ không tự thực hiện theo mặc định, chẳng hạn như triển khai các ngoại lệ tùy chỉnh cho tất cả các thất bại nghiệp vụ.4

### **Chiến lược Cấu hình Rule**

| Loại Rule | Phạm vi | Cách sử dụng |
| :---- | :---- | :---- |
| **Always** | Toàn cục/Dự án | Định dạng cơ bản, tông giọng phản hồi, bảo mật toàn cục 2 |
| **Auto Attached** | Dựa trên Glob (ví dụ: app/Http/Controllers/\*.php) | Các mẫu dành riêng cho từng tầng (Actions, Services) 2 |
| **Agent Requested** | Dựa trên mục đích | Tối ưu hóa sâu, chiến lược refactoring 40 |
| **Manual** | Lệnh @ruleName | Các cuộc di chuyển phức tạp được kích hoạt rõ ràng 2 |

Một rule cấp cao cho Laravel nên bao gồm giai đoạn "Thăm dò" (Reconnaissance), nơi agent bắt buộc phải nghiên cứu mã nguồn hiện có, xác định các model và service liên quan, và đề xuất một kế hoạch trước khi viết bất kỳ mã nào.32 Cách tiếp cận "Nghiên cứu trước" này ngăn chặn agent tạo ra logic dư thừa hoặc vi phạm các mẫu kiến trúc hiện có.42

## **Tiến hóa và Triển vọng Tương lai**

Laravel 13 và các phiên bản tiếp theo ngày càng trở nên "sẵn sàng cho AI", với các quy ước định hướng cao phù hợp hoàn hảo với nhu cầu dựa trên ngữ cảnh của các LLM.8 Việc giới thiệu Laravel Boost và các hướng dẫn dành riêng cho AI giúp đơn giản hóa hơn nữa việc tích hợp các agent vào quy trình phát triển.8

Khi khung làm việc tiến hóa, ranh giới giữa nhà phát triển và công cụ sẽ tiếp tục mờ nhạt. Vai trò của kỹ sư cấp cao sẽ ngày càng tập trung vào việc tạo ra và duy trì "Học thuyết" (The Doctrine)—một bộ các quy tắc và skill định nghĩa tâm hồn kiến trúc duy nhất của ứng dụng.42 Bằng cách thành thạo việc cấu hình các rule Cursor, nhà phát triển có thể đảm bảo rằng các AI agent của họ không chỉ viết mã, mà còn xây dựng các hệ thống doanh nghiệp mạnh mẽ, có khả năng mở rộng và an toàn, phản ánh trí tuệ của một kỹ sư kỳ cựu.

### **Danh sách Kiểm tra Tối ưu hóa cho Agent Laravel Cấp cao**

* **Kiến trúc:** Thực thi mô hình Service-Action và giữ cho các controller mỏng.6  
* **Định kiểu:** Bắt buộc sử dụng các kiểu nghiêm ngặt, DTO và Form Requests.16  
* **Hiệu suất:** Kiểm tra các vấn đề N+1 và đề xuất caching Redis.21  
* **Kiểm thử:** Yêu cầu các bài kiểm thử Pest cho mọi tính năng mới, hướng tới độ bao phủ \>85%.5  
* **Bảo mật:** Xác thực mọi đầu vào và đảm bảo các cấu hình an toàn cho sản xuất.26  
* **Suy luận:** Yêu cầu agent tuân thủ "Think-Act-Observe" và trình bày kế hoạch trước khi thực hiện.36

Cách tiếp cận có cấu trúc này biến Cursor từ một công cụ tự động hoàn thành đơn giản thành một cộng tác viên mạnh mẽ, tự chủ, có khả năng thực hiện các nhiệm vụ kỹ thuật phức tạp với sự chính xác và tầm nhìn xa của một chuyên gia Laravel cấp cao.

## **Triển khai Suy luận Nâng cao của Agent**

Việc tích hợp các mô hình suy luận nâng cao là điều tách biệt một trợ lý AI thông thường khỏi một coding agent cấp cao. Các mô hình này không chỉ là những gợi ý mà là các yêu cầu quy trình phải được nhúng vào các rule Cursor để đảm bảo rằng agent không đi đường tắt hoặc bỏ sót các trường hợp biên quan trọng.38

### **Khung làm việc Kỹ sư Trưởng Tự chủ**

Để rèn luyện tính "Extreme Ownership" cho một agent, các rule phải xác định một vòng đời cho mọi nhiệm vụ.42 Vòng đời này bắt đầu bằng việc "Thăm dò" bắt buộc, nơi agent phải sử dụng các công cụ như grep hoặc tìm kiếm hệ thống tệp để hiểu cách các tính năng tương tự được triển khai ở những nơi khác trong dự án.42 Quy tắc này nghiêm cấm agent đưa ra các giả định về sự tồn tại của các cột trong cơ sở dữ liệu, các phương thức service hoặc các biến môi trường.

Tiếp sau nghiên cứu, agent phải trình bày một "Kế hoạch Triển khai" liệt kê mọi tệp tin sẽ được tạo mới hoặc sửa đổi.32 Kế hoạch này đóng vai trò như một trạm kiểm soát để nhà phát triển con người cung cấp phản hồi trước khi agent bắt đầu giai đoạn "Thực thi". Giai đoạn cuối cùng là "Tự kiểm toán" (Self-Audit), nơi agent phải chạy bộ kiểm thử và xác minh các thay đổi của chính mình so với các yêu cầu ban đầu.33

### **Reflexion và Tự sửa lỗi**

Một agent cấp cao có khả năng xác định các lỗi của chính mình. Bằng cách kết hợp các bước "Reflexion" (Phản chiếu) vào các rule, agent được nhắc nhở để hỏi: "Điều gì có thể sai với cách tiếp cận này?" hoặc "Có mẫu thay thế nào hiệu quả hơn không?".38 Lớp siêu nhận thức này cho phép agent bắt được các lỗi logic, chẳng hạn như thiếu các giao dịch cơ sở dữ liệu hoặc các điều kiện chạy đua (race conditions) tiềm tàng, trước khi mã nguồn được cam kết.5

## **Xử lý Độ phức tạp Doanh nghiệp**

Trong các ứng dụng Laravel quy mô lớn, các thao tác CRUD đơn giản được thay thế bằng các mẫu nâng cao như Domain-Driven Design (DDD), Command Query Responsibility Segregation (CQRS) và Event Sourcing.5 Các rule Cursor cấp cao phải được chuẩn bị để xử lý các cấu trúc tiên tiến này.

### **Domain-Driven Design và Mô-đun hóa**

Khi ứng dụng phát triển, thư mục app/Models tiêu chuẩn có thể trở nên lộn xộn. Các kỹ sư cấp cao thường tổ chức lại mã nguồn của họ thành các thư mục "Domain" hoặc "Module", nơi tất cả logic liên quan đến một khu vực nghiệp vụ cụ thể (ví dụ: Thanh toán, Kho hàng, Người dùng) được nhóm lại với nhau.5 Các rule Cursor có thể được cấu hình để nhận diện các ranh giới domain này, đảm bảo rằng mã nguồn từ domain Billing không trực tiếp khởi tạo các đối tượng từ domain Inventory mà không thông qua một interface service thích hợp.17

### **Kiến trúc Dựa trên Sự kiện (Event-Driven)**

Laravel hiện đại sử dụng các event và listener để tách biệt các phần khác nhau của ứng dụng.41 Ví dụ, khi một đơn hàng được đặt, một event OrderPlaced được kích hoạt. Nhiều listener sau đó có thể xử lý các nhiệm vụ như gửi email, cập nhật kho hàng và thông báo cho kênh Slack, tất cả đều chạy không đồng bộ trong nền.5 Một rule Cursor dành cho senior đảm bảo rằng agent tuân theo mẫu tách biệt này thay vì thêm tất cả các tác dụng phụ này trực tiếp vào OrderController.13

## **Xây dựng File Rule .mdc Mẫu cho Laravel Senior**

Dưới đây là cấu trúc nội dung cốt lõi cho một file rule Cursor (định dạng .mdc) được thiết kế để ép buộc AI hoạt động như một kỹ sư Laravel cấp cao.

## ---

**description: Thực thi các tiêu chuẩn kiến trúc Laravel Senior, an toàn kiểu dữ liệu và tối ưu hóa hiệu suất. globs: \["app//\*.php", "config//*.php", "database/\*\*/*.php", "tests/\*\*/\*.php"\]**

# **Tiêu chuẩn Kỹ sư Laravel Cấp cao**

Bạn là một Kỹ sư Trưởng Laravel (Senior Principal Engineer). Bạn không chỉ viết code; bạn thiết kế các hệ thống bền vững, an toàn và hiệu suất cao.

## **1\. Giai đoạn Suy luận & Thăm dò (Reconnaissance)**

TRƯỚC KHI tạo code, bạn PHẢI thực hiện các bước sau:

* Sử dụng grep hoặc find để nghiên cứu các mẫu hiện có trong codebase.  
* Kiểm tra các Model liên quan để xác định Schema và Relationships chính xác.  
* Đề xuất một "Kế hoạch Triển khai" chi tiết bao gồm danh sách tệp và logic thay đổi.  
* CHỈ thực hiện sau khi kế hoạch được phê duyệt hoặc xác nhận tính đúng đắn.

## **2\. Kiến trúc & Phân tầng (Layering)**

* **Controllers:** Phải mỏng (Skinny). Chỉ xử lý Request/Response. Giao phó logic cho Services/Actions.  
* **Actions:** Sử dụng cho các nhiệm vụ nghiệp vụ đơn nhất (Single Responsibility). Ưu tiên các class có phương thức \_\_invoke() hoặc execute().  
* **Services:** Sử dụng để điều phối nhiều Action hoặc xử lý logic nghiệp vụ phức tạp.  
* **DTOs:** Luôn sử dụng DTO (Data Transfer Objects) để truyền dữ liệu từ Controller vào Service/Action. Không truyền mảng array thô.  
* **Form Requests:** Luôn sử dụng Form Request để xác thực và ủy quyền HTTP.

## **3\. An toàn Kiểu dữ liệu & PHP 8.3+**

* Sử dụng declare(strict\_types=1); trong tất cả các tệp mới.  
* Luôn chỉ định kiểu dữ liệu cho tham số đầu vào và giá trị trả về (return types).  
* Sử dụng thuộc tính readonly cho các DTO.  
* Tận dụng match expressions thay vì switch phức tạp.

## **4\. Tối ưu hóa Database (Performance)**

* **N+1 Detection:** Luôn kiểm tra xem có cần Eager Loading (with()) không. Không bao giờ thực hiện truy vấn trong vòng lặp.  
* **Indexing:** Khi tạo Migration, luôn xem xét việc thêm Index cho các cột trong mệnh đề WHERE hoặc JOIN.  
* **Large Datasets:** Sử dụng chunk() hoặc cursor() khi xử lý hàng nghìn bản ghi để tiết kiệm bộ nhớ.  
* **Caching:** Sử dụng Cache::remember() với Redis driver cho các dữ liệu đắt đỏ.

## **5\. Kiểm thử & Chất lượng (Quality Assurance)**

* Luôn viết bài kiểm thử (ưu tiên Pest PHP) TRƯỚC HOẶC SONG SONG với code triển khai.  
* Đạt độ bao phủ code tối thiểu 85%.  
* Thực hiện kiểm thử kiến trúc (arch()) để đảm bảo không vi phạm các ranh giới lớp (ví dụ: Controller không gọi Repository trực tiếp).

## **6\. Bảo mật (Security)**

* Luôn thoát (escape) dữ liệu đầu ra. Không dùng {\!\!\!\!} trừ khi tuyệt đối cần thiết.  
* Sử dụng validated() từ Form Request thay vì request()-\>all().  
* Đảm bảo các thuộc tính nhạy cảm được bảo vệ thông qua $fillable hoặc $guarded.

Tệp rule này, khi được đặt trong thư mục .cursor/rules/laravel-senior.mdc, sẽ cung cấp một khung làm việc không thể lay chuyển cho AI agent. Nó đảm bảo rằng mọi dòng code được tạo ra đều được xem xét qua lăng kính của hiệu suất, bảo mật và tính duy trì lâu dài.3 Bằng cách kết hợp các chỉ dẫn kiến trúc này với khả năng thực thi của các skill agent, quy trình phát triển Laravel sẽ đạt được một mức độ tự động hóa và chất lượng tương đương với các đội ngũ kỹ sư hàng đầu.1

## **Tổng kết và Hướng dẫn Thực thi**

Việc xây dựng một hệ thống rule hiệu quả cho Cursor là một quá trình lặp đi lặp lại. Các kỹ sư nên bắt đầu với các quy tắc cơ bản và dần dần mở rộng chúng khi phát hiện ra các sai lầm lặp lại của AI.32 Điều quan trọng là phải giữ cho các rule luôn cụ thể, có thể kiểm chứng được và bổ sung cho những gì AI chưa tự thực hiện tốt.4 Sự kết hợp giữa tư duy thiết kế của con người và khả năng thực thi mạnh mẽ của AI agent thông qua các hệ thống rule như .mdc sẽ mở ra một kỷ nguyên mới của năng suất và sự xuất sắc trong phát triển phần mềm Laravel.

#### **Nguồn trích dẫn**

1. Boost Your Development Productivity with Cursor Rules: A Complete Guide, truy cập vào tháng 3 29, 2026, [https://dev.to/blamsa0mine/boost-your-development-productivity-with-cursor-rules-a-complete-guide-3nhm](https://dev.to/blamsa0mine/boost-your-development-productivity-with-cursor-rules-a-complete-guide-3nhm)  
2. digitalchild/cursor-best-practices \- GitHub, truy cập vào tháng 3 29, 2026, [https://github.com/digitalchild/cursor-best-practices](https://github.com/digitalchild/cursor-best-practices)  
3. Cursor Rules Advanced Guide: Pattern Configuration & Templates, truy cập vào tháng 3 29, 2026, [https://www.sitepoint.com/cursor-rules-advanced-pattern-configuration-guide/](https://www.sitepoint.com/cursor-rules-advanced-pattern-configuration-guide/)  
4. How to Write .cursorrules That Actually Work \- DEV Community, truy cập vào tháng 3 29, 2026, [https://dev.to/nedcodes/how-to-write-cursorrules-that-actually-work-2imd](https://dev.to/nedcodes/how-to-write-cursorrules-that-actually-work-2imd)  
5. awesome-claude-code-subagents/categories/02-language ... \- GitHub, truy cập vào tháng 3 29, 2026, [https://github.com/VoltAgent/awesome-claude-code-subagents/blob/main/categories/02-language-specialists/laravel-specialist.md](https://github.com/VoltAgent/awesome-claude-code-subagents/blob/main/categories/02-language-specialists/laravel-specialist.md)  
6. Clean Service-Action Architecture: A Battle-Tested Pattern for Laravel Applications, truy cập vào tháng 3 29, 2026, [https://ratheepan.medium.com/clean-service-action-architecture-a-battle-tested-pattern-for-laravel-applications-dc311ecc5c29](https://ratheepan.medium.com/clean-service-action-architecture-a-battle-tested-pattern-for-laravel-applications-dc311ecc5c29)  
7. Laravel testing strategies: A developer's guide to efficient, robust applications \- Binarcode, truy cập vào tháng 3 29, 2026, [https://www.binarcode.com/blog/laravel-testing-strategies-a-developers-guide-to-efficient-robust-applications](https://www.binarcode.com/blog/laravel-testing-strategies-a-developers-guide-to-efficient-robust-applications)  
8. AI Assisted Development | Laravel 13.x \- The clean stack for Artisans and agents, truy cập vào tháng 3 29, 2026, [https://laravel.com/docs/13.x/ai](https://laravel.com/docs/13.x/ai)  
9. adobrovolsky97/laravel-repository-service-pattern \- GitHub, truy cập vào tháng 3 29, 2026, [https://github.com/adobrovolsky97/laravel-repository-service-pattern](https://github.com/adobrovolsky97/laravel-repository-service-pattern)  
10. Writing Clean and Maintainable Code in Laravel 12 \- NeedLaravelSite, truy cập vào tháng 3 29, 2026, [https://needlaravelsite.com/blog/writing-clean-and-maintainable-code-in-laravel-12](https://needlaravelsite.com/blog/writing-clean-and-maintainable-code-in-laravel-12)  
11. Understanding the Action Pattern in Laravel: A Cleaner Way to Organize Your Code, truy cập vào tháng 3 29, 2026, [https://medium.com/@harryespant/understanding-the-action-pattern-in-laravel-a-cleaner-way-to-organize-your-code-3c7f04666c23](https://medium.com/@harryespant/understanding-the-action-pattern-in-laravel-a-cleaner-way-to-organize-your-code-3c7f04666c23)  
12. Actions vs Repositories in Laravel | Krodox Official Web site, truy cập vào tháng 3 29, 2026, [https://krodox.com/blog/actions-vs-repositories](https://krodox.com/blog/actions-vs-repositories)  
13. The Art of Refactoring: How to Refactor Your Laravel Codebase | SaaSykit, truy cập vào tháng 3 29, 2026, [https://saasykit.com/blog/the-art-of-refactoring-how-to-refactor-your-laravel-codebase](https://saasykit.com/blog/the-art-of-refactoring-how-to-refactor-your-laravel-codebase)  
14. GitHub \- alexeymezenin/laravel-best-practices, truy cập vào tháng 3 29, 2026, [https://github.com/alexeymezenin/laravel-best-practices](https://github.com/alexeymezenin/laravel-best-practices)  
15. A Dive into "Laravel" Design Patterns: Action Pattern, Repository Pattern, and Query Service, truy cập vào tháng 3 29, 2026, [https://drobny.dev/blog/a-dive-into-laravel-design-patterns-action-pattern-repository-pattern-and-query-service](https://drobny.dev/blog/a-dive-into-laravel-design-patterns-action-pattern-repository-pattern-and-query-service)  
16. awesome-cursorrules/rules/laravel-php-83-cursorrules-prompt-file/.cursorrules at main · PatrickJS/awesome-cursorrules \- GitHub, truy cập vào tháng 3 29, 2026, [https://github.com/PatrickJS/awesome-cursorrules/blob/main/rules/laravel-php-83-cursorrules-prompt-file/.cursorrules](https://github.com/PatrickJS/awesome-cursorrules/blob/main/rules/laravel-php-83-cursorrules-prompt-file/.cursorrules)  
17. What is the advantage of DTO (over model instances)? : r/laravel \- Reddit, truy cập vào tháng 3 29, 2026, [https://www.reddit.com/r/laravel/comments/1arq55e/what\_is\_the\_advantage\_of\_dto\_over\_model\_instances/](https://www.reddit.com/r/laravel/comments/1arq55e/what_is_the_advantage_of_dto_over_model_instances/)  
18. Need Guidance on Choosing the Right Architecture for a Laravel Application \- Laracasts, truy cập vào tháng 3 29, 2026, [https://laracasts.com/discuss/channels/general-discussion/need-guidance-on-choosing-the-right-architecture-for-a-laravel-application](https://laracasts.com/discuss/channels/general-discussion/need-guidance-on-choosing-the-right-architecture-for-a-laravel-application)  
19. Mastering Data Transfer Objects in Laravel | Twilio, truy cập vào tháng 3 29, 2026, [https://www.twilio.com/en-us/blog/developers/community/mastering-data-transfer-objects-laravel](https://www.twilio.com/en-us/blog/developers/community/mastering-data-transfer-objects-laravel)  
20. Form Requests vs Value Objects for Handling Complex Nested Requests in Laravel? : r/PHPhelp \- Reddit, truy cập vào tháng 3 29, 2026, [https://www.reddit.com/r/PHPhelp/comments/1gl1cq0/form\_requests\_vs\_value\_objects\_for\_handling/](https://www.reddit.com/r/PHPhelp/comments/1gl1cq0/form_requests_vs_value_objects_for_handling/)  
21. Laravel Performance Optimization: Best Practices & Tips \- Innoraft, truy cập vào tháng 3 29, 2026, [https://www.innoraft.ai/blog/optimize-laravel-performance](https://www.innoraft.ai/blog/optimize-laravel-performance)  
22. Laravel Performance Optimization: Caching, Queues & Scaling ..., truy cập vào tháng 3 29, 2026, [https://pola5h.github.io/blog/laravel-performance-optimization/](https://pola5h.github.io/blog/laravel-performance-optimization/)  
23. Laravel Optimization: The Complete Performance Guide for Faster Applications, truy cập vào tháng 3 29, 2026, [https://www.inmotionhosting.com/blog/laravel-optimization-complete-guide/](https://www.inmotionhosting.com/blog/laravel-optimization-complete-guide/)  
24. Optimizing Laravel Performance: Tips for Faster Applications \- NeedLaravelSite, truy cập vào tháng 3 29, 2026, [https://needlaravelsite.com/blog/optimizing-laravel-performance-tips-for-faster-applications](https://needlaravelsite.com/blog/optimizing-laravel-performance-tips-for-faster-applications)  
25. Refactoring Laravel: Tools and Techniques for Improving Legacy Code \- OtterWise, truy cập vào tháng 3 29, 2026, [https://getotterwise.com/article/refactoring-laravel-tools-and-techniques-for-improving-legacy-code](https://getotterwise.com/article/refactoring-laravel-tools-and-techniques-for-improving-legacy-code)  
26. Laravel Security Best Practices: Protecting Against Common Vulnerabilities, truy cập vào tháng 3 29, 2026, [https://dev.to/addwebsolutionpvtltd/laravel-security-best-practices-protecting-against-common-vulnerabilities-3794](https://dev.to/addwebsolutionpvtltd/laravel-security-best-practices-protecting-against-common-vulnerabilities-3794)  
27. Securing Laravel Applications from SQL Injection and XSS Attacks \- E Edge Technology, truy cập vào tháng 3 29, 2026, [https://eedgetechnology.com/blog/securing-laravel-applications-from-sql-injection-and-xss-attacks/](https://eedgetechnology.com/blog/securing-laravel-applications-from-sql-injection-and-xss-attacks/)  
28. Laravel Security: 11 Tips to Prevent Attacks \- Hostever, truy cập vào tháng 3 29, 2026, [https://www.hostever.com/blog/laravel-security-11-tips-to-prevent-attacks](https://www.hostever.com/blog/laravel-security-11-tips-to-prevent-attacks)  
29. Laravel Testing Made Simple with Pest: Write Clean, Readable, and Fast Tests, truy cập vào tháng 3 29, 2026, [https://dev.to/addwebsolutionpvtltd/laravel-testing-made-simple-with-pest-write-clean-readable-and-fast-tests-2b44](https://dev.to/addwebsolutionpvtltd/laravel-testing-made-simple-with-pest-write-clean-readable-and-fast-tests-2b44)  
30. Architecture Testing | Pest \- The elegant PHP Testing Framework, truy cập vào tháng 3 29, 2026, [https://pestphp.com/docs/arch-testing](https://pestphp.com/docs/arch-testing)  
31. Testing: Getting Started | Laravel 13.x \- The clean stack for Artisans and agents, truy cập vào tháng 3 29, 2026, [https://laravel.com/docs/13.x/testing](https://laravel.com/docs/13.x/testing)  
32. Best practices for coding with agents \- Cursor, truy cập vào tháng 3 29, 2026, [https://cursor.com/blog/agent-best-practices](https://cursor.com/blog/agent-best-practices)  
33. I don't read my AI agent's code until CI and three code reviews pass : r/rails \- Reddit, truy cập vào tháng 3 29, 2026, [https://www.reddit.com/r/rails/comments/1qvty6s/i\_dont\_read\_my\_ai\_agents\_code\_until\_ci\_and\_three/](https://www.reddit.com/r/rails/comments/1qvty6s/i_dont_read_my_ai_agents_code_until_ci_and_three/)  
34. Chain of Thought Prompting Guide \- Medium, truy cập vào tháng 3 29, 2026, [https://medium.com/@dan\_43009/chain-of-thought-prompting-guide-3fdfd1972e03](https://medium.com/@dan_43009/chain-of-thought-prompting-guide-3fdfd1972e03)  
35. Chain-of-Thought (CoT) Prompting \- Prompt Engineering Guide, truy cập vào tháng 3 29, 2026, [https://www.promptingguide.ai/techniques/cot](https://www.promptingguide.ai/techniques/cot)  
36. AI Agent Skills. Chapter 1 \- Dilip Kumar, truy cập vào tháng 3 29, 2026, [https://dilipkumar.medium.com/ai-agent-skills-da8dc400e74a](https://dilipkumar.medium.com/ai-agent-skills-da8dc400e74a)  
37. Building Smarter AI Agents: How to Embed Reasoning Patterns in Your Code \- Medium, truy cập vào tháng 3 29, 2026, [https://medium.com/@Micheal-Lanham/building-smarter-ai-agents-how-to-embed-reasoning-patterns-in-your-code-c5c04fade340](https://medium.com/@Micheal-Lanham/building-smarter-ai-agents-how-to-embed-reasoning-patterns-in-your-code-c5c04fade340)  
38. How To Add Reasoning to AI Agents via Prompt Engineering \- The New Stack, truy cập vào tháng 3 29, 2026, [https://thenewstack.io/how-to-add-reasoning-to-ai-agents-via-prompt-engineering/](https://thenewstack.io/how-to-add-reasoning-to-ai-agents-via-prompt-engineering/)  
39. What Are Agent Skills? Modular AI Agent Frameworks Explained \- DataCamp, truy cập vào tháng 3 29, 2026, [https://www.datacamp.com/blog/agent-skills](https://www.datacamp.com/blog/agent-skills)  
40. How to write great Cursor Rules \- Trigger.dev, truy cập vào tháng 3 29, 2026, [https://trigger.dev/blog/cursor-rules](https://trigger.dev/blog/cursor-rules)  
41. awesome-cursorrules/rules/laravel-tall-stack-best-practices-cursorrules-prom/.cursorrules at main · PatrickJS/awesome-cursorrules \- GitHub, truy cập vào tháng 3 29, 2026, [https://github.com/PatrickJS/awesome-cursorrules/blob/main/rules/laravel-tall-stack-best-practices-cursorrules-prom/.cursorrules](https://github.com/PatrickJS/awesome-cursorrules/blob/main/rules/laravel-tall-stack-best-practices-cursorrules-prom/.cursorrules)  
42. This gist provides structured prompting rules for optimizing Cursor AI interactions. It includes three key files to streamline AI behavior for different tasks. · GitHub, truy cập vào tháng 3 29, 2026, [https://gist.github.com/aashari/07cc9c1b6c0debbeb4f4d94a3a81339e](https://gist.github.com/aashari/07cc9c1b6c0debbeb4f4d94a3a81339e)  
43. Laravel 13 Upgrade Guide: What CTOs Need to Know \- Curotec, truy cập vào tháng 3 29, 2026, [https://www.curotec.com/insights/laravel-13-upgrade-guide-what-ctos-need-to-know/](https://www.curotec.com/insights/laravel-13-upgrade-guide-what-ctos-need-to-know/)  
44. Securing your Laravel application: A comprehensive guide | Pentest-Tools.com Blog, truy cập vào tháng 3 29, 2026, [https://pentest-tools.com/blog/laravel-application-security-guide](https://pentest-tools.com/blog/laravel-application-security-guide)  
45. What Are AI Agents? | IBM, truy cập vào tháng 3 29, 2026, [https://www.ibm.com/think/topics/ai-agents](https://www.ibm.com/think/topics/ai-agents)