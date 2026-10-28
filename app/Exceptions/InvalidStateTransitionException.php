<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar oleh OrderStateMachine / VpsStateMachine ketika ada percobaan
 * transisi status yang tidak diizinkan oleh definisi state machine.
 */
class InvalidStateTransitionException extends RuntimeException
{
    public function __construct(
        public readonly string $entity,
        public readonly string $fromStatus,
        public readonly string $toStatus,
        ?string $extraMessage = null
    ) {
        $msg = sprintf(
            'Transisi %s dari [%s] ke [%s] tidak diizinkan.',
            $entity,
            $fromStatus,
            $toStatus
        );
        if ($extraMessage) {
            $msg .= ' ' . $extraMessage;
        }
        parent::__construct($msg);
    }
}
