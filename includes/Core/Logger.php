<?php
namespace Kresuber\POS_Pro\Core;
if ( ! defined( 'ABSPATH' ) ) exit;

class Logger {
    public static function log($message, $level = 'INFO') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_entry = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $message);
            error_log($log_entry); // Writes to debug.log
        }
    }
}
