<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PublicHoliday;
use Illuminate\Database\Seeder;

class PublicHolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            ['date' => '2025-01-01', 'name' => 'Tet Duong lich', 'holiday_type' => 'national'],
            ['date' => '2025-01-28', 'name' => 'Tet Nguyen Dan (29 thang Chap)', 'holiday_type' => 'national'],
            ['date' => '2025-01-29', 'name' => 'Tet Nguyen Dan (Mung 1)', 'holiday_type' => 'national'],
            ['date' => '2025-01-30', 'name' => 'Tet Nguyen Dan (Mung 2)', 'holiday_type' => 'national'],
            ['date' => '2025-01-31', 'name' => 'Tet Nguyen Dan (Mung 3)', 'holiday_type' => 'national'],
            ['date' => '2025-02-01', 'name' => 'Tet Nguyen Dan (Mung 4)', 'holiday_type' => 'national'],
            ['date' => '2025-02-02', 'name' => 'Tet Nguyen Dan (Mung 5)', 'holiday_type' => 'national'],
            ['date' => '2025-04-07', 'name' => 'Gio To Hung Vuong', 'holiday_type' => 'national'],
            ['date' => '2025-04-30', 'name' => 'Ngay Giai phong', 'holiday_type' => 'national'],
            ['date' => '2025-05-01', 'name' => 'Ngay Quoc te Lao dong', 'holiday_type' => 'national'],
            ['date' => '2025-09-02', 'name' => 'Ngay Quoc khanh', 'holiday_type' => 'national'],
        ];

        foreach ($holidays as $holiday) {
            PublicHoliday::query()->updateOrCreate(
                [
                    'country_code' => 'VN',
                    'date' => $holiday['date'],
                ],
                [
                    'year' => (int) substr($holiday['date'], 0, 4),
                    'name' => $holiday['name'],
                    'holiday_type' => $holiday['holiday_type'],
                ],
            );
        }
    }
}
