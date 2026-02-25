<?php
defined('ABSPATH') || die('No direct script access allowed.');

/**
 * Chunk-based AJAX batch processor.
 * Queues a list of attachment IDs + action into a WordPress transient,
 * then processes them 30 at a time via repeated AJAX calls.
 */
class WP_Washer_Batch_Processor
{

    const QUEUE_TRANSIENT = 'wpwasher_queue_%s';
    const PROGRESS_TRANSIENT = 'wpwasher_progress_%s';
    const TRANSIENT_TTL = 3600; // 1 hour

    /**
     * Enqueue a new batch job.
     *
     * @param string $job_id  Unique job identifier (returned to client).
     * @param array  $ids     Attachment IDs.
     * @param string $action  Action to perform (delete_meta|delete_file|strip_exif|remove_thumbs|clean_orphan_meta).
     */
    public static function enqueue($job_id, array $ids, $action)
    {
        $ids = array_map('absint', $ids);
        $ids = array_filter($ids);
        $ids = array_values($ids);

        $queue = array(
            'ids' => $ids,
            'action' => sanitize_key($action),
            'batch_key' => $job_id,
        );

        set_transient(sprintf(self::QUEUE_TRANSIENT, $job_id), $queue, self::TRANSIENT_TTL);
        set_transient(sprintf(self::PROGRESS_TRANSIENT, $job_id), array(
            'total' => count($ids),
            'processed' => 0,
            'success' => 0,
            'skipped' => 0,
            'errors' => array(),
            'done' => false,
        ), self::TRANSIENT_TTL);
    }

    /**
     * Process the next chunk for a job.
     *
     * @param string $job_id Job identifier.
     * @return array Current progress snapshot.
     */
    public static function process_chunk($job_id)
    {
        $queue = get_transient(sprintf(self::QUEUE_TRANSIENT, $job_id));
        $progress = get_transient(sprintf(self::PROGRESS_TRANSIENT, $job_id));

        if (!$queue || !$progress || $progress['done']) {
            return $progress ?: array('done' => true, 'processed' => 0, 'total' => 0);
        }

        $batch_size = (int) WP_Washer_Settings::get('batch_size', 30);
        $chunk = array_splice($queue['ids'], 0, $batch_size);
        $action = $queue['action'];
        $batch_key = $queue['batch_key'];

        foreach ($chunk as $attachment_id) {
            // Memory safety guard – pause if > 80 % of memory limit used.
            if (self::memory_exceeded()) {
                $progress['errors'][] = 'Memory limit reached. Pausing. Resume processing.';
                break;
            }

            // Snapshot metadata before operation (for undo).
            WP_Washer_Restore::snapshot($attachment_id, $batch_key, (string) get_attached_file($attachment_id));

            $result = self::execute_action($action, $attachment_id);

            if ($result['success']) {
                $progress['success']++;
            } else {
                if (strpos($result['message'], 'Skipped') !== false || strpos($result['message'], 'only applies') !== false) {
                    $progress['skipped']++;
                } else {
                    $progress['errors'][] = $result['message'];
                }
            }

            $progress['processed']++;
        }

        // Save remaining IDs back.
        $queue['ids'] = array_values($queue['ids']);
        if (empty($queue['ids'])) {
            $progress['done'] = true;
            delete_transient(sprintf(self::QUEUE_TRANSIENT, $job_id));
        } else {
            set_transient(sprintf(self::QUEUE_TRANSIENT, $job_id), $queue, self::TRANSIENT_TTL);
        }

        set_transient(sprintf(self::PROGRESS_TRANSIENT, $job_id), $progress, self::TRANSIENT_TTL);
        return $progress;
    }

    /**
     * Get the current progress of a job.
     *
     * @param string $job_id Job identifier.
     * @return array|null
     */
    public static function get_progress($job_id)
    {
        return get_transient(sprintf(self::PROGRESS_TRANSIENT, $job_id));
    }

    /**
     * Route an action to the appropriate Media Cleaner method.
     */
    private static function execute_action($action, $attachment_id)
    {
        switch ($action) {
            case 'delete_meta':
                return WP_Washer_Media_Cleaner::delete_meta($attachment_id);
            case 'delete_file':
                return WP_Washer_Media_Cleaner::delete_attachment($attachment_id);
            case 'strip_exif':
                return WP_Washer_Media_Cleaner::strip_exif($attachment_id);
            case 'remove_thumbs':
                return WP_Washer_Media_Cleaner::remove_unused_thumbnails($attachment_id);
            case 'clean_orphan_meta':
                return WP_Washer_Media_Cleaner::clean_orphaned_postmeta($attachment_id);
            default:
                return array('success' => false, 'message' => "Unknown action: {$action}");
        }
    }

    /**
     * Check if PHP memory usage exceeds 80 % of the limit.
     */
    private static function memory_exceeded()
    {
        $limit = self::parse_memory_limit(ini_get('memory_limit'));
        if ($limit <= 0) {
            return false; // No limit.
        }
        return (memory_get_usage(true) / $limit) > 0.80;
    }

    private static function parse_memory_limit($limit)
    {
        if ('-1' === $limit) {
            return -1;
        }
        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
        }
        return $value;
    }
}
