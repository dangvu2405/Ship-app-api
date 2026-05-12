1. Nguyên tắc ĐƠN TRÁCH NHIỆM - Single responsibility principle (SRP)
Một lớp và một phương thức nên chỉ có một trách nhiệm và nên chỉ có một trách nhiệm mà thôi, cái này là nguyên tắc của SOLID trong hướng đối tượng PHP mình sẽ có một bài nói rõ hơn về nguyên tắc SOLID trong PHP nhé.

Giả sử như bẳng user của bạn có các trường là first_name, middle_name, last_name, gender và bạn muốn lấy ra full_name. Nhưng nếu user đã đăng nhập, đã được xác nhận và user đó là khách hàng của công ty bạn, bạn phải xưng hô Ông/Bà ở đằng trước tên. Sử dụng Accessors trong Laravel ta làm như sau:

Coder Laravel gà sẽ viết:

public function getFullNameAttribute()
{
	if ($this->gender) {
		$genderText = 'Mr. ';
	} else {
		$genderText = 'Mrs. ';
	}
	
	if (auth()->user() && auth()->user()->hasRole('client') && auth()->user()->isVerified()) {
		return $genderText . $this->first_name . ' ' . $this->middle_name . ' ' . $this->last_name;
	} else {
		return $this->first_name . ' ' . $this->last_name;
	}
}
Coder Laravel chuẩn sẽ viết:

public function getFullNameAttribute()
{
    return $this->isVerifiedClient() ? $this->getFullNameLong() : $this->getFullNameShort();
}

public function isVerifiedClient()
{
	// Trả về true or false
    return auth()->user() && auth()->user()->hasRole('client') && auth()->user()->isVerified();
}

public function getFullNameLong()
{
    return $this->gender_text . $this->first_name . ' ' . $this->middle_name . ' ' . $this->last_name;
}

public function getFullNameShort()
{
    return $this->first_name . ' ' . $this->last_name;
}

public function getGenderTextAttribute()
{
	// Nếu là nam
	if ($this->gender) {
		return 'Mr. ';
	}
	return 'Mrs. ';
}
Tách bạch mọi thứ ra hết, mỗi hàm chỉ đảm nhận 1 nhiệm vụ xử lý duy nhất.

2. Models thì mập, Controllers thì gầy - Fat models, skinny controllers
Đặt tất cả các logic liên quan đến DB vào các Eloquent model hoặc vào các lớp Repository nếu bạn đang sử dụng Query Builder hoặc raw Query.

Coder Laravel gà sẽ viết:

public function index()
{
    $clients = Client::verified()
        ->with(['orders' => function ($q) {
            $q->where('created_at', '>', Carbon::today()->subWeek());
        }])
        ->get();

    return view('index', ['clients' => $clients]);
}
Về logic chả có gì sai, mình mà nói nó sai có khi nó táng vỡ alo mình. Nhưng nếu mình là coder Laravel chuẩn mình sẽ viết sao:

public function index()
{
    return view('index', ['clients' => $this->client->getWithNewOrders()]);
}

class Client extends Model
{
    public function getWithNewOrders()
    {
        return $this->verified()
            ->with(['orders' => function ($q) {
                $q->where('created_at', '>', Carbon::today()->subWeek());
            }])
            ->get();
    }
}
đặt một phương thức getWithNewOrders() viết tất tần tật Query trong đó và ngoài controller chỉ gọi 1 dòng duy nhất. Giả sử như có 10 chỗ xài getWithNewOrders() chỗ nào bạn cũng phang thẳng vào controller, chẳng may logic đó cần chỉnh sửa, bạn phải chỉnh 10 chỗ. Nếu không phải bạn chỉnh sửa mà là đồng đội của bạn, nó sẽ tẩn cho bạn 1 trận ngay cho mà xem.

3. Validation - Kiểm tra dữ liệu đầu vào
Hãy duy chuyển đoạn code validate vào trong Request.

