<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PublicHoliday extends Model
{
    protected $fillable = [
        'country_code',
        'year',
        'date',
        'name',
        'holiday_type',
        'is_compensatory',
        'compensatory_for',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date'             => 'date',
            'year'             => 'integer',
            'is_compensatory'  => 'boolean',
            'compensatory_for' => 'date',
        ];
    }

    public function scopeForYear(Builder $query, int $year, string $countryCode = 'VN'): Builder
    {
        return $query->where('country_code', $countryCode)->where('year', $year);
    }

    public function scopeForDate(Builder $query, string $date, string $countryCode = 'VN'): Builder
    {
        return $query->where('country_code', $countryCode)->where('date', $date);
    }

    /**
     * Check if a given date falls on a public holiday.
     */
    public static function isHoliday(string $date, string $countryCode = 'VN'): bool
    {
        return self::query()
            ->where('country_code', $countryCode)
            ->where('date', $date)
            ->exists();
    }

    /**
     * Get all holiday dates for a month as Carbon date strings.
     *
     * @return array<string>
     */
    public static function datesForMonth(int $year, int $month, string $countryCode = 'VN'): array
    {
        return self::query()
            ->where('country_code', $countryCode)
            ->where('year', $year)
            ->whereMonth('date', $month)
            ->pluck('date')
            ->map(fn ($d) => (string) $d)
            ->toArray();
    }
}
