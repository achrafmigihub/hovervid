<?php

namespace App\Services\Session;

use Illuminate\Database\ConnectionInterface;
use SessionHandlerInterface;

class DatabaseSessionHandler implements SessionHandlerInterface
{
    /**
     * The database connection instance.
     */
    protected ConnectionInterface $connection;

    /**
     * The name of the session table.
     */
    protected string $table;

    /**
     * The number of minutes the session should be valid.
     */
    protected int $minutes;

    /**
     * Create a new database session handler instance.
     */
    public function __construct(ConnectionInterface $connection, string $table, int $minutes)
    {
        $this->connection = $connection;
        $this->table = $table;
        $this->minutes = $minutes;
    }

    /**
     * Open session
     */
    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    /**
     * Close session
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Read session data
     */
    public function read(string $sessionId): string
    {
        try {
            $session = $this->connection->table($this->table)
                ->where('id', $sessionId)
                ->first();

            if ($session) {
                // Check if session hasn't expired
                if ($session->last_activity >= (time() - ($this->minutes * 60))) {
                    return base64_decode($session->payload);
                } else {
                    // Session expired, delete it
                    $this->destroy($sessionId);
                }
            }

            return '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Write session data
     */
    public function write(string $sessionId, string $sessionData): bool
    {
        try {
            $payload = base64_encode($sessionData);
            $lastActivity = time();

            // Use upsert to insert or update
            $this->connection->table($this->table)->updateOrInsert(
                ['id' => $sessionId],
                [
                    'payload' => $payload,
                    'last_activity' => $lastActivity,
                    'user_id' => null, // Set to null or get from session data if needed
                    'ip_address' => request()->ip() ?? null,
                    'user_agent' => request()->header('User-Agent') ?? null,
                ]
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Destroy session
     */
    public function destroy(string $sessionId): bool
    {
        try {
            $this->connection->table($this->table)
                ->where('id', $sessionId)
                ->delete();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Garbage collection
     */
    public function gc(int $maxlifetime): int|false
    {
        try {
            $expiredTime = time() - $maxlifetime;
            
            $deleted = $this->connection->table($this->table)
                ->where('last_activity', '<', $expiredTime)
                ->delete();

            return $deleted;
        } catch (\Exception $e) {
            return false;
        }
    }
} 
