<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CurrentSchemaAllTablesSeeder::class,
            EnsureAdminAbcTransportSeeder::class,
            KnowledgeArticleSeeder::class,
            CloudinaryImageSeeder::class,
        ]);
    }
}
