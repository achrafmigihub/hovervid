<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // Format the user data according to frontend expectations
        return [
            'id' => $this->id,
            'fullName' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'avatar' => null, // Frontend expects this field, even if null
            'currentPlan' => $this->whenLoaded('subscriptions', function () {
                return $this->subscriptions->first() ? $this->subscriptions->first()->plan->name : 'Basic';
            }, 'Basic'),
            'billing' => 'Auto Debit', // Default or from payment method
            'company' => 'HoverVid', // Default company name
            'country' => 'US', // Default country
            'contact' => null, // Contact information (could be added later)
            // Format dates in ISO 8601 format as expected by JavaScript Date
            'created_at' => Carbon::parse($this->created_at)->toIso8601String(),
            'updated_at' => Carbon::parse($this->updated_at)->toIso8601String(),
            // Include any soft-delete info if applicable
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toIso8601String() : null,
        ];
    }
} 
