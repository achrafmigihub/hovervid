<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DomainRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $user = Auth::user();
        $currentDomain = null;
        
        // Log the incoming request data for debugging
        Log::info('DomainRequest validation started', [
            'method' => $this->method(),
            'input_data' => $this->all(),
            'user_id' => $user ? $user->id : null,
            'user_domain_id' => $user ? $user->domain_id : null
        ]);
        
        // For updates, get the current user's active domain to ignore in unique validation
        if (($this->isMethod('PUT') || $this->isMethod('PATCH')) && $user) {
            $currentDomain = \App\Models\Domain::where('user_id', $user->id)
                                              ->where('is_active', true)
                                              ->first();
        }

        $rules = [
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9]*\.[a-zA-Z]{2,}$/',
                Rule::unique('domains', 'domain')->ignore($currentDomain ? $currentDomain->id : null)
            ],
            'platform' => 'sometimes|string|max:50|in:wordpress,shopify,wix,custom',
            'status' => 'sometimes|string|in:active,inactive,pending,suspended',
        ];

        // If this is an update request, make domain field optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['domain'][0] = 'sometimes';
        }

        Log::info('DomainRequest validation rules generated', [
            'rules' => $rules,
            'current_domain_ignored' => $currentDomain ? $currentDomain->id : null
        ]);

        return $rules;
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'domain.required' => 'A domain name is required.',
            'domain.regex' => 'Please enter a valid domain name (e.g., example.com).',
            'domain.unique' => 'This domain is already registered by another user.',
            'domain.max' => 'Domain name cannot exceed 255 characters.',
            'platform.in' => 'Platform must be one of: wordpress, shopify, wix, custom.',
            'status.in' => 'Status must be one of: active, inactive, pending, suspended.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('domain')) {
            $this->merge([
                'domain' => strtolower(trim($this->domain))
            ]);
        }
    }

    /**
     * Get validated and formatted data
     */
    public function getValidatedData(): array
    {
        $validated = $this->validated();
        
        // Set default values for new domains
        if ($this->isMethod('POST')) {
            $validated['platform'] = $validated['platform'] ?? 'wordpress';
            
            // For popup domain registration, make domain active by default
            // For admin-created domains, they might want to control this separately
            $validated['status'] = 'active';
            $validated['is_active'] = true;
            $validated['is_verified'] = false;
            
            // Auto-assign user_id if not provided (for client self-registration)
            if (!isset($validated['user_id'])) {
                $validated['user_id'] = Auth::id();
            }
        }

        return $validated;
    }
} 
