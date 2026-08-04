<?php

class MarkInvoicesOverdue extends Command
{
    protected $signature = 'finance:mark-overdue';
    protected $description = 'Bascule en retard les échéances dépassées non soldées';

    public function handle(): int
    {
        $count = StudentInvoice::withoutGlobalScopes()
            ->where('status', 'pending')
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', today())
            ->update(['status' => 'overdue']);

        $this->info("{$count} échéance(s) basculée(s) en retard.");
        return self::SUCCESS;
    }
}