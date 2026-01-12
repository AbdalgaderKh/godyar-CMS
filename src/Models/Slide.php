<?php
namespace App\Models;

/**
 * Adapter model to keep App\Models namespace consistent.
 * Canonical implementation lives in Godyar\Models\Slide (includes/classes/Models/Slide.php).
 */
class Slide
{
    /**
     * Return all slides (delegates to canonical model).
     */
    public static function all(): array
    {
        try {
            $m = new \Godyar\Models\Slide();
            return $m->all();
        } catch (\Throwable $e) {
            @error_log('[App\\Models\\Slide::all] ' . $e->getMessage());
            return [];
        }
    }
}
