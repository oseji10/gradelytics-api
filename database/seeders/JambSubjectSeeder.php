<?php

namespace Database\Seeders;

use App\Models\JambSubject;
use Illuminate\Database\Seeder;

class JambSubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['name' => 'Use of English', 'code' => 'ENG'],
            ['name' => 'Mathematics', 'code' => 'MTH'],
            ['name' => 'Physics', 'code' => 'PHY'],
            ['name' => 'Chemistry', 'code' => 'CHM'],
            ['name' => 'Biology', 'code' => 'BIO'],
            ['name' => 'Economics', 'code' => 'ECO'],
            ['name' => 'Government', 'code' => 'GOV'],
            ['name' => 'Literature in English', 'code' => 'LIT'],
            ['name' => 'Commerce', 'code' => 'COM'],
            ['name' => 'Accounting', 'code' => 'ACC'],
            ['name' => 'Geography', 'code' => 'GEO'],
            ['name' => 'Agricultural Science', 'code' => 'AGR'],
            ['name' => 'CRS', 'code' => 'CRS'],
            ['name' => 'IRS', 'code' => 'IRS'],
        ];

        foreach ($subjects as $subject) {
            JambSubject::updateOrCreate(
                ['name' => $subject['name']],
                [
                    'code' => $subject['code'],
                    'isActive' => true,
                ]
            );
        }
    }
}