Laravel cung cấp linh hoạt nhiều cách để validate. Ok, ổn cả thôi. Coder Laravel gà chắc chắn sẽ chọn cách đơn giản nhứt:

public function store(Request $request)
{
    $request->validate([
        'title' => 'required|unique:posts|max:255',
        'body' => 'required',
        'publish_at' => 'nullable|date',
    ]);

    ....
}
Pro thì sao:

public function store(PostRequest $request)
{    
    ....
}

// chạy lệnh: php artisan make:request PostRequest
class PostRequest extends Request
{
    public function authorize()
    {
        true;
    }

    public function rules()
    {
        return [
            'title' => 'required|unique:posts|max:255',
            'body' => 'required',
            'publish_at' => 'nullable|date',
        ];
    }
}
4. Logic nghiệp vụ (Business) phải ở trong lớp dịch vụ (Service)
Giả sử như bạn có 1 form trong đó có mục upload hình, dữ liệu sẽ được gửi lên "ArticleController@store" và bạn lưu hình upload lên vào một thư mục nào đó trên server. Ta cùng coi coder Laravel gà sẽ viết gì nào:

public function store(Request $request)
{
    if ($request->hasFile('image')) {
        // move file vào 1 thư mục nào đó
        $request->file('image')->move(public_path('images') . 'temp');
    }
    
    ....
}
Version Pro

public function store(Request $request)
{
    $this->articleService->handleUploadedImage($request->file('image'));

    ....
}

class ArticleService
{
    public function handleUploadedImage($image)
    {
        if (!is_null($image)) {
            $image->move(public_path('images') . 'temp');
        }
    }
}
Bạn thấy gì không? NGUYÊN TẮC THỨ NHẤT LUÔN ĐÚNG! Vì sao? Hàm store ý nghĩa của nó là store dữ liệu người dùng gửi lên vào database, nếu kiêm luôn cả việc move hình đi đâu nữa thì há chẳng phải vi phạm nguyên tắc thứ nhất rồi sao? Việc lưu hình đi đâu có phải là 1 nghiệp vụ không ❓ Quá phải y chứ lị, những thứ gì mang tính đặc thù hãy đưa nó cho Service class xử lý nhé.

5. Don't repeat yourself (DRY) - Đừng tự lặp lại (bóp dái) chính mình
Tái sử dụng code của bạn bất cứ khi nào có thể. Hãy áp dụng nguyên tắc thứ nhất ở trên, chắc chắn bạn sẽ không vi phạm nguyên tắc số 5 này. Hễ bạn thấy đoạn code nào được viết từ 2 lần trở lên, hãy nghiên cứu đưa nó về 1 hàm và gọi đến, nhiều hàm có cùng điểm giống nhau thì tổ chức thành class. Đấy là cách mà pro đã làm. Ta hãy xem bạn tự bóp dái mình như thế nào bằng cách xem ví dụ dưới đây nhé

public function getActive()
{
    return $this->where('verified', 1)->whereNotNull('deleted_at')->get();
}

public function getArticles()
{
    return $this->whereHas('user', function ($q) {
            $q->where('verified', 1)->whereNotNull('deleted_at');
        })->get();
}
Bạn thấy gì không where('verified', 1)->whereNotNull('deleted_at') được viết ở 2 nơi. Coder có kinh nghiệm sẽ viết:

public function scopeActive($q)
{
    return $q->where('verified', 1)->whereNotNull('deleted_at');
}

public function getActive()
{
    return $this->active()->get();
}

public function getArticles()
{
    return $this->whereHas('user', function ($q) {
            $q->active();
        })->get();
}
Easy, đưa phần giống nhau vào scope query trong Laravel, sau này có thêm điều kiện active là sms verify chẳng hạn, bạn có phải mất công đi sửa code ở N chỗ hay không ❓

6. Ưu tiên dùng Eloquent hơn Query Builder, raw SQL. Ưu tiên Collection hơn là array.
Eloquent cho phép bạn viết mã có thể đọc và duy trì được sau này. Eloquent có quá trời built-in tools như scope, soft deletes, events, relationship, ...

