<?php

namespace App\Services;

use App\Models\Setting;

class QuorumSettings
{
    public const KEY_THRESHOLD = 'quorum_threshold';

    public function threshold(): int
    {
        $stored = Setting::query()->find(self::KEY_THRESHOLD)?->value;

        $value = is_numeric($stored)
            ? (int) $stored
            : (int) config('quorum.threshold', 2);

        return max(1, $value);
    }

    public function setThreshold(int $threshold): void
    {
        Setting::putValue(self::KEY_THRESHOLD, max(1, $threshold));
    }
}
