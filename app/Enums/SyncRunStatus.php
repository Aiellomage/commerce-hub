<?php

namespace App\Enums;

enum SyncRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Determine if the run has reached a final state and will not change anymore.
     */
    public function isFinished(): bool
    {
        return match ($this) {
            self::Completed, self::Failed => true,
            self::Pending, self::Running => false,
        };
    }
}
