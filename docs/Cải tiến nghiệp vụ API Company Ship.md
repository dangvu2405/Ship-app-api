# **Chiến Lược Tái Cấu Trúc Nghiệp Vụ Hệ Thống Quản Trị Vận Tải Và Cơ Chế Lương Tài Xế Tích Hợp: Từ Mô Hình MVP Đến Hệ Sinh Thái Quản Trị Hiệu Suất Thông Minh**

Sự phát triển của ngành logistics hiện đại đòi hỏi một bước nhảy vọt từ việc số hóa cơ bản sang quản trị dựa trên dữ liệu thông minh. Hệ thống quản trị vận tải (Transportation Management System \- TMS) không còn chỉ là một công cụ ghi chép mà đã trở thành trung tâm điều hành chiến lược, nơi các luồng thông tin về con người, phương tiện và tài chính hội tụ để tối ưu hóa hiệu quả vận hành.1 Trong bối cảnh đó, dự án Company Ship API đại diện cho một nỗ lực thiết lập nền tảng quản trị vận tải nội bộ chuyên sâu. Tuy nhiên, để đáp ứng sự phức tạp của thị trường vận tải và kỳ vọng về sự minh bạch tài chính, mô hình sản phẩm tối thiểu (MVP) hiện tại cần được nâng cấp toàn diện về mặt logic nghiệp vụ. Báo cáo này phân tích các lỗ hổng trong logic hiện tại và đề xuất một kiến trúc nghiệp vụ mới, tập trung vào tính chính xác của dữ liệu, sự công bằng trong thù lao và khả năng kiểm soát chi phí biến động.

## **Kiến Trúc Tổ Chức Và Quản Trị Thực Thể Đa Tầng**

Một hệ thống TMS mạnh mẽ bắt đầu từ một cấu trúc tổ chức linh hoạt nhưng chặt chẽ. Mô hình hiện tại của Company Ship API đã xác lập được trục Công ty — Văn phòng — Phòng ban, đây là nền tảng thiết yếu để hỗ trợ các doanh nghiệp vận tải đa chi nhánh.3 Tuy nhiên, để tối ưu hóa quản trị, logic này cần được mở rộng để phản ánh đúng vai trò của các thực thể trong chuỗi giá trị vận tải.

Cấu trúc tổ chức không chỉ đơn thuần là phân cấp quản lý mà còn là cơ chế để phân bổ chi phí và trách nhiệm. Trong mô hình nâng cao, mỗi Văn phòng (Office) không chỉ là một điểm hoạt động mà còn được định nghĩa như một Trung tâm Chi phí (Cost Center) hoặc Trung tâm Lợi nhuận (Profit Center).5 Điều này cho phép hệ thống báo cáo dashboard không chỉ dừng lại ở tổng quan toàn công ty mà có thể chi tiết hóa hiệu quả vận hành của từng đơn vị địa phương. Việc gắn quản lý văn phòng với một thực thể tài xế cụ thể là một bước đi thực tế, nhưng cần được bổ sung bằng các vai trò điều phối (Dispatcher) chuyên biệt để tách biệt giữa người thực hiện nhiệm vụ và người giám sát.3

| Thực thể | Logic MVP | Logic Nâng cao (Đề xuất) | Ý nghĩa quản trị |
| :---- | :---- | :---- | :---- |
| **Công ty (Company)** | Pháp nhân vận hành đơn lẻ | Thực thể mẹ trong mô hình Multi-tenancy | Hỗ trợ quản lý tập đoàn và đối soát liên công ty.3 |
| **Văn phòng (Office)** | Điểm hoạt động địa lý | Trung tâm chi phí (Cost Center) độc lập | Phân bổ ngân sách nhiên liệu và bảo trì theo vùng.7 |
| **Phòng ban (Dept)** | Phân nhóm hành chính | Đơn vị chức năng (Vận hành, Kỹ thuật, HR) | Tối ưu hóa quy trình phê duyệt bảng lương theo chức năng.8 |
| **Vị trí (Position)** | Gắn lương cơ bản tĩnh | Gắn bậc lương, phụ cấp độc hại và KPI | Tạo lộ trình thăng tiến và dải lương linh hoạt cho tài xế.9 |

Sự chuyển đổi từ quản lý tĩnh sang quản lý động yêu cầu các thực thể này phải có tính liên kết cao. Ví dụ, khi một tài xế được điều chuyển giữa các văn phòng, hệ thống phải tự động tính toán lại phần lương cơ bản thuộc về từng trung tâm chi phí dựa trên thời gian làm việc thực tế tại mỗi nơi. Đây là tiền đề cho việc tỷ lệ hóa lương (proration) một cách chính xác, tránh các sai sót trong kế toán quản trị.10

## **Tái Cấu Trúc Quản Trị Tài Xế: Từ Hồ Sơ Tĩnh Đến Vòng Đời Hiệu Suất**

Tài xế là tài sản quan trọng nhất trong hệ thống vận tải, nhưng cũng là đối tượng khó quản lý nhất do tính chất công việc lưu động. Logic hiện tại tập trung vào hồ sơ và trạng thái hoạt động, nhưng lại bỏ qua các yếu tố về biến động nhân sự trong kỳ lương.

### **Logic Tỷ Lệ Hóa Lương Cơ Bản (Proration Logic)**

Trong thực tế vận hành, tài xế có thể bắt đầu làm việc, xin nghỉ việc hoặc tạm đình chỉ công tác vào bất kỳ ngày nào trong tháng. Việc tính lương cơ bản nguyên tháng cho tất cả tài xế active là một lỗi logic nghiêm trọng trong kế toán, dẫn đến việc chi trả thừa hoặc thiếu.10 Nghiệp vụ mới yêu cầu hệ thống phải tự động tính toán lương dựa trên số ngày làm việc thực tế (working days) thay vì ngày lịch.

Cơ chế tỷ lệ hóa phải xem xét các kịch bản sau:

* **Gia nhập giữa kỳ:** Tài xế bắt đầu từ ngày 15 của tháng có 22 ngày công tiêu chuẩn sẽ chỉ nhận 50% lương cơ bản.10  
* **Chấm dứt hợp đồng:** Hệ thống tự động khóa dòng lương và tính toán đến ngày làm việc cuối cùng sau khi đã đối soát các khoản nợ hoặc tạm ứng.  
* **Nghỉ không lương/FMLA:** Các khoảng thời gian nghỉ phép không hưởng lương phải được khấu trừ trực tiếp vào lương cơ bản dựa trên đơn giá ngày công.11

Công thức tính đơn giá ngày công tiêu chuẩn (![][image1]) nên được thiết lập linh hoạt:

![][image2]  
Việc sử dụng số ngày làm việc tiêu chuẩn (thường là 22 hoặc 26 ngày tùy chính sách công ty) thay vì 30 ngày lịch giúp đảm bảo quyền lợi của tài xế và tính chính xác của chi phí lao động trên mỗi đơn vị thời gian làm việc thực tế.10

### **Quản trị Chứng chỉ và Tuân thủ**

Bên cạnh yếu tố tài chính, logic quản lý tài xế phải tích hợp cơ chế cảnh báo tuân thủ. GPLX và chứng chỉ tập huấn có thời hạn là những rủi ro pháp lý lớn. Một hệ thống logic hơn sẽ tự động chuyển trạng thái tài xế sang restricted (hạn chế) hoặc inactive nếu chứng chỉ hết hạn, từ đó ngăn chặn việc phân công chuyến và loại bỏ tài xế khỏi kỳ lương một cách tự động để đảm bảo tính an toàn.13

## **Tối Ưu Hóa Phương Tiện Và Cơ Chế Quản Trị Chi Phí Nhiên Liệu**

Phương tiện không chỉ là công cụ mà còn là một nguồn phát sinh chi phí biến động lớn nhất. Logic MVP hiện tại đang áp dụng cơ chế "trừ trực tiếp chi phí nhiên liệu thực tế vào lương tài xế". Cách tiếp cận này chứa đựng nhiều rủi ro về mặt nhân sự và vận hành: nó khiến thu nhập của tài xế phụ thuộc vào giá xăng dầu thị trường (một yếu tố họ không kiểm soát được) và không khuyến khích việc lái xe tiết kiệm.

### **Chuyển Đổi Từ Chi Phí Thực Tế Sang Định Mức (Fuel Quota Logic)**

Để tạo ra sự công bằng và động lực, hệ thống cần áp dụng mô hình "Quản trị định mức nhiên liệu". Theo mô hình này, mỗi loại phương tiện sẽ được gắn với một định mức tiêu thụ kỹ thuật.15 Tài xế sẽ được cấp một lượng nhiên liệu "quota" dựa trên quãng đường thực tế của chuyến xe.

Hệ thống định mức nhiên liệu nâng cao sẽ được tính toán dựa trên các biến số thực tế của Việt Nam như sau 15:

1. **Định mức cơ bản (![][image3]):** Số lít nhiên liệu cho 100km khi xe chạy không tải.  
2. **Hệ số tải trọng (![][image4]):** Phụ cấp nhiên liệu cho mỗi tấn hàng hóa vận chuyển.  
3. **Phụ cấp dừng đỗ (![][image5]):** Lượng nhiên liệu tiêu hao cho mỗi lần dừng, giao nhận hàng hoặc nổ máy chờ.15  
4. **Hệ số cung đường và thời gian:** Tăng thêm tỷ lệ % cho các tuyến đường miền núi, đô thị tắc nghẽn hoặc sử dụng máy điều hòa trong thời tiết cực đoan.15

| Thông số xe | Định mức tiêu chuẩn (Lít/100km) | Hệ số tải trọng (Lít/tấn.100km) | Ghi chú |
| :---- | :---- | :---- | :---- |
| Xe tải 1 \- 2.5 tấn | 8 \- 10 | 1.0 | Phù hợp vận tải nội đô.16 |
| Xe tải 3.5 \- 8 tấn | 11 \- 12 | 1.5 | Vận tải liên tỉnh cự ly ngắn.16 |
| Xe tải 15 tấn | 30 | 2.5 | Vận tải đường dài.16 |
| Đầu kéo Container | 35 \- 40 | 3.0 | Tùy thuộc vào loại rơ-moóc. |

### **Cơ Chế Thưởng Tiết Kiệm Và Phạt Vượt Định Mức**

Thay vì trừ chi phí nhiên liệu thực tế, hệ thống sẽ thực hiện đối soát:

* Nếu ![][image6]: Tài xế được hưởng một phần giá trị tiết kiệm được dưới dạng "Thưởng tiết kiệm nhiên liệu". Điều này tạo động lực cho tài xế rèn luyện kỹ năng lái xe sinh thái, giảm thiểu tình trạng nổ máy chờ (idling) vô ích.17  
* Nếu ![][image7]: Tài xế phải giải trình. Nếu không có lý do chính đáng (như hỏng hóc kỹ thuật đột xuất), phần chi phí vượt mức sẽ bị khấu trừ vào lương.

