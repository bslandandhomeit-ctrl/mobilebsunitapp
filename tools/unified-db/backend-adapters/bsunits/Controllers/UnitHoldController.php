<?php

namespace App\Http\Controllers\Api;

use Validator;
use App\User;
use App\Unit;
use App\UnitAction;
use App\UnitHoldRequest;
use App\Jobs\ProcessUnitHold;
use App\Helpers\UserRole;
use App\Helpers\UnitHoldStatus;
use App\Helpers\UnitStatus;
use App\Http\Resources\UnitHoldRequest as UnitHoldRequestResource;
use App\Http\Resources\UnitHoldRequestCollection;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Notifications\UnitHoldRequestNotificationController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class UnitHoldController extends Controller
{
    protected $validationRules =  [    
        "unit_id" => 'required|exists:units,id',
        'hold_day' => 'required|integer|min:1|max:7',
        "remark" => 'nullable|string|max:500'
    ];

    public function __construct() 
    {
        $this->middleware('role:'.UserRole::SALE_TEAM_LEADER.'|'.UserRole::AGENT)->only('hold');
    }

    public function get(Request $request)
    {
        $paginate = 25;
        $unit_hold_requests = UnitHoldRequest::query();
        $auth_user = $request->user();
        // Permit local administrators to inspect restored requests while testing.
        $local_admin = app()->environment('local') && $auth_user->hasRole(UserRole::ADMINISTRATOR);

        if ( !$local_admin && $auth_user->hasRole(UserRole::SALE_TEAM_LEADER) ) {
            $agents = User::where("managed_by", $auth_user->id)->pluck('id')->toArray();
            array_push($agents,$auth_user->id);        
            $unit_hold_requests = $unit_hold_requests->agents($agents);            
        } elseif ( !$local_admin && $auth_user->hasRole(UserRole::AGENT) ) {
           $unit_hold_requests = $unit_hold_requests->ownerOnly();
        } 

        if ( $request->input('status') ) {
            $unit_hold_requests = $unit_hold_requests->where('status', $request->input('status'));
        }

        if ( $request->input('term') ) {           
            $term = $request->input('term');
            $unit_hold_requests = $unit_hold_requests->whereHas('unit', function ($query) use ($term) {
                $query->where('code', 'LIKE', '%'.$term.'%');
            });
        }
        
        if ( $request->input('unit_type_id') ) {
            $unit_type_id = $request->input('unit_type_id');
            $unit_hold_requests = $unit_hold_requests->whereHas('unit', function ($query) use ($unit_type_id) {
                return $query->where('unit_type_id', $unit_type_id);
            });
        }elseif ( $request->input('project_id') ) {
            $unit_type_array = \App\UnitType::where('project_id', $request->input('project_id'))->get()->pluck('id');
            $unit_hold_requests = $unit_hold_requests->whereHas('unit', function ($query) use ($unit_type_array) {
                return $query->whereIn('unit_type_id', $unit_type_array);
            });
        }

        if ( $request->input("embed") ) {           
            $relationships = explode(',', trim($request->input('embed')) );
            return new UnitHoldRequestCollection( $unit_hold_requests->with($relationships)->paginate($paginate) ) ; 
        }
        return new UnitHoldRequestCollection( $unit_hold_requests->paginate($paginate) );
    }

    public function show(Request $request, $id)
    {
        $unit_hold_request = UnitHoldRequest::findOrFail($id);
        
        if ( $request->input("embed") ) {           
            $relationships = explode(',', trim($request->input('embed')) );
            $unit_hold_request->load($relationships);
        }

        return new UnitHoldRequestResource( $unit_hold_request );
    }

   	public function hold(Request $request)
   	{ 
        // 2020-07-21 Update : disable hold
        if (!app()->environment('local')) {
            return $this->sendErrorJsonResponse( __('This functionality is currently disable.'), 400);
        }
        //          
        $validator = Validator::make($request->all(), $this->getValidationRules());

        if ($validator->fails()) {
            return $this->sendErrorJsonResponse($validator->errors()->first(), 422);
        }

        $validated_data = $validator->valid();	
        $auth_user = $request->user();
        try {
            DB::beginTransaction();
            $unitTable = config('unified_catalog.request_writes_enabled')
                ? config('unified_catalog.request_unit_table', 'units') : 'units';
            DB::table($unitTable)->where('id', $validated_data['unit_id'])->lockForUpdate()->first();
        $unit = Unit::find($validated_data['unit_id']);
        $unit->load(['action']);
        
        if ( !$unit->allowHold() ) {
            DB::rollBack();
            return $this->sendErrorJsonResponse( __("This unit is not available for making hold request."), 422);
        }

        if ( UnitHoldRequest::isUnitRequestedPending($validated_data['unit_id'], $auth_user->id) ) {
            DB::rollBack();
            return $this->sendErrorJsonResponse( __("You can not make another request due to there is a pending request."), 422);
        }

        $hold_request_data = [
            'user_id' => $auth_user->id,
            'unit_id' => $validated_data['unit_id'],
            'remark' => $validated_data['remark'] ?? "",
            'status' => UnitHoldStatus::PENDING, 
            'hold_day' => $validated_data['hold_day'],
            "release_date" => now()->addDays($validated_data['hold_day'])->toDateTimeString()
        ];

        if ( $validated_data['hold_day'] ==  1 AND strcasecmp($unit->action->action, UnitStatus::AVAILABLE) == 0) {
           	$hold_request_data['status'] = UnitHoldStatus::APPROVED;
        }

            $unit_hold_request = UnitHoldRequest::create($hold_request_data);

            $action = UnitAction::create([
                'user_id'       => $auth_user->id ,
                'unit_id'       => $unit->id,
                'action'        => UnitStatus::HOLD,
                'status_action' => UnitHoldStatus::PENDING,
                'actionable_type' => $unit_hold_request->getMorphClass(),
                'actionable_id' => $unit_hold_request->id
            ]);

            if ( strcasecmp($unit_hold_request['status'], UnitHoldStatus::APPROVED) == 0 ) {
                $action = UnitAction::create([
                    'user_id'       => config('app.default_system_user_id'),
                    'unit_id'       => $unit->id,
                    'action'        => UnitStatus::HOLD,
                    'status_action' => UnitHoldStatus::APPROVED,
                    'actionable_type' => $unit_hold_request->getMorphClass(),
                    'actionable_id' => $unit_hold_request->id
                ]);
            }

            // Queue to release after the provided day            
            ProcessUnitHold::dispatch($unit_hold_request)
                               ->delay(now()->addDays($validated_data['hold_day']));

            // Send Notification to Unit Controller
            if ( strcasecmp($hold_request_data['status'], UnitHoldStatus::PENDING) == 0) {
                $users_need_to_be_notified = User::role([UserRole::UNIT_CONTROLLER])->get();
                UnitHoldRequestNotificationController::notifyRequestPending($users_need_to_be_notified, $unit_hold_request);
            }

            DB::commit();
            return new UnitHoldRequestResource($unit_hold_request->fresh());
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendErrorJsonResponse($e->getMessage(), 500);
        }
   	}

    public function approve(Request $request, $id)
    {
        $unit_hold_request = UnitHoldRequest::findOrFail($id);
        $unit = Unit::find($unit_hold_request->unit_id);
        $auth_user = $request->user();        

        if ( !$auth_user->hasRole(UserRole::UNIT_CONTROLLER) ) {
            return $this->sendErrorJsonResponse( __("You don't have permission to process your request"), 403);
        }
        
        if (  strcasecmp($unit_hold_request->status, UnitHoldStatus::PENDING) != 0 ) {
            return $this->sendErrorJsonResponse( __('The request no need to be approved.'), 422);
        }

        try {
            DB::beginTransaction();
            $unitTable = config('unified_catalog.request_writes_enabled')
                ? config('unified_catalog.request_unit_table', 'units') : 'units';
            $lockedUnit = DB::table($unitTable)->where('id', $unit_hold_request->unit_id)->lockForUpdate()->first();
            $pointer = $lockedUnit ? (config('unified_catalog.request_writes_enabled')
                ? $lockedUnit->current_action_id : $lockedUnit->unit_action_id) : null;
            $currentAction = $pointer ? UnitAction::find($pointer) : null;
            if (!$currentAction || $currentAction->action !== 'HOLD' || $currentAction->status_action !== UnitHoldStatus::PENDING) {
                DB::rollBack();
                return $this->sendErrorJsonResponse(__('This unit is not available for hold approval.'), 422);
            }
            $unit_hold_request = UnitHoldRequest::where('id', $id)->lockForUpdate()->firstOrFail();
            if ($unit_hold_request->status !== UnitHoldStatus::PENDING) {
                DB::rollBack();
                return $this->sendErrorJsonResponse(__('The request no need to be approved.'), 422);
            }
            $other_pending = UnitHoldRequest::status(UnitHoldStatus::PENDING, $unit_hold_request->unit_id)
                ->excluded([$unit_hold_request->id])->lockForUpdate()->get();
            if ( count($other_pending) > 0 ) {              
                foreach( $other_pending as $pending_hold_request ) {
                    $this->rejectRequest($pending_hold_request, $auth_user);
                    // Send Rejected Notification to Owner of the request
                    $owner = User::where('id', $pending_hold_request->user_id)->first();
                    UnitHoldRequestNotificationController::notifyRequestRejected($owner, $pending_hold_request);
                }
            }

            // Set Unit Hold Request to Approved
            $unit_hold_request->approve(true, true);
            $unit_hold_request = $unit_hold_request->fresh();
            // Send Approved Notification to Owner of the request
            $owner = User::where('id',$unit_hold_request->user_id)->first();
            UnitHoldRequestNotificationController::notifyRequestApproved($owner, $unit_hold_request);
            // Commit changes into database
            DB::commit();
            return new UnitHoldRequestResource($unit_hold_request);       
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return $this->sendErrorJsonResponse( __("Internal Server Error!"), 500);
        }
    }

    public function reject(Request $request, $id)
    {
        $unit_hold_request = UnitHoldRequest::findOrFail($id);
        $auth_user = $request->user();
        
        if ( !$auth_user->hasRole(UserRole::UNIT_CONTROLLER) ) {
            return $this->sendErrorJsonResponse( __("Unauthorized"), 403);
        }

        if ( strcasecmp($unit_hold_request->status, UnitHoldStatus::PENDING) != 0 ) {            
            return $this->sendErrorJsonResponse( __("The request is not in the status which allow to reject."), 422);
        }
        
        try {
            // Set Unit Hold Request to Rejected
            $unit_hold_request = $this->rejectRequest($unit_hold_request, $auth_user);
            // Send Rejected Notification to Owner of the request
            $owner = User::where('id', $unit_hold_request->user_id)->first();
            UnitHoldRequestNotificationController::notifyRequestRejected($owner, $unit_hold_request);

            return new UnitHoldRequestResource($unit_hold_request->fresh()); 
        } catch (\Exception $e) {
            Log::error($e->message());
            return $this->sendErrorJsonResponse( __('Internal Server Error!'), 500);
        }
    }

    public function cancel(Request $request, $id)
    {
        $unit_hold_request = UnitHoldRequest::findOrFail($id);
        $auth_user = $request->user();
        // $is_unit_controller = false;     

        $validator = Validator::make($request->all(), [
            'action_reason' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendErrorJsonResponse($validator->errors()->first(), 422);
        }

        $is_not_agent = $unit_hold_request->user_id != $auth_user->id  ;
        if ($is_not_agent && !$auth_user->hasRole(UserRole::UNIT_CONTROLLER)) {
            return $this->sendErrorJsonResponse(__("You don't have permission to process your request"), 403);
        }


        if ( strcasecmp($unit_hold_request->status, UnitHoldStatus::APPROVED) != 0 ) {
            return $this->sendErrorJsonResponse( __("The request is not in the status which allow to cancel."), 422);
        }

        try {
            $unit_hold_request = $this->cancelRequest($unit_hold_request, $auth_user, $request->action_reason ?? '');
            $users_need_to_be_notified = User::where('id', $unit_hold_request->user_id)->get();
            UnitHoldRequestNotificationController::notifyRequestCancelled($users_need_to_be_notified, $unit_hold_request);
            return new UnitHoldRequestResource($unit_hold_request->fresh()); 
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendErrorJsonResponse($e->getCode() === 409 ? $e->getMessage() : __('Internal Server Error!'), $e->getCode() === 409 ? 409 : 500);
        }
    }

    private function rejectRequest(UnitHoldRequest $unit_hold_request, User $rejected_by)
    {
        try {
            DB::beginTransaction();
            $unit_hold_request->status = UnitHoldStatus::REJECTED;
            $unit_hold_request->actioned_user_id = $rejected_by->id;
            $unit_hold_request->actioned_at = now();
            $unit_hold_request->save();

            UnitAction::create([
                'user_id'       => $rejected_by->id,
                'unit_id'       => $unit_hold_request->unit_id,
                'action'        => 'HOLD',
                'status_action' => UnitHoldStatus::REJECTED,
                'actionable_type' => $unit_hold_request->getMorphClass(),
                'actionable_id' => $unit_hold_request->id
            ]);

            // if there are not any other reqeusts pending, set the unit to AVAILABLE
            $other_pending = UnitHoldRequest::status(UnitHoldStatus::PENDING, $unit_hold_request->unit_id)->excluded([$unit_hold_request->id])->get();
            if ( count($other_pending) == 0 ) {
                $available_action  = UnitAction::create([
                    'user_id'       => config('app.default_system_user_id'),
                    'unit_id'       => $unit_hold_request->unit_id,
                    'action'        => 'AVAILABLE',
                    'status_action' => "",
                    'actionable_type' => $unit_hold_request->getMorphClass(),
                    'actionable_id' => $unit_hold_request->id
                ]);
            }

            DB::commit();
            return $unit_hold_request;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage(), $e->getCode());            
        }    
    }

    private function cancelRequest(UnitHoldRequest $unit_hold_request, User $cancelled_by, $reason = '') 
    {
        try {
            DB::beginTransaction();
            $canonical = config('unified_catalog.request_writes_enabled');
            $table = $canonical ? config('unified_catalog.request_unit_table', 'units') : 'units';
            $unit = DB::table($table)->where('id', $unit_hold_request->unit_id)->lockForUpdate()->first();
            $unit_hold_request = UnitHoldRequest::where('id', $unit_hold_request->id)->lockForUpdate()->firstOrFail();
            if ($unit_hold_request->status !== UnitHoldStatus::APPROVED) {
                throw new \Exception('This hold has already changed status.', 409);
            }
            $pointer = $unit ? ($canonical ? $unit->current_action_id : $unit->unit_action_id) : null;
            $current = $pointer ? UnitAction::find($pointer) : null;
            $ownsCurrent = $current && $current->action === 'HOLD'
                && $current->actionable_type === $unit_hold_request->getMorphClass()
                && (int)$current->actionable_id === (int)$unit_hold_request->id;
            $unit_hold_request->status = UnitHoldStatus::CANCELLED;
            $unit_hold_request->actioned_user_id = $cancelled_by->id;
            $unit_hold_request->actioned_at = now();
            $unit_hold_request->action_reason = $reason;
            $unit_hold_request->save();

            if ($ownsCurrent) {
            $cancel_action  = UnitAction::create([
                'user_id'       => $cancelled_by->id,
                'unit_id'       => $unit_hold_request->unit_id,
                'action'        => 'HOLD',
                'status_action' => UnitHoldStatus::CANCELLED,
                'actionable_type' => $unit_hold_request->getMorphClass(),
                'actionable_id' => $unit_hold_request->id
            ]);

            $available_action  = UnitAction::create([
                'user_id'       => config('app.default_system_user_id'),
                'unit_id'       => $unit_hold_request->unit_id,
                'action'        => 'AVAILABLE',
                'status_action' => "",
                'actionable_type' => $unit_hold_request->getMorphClass(),
                'actionable_id' => $unit_hold_request->id
            ]);
            }
            DB::commit();
            return $unit_hold_request;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage(), $e->getCode());            
        }       
    }

    public function getValidationRules(){
        return $this->validationRules;
    }
}
