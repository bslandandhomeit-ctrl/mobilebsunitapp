<?php

namespace App\Http\Controllers\Api;

use Validator;
use App\User;
use App\Unit;
use App\UnitAction;
use App\UnitDepositRequest;
use App\UnitHoldRequest;
use App\Helpers\UserRole;
use App\Helpers\UnitDepositStatus;
use App\Helpers\UnitHoldStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\UnitDepositRequest as UnitDepositRequestResource;
use App\Http\Resources\UnitDepositRequestCollection;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Notifications\UnitDepositRequestNotificationController;
use App\Http\Controllers\Api\PaymentOptionController;
use Illuminate\Http\Request;
use Carbon\Carbon;

class UnitDepositRequestController extends Controller
{
    protected $validationRules =  [
        'unit_id' => 'bail|required_without:unit_hold_request_id|exists:units,id',
        'unit_hold_request_id' => 'bail|required_without:unit_id|exists:unit_hold_requests,id',       
        'other_discount_allowance' => 'bail|required|numeric|min:0|max:999999999.99',
        'deposit_amount' => 'bail|required|numeric|min:0|max:999999999.99',
        'is_selected_payment_option' => "bail|required|boolean",          
    	'customer_name' => 'bail|required|string|min:1|max:255', 
    	'customer_gender' => 'bail|required|integer|min:1|max:2',
        'customer2_name' => 'nullable|string|min:1|max:255',
        'customer2_gender' => 'required_with:customer2_name|integer|min:1|max:2',
    	'customer_phone_number' => 'bail|required|regex:(^0\d{8,9}$)',
    	'customer_phone_number2' => 'bail|nullable|regex:(^0\d{8,9}$)',
        'remark' => "nullable|string|max:500"
    ];

    protected $payment_option_validation_rules = [
        'payment_option_id' => 'bail|nullable|integer|exists:payment_options,id',
        'loan_duration' => 'bail|required_without:payment_option_id|integer|min:0',
        'interest' => 'bail|required_without:payment_option_id|numeric|min:0',
        'special_discount' => 'bail|required_without:payment_option_id|numeric|min:0|max:100',
        'is_first_payment' => 'bail|required_without:payment_option_id|boolean',
        'first_payment_duration' => 'bail|required_without:payment_option_id|integer|min:0',
        'first_payment_percentage' => 'bail|required_without_all:first_payment_amount,payment_option_id|numeric|min:0|max:100',
        'first_payment_amount' => 'bail|required_without_all:first_payment_percentage,payment_option_id|numeric|min:0',
    ];

    protected $payment_option_attributes = [
        "payment_option_id" => null,
        "loan_duration" => 0,
        "interest" => 0,
        "special_discount" => 0,
        "is_first_payment" => false,
        "first_payment_duration" => 0,
        "first_payment_percentage" => 0,
        "first_payment_amount" => 0,
    ];

    public function __construct() 
    {
        $this->middleware('role:'.UserRole::SALE_TEAM_LEADER.'|'.UserRole::AGENT)->only('create');
    }

    public function all(Request $request)
    {
        $paginate = 25;
        $unit_deposit_requests = UnitDepositRequest::query();
        $auth_user = $request->user();
        // Permit local administrators to inspect restored requests while testing.
        $local_admin = app()->environment('local') && $auth_user->hasRole(UserRole::ADMINISTRATOR);

        if ( !$local_admin && $auth_user->hasRole(UserRole::SALE_TEAM_LEADER) ) {
            $agents = User::where("managed_by", $auth_user->id)->pluck('id')->toArray();
            array_push($agents,$auth_user->id);        
            $unit_deposit_requests = $unit_deposit_requests->agents($agents);
        } elseif ( !$local_admin && $auth_user->hasRole(UserRole::AGENT) ) {
           $unit_deposit_requests = $unit_deposit_requests->where('user_id', $auth_user->id);
        } 

        if ( $request->input('status') ) {
            $unit_deposit_requests = $unit_deposit_requests->where('status',$request->input('status'));
        }

        if ( $request->input('term') ) {
            $term = $request->input('term');
            $unit_deposit_requests = $unit_deposit_requests->where(function ($query) use ($term){
                $query->whereHas('unit', function ($query) use ($term) {
                    $query->where('code', 'LIKE', '%'.$term.'%');
                });
                $query->orWhere('customer_name', "LIKE", '%'.$term.'%');
                $query->orWhere('customer_phone_number', "LIKE", '%'.$term.'%');               
            });
        }

        if ( $request->input('unit_type_id') ) {
            $unit_type_id = $request->input('unit_type_id');
            $unit_deposit_requests = $unit_deposit_requests->whereHas('unit', function ($query) use ($unit_type_id) {
                return $query->where('unit_type_id', $unit_type_id);
            });
        }elseif ( $request->input('project_id') ) {
            $unit_type_array = \App\UnitType::where('project_id', $request->input('project_id'))->get()->pluck('id');
            $unit_deposit_requests = $unit_deposit_requests->whereHas('unit', function ($query) use ($unit_type_array) {
                return $query->whereIn('unit_type_id', $unit_type_array);
            });
        }
        
        if ( $request->input("embed") ) {           
            $relationships = explode(',', trim($request->input('embed')) );
            return new UnitDepositRequestCollection( $unit_deposit_requests->with($relationships)->paginate($paginate) ) ; 
        }

        return new UnitDepositRequestCollection( $unit_deposit_requests->paginate($paginate) );
    }

    public function get(Request $request, $id)
    {
        $unit_deposit_request = UnitDepositRequest::findOrFail($id);

        if ( $request->input("embed") ) {           
            $relationships = explode(',', trim($request->input('embed')) );
            $unit_deposit_request->load($relationships);
        }
        return new UnitDepositRequestResource( $unit_deposit_request );
    }