Nhiều bạn sẽ đặt tốc độ lên bàn cân để so sánh, đặt hoài nghi về Eloquent hãy xem biết bao nhiêu ưu điểm đổi lấy vài phần trăm speed là quá đáng để hi sinh.

SELECT *
FROM `articles`
WHERE EXISTS (SELECT *
              FROM `users`
              WHERE `articles`.`user_id` = `users`.`id`
              AND EXISTS (SELECT *
                          FROM `profiles`
                          WHERE `profiles`.`user_id` = `users`.`id`) 
              AND `users`.`deleted_at` IS NULL)
AND `verified` = '1'
AND `active` = '1'
ORDER BY `created_at` DESC
wow, tôi - Chung Nguyễn phải có kiến thức về mysql, Chung đọc cái đoạn EXITS loằng ngoằng kia chỉ để mất 3 tiếng để hiểu và 5 phút để chỉnh sửa, thất là quá đáng lắm luôn á 😱😱😭😭

Coder Laravel pro ơi, viết Eloquent em coi thử đi:

Article::has('user.profile')->verified()->latest()->get();
Oh, ACTICLE ⇒ HAS ⇒ USER ⇒ PROFILE ⇒ VERIFIED ⇒ LATEST ⇒ GET. Ôi mẹ ơi, không khác gì bài văn tiếng Anh 🤩🤩🌺🌺

Collection trong Laravel phải nói là kì quan thiên nhiên vĩ đại, nó bao hàm cả array trong đó và còn plus thêm gần cả trăm function hỗ trợ hiện đại, bạn đã bao giờ dùng map, chunk, pop, push, pipe,... chưa 😵 

Chẳng hạn bạn muốn lấy ra user có tuổi lớn hơn 18:

$usersOver18 = User::where('age', '>=' 18)->get();
Bây giờ bạn muốn lọc thêm là ở Hà Nội, có mã province_id là 4. Thay vì tốn thêm 1 query vào db như cách viết dưới đây:

$usersOver18LiveInHaNoi = User::where('age', '>=' 18)->where('province_id', 4)->get();
Bạn có thể tận dụng kết quả đã lấy ở trên

$usersOver18LiveInHaNoi = $usersOver18->where('province_id', 4);
7. Mass assignment - Gán giá trị hàng loạt
Khi tạo một bài viết mới, code sẽ như thế này:

$article = new Article;
$article->title = $request->title;
$article->content = $request->content;
$article->verified = $request->verified;
// Add category to article
$article->category_id = $category->id;
$article->save();
nếu database có 50 cột, okey fine 🤣🤣

Hãy dùng tính năng Mass assignment đưa Laravel kế thừa từ Ruby đi

$category->article()->create($request->all());
8. Không thực hiện truy vấn trong Blade view và sử dụng eager loading (N + 1 query)
Giả sử trong Blade bạn thực hiện show danh sách all user, đi kèm với profile.

@foreach (User::all() as $user)
    {{ $user->profile->name }}
@endforeach
Bạn thấy đấy, profile chính là mối quan hệ và Laravel sẽ truy vấn Query đó sau mỗi vòng lặp, giả sử có 100 user thì đoạn code trên sẽ thực hiện 101 truy vấn lên db 😐😐

Coder Laravel chuẩn phải viết

// trong controller
$users = User::with('profile')->get();


// ngoài blade view
@foreach ($users as $user)
    {{ $user->profile->name }}
@endforeach
9. Ghi chú cho đoạn code của bạn, nhưng hơn hết hãy đặt tên hàm và biến có ý nghĩa
Bad:

if (count((array) $builder->getQuery()->joins) > 0)
Tốt hơn xíu là có comment lại code:

// Determine if there are any joins.
if (count((array) $builder->getQuery()->joins) > 0)
Tốt hơn hết là phải thế này:

