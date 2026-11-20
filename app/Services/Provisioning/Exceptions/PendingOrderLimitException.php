<?php

namespace App\Services\Provisioning\Exceptions;

use RuntimeException;

/**
 * Anti-DoS (revision 1): a user may hold at most 2 pending (unpaid) orders.
 * Pending orders expire via ExpirePendingOrdersCommand (24h TTL) and never
 * hold quota slots, so this cap only limits checkout spam.
 */
class PendingOrderLimitException extends RuntimeException
{
    public function __construct(public readonly int $max = 2)
    {
        parent::__construct("User already holds {$max} pending orders.");
    }

    public function customerMessage(): string
    {
        return 'Anda masih memiliki pesanan yang belum dibayar. Selesaikan atau batalkan pesanan tersebut terlebih dahulu sebelum membuat pesanan baru.';
    }
}