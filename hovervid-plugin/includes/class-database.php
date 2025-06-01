<?php
/**
 * Database connection handler for HoverVid plugin
 *
 * @package HoverVid
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class HoverVid_Database {
    /**
     * Database class instance
     *
     * @var HoverVid_Database
     */
    private static $instance = null;

    /**
     * PDO connection
     * 
     * @var PDO
     */
    private $connection = null;

    /**
     * Database connection parameters
     */
    private $host = '127.0.0.1';
    private $port = '5432';
    private $dbname = 'hovervid_db';
    private $username = 'postgres';
    private $password = 'postgres_hovervid';

    /**
     * Get database instance
     *
     * @return HoverVid_Database
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        try {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->dbname}";
            $this->connection = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // Log the error but don't expose sensitive information
            error_log('HoverVid Database Connection Error: ' . $e->getMessage());
            throw new Exception('Database connection failed. Please check your configuration.');
        }
    }

    /**
     * Get the PDO connection
     *
     * @return PDO
     */
    public function get_connection() {
        return $this->connection;
    }
    
    /**
     * Check if domain is verified in database
     *
     * @param string $domain Domain to check
     * @return array|bool Domain data if found, false otherwise
     */
    public function check_domain_status($domain) {
        try {
            // First check database structure
            $tablesStmt = $this->connection->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name;");
            $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
            error_log('HoverVid Debug: Database tables: ' . implode(', ', $tables));
            
            // Check if domains table exists
            if (!in_array('domains', $tables)) {
                error_log('HoverVid Debug: Domains table does not exist in database');
                return [
                    'is_active' => false, 
                    'message' => "Domain '{$domain}' is not authorized to use the HoverVid plugin.",
                    'domain_exists' => false
                ];
            }
            
            // Check domains table structure
            $columnsStmt = $this->connection->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'domains' ORDER BY column_name;");
            $columns = $columnsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            error_log('HoverVid Debug: Domains table columns: ' . json_encode($columns));
            
            // Now check for the domain
            $stmt = $this->connection->prepare("SELECT * FROM domains WHERE domain = :domain LIMIT 1");
            $stmt->bindParam(':domain', $domain, PDO::PARAM_STR);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                error_log('HoverVid Debug: Domain not found in database: ' . $domain);
                // For domains not in the database, return unauthorized with error message
                return [
                    'is_active' => false, 
                    'message' => "Domain '{$domain}' is not authorized to use the HoverVid plugin.",
                    'domain_exists' => false
                ];
            }
            
            // Log raw domain record for debugging
            error_log('HoverVid Debug: Raw domain record: ' . json_encode($result));
            
            // Check is_verified column instead of is_active
            $is_verified = false;
            if (isset($result['is_verified'])) {
                $original_value = $result['is_verified'];
                
                // Convert various PostgreSQL boolean representations to PHP boolean
                if (is_string($original_value)) {
                    $is_verified = in_array(strtolower($original_value), ['t', 'true', '1', 'yes', 'y'], true);
                } else if (is_numeric($original_value)) {
                    $is_verified = (bool)(int)$original_value;
                } else {
                    $is_verified = (bool)$original_value;
                }
                
                // Log both original and converted values
                error_log('HoverVid Debug: Original is_verified: ' . var_export($original_value, true) . ' (type: ' . gettype($original_value) . ')');
                error_log('HoverVid Debug: Converted is_verified: ' . ($is_verified ? 'true' : 'false') . ' (type: ' . gettype($is_verified) . ')');
            } else {
                // If is_verified is not set, default to false for security
                $is_verified = false;
                error_log('HoverVid Debug: is_verified not found in record, defaulting to false for security');
            }
            
            // Set the plugin status based on is_verified
            $result['is_active'] = $is_verified;
            
            // Set appropriate message based on verification status
            if ($is_verified) {
                $result['message'] = "Domain '{$domain}' is verified and active.";
            } else {
                $result['message'] = "Your subscription or license has expired. Please contact support to renew your access.";
            }
            
            // Flag that the domain exists in the database
            $result['domain_exists'] = true;
            
            // Log the verification status for debugging
            $verification_status = $is_verified ? 'verified' : 'unverified';
            error_log('HoverVid Debug: Domain ' . $domain . ' verification status: ' . $verification_status);
            
            return $result; // Return the entire domain record with properly set is_active based on is_verified
        } catch (PDOException $e) {
            error_log('HoverVid Domain Check Error: ' . $e->getMessage());
            // In case of errors, default to inactive for security
            return [
                'is_active' => false, 
                'message' => 'HoverVid database connection error. Please contact the plugin provider.',
                'domain_exists' => false
            ];
        }
    }

    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
