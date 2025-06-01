<?php

namespace App\Services;

use App\Models\Domain;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class PluginDomainValidationService
{
    /**
     * Validate if a domain is authorized to use the HoverVid plugin
     *
     * @param string $domain
     * @return array
     */
    public function validateDomain(string $domain): array
    {
        try {
            // Clean the domain name
            $cleanDomain = $this->cleanDomainName($domain);
            
            Log::info("Validating domain: {$cleanDomain}");

            // Find the domain in the database
            $domainRecord = Domain::where('domain', $cleanDomain)->first();

            if (!$domainRecord) {
                Log::warning("Domain validation attempted for non-existent domain: {$cleanDomain}");
                
                return [
                    'authorized' => false,
                    'domain_exists' => false,
                    'is_active' => false,
                    'is_verified' => false,
                    'status' => 'not_found',
                    'message' => "Domain '{$cleanDomain}' is not registered in our system.",
                    'reason' => 'domain_not_found'
                ];
            }

            // Check if domain is active
            if (!$domainRecord->is_active || $domainRecord->status !== 'active') {
                return [
                    'authorized' => false,
                    'domain_exists' => true,
                    'is_active' => false,
                    'is_verified' => $domainRecord->is_verified ?? false,
                    'status' => $domainRecord->status ?? 'inactive',
                    'message' => "Domain '{$cleanDomain}' is not active.",
                    'reason' => 'domain_inactive'
                ];
            }

            // Domain is authorized
            return [
                'authorized' => true,
                'domain_exists' => true,
                'is_active' => true,
                'is_verified' => $domainRecord->is_verified ?? false,
                'status' => $domainRecord->status ?? 'active',
                'message' => "Domain '{$cleanDomain}' is authorized to use the HoverVid plugin.",
                'reason' => 'authorized'
            ];

        } catch (Exception $e) {
            Log::error("Domain validation error for {$domain}: " . $e->getMessage());
            
            return [
                'authorized' => false,
                'domain_exists' => false,
                'is_active' => false,
                'is_verified' => false,
                'status' => 'error',
                'message' => 'System error occurred during domain validation.',
                'reason' => 'validation_error'
            ];
        }
    }

    /**
     * Validate domain format
     *
     * @param string $domain
     * @return bool
     */
    public function isValidDomainFormat(string $domain): bool
    {
        // Clean the domain first
        $cleanDomain = $this->cleanDomainName($domain);
        
        // Basic domain format validation
        if (empty($cleanDomain)) {
            return false;
        }

        // Check for basic domain pattern
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $cleanDomain)) {
            return false;
        }

        // Additional checks
        if (strlen($cleanDomain) > 253) {
            return false;
        }

        // Allow localhost and development domains
        $developmentDomains = ['localhost', '127.0.0.1', '::1'];
        if (in_array($cleanDomain, $developmentDomains)) {
            return true;
        }

        // Must have at least one dot for production domains
        if (strpos($cleanDomain, '.') === false) {
            return false;
        }

        return true;
    }

    /**
     * Clean domain name for consistency
     *
     * @param string $domain
     * @return string
     */
    private function cleanDomainName(string $domain): string
    {
        // Remove protocol
        $domain = preg_replace('#^https?://#i', '', $domain);
        
        // Remove www prefix
        $domain = preg_replace('#^www\.#i', '', $domain);
        
        // Remove path and query string
        $domain = explode('/', $domain)[0];
        $domain = explode('?', $domain)[0];
        $domain = explode('#', $domain)[0];
        
        // Remove port
        $domain = explode(':', $domain)[0];
        
        // Convert to lowercase and trim
        return strtolower(trim($domain));
    }
} 