    public function create(Request $request)
    {
        if (!config('unified_catalog.request_writes_enabled')) return $this->processCreate($request);
        $user = $request->user();
        if (!$user->hasRole([UserRole::SALE_TEAM_LEADER, UserRole::AGENT])) {
            return $this->sendErrorJsonResponse(__('Unauthorized.'), 403);
        }
        $hold = $request->input('unit_hold_request_id') ? UnitHoldRequest::find($request->input('unit_hold_request_id')) : null;
        $unitId = $hold ? $hold->unit_id : $request->input('unit_id');
        $db = (new UnitDepositRequest)->getConnection();
        $table = config('unified_catalog.request_unit_table', 'units');
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $table)) throw new \LogicException('Invalid canonical unit table.');
        $db->beginTransaction();
        try {
            $unit = $db->table($table)->where('id', $unitId)->lockForUpdate()->first();
            $action = $unit ? UnitAction::find($unit->current_action_id) : null;
            if ($hold) $hold = UnitHoldRequest::where('id', $hold->id)->lockForUpdate()->first();
            $held = $action && $action->action === 'HOLD' ? UnitHoldRequest::where('id', $action->actionable_id)->lockForUpdate()->first() : null;
            $available = $action && ($action->action === 'AVAILABLE'
                || ($held && $action->actionable_type === $held->getMorphClass()
                    && $action->status_action === UnitHoldStatus::APPROVED
                    && $held->status === UnitHoldStatus::APPROVED && $held->user_id == $user->id
                    && $held->release_date && Carbon::parse($held->release_date)->isFuture()));
            if (!$unit || $unit->catalog_state !== 'CURRENT' || !$unit->is_saleable || $unit->deleted_at !== null || !$available
                || ($request->input('unit_hold_request_id') && (!$hold || !$held || $hold->id != $held->id))) {
                $db->rollBack();
                return $this->sendErrorJsonResponse(__('This unit is not available for making deposit request.'), 422);
            }
            $response = $this->processCreate($request);
            if ($response instanceof \Symfony\Component\HttpFoundation\Response && $response->getStatusCode() >= 400) $db->rollBack();
            else $db->commit();
            return $response;
        } catch (\Throwable $e) {
            $db->rollBack();
            Log::error('Canonical deposit creation failed: '.get_class($e));
            return $this->sendErrorJsonResponse(__('Internal System Error!'), 500);
        }
    }

    private function processCreate(Request $request)
    {
        $unit_hold_request = null;
        $auth_user = $request->user();

        // Append is_selected_payment_option key to validation data if not provide from
        // previous App Verison
        if ( !$request->has('is_selected_payment_option') ) {
            $request->merge([ 'is_selected_payment_option' => 1 ]);           
        }        
        // -->

        // Validate User Input
        $validator = Validator::make($request->all(), $this->getValidationRules());
        // Add extra rules when is_selected_payment_option is true
        foreach ( $this->payment_option_validation_rules as $key => $value ) {
            $validator->sometimes($key, $value , function ($input) {
                return $input->is_selected_payment_option == 1;
            });
        }
        // -->

        if ($validator->fails()) {
            return $this->sendErrorJsonResponse($validator->errors()->first(), 422);
        }

        $validated_data = $validator->validate();
        // Correcting Payment Option attribute
        $validated_data = $this->setPaymentOption($validated_data);
    
        // check if the request from unit_hold_request
        // 2019-05-21 Keep to allow backward compatibility
        if ( $request->input('unit_hold_request_id') ) {
            $unit_hold_request = UnitHoldRequest::find($request->input('unit_hold_request_id'));
            // check if the auth user is the owner of unit_hold_request
            if ( $unit_hold_request->user_id != $auth_user->id ) {
                return $this->sendErrorJsonResponse( __("You don't have permission to process your request"), 422);
            }
            // check if Unit Hold Request can be converted to 
            if ( strcasecmp($unit_hold_request->status, UnitHoldStatus::APPROVED) != 0 ) {
                return $this->sendErrorJsonResponse( 
                    __("This Unit Hold Request can not be converted to Unit Deposit Request.")
                , 422);
            }
            $validated_data['unit_id'] =  $unit_hold_request->unit_id;
        }
        // -->

        $unit = Unit::find($validated_data['unit_id']);
        $unit->load(['action']);
        $discount_promotion = $unit->unitType
                                   ->discountPromotion()
                                   ->where("start_date", "<=", Carbon::today())
                                   ->where('end_date', ">=", Carbon::today())
                                   ->first();

        // Appending addtional data to Unit Deposit Request.
        $validated_data['user_id'] = $auth_user->id;
        
        $validated_data['status'] = "PENDING";
        $validated_data['deposited_at'] = Carbon::now()->startOfDay();
        $validated_data = $this->setUnitData($validated_data, $unit, $validated_data['deposited_at']);

        if ( !$unit->allowDeposit() ) {
            return $this->sendErrorJsonResponse( __("This unit is not available for making deposit request."), 422);
        }

        // check in case the target Unit is in Hold Status       
        if ( $unit->isHold(UnitHoldStatus::APPROVED) ) {   
            $unit_hold_request = UnitHoldRequest::find($unit->action->actionable_id);
        }
        // -->

        try {
            $unit_deposit_request = $this->createUnitDepositRequest($validated_data);
            if ( $unit_hold_request instanceof \App\UnitHoldRequest ) {
                $unit_hold_request->status = UnitHoldStatus::RELEASE;
                $unit_hold_request->save();
            }
            return new UnitDepositRequestResource($unit_deposit_request->fresh());
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendErrorJsonResponse( __("Internal System Error!"), 500);
        }
    }

    public function update(Request $request, $id)
    {
        if (!config('unified_catalog.request_writes_enabled')) return $this->processUpdate($request, $id);
        return $this->processUnifiedEdit($request, $id, false);
    }

    private function processUnifiedEdit(Request $request, $id, $approvedChange)
    {
        $snapshot = UnitDepositRequest::findOrFail($id);
        if ($snapshot->user_id != $request->user()->id) return $this->sendErrorJsonResponse(__('Unauthorized.'), 403);
        $targetId = $request->input('unit_id');
        if (!is_scalar($targetId) || !ctype_digit((string) $targetId) || (int) $targetId < 1) {
            return $this->sendErrorJsonResponse(__('A valid unit is required.'), 422);
        }
        $db = $snapshot->getConnection();
        $table = config('unified_catalog.request_unit_table', 'units');
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $table)) throw new \LogicException('Invalid canonical unit table.');
        $db->beginTransaction();
        try {
            // Lock both units by ID so opposite transfers use one consistent order.
            $units = $db->table($table)->whereIn('id', [$snapshot->unit_id, $targetId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $deposit = UnitDepositRequest::where('id', $id)->lockForUpdate()->firstOrFail();
            if ((new \App\Services\UnifiedDepositFinanceGuard($db))->isFinanced($deposit->id)) {
                $db->rollBack();
                return $this->sendErrorJsonResponse(__('This deposit has a finance account and requires finance review.'), 422);
            }
            $oldUnit = $units->get($snapshot->unit_id);
            $target = $units->get((int) $targetId);
            $action = $oldUnit ? UnitAction::find($oldUnit->current_action_id) : null;
            $owned = $action && $action->action === 'DEPOSIT' && $action->actionable_type === $deposit->getMorphClass()
                && (int) $action->actionable_id === (int) $deposit->id;
            $editable = $approvedChange ? $deposit->isChangeable()
                : ($deposit->status === UnitDepositStatus::PENDING && $deposit->isEditable());
            if (!$owned || $deposit->unit_id != $snapshot->unit_id || !$editable) {
                $db->rollBack();
                return $this->sendErrorJsonResponse(__('This request can no longer be edited.'), 422);
            }
            if (!$target || $target->catalog_state !== 'CURRENT' || !$target->is_saleable || $target->deleted_at !== null) {
                $db->rollBack();
                return $this->sendErrorJsonResponse(__('This unit is not available.'), 422);
            }
            if ($target->id != $deposit->unit_id) {
                $targetAction = UnitAction::find($target->current_action_id);
                $held = $targetAction && $targetAction->action === 'HOLD'
                    ? UnitHoldRequest::where('id', $targetAction->actionable_id)->lockForUpdate()->first() : null;
                $available = $targetAction && ($targetAction->action === 'AVAILABLE'
                    || ($held && $targetAction->actionable_type === $held->getMorphClass()
                        && $targetAction->status_action === UnitHoldStatus::APPROVED && $held->status === UnitHoldStatus::APPROVED
                        && $held->user_id == $request->user()->id && $held->release_date && Carbon::parse($held->release_date)->isFuture()));
                if (!$available) {
                    $db->rollBack();
                    return $this->sendErrorJsonResponse(__('This unit is not available.'), 422);
                }
            }
            $response = $approvedChange ? $this->processChangeUnit($request, $id) : $this->processUpdate($request, $id);
            if ($response instanceof \Symfony\Component\HttpFoundation\Response && $response->getStatusCode() >= 400) $db->rollBack();
            else $db->commit();
            return $response;
        } catch (\Throwable $e) {
            $db->rollBack();
            Log::error('Canonical deposit edit failed: '.get_class($e));
            return $this->sendErrorJsonResponse(__('Internal System Error!'), 500);
        }
    }

    private function processUpdate(Request $request, $id)
    {
        $unit_deposit_request = UnitDepositRequest::findOrFail($id);
        $auth_user = $request->user();

        // Append is_selected_payment_option key to validation data if not provide from
        // previous App Verison
        if ( !$request->has('is_selected_payment_option') ) {
            $request->merge([ 'is_selected_payment_option' => 1 ]);           
        }        
        // -->

        $validator = Validator::make($request->all(), $this->getValidationRules());
        foreach ( $this->payment_option_validation_rules as $key => $value ) {
            $validator->sometimes($key, $value , function ($input) {
                return $input->is_selected_payment_option == 1;
            });
        }

        if ($validator->fails()) {
            return $this->sendErrorJsonResponse($validator->errors()->first(), 422);
        }

        $validated_data = $validator->validate();
        // Correcting Payment Option attribute
        $validated_data = $this->setPaymentOption($validated_data);
        // Get Unit;
        $unit = Unit::find($validated_data['unit_id']);

        // check if user remove customer2 
        if ( empty($request->customer2_name) ) {
            $validated_data['customer2_name'] = "";
            $validated_data['customer2_gender'] = 0;
        }

        // Allow update only the owner of request 
        if ( $auth_user->id != $unit_deposit_request->user_id ) {
            return $this->sendErrorJsonResponse( __("You don't have permission to process your request"), 403);
        }

        // Allow update only pending request
        if ( !$unit_deposit_request->isEditable() ) {
            return $this->sendErrorJsonResponse( __("You can not edit on the request which is either approved or rejected."), 422);
        }

        // if user changed the unit, check if the target unit is AVAILABLE
        if ( $unit->id != $unit_deposit_request->unit_id 
            AND $unit->allowDeposit() == false ) {

            return $this->sendErrorJsonResponse( __("This unit is not available for making deposit request."), 422);
        }

        // check in case the target Unit is in Hold Status
        $unit_hold_request = null;
        if ( $unit->isHold(UnitHoldStatus::APPROVED) ) {   
            $unit_hold_request = UnitHoldRequest::find($unit->action->actionable_id);
        }
        // -->

        // check and set unit's price and discount promotion if unit has been changed
        $unit_changed = false;
        if ( $request->unit_id != $unit_deposit_request->unit_id ) {            
            $validated_data = $this->setUnitData($validated_data, $unit, $unit_deposit_request->deposited_at);
            $unit_changed = true;
        }
        // -->

        try {
            DB::beginTransaction();           
            
            // Set Sale Manager Status to PENDING if pyament_option_id is null or other_discount_allowance is greater than 0
            if ( is_null($validated_data['payment_option_id']) OR $validated_data['other_discount_allowance'] > 0 ) {
                $unit_deposit_request = $this->setSaleManagerStatus($unit_deposit_request, UnitDepositStatus::PENDING, null, null);                   
            }
            // Set Sale Manager Status to NOT_REQUIRED if is_selected_payment_option is false
            if ( $validated_data['is_selected_payment_option'] == 0 
                 OR $validated_data['is_selected_payment_option'] == false) {

                $unit_deposit_request = $this->setSaleManagerStatus($unit_deposit_request, "NOT_REQUIRED", null, null);
            }

            // before saving data, check if request's unit_id is not the same as the exisiting one
            // if not the same, make the old unit_id to AVAILABLE
            if ( $unit_changed ) {               
                $this->cancelDepositRequestUnitAction($unit_deposit_request, $auth_user->id);
            }

            $unit_deposit_request->fill($validated_data);
            $unit_deposit_request->save();

            // after saving data, check if request's unit_id is not the same as the exisiting one
            // if not the same, make the new unit_id to PENDING
            if ( $unit_changed ) {               
                $this->pendingDepositRequestUnitAction($unit_deposit_request, $auth_user->id);
            }
            
            if ( $unit_hold_request instanceof \App\UnitHoldRequest ) {
                $unit_hold_request->status = UnitHoldStatus::RELEASE;
                $unit_hold_request->save();
            }

            DB::commit();

            return new UnitDepositRequestResource($unit_deposit_request->fresh());
        } catch (\Exception $e) { 
            DB::rollBack();          
            return $this->sendErrorJsonResponse($e->getMessage(), 500);
            // Log::error($e->getMessage());
            // return $this->sendErrorJsonResponse("Internal System Error!", 500);
        }
    }

    public function approve(Request $request, $id)
    {
        if (config('unified_catalog.request_writes_enabled')) {
            return $this->processUnifiedDecision($request, $id, 'approve');
        }
        return $this->processapprove($request, $id);
    }

    private function processapprove(Request $request, $id)
    {
        $unit_deposit_request = UnitDepositRequest::findOrFail($id);
        $auth_user = $request->user();
        $set_status_success = false;

        if ( !($auth_user->hasRole([UserRole::UNIT_CONTROLLER, UserRole::SALE_MANAGER]))) {
            return $this->sendErrorJsonResponse( __("You don't have permission to process your request") , 403);
        }

        try {            
            if ( $auth_user->hasRole(UserRole::UNIT_CONTROLLER)
                && (!config('unified_catalog.request_writes_enabled') || $unit_deposit_request->unit_controller_status === UnitDepositStatus::PENDING) ) {                
                if ( $unit_deposit_request->unit_controller_status == "PENDING" ) {
                    $set_status_success = $this->setUnitControllerAction($unit_deposit_request, UnitDepositStatus::APPROVED, $auth_user->id);
                } else {
                    return $this->sendErrorJsonResponse( __('This record is already either approved or rejected.') , 422 );
                }
            }

            if ( $auth_user->hasRole(UserRole::SALE_MANAGER)
                && (!config('unified_catalog.request_writes_enabled') || $unit_deposit_request->sale_manager_status === UnitDepositStatus::PENDING) ) {
                if ( $unit_deposit_request->sale_manager_status == 'PENDING' ) {
                    $set_status_success = $this->setSaleManagerAction($unit_deposit_request, UnitDepositStatus::APPROVED , $auth_user->id);
                } else {
                    return $this->sendErrorJsonResponse( __('This record is already either approved or rejected.') , 422 );
                }
            }

            // Set Check Unit Deposit Request's status
            if ( (strcasecmp($unit_deposit_request->unit_controller_status, UnitDepositStatus::APPROVED) == 0 ) 
                    AND ( strcasecmp($unit_deposit_request->sale_manager_status, UnitDepositStatus::APPROVED) == 0 
                        OR strcasecmp($unit_deposit_request->sale_manager_status, "NOT_REQUIRED") == 0
                    ) 
               ) {

                $unit_deposit_request->status = UnitDepositStatus::APPROVED;
                $unit_deposit_request->save();

                $accountants = User::role([UserRole::ACCOUNTANT])->get();
                UnitDepositRequestNotificationController::sendUnitDepositApprovedRequest(
                    $accountants,
                    $unit_deposit_request,
                    null
                );

            }

            return new UnitDepositRequestResource($unit_deposit_request);
        } catch (\Exception $e) {
            return $this->sendErrorJsonResponse($e->getMessage(),500);
        } finally {
            if ( $set_status_success ) {
                // Send Notification
                $owner = User::where('id',$unit_deposit_request->user_id)->first();
                UnitDepositRequestNotificationController::sendUnitDepositApprovedRequest(
                    $owner,
                    $unit_deposit_request,
                    $auth_user->roles->first()->name
                );
            }
        }
    }

    public function reject(Request $request, $id)
    {
        if (config('unified_catalog.request_writes_enabled')) {
            return $this->processUnifiedDecision($request, $id, 'reject');
        }
        return $this->processreject($request, $id);
    }

    private function processreject(Request $request, $id)
    {
        if (!$request->user()->hasRole([UserRole::UNIT_CONTROLLER, UserRole::SALE_MANAGER])) {
            return $this->sendErrorJsonResponse(__("You don't have permission to process your request"), 403);
        }

        $validator = Validator::make($request->all(), [
            'reject_reason' => "nullable|string|max:500"
        ]);
        
        if ($validator->fails()) {
            return $this->sendErrorJsonResponse($validator->errors()->first(), 422);
        }
      
        $unit_deposit_request = UnitDepositRequest::findOrFail($id);
        $auth_user = $request->user();
        $auth_user_role = "";
        $set_status_success = false;

        try {
            if ( $auth_user->hasRole(UserRole::UNIT_CONTROLLER)
                && (!config('unified_catalog.request_writes_enabled') || $unit_deposit_request->unit_controller_status === UnitDepositStatus::PENDING) ) {
                if ( $unit_deposit_request->unit_controller_status == UnitDepositStatus::PENDING ) {
                    $auth_user_role = UserRole::UNIT_CONTROLLER;
                    $set_status_success = $this->setUnitControllerAction($unit_deposit_request, UnitDepositStatus::REJECTED, $auth_user->id);
                } else {
                    return $this->sendErrorJsonResponse( __('This record is already either approved or rejected.') , 422 );
                }
            }

            if ( $auth_user->hasRole(UserRole::SALE_MANAGER)
                && (!config('unified_catalog.request_writes_enabled') || $unit_deposit_request->sale_manager_status === UnitDepositStatus::PENDING) ) {
                if ( $unit_deposit_request->sale_manager_status == UnitDepositStatus::PENDING ) {
                    $auth_user_role = UserRole::SALE_MANAGER;
                    $set_status_success =  $this->setSaleManagerAction($unit_deposit_request, UnitDepositStatus::REJECTED, $auth_user->id);
                }else{
                    return $this->sendErrorJsonResponse( __('This record is already either approved or rejected.') , 422 );
                }
            }

            $this->setAvailableUnitAction($unit_deposit_request);
            $unit_deposit_request->status = UnitDepositStatus::REJECTED;
            if ( strcasecmp($auth_user_role, UserRole::UNIT_CONTROLLER) == 0 ) {
                $unit_deposit_request->unit_controller_reason = $request->reject_reason ? $request->reject_reason : ""; 
            } elseif (strcasecmp($auth_user_role, UserRole::SALE_MANAGER) == 0) {
                $unit_deposit_request->sale_manager_reason = $request->reject_reason ? $request->reject_reason : ""; 
            } 
            $unit_deposit_request->save();

            return new UnitDepositRequestResource($unit_deposit_request);
        } catch (\Exception $e) {
            return $this->sendErrorJsonResponse($e->getMessage(),500);
        } finally {
            // Send Notification
            if ( $set_status_success ) {
                $owner = User::where('id',$unit_deposit_request->user_id)->first();
                UnitDepositRequestNotificationController::sendUnitDepositRejectedRequest($owner, $unit_deposit_request, $auth_user_role);
            }
        }
    }

    public function cancel(Request $request, $id)
    {
        if (config('unified_catalog.request_writes_enabled')) {
            return $this->processUnifiedDecision($request, $id, 'cancel');
        }
        return $this->processcancel($request, $id);
    }

    private function processcancel(Request $request, $id)
    {
        $unit_deposit_request = UnitDepositRequest::findOrFail($id);
        $auth_user = $request->user();

        if ( !($auth_user->hasRole([UserRole::UNIT_CONTROLLER]))) {
            return $this->sendErrorJsonResponse( __("You don't have permission to process your request") , 403);
        }

        $validator = Validator::make($request->all(), [           
            'action_reason' => "required|string|max:500"
        ]);
      
        if ($validator->fails()) {
            return $this->sendErrorJsonResponse($validator->errors()->first(), 422);
        }

        if ( !$unit_deposit_request->isCancellable() ) {
            return $this->sendErrorJsonResponse( __("The request is not in the status which allow to cancel."), 422);
        }

        try {
            DB::beginTransaction();
            // $unit_deposit_request = $this->cancelRequest($unit_deposit_request, $auth_user);
            $unit_deposit_request->status = UnitDepositStatus::CANCELLED;
            $unit_deposit_request->action_reason = $request->action_reason ? $request->action_reason : "";    
            $unit_deposit_request->actioned_user_id = $auth_user->id;
            $unit_deposit_request->actioned_at = now();
            $unit_deposit_request->save();
            $this->cancelDepositRequestUnitAction($unit_deposit_request, $auth_user->id);
            DB::commit();
            // Send Notification to Object owner            
            $requestor = User::where('id', $unit_deposit_request->user_id)->first();
            UnitDepositRequestNotificationController::sendUnitDepositCancelledRequest(
                $requestor, 
                $unit_deposit_request,
                $auth_user->roles->first()->name
            );

            // Send notification to Accountant Role
            $accountants = User::role([UserRole::ACCOUNTANT])->get();
            UnitDepositRequestNotificationController::sendUnitDepositCancelledRequest(
                $accountants, 
                $unit_deposit_request,
                null
            );

            return new UnitDepositRequestResource($unit_deposit_request->fresh());
        } catch (\Exception $e) {
            DB::rollback();
            return $this->sendErrorJsonResponse( __('Internal Server Error!'), 500);
        }
    }

    public function changeUnit(Request $request, $id)
    {
        if (config('unified_catalog.request_writes_enabled')) return $this->processUnifiedEdit($request, $id, true);
        return $this->processChangeUnit($request, $id);
    }

    private function processChangeUnit(Request $request, $id)
    {
        $unit_deposit_request = UnitDepositRequest::findOrFail($id);
        $auth_user = $request->user();
        // check Ownership;       
        if ( $unit_deposit_request->user_id != $auth_user->id ) {  
            return $this->sendErrorJsonResponse( __("Unauthorized."), 403);
        }

        // Append is_selected_payment_option key to validation data if not provide from
        // previous App Verison
        $request = $this->prepareRequestData($request);
        // -->
        $validation_rules = [
            'unit_id' => 'bail|required|exists:units,id',        
            'other_discount_allowance' => 'bail|required|numeric|min:0|max:999999999.99', 
            'is_selected_payment_option' => "bail|required|boolean",          
            'remark' => "nullable|string|max:500"
        ];

        $validator = Validator::make($request->all(), $validation_rules);      
        // Add extra rules when is_selected_payment_option is true
        foreach ( $this->payment_option_validation_rules as $key => $value ) {
            $validator->sometimes($key, $value , function ($input) {
                return $input->is_selected_payment_option == 1;
            });
        }
        // -->

        // validate user's input data
        if ($validator->fails()) {
            return $this->sendErrorJsonResponse($validator->errors()->first(), 422);
        }

        $validated_data = $validator->validate();
        // Correcting Payment Option attribute
        $validated_data = $this->setPaymentOption($validated_data);

        // cast keys to boolean
        $validated_data['is_selected_payment_option'] = (bool) $validated_data['is_selected_payment_option'];
        $validated_data['is_first_payment'] = (bool) $validated_data['is_first_payment'];
        // -->

        // check the unit_deposit_status whether it is allowed to change or not
        if ( !$unit_deposit_request->isChangeable() ) {
            return $this->sendErrorJsonResponse( __('The Unit Deposit Request is not in the status which allow you to change.'), 422);
        }

        // Check data if it was changed
        $new_data = $validated_data;
        $old_data = $unit_deposit_request->only(array_merge(array_keys($this->payment_option_validation_rules),array_keys($validation_rules)));
        $data_changed  = count(array_diff_assoc($new_data,$old_data)) > 0 ;
        // -->              
        if ( !$data_changed ) {
            return $this->sendErrorJsonResponse( __("No Change! the data is the same as previous one."), 422);
        }

        // return $validated_data;

        // Check the old and new Unit id the same or not
        if ( $unit_deposit_request->unit_id == $request->unit_id ) {   
            try {
                DB::beginTransaction();
                $unit_deposit_request->fill($validated_data);
                
                // Set Sale Manager Status to PENDING if pyament_option_id is null or other_discount_allowance is greater than 0
                if ( is_null($validated_data['payment_option_id']) 
                     OR $validated_data['other_discount_allowance'] > 0 ) {

                    $unit_deposit_request->status = UnitDepositStatus::PENDING;                  
                    $unit_deposit_request = $this->setSaleManagerStatus($unit_deposit_request, UnitDepositStatus::PENDING, null, null);
                }
               
                // Set Sale Manager Status to NOT_REQUIRED if is_selected_payment_option is false
                if ( $validated_data['is_selected_payment_option'] == 0 
                     OR $validated_data['is_selected_payment_option'] == false) {

                    $unit_deposit_request->status = UnitDepositStatus::APPROVED;
                    $unit_deposit_request = $this->setSaleManagerStatus($unit_deposit_request, "NOT_REQUIRED", null, null);
                }
                // Save data to database
                $unit_deposit_request->save();
                // action should be taken after saving successfully
                if ( $unit_deposit_request->sale_manager_status == UnitDepositStatus::PENDING ) {
                    // Set Unit Deposit Request to Pending
                    $this->pendingDepositRequestUnitAction($unit_deposit_request, $auth_user->id);
                    // Send notification
                    $users_need_to_be_notified = User::role([UserRole::SALE_MANAGER])->get();
                    UnitDepositRequestNotificationController::sendUnitDepositPendingRequest($users_need_to_be_notified, $unit_deposit_request);
                }
                DB::commit();
                return new UnitDepositRequestResource($unit_deposit_request->fresh());
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error($e);
                return $this->sendErrorJsonResponse( __("Internal Server Error!"), 500);
            }
        } else {
            // check the target unit_id's status is allow to make deposit request or not
            $unit = Unit::findOrFail($request->unit_id);
            if ( !$unit->allowDeposit() ) {
                return $this->sendErrorJsonResponse( __("The Unit which you want to change to is not available."), 422);
            }
            // check in case the target Unit is in Hold Status
            $unit_hold_request = null;
            if ( $unit->isHold(UnitHoldStatus::APPROVED) ) {   
                $unit_hold_request = UnitHoldRequest::find($unit->action->actionable_id);
            }
            // -->

            // Create new Unit_Deposit_Request object
            $new_unit_deposit_request = New UnitDepositRequest();
            // Fill User's Input data into the Newly created object
            $new_unit_deposit_request->fill($validated_data);
            // Assign requirement Old data from Old UnitDepositRequest object
            $new_unit_deposit_request->setDuplicateAttributes($unit_deposit_request);
            $new_unit_deposit_request->status = "PENDING";
            $new_unit_deposit_request->unit_sale_price = $unit->price;
            $new_unit_deposit_request->discount_promotion = $unit->unitType
                                                                 ->getDiscountPromotionAmountByDate($unit_deposit_request->deposited_at);
            $new_unit_deposit_request->from_unit_deposit_request_id = $unit_deposit_request->id;
            try {
                DB::beginTransaction();
                //saving data to database
                $new_unit_deposit_request->save();
                $new_unit_deposit_request = $new_unit_deposit_request->fresh();

                // Set data to Old Unit Deposit Request          
                $unit_deposit_request->to_unit_deposit_request_id = $new_unit_deposit_request->id;
                $unit_deposit_request->status = UnitDepositStatus::CHANGED;
                $unit_deposit_request->save();

                // Set Unit in old unit_deposit_request to AVAILABLE
                $this->changeDepositRequestUnitAction($unit_deposit_request, $unit_deposit_request->user_id);
                 
                // Set Unit in New unit_deposit_request to PENDING
                $this->pendingDepositRequestUnitAction($new_unit_deposit_request, $new_unit_deposit_request->user_id);

                // Change Hold Request to Release
                if ( $unit_hold_request instanceof \App\UnitHoldRequest ) {
                    $unit_hold_request->status = UnitHoldStatus::RELEASE;
                    $unit_hold_request->save();
                } 

                $users_need_to_be_notified;
                // Send Notification
                if ( $new_unit_deposit_request->unit_controller_status == "PENDING" ) {
                    $users_need_to_be_notified = User::role([UserRole::UNIT_CONTROLLER])->get();
                }

                if ( $new_unit_deposit_request->sale_manager_status == "PENDING" ) {
                    $users_need_to_be_notified = User::role([UserRole::UNIT_CONTROLLER,UserRole::SALE_MANAGER])->get();
                }
                UnitDepositRequestNotificationController::sendUnitDepositPendingRequest($users_need_to_be_notified, $new_unit_deposit_request);
                DB::commit();
                return new UnitDepositRequestResource($new_unit_deposit_request);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error($e);                
                return $this->sendErrorJsonResponse( __("Internal Server Error!"), 500);
            }
        }
    }

    private function createUnitDepositRequest(Array $data) 
    {
        try {
            DB::beginTransaction();
            $unit_deposit_request = UnitDepositRequest::create($data);
            $unit_deposit_request = $unit_deposit_request->fresh();
            
            $action = UnitAction::create([
                'user_id' => $unit_deposit_request->user_id,
                'unit_id' => $unit_deposit_request->unit_id,
                'action' => 'DEPOSIT',
                'status_action' => UnitDepositStatus::PENDING,
                'actionable_type' => $unit_deposit_request->getMorphClass(),
                'actionable_id' => $unit_deposit_request->id
            ]);

            // Queue to release after the provided day
            // ProcessUnitHold::dispatch($unit_deposit_request)
            //                    ->delay(now()->addDays($validated_data['hold_day']));
            
            $users_need_to_be_notified = [];
            // Send Notification
            if ( $unit_deposit_request->unit_controller_status == "PENDING" ) {
                $users_need_to_be_notified = User::role([UserRole::UNIT_CONTROLLER])->get();
            }

            if ( $unit_deposit_request->sale_manager_status == "PENDING" ) {               
                $users_need_to_be_notified = User::role([UserRole::UNIT_CONTROLLER,UserRole::SALE_MANAGER])->get();
            }
            
            UnitDepositRequestNotificationController::sendUnitDepositPendingRequest($users_need_to_be_notified, $unit_deposit_request);
            DB::commit();
            return $unit_deposit_request;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    /** Serialize decisions with unit reservations on the canonical connection. */
    private function processUnifiedDecision(Request $request, $id, $decision)
    {
        $roles = $decision === 'cancel' ? [UserRole::UNIT_CONTROLLER]
            : [UserRole::UNIT_CONTROLLER, UserRole::SALE_MANAGER];
        if (!$request->user()->hasRole($roles)) {
            return $this->sendErrorJsonResponse(__("You don't have permission to process your request"), 403);
        }
        $snapshot = UnitDepositRequest::findOrFail($id);
        $db = $snapshot->getConnection();
        $table = config('unified_catalog.request_unit_table', 'units');
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $table)) {
            throw new \LogicException('Invalid canonical unit table.');
        }
        $db->beginTransaction();
        try {
            // Unit first: use the same lock order as holds and canonical action writes.
            $unit = $db->table($table)->where('id', $snapshot->unit_id)->lockForUpdate()->first();
            $deposit = UnitDepositRequest::where('id', $id)->lockForUpdate()->firstOrFail();
            if ((new \App\Services\UnifiedDepositFinanceGuard($db))->isFinanced($deposit->id)) {
                $db->rollBack();
                return $this->sendErrorJsonResponse(__('This deposit has a finance account and requires finance review.'), 422);
            }
            $action = $unit ? UnitAction::find($unit->current_action_id) : null;
            $owned = $action && $action->action === 'DEPOSIT'
                && $action->actionable_type === $deposit->getMorphClass()
                && (int) $action->actionable_id === (int) $deposit->id;
            $validStatus = $decision === 'cancel' ? $deposit->isCancellable()
                : $deposit->status === UnitDepositStatus::PENDING;
            $pendingStage = $decision === 'cancel'
                || ($request->user()->hasRole(UserRole::UNIT_CONTROLLER) && $deposit->unit_controller_status === UnitDepositStatus::PENDING)
                || ($request->user()->hasRole(UserRole::SALE_MANAGER) && $deposit->sale_manager_status === UnitDepositStatus::PENDING);
            if (!$owned || !$validStatus || !$pendingStage || $deposit->unit_id != $snapshot->unit_id) {
                $db->rollBack();
                return $this->sendErrorJsonResponse(__('This request no longer owns the current unit reservation.'), 422);
            }
            $method = 'process'.$decision;
            $response = $this->$method($request, $id);
            if ($response instanceof \Symfony\Component\HttpFoundation\Response && $response->getStatusCode() >= 400) {
                $db->rollBack();
            } else {
                $db->commit();
            }
            return $response;
        } catch (\Throwable $e) {
            $db->rollBack();
            Log::error('Canonical deposit decision failed: '.get_class($e));
            return $this->sendErrorJsonResponse(__('Internal System Error!'), 500);
        }
    }

    private function setUnitControllerAction(UnitDepositRequest &$unit_deposit_request, $action_status, $user_id)
    {
        try {
            DB::beginTransaction();
            $unit_deposit_request->unit_controller_status = $action_status;
            $unit_deposit_request->unit_controller_id = $user_id;
            $unit_deposit_request->unit_controller_actioned_at = \Carbon\Carbon::now();

            $action = UnitAction::create([
                'user_id' =>  $user_id,
                'unit_id' => $unit_deposit_request->unit_id,
                'action' => 'DEPOSIT',
                'status_action' => $action_status,
                'actionable_type' => $unit_deposit_request->getMorphClass(),
                'actionable_id' => $unit_deposit_request->id
            ]);

            $unit_deposit_request->save();
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    private function setSaleManagerAction(UnitDepositRequest &$unit_deposit_request, $action_status, $user_id)
    {
        try {
            DB::beginTransaction();
            $unit_deposit_request->sale_manager_status = $action_status;
            $unit_deposit_request->sale_manager_id = $user_id;
            $unit_deposit_request->sale_manager_actioned_at = \Carbon\Carbon::now();
            
            $action = UnitAction::create([
                'user_id' =>  $user_id,
                'unit_id' => $unit_deposit_request->unit_id,
                'action' => 'DEPOSIT',
                'status_action' => $action_status,
                'actionable_type' => $unit_deposit_request->getMorphClass(),
                'actionable_id' => $unit_deposit_request->id
            ]);
            $unit_deposit_request->save();
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    private function pendingDepositRequestUnitAction(UnitDepositRequest $unit_deposit_request, $user_id)
    {
        return UnitAction::create([
            'user_id'       => $user_id,
            'unit_id'       => $unit_deposit_request->unit_id,
            'action'        => 'DEPOSIT',
            'status_action' => UnitDepositStatus::PENDING,
            'actionable_type' => $unit_deposit_request->getMorphClass(),
            'actionable_id' => $unit_deposit_request->id
        ]);
    }

    private function setAvailableUnitAction(UnitDepositRequest $unit_deposit_request)
    {
        $available_action  = UnitAction::create([
            'user_id'       => config('app.default_system_user_id'),
            'unit_id'       => $unit_deposit_request->unit_id,
            'action'        => 'AVAILABLE',
            'status_action' => "",
            'actionable_type' => $unit_deposit_request->getMorphClass(),
            'actionable_id' => $unit_deposit_request->id
        ]);
    }

    private function cancelDepositRequestUnitAction(UnitDepositRequest $unit_deposit_request, $user_id)
    {
        try {
            $cancel_action  = UnitAction::create([
            'user_id'       => $user_id,
            'unit_id'       => $unit_deposit_request->unit_id,
            'action'        => 'DEPOSIT',
            'status_action' => UnitDepositStatus::CANCELLED,
            'actionable_type' => $unit_deposit_request->getMorphClass(),
            'actionable_id' => $unit_deposit_request->id
            ]);

            $available_action  = UnitAction::create([
                'user_id'       => config('app.default_system_user_id'),
                'unit_id'       => $unit_deposit_request->unit_id,
                'action'        => 'AVAILABLE',
                'status_action' => "",
                'actionable_type' => $unit_deposit_request->getMorphClass(),
                'actionable_id' => $unit_deposit_request->id
            ]);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    private function changeDepositRequestUnitAction(UnitDepositRequest $unit_deposit_request, $user_id)
    {
        $change_action  = UnitAction::create([
            'user_id'       => $user_id,
            'unit_id'       => $unit_deposit_request->unit_id,
            'action'        => 'DEPOSIT',
            'status_action' => UnitDepositStatus::CHANGED,
            'actionable_type' => $unit_deposit_request->getMorphClass(),
            'actionable_id' => $unit_deposit_request->id
        ]);

        $available_action  = UnitAction::create([
            'user_id'       => config('app.default_system_user_id'),
            'unit_id'       => $unit_deposit_request->unit_id,
            'action'        => 'AVAILABLE',
            'status_action' => "",
            'actionable_type' => $unit_deposit_request->getMorphClass(),
            'actionable_id' => $unit_deposit_request->id
        ]);
    }
   
    private function setPaymentOption($data)
    {
        $is_selected_payment_option = $data['is_selected_payment_option'];
        
        if ( $is_selected_payment_option ) {
            return PaymentOptionController::validatedPaymentOptionFromRequest($data);
        } else {
            $data['payment_option_id'] = null;
            $data['loan_duration'] = 0;
            $data['interest'] = 0;
            $data['special_discount'] = 0;
            $data['is_first_payment'] = 0;
            $data['first_payment_duration'] = 0;
            $data['first_payment_percentage'] = 0;
            $data['first_payment_amount'] = 0;
            return $data;
        }
    }

    private function setUnitData($data, Unit $unit, $deposit_date) {
        $data['unit_sale_price'] = $unit->price;
        $data['discount_promotion'] = $unit->unitType
                                           ->getDiscountPromotionAmountByDate($deposit_date);

        return $data;
    }

    private function prepareRequestData(Request $request)
    {
        if ( !$request->has('is_selected_payment_option') ) {
            $request->merge([ 'is_selected_payment_option' => 1 ]);           
        }
        if ( !$request->has('remark') ) {
            $request->merge([ 'remark' => '' ]);           
        }
        return $request;
    }

    private function setSaleManagerStatus(UnitDepositRequest $unit_deposit_request, $status, $user_id, $timestamp) 
    {   
        $unit_deposit_request->sale_manager_status = $status;
        $unit_deposit_request->sale_manager_id = $user_id;
        $unit_deposit_request->sale_manager_actioned_at = $timestamp;
        return $unit_deposit_request;
    }

    private function setUnitControllerStatus(UnitDepositRequest $unit_deposit_request, $status, $user_id, $timestamp) 
    {   
        $unit_deposit_request->unit_controller_status = $status;
        $unit_deposit_request->unit_controller_id = $user_id;
        $unit_deposit_request->unit_controller_actioned_at = $timestamp;
        return $unit_deposit_request;
    }

    public function getValidationRules() {
        return $this->validationRules;    
    }
}
