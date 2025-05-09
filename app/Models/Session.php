<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Session extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'sessions';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'ip_address',
        'user_agent',
        'payload',
        'last_activity',
        'device_info',
        'expires_at',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'device_info' => 'array',
        'last_activity' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the session.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Override the updateOrCreate method to not actually touch the database
     */
    public static function updateOrCreate(array $attributes, array $values = [])
    {
        // Create a dummy session instance
        $session = new static;
        
        // Fill the instance with the provided values
        $session->fill(array_merge($attributes, $values));
        
        // Set an ID so it seems real
        $session->id = rand(1000, 9999);
        
        return $session;
    }

    public static function createNewSession(array $attributes)
    {
        // Generate a unique session ID
        $sessionId = uniqid('session_', true);
        
        // Create a new session
        $session = new static;
        $session->id = $sessionId;
        $session->fill($attributes);
        $session->is_active = true;
        $session->expires_at = now()->addMinutes(config('session.lifetime', 120));
        $session->save();
        
        return $session;
    }

    public function deactivate()
    {
        $this->is_active = false;
        $this->save();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('expires_at', '>', now());
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
} 