<?php

namespace App\Enums;

enum UserRoleEnum: string
{
    case ADMIN = 'admin';
    case CLIENT = 'client';
    case MANAGER = 'manager';
    
    /**
     * Get all available role values
     *
     * @return array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
    
    /**
     * Check if role is admin
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }
    
    /**
     * Check if role is client
     *
     * @return bool
     */
    public function isClient(): bool
    {
        return $this === self::CLIENT;
    }
    
    /**
     * Check if role is manager
     *
     * @return bool
     */
    public function isManager(): bool
    {
        return $this === self::MANAGER;
    }
} 