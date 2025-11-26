<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Throttling system for Panopto SOAP API operations
 *
 * @package block_panopto
 * @copyright Panopto 2025
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/panopto_data.php');

/**
 * Throttling utility for managing Panopto API call rates
 */
class panopto_throttling {
    /** @var array Static counters for different operation types */
    private static $counters = [
        'usermanagement_sync' => 0,
        'usermanagement_get' => 0,
        'usermanagement_create' => 0,
        'usermanagement_update' => 0,
        'sessionmanagement' => 0,
        'bulk_operations' => 0,
    ];

    /** @var array Last operation timestamps for rate limiting */
    private static $lastoperationtimes = [
        'usermanagement_sync' => 0,
        'usermanagement_get' => 0,
        'usermanagement_create' => 0,
        'usermanagement_update' => 0,
        'sessionmanagement' => 0,
        'bulk_operations' => 0,
    ];

    /** @var int Default minimum interval between API calls (seconds) */
    const DEFAULT_MIN_INTERVAL = 0.2; // 200ms between calls

    /** @var int Maximum interval during high error rates */
    const MAX_INTERVAL = 5.0; // 5 seconds max

    /** @var array Throttling configuration */
    private static $config = null;

    /**
     * Get throttling configuration - simple, universal settings
     *
     * @return array Throttling configuration
     */
    private static function get_config() {
        if (self::$config === null) {
            // Simple universal settings that work well for all batch sizes.
            // Auto-adjusts only on API errors (429, 5xx) via adaptive throttling.

            self::$config = [
                'enabled' => get_config('block_panopto', 'enable_api_throttling') ?: 1,
                'usermanagement_min_interval' => 0.05, // 50ms - fast but safe.
                'bulk_throttle_batch_size' => 50, // Pause every 50 operations.
                'bulk_throttle_interval' => 1, // 1 second pause.
                'api_max_retries' => 3, // Retry on 429/5xx errors.
                'error_backoff_multiplier' => 2.0,
                'max_concurrent_operations' => 5,
                'enable_adaptive_throttling' => 1, // Slows down on errors.
            ];
        }
        return self::$config;
    }

    /**
     * Apply throttling before UserManagement sync operation
     *
     * @param string $operationname Name of the operation for logging
     * @param int $userid User ID for context
     * @return bool True if operation can proceed
     */
    public static function throttle_usermanagement_sync($operationname = '', $userid = null) {
        $config = self::get_config();

        // Check if throttling is enabled.
        if (!$config['enabled']) {
            return true;
        }

        // Increment counter.
        self::$counters['usermanagement_sync']++;

        // Apply minimum interval throttling.
        $timesincelastsync = microtime(true) - self::$lastoperationtimes['usermanagement_sync'];
        if ($timesincelastsync < $config['usermanagement_min_interval']) {
            $sleeptime = $config['usermanagement_min_interval'] - $timesincelastsync;
            usleep($sleeptime * 1000000); // Convert to microseconds.
        }

        // Update last operation time.
        self::$lastoperationtimes['usermanagement_sync'] = microtime(true);

        // Log throttling if enabled.
        if ($operationname && get_config('block_panopto', 'throttling_debug_logging')) {
            $counter = self::$counters['usermanagement_sync'];
            $context = $userid ? " (user: {$userid})" : "";
            \panopto_data::print_log("UserManagement sync throttling: operation {$counter}{$context} - {$operationname}");
        }

        return true;
    }

    /**
     * Apply throttling before UserManagement get operation
     *
     * @param string $operationname Name of the operation for logging
     * @return bool True if operation can proceed
     */
    public static function throttle_usermanagement_get($operationname = '') {
        $config = self::get_config();

        // Check if throttling is enabled.
        if (!$config['enabled']) {
            return true;
        }

        // Increment counter.
        self::$counters['usermanagement_get']++;

        // Apply minimum interval throttling.
        $timesincelastget = microtime(true) - self::$lastoperationtimes['usermanagement_get'];
        if ($timesincelastget < $config['usermanagement_min_interval']) {
            $sleeptime = $config['usermanagement_min_interval'] - $timesincelastget;
            usleep($sleeptime * 1000000);
        }

        // Update last operation time.
        self::$lastoperationtimes['usermanagement_get'] = microtime(true);

        return true;
    }