if ($this->hasJoins())
Tên hàm đã rõ nghĩa quá rồi, không cần phải comment. Trong hàm hasJoins bạn viết logic cho nó là ok 😎

10. Đừng bỏ code JS, CSS vào blade view, đừng bỏ HTML vào class PHP
Css thì bỏ vào file css thì không nói gì rồi, trường hợp hay gặp nhất là push dữ liệu PHP vào JS. Gà mờ sẽ viết như sau

let article = `{{ json_encode($article) }}`;
Nạp thẳng json_encode vào javascript ngay trong file blade view.

Cách tốt hơn là put giá trị vào 1 nơi nào đó trong blade view

<input id="article" type="hidden" value="@json($article)">

<!-- hoặc -->

<button class="js-fav-article" data-article="@json($article)">{{ $article->name }}<button>
Rồi lấy giá trị đó ra ở file JS:

let article = $('#article').val();

// hoặc

let article = $('#article').data('article');
Có trường output form bằng php code echo trong class, hãy bỏ chúng ngoài blade view nhé.

11. Sử dụng các files config và language, hằng số thay vì văn bản trong code
Nguyên tắc khi code là những thứ có khả năng thay đổi linh hoạt thì k fix cứng vào 1 chỗ. Giả dụ, kiểm tra bài viết này có phải là normal hay không?

public function isNormal()
{
    return $article->type === 'normal';
}
Thay vì gõ normal trực tiếp trong code, ta định nghĩa 1 lớp ArticleType hoặc bỏ luôn trong Article Eloquent Model luôn:

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    const TYPE_NORMAL = 'normal';
    
    public function isNormal()
    {
        return $article->type === Article::TYPE_NORMAL;
    }
}
Hoặc như trong trường hợp controller xử lý thành công và trả về một message cho người dùng, coder mới thường hay viết thế này

public function store(Request $request)
{
    # lưu bài viết vào db
    return back()->with('message', 'Thêm bài viết thành công!');
}
Hãy dùng Laravel Localization đi nhé, chả phức tạp như bạn nghĩ. Nhiều bạn cho là rắc rối vì phải định nghĩa trong language file, nhưng thực ra các bạn chưa biết 🧐. Laravel cung cấp 2 hình thức đọc file đa ngôn ngữ là từ file .php và .json. Trường hợp ở trên sẽ viết lại như sau:

public function store(Request $request)
{
    # lưu bài viết vào db
    // đọc từ file php
    return back()->with('message', __('app.article_added'));
    // đọc từ file json
    return back()->with('message', __('Thêm bài viết thành công!'));
}
Cách đọc từ file json rất tiện cho bạn nào lười, Laravel sẽ hiện thị nguyên chuỗi gốc nếu k tìm thấy bản dịch tương ứng, thay vì show đoạn text 'app.article_add' vô nghĩa với người dùng khi không tìm thấy.

12. Sử dụng các công cụ chuẩn của Laravel được cộng đồng chấp nhận
Laravel tích hợp cực kì nhiều chức năng và gói, đã được cộng đồng đón nhận và sử dụng, thay vì phải sử dụng gói và công cụ của bên thứ 3. Trừ khi khách hàng yêu cầu, và tính chất dự án yêu cầu phải có.

Tại sao:

