<?php

namespace App\Support;

use App\Models\ImportSession;

class ImportLifecycle
{
    public const ALLOWED = [
        'CREATED' => ['UPLOADING', 'UPLOADED'],
        'UPLOADING' => ['UPLOADED'],
        'UPLOADED' => ['INSPECTING'],
        'INSPECTING' => ['AWAITING_MAPPING', 'FAILED'],
        'AWAITING_MAPPING' => ['VALIDATING'],
        'VALIDATING' => ['NEEDS_CORRECTION', 'READY_FOR_DRY_RUN', 'FAILED'],
        'NEEDS_CORRECTION' => ['VALIDATING', 'READY_FOR_DRY_RUN'],
        'READY_FOR_DRY_RUN' => ['DRY_RUN_READY'],
        'DRY_RUN_READY' => ['FINALIZING'],
        'FINALIZING' => ['COMPLETED', 'FAILED'],
        'RETRYING' => ['INSPECTING', 'FAILED'],
    ];

    public static function transition(ImportSession $session, string $to): bool
    {
        $from = $session->state;
        if ($from === $to) {
            return true;
        }

        $allowed = self::ALLOWED[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            return false;
        }

        $updated = ImportSession::where('id', $session->id)
            ->where('state', $from)
            ->update(['state' => $to]);

        if ($updated) {
            $session->state = $to;
            return true;
        }

        $session->refresh();
        return $session->state === $to;
    }
}
