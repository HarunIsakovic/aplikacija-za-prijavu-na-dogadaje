<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

class ExpireEvents extends Command
{
    protected $signature = 'events:expire';

    protected $description = 'Automatski prebacuje završene događaje u status expired';

    public function handle(): int
    {
        $expiredCount = Event::query()
            ->where('status', 'published')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->update([
                'status' => 'expired',
            ]);

        $this->info("Broj završenih događaja: {$expiredCount}");

        return self::SUCCESS;
    }
}
