<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Session extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sessions';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'user_id',
        'ip_address',
        'user_agent',
        'payload',
        'last_activity',
        'is_active',
        'expires_at',
        'fingerprint',
        'device_info'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'last_activity' => 'integer',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'device_info' => 'array'
    ];

    /**
     * Get the user that owns the session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a new session record with all necessary data.
     *
     * @param array $data
     * @return static
     */
    public static function createNewSession(array $data): static
    {
        return static::create(array_merge([
            'is_active' => true,
            'expires_at' => now()->addMinutes((int)config('session.lifetime', 120)),
            'last_activity' => time(),
        ], $data));
    }

    /**
     * Mark the session as inactive.
     *
     * @return bool
     */
    public function deactivate(): bool
    {
        return $this->update([
            'is_active' => false,
            'expires_at' => now()
        ]);
    }

    /**
     * Check if the session is active and not expired.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->is_active && 
               $this->expires_at && 
               $this->expires_at->isFuture();
    }

    /**
     * Scope for active sessions only.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope for a specific user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
} 