    /**
     * Apply throttling for bulk operations
     *
     * @param int $processedcount Number of operations processed so far
     * @param string $operationtype Type of bulk operation
     * @param int $totalcount Total operations to process (optional, for logging)
     * @return int Updated processed count
     */
    public static function throttle_bulk_operation($processedcount, $operationtype = 'bulk_operations', $totalcount = null) {
        $config = self::get_config();

        // Check if throttling is enabled.
        if (!$config['enabled']) {
            return $processedcount + 1;
        }

        // Increment counter.
        self::$counters[$operationtype]++;

        $batchsize = $config['bulk_throttle_batch_size'];
        $interval = $config['bulk_throttle_interval'];

        // Apply batch-based throttling only at batch boundaries.
        if ($processedcount > 0 && $processedcount % $batchsize == 0) {
            $context = $totalcount ? " ({$processedcount}/{$totalcount})" : " ({$processedcount} processed)";

            if ($interval >= 1) {
                // Use sleep for intervals >= 1 second.
                \panopto_data::print_log("Bulk throttling: {$operationtype} - sleeping {$interval}s{$context}");
                sleep($interval);
            } else if ($interval > 0) {
                // Use usleep for sub-second intervals.
                \panopto_data::print_log("Bulk throttling: {$operationtype} - sleeping " . ($interval * 1000) . "ms{$context}");
                usleep($interval * 1000000);
            }
        }

        return $processedcount + 1;
    }

    /**
     * Apply adaptive throttling based on recent error rates
     *
     * @param string $operationtype Type of operation
     * @param bool $haderror Whether the last operation had an error
     * @return float Adaptive delay in seconds
     */
    public static function get_adaptive_delay($operationtype, $haderror = false) {
        if (!self::get_config()['enable_adaptive_throttling']) {
            return 0;
        }

        static $errorcounts = [];
        static $operationcounts = [];

        // Initialize counters if needed.
        if (!isset($errorcounts[$operationtype])) {
            $errorcounts[$operationtype] = 0;
            $operationcounts[$operationtype] = 0;
        }

        $operationcounts[$operationtype]++;

        if ($haderror) {
            $errorcounts[$operationtype]++;
        }

        // Calculate error rate.
        $errorrate = $operationcounts[$operationtype] > 0 ?
            ($errorcounts[$operationtype] / $operationcounts[$operationtype]) : 0;

        // Reset counters periodically to avoid long-term bias.
        if ($operationcounts[$operationtype] > 100) {
            $errorcounts[$operationtype] = (int)($errorcounts[$operationtype] * 0.5);
            $operationcounts[$operationtype] = (int)($operationcounts[$operationtype] * 0.5);
        }

        // Apply adaptive delay based on error rate.
        if ($errorrate > 0.5) { // More than 50% error rate.
            return min(self::MAX_INTERVAL, self::get_config()['usermanagement_min_interval'] * 10);
        } else if ($errorrate > 0.25) { // More than 25% error rate.
            return min(self::MAX_INTERVAL, self::get_config()['usermanagement_min_interval'] * 5);
        } else if ($errorrate > 0.1) { // More than 10% error rate.
            return min(self::MAX_INTERVAL, self::get_config()['usermanagement_min_interval'] * 2);
        }

        return 0; // No adaptive delay needed.
    }

    /**
     * Execute operation with retry logic and exponential backoff throttling
     *
     * @param callable $operation The operation to execute
     * @param array $params Parameters for the operation
     * @param string $operationtype Type of operation for throttling
     * @param string $operationname Name for logging
     * @param int $userid User ID for context
     * @param bool $throwonfailure Whether to throw exception on failure (default: true)
     * @return mixed Operation result or false on failure if throwOnFailure=false
     * @throws Exception If operation fails after retries and throwOnFailure=true
     */
    public static function execute_with_throttling(
        $operation,
        $params,
        $operationtype,
        $operationname,
        $userid = null,
        $throwonfailure = true
    ) {
        $config = self::get_config();

        // If throttling is disabled, just execute the operation directly.
        if (!$config['enabled']) {
            return call_user_func_array($operation, $params);
        }

        $maxretries = $config['api_max_retries'];
        $attempt = 0;
        $lasterror = null;
        $starttime = microtime(true);

        while ($attempt < $maxretries) {
            $attempt++;

            try {
                // Apply throttling before operation.
                if ($operationtype === 'usermanagement_sync') {
                    self::throttle_usermanagement_sync($operationname, $userid);
                } else if ($operationtype === 'usermanagement_get') {
                    self::throttle_usermanagement_get($operationname);
                }

                // Apply exponential backoff with jitter for retries.
                if ($attempt > 1) {
                    $backoffdelay = self::calculate_exponential_backoff($attempt);
                    $adaptivedelay = self::get_adaptive_delay($operationtype, true);

                    $totaldelay = $backoffdelay + $adaptivedelay;

                    if ($totaldelay > 0) {
                        $delaymsg = "Exponential backoff";
                        if ($adaptivedelay > 0) {
                            $delaymsg .= " + adaptive throttling";
                        }
                        \panopto_data::print_log("{$delaymsg}: delaying {$totaldelay}s on attempt {$attempt} " .
                            "for {$operationname}");
                        usleep($totaldelay * 1000000);
                    }
                }

                // Execute the operation.
                $result = call_user_func_array($operation, $params);

                // Success - reset adaptive error tracking and log success.
                if ($attempt > 1) {
                    self::get_adaptive_delay($operationtype, false); // Reset error tracking.
                    $totaltime = microtime(true) - $starttime;
                    \panopto_data::print_log("Operation {$operationname} succeeded on attempt {$attempt} after {$totaltime}s");
                }
                return $result;
            } catch (SoapFault $soapfault) {
                $lasterror = $soapfault;

                // Check if this is a retryable error.
                if (!self::is_retryable_error($soapfault) || $attempt >= $maxretries) {
                    break;
                }
                $retryablemsg = self::get_retry_reason($soapfault);
                \panopto_data::print_log("Retryable SOAP fault on attempt {$attempt}/{$maxretries} " .
                    "for {$operationname}: {$retryablemsg}");
            } catch (Exception $exception) {
                $lasterror = $exception;

                // Check if this is a retryable error.
                if (!self::is_retryable_error($exception) || $attempt >= $maxretries) {
                    break;
                }

                $retryablemsg = self::get_retry_reason($exception);
                \panopto_data::print_log("Retryable exception on attempt {$attempt}/{$maxretries} " .
                    "for {$operationname}: {$retryablemsg}");
            }
        }

        // All retries exhausted.
        $totaltime = microtime(true) - $starttime;
        $errormessage = "Operation {$operationname} failed after {$maxretries} attempts ({$totaltime}s total)";
        if ($userid) {
            $errormessage .= " (user: {$userid})";
        }
        if ($lasterror) {
            $errormessage .= ": " . $lasterror->getMessage();
        }

        // Log the error.
        \panopto_data::print_log($errormessage);

        if ($throwonfailure) {
            throw $lasterror ?: new Exception($errormessage);
        } else {
            return false;
        }
    }

