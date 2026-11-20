<?php

namespace App\Services\Provisioning\Support;

/**
 * Strong server login password meeting upstream complexity rules
 * (12–30 chars, at least one of each: uppercase, lowercase, digit, symbol).
 * Shared by the provider (provision/reinstall) and ResetPasswordJob so the
 * generated secret never needs to travel through task.input/output.
 */
final class ServerPassword
{
    public static function generate(int $extra = 12): string
    {
        $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789', '!@#$%^&*'];
        $all = implode('', $sets);

        $chars = [];
        foreach ($sets as $set) {
            $chars[] = $set[random_int(0, strlen($set) - 1)];
        }
        for ($i = 0; $i < $extra; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        shuffle($chars);

        return implode('', $chars);
    }
}