Logic này tách biệt hoàn toàn rủi ro biến động giá dầu khỏi thu nhập của tài xế. Doanh nghiệp chịu trách nhiệm về giá, tài xế chịu trách nhiệm về hiệu suất sử dụng. Sự kết hợp giữa dữ liệu GPS để xác thực quãng đường và dữ liệu hóa đơn nhiên liệu (Fuel Card) giúp ngăn chặn triệt để các hành vi gian lận như hút dầu hoặc kê khai khống.19

## **Vận Hành Vận Chuyển Và Logic Thưởng Chuyến Đa Tầng**

Trong mô hình MVP, thưởng chuyến được tính dựa trên quy tắc toàn cục đơn giản. Một logic vận hành chuyên sâu cần nhận diện rằng không phải mọi km đều có giá trị như nhau và không phải mọi chuyến xe đều có độ khó tương đương.

### **Phân Hóa Quy Tắc Thưởng (Tiered Bonus Rules)**

Hệ thống cần cho phép cấu hình quy tắc thưởng linh hoạt theo nhiều chiều:

* **Theo loại xe:** Lái xe tải nặng hoặc xe chuyên dụng (bồn, lạnh) cần mức thưởng/km cao hơn xe tải nhẹ.  
* **Theo khu vực/tuyến đường:** Các tuyến đường xuyên tâm thành phố hoặc đường đèo dốc cần có hệ số điều chỉnh để bù đắp cho thời gian và rủi ro của tài xế.22  
* **Thưởng theo sản lượng (Achievement Bonus):** Áp dụng lũy tiến để khuyến khích tài xế tối ưu hóa năng suất trong kỳ. Ví dụ: từ km thứ 2.000 trong tháng, đơn giá thưởng mỗi km sẽ tăng thêm 15%.23

### **Trạng Thái Chuyến Và Điểm Chốt Lương**

Logic hiện tại chỉ tính thưởng cho chuyến completed. Tuy nhiên, thời điểm xác định chuyến thuộc về kỳ lương nào cần được chuẩn hóa để tránh tranh chấp. Quy tắc đề xuất là sử dụng thời điểm "Xác nhận hoàn thành bởi khách hàng/người điều phối" thay vì chỉ dựa vào updated\_at của hệ thống. Đối với các chuyến chạy xuyên tháng, hệ thống cần có cơ chế "cắt lát" (splitting) để ghi nhận doanh thu và thưởng tương ứng với khối lượng công việc đã hoàn thành trong từng tháng, đảm bảo tính khớp đúng giữa chi phí và kỳ báo cáo.2

## **Hệ Thống Lương Tài Xế (Driver Payroll): Kiến Trúc Phê Duyệt Và Kiểm soát**

Lương tài xế không chỉ là một bảng tính, nó là kết quả của một quy trình kiểm soát nội bộ nghiêm ngặt. Logic MVP về các trạng thái draft và locked là đúng nhưng chưa đủ. Một quy trình 7 bước chuẩn khoa học cần được tích hợp vào hệ thống để đảm bảo tính minh bạch và khả năng kiểm toán.25

### **Quy Trình Phê Duyệt 7 Bước Tích Hợp**

Để một bảng lương được chi trả, nó phải đi qua các chặng kiểm soát sau:

1. **Thu thập dữ liệu:** Tự động tổng hợp từ chuyến, chi phí nhiên liệu, ngày công và KPI.25  
2. **Đối chiếu và xác nhận:** Người điều phối (Dispatcher) xác nhận số lượng chuyến; Kỹ thuật xác nhận định mức nhiên liệu.26  
3. **Lập bảng tính chi tiết:** Hệ thống áp dụng công thức và các quy tắc tỷ lệ hóa để tạo ra dòng lương payroll\_lines.25  
4. **Kiểm tra và Rà soát:** Bộ phận HR/Kế toán rà soát các khoản khấu trừ thuế, bảo hiểm và các khoản phạt vi phạm.24  
5. **Phê duyệt:** Cấp quản lý cao nhất khóa kỳ lương (locked), chuyển dữ liệu sang trạng thái không thể sửa đổi.25  
6. **Công bố Phiếu lương (Payslip):** Hệ thống gửi thông báo cho tài xế qua ứng dụng di động để đối soát cá nhân.25  
7. **Thanh toán và Lưu trữ:** Ghi nhận chứng từ chi và lưu trữ vết kiểm toán (Audit Trail) để phục vụ kiểm tra thuế hoặc SOC 2\.14

### **Snapshot Dữ Liệu Và Vết Kiểm Toán (Audit Trail)**

Một điểm yếu lớn trong các hệ thống lương đơn giản là khi dữ liệu gốc (như định mức xăng hoặc giá thưởng km) thay đổi, bảng lương của các tháng trước có thể bị tính toán lại sai lệch. Logic chuyên sâu yêu cầu hệ thống phải thực hiện "Snapshot" (chụp ảnh dữ liệu) tại thời điểm khóa kỳ lương. Mọi thông số như lương cơ bản, quy tắc thưởng và định mức nhiên liệu tại thời điểm đó phải được lưu vào trường meta\_json của mỗi dòng lương.27 Điều này đảm bảo rằng dù trong tương lai công ty có thay đổi chính sách lương, dữ liệu lịch sử vẫn được bảo toàn và có thể giải trình được.

## **Quản Trị Hiệu Suất Thông Minh Và AI Business Assist**

Vai trò của Dashboard và AI trong hệ thống mới không chỉ là hiển thị thông tin mà là đưa ra các phân tích mang tính dự báo và kiến nghị.

### **Chỉ Số Hiệu Suất Key Performance Indicators (KPI)**

Thay vì chỉ nhìn vào con số tổng, dashboard cần phân tích sâu vào các KPI vận hành của tài xế 29:

* **OEE (Overall Equipment Effectiveness):** Hiệu suất sử dụng xe, bao gồm thời gian xe chạy có tải so với thời gian nằm bãi.31  
* **Tỷ lệ tiêu hao nhiên liệu so với định mức:** Nhận diện các tài xế có kỹ năng lái xe tốt hoặc các xe cần được bảo trì.32  
* **Chỉ số an toàn:** Tần suất phanh gấp, quá tốc độ lấy từ dữ liệu GPS/Telematics.34

### **AI Trong Việc Tối Ưu Hóa Điều Phối Và Lương**

AI không chỉ là một tính năng phụ trợ. Trong một TMS logic cao, AI sẽ thực hiện các nhiệm vụ:

* **Gợi ý tài xế cho chuyến:** Dựa trên giờ lái còn lại trong ngày (để đảm bảo an toàn và tuân thủ luật lao động), vị trí hiện tại và lịch sử hiệu suất trên tuyến đường đó.6  
* **Phát hiện bất thường (Anomaly Detection):** Tự động gắn cờ các dòng lương có sự biến động lớn so với mức trung bình 3 tháng gần nhất hoặc các hóa đơn nhiên liệu nạp tại các địa điểm nằm ngoài lộ trình của chuyến xe.21

## **Phân Quyền Và Bảo Mật Dữ Liệu Theo Tiêu Chuẩn SOC 2**

Với một hệ thống xử lý dữ liệu nhạy cảm như lương và lịch trình vận tải, việc phân quyền không thể chỉ dừng lại ở ba vai trò admin, manager, staff. Hệ thống cần áp dụng mô hình RBAC (Role-Based Access Control) kết hợp với phân quyền theo phạm vi dữ liệu (Data Scope).14

Ví dụ, một Manager tại Văn phòng Hà Nội chỉ được phép xem và phê duyệt chuyến xe của các tài xế thuộc văn phòng đó, nhưng không được quyền truy cập vào cấu trúc lương cơ bản của công ty. Chỉ những người có vai trò Payroll Specialist mới được quyền thao tác trên các bảng tính lương. Mọi hành động nhạy cảm như sửa đổi mức thưởng hoặc mở khóa kỳ lương đã đóng phải được ghi nhật ký (logging) với đầy đủ thông tin về người thực hiện, thời gian và giá trị cũ/mới để đáp ứng các yêu cầu khắt khe về Processing Integrity trong SOC 2\.28

## **Kết Luận Và Khuyến Nghị Triển Khai**

Sự chuyển dịch từ mô hình Company Ship API MVP sang một hệ thống quản trị vận tải chuyên sâu là hành trình tất yếu để doanh nghiệp đạt tới sự chuyên nghiệp và minh bạch. Bằng cách thay thế cơ chế trừ nhiên liệu thực tế bằng quản trị định mức, tích hợp logic tỷ lệ hóa lương cơ bản và áp dụng quy trình phê duyệt 7 bước, hệ thống không chỉ giải quyết được bài toán vận hành mà còn trở thành một công cụ tài chính đáng tin cậy.

Để triển khai thành công logic mới này, doanh nghiệp cần tập trung vào ba trụ cột:

1. **Chuẩn hóa dữ liệu danh mục:** Thiết lập định mức kỹ thuật chi tiết cho từng phương tiện và dải lương cho từng vị trí.  
2. **Tích hợp dữ liệu thời gian thực:** Kết nối chặt chẽ giữa thiết bị giám sát hành trình GPS và hệ thống thanh toán nhiên liệu để làm nền tảng cho việc đối soát tự động.  
3. **Đào tạo và Truyền thông:** Giúp tài xế hiểu rõ cơ chế thưởng tiết kiệm nhiên liệu và hiệu suất để họ đồng hành cùng mục tiêu tối ưu hóa của công ty.

Hệ thống TMS khi được xây dựng trên một logic nghiệp vụ đúng đắn sẽ không chỉ giúp giảm 15-30% chi phí nhiên liệu và quản lý 2 mà còn tạo ra một môi trường làm việc công bằng, thúc đẩy sự gắn bó của đội ngũ tài xế \- những người trực tiếp tạo ra giá trị cho mỗi chuyến hàng. Đây chính là chìa khóa để doanh nghiệp vận tải không chỉ tồn tại mà còn bứt phá trong kỷ nguyên logistics số hóa.

## Lộ trình triển khai nghiệp vụ (giai đoạn tiếp theo)

### 1) Triển khai lịch trình làm việc tài xế theo cơ chế đăng ký

Mục tiêu của module lịch trình là đảm bảo tài xế chủ động đăng ký ca làm việc, đồng thời hệ thống điều phối có dữ liệu đầu vào chính xác để phân chuyến và phân xe.

- **Nguyên tắc nghiệp vụ**
  - Tài xế phải đăng ký lịch làm việc trước thời điểm chốt lịch (ví dụ: trước 17:00 của ngày hôm trước).
  - Mỗi lịch đăng ký có trạng thái: `draft -> submitted -> approved -> locked`.
  - Sau khi đã `locked`, tài xế không tự sửa; mọi thay đổi phải qua điều phối/manager.
  - Lịch đăng ký là dữ liệu đầu vào bắt buộc cho phân công chuyến.

