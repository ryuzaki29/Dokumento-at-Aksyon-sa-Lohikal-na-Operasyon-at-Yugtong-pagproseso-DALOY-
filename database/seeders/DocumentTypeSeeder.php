<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public const TYPES = [
        ['code' => 'MEMO', 'name' => 'Memo'],
        ['code' => 'REQ', 'name' => 'Request'],
        ['code' => 'END', 'name' => 'Endorsement'],
        ['code' => 'LTR', 'name' => 'Letter'],
    ];

    public function run(): void
    {
        $adminId = User::where('email', 'admin@example.com')->value('id');

        foreach (self::TYPES as $type) {
            DocumentType::updateOrCreate(
                ['code' => $type['code']],
                ['name' => $type['name'], 'is_active' => true, 'created_by' => $adminId],
            );
        }
    }
}