    /**
     * Calculate exponential backoff delay with jitter
     *
     * @param int $attempt Current attempt number (starting from 2)
     * @return float Delay in seconds
     */
    private static function calculate_exponential_backoff($attempt) {
        // Base exponential backoff: 2^(attempt-1) seconds.
        $basedelay = pow(2, $attempt - 1);

        // Cap maximum base delay at 10 seconds (reduced from 30s to prevent timeout on large batches).
        $basedelay = min($basedelay, 10.0);

        // Add jitter (±25%) to prevent thundering herd.
        $jitterrange = $basedelay * 0.25;
        $jitter = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $jitterrange;

        $delay = $basedelay + $jitter;

        return $delay;
    }

    /**
     * Get human-readable retry reason for logging
     *
     * @param Exception $exception The exception that triggered retry
     * @return string Human-readable reason
     */
    private static function get_retry_reason($exception) {
        $message = strtolower($exception->getMessage());
        $code = method_exists($exception, 'getCode') ? $exception->getCode() : 0;

        // Handle HTTP status codes explicitly.
        if ($code == 429) {
            return "HTTP 429 - Rate limit exceeded";
        }

        if (in_array($code, [500, 502, 503, 504])) {
            return "HTTP {$code} error";
        }

        // Handle message-based detection.
        if (strpos($message, 'rate limit') !== false || strpos($message, '429') !== false) {
            return "rate limiting (429)";
        }

        if (strpos($message, 'timeout') !== false) {
            return "timeout";
        }

        if (strpos($message, 'connection') !== false) {
            return "connection error";
        }

        if (strpos($message, 'service unavailable') !== false) {
            return "service unavailable";
        }

        return $exception->getMessage();
    }

    /**
     * Check if an error should trigger a retry
     *
     * @param Exception $exception The error to check
     * @return bool True if retryable
     */
    private static function is_retryable_error($exception) {
        $message = strtolower($exception->getMessage());
        $code = method_exists($exception, 'getCode') ? $exception->getCode() : 0;

        // HTTP status codes that should trigger retries.
        $retryablecodes = [500, 502, 503, 504, 429];
        if (in_array($code, $retryablecodes)) {
            return true;
        }

        // Error messages that indicate temporary issues.
        $retryablepatterns = [
            '500', '502', '503', '504',
            'internal server error',
            'service unavailable',
            'timeout',
            'connection',
            'network',
            'temporary',
            'rate limit',
        ];

        foreach ($retryablepatterns as $pattern) {
            if (strpos($message, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get current throttling statistics
     *
     * @return array Statistics for each operation type
     */
    public static function get_statistics() {
        return [
            'counters' => self::$counters,
            'lastoperationtimes' => self::$lastoperationtimes,
            'config' => self::get_config(),
        ];
    }

    /**
     * Reset throttling counters (useful for testing)
     */
    public static function reset_counters() {
        self::$counters = array_fill_keys(array_keys(self::$counters), 0);
        self::$lastoperationtimes = array_fill_keys(array_keys(self::$lastoperationtimes), 0);
    }

    /**
     * Get current throttle delay for an operation type
     *
     * @param string $operationtype Type of operation
     * @return float Current delay in seconds
     */
    public static function get_current_delay($operationtype) {
        $config = self::get_config();
        $timesincelast = microtime(true) - (self::$lastoperationtimes[$operationtype] ?? 0);
        $requireddelay = $config['usermanagement_min_interval'];

        if ($timesincelast < $requireddelay) {
            return $requireddelay - $timesincelast;
        }

        return 0;
    }
}
