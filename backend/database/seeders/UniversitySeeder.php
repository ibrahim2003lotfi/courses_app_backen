<?php

namespace Database\Seeders;

use App\Models\University;
use App\Models\Faculty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UniversitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $universities = [
            [
                'name' => 'جامعة دمشق',
                'slug' => 'damascus-university',
                'city' => 'دمشق',
                'country' => 'سوريا',
                'type' => 'حكومية',
                'logo_url' => 'https://via.placeholder.com/200x200/4B5563/FFFFFF?text=Damascus+University',
                'website_url' => 'https://www.damascusuniversity.edu.sy',
                'description' => 'أقدم جامعة سورية، تأسست عام 1923',
            ],
            [
                'name' => 'جامعة حلب',
                'slug' => 'aleppo-university',
                'city' => 'حلب',
                'country' => 'سوريا',
                'type' => 'حكومية',
                'logo_url' => 'https://via.placeholder.com/200x200/4B5563/FFFFFF?text=Aleppo+University',
                'website_url' => 'https://www.alepuniv.edu.sy',
                'description' => 'جامعة حكومية تأسست عام 1958',
            ],
            [
                'name' => 'جامعة تشرين',
                'slug' => 'tishreen-university',
                'city' => 'اللاذقية',
                'country' => 'سوريا',
                'type' => 'حكومية',
                'logo_url' => 'https://via.placeholder.com/200x200/4B5563/FFFFFF?text=Tishreen+University',
                'website_url' => 'https://www.tishreen.edu.sy',
                'description' => 'جامعة حكومية تأسست عام 1971',
            ],
            [
                'name' => 'جامعة البعث',
                'slug' => 'baath-university',
                'city' => 'حمص',
                'country' => 'سوريا',
                'type' => 'حكومية',
                'logo_url' => 'https://via.placeholder.com/200x200/4B5563/FFFFFF?text=Baath+University',
                'website_url' => 'https://www.albaath-univ.edu.sy',
                'description' => 'جامعة حكومية تأسست عام 1979',
            ],
            [
                'name' => 'الجامعة الافتراضية السورية',
                'slug' => 'syrian-virtual-university',
                'city' => 'دمشق',
                'country' => 'سوريا',
                'type' => 'افتراضية',
                'logo_url' => 'https://via.placeholder.com/200x200/4B5563/FFFFFF?text=Virtual+University',
                'website_url' => 'https://www.svuonline.org',
                'description' => 'جامعة افتراضية للتعليم عن بعد تأسست عام 2002',
            ],
            [
                'name' => 'جامعة الوادي الدولية',
                'slug' => 'wadi-international-university',
                'city' => 'دمشق',
                'country' => 'سوريا',
                'type' => 'خاصة',
                'logo_url' => 'https://via.placeholder.com/200x200/4B5563/FFFFFF?text=Wadi+International',
                'website_url' => 'https://www.wiu.edu.sy',
                'description' => 'جامعة خاصة تأسست عام 2005',
            ],
            [
                'name' => 'جامعة القلمون',
                'slug' => 'qalamoun-university',
                'city' => 'دمشق',
                'country' => 'سوريا',
                'type' => 'خاصة',
                'logo_url' => 'https://via.placeholder.com/200x200/4B5563/FFFFFF?text=Qalamoun+University',
                'website_url' => 'https://www.qu.edu.sy',
                'description' => 'جامعة خاصة تأسست عام 2003',
            ],
            [
                'name' => 'جامعة الاتحاد',
                'slug' => 'ittihad-university',
                'city' => 'حلب',
                'country' => 'سوريا',
                'type' => 'خاصة',
                'logo_url' => 'https://via.placeholder.com/200x200/4B5563/FFFFFF?text=Ittihad+University',
                'website_url' => 'https://www.ittihad.edu.sy',
                'description' => 'جامعة خاصة تأسست عام 2005',
            ],
        ];

        foreach ($universities as $uniData) {
            University::firstOrCreate(
                ['slug' => $uniData['slug']],
                $uniData
            );
        }

        // Add sample faculties for Damascus University
        $damascusUni = University::where('slug', 'damascus-university')->first();
        if ($damascusUni) {
            $faculties = [
                ['name' => 'كلية الطب', 'slug' => 'medicine', 'type' => 'علمي'],
                ['name' => 'كلية الهندسة المدنية', 'slug' => 'civil-engineering', 'type' => 'علمي'],
                ['name' => 'كلية الهندسة الميكانيكية', 'slug' => 'mechanical-engineering', 'type' => 'علمي'],
                ['name' => 'كلية الحقوق', 'slug' => 'law', 'type' => 'أدبي'],
                ['name' => 'كلية الاقتصاد', 'slug' => 'economics', 'type' => 'أدبي'],
                ['name' => 'كلية العلوم', 'slug' => 'science', 'type' => 'علمي'],
                ['name' => 'كلية الصيدلة', 'slug' => 'pharmacy', 'type' => 'علمي'],
                ['name' => 'كلية طب الأسنان', 'slug' => 'dentistry', 'type' => 'علمي'],
            ];

            foreach ($faculties as $facultyData) {
                Faculty::firstOrCreate(
                    [
                        'university_id' => $damascusUni->id,
                        'slug' => $facultyData['slug']
                    ],
                    [
                        'name' => $facultyData['name'],
                        'type' => $facultyData['type'],
                        'description' => 'كلية ' . $facultyData['name'] . ' في جامعة دمشق',
                    ]
                );
            }
        }

        // Add sample faculties for Aleppo University
        $aleppoUni = University::where('slug', 'aleppo-university')->first();
        if ($aleppoUni) {
            $faculties = [
                ['name' => 'كلية الطب', 'slug' => 'medicine', 'type' => 'علمي'],
                ['name' => 'كلية الهندسة', 'slug' => 'engineering', 'type' => 'علمي'],
                ['name' => 'كلية الاقتصاد', 'slug' => 'economics', 'type' => 'أدبي'],
            ];

            foreach ($faculties as $facultyData) {
                Faculty::firstOrCreate(
                    [
                        'university_id' => $aleppoUni->id,
                        'slug' => $facultyData['slug']
                    ],
                    [
                        'name' => $facultyData['name'],
                        'type' => $facultyData['type'],
                        'description' => 'كلية ' . $facultyData['name'] . ' في جامعة حلب',
                    ]
                );
            }
        }

        // Add faculties for Tishreen University
        $tishreenUni = University::where('slug', 'tishreen-university')->first();
        if ($tishreenUni) {
            $faculties = [
                ['name' => 'كلية الطب', 'slug' => 'medicine', 'type' => 'علمي'],
                ['name' => 'كلية الهندسة', 'slug' => 'engineering', 'type' => 'علمي'],
                ['name' => 'كلية الحقوق', 'slug' => 'law', 'type' => 'أدبي'],
                ['name' => 'كلية التمريض', 'slug' => 'nursing', 'type' => 'علمي'],
                ['name' => 'كلية الزراعة', 'slug' => 'agriculture', 'type' => 'علمي'],
            ];

            foreach ($faculties as $facultyData) {
                Faculty::firstOrCreate(
                    [
                        'university_id' => $tishreenUni->id,
                        'slug' => $facultyData['slug']
                    ],
                    [
                        'name' => $facultyData['name'],
                        'type' => $facultyData['type'],
                        'description' => 'كلية ' . $facultyData['name'] . ' في جامعة تشرين',
                    ]
                );
            }
        }

        // Add faculties for Baath University
        $baathUni = University::where('slug', 'baath-university')->first();
        if ($baathUni) {
            $faculties = [
                ['name' => 'كلية الطب', 'slug' => 'medicine', 'type' => 'علمي'],
                ['name' => 'كلية الهندسة', 'slug' => 'engineering', 'type' => 'علمي'],
                ['name' => 'كلية العلوم', 'slug' => 'science', 'type' => 'علمي'],
                ['name' => 'كلية الاقتصاد', 'slug' => 'economics', 'type' => 'أدبي'],
                ['name' => 'كلية التربية', 'slug' => 'education', 'type' => 'أدبي'],
            ];

            foreach ($faculties as $facultyData) {
                Faculty::firstOrCreate(
                    [
                        'university_id' => $baathUni->id,
                        'slug' => $facultyData['slug']
                    ],
                    [
                        'name' => $facultyData['name'],
                        'type' => $facultyData['type'],
                        'description' => 'كلية ' . $facultyData['name'] . ' في جامعة البعث',
                    ]
                );
            }
        }

        // Add faculties for Syrian Virtual University
        $virtualUni = University::where('slug', 'syrian-virtual-university')->first();
        if ($virtualUni) {
            $faculties = [
                ['name' => 'كلية تقنيات المعلومات', 'slug' => 'it', 'type' => 'علمي'],
                ['name' => 'كلية إدارة الأعمال', 'slug' => 'business', 'type' => 'أدبي'],
                ['name' => 'كلية اللغات', 'slug' => 'languages', 'type' => 'أدبي'],
                ['name' => 'كلية البرمجيات', 'slug' => 'software', 'type' => 'علمي'],
            ];

            foreach ($faculties as $facultyData) {
                Faculty::firstOrCreate(
                    [
                        'university_id' => $virtualUni->id,
                        'slug' => $facultyData['slug']
                    ],
                    [
                        'name' => $facultyData['name'],
                        'type' => $facultyData['type'],
                        'description' => 'كلية ' . $facultyData['name'] . ' في الجامعة الافتراضية',
                    ]
                );
            }
        }

        // Add faculties for Wadi International University
        $wadiUni = University::where('slug', 'wadi-international-university')->first();
        if ($wadiUni) {
            $faculties = [
                ['name' => 'كلية الطب', 'slug' => 'medicine', 'type' => 'علمي'],
                ['name' => 'كلية طب الأسنان', 'slug' => 'dentistry', 'type' => 'علمي'],
                ['name' => 'كلية الصيدلة', 'slug' => 'pharmacy', 'type' => 'علمي'],
                ['name' => 'كلية الهندسة', 'slug' => 'engineering', 'type' => 'علمي'],
            ];

            foreach ($faculties as $facultyData) {
                Faculty::firstOrCreate(
                    [
                        'university_id' => $wadiUni->id,
                        'slug' => $facultyData['slug']
                    ],
                    [
                        'name' => $facultyData['name'],
                        'type' => $facultyData['type'],
                        'description' => 'كلية ' . $facultyData['name'] . ' في جامعة الوادي',
                    ]
                );
            }
        }

        // Add faculties for Qalamoun University
        $qalamounUni = University::where('slug', 'qalamoun-university')->first();
        if ($qalamounUni) {
            $faculties = [
                ['name' => 'كلية الطب', 'slug' => 'medicine', 'type' => 'علمي'],
                ['name' => 'كلية الهندسة', 'slug' => 'engineering', 'type' => 'علمي'],
                ['name' => 'كلية العلوم', 'slug' => 'science', 'type' => 'علمي'],
                ['name' => 'كلية إدارة الأعمال', 'slug' => 'business', 'type' => 'أدبي'],
            ];

            foreach ($faculties as $facultyData) {
                Faculty::firstOrCreate(
                    [
                        'university_id' => $qalamounUni->id,
                        'slug' => $facultyData['slug']
                    ],
                    [
                        'name' => $facultyData['name'],
                        'type' => $facultyData['type'],
                        'description' => 'كلية ' . $facultyData['name'] . ' في جامعة القلمون',
                    ]
                );
            }
        }

        // Add faculties for Ittihad University
        $ittihadUni = University::where('slug', 'ittihad-university')->first();
        if ($ittihadUni) {
            $faculties = [
                ['name' => 'كلية الطب', 'slug' => 'medicine', 'type' => 'علمي'],
                ['name' => 'كلية الهندسة', 'slug' => 'engineering', 'type' => 'علمي'],
                ['name' => 'كلية الحقوق', 'slug' => 'law', 'type' => 'أدبي'],
                ['name' => 'كلية الإعلام', 'slug' => 'media', 'type' => 'أدبي'],
            ];

            foreach ($faculties as $facultyData) {
                Faculty::firstOrCreate(
                    [
                        'university_id' => $ittihadUni->id,
                        'slug' => $facultyData['slug']
                    ],
                    [
                        'name' => $facultyData['name'],
                        'type' => $facultyData['type'],
                        'description' => 'كلية ' . $facultyData['name'] . ' في جامعة الاتحاد',
                    ]
                );
            }
        }
    }
}