- **Rule chống trùng phân phối xe**
  - Không cho phép một xe được gán cho hơn một tài xế trong cùng một ngày làm việc (trừ khi có khung giờ không chồng lấn và được bật rule cho phép chia ca).
  - Không cho phép một tài xế nhận hai xe trong cùng một khung giờ.
  - Khi phát hiện trùng, API trả `409 Conflict` với chi tiết bản ghi gây xung đột để UI hiển thị và xử lý.
  - Rule kiểm tra trùng phải chạy cả khi tạo mới và cập nhật lịch.

- **Đề xuất dữ liệu tối thiểu cho lịch**
  - `driver_id`, `work_date`, `shift_code`, `start_time`, `end_time`, `office_id`, `status`, `notes`.
  - Chỉ mục cần có: `(driver_id, work_date)`, `(vehicle_id, work_date)`, `(work_date, status)`.

### 2) Triển khai bảng lương chi tiết và bản xem trước (preview)

Mục tiêu là tách rõ giữa kết quả tính lương tạm thời để rà soát và bảng lương chính thức đã khóa.

- **Luồng nghiệp vụ đề xuất**
  - B1. Tạo kỳ lương `draft`.
  - B2. Hệ thống tính toán `payroll_lines` cho từng tài xế (có thể recalculation nhiều lần khi chưa khóa).
  - B3. Trả về API preview để HR/Manager duyệt.
  - B4. Duyệt kỳ lương (`approved`).
  - B5. Khóa kỳ lương (`locked`) và chụp snapshot.

- **Nội dung bản preview cần hiển thị**
  - Lương cứng, phụ cấp, thưởng chuyến, khấu trừ, thuế, lương thực nhận.
  - Số chuyến hoàn thành, tổng km, cảnh báo vượt định mức nhiên liệu.
  - Ghi chú bất thường (nếu có) để người duyệt xử lý trước khi khóa.

- **Yêu cầu API**
  - Endpoint preview phải phản hồi nhanh và có thể phân trang theo tài xế.
  - Có filter theo `company_id`, `month`, `year`, `status`.
  - Cho phép xuất preview ra file đối soát nội bộ (CSV/XLSX/PDF).

### 3) Triển khai công thức tính lương và phụ cấp từ DB

Mục tiêu là chuyển toàn bộ tham số tính lương từ hard-code sang cấu hình dữ liệu để có thể thay đổi chính sách mà không cần sửa mã nguồn.

- **Thành phần công thức**
  - `base_salary`: lấy theo bậc lương/vị trí trong DB.
  - `trip_bonus`: tính theo rule thưởng chuyến (km, loại xe, tuyến).
  - `allowance`: phụ cấp chức vụ, phụ cấp trách nhiệm, phụ cấp vùng, phụ cấp độc hại.
  - `deduction`: bảo hiểm, thuế, phạt vi phạm, tạm ứng.
  - `net_salary = base_salary + bonus + allowance - deduction - tax`.

- **Nguyên tắc quản trị cấu hình**
  - Mỗi công thức có hiệu lực theo thời gian (`effective_from`, `effective_to`).
  - Mỗi lần thay đổi rule phải có người duyệt và lưu audit log.
  - Khi khóa kỳ lương phải snapshot toàn bộ tham số công thức vào `meta_json` để phục vụ kiểm toán.
  - Công thức phải deterministic: cùng dữ liệu đầu vào phải luôn cho cùng kết quả.

- **Ưu tiên triển khai kỹ thuật**
  - Tách lớp `CalculationService` độc lập để unit test.
  - Cấu hình công thức theo bảng dữ liệu thay vì config file.
  - Có bộ test regression cho các tháng trước để tránh sai lệch lịch sử.

## Workflow nghiệp vụ tổng thể (End-to-End)

Phần này mô tả đầy đủ luồng vận hành theo trình tự thực tế để đội Product, BA, Backend, FE, QA và Ops dùng chung một chuẩn triển khai.

### A. Workflow khởi tạo dữ liệu nền (Master Data Setup)

1. **Tạo Company/Văn phòng/Phòng ban/Vị trí**
   - Admin tạo `company`.
   - Admin tạo `office` thuộc `company`.
   - Admin tạo `department` thuộc `office`.
   - Admin tạo `position` và khai báo mức lương cứng theo bậc.
2. **Tạo tài xế và hồ sơ người dùng**
   - HR/Manager tạo `driver` (thông tin nhân sự + giấy phép + trạng thái).
   - Tạo `user` và liên kết `driver_id`.
   - Gán role (`staff`, `dispatcher`, `manager`, `payroll_specialist`, `admin`).
3. **Tạo phương tiện và thông số vận hành**
   - Khai báo `vehicle` + trạng thái hoạt động.
   - Cấu hình định mức tiêu hao nhiên liệu theo loại xe/tuyến (nếu có module định mức).
4. **Khai báo quy tắc thưởng chuyến**
   - Tạo `trip_bonus_rules` theo km/loại xe/tuyến.
   - Thiết lập hiệu lực thời gian để phục vụ snapshot payroll.

### B. Workflow đăng ký lịch làm việc và phân phối xe

1. **Đăng ký lịch làm việc**
   - Tài xế tạo lịch theo ngày/ca (`draft`).
   - Tài xế gửi duyệt (`submitted`).
2. **Duyệt lịch**
   - Dispatcher/Manager rà soát và duyệt (`approved`) hoặc từ chối (kèm lý do).
   - Đến giờ chốt lịch hệ thống chuyển `locked`.
3. **Phân phối xe**
   - Dispatcher gán xe cho tài xế theo lịch đã duyệt.
   - Hệ thống kiểm tra rule trùng:
     - Không trùng xe cùng ngày/cùng khung giờ.
     - Không trùng tài xế nhiều xe cùng khung giờ.
   - Nếu vi phạm trả `409 Conflict` + payload bản ghi xung đột.
4. **Điều chỉnh khẩn cấp**
   - Chỉ role quản lý mới được override lịch/xe đã lock.
   - Mọi override phải có reason và ghi audit log.

### C. Workflow vận hành chuyến xe

1. **Tạo chuyến**
   - Dispatcher tạo `trip` và gán `driver`, `vehicle`, `customer`.
   - Trạng thái ban đầu: `planned` hoặc `pending`.
2. **Thực hiện chuyến**
   - Cập nhật mốc thời gian (`start_time`, `end_time`), quãng đường (`distance_km`).
   - Cập nhật trạng thái trong vòng đời chuyến (`pending -> in_progress -> completed/cancelled`).
3. **Xác nhận hoàn thành**
   - Chuyến `completed` mới được đưa vào nguồn tính thưởng/lương.
   - Trường hợp chạy xuyên tháng: áp dụng rule phân bổ kỳ (nếu chính sách bật).
4. **Đối soát chi phí chuyến**
   - Ghi nhận `vehicle_expenses` (fuel/maintenance/other).
   - Liên kết chi phí với chuyến hoặc với kỳ thời gian phục vụ payroll.

### D. Workflow tính lương tài xế

1. **Tạo kỳ lương**
   - Payroll specialist tạo kỳ theo `company_id + month + year`.
   - Nếu kỳ đã tồn tại và chưa lock thì cho phép recalculation.
2. **Tính toán dòng lương**
   - Hệ thống tạo `payroll_lines` theo từng tài xế active thuộc công ty.
   - Tính các thành phần:
     - `base_salary`
     - `trip_bonus`
     - `allowance`
     - `deduction`
     - `tax`
     - `net_salary`
3. **Bản xem trước (preview)**
   - Hiển thị chi tiết từng dòng lương.
   - Đánh dấu các bất thường: thiếu dữ liệu, chênh lệch quá ngưỡng, vượt định mức.
4. **Phê duyệt kỳ lương**
   - `draft -> approved`
   - Role duyệt: manager/payroll specialist (theo policy).
5. **Khóa kỳ lương**
   - `approved -> locked`
   - Snapshot toàn bộ tham số công thức và dữ liệu liên quan vào `snapshot_json`/`meta_json`.
   - Kỳ lương locked không cho chỉnh sửa/xóa/tính lại.

### E. Workflow quên mật khẩu và đăng nhập

1. **Login**
   - `POST /api/v1/auth/login` với email/password.
2. **Social Login**
   - `POST /api/v1/auth/social/login` cho Google/Facebook/Apple.
3. **Forgot Password**
   - `POST /api/v1/auth/forgot-password` gửi link reset.
4. **Reset Password**
   - `POST /api/v1/auth/reset-password` với `email + token + password`.
5. **Phiên đăng nhập**
   - `refresh`, `logout`, `auth/me` tuân thủ envelope thống nhất.

### F. Workflow báo cáo và dashboard

1. **Dashboard tổng quan**
   - `GET /api/v1/reports/dashboard`
   - Cache ngắn hạn để tối ưu truy vấn.
2. **Payroll summary**
   - `GET /api/v1/reports/payroll-summary`
   - Trả tổng hợp theo công ty/kỳ lương.
3. **Báo cáo phục vụ điều hành**
   - Tổng chuyến, tỷ lệ hoàn thành, tổng lương, tổng chi phí nhiên liệu.
   - KPI hiệu suất theo tài xế/phương tiện/văn phòng.

### G. Workflow quyền hạn và kiểm soát dữ liệu

1. **Phân quyền truy cập**
   - Public: health/login/forgot/reset.
   - Authenticated: me/chat/my-salary.
   - Admin/Manager: CRUD danh mục, payroll workflow, reports quản trị.
2. **Phạm vi dữ liệu**
   - Role manager chỉ xem được dữ liệu trong company/office được phân quyền.
3. **Nhật ký kiểm toán**
   - Ghi nhận thao tác nhạy cảm: approve/lock payroll, override lịch, thay đổi công thức lương.

### H. Workflow xử lý sự cố và ngoại lệ nghiệp vụ

1. **Thiếu dữ liệu nền**
   - Không có driver/position/base_salary -> tạo cảnh báo và loại khỏi kỳ tính hoặc gắn lỗi chi tiết.
2. **Trùng lịch/xe**
   - Chặn giao dịch tạo/cập nhật, trả thông tin xung đột để UI xử lý.
3. **Kỳ lương đã khóa**
   - Chặn mọi hành động mutate, trả mã lỗi rõ ràng (`403` hoặc `422` theo policy).
4. **Lỗi tích hợp ngoài hệ thống**
   - Retry có kiểm soát cho dịch vụ gửi mail reset password.
   - Log đầy đủ request id để truy vết.

### I. Workflow kiểm thử và nghiệm thu

1. **Unit test**
   - Công thức tính lương, rule thưởng, rule chống trùng lịch.
2. **Feature test**
   - End-to-end từ tạo kỳ lương -> preview -> approve -> lock.
   - Auth flow login/forgot/reset/social.
