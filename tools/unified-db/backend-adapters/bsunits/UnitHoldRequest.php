<?php

namespace App;

use App\UnitAction;
use App\Unit;
use App\Helpers\UnitHoldStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class UnitHoldRequest extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('sortByCreatedAt', function (Builder $builder) { 
            $builder->orderBy('created_at', 'DESC');         
           
        });
    }

    protected $fillable = ['user_id','unit_id','remark','status','hold_day','release_date','actioned_user_id', 'action_reason' ,'actioned_at'];

    protected $with = [
        "actionedBy"
    ];

    /**
     * The attributes that are needed to cast to Carbon Date Object.
     *
     * @var array
    */
    protected $dates = ["actioned_at", "release_date", "deleted_at", "updated_at", "created_at"];

    // Model Relationship
    public function createdBy()
    {
    	return $this->belongsTo('App\User','user_id','id')->select('id', 'name', 'avatar', 'phone_number', 'gender');
    }

    public function actionedBy()
    {
        return $this->belongsTo('App\User','actioned_user_id', 'id')->select('id', 'name', 'avatar', 'phone_number', 'gender');
    }

    public function unit()
    {
        return $this->belongsTo("App\Unit", "unit_id", "id")->withTrashed();
    }

    public function action()
    {
        return $this->morphOne("App\UnitAction", "actionable");
    }
    // End Model Relationship

    // Query Scope 
    public function scopeOwnerOnly($query)
    {
        return $query->where('user_id', Auth::id());
    }

    public function scopeStatus($query, $status = null, $unit_id = null) 
    {
        if ( !is_null($status) ) {
            $query = $query->where('status', $status);
        }
        if ( !is_null($unit_id) ) {
            $query  = $query->where('unit_id', $unit_id);
        }
        return $query;
    }

    public function scopeHoldPending($query, $unit_id = null)
    {   
        if ( is_null($unit_id) ) {
            return $query->where('status', UnitHoldStatus::PENDING);
        }
        return $query->where('status', UnitHoldStatus::PENDING)
                     ->where('unit_id', $unit_id);
    }

    public function scopeApproved($query, $unit_id = null)
    {   
        if ( is_null($unit_id) ) {
            return $query->where('status', UnitHoldStatus::APPROVED);
        }
        return $query->where('status', UnitHoldStatus::APPROVED)
                     ->where('unit_id', $unit_id);
    }

    public function scopeOverdued($query, $unit_id = null)
    {   
        if ( is_null($unit_id) ) {
            return $query->where('status', UnitHoldStatus::OVERDUE);
        }
        return $query->where('status', UnitHoldStatus::OVERDUE)
                     ->where('unit_id', $unit_id);
    }

    public function scopeExcluded($query, Array $id_array = [])
    {
        if ( is_array($id_array) and count($id_array) > 0 ) {
            return $query->whereNotIn("id", $id_array);
        }
        return $query;
    } 

    public function scopeAgents($query, $agents)
    {
        return $query->whereIn('user_id', $agents);
    }

    public function scopeUnitOtherPending($query)
    {
        return $query->whereIn('user_id', $agents);
    }

    public function scopeOfCreatedBetweenDate($query, $from = null, $to = null)
    {   
        $date_format = config("app.php_date_format");

        $from   = is_null($from) ? Carbon::today()->firstOfMonth() : Carbon::createFromFormat($date_format, $from)->startOfDay();
        $to     = is_null($to) ? Carbon::today()->lastOfMonth()->addHours(23)->addMinutes(59)->addSeconds(59) : Carbon::createFromFormat($date_format.' H:i:s', $to." 23:59:59");

        return $query->whereBetween('created_at', [$from, $to]);
    }

    public function scopeOfCountStatistic($query, $from = null , $to = null, $group_by = 'DAY') {
        $sql_format = '';
        switch ($group_by) {          
            case 'MONTH':
                $sql_format = 'DATE_FORMAT(created_at, "%Y-%m")';
                break;
            case 'YEAR':
                $sql_format = 'DATE_FORMAT(created_at, "%Y")';
                break;
            default:
                $sql_format = 'DATE_FORMAT(created_at, "%Y-%m-%d")';
                break;
        }       
        return DB::table($this->getTable())             
               ->groupBy('date')
               ->whereBetween('created_at', [$from, $to])
               ->select(DB::raw('count(id) as count'), DB::raw($sql_format. ' `date`'));
    }

    // End Query Scope

    // Model Mutator
    public function getRemarkAttribute($value)
    {
        if ( is_null($value) ) {
            return "";
        } 
        return $value;
    }
    // End Model Mutator

    public function needApprove()
    {
        return (strcasecmp($this->status, UnitHoldStatus::PENDING) == 0);
    }

    public function approve(bool $send_notication = false, bool $create_unit_action = false)
    {
        $auth_user = Auth::user();
        
        if ( !$auth_user ) {
            abort(403);
        }

        $this->status = UnitHoldStatus::APPROVED;
        $this->actioned_user_id =  $auth_user->id;
        $this->actioned_at = now();        

        if ( $create_unit_action ) {
            $action = UnitAction::create([
                'user_id' => $auth_user->id,
                'unit_id' => $this->unit_id,
                'action' => "HOLD",
                'status_action' => UnitHoldStatus::APPROVED,
                'actionable_type' => $this->getMorphClass(),
                'actionable_id' => $this->id
            ]);
            if (!config('unified_catalog.request_writes_enabled')) {
                $unit = Unit::findOrFail($this->unit_id);
                $unit->action()->associate($action)->save();
            }
        }

        return $this->save();
    }

    public static function isUnitRequestedPending($unit_id, $user_id) {
    	$hold_request = self::where("unit_id",$unit_id)
    	                    ->where("user_id",$user_id)
    	                    ->where("status", UnitHoldStatus::PENDING)->first();

    	return !is_null($hold_request);
    }

    public function isContainCustomer2()
    {
        return !(empty($this->customer2_name));
    }

    public function isCustomerPhoneNumber2()
    {
        return !(empty($this->customer_phone_number2));
    }

    public function getStatusHtml()
    {
        $result = "";
        switch ($this->status) {
            case 'PENDING':
                $result = '<span class="badge badge-pill badge-warning">';
                break;
            case 'APPROVED':
                $result = '<span class="badge badge-pill badge-success">';
                break;
            case 'OVERDUE':
                $result = '<span class="badge badge-pill badge-danger">';
                break;
            case 'RELEASE':
                $result = '<span class="badge badge-pill badge-primary">';
                break;
            case 'CANCELLED':
                $result = '<span class="badge badge-pill badge-danger">';
                break; 
            case 'VOID':
                $result = '<span class="badge badge-pill badge-danger">';
                break;    
            default:
                $result = '<span class="badge badge-pill badge-secondary">';
                break;
        }

        return $result.$this->status."</span>";
    }


}
