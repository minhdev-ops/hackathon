<?php

namespace App\Console\Commands;

use App\Services\GrantService;
use Illuminate\Console\Command;

class ExpireGrants extends Command
{
    protected $signature = 'grants:expire';

    protected $description = 'Đặt các grant đã quá hạn sang trạng thái expired';

    public function handle(GrantService $grants): int
    {
        $expired = $grants->expireStale();

        $this->info("Đã hết hạn {$expired} grant.");

        return self::SUCCESS;
    }
}
