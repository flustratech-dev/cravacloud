<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * D10 one-time re-encryption of legacy plaintext credentials.
 *
 * Targets: orders.root_password, orders.db_password, vps_instances.db_password
 * (vps_instances.initial_root_password was already encrypted).
 *
 * Reads RAW column values via DB::table (bypasses model casts, so it works
 * before AND after the encrypted casts are deployed). A value that already
 * decrypts successfully is left untouched → fully idempotent.
 *
 * Run immediately after migrating 2026_11_19_100007:
 *   php artisan migrate && php artisan credentials:reencrypt
 *
 * Values are NEVER printed — only counts.
 */
class CredentialsReencryptCommand extends Command
{
    protected $signature = 'credentials:reencrypt
        {--dry-run : Hitung kandidat tanpa mengubah data}
        {--chunk=500 : Ukuran chunk per batch}';

    protected $description = 'Enkripsi ulang kredensial plaintext lama (D10, chunked, idempotent)';

    private const TARGETS = [
        ['orders', 'root_password'],
        ['orders', 'db_password'],
        ['vps_instances', 'db_password'],
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $chunk = max(50, (int) $this->option('chunk'));

        $this->info($dry ? 'DRY RUN — tidak ada perubahan.' : 'Re-enkripsi kredensial lama (nilai tidak pernah ditampilkan).');

        $totalConverted = 0;
        $totalSkipped = 0;

        foreach (self::TARGETS as [$table, $column]) {
            $converted = 0;
            $alreadyEncrypted = 0;
            $empty = 0;

            DB::table($table)
                ->whereNotNull($column)
                ->orderBy('id')
                ->chunkById($chunk, function ($rows) use ($table, $column, $dry, &$converted, &$alreadyEncrypted, &$empty) {
                    foreach ($rows as $row) {
                        $value = (string) $row->{$column};

                        if ($value === '') {
                            $empty++;
                            continue;
                        }

                        if ($this->isEncrypted($value)) {
                            $alreadyEncrypted++;
                            continue;
                        }

                        if (! $dry) {
                            DB::table($table)->where('id', $row->id)->update([
                                $column => Crypt::encryptString($value),
                            ]);
                        }
                        $converted++;
                    }
                });

            $this->line(sprintf(
                '%s.%s: %d dikonversi%s, %d sudah terenkripsi, %d kosong',
                $table,
                $column,
                $converted,
                $dry ? ' (kandidat)' : '',
                $alreadyEncrypted,
                $empty,
            ));

            $totalConverted += $converted;
            $totalSkipped += $alreadyEncrypted + $empty;
        }

        $this->info("Selesai: {$totalConverted} dikonversi, {$totalSkipped} dilewati.");

        return self::SUCCESS;
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        } catch (\Throwable) {
            // Malformed payload (not valid base64/JSON) → treat as plaintext.
            return false;
        }
    }
}