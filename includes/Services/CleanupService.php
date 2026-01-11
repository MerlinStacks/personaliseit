<?php
namespace PersonaliseIt\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cleanup Service
 * Handles periodic cleanup of temporary files and old proofs.
 */
class CleanupService {

    const CRON_HOOK = 'personaliseit_daily_cleanup';

    /**
     * Init hooks
     */
    public static function init() {
        add_action( self::CRON_HOOK, [ __CLASS__, 'cleanup_proofs' ] );
        
        // Schedule if not already scheduled
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    /**
     * Delete proofs older than retention period
     */
    public static function cleanup_proofs() {
        $retention_days = apply_filters( 'personaliseit_proof_retention_days', 90 );
        $threshold = time() - ( $retention_days * DAY_IN_SECONDS );
        
        $upload_dir = wp_upload_dir();
        $proof_dir = $upload_dir['basedir'] . '/personaliseit-proofs/';
        
        if ( ! file_exists( $proof_dir ) ) {
            return;
        }

        $files = scandir( $proof_dir );
        if ( ! $files ) {
            return;
        }

        foreach ( $files as $file ) {
            if ( $file === '.' || $file === '..' ) continue;
            
            $filepath = $proof_dir . $file;
            if ( is_file( $filepath ) ) {
                $mtime = filemtime( $filepath );
                if ( $mtime < $threshold ) {
                    unlink( $filepath );
                }
            }
        }
    }
}