3. **Regression test**
   - Snapshot kỳ lương cũ không bị ảnh hưởng khi thay đổi công thức mới.
4. **UAT checklist**
   - Đối soát số liệu lương với 1-2 kỳ thực tế.
   - Đối chiếu hành vi phân quyền theo vai trò.

### J. Thứ tự triển khai khuyến nghị (roadmap)

1. Chuẩn hóa master data + RBAC + audit log.
2. Hoàn thiện workflow lịch làm việc + phân phối xe chống trùng.
3. Ổn định workflow payroll (preview/approve/lock + snapshot).
4. Mở rộng báo cáo/KPI và cảnh báo bất thường.
5. Tối ưu hiệu năng, cache và tài liệu API chính thức cho FE.

## Mẫu bảng lương (tham chiếu nghiệp vụ)

### 1) Mẫu bảng lương chi tiết theo tài xế (payroll_lines)

| STT | Mã NV/Tài xế | Họ tên | Kỳ lương | Lương cứng | Thưởng chuyến | Phụ cấp | Khấu trừ BH | Chi phí nhiên liệu | Thuế TNCN | Thực nhận | Số chuyến | Tổng km | Trạng thái |
| :-- | :-- | :-- | :-- | --: | --: | --: | --: | --: | --: | --: | --: | --: | :-- |
| 1 | DRV001 | Nguyễn Văn A | 06/2026 | 10,000,000 | 2,500,000 | 700,000 | 1,050,000 | 500,000 | 200,000 | 11,450,000 | 42 | 1,850 | draft |
| 2 | DRV002 | Trần Văn B | 06/2026 | 9,000,000 | 1,900,000 | 500,000 | 945,000 | 620,000 | 150,000 | 9,685,000 | 35 | 1,420 | draft |
| 3 | DRV003 | Lê Văn C | 06/2026 | 12,000,000 | 3,100,000 | 900,000 | 1,260,000 | 780,000 | 320,000 | 13,640,000 | 51 | 2,230 | draft |

> Công thức chuẩn:
>
> `Thực nhận = Lương cứng + Thưởng chuyến + Phụ cấp - Khấu trừ BH - Chi phí nhiên liệu - Thuế TNCN`

### 2) Mẫu bảng tổng hợp kỳ lương (payroll summary)

| Chỉ tiêu | Giá trị |
| :-- | --: |
| Kỳ lương | 06/2026 |
| Công ty | Company A |
| Tổng số tài xế trong kỳ | 120 |
| Số tài xế có phát sinh lương | 117 |
| Tổng lương cứng | 1,180,000,000 |
| Tổng thưởng chuyến | 265,000,000 |
| Tổng phụ cấp | 88,000,000 |
| Tổng khấu trừ BH | 123,900,000 |
| Tổng chi phí nhiên liệu phân bổ | 74,500,000 |
| Tổng thuế TNCN | 32,000,000 |
| **Tổng thực nhận** | **1,302,600,000** |
| Trạng thái kỳ lương | approved |

