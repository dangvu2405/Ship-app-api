<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Upload ảnh mẫu lên Cloudinary và cập nhật DB.
 *
 * - Driver avatar_url  : ảnh người thật từ randomuser.me (free, stable)
 * - User   avatar_url  : ảnh giống driver (nếu user có driver profile)
 * - Vehicle image_*    : ảnh xe tải/xe van từ Pexels (free, no API key required)
 *
 * Chạy: php artisan db:seed --class=CloudinaryImageSeeder
 */
class CloudinaryImageSeeder extends Seeder
{
    private string $cloudName;

    // Ảnh xe từ Pexels (public CDN, stable URLs, no auth needed)
    private array $vehicleImageSets = [
        'truck' => [
            'front' => 'https://images.pexels.com/photos/1687342/pexels-photo-1687342.jpeg?w=800',
            'back' => 'https://images.pexels.com/photos/2199293/pexels-photo-2199293.jpeg?w=800',
            'side' => 'https://images.pexels.com/photos/2533092/pexels-photo-2533092.jpeg?w=800',
            'other' => 'https://images.pexels.com/photos/906494/pexels-photo-906494.jpeg?w=800',
        ],
        'van' => [
            'front' => 'https://images.pexels.com/photos/210019/pexels-photo-210019.jpeg?w=800',
            'back' => 'https://images.pexels.com/photos/3802510/pexels-photo-3802510.jpeg?w=800',
            'side' => 'https://images.pexels.com/photos/244553/pexels-photo-244553.jpeg?w=800',
            'other' => 'https://images.pexels.com/photos/164634/pexels-photo-164634.jpeg?w=800',
        ],
        'bus' => [
            'front' => 'https://images.pexels.com/photos/258045/pexels-photo-258045.jpeg?w=800',
            'back' => 'https://images.pexels.com/photos/1624695/pexels-photo-1624695.jpeg?w=800',
            'side' => 'https://images.pexels.com/photos/1008155/pexels-photo-1008155.jpeg?w=800',
            'other' => 'https://images.pexels.com/photos/1031645/pexels-photo-1031645.jpeg?w=800',
        ],
        'car' => [
            'front' => 'https://images.pexels.com/photos/170811/pexels-photo-170811.jpeg?w=800',
            'back' => 'https://images.pexels.com/photos/100656/pexels-photo-100656.jpeg?w=800',
            'side' => 'https://images.pexels.com/photos/116675/pexels-photo-116675.jpeg?w=800',
            'other' => 'https://images.pexels.com/photos/1402787/pexels-photo-1402787.jpeg?w=800',
        ],
    ];

    // Ảnh người thật từ randomuser.me — ổn định, miễn phí, không cần key
    private array $personPhotos = [
        'male' => [
            'https://randomuser.me/api/portraits/men/1.jpg',
            'https://randomuser.me/api/portraits/men/2.jpg',
            'https://randomuser.me/api/portraits/men/3.jpg',
            'https://randomuser.me/api/portraits/men/4.jpg',
            'https://randomuser.me/api/portraits/men/5.jpg',
            'https://randomuser.me/api/portraits/men/6.jpg',
            'https://randomuser.me/api/portraits/men/7.jpg',
            'https://randomuser.me/api/portraits/men/8.jpg',
            'https://randomuser.me/api/portraits/men/9.jpg',
            'https://randomuser.me/api/portraits/men/10.jpg',
        ],
        'female' => [
            'https://randomuser.me/api/portraits/women/1.jpg',
            'https://randomuser.me/api/portraits/women/2.jpg',
            'https://randomuser.me/api/portraits/women/3.jpg',
            'https://randomuser.me/api/portraits/women/4.jpg',
            'https://randomuser.me/api/portraits/women/5.jpg',
        ],
    ];

    public function run(): void
    {
        $this->cloudName = $this->resolveCloudName();

        $this->command->info('');
        $this->command->info('☁️  Cloudinary Image Seeder — cloud: '.$this->cloudName);
        $this->command->info('');

        $this->seedVehicleImages();
        $this->seedDriverAvatars();
        $this->seedUserAvatars();

        $this->command->info('');
        $this->command->info('✅  Cloudinary image seeding complete');
    }

    // ── Vehicles ──────────────────────────────────────────────────────────────

