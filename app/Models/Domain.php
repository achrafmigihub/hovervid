<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Domain extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'domains';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'domain',
        'platform',
        'plugin_status',
        'status',
        'is_active',
        'is_verified',
        'api_key',
        'verification_token',
        'last_checked_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'last_checked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Default attribute values.
     *
     * @var array
     */
    protected $attributes = [
        'platform' => 'wordpress',
        'plugin_status' => 'inactive',
        'status' => 'inactive',
        'is_active' => false,
        'is_verified' => false,
    ];

    /**
     * Get the user that owns the domain.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include active domains.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('status', 'active');
    }

    /**
     * Scope a query to only include verified domains.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Check if the domain is authorized to use the plugin.
     */
    public function isAuthorized(): bool
    {
        return $this->is_active && $this->status === 'active';
    }

    /**
     * Generate a unique API key for this domain.
     */
    public function generateApiKey(): void
    {
        $this->api_key = Str::uuid();
    }

    /**
     * Generate a verification token for this domain.
     */
    public function generateVerificationToken(): void
    {
        $this->verification_token = Str::random(32);
    }
} 
