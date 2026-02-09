<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExtendedCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        // Delete existing categories first to avoid duplicates
        Category::query()->delete();
        
        $categories = [
            // Programming & Technology (5)
            ['name' => 'برمجة', 'slug' => 'programming', 'description' => 'دورات البرمجة وتطوير البرمجيات'],
            ['name' => 'تطوير الويب', 'slug' => 'web-development', 'description' => 'دورات تطوير مواقع الويب'],
            ['name' => 'تطوير التطبيقات', 'slug' => 'app-development', 'description' => 'دورات تطوير تطبيقات الهواتف'],
            ['name' => 'الذكاء الاصطناعي', 'slug' => 'artificial-intelligence', 'description' => 'دورات الذكاء الاصطناعي'],
            ['name' => 'أمن المعلومات', 'slug' => 'cybersecurity', 'description' => 'دورات الأمن السيبراني'],
            
            // Design (3)
            ['name' => 'تصميم', 'slug' => 'design', 'description' => 'دورات التصميم الجرافيكي'],
            ['name' => 'تصميم واجهات المستخدم', 'slug' => 'ui-ux', 'description' => 'دورات UI/UX Design'],
            ['name' => 'تصوير ومونتاج', 'slug' => 'photography', 'description' => 'دورات التصوير والمونتاج'],
            
            // Business & Marketing (4)
            ['name' => 'أعمال', 'slug' => 'business', 'description' => 'دورات ريادة الأعمال'],
            ['name' => 'تسويق', 'slug' => 'marketing', 'description' => 'دورات التسويق'],
            ['name' => 'إدارة مشاريع', 'slug' => 'project-management', 'description' => 'دورات إدارة المشاريع'],
            ['name' => 'محاسبة', 'slug' => 'accounting', 'description' => 'دورات المحاسبة والمالية'],
            
            // Languages (4)
            ['name' => 'لغات', 'slug' => 'languages', 'description' => 'دورات تعلم اللغات'],
            ['name' => 'اللغة الإنجليزية', 'slug' => 'english', 'description' => 'دورات اللغة الإنجليزية'],
            ['name' => 'اللغة الفرنسية', 'slug' => 'french', 'description' => 'دورات اللغة الفرنسية'],
            ['name' => 'اللغة الألمانية', 'slug' => 'german', 'description' => 'دورات اللغة الألمانية'],
            
            // Personal Development (3)
            ['name' => 'تطوير شخصي', 'slug' => 'personal-development', 'description' => 'دورات التطوير الذاتي'],
            ['name' => 'قيادة', 'slug' => 'leadership', 'description' => 'دورات القيادة والإدارة'],
            ['name' => 'التحدث أمام الجمهور', 'slug' => 'public-speaking', 'description' => 'دورات الخطابة'],
            
            // Science & Education (4)
            ['name' => 'علوم', 'slug' => 'science', 'description' => 'دورات العلوم'],
            ['name' => 'رياضيات', 'slug' => 'mathematics', 'description' => 'دورات الرياضيات'],
            ['name' => 'فيزياء', 'slug' => 'physics', 'description' => 'دورات الفيزياء'],
            ['name' => 'كيمياء', 'slug' => 'chemistry', 'description' => 'دورات الكيمياء'],
            
            // Health & Fitness (3)
            ['name' => 'صحة ولياقة', 'slug' => 'health-fitness', 'description' => 'دورات الصحة والرياضة'],
            ['name' => 'تغذية', 'slug' => 'nutrition', 'description' => 'دورات التغذية'],
            ['name' => 'يوغا', 'slug' => 'yoga', 'description' => 'دورات اليوغا والتأمل'],
            
            // Arts & Music (3)
            ['name' => 'موسيقى', 'slug' => 'music', 'description' => 'دورات الموسيقى'],
            ['name' => 'عزف جيتار', 'slug' => 'guitar', 'description' => 'دورات العزف على الجيتار'],
            ['name' => 'رسم', 'slug' => 'drawing', 'description' => 'دورات الرسم'],
            
            // Lifestyle (2)
            ['name' => 'طهي', 'slug' => 'cooking', 'description' => 'دورات الطبخ'],
            ['name' => 'حرف يدوية', 'slug' => 'handicrafts', 'description' => 'دورات الحرف اليدوية'],
        ];

        foreach ($categories as $category) {
            Category::create([
                'id' => (string) Str::uuid(),
                'name' => $category['name'],
                'slug' => $category['slug'],
                'description' => $category['description'],
            ]);
        }

        $this->command->info('Categories seeded successfully! Total: ' . count($categories));
    }
}
