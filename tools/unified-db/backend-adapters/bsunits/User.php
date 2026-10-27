<?php

namespace App;

use App\Helpers\UserRole;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use League\OAuth2\Server\Exception\OAuthServerException;
use Carbon\Carbon;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasRoles;

    public function notifications()
    {
        if (config('unified_catalog.notification_reads_enabled', false)) {
            config(['database.connections.unified_notifications' => config('unified_catalog.notification_database')]);
            return $this->morphMany(UnifiedRequestNotification::class, 'notifiable')->orderBy('created_at', 'desc');
        }
        return $this->morphMany(\Illuminate\Notifications\DatabaseNotification::class, 'notifiable')->orderBy('created_at', 'desc');
    }

    protected static function boot()
    {
        parent::boot();

        // delete user's access tokens
        static::creating(function($model) {          
           if ( !is_array($model->metadata) ) {
               $model->metadata = [];
            }
        });

        static::saving(function($model){
            // Add 3 months from now to password_expired_at attribute
            if ( $model->isDirty('password') ) {
                $model->password_expired_at = now()->addMonths(3);
            }
        });
      
        static::updated(function($model) {  
            // if any status of [verified,active] is set to false,
            // delete user's access tokens        
            if ( $model->verified == false OR $model->active == false ) {
                $model->tokens()->delete();
            }
        });

        // When user destroyed, delete user's avatar to save disk space
        static::deleting(function($model) {
            $model->deleteOldProfileImage();
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 
        'email',
        'password',
        'phone_number',
        'gender',
        'birthdate',
        'department_id',
        'position',
        'avatar',
        'identification_id',
        'managed_by',
        'need_change_password',
        'password_expired_at',
        'position',
        'approvable'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'avatar', 'pivot', 'signature_image', 'department_id', 'position'
    ];

    protected $dates = [
        'birthdate',
        'created_at',
        'updated_at',
        'deleted_at',
        'activated_at',        
        'verified_at',
        'password_expired_at'
    ];

    /**
     * The The attributes that need to cast.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
        'need_change_password' => 'boolean',
        'verified' => 'boolean',
        'active' => 'boolean'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['avatar_url', 'player_id'];    
   
    static $created_by_fields = ['id', 'name', 'avatar', 'phone_number','gender'];

    /**
     * Append the avatar_url attribute into JSON response.
     *
     * @return string (avatar url)
     */
    public function getAvatarUrlAttribute()
    {
        // if ( is_null($this->avatar) OR trim($this->avatar) == "" ){
        //     // return asset('img/default_user_avatar.png');

        //     // new version of defualt user avatar
        //     return "https://ui-avatars.com/api/?name=$this->name&background=00b4ff&color=fff&rounded=true";

        // }
        if ( is_null($this->avatar) OR trim($this->avatar) == "" ){
            return asset('img/default_user_avatar.png');
        }
        
        return asset('storage/'.$this->avatar);
    }

    public function getSignatureImageUrlAttribute()
    {
        return is_null($this->signature_image) ? NULL : asset('storage/'.$this->signature_image);
    }

    public function getPlayerIdAttribute()
    {
        $token = $this->token();       
        if ( $token instanceof \Laravel\Passport\Token ) {
            if ( is_null($token->onesignal_player_id) ){
                return "";
            }
            return $token->onesignal_player_id;
        } else {
            return "";
        }
    }

    /**
     * if Birthday null or empty -> convert it to empty string.
     *
     * @param  string  $value
     * @return string
     */
    public function getBirthdateAttribute($value)
    {
        if ( is_null($value) OR trim($value) == "" ){
            return "";
        }
        return \Carbon\Carbon::parse($value);
    }

    /**
     * Get the user's Avatar.
     *
     * @param  string  $value
     * @return string
     */
    public function getManagedByAttribute($value)
    {
        if ( is_null($value) OR trim($value) == "" ){
            return 0;
        }

        return $value;
    }

    public function identifications() 
    {   
        return $this->hasMany('App\IdentificationImage');
    }

    public function deleteOldProfileImage(){
        Storage::disk('public')->delete($this->avatar);
    }

    public function deleteSignaturePhoto(){
        Storage::disk('public')->delete($this->signature_image);
    }

    // Model Relatioship go here

    public function projects() {
        return $this->hasMany('App\Project');
    }

    public function contractRequests() {
        return $this->hasMany('App\ContractRequest');
    }

    public function manager() 
    {
        return $this->belongsTo("App\User", "managed_by", "id");
    }

    public function members()
    {
        return $this->hasMany('App\User', 'managed_by', 'id');
    }

    public function posts()
    {
        return $this->hasMany('App\Post');
    }

    public function unitHoldRequests()
    {
        return $this->hasMany('App\UnitHoldRequest');
    }

    public function unitDepositRequests()
    {
        return $this->hasMany('App\UnitDepositRequest');
    }

    public function unitContractRequests()
    {
        return $this->hasMany('App\UnitContractRequest');
    }
    
    public function handovers()
    {
        return $this->hasMany('App\UnitHandoverRequest');
    }

    public function activator()
    {
        return $this->belongsTo(User::class, "activator_id", "id");
    }

    public function activities()
    {
        return $this->hasMany('App\Activity');
    }

    public function unitHandoverBatch()
    {
        return $this->hasMany('App\UnitHandoverBatch');
    }

    public function unitConstructions()
    {
        return $this->hasMany('App\UnitConstruction');
    }

    public function purchaseRequests()
    {
        return $this->hasMany('App\PurchaseRequest');
    }
   
    public function department()
    {
        return $this->belongsTo('App\Department');
    }

    /**
     * Get all of the projects that are assigned this user.
     */
    public function manageProjects()
    {
        return $this->morphedByMany(
            'App\Project', 
            'model',
            'user_manages_constructions', 
            'user_id',
            'model_id'
        );
    }

    /**
     * Get all of the projects that are assigned this user.
     */
    public function manageZones()
    {
        return $this->morphedByMany(
            'App\Zone', 
            'model',
            'user_manages_constructions', 
            'user_id',
            'model_id'
        );
    }

    public function zones()
    {
        return $this->hasMany('App\Zone');
    }

    public function constructionProcedures()
    {
        return $this->hasMany('App\ConstructionProcedure');
    }
    // End Model Relatioship go here


    // Model Mutator
    public function getGenderAttribute($value)
    {
        if ( is_null($value) OR $value == "") {
            return "N/A";
        }
        return $value;
    }
    // End Model Mutator

    // Model Query Scope
    public function scopeOfVerified($query, $verified = "") 
    {
        if ( is_bool($verified) ) {
            return $query->where('verified', $verified);
        } else {
            return $query;
        }

    }

    public function scopeOfActive($query, $active = "") 
    {
        if ( is_bool($active) ) {
            return $query->where('active', $active);
        } else {
            return $query;
        }

    }
    // End Model Query Scope

    // Helper Function 
    public function activate($user_id, $save = false) 
    {
        $this->forceFill([
            'active' => true,
            'activator_id' => $user_id,
            'activated_at' => now(),
        ]);

        if ( $save ) {
            return $this->save();
        }
    }

    public function deactivate($user_id, $save = false) 
    {
        $this->forceFill([
            'active' => false,
            'activator_id' => null,
            'activated_at' => null,
        ]);

        if ( $save ) {
            return $this->save();
        }
    }

    public function verified($user_id, $save = false) 
    {
        $this->forceFill([
            'verified' => true,
            'verifier_id' => $user_id,
            'verified_at' => now(),
        ]);
        if ( $save ) {
            return $this->save();
        }
    }

    public function unverified($user_id, $save = false)
    {
        $this->forceFill([
            'verified' => false,
            'verifier_id' => null,
            'verified_at' => null,
        ]);
        if ( $save ) {
            return $this->save();
        }
    }

    public function isManageProject($project)
    {
        $project = ( $project instanceof \App\Project ) ? $project->id : $project;
        return $this->manageProjects()->where('model_id', $project)->exists();
    }

    public function getVerifiedHtml()
    {
        if ( $this->verified ) {
            return '<span class="text-success"><i class="fas fa-check-circle"></i></span>';
        } else {
            return '<span class="text-danger"><i class="fas fa-times-circle"></i></span>';
        }
    }

    public function getActiveHtml()
    {
        if ( $this->active ) {
            return '<span class="text-success text-center"><i class="fas fa-check-circle"></i></span>';
        } else {
            return '<span class="text-danger text-center"><i class="fas fa-times-circle"></i></span>';
        }
    }
    // End Helper Function 

    /**
     * OneSignal attribute to be send push notification
     *
     * @return array of player_id
    */
    public function routeNotificationForOneSignal()
    {
        // Get Player_id array
        $player_id_array = $this->tokens->pluck('onesignal_player_id')->toArray();
        // remove duplicate
        $player_id_array = array_unique($player_id_array);
        // Remove All Null
        $player_id_array = array_filter($player_id_array);
        // flatten the array [only one dismentional array]
        $player_id_array = array_flatten($player_id_array);
        return $player_id_array;
    } 

    public function getKhFormattedPhoneNumber()
    {
        $khmer_prefix = '+855';
        return $khmer_prefix.substr($this->phone_number,1);
    }

    /**
     * Route notifications for the Twilio channel.
     *
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return string
     */
    public function routeNotificationForTwilio($notification)
    {
        return $this->getKhFormattedPhoneNumber();
    }

    /**
     * Change Authentication Field of Passport (Email -> phone_number)
     *
     * @return Model|Null|QueryBuilder
     */
    public function findForPassport($username) {
        $user = $this->where('phone_number', $username)->first();

        $allowed_role = [
            'customer',
            UserRole::ADMINISTRATOR, 
            UserRole::SALE_MANAGER,
            UserRole::UNIT_CONTROLLER,
            UserRole::SALE_TEAM_LEADER,
            UserRole::ACCOUNTANT,
            UserRole::AGENT,
            UserRole::REPORT
        ];

        if ( $user AND !$user->active ) {
            throw new OAuthServerException( __("Your account has been deactivated."), 6, 'account_inactive', 401);
        }

        if ( $user AND !$user->verified ) {
            throw new OAuthServerException( __("Your account is not verified."), 6, 'account_unverified', 401);
        }

        if ( $user AND !$user->hasRole($allowed_role) ) {
            throw new OAuthServerException( __("Your role is not supported by the app."), 6, 'role_not_permitted', 401);
        }

        return $user;
    }
   
    // public static functions
    public static function getCreatedByFields() 
    {
        return self::$created_by_fields;
    }

    // End public static functions
}
