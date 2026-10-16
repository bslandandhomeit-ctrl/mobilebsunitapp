<?php

namespace App\Jobs;

use App\UnitAction;
use App\UnitHoldRequest;
use App\Helpers\UnitHoldStatus;
use App\Http\Controllers\Notifications\UnitHoldRequestNotificationController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class ProcessUnitHold implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $unit_hold_request;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(UnitHoldRequest $unit_hold_request)
    {
        $this->unit_hold_request = $unit_hold_request;     
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {   
        try {
            DB::transaction(function () {
                $snapshot = UnitHoldRequest::query()->setEagerLoads([])->find($this->unit_hold_request->id);
                if (!$snapshot) return;
                $canonical = config('unified_catalog.request_writes_enabled');
                $table = $canonical ? config('unified_catalog.request_unit_table', 'units') : 'units';
                $unit = DB::table($table)->where('id', $snapshot->unit_id)->lockForUpdate()->first();
                $hold = UnitHoldRequest::where('id', $snapshot->id)->lockForUpdate()->first();
                if (!$hold || !in_array($hold->status, ['APPROVED', 'PENDING'], true)) return;
                if ($hold->release_date && $hold->release_date->isFuture()) return;
                $pointer = $unit ? ($canonical ? $unit->current_action_id : $unit->unit_action_id) : null;
                $current = $pointer ? UnitAction::find($pointer) : null;
                // A delayed job must never release a newer deposit, contract or hold.
                $ownsCurrent = $current && $current->actionable_type === $hold->getMorphClass()
                    && (int)$current->actionable_id === (int)$hold->id && $current->action === 'HOLD';
                $hold->status = UnitHoldStatus::OVERDUE;
                $hold->actioned_user_id = config('app.default_system_user_id');
                $hold->actioned_at = now();
                $hold->save();
                if ($ownsCurrent) {
                    foreach (['HOLD', 'AVAILABLE'] as $action) {
                        UnitAction::create([
                            'user_id' => config('app.default_system_user_id'), 'unit_id' => $hold->unit_id,
                            'action' => $action, 'status_action' => $action === 'HOLD' ? UnitHoldStatus::OVERDUE : '',
                            'description' => 'The unit was automatically made available.',
                            'actionable_type' => $hold->getMorphClass(), 'actionable_id' => $hold->id,
                        ]);
                    }
                }
                $owner = $hold->createdBy;
                if ($owner) UnitHoldRequestNotificationController::notifyRequestOverdue($owner, $hold);
            });
        } catch (\Exception $e) {
            Log::error('Unit Holding Queue Error: '.$e->getMessage());
            throw $e;
        }
    }
}
