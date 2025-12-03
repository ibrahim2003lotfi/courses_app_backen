<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InstructorApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Required fields
            'education_level' => 'required|string|max:255',
            'department' => 'required|string|max:500',
            'years_of_experience' => 'required|integer|min:0|max:50',
            'experience_description' => 'required|string|min:40|max:2000',
            'agreed_to_terms' => 'required|accepted',
            
            // Optional fields
            'specialization' => 'nullable|string|max:255',
            'linkedin_url' => 'nullable|url|max:500',
            'portfolio_url' => 'nullable|url|max:500',
            
            // Certificates (optional, array of files)
            'certificates' => 'nullable|array|max:5',
            'certificates.*' => 'file|mimes:pdf,jpg,jpeg,png|max:15360', // 15MB
        ];
    }

    public function messages(): array
    {
        return [
            'education_level.required' => 'المستوى التعليمي مطلوب',
            'department.required' => 'التخصص مطلوب',
            'years_of_experience.required' => 'سنوات الخبرة مطلوبة',
            'years_of_experience.min' => 'سنوات الخبرة يجب أن تكون 0 أو أكثر',
            'experience_description.required' => 'وصف الخبرات مطلوب',
            'experience_description.min' => 'وصف الخبرات يجب أن يحتوي على 40 حرف على الأقل',
            'agreed_to_terms.accepted' => 'يجب الموافقة على الشروط والأحكام',
            'linkedin_url.url' => 'رابط LinkedIn غير صالح',
            'portfolio_url.url' => 'رابط الموقع الشخصي غير صالح',
            'certificates.max' => 'لا يمكن رفع أكثر من 5 شهادات',
            'certificates.*.max' => 'حجم الملف يجب أن لا يتجاوز 15MB',
            'certificates.*.mimes' => 'صيغة الملف يجب أن تكون PDF, JPG, JPEG, أو PNG',
        ];
    }
}