DEV LARAVEL sẽ cần phải tìm hiểu các công cụ thứ 3 này.
cơ hội nhận trợ giúp từ cộng đồng Laravel thấp hơn đáng kể
Khách hàng của bạn cũng sẽ không trả tiền cho cho điều đó
Yêu cầu	Công cụ của Laravel	Công cụ của bên thứ 3
Authorization - Quyền hạn	Policies	Entrust, Sentinel, ...
Compiling assets - biên dịch tài nguyên	Laravel Mix	Grunt, Gulp,...
Development Environment - Môi trường DEV	Homestead	Docker
Deployment - Triển khai	Laravel Forge	Deployer...
Unit testing	PHPUnit, Mockery	Phpspec
Browser testing	Laravel Dusk	Codeception
DB	Eloquent	SQL, Doctrine
Templates Engineer	Blade	Twig
Làm việc với dữ liệu	Laravel collections	Arrays
Form validation	Request classes	bên thứ 3, validation in controller
Authentication - Chứng thực	Built-in (có sẵn)	bên thứ 3, giải pháp của bạn
API authentication	Laravel Passport	bên thứ 3 JWT và OAuth packages
Creating API	Built-in (có sẵn)	Dingo API và gói tương tự
Working with DB structure	Migrations	Thao tác trực tiếp trên DB
Localization - Đa ngôn ngữ	Built-in (có sẵn)	bên thứ 3
Realtime user interfaces	Laravel Echo, Pusher	bên thứ 3 làm việc trực tiếp với WebSockets
Generating testing data - Tạo dữ liệu test	Seeder classes, Model Factories, Faker	Làm tay
Task scheduling - Lịch công việc	Laravel Task Scheduler	Scripts và bên thứ 3
DB	MySQL, PostgreSQL, SQLite, SQL Server	MongoDB
13. Tuân theo quy ước đặt tên của Laravel
Đặt tên trong Laravel trước tiên phải tuân thủ quy cách đặt tên của PHP, đó là chuẩn PSR-2 Chung Nguyễn Blog sẽ viết sau

Ngoài ra, hãy làm theo các quy ước đặt tên được chấp nhận bởi cộng đồng Laravel như sau:

What	How	Good	Bad
Controller	số ít	ArticleController	ArticlesController
Route	số nhiều	articles/1	article/1
Named route	snake_case (kiểu rắn 🐍) với dấu chấm	users.show_active	users.show-active, show-active-users
Model	số ít	User	Users
hasOne hoặc belongsTo relationship	số ít	articleComment	articleComments, article_comment
Tất cả các relationships khác	số nhiều	articleComments	articleComment, article_comments
Table - bảng	số nhiều	article_comments	article_comment, articleComments
Bảng Pivot	gồm tên 2 bảng số ít, xếp theo an pha bét 🤣	article_user	user_article, articles_users
tên cột trong bảng	snake_case (kiểu rắn 🐍) không bao gồm tên bảng	meta_title	MetaTitle; article_meta_title
Thuộc tính của Model	snake_case (kiểu rắn 🐍)	$model->created_at	$model->createdAt
Foreign key - khóa ngoại	tên model số ít, kèm _id đằng sau	article_id	ArticleId, id_article, articles_id
Primary key	-	id	custom_id
Migration	-	2017_01_01_000000_create_articles_table	2017_01_01_000000_articles
Method	camelCase (kiểu lạc đà 🐫)	getAll	get_all
Method trong resource controller	xem tài liệu	store	saveArticle
Method in test class	camelCase (kiểu lạc đà 🐫)	testGuestCannotSeeArticle	test_guest_cannot_see_article
Variable	camelCase (kiểu lạc đà 🐫)	$articlesWithAuthor	$articles_with_author
Collection	có nghĩa mô tả, số nhiều	$activeUsers = User::active()->get()	$active, $data
Object	có nghĩa mô tả, số ít	$activeUser = User::active()->first()	$users, $obj
Config and language files index	snake_case (kiểu rắn 🐍)	articles_enabled	ArticlesEnabled; articles-enabled
View	kebab_case	show-filtered.blade.php	showFiltered.blade.php, show_filtered.blade.php
Config	snake_case (kiểu rắn 🐍)	google_calendar.php	googleCalendar.php, google-calendar.php
Contract (interface)	tính từ hoặc danh từ	Authenticatable	AuthenticationInterface, IAuthentication
Trait	tính từ	Notifiable	NotificationTrait
14. Sử dụng cú pháp ngắn hơn và dễ đọc hơn nếu có thể
Laravel hỗ trợ rất nhiều helpers method để tạo nên những đoạn code ngắn và rõ ngữ nghĩa. Danh sách helpers method xem ở đây nhé 