    private function seedVehicleImages(): void
    {
        // Only upload for vehicles missing all images to allow re-run safely
        $vehicles = Vehicle::whereNull('image_front')->get();

        if ($vehicles->isEmpty()) {
            $this->command->info('  All vehicles already have images — skipping');

            return;
        }

        $this->command->info("  Uploading vehicle images ({$vehicles->count()} vehicles)...");
        $bar = $this->command->getOutput()->createProgressBar($vehicles->count());
        $bar->start();

        $typeKeys = array_keys($this->vehicleImageSets);
        $allSets = array_values($this->vehicleImageSets);
        $totalSets = count($allSets);

        foreach ($vehicles as $index => $vehicle) {
            // Rotate through image sets so consecutive vehicles look different
            $setIndex = $index % $totalSets;
            $images = array_key_exists($vehicle->type, $this->vehicleImageSets)
                ? $this->vehicleImageSets[$vehicle->type]
                : $allSets[$setIndex];

            $folder = 'ship_app/vehicles';
            $updates = [];

            foreach (['front', 'back', 'side', 'other'] as $side) {
                $url = $this->uploadFromUrl(
                    $images[$side],
                    $folder,
                    "vehicle_{$vehicle->id}_{$side}"
                );
                if ($url !== null) {
                    $updates["image_{$side}"] = $url;
                }
            }

            if (! empty($updates)) {
                $vehicle->updateQuietly($updates);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->command->info('');
    }

    // ── Driver avatars ────────────────────────────────────────────────────────

    private function seedDriverAvatars(): void
    {
        $drivers = Driver::whereNull('avatar_url')->orWhere('avatar_url', '')->get();

        if ($drivers->isEmpty()) {
            $this->command->info('  All drivers already have avatars — skipping');

            return;
        }

        $this->command->info("  Uploading driver avatars ({$drivers->count()} drivers)...");
        $bar = $this->command->getOutput()->createProgressBar($drivers->count());
        $bar->start();

        $malePhotos = $this->personPhotos['male'];
        $femalePhotos = $this->personPhotos['female'];
        $maleIdx = 0;
        $femaleIdx = 0;

        foreach ($drivers as $driver) {
            $isFemale = $driver->gender === 'female';
            $photoUrl = $isFemale
                ? $femalePhotos[$femaleIdx++ % count($femalePhotos)]
                : $malePhotos[$maleIdx++ % count($malePhotos)];

            $url = $this->uploadFromUrl(
                $photoUrl,
                'ship_app/drivers',
                "driver_{$driver->id}_avatar"
            );

            if ($url !== null) {
                $driver->updateQuietly(['avatar_url' => $url]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->command->info('');
    }

    // ── User avatars (synced from their driver profile) ───────────────────────

    private function seedUserAvatars(): void
    {
        $users = User::with('driver')
            ->whereNull('avatar_url')
            ->orWhere('avatar_url', '')
            ->get();

        $updated = 0;
        foreach ($users as $user) {
            if ($user->driver?->avatar_url) {
                $user->updateQuietly(['avatar_url' => $user->driver->avatar_url]);
                $updated++;
            }
        }

        $this->command->info("  Synced {$updated} user avatars from driver profiles");
    }

    // ── Cloudinary upload helper ──────────────────────────────────────────────

    /**
     * Upload an image from a remote URL to Cloudinary.
     * Returns the secure URL on success, null on failure.
     */
    private function uploadFromUrl(string $sourceUrl, string $folder, string $publicId): ?string
    {
        try {
            $result = cloudinary()->uploadApi()->upload($sourceUrl, [
                'folder' => $folder,
                'public_id' => $publicId,
                'overwrite' => true,
                'resource_type' => 'image',
                'transformation' => [
                    ['quality' => 'auto', 'fetch_format' => 'auto'],
                ],
            ]);

            return $result['secure_url'] ?? null;
        } catch (\Throwable $e) {
            Log::warning("Cloudinary upload failed for {$sourceUrl}: ".$e->getMessage());
            $this->command->warn("\n  ⚠ Failed: {$publicId} — ".$e->getMessage());

            return null;
        }
    }

    private function resolveCloudName(): string
    {
        $url = config('services.cloudinary.url') ?? env('CLOUDINARY_URL', '');
        $host = parse_url($url, PHP_URL_HOST);

        return $host ?: 'unknown';
    }
}
