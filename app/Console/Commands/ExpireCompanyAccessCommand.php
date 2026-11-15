<?php

namespace App\Console\Commands;

use App\Services\CompanyLifecycleService;
use Illuminate\Console\Command;

class ExpireCompanyAccessCommand extends Command
{
    protected $signature = 'stockora:expire-companies';

    protected $description = 'Transition active Stockora customer companies whose access_expires_at has passed to EXPIRED status.';

    public function handle(CompanyLifecycleService $lifecycle): int
    {
        $expiredCount = $lifecycle->syncExpiredCompanies();
        $this->info("Expired {$expiredCount} overdue customer company workspace(s).");

        return self::SUCCESS;
    }
}