### 3) Mẫu payload API trả về cho preview lương

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "payroll": {
      "id": 101,
      "company_id": 1,
      "month": 6,
      "year": 2026,
      "status": "draft"
    },
    "lines": [
      {
        "driver_id": 1,
        "base_salary": 10000000,
        "trip_bonus": 2500000,
        "allowance": 700000,
        "deduction": 1050000,
        "fuel_cost": 500000,
        "tax": 200000,
        "net_salary": 11450000,
        "trips_completed_count": 42,
        "total_distance_km": 1850
      }
    ],
    "summary": {
      "employees_count": 117,
      "total_net_salary": 1302600000
    }
  }
}
```

### 4) Cột bắt buộc đề xuất khi export bảng lương

- Thông tin định danh: `driver_code`, `driver_name`, `company`, `office`, `department`
- Thông tin kỳ lương: `month`, `year`, `payroll_status`, `approved_at`, `locked_at`
- Thành phần lương: `base_salary`, `trip_bonus`, `allowance`, `deduction`, `fuel_cost`, `tax`, `net_salary`
- Chỉ số vận hành: `trips_completed_count`, `total_distance_km`
- Kiểm toán: `meta_json.lock_snapshot`, `updated_by`, `updated_at`
## Rule và validate bắt buộc triển khai

### 1) Rule/validate chung cho toàn hệ thống

- Mọi request tạo/cập nhật phải dùng whitelist field (`validated()`), không nhận payload dư.
- Trả lỗi chuẩn:
  - `422` cho validation lỗi dữ liệu.
  - `409` cho xung đột nghiệp vụ (trùng lịch, trùng phân phối xe, trùng kỳ lương không cho phép).
  - `403` cho thao tác bị chặn bởi trạng thái (`locked`) hoặc thiếu quyền.
- Chuẩn hóa timezone khi validate thời gian (`Asia/Ho_Chi_Minh`) để tránh lệch ngày.
- Các thao tác nhạy cảm phải log audit: người thao tác, trước/sau thay đổi, thời điểm, lý do.

### 2) Rule/validate cho lịch làm việc tài xế

- `driver_id` bắt buộc tồn tại và đang `active`.
- `work_date` không được ở quá khứ nếu policy không cho backdate.
- `shift_start < shift_end`.
- Không được trùng ca cùng tài xế trong cùng ngày.
- Nếu lịch đã `locked`:
  - Tài xế không được sửa/xóa.
  - Chỉ manager/dispatcher được override với `override_reason` bắt buộc.

### 3) Rule/validate cho phân phối xe

- `vehicle_id` bắt buộc tồn tại và `active`.
- Cấm trùng xe cho 2 tài xế trong cùng ngày/cùng khung giờ.
- Cấm trùng tài xế với 2 xe trong cùng khung giờ.
- Nếu xe đang bảo trì hoặc hết hạn đăng kiểm/bảo hiểm thì không cho phân công.
- Khi cập nhật phân phối phải re-check toàn bộ rule trùng như lúc tạo mới.

### 4) Rule/validate cho chuyến xe

- `driver_id`, `vehicle_id`, `customer_id` bắt buộc tồn tại.
- Chỉ cho `driver` thuộc đúng phạm vi company/office theo policy.
- `distance_km >= 0`.
- Không cho chuyển trạng thái ngược chiều workflow (ví dụ `completed -> pending`).
- Chỉ chuyến `completed` mới được tính vào payroll.
- Chuyến bị `cancelled` phải có `cancel_reason`.

### 5) Rule/validate cho payroll

- Khóa duy nhất kỳ lương theo `(company_id, month, year)`.
- `month` trong `[1..12]`, `year` trong khoảng policy.
- Khi `status = locked`:
  - Cấm `update`, `delete`, `recalculate`.
  - Cấm sửa `payroll_lines`.
- `net_salary` không âm (nếu chính sách yêu cầu), hoặc nếu âm phải có cờ xác nhận đặc biệt.
- Snapshot bắt buộc khi lock:
  - công thức áp dụng,
  - bonus rules,
  - thông số phụ cấp/khấu trừ,
  - timestamp + actor.

### 6) Rule/validate cho auth và tài khoản

- Login chỉ cho user `status = active`.
- Social login:
  - Bắt buộc verify token issuer/audience/signature theo provider.
  - Chỉ link theo email khi provider xác nhận `email_verified = true`.
- Forgot/reset password:
  - `token` bắt buộc và còn hiệu lực.
  - `password_confirmation` phải khớp.
  - Áp dụng policy độ mạnh mật khẩu (tối thiểu 8 ký tự, khuyến nghị gồm chữ hoa/thường/số/ký tự đặc biệt).
- Rate limit cho login/forgot password để giảm brute-force.

### 7) Rule/validate cho báo cáo

- `month`, `year` validate phạm vi hợp lệ.
- `company_id` bắt buộc cho payroll summary.
- Chỉ role hợp lệ mới truy cập báo cáo quản trị.
- Dữ liệu cache phải có TTL rõ ràng và cơ chế invalidate khi dữ liệu nguồn thay đổi.

### 8) Rule/validate cho dữ liệu danh mục

- `code` định danh (nếu có) phải unique theo phạm vi company.
- Trạng thái chỉ nhận giá trị trong enum chuẩn (`active`, `inactive`, ...).
- Không cho xóa cứng bản ghi đang có quan hệ phát sinh nghiệp vụ; ưu tiên `soft delete`.

### 9) Checklist test bắt buộc theo rule

- Test thành công (happy path) cho từng endpoint chính.
- Test validation lỗi (`422`) cho từng field quan trọng.
- Test conflict (`409`) cho rule chống trùng lịch/phân phối xe.
- Test permission (`401/403`) theo role.
- Test locked-state cho payroll (không mutate được).
- Test regression khi thay đổi công thức lương (snapshot cũ không đổi).

## Bổ sung lỗ hổng nghiệp vụ và kế hoạch khắc phục

Phần này tổng hợp các lỗ hổng trọng yếu ảnh hưởng trực tiếp tính đúng đắn của payroll và tính toàn vẹn vận hành. Mục tiêu là chuyển toàn bộ khoảng trống thành workflow có thể triển khai.

### A) Nhóm khẩn cấp (đỏ)

#### A.1 Leave Management (bắt buộc có trước khi proration chính xác)

- **Workflow đề xuất**
  1. Tài xế tạo yêu cầu nghỉ: `annual`, `sick`, `maternity`, `unpaid`.
  2. Trạng thái: `draft -> submitted -> approved/rejected -> locked`.
  3. Khi `approved`, hệ thống cập nhật work schedule tương ứng (auto block ca đã đăng ký).
  4. Khi đến kỳ payroll, service tính `working_days` lấy từ lịch đã trừ leave được duyệt.
- **Rule chính**
  - Nghỉ không lương (`unpaid`) bắt buộc làm giảm `base_salary` theo proration.
  - Nghỉ có lương không làm giảm lương cứng nhưng có thể ảnh hưởng KPI thưởng.
  - Không cho duyệt leave trùng ngày đã khóa payroll (trừ luồng amendment).

#### A.2 Payroll Amendment / Supplementary Payroll

- **Không cho mở khóa tùy tiện**, thay vào đó ưu tiên 2 cơ chế:
  - `supplementary payroll` tham chiếu kỳ gốc.
  - hoặc `controlled unlock` (2 cấp duyệt + reason bắt buộc + audit đầy đủ).
- **Workflow Supplementary**
  1. Tạo phiếu điều chỉnh tham chiếu `origin_payroll_id`.
  2. Chỉ chứa phần chênh lệch (+/-) theo từng tài xế.
  3. Duyệt và khóa độc lập.
  4. Báo cáo tổng hiển thị: `net_final = payroll_goc + payroll_dieu_chinh`.

#### A.3 Salary Advance (tạm ứng lương)

- **Workflow**
  1. Tài xế gửi yêu cầu tạm ứng.
  2. Manager/Payroll Specialist duyệt.
  3. Ghi nhận giải ngân (`advance_disbursement`).
  4. Tự động tạo khoản `deduction` cho kỳ kế tiếp hoặc chia kỳ theo policy.
- **Rule**
  - Giới hạn tối đa: `% lương cứng` hoặc trần cố định.
  - Giới hạn số lần tạm ứng/tháng.
  - Chặn tạm ứng khi còn nợ quá hạn chưa khấu trừ.

#### A.4 Cross-month Trip Splitting (quy tắc cắt lát chuyến xuyên tháng)

- **Chuẩn tính đề xuất**
  - Ghi nhận theo **thời điểm hoàn thành phần việc** (km hoặc thời gian) trong từng kỳ.
  - `trip_bonus` phân bổ theo tỷ lệ km hoàn thành trong tháng.
  - Rule áp dụng theo `effective_from` tại **thời điểm phần km phát sinh**, không theo ngày tạo payroll.
- **Công thức gợi ý**
  - `bonus_month_n = total_bonus_trip * (km_month_n / total_km_trip)`
  - Nếu không có GPS km theo đoạn, fallback theo tỷ lệ thời gian trong từng tháng.

### B) Nhóm cao (cam)

#### B.1 Vehicle Maintenance Workflow

- Tạo lệnh bảo trì: `planned`, `in_progress`, `completed`, `cancelled`.
- Khi xe `in_progress`, chặn phân công chuyến mới.
- Chi phí bảo trì phải gắn `company_id`, `office_id`, kỳ hạch toán.
- Cảnh báo bảo trì định kỳ trước N ngày hoặc N km.

#### B.2 Violation & Dispute Workflow

- Vi phạm có thể đến từ:
  - telematics tự động (quá tốc độ, phanh gấp),
  - hoặc dispatcher nhập tay.
- Trạng thái vi phạm: `pending_review -> confirmed/rejected -> deducted`.
- Tài xế có quyền khiếu nại trong SLA (ví dụ 3 ngày làm việc).
- Chỉ khấu trừ khi vi phạm đã `confirmed`.

#### B.3 Onboarding/Offboarding tài xế

- **Onboarding**: hồ sơ, chứng chỉ, cấp tài khoản, phân role, cấp thẻ nhiên liệu/thiết bị.
- **Offboarding**: thu hồi xe/thẻ/thiết bị, đối soát công nợ/tạm ứng, quyết toán cuối.
- User account:
  - revoke token ngay khi offboard,
  - giữ lịch sử để audit.

#### B.4 Bonus Rule Versioning trong kỳ

- Rule thưởng phải có version + hiệu lực thời gian.
- Trip completed trước/sau mốc thay đổi rule dùng version tương ứng.
- Snapshot version rule vào từng payroll line khi lock.

### C) Nhóm trung bình (vàng)

#### C.1 Co-driver model

- Bổ sung bảng `trip_drivers` (main/co-driver, tỷ lệ chia thưởng).
- Rule tính thưởng:
  - chia đều,
  - chia theo vai trò,
  - hoặc chỉ tài xế chính (theo policy cấu hình).

#### C.2 Customer Completion Confirmation

- Kênh xác nhận: app web link, OTP SMS, email.
- Nếu quá hạn X ngày không xác nhận:
  - auto-complete có cờ `system_confirmed`,
  - đưa vào queue rà soát hậu kiểm.

#### C.3 Kết nối kế toán

- Sau khi payroll lock:
  - export chuẩn (CSV/XLSX/API),
  - hoặc webhook sang ERP/accounting.
- Có mapping tài khoản kế toán cho lương/BHXH/thuế/phụ cấp.

#### C.4 Hoàn ứng chi phí tài xế (out-of-pocket)

- Luồng: khai báo chứng từ -> duyệt -> chi trả (payroll hoặc payment riêng).
- Tách bạch với fuel cost để tránh cộng trừ sai vào net salary.

### D) Nhóm kỹ thuật dữ liệu (xanh dương)

#### D.1 Certificate expiry khi đang in-progress

- Nếu chứng chỉ hết hạn giữa chuyến:
  - không auto kill chuyến ngay,
  - cảnh báo dispatcher,
  - chặn nhận chuyến mới sau điểm dừng an toàn.

#### D.2 Timezone strategy

- Lưu thời gian chuẩn `UTC`.
- Lưu thêm timezone nghiệp vụ tại site/chuyến để hiển thị local time.
- Rule chốt kỳ lương dùng timezone của `company/office`.

#### D.3 Phân loại hợp đồng tài xế

- Bổ sung `contract_type`: `fulltime`, `parttime`, `seasonal`, `contractor`.
- Mỗi loại hợp đồng có bộ rule riêng cho:
  - bảo hiểm,
  - thuế,
  - phụ cấp,
  - điều kiện tạm ứng.

## Ma trận ưu tiên triển khai

| Mức độ | Hạng mục |
| :-- | :-- |
| 🔴 Khẩn | Leave management, Payroll amendment/supplementary payroll, Salary advance, Cross-month trip splitting |
| 🟠 Cao | Maintenance workflow, Violation-dispute workflow, Onboarding/offboarding, Bonus rule versioning |
| 🟡 Trung bình | Co-driver model, Customer confirmation, Accounting export, Out-of-pocket reimbursement |
| 🔵 Kỹ thuật | Contract type, Certificate expiry mid-trip rule, Timezone strategy |

## Bổ sung lỗ hổng nghiệp vụ vòng 2 và phương án chốt rule

### A) Nhóm nghiêm trọng (đỏ) cần đóng ngay

#### A.1 Đối soát BHXH với cơ quan nhà nước

- **Workflow bắt buộc**
  1. Chốt danh sách lao động tham gia BHXH theo tháng.
  2. Sinh bảng kê BHXH (định dạng theo biểu mẫu nội bộ/mapping D02-LT).
  3. Đối soát với danh sách thực nộp.
  4. Ghi nhận chênh lệch và tạo phiếu điều chỉnh kỳ sau.
- **Rule**
  - Chỉ tài xế đủ điều kiện hợp đồng mới phát sinh khoản BHXH.
  - Có cờ trạng thái tham gia BHXH theo tháng (`eligible`, `not_eligible`, `suspended`).
  - Snapshot tham số BHXH trong payroll locked để phục vụ thanh tra.

#### A.2 Thuế TNCN chuẩn Việt Nam

- **Workflow**
  1. Khai báo thông tin thuế cá nhân + người phụ thuộc.
  2. Tính thu nhập chịu thuế theo từng kỳ.
  3. Áp dụng giảm trừ gia cảnh.
  4. Áp dụng biểu thuế lũy tiến từng phần.
  5. Tổng hợp quyết toán năm.
- **Rule**
  - Lưu version biểu thuế theo hiệu lực thời gian.
  - Tài xế đa nguồn thu nhập phải có cờ khai báo và logic quyết toán riêng.
  - Mọi dòng thuế phải trace được công thức và tham số nguồn.

#### A.3 Edge case tạm ứng khi nghỉ việc

- **Rule chốt**
  - Khi offboarding, hệ thống auto tính `outstanding_advance`.
  - Ưu tiên trừ toàn bộ vào khoản thanh toán cuối.
  - Nếu vẫn còn dư nợ: chuyển trạng thái `receivable` và sinh hồ sơ công nợ.
  - Không cho hoàn tất offboarding nếu chưa đóng hồ sơ công nợ (hoặc chưa có xác nhận miễn trừ).

### B) Nhóm quan trọng (cam) cần chuẩn hóa sâu

#### B.1 Leave tương tác với bonus/KPI

- **Rule cụ thể**
  - Nghỉ phép năm có lương: không giảm lương cứng.
  - Nghỉ ốm/không lương: không tạo km/không tính quota vận hành.
  - Ngưỡng bonus lũy tiến theo km phải được **prorate theo số ngày làm việc thực tế**:
    - `km_threshold_effective = km_threshold_month * (working_days_actual / working_days_standard)`.

#### B.2 Phân loại bảo trì và trách nhiệm chi phí

- Bảo trì định kỳ (`preventive`): chi phí vận hành công ty.
- Sửa chữa do lỗi tài xế (`driver_fault`): có thể tạo deduction theo policy.
- Sự cố kỹ thuật (`mechanical_failure`): công ty chịu.
- Mọi phiếu bảo trì phải có:
  - loại bảo trì,
  - kết luận nguyên nhân,
  - quyết định người chịu chi phí.

#### B.3 Offboarding và payslip cuối kỳ

- Nếu nghỉ việc giữa tháng:
  - sinh `final_settlement_line` ngay tại thời điểm offboarding (không đợi cuối tháng),
  - sau đó hợp nhất vào kỳ payroll hoặc supplementary payroll gần nhất.
- Điều này tránh treo công nợ/thu nhập đến cuối kỳ.

### C) Nhóm vận hành (vàng) cần hoàn thiện

#### C.1 Tranh chấp xác nhận khách hàng

- Bổ sung trạng thái chuyến:
  - `completed_pending_confirmation`
  - `disputed`
  - `confirmed`
- Khi `disputed`:
  - tạm treo thưởng chuyến,
  - mở ticket xử lý,
  - có SLA xử lý trước ngày lock payroll.

#### C.2 Workflow khách hàng và hợp đồng vận chuyển

- Bổ sung module:
  - hồ sơ khách hàng,
  - hợp đồng giá cước/SLA,
  - điều khoản tuyến cố định,
  - công nợ phải thu.
- Dữ liệu hợp đồng là đầu vào bắt buộc cho phân tích doanh thu văn phòng.

#### C.3 Hoàn ứng chi phí có kiểm soát

- Bổ sung danh mục whitelist chi phí được hoàn ứng.
- Thiết lập hạn mức:
  - tối đa/chuyến,
  - tối đa/tháng,
  - ngưỡng cần chứng từ bắt buộc.
- Không có chứng từ hợp lệ -> trạng thái `pending_review` hoặc `rejected`.

### D) Nhóm kỹ thuật dữ liệu (xanh dương) cần khóa kiến trúc

#### D.1 Biến động cấu trúc tổ chức và dữ liệu lịch sử

- Không cascade delete các thực thể tổ chức đã phát sinh payroll locked.
- Dùng cơ chế `merge/archive` thay vì xóa cứng office/department.
- Lưu trường hiển thị snapshot (tên office/department tại thời điểm lock) để giữ tính lịch sử.

#### D.2 Payroll quy mô lớn (500+ tài xế)

- **Chiến lược đề xuất**
  - chuyển tính payroll sang async job theo batch.
  - Có progress tracking theo `batch_id`.
  - Khi lỗi 1 tài xế:
    - không rollback toàn bộ,
    - gắn cờ `line_error` và cho phép reprocess riêng.
- Kênh thông báo hoàn thành:
  - polling API,
  - hoặc webhook/event bus.

#### D.3 Idempotency cho thao tác tài chính

- Bắt buộc `idempotency_key` cho endpoint nhạy cảm:
  - lock payroll,
  - approve payroll,
  - approve advance,
  - disbursement.
- Nếu key trùng:
  - trả lại kết quả lần đầu,
  - không thực thi mutate lần 2.
- Kết hợp kiểm tra trạng thái trước khi mutate để bảo vệ hai lớp.

## Bổ sung lỗ hổng nghiệp vụ vòng 3 và phương án đóng gap

### A) Nhóm khẩn cấp pháp lý (đỏ)

#### A.1 Tính lương làm thêm giờ (Overtime) theo Bộ luật Lao động

- **Workflow bắt buộc**
  1. Ghi nhận giờ làm việc thực tế theo ca/chuyến (`actual_start`, `actual_end`).
  2. Tách giờ chuẩn và giờ vượt chuẩn theo ngày.
  3. Tạo phiếu OT (`overtime_request`) có trạng thái `submitted -> approved -> paid`.
  4. Chỉ OT đã duyệt mới vào payroll.
- **Rule pháp lý tối thiểu**
  - Ngày thường: tối thiểu 150% đơn giá giờ.
  - Ngày nghỉ hằng tuần: tối thiểu 200%.
  - Ngày lễ/tết: tối thiểu 300% (chưa gồm lương ngày lễ nếu pháp luật yêu cầu cộng thêm theo policy doanh nghiệp).
- **Rule dữ liệu**
  - Không cho OT âm hoặc chồng chéo.
  - Mọi OT phải truy vết được từ log ca/chuyến.

#### A.2 Phụ cấp làm đêm (Night Shift Differential)

- Khung giờ đêm mặc định: `22:00 -> 06:00`.
- Tự động tính số giờ làm đêm trên từng ca/chuyến.
- Áp phụ cấp tối thiểu 30% trên đơn giá giờ ban ngày cho phần giờ đêm.
- Nếu vừa OT vừa night shift, cần policy cộng gộp rõ ràng (cộng dồn hay ưu tiên mức cao hơn).

#### A.3 Lịch ngày lễ/tết quốc gia (Public Holiday Calendar)

- Bổ sung bảng danh mục ngày lễ theo năm: mã ngày lễ, ngày bắt đầu/kết thúc, loại nghỉ.
- Rule payroll:
  - Nghỉ đúng ngày lễ không trừ `working_days` khi prorate.
  - Làm việc ngày lễ tự sinh hệ số lương lễ theo policy.
- Cho phép cấu hình thêm ngày nghỉ bù nội bộ công ty.

#### A.4 Quyết toán PIT cuối năm (TNCN Year-end Finalization)

- **Workflow**
  1. Chốt dữ liệu PIT cả năm.
  2. Phân loại đối tượng:
     - công ty quyết toán thay (có ủy quyền),
     - cá nhân tự quyết toán.
  3. Sinh chứng từ khấu trừ thuế cho lao động nghỉ việc trong năm.
  4. Đóng hồ sơ quyết toán theo deadline pháp lý.
- **Rule**
  - Có cờ ủy quyền quyết toán theo nhân sự.
  - Lưu lịch sử người phụ thuộc và hiệu lực giảm trừ theo thời gian.

### B) Nhóm quan trọng (cam)

#### B.1 Race condition khi phân công xe

- **Chiến lược bắt buộc**
  - Dùng transaction + lock khi ghi phân công (`vehicle_id`, `work_date`, `time_range`).
  - Có unique/index hỗ trợ chống double-booking ở tầng DB.
- **Phương án xử lý**
  - Nếu conflict: trả `409` kèm thông tin bản ghi thắng lock.
  - UI bắt buộc refresh trạng thái và yêu cầu dispatcher phân công lại.

#### B.2 Đối soát Fuel Card thực tế

- Import giao dịch thẻ nhiên liệu từ file/API nhà cung cấp.
- Matching tự động với chuyến theo thời gian, vị trí, xe, tài xế.
- Giao dịch unmatched vào hàng chờ đối soát, có SLA xử lý.
- Giao dịch ngoài lộ trình: tạo cờ bất thường + mở luồng xác minh.

#### B.3 Chu kỳ thăng bậc lương và đánh giá hiệu suất

- Chu kỳ đánh giá mặc định: 6 tháng hoặc 12 tháng.
- Workflow: đánh giá -> đề xuất bậc -> duyệt -> hiệu lực.
- Nếu tăng bậc giữa tháng:
  - áp dụng prorate theo ngày hiệu lực,
  - snapshot rõ trước/sau tăng bậc trong payroll line.

#### B.4 Chính sách lưu trữ dữ liệu (Data Retention)

- Payroll locked + chứng từ kế toán: lưu tối thiểu theo luật hiện hành (khuyến nghị 10 năm).
- Audit log: lưu dài hạn, không được sửa.
- GPS/hành trình: lưu theo tier (hot/warm/archive) để cân bằng chi phí.
- Dữ liệu cá nhân: có luồng ẩn danh/xóa theo Nghị định 13/2023 và chính sách nội bộ.

### C) Nhóm vận hành (vàng)

#### C.1 Scope nghiệp vụ cho Driver App

- Chức năng nên có:
  - xem lịch đã duyệt,
  - đăng ký ca/nghỉ phép,
  - xem payslip và gửi khiếu nại,
  - xem vi phạm và phản hồi,
  - xác nhận mốc chuyến.
- Chức năng không được có:
  - duyệt payroll,
  - sửa dữ liệu đã lock,
  - xem dữ liệu người khác ngoài phạm vi được phân quyền.

#### C.2 SLA thông báo payslip trước khi lock

- Gửi payslip preview trước ngày lock (ví dụ T-3).
- Cửa sổ khiếu nại (ví dụ 48h).
- Hết SLA không phản hồi:
  - mặc định đồng ý theo policy,
  - nhưng vẫn cho phép khiếu nại hậu kiểm qua supplementary payroll.

#### C.3 Backup dispatcher

- Có cơ chế phân quyền tạm quyền dispatcher khi vắng mặt đột xuất.
- Nếu thiếu dispatcher:
  - không cho tự nhận chuyến trừ khi policy đặc biệt được bật,
  - mọi tự nhận phải qua rule kiểm tra an toàn và ghi audit.

### D) Nhóm kỹ thuật bắt buộc chuẩn hóa (xanh dương)

#### D.1 Transaction boundary cho nghiệp vụ tài chính

- Bắt buộc atomic transaction cho các luồng:
  - lock payroll: update status + snapshot + audit log.
  - approve advance: disbursement + deduction future.
  - supplementary payroll posting.
- Nếu fail giữa chừng:
  - rollback toàn bộ,
  - trả mã lỗi có thể retry,
  - tạo incident log để xử lý manual nếu retry thất bại.

#### D.2 Chuẩn làm tròn và độ chính xác tiền tệ

- Chuẩn lưu DB: decimal cố định (khuyến nghị `decimal(18,2)` cho VND).
- Chuẩn làm tròn:
  - round half up đến đơn vị cấu hình (`1` đồng hoặc `1000` đồng).
  - thống nhất một rule cho tất cả service.
- Khi chia co-driver:
  - phần lẻ quy về main driver hoặc quỹ làm tròn (phải có policy rõ).
- Bắt buộc kiểm tra cân bằng:
  - tổng `net_salary` lines phải khớp summary sau khi làm tròn.

## Ma trận ưu tiên bổ sung vòng 3

| Mức độ | Hạng mục |
| :-- | :-- |
| 🔴 Khẩn | Overtime theo luật, phụ cấp ca đêm, lịch ngày lễ, PIT finalization |
| 🟠 Cao | Race condition strategy, fuel card reconciliation, salary grade cycle, data retention |
| 🟡 Trung bình | Driver app scope, payslip dispute SLA, backup dispatcher |
| 🔵 Kỹ thuật | Transaction boundary, rounding/precision strategy |

> Lưu ý pháp lý: Overtime, night shift và public holiday là nhóm có rủi ro thanh tra lao động cao nhất, cần ưu tiên triển khai trước các tối ưu kỹ thuật khác.

## Bổ sung lỗ hổng nghiệp vụ vòng 4 và phương án đóng gap

### A) Nhóm khẩn cấp (đỏ)

#### A.1 Attendance Verification (chấm công thực tế)

- **Workflow bắt buộc**
  1. Tài xế check-in/check-out ca qua app (GPS + timestamp).
  2. Dispatcher xác nhận ca trong trường hợp ngoại lệ.
  3. Hệ thống sinh `attendance_status`: `present`, `late`, `absent`, `partial`.
  4. Payroll lấy `working_days` từ attendance thực tế, không lấy trực tiếp từ lịch đăng ký.
- **Rule**
  - Lịch `locked` chỉ là kế hoạch, không phải bằng chứng đi làm.
  - Mọi chỉnh sửa chấm công hậu kiểm phải có `adjust_reason` + audit log.
  - Chốt attendance trước khi chốt payroll (SLA rõ ràng).

#### A.2 Hours of Service (HOS) compliance

- **Mục tiêu pháp lý**: kiểm soát giới hạn lái liên tục và tổng giờ lái/ngày theo quy định hiện hành.
- **Workflow**
  1. Tích lũy giờ lái theo tài xế theo ngày/tuần.
  2. Trước khi phân công chuyến, chạy rule pre-check HOS.
  3. Nếu vượt ngưỡng: chặn phân công + cảnh báo dispatcher.
  4. Lưu báo cáo tuân thủ HOS phục vụ thanh tra.
- **Rule**
  - Không cho assign chuyến mới khi vi phạm HOS.
  - Chuyến đang chạy gần ngưỡng phải có cảnh báo sớm (threshold warning).

#### A.3 Minimum wage floor check

- **Workflow**
  1. Map office vào vùng lương tối thiểu I/II/III/IV.
  2. Khi tính payroll, kiểm tra `net_salary` và các khoản khấu trừ theo policy pháp lý.
  3. Nếu vi phạm ngưỡng tối thiểu: block hoặc đưa vào queue phê duyệt đặc biệt.
- **Rule**
  - Bảng lương tối thiểu theo vùng phải version theo năm hiệu lực.
  - Quy định thứ tự ưu tiên khấu trừ khi gần chạm sàn lương (ví dụ: deduction tự nguyện sau, deduction bắt buộc trước).

### B) Nhóm quan trọng (cam)

#### B.1 Tai nạn và bồi thường bảo hiểm

- **Workflow**
  1. Tạo hồ sơ tai nạn (`accident_report`) gắn `trip_id`, `driver_id`, `vehicle_id`.
  2. Phân loại nguyên nhân: `driver_fault`, `third_party_fault`, `force_majeure`, `mechanical`.
  3. Theo dõi claim bảo hiểm: `submitted -> under_review -> approved/rejected -> paid`.
  4. Nếu lỗi tài xế, tạo kế hoạch khấu trừ nhiều kỳ theo policy.
- **Rule**
  - Không cho khấu trừ trực tiếp nếu chưa có kết luận trách nhiệm.
  - Khoản bồi thường bảo hiểm phải hạch toán riêng, không trộn deduction payroll.

#### B.2 Suspension workflow (đình chỉ)

- Bổ sung trạng thái nhân sự:
  - `suspended_with_pay`
  - `suspended_without_pay`
  - `suspended_safety_hold`
- **Rule payroll**
  - with pay: vẫn tính lương cứng theo policy.
  - without pay: prorate giảm tương ứng.
  - safety hold: chặn nhận chuyến mới, giữ trạng thái nhân sự để có thể quay lại.

#### B.3 Medical fitness tracking

- Quản lý giấy khám sức khỏe: loại, ngày cấp, ngày hết hạn, nơi cấp.
- Cảnh báo trước hạn N ngày.
- Hết hạn:
  - chặn phân công chuyến mới,
  - giữ lịch sử và lý do chặn để phục vụ kiểm tra.

#### B.4 Điều chuyển tài xế giữa văn phòng giữa kỳ

- **Workflow**
  1. Tạo quyết định điều chuyển (effective date).
  2. Duyệt quyết định.
  3. Payroll tự chia cost center theo mốc hiệu lực.
- **Rule**
  - Điều chuyển giữa tháng phải proration chi phí theo số ngày ở từng văn phòng.
  - Chuyến đã hoàn thành trước mốc thuộc cost center cũ; sau mốc thuộc cost center mới.

### C) Nhóm vận hành (vàng)

#### C.1 Notification architecture

- Định nghĩa ma trận kênh:
  - khẩn cấp: push + SMS
  - nghiệp vụ thường: in-app + email
  - chứng từ/pháp lý: email + in-app ack
- Có retry/backoff cho gửi thất bại.
- Có cơ chế `read_ack` cho thông báo quan trọng (payslip, vi phạm, quyết định kỷ luật).

#### C.2 Budget vs Actual

- Bổ sung module ngân sách theo tháng/quý cho từng office/company:
  - payroll budget
  - fuel budget
  - maintenance budget
- Dashboard phải hiển thị variance:
  - `actual`
  - `budget`
  - `% over/under`.

#### C.3 Tax code onboarding (MST)

- Bổ sung trường MST cá nhân + trạng thái xác thực.
- SLA hoàn thiện MST sau onboarding.
- Nếu chưa có MST:
  - áp dụng rule PIT mặc định theo chính sách pháp lý,
  - gắn cờ cần hoàn thiện hồ sơ.

### D) Nhóm kỹ thuật (xanh dương)

#### D.1 Downtime recovery cho payroll batch

- Bổ sung lifecycle batch:
  - `pending`
  - `running`
  - `partial_complete`
  - `failed`
  - `completed`.
- Khi hệ thống down giữa chừng:
  - không cập nhật payroll sang trạng thái cuối nếu batch chưa completed.
  - hỗ trợ resume từ checkpoint.
  - yêu cầu manual review với batch `partial_complete`.

#### D.2 API versioning strategy

- Chính sách chạy song song `v1` và `v2` theo thời hạn công bố.
- Có deprecation notice:
  - response header,
  - changelog,
  - thông báo cho FE/mobile/ERP integrators.
- Snapshot payroll nên lưu thêm `calculation_version`/`api_version` để replay đúng logic lịch sử.

## Ma trận ưu tiên bổ sung vòng 4

| Mức độ | Hạng mục |
| :-- | :-- |
| 🔴 Khẩn | Attendance verification, HOS compliance, minimum wage floor check |
| 🟠 Cao | Accident & insurance, suspension workflow, medical fitness, inter-office transfer mid-payroll |
| 🟡 Trung bình | Notification architecture, budget vs actual, tax code onboarding |
| 🔵 Kỹ thuật | Downtime recovery cho payroll batch, API versioning strategy |

> Ghi chú trọng yếu: Attendance verification và HOS là nền tảng dữ liệu thực địa. Nếu chưa có hai lớp kiểm soát này, proration/OT dù đúng công thức vẫn không phản ánh đúng thực tế vận hành.

## Bổ sung lỗ hổng nghiệp vụ vòng 5 và phương án đóng gap

### A) Nhóm khẩn cấp (đỏ)

#### A.1 Consent & Policy Acknowledgment (đồng thuận chính sách lương)

- **Workflow**
  1. Onboarding: tài xế ký/xác nhận điện tử bộ chính sách lương-thưởng-phạt.
  2. Khi policy thay đổi: phát hành policy version mới.
  3. Hệ thống yêu cầu tài xế đọc/xác nhận trước ngày hiệu lực.
  4. Nếu chưa xác nhận đúng hạn: cảnh báo HR/manager và xử lý theo nội quy.
- **Rule**
  - Mọi khoản deduction nhạy cảm phải tham chiếu policy version đã được acknowledge.
  - Lưu bằng chứng: timestamp, thiết bị, IP, phiên bản chính sách.

#### A.2 GPS offline fallback & chống gian lận

- **Workflow**
  1. Khi mất tín hiệu GPS, hệ thống gắn trạng thái `gps_offline_segment`.
  2. Tài xế khai báo bổ sung km offline (nếu có).
  3. Dispatcher/QA vận hành đối soát với dữ liệu phụ (ODO, fuel card, camera, trạm thu phí).
  4. Segment được chốt: `approved` hoặc `rejected`.
- **Rule**
  - Không tự động cộng km khai báo thủ công vào payroll nếu chưa duyệt.
  - Đặt ngưỡng bất thường (offline quá lâu, km khai báo vượt chuẩn) để tạo incident.
  - Ghi toàn bộ lịch sử chỉnh sửa để phục vụ điều tra gian lận.

#### A.3 Lương tháng 13 và thưởng Tết

- **Workflow**
  1. Cấu hình policy thưởng năm: điều kiện hưởng, công thức tính, thời điểm chi.
  2. Snapshot dữ liệu đánh giá cuối năm.
  3. Tạo batch chi thưởng riêng (không trộn kỳ payroll tháng thường).
  4. Tính PIT cho khoản thưởng theo quy định.
- **Rule**
  - Có hỗ trợ pro-rata cho nhân sự chưa đủ năm (theo policy công ty).
  - Tài xế nghỉ trước kỳ thưởng: xử lý quyền lợi theo điều kiện đã công bố.
  - Bút toán thưởng phải truy vết độc lập phục vụ kiểm toán.

### B) Nhóm quan trọng (cam)

#### B.1 Workflow phương tiện thuê ngoài (leased/rented)

- Quản lý hợp đồng thuê xe: thời hạn, chi phí, điều khoản bảo hiểm.
- Rule phân công:
  - hết hạn hợp đồng thuê -> block assign.
  - định mức nhiên liệu có thể khác xe sở hữu nội bộ.
- Chi phí thuê xe được hạch toán theo cost center/chuyến.

#### B.2 Enforcement giới hạn OT tháng/năm

- Theo dõi OT tích lũy theo tài xế:
  - theo tháng,
  - theo năm.
- Cảnh báo trước ngưỡng.
- Chạm ngưỡng pháp lý: block tạo OT mới.
- Trường hợp đặc biệt (300h/năm) phải có hồ sơ đồng ý và phê duyệt bổ sung.

#### B.3 Labor dispute escalation

- Tầng tranh chấp mở rộng:
  - khiếu nại nội bộ,
  - hòa giải nội bộ,
  - chuyển hội đồng/hệ thống pháp lý.
- Có khả năng tạo supplementary payroll từ kết luận hòa giải.
- Hỗ trợ export hồ sơ tranh chấp theo bộ chứng cứ chuẩn.

#### B.4 Pre-lock Validation Gate (cổng kiểm tra trước khóa lương)

- Trước khi `lock payroll`, hệ thống bắt buộc pass checklist tự động:
  - không còn trip `in_progress/disputed` chưa xử lý.
  - fuel card transactions đã match hoặc có quyết định xử lý.
  - OT request không còn `submitted`.
  - violation không còn `pending_review`.
  - attendance đã chốt.
  - HOS không còn vi phạm mở.
- Nếu fail bất kỳ mục nào: block lock và trả danh sách lỗi theo module.

### C) Nhóm vận hành (vàng)

#### C.1 Đăng kiểm và bảo hiểm xe

- Quản lý hồ sơ:
  - đăng kiểm,
  - bảo hiểm TNDS bắt buộc,
  - phù hiệu/biển hiệu vận tải.
- Cảnh báo trước hạn nhiều mốc (T-30, T-7, T-1).
- Hết hạn: chặn phân công xe tự động.

#### C.2 Hàng hóa đặc biệt và phụ cấp chuyên biệt

- Bổ sung `cargo_type` trên chuyến: `normal`, `hazmat`, `oversize`, `cold_chain`, ...
- Rule:
  - yêu cầu chứng chỉ bắt buộc theo loại hàng.
  - áp phụ cấp/bonus tương ứng.
  - block assign nếu tài xế/xe không đủ điều kiện.

#### C.3 Quyền truy cập/xóa dữ liệu cá nhân (PDPA)

- Workflow xử lý yêu cầu của tài xế:
  - right to access: xuất dữ liệu cá nhân đang lưu.
  - right to erasure: ẩn danh hóa dữ liệu khi không vi phạm nghĩa vụ lưu trữ kế toán.
- Có ma trận field nào xóa, field nào chỉ anonymize.
- Ghi log pháp lý cho mọi yêu cầu xử lý dữ liệu cá nhân.

### D) Nhóm kỹ thuật (xanh dương)

#### D.1 Test data isolation giữa môi trường

- Cấm sao chép raw production data sang dev/staging.
- Bắt buộc anonymization/pseudonymization trước khi dùng cho test.
- Duy trì bộ seed data chuẩn để tái hiện bug payroll mà không dùng dữ liệu thật.
- Kiểm soát quyền truy cập production theo break-glass flow.

#### D.2 Onboard fuel balance tracking

- Bổ sung ghi nhận mức nhiên liệu đầu ca/cuối ca theo xe.
- Khi đổi tài xế/đổi xe trong ngày:
  - lập biên bản bàn giao nhiên liệu.
  - đối soát chênh lệch nhiên liệu giữa các phiên sử dụng.
- Rule này là đầu vào bắt buộc để định mức nhiên liệu chính xác khi nhiều tài xế dùng chung xe.

## Ma trận ưu tiên bổ sung vòng 5

| Mức độ | Hạng mục |
| :-- | :-- |
| 🔴 Khẩn | Driver consent acknowledgment, GPS offline fallback & fraud prevention, tháng 13 & thưởng Tết |
| 🟠 Cao | Leased vehicle workflow, OT cap enforcement, labor dispute escalation, pre-lock validation gate |
| 🟡 Trung bình | Đăng kiểm/bảo hiểm xe, cargo type phụ cấp đặc thù, right to access/erasure |
| 🔵 Kỹ thuật | Test data isolation strategy, onboard fuel balance tracking |

> Điểm chốt vòng 5: GPS offline fallback và pre-lock validation gate cần được coi là control bắt buộc trước go-live để giảm rủi ro gian lận và khóa nhầm payroll khi dữ liệu còn treo.

## Bổ sung lỗ hổng nghiệp vụ vòng 6 và định hướng Phase 2

### A) Nhóm khẩn cấp (đỏ)

#### A.1 Trip refusal workflow (tài xế từ chối chuyến)

- **Workflow**
  1. Dispatcher assign chuyến.
  2. Tài xế có quyền `accept` hoặc `refuse` trong SLA phản hồi.
  3. Nếu `refuse`, bắt buộc chọn lý do:
     - hợp lệ: sức khỏe, HOS gần ngưỡng, chứng chỉ/giấy tờ không hợp lệ,
     - không hợp lệ: từ chối chủ quan không có căn cứ.
  4. Chuyến vào hàng chờ re-assign, dispatcher phải xử lý trong SLA.
- **Rule**
  - Từ chối hợp lệ không phạt KPI.
  - Từ chối không hợp lệ có thể trừ điểm KPI/bonus theo chính sách.
  - Lưu lịch sử từ chối để phân tích cân bằng phân công.

#### A.2 Payroll budget cap trước khi lock

- **Workflow**
  1. Khi chuẩn bị lock payroll, hệ thống so `total_net_salary` với budget kỳ.
  2. Nếu vượt ngưỡng cấu hình (ví dụ 5-10%):
     - block lock hoặc chuyển luồng override.
  3. Override cần multi-level approval + lý do.
- **Rule**
  - Không cho bypass silent.
  - Mọi override budget phải có audit trail và mã phê duyệt.

### B) Nhóm quan trọng (cam)

#### B.1 Peak season policy (mùa cao điểm)

- Cấu hình policy theo khoảng thời gian:
  - holiday surcharge,
  - call-back allowance,
  - max trips/day theo mức an toàn.
- Policy mùa cao điểm có version và hiệu lực rõ ràng.

#### B.2 Spare parts inventory cho maintenance

- Liên kết lệnh bảo trì với phụ tùng sử dụng.
- Theo dõi tồn kho tối thiểu, cảnh báo thiếu hàng.
- Hạch toán chi phí phụ tùng theo cost center/phương tiện/chuyến.

#### B.3 Internal audit workflow

- Định kỳ review audit log (month/quarter).
- AI anomaly phải đi vào hàng đợi kiểm toán có owner xử lý.
- Tạo báo cáo kiểm toán nội bộ và biên bản khắc phục.

#### B.4 Mobile app version control

- Gắn `min_supported_version` cho API quan trọng.
- Có chế độ forced update khi thay đổi nghiệp vụ bắt buộc.
- Có fallback tạm thời cho vùng mạng yếu (grace period có kiểm soát).

### C) Nhóm vận hành (vàng)

#### C.1 Payment failure recovery

- Trạng thái thanh toán chi tiết theo dòng lương:
  - `pending_transfer`, `transfer_failed`, `transferred`.
- Luồng xử lý lỗi:
  - phát hiện lỗi tài khoản,
  - sửa thông tin,
  - re-transfer trong SLA trả lương.
- Có báo cáo các khoản chưa đến tay tài xế.

#### C.2 PPE management

- Quản lý cấp phát bảo hộ theo loại công việc/hàng hóa.
- Theo dõi chu kỳ thay thế PPE.
- Chặn phân công hàng nguy hiểm nếu thiếu PPE/chứng chỉ tương ứng.

#### C.3 Multi-employer scenario

- Với nhóm contractor/thời vụ:
  - bổ sung cờ nhiều nguồn thu nhập,
  - cảnh báo rủi ro PIT/HOS liên doanh nghiệp.
- Trước mắt ở Phase 1 có thể triển khai ở mức self-declaration + compliance warning.

### D) Nhóm kỹ thuật (xanh dương)

#### D.1 Eventual consistency strategy

- Định nghĩa rõ trigger recalculate khi source data đổi:
  - attendance, fuel match, violation, OT.
- Có state machine cho payroll draft/approved/locked khi dữ liệu nguồn đến trễ.
- Bắt buộc hiển thị cờ `stale_data` nếu payroll đang không đồng bộ.

#### D.2 External integration resilience

- Áp dụng rate limit theo connector.
- Circuit breaker khi bên thứ ba lỗi lặp.
- Định nghĩa fallback rõ:
  - queue retry,
  - degrade mode,
  - manual reconciliation path.

## Ma trận ưu tiên bổ sung vòng 6

| Mức độ | Hạng mục |
| :-- | :-- |
| 🔴 Khẩn | Trip refusal workflow, Payroll budget cap enforcement trước lock |
| 🟠 Cao | Peak season policy, Spare parts inventory, Internal audit workflow, Mobile app version control |
| 🟡 Trung bình | Payment failure recovery, PPE management, Multi-employer scenario |
| 🔵 Kỹ thuật | Eventual consistency strategy, Rate limiting & circuit breaker cho integration |

## Đánh giá tổng thể sau 6 vòng

- **Đã hoàn chỉnh ở mức cao**: payroll core, RBAC, audit trail, OT/night shift/holiday, attendance, HOS, fuel quota, snapshot.
- **Tiệm cận hoàn chỉnh**: leave, maintenance, dispute, onboarding/offboarding, BHXH/PIT.
- **Nên đưa vào Phase 2 khi scale**: internal audit sâu, peak season orchestration, multi-employer, eventual consistency.

### Khuyến nghị thực thi

- Dừng mở rộng phạm vi thiết kế tại đây cho Phase 1.
- Chuyển sang triển khai theo roadmap đã chốt.
- Gắn các gap vòng 6 vào backlog Phase 2, không dùng làm hard-block go-live trừ các control đỏ nếu có ràng buộc pháp lý tức thời.

## Bổ sung lỗ hổng nghiệp vụ vòng 7 (vòng rà soát cuối)

### A) Nhóm khẩn cấp (đỏ)

#### A.1 Separation of Duties (SoD)

RBAC hiện có cần bổ sung lớp kiểm soát xung đột nhiệm vụ để đáp ứng kiểm toán tài chính/SOC 2.

- **Cặp quyền xung đột bắt buộc tách**
  - Người tạo payroll không được duyệt payroll cùng kỳ do mình tạo.
  - Người tạo salary advance không được là người giải ngân.
  - Người ghi nhận vi phạm không được là người xác nhận vi phạm đó.
- **Rule thực thi**
  - Kiểm tra SoD ở bước submit duyệt và approve, không chỉ ở UI.
  - Trả `403` khi vi phạm SoD.
  - Audit log bắt buộc lưu actor-1 (creator) và actor-2 (approver/disburser).

### B) Nhóm quan trọng (cam)

#### B.1 Route deviation detection

- So sánh tuyến kế hoạch và tuyến thực tế từ GPS.
- Phân loại lệch tuyến:
  - hợp lệ (kẹt xe, điều phối thay đổi),
  - bất thường cần giải trình.
- **Rule ảnh hưởng payroll/fuel**
  - Km lệch tuyến không tự động cộng thưởng nếu chưa được xác minh hợp lệ.
  - Fuel quota điều chỉnh theo route thực tế đã xác minh, không theo route khai báo tay.

#### B.2 Third-party / outsourced driver management

- Bổ sung phân loại tài xế:
  - `internal_driver`
  - `outsourced_driver`
- Tài xế outsource:
  - tham gia vận hành chuyến,
  - không đi vào payroll nội bộ,
  - chi phí đi vào AP/đối tác.
- Bắt buộc tách báo cáo chi phí nhân công nội bộ vs thuê ngoài.

#### B.3 Toll reconciliation

- Tích hợp dữ liệu ETC/thẻ thu phí.
- Matching tự động theo thời gian/tuyến/chuyến.
- Trạm thu phí ngoài tuyến kế hoạch -> flag bất thường + luồng xác minh.
- Đưa phí cầu đường khỏi “manual reimbursement-only” sang kiểm soát bán tự động/tự động.

### C) Nhóm vận hành (vàng)

#### C.1 Data lock khi payroll batch đang chạy

- Khi batch `running`, dữ liệu nguồn payroll phải vào chế độ kiểm soát:
  - hoặc read snapshot cố định,
  - hoặc lock mutate các thực thể đầu vào trong cửa sổ tính.
- Nếu có thay đổi phát sinh trong lúc batch chạy:
  - đưa vào queue recalculation sau batch hiện tại,
  - không trộn vào kết quả batch đang chạy.

#### C.2 Security deposit (ký quỹ tài xế)

- Bổ sung sổ theo dõi ký quỹ:
  - số tiền, ngày nhận, điều kiện hoàn trả, trạng thái.
- Offboarding:
  - đối soát thiệt hại trước khi hoàn trả ký quỹ.
- Kế toán:
  - ký quỹ là liability, không được trộn trực tiếp vào deduction lương.

### D) Nhóm kỹ thuật (xanh dương)

#### D.1 Lock/approve behavior khi batch đang `running`

- Quy tắc bắt buộc:
  - chặn lock/approve trong khi batch `running`,
  - trả thông báo rõ trạng thái batch và ETA.
- Timeout/recovery:
  - nếu batch treo quá ngưỡng, chuyển `failed` và yêu cầu thao tác resume/retry có kiểm soát.
- Tránh tuyệt đối race condition giữa batch writer và lock writer.

## Ma trận ưu tiên vòng 7

| Mức độ | Hạng mục |
| :-- | :-- |
| 🔴 Khẩn | Separation of Duties (SoD) cho payroll và tài chính |
| 🟠 Cao | Route deviation detection, third-party driver management, toll reconciliation |
| 🟡 Trung bình | Data lock during batch execution, security deposit management |
| 🔵 Kỹ thuật | Behavior khi lock/approve payroll trong khi batch đang running |

## Tuyên bố kết thúc rà soát

Sau 7 vòng, tài liệu đạt trạng thái production-ready ở mức thiết kế nghiệp vụ. Các gap vòng 7 có phạm vi hẹp, không làm thay đổi kiến trúc lõi, có thể triển khai song song theo backlog.

### Ba mục cần xử lý trước go-live

1. SoD controls (yêu cầu kiểm soát nội bộ/SOC 2).
2. Route deviation logic (độ chính xác fuel quota và thưởng km).
3. Batch data lock behavior (ngăn race condition khi tính/khóa payroll).

Các mục còn lại nên đưa vào Phase 2 để tối ưu vận hành sau khi hệ thống đi vào hoạt động ổn định.






