<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SecureFormRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        // Sanitize string inputs
        $this->sanitizeInputs();
    }

    protected function sanitizeInputs()
    {
        $input = $this->all();

        array_walk_recursive($input, function (&$value, $key) {
            if (is_string($value)) {
                // Basic XSS protection - remove script tags
                $value = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $value);
                
                // Trim and clean strings
                $value = trim($value);
                $value = strip_tags($value);
                
                // For specific fields, apply additional cleaning
                if (in_array($key, ['name', 'title', 'description'])) {
                    $value = Str::limit($value, 1000); // Prevent extremely long inputs
                }
            }
        });

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'required' => 'The :attribute field is required.',
            'email' => 'The :attribute must be a valid email address.',
            'unique' => 'The :attribute has already been taken.',
            'min' => 'The :attribute must be at least :min characters.',
            'max' => 'The :attribute may not be greater than :max characters.',
        ];
    }
}