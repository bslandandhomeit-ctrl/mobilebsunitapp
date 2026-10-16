<?php

namespace App\Jobs;

use App\User;
use App\Unit;
use App\UnitAction;
use App\UnitDepositRequest;
use App\Helpers\UnitDepositStatus;
use App\Notifications\UnitDepositRequestOverdue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ProcessDepositRequestOverdue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $unit_deposit_request;

    public $tries = 5;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(UnitDepositRequest $unit_deposit_request)
    {
        $this->unit_deposit_request = $unit_deposit_request;   
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (config('unified_catalog.request_writes_enabled')) return $this->handleCanonical();
        try {
            if ( $this->unit_deposit_request->isStatus(UnitDepositStatus::APPROVED) ) {             
                DB::beginTransaction();
                // Setting the Unit Deposit Request to Overdue
                $this->unit_deposit_request->status = UnitDepositStatus::OVERDUE;
                $this->unit_deposit_request->save();

                UnitAction::create([
                    'user_id' => config('app.default_system_user_id'),
                    'unit_id' => $this->unit_deposit_request->unit_id,
                    'action'  => UnitDepositStatus::DEPOSIT,
                    'status_action'  => UnitDepositStatus::OVERDUE,
                    'actionable_type' => $this->unit_deposit_request->getMorphClass(),
                    'actionable_id' => $this->unit_deposit_request->id
                ]);

                DB::commit();
                Notification::send(
                User::find($this->unit_deposit_request->user_id), 
                    new UnitDepositRequestOverdue($this->unit_deposit_request)
                );          
            }
        } catch (\Exception $e) {
            Log::error($e);
        }
    }
    private function handleCanonical()
    {
        $db = $this->unit_deposit_request->getConnection();
        $table = config('unified_catalog.request_unit_table', 'units');
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $table)) throw new \LogicException('Invalid canonical unit table.');
        $expired = $db->transaction(function () use ($db, $table) {
            $query = $this->unit_deposit_request->newQuery()->setEagerLoads([]);
            $preview = $query->find($this->unit_deposit_request->id);
            if (!$preview) return null;
            $unit = $db->table($table)->where('id', $preview->unit_id)->lockForUpdate()->first();
            $deposit = $this->unit_deposit_request->newQuery()->setEagerLoads([])->where('id', $preview->id)->lockForUpdate()->first();
            if (!$deposit || $deposit->unit_id != $preview->unit_id || $deposit->status !== UnitDepositStatus::APPROVED
                || $deposit->to_unit_deposit_request_id !== null || !$deposit->overdueJobDueAt()
                || $deposit->overdueJobDueAt()->isFuture()) return null;
            $action = $unit ? $db->table('unit_actions')->where('id', $unit->current_action_id)->lockForUpdate()->first() : null;
            if (!$action || $action->action !== 'DEPOSIT' || $action->status_action !== UnitDepositStatus::APPROVED
                || $action->actionable_type !== $deposit->getMorphClass() || (int)$action->actionable_id !== (int)$deposit->id) return null;
            if ((new \App\Services\UnifiedDepositFinanceGuard($db))->isFinanced($deposit->id)) return null;
            $deposit->status = UnitDepositStatus::OVERDUE;
            $deposit->save();
            $next = new UnitAction([
                'user_id' => config('app.default_system_user_id'), 'unit_id' => $deposit->unit_id,
                'action' => UnitDepositStatus::DEPOSIT, 'status_action' => UnitDepositStatus::OVERDUE,
                'actionable_type' => $deposit->getMorphClass(), 'actionable_id' => $deposit->id,
            ]);
            $next->setConnection($deposit->getConnectionName());
            $next->save();
            if (config('unified_catalog.overdue_local_database_notifications', false) && app()->environment('local')) {
                $recipient = (new \App\UnifiedOverdueRecipient)->setConnection($deposit->getConnectionName())->find($deposit->user_id);
                if (!$recipient) throw new \LogicException('Overdue recipient mapping is missing.');
                $recipient->notify(new UnitDepositRequestOverdue($deposit));
            }
            return $deposit;
        }, 5);
        if ($expired && !(config('unified_catalog.overdue_local_database_notifications', false) && app()->environment('local'))) Notification::send(User::find($expired->user_id), new UnitDepositRequestOverdue($expired));
    }

}