Bad:

$request->session()->get('cart');
$request->input('name');
Good:

session('cart');
$request->name;
Nhiều ví dụ hơn

Cú pháp thông thường	Cú pháp ngắn và rõ nghĩa hơn
Session::get('cart')	session('cart')
$request->session()->get('cart')	session('cart')
Session::put('cart', $data)	session(['cart' => $data])
$request->input('name'), Request::get('name')	$request->name, request('name')
return Redirect::back()	return back()
is_null($object->relation) ? $object->relation->id : null	optional($object->relation)->id
return view('index')->with('title', $title)->with('client', $client)	return view('index', compact('title', 'client'))
$request->has('value') ? $request->value : 'default';	$request->get('value', 'default')
Carbon::now(), Carbon::today()	now(), today()
App::make('Class')	app('Class')
->where('column', '=', 1)	->where('column', 1)
->orderBy('created_at', 'desc')	->latest()
->orderBy('age', 'desc')	->latest('age')
->orderBy('created_at', 'asc')	->oldest()
->select('id', 'name')->get()	->get(['id', 'name'])
->first()->name	->value('name')
15. Sử dụng IoC container hoặc facades thay vì new Class
Chả biết nói sao với cái này, vì nó hơi hướng trừu tượng khó hiểu. Đại loại object của class phải được khởi tạo qua IoC container hoặc facade trong Laravel. Khởi tạo ở __construct

Coder mới vào Laravel sẽ viết

public function store(Request $request)
{
    $user = new User;
    $user->create($request->all());
}
Còn áp dụng nguyên tắc 15 trên thì viết lại như sau:

protected $user;

public function __construct(User $user)
{
    $this->user = $user;
}

public function store(Request $request)
{
    $this->user->create($request->all());
}
16. Không lấy dữ liệu trực tiếp từ tệp .env
Đây là lỗi phổ biến khá nhiều bạn mắc phải nè, mục đích ENV sinh ra là để cá nhân hóa môi trường (env trong environment) để làm việc tập thể, ngoài ra còn để chứa 1 số dữ liệu nhạy cảm như mật khẩu, ... khi share, public code.

Quy trình chuẩn phải là như sau: khai báo trong .env ⏩ nạp vào config ⏩ lấy ra dữ liệu.

Gà thì viết như vầy:

public function index()
{
    $apiKey = env('API_KEY');
}
Còn ... à mà thôi

// config/api.php
<?php

return [
    ...
    'key' => env('API_KEY'),
    ...
]

// lấy API_KEY ở controller
public function index()
{
    $apiKey = config('api.key');
}
17. Lưu trữ ngày theo định dạng chuẩn. Sử dụng accessors and mutators để sửa đổi định dạng ngày
Chung hay nhận được nhiều inbox về vấn đề này, cũng như gặp các câu hỏi dạng như thế này trên các group về việc lưu vào db định dạng theo ý muốn cá nhân.

Bad

// lưu vào db định dạng cá nhân mong muốn
{{ Carbon::createFromFormat('Y-d-m H-i', $object->ordered_at)->toDateString() }}
{{ Carbon::createFromFormat('Y-d-m H-i', $object->ordered_at)->format('m-d') }}
Good:

// trong Eloquent Model
protected $dates = ['ordered_at', 'created_at', 'updated_at']
public function getSomeDateAttribute($date)
{
    return $date->format('m-d');
}

// hiển thị ra ngoài View
{{ $object->ordered_at->toDateString() }}
{{ $object->ordered_at->some_date }}
18. Khác
Không bao giờ đặt bất kỳ logic nào trong các tệp routes (web.php, api.php, ...).
Giảm thiểu việc sử dụng Vanilla PHP trong Blade templates. (Thay vì viết {{ csrf_token() }} hoặc @csrf bạn lại đi viết <?php echo csrf_field(); ?>)