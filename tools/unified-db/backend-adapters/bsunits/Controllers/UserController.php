<?php

namespace App\Http\Controllers\Api;

use Validator;
use App\User;
use App\UnitHoldRequest;
use App\UnitDepositRequest;
use App\UnitContractRequest;
use App\Helpers\UnitHoldStatus;
use App\Helpers\UnitDepositStatus;
use App\Helpers\UnitContractStatus;
use App\Helpers\UserRole;
use App\Http\Resources\User as UserResource;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UnitHoldRequestCollection;
use App\Http\Resources\UnitDepositRequestCollection;
use App\Http\Resources\UnitContractRequestCollection;
use App\Http\Resources\NotificationCollection;
use App\Http\Controllers\Controller;
use App\Notifications\UserCreated;
use App\Notifications\MemberRequestCreated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{    
    public function getAuthenticatedUser(Request $request) 
    {     
        if ( $request->input('embed') ){
            $relationships = explode(',', trim($request->input('embed')) );        
            return new UserResource($request->user()->load($relationships));
        }
        return new UserResource($request->user());
    }

    public function getMembers(Request $request, $id = null) 
    {       
        $auth_user = $request->user();

        $role = $auth_user->roles()->first()->name;

        switch ($role) {
            case UserRole::SALE_TEAM_LEADER:
            case UserRole::AGENT:
                $users = $auth_user->members();
                break;
            case UserRole::SALE_MANAGER:
            case UserRole::UNIT_CONTROLLER:
            case UserRole::ADMINISTRATOR:
                $user = User::findOrFail($id);
                $users = $user->members();
                break;
            default:
                return $this->sendErrorJsonResponse( __("You don't have permission to process your request"), 403);
                break;
        }
    
        $users = $users->orderBy("verified")
                       ->orderBy("created_at", 'DESC');

        if ( $request->input('embed') ){
            $relationships = explode(',', trim($request->input('embed')) );        
            return new UserCollection( $users->with($relationships)->paginate() ) ; 
        }

        return new UserCollection( $users->paginate() ) ; 
    }

    // Get User Request Activities
    public function getUnitHoldRequest(Request $request, $id) 
    {
        
        $unit_hold_requests = UnitHoldRequest::where('user_id', $id);
        
        if ( $request->query('term') ) {
            $term = $request->query('term');           
            $unit_hold_requests = $unit_hold_requests->orWhereHas('unit' , function ($query) use ($term) {
                $query->where('code', 'LIKE', '%'.$term.'%');
            });
        }

        if ( $request->query('from') AND $request->query('to') ) {
            $unit_hold_requests = $unit_hold_requests->ofCreatedBetweenDate($request->query('from'), $request->query('to'));
        }
        
        // if ( $request->query('status') ) {
        //     $unit_hold_requests = $unit_hold_requests->where('status', $request->query('status'));
        // } 

        if ( $request->query("embed") ) {           
            $relationships = explode(',', trim($request->input('embed')) );
            $unit_hold_requests = $unit_hold_requests->with($relationships) ; 
        }
        
        return new UnitHoldRequestCollection( $unit_hold_requests->paginate($request->per_page ?? null) );
    }

    public function getUnitDepositRequest(Request $request, $id) 
    {    
        $unit_deposit_requests = UnitDepositRequest::query();
        $unit_deposit_requests = $unit_deposit_requests->where('user_id', $id);

        if ( $request->query('term') ) {
            $term = $request->query('term');           
            $unit_deposit_requests = $unit_deposit_requests->orWhereHas('unit' , function ($query) use ($term) {
                $query->where('code', 'LIKE', '%'.$term.'%');
            });
            $unit_deposit_requests = $unit_deposit_requests->orWhere('customer_name', 'LIKE', '%'.$term.'%');
            $unit_deposit_requests = $unit_deposit_requests->orWhere('customer2_name', 'LIKE', '%'.$term.'%');
            $unit_deposit_requests = $unit_deposit_requests->orWhere('customer_phone_number', 'LIKE', '%'.$term.'%');
            $unit_deposit_requests = $unit_deposit_requests->orWhere('customer_phone_number2', 'LIKE', '%'.$term.'%');         
        } 

        if ( $request->query('from') AND $request->query('to') ) {
            $unit_deposit_requests = $unit_deposit_requests->ofCreatedBetweenDate($request->query('from'), $request->query('to'));
        }

        if ( $request->query("embed") ) {           
            $relationships = explode(',', trim($request->input('embed')) );
            $unit_deposit_requests = $unit_deposit_requests->with($relationships) ; 
        }

        return new UnitDepositRequestCollection( $unit_deposit_requests->paginate($request->per_page ?? null) );
    }

    public function getUnitContractRequest(Request $request, $id) 
    {
        $unit_contract_requests = UnitContractRequest::query();
        $unit_contract_requests = $unit_contract_requests->where('user_id', $id);

        if ( $request->query('term') ) {   
            $term = $request->query('term');
            $unit_contract_requests = $unit_contract_requests->orWhereHas('unit' , function ($query) use ($term) {
                $query->where('code', 'LIKE', '%'.$term.'%');
            });
            $unit_contract_requests = $unit_contract_requests->orWhereHas('unitDepositRequest' , function ($query) use ($term) {
                $query->where('customer_name', 'LIKE', '%'.$term.'%');
            });
            $unit_contract_requests = $unit_contract_requests->orWhereHas('unitDepositRequest' , function ($query) use ($term) {
                $query->where('customer_phone_number', 'LIKE', '%'.$term.'%');
            });
            $unit_contract_requests = $unit_contract_requests->orWhereHas('unitDepositRequest' , function ($query) use ($term) {
                $query->where('customer_phone_number2', 'LIKE', '%'.$term.'%');
            });
        }      

        if ( $request->query('from') AND $request->query('to')) {
            $unit_contract_requests = $unit_contract_requests->ofCreatedBetweenDate($request->query('from'), $request->query('to'));
        } 
        if ( $request->query("embed") ) {           
            $relationships = explode(',', trim($request->input('embed')) );
            $unit_contract_requests = $unit_contract_requests->with($relationships) ; 
        }
        return new UnitContractRequestCollection( $unit_contract_requests->paginate($request->per_page ?? null) );
    }

    public function getunitRequestStatistic(Request $request, $id) {
        $user = User::withCount(['unitHoldRequests', 'unitDepositRequests', 'unitContractRequests'])
                    ->where('id', $id)->firstOrFail();

        return new UserResource($user);
    }
    // End User Request Activities

    public function getNotification(Request $request)
    {
        $per_page = 10;
        return new NotificationCollection(
            Auth::user()
                ->notifications()
                ->simplePaginate($request->query('per_page') ??  $per_page)
        );
    }

    public function get(Request $request, $id)
    {     
        if ( $id == config('app.default_system_user_id') ) {
            abort(404);
        }

        $user = User::where('id', $id)->firstOrFail();

        if ( $request->input("embed") ) {           
            $relationships = explode(',', trim($request->input('embed')) );
            $user->load($relationships);
        }

        return new UserResource($user);
    }

    public function create(Request $request)
    {
        //disable user registration  2020-02-18
        return $this->sendErrorJsonResponse( __('This functionality is currently disable.'), 400);
        //

        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|min:1|max:191',
            'email'         => 'nullable|string|email|max:255|unique:users',                
            'phone_number'  => "required|regex:(^0\d{8,9}$)|unique:users,phone_number",
            'gender'        => 'required|in:Male,Female',
            'birthdate'     => 'required|date',                
            'password'      => 'required|string|min:6|max:32',                
            'managed_by'    => 'required|exists:users,id',
            'avatar'        => 'nullable|image|dimensions:min_width=256,min_height=256,max_width=1028,max_height=1028',
        ]);

        if ($validator->fails()) {
            return $this->sendErrorJsonResponse($validator->errors()->first(), 422);
        } 

        try {
            $user = New User();
            $user->name         = $request->input('name');
            $user->email        = $request->input('email') ?? null;
            $user->phone_number = $request->input('phone_number');
            $user->gender       = $request->input('gender');
            $user->birthdate    = $request->input('birthdate');
            $user->password     = bcrypt($request->input('password'));
            $user->managed_by   = $request->input('managed_by');
            $user->need_change_password = false;
            $user->active       = true;
            $user->verified     = false;

            // Check avatar
            if ( $request->hasFile('avatar') ) {            
                $user->deleteOldProfileImage();        
                $path = $request->file('avatar')->store("user_avatar","public");  
                $user->avatar = $path;
            }

            // Commit save to database
            $user->save();
            // Assign Default Role (Agent)
            $user->assignRole(config('permission.default_role'));

            return $this->sendSuccessResponse( __("Account has been created successfully.") , 200);

        } catch (Exception $e) {
           return $this->sendErrorJsonResponse(__('Internal Server Error!'), 500);
        } finally {
            if ( $user instanceof \App\User ) {
                // Send Notification to SYSTEM Account
                User::find(config('app.default_system_user_id'))
                    ->notify(new UserCreated($user));
                // Send notification to Sale Team Leader
                User::find($user->managed_by)
                    ->notify(new MemberRequestCreated($user));
                // Log to slack in order to notify user created
                Log::channel('slack')->info("User {$user->name} ({$user->phone_number}) has registered.");
            }
        }
    }

    public function update(Request $request)
    {
    	$user = User::where('id', $request->user()->id)->firstOrFail();
       
        $validator = Validator::make($request->all(), [          
            'email' => "email|unique:users,email",                                 
            'gender' => 'in:Male,Female',
            'birthdate' => 'date',
            'avatar' => 'image|dimensions:min_width=256,min_height=256,max_width=1028,max_height=1028',
            'metadata.last_notification_read_at' => 'date'
        ]);

        if ($validator->fails()) {
            return $this->sendErrorJsonResponse($validator->errors()->first(), 422);
        }
        
		$user->fill($request->only(['email','gender','birthdate']));
        $user->metadata = array_merge($user->metadata ?? [], $request->metadata ?? []);

        if ( $request->device_info ) {  
            $token = $request->user()->token();
            $token->setDeviceInfomation($request->device_info);
        }

		if ( $request->hasFile('avatar') ) {			
			$user->deleteOldProfileImage();        
    		$path = $request->file('avatar')->store("user_avatar","public");  
    		$user->avatar = $path;
    	}

        try {
            $user->save();
            if ( isset($token) ) {
                $token->save();             
            }

            return new UserResource( $user->load(['identifications','roles']) );
        } catch (\Exception $e) {
            return $this->sendErrorJsonResponse(__('Internal Server Error!'), 500);
        }
    }

    public function changePassword(Request $request)
    {
        // validate user data
        $validator = Validator::make($request->only(['password_current','password_new']), [
                'password_current'  => 'required',
                'password_new'      => 'required|string|min:6'
            ] 
        );

        if ($validator->fails()) {
            return $this->sendErrorJsonResponse($validator->errors()->first(), 422);
        }

        // return validation error when the current and new is the same
        if ( strcasecmp($request->password_current , $request->password_new) == 0 ) {
            return $this->sendErrorJsonResponse( __("New password can not be the same as old password."), 422);
        }

        $user = User::where('id', $request->user()->id)->first();

        if ( !(Hash::check($request->input('password_current'), $user->password)) ) {
            return $this->sendErrorJsonResponse(__('Current password is not correct.'), 422);
        }

        try {
            $user->password = bcrypt($request->input('password_new'));
            $need_change_password = $user->need_change_password;            
            $user->need_change_password = false;
            if ( !$user->save() ) {
                return $this->sendErrorJsonResponse( __("There are some problems while trying to updating your request"), 500);    
            }
            if ( !$need_change_password ) {
                $request->user()->tokens()->delete();
            }
            return $this->sendSuccessResponse( __("Password has been changed successfully.") , 200);
        } catch (\Exception $e) {
            return $this->sendErrorJsonResponse( __("Internal Server Error!"), 500 );
        }
    }

    public function logout(Request $request)
    {
        try {
            $token = $request->user()->token();
            $token->getConnection()->transaction(function () use ($token) {
                $token->getConnection()->table('oauth_refresh_tokens')
                    ->where('access_token_id', $token->id)->update(['revoked' => true]);
                $token->delete();
            });
            return $this->sendSuccessResponse( __('You have successfully log out.'), 200);
        } catch (\Exception $e) {
            return $this->sendErrorJsonResponse( __("Internal Server Error!"), 500);
        }
    }

    public function getToken(Request $request)
    {
        return $request->user()->token()->first();
    }

    public function getSaleTeamLeaders() {
        $users = Role::findByName(UserRole::SALE_TEAM_LEADER,'web')->users()->get(['id', 'name', 'avatar', 'phone_number','gender']);
        return new UserCollection( $users );        
    }

    public function getSiteManagers() {
        $users = Role::findByName(UserRole::SITE_MANAGER,'web')->users()->get(['id', 'name', 'avatar', 'phone_number','gender']);
        return new UserCollection( $users );
    }
}
