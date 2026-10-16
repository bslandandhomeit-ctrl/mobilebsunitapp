<?php

namespace App;

use App\Jobs\ProcessDepositRequestOverdue;
use App\Helpers\UnitDepositStatus;
use App\Helpers\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class UnitDepositRequest extends Model
{
    // number of day to send notification; 
    protected static $overdue_threshold = 7;

    protected $fillable = [
    	'user_id',
    	'unit_id',
        'status',
        'remark',
    	'unit_sale_price',
    	'discount_promotion',
    	'other_discount_allowance',
    	'deposit_amount',
    	'deposited_at',
        'is_selected_payment_option',
    	'payment_option_id',
    	'loan_duration',
    	'interest',
    	'special_discount',
    	'is_first_payment',
    	'first_payment_duration',
    	'first_payment_percentage',
    	'first_payment_amount',
    	'customer_name',
    	'customer_gender',
        'customer2_name',
        'customer2_gender',
    	'customer_phone_number',
        'customer_phone_number2'
    ];

    protected $dates = [
        'canceled_at',
        'deleted_at',
        'created_at',
        'updated_at',
        'deposited_at',
        'received_at',
        'actioned_at',
        'unit_controller_actioned_at',
        'sale_manager_actioned_at'
    ];
       
    protected $casts = [
        'is_selected_payment_option' => 'boolean',
        'is_first_payment' => 'boolean',
    ];

    protected $appends = ['payment_status', 'editable', 'changeable', 'contract_requestable', 'cancellable'];

    protected $duplicate_attributes = [
        'user_id',
        'deposit_amount',
        'deposited_at',
        'receiving_amount',
        'customer_name',
        'customer_gender',
        'customer2_name',
        'customer2_gender',
        'customer_phone_number',
        'customer_phone_number2'
    ];
    
    static protected $payment_status = [
        'Overdue','Unpaid','Partial','Full'
    ];

    protected $overdue_day = 2;

    /**
     * The User's attributes that will be return on this's model's attribute that reference to User Model.
     *
     * @var array
     */
    protected $userVisibleAttributes = ['id', 'name', 'avatar', 'phone_number', 'managed_by'];

    public function save(array $options = [])
    {
        if (!config('unified_catalog.deposit_conversion_enabled')) return parent::save($options);
        return $this->getConnection()->transaction(function () use ($options) { return parent::save($options); });
    }

    public function overdueJobDueAt()
    {
        return $this->created_at ? $this->created_at->copy()->addDays(self::$overdue_threshold) : null;
    }

    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            if (config('unified_catalog.deposit_conversion_enabled') && $model->status !== 'APPROVED') {
                $model->getConnection()->table('unified_approved_deposit_outbox')->where('deposit_request_id', $model->id)->delete();
            }
            if (config('unified_catalog.deposit_conversion_enabled') && $model->status === 'APPROVED'
                && ($model->isDirty('status') || $model->wasRecentlyCreated)) {
                \App\Services\UnifiedApprovedDepositOutbox::enqueue($model);
            }
        });

        static::creating( function($model) {
            if (is_null( $model->customer_phone_number2 )) {
                $model->customer_phone_number2 = "";
            }     
            if ( ($model->other_discount_allowance == 0) AND (is_null($model->payment_option_id) == false) ) {
                $model->sale_manager_status = 'NOT_REQUIRED';
            }
            if ( is_null($model->customer2_name) )
            {
                $model->customer2_name = "";
                $model->customer2_gender = 0;
            }
            if ( is_null($model->remark) ) {
                $model->remark = "";
            }
        });

        static::created( function($model){
            $pending = ProcessDepositRequestOverdue::dispatch($model)
                ->delay(now()->addDays(self::$overdue_threshold));
            if (config('unified_catalog.request_writes_enabled')) $pending->onQueue('unified-deposit-overdue');
        });

        static::updating( function($model) {
            if (is_null( $model->customer_phone_number2 )) {
                $model->customer_phone_number2 = "";
            }

            if ( is_null($model->customer2_name) )
            {
                $model->customer2_name = "";
                $model->customer2_gender = 0;
            }
        });

        static::addGlobalScope('sortByCreatedAt', function (Builder $builder) { 
            $builder->orderBy('created_at', 'DESC');         
           
        });
    }

    // Model Relationship
    public function createdBy() 
    {
        return $this->belongsTo('App\User', 'user_id', 'id')->select($this->getUserVisibleAttributes());
    }

    public function unitController()
    {
        return $this->belongsTo('App\User', 'unit_controller_id', 'id')->select($this->getUserVisibleAttributes());
    }

    public function saleManager()
    {
        return $this->belongsTo('App\User', 'sale_manager_id', 'id')->select($this->getUserVisibleAttributes());
    }
   
    public function paymentOption()
    {   
        return $this->belongsTo('App\PaymentOption', "payment_option_id", "id")->withTrashed();
    }

    public function unit()
    {
        return $this->belongsTo("App\Unit")->withTrashed();
    }

    public function changeFrom()
    {
        return $this->belongsTo('App\UnitDepositRequest', "from_unit_deposit_request_id", 'id');
    }

    public function changeTo()
    {
        return $this->belongsTo('App\UnitDepositRequest', "to_unit_deposit_request_id", 'id');
    }

    public function actionedBy()
    {
        return $this->belongsTo('App\User', 'actioned_user_id', 'id')->select($this->getUserVisibleAttributes());
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

    // End Model Relationship

    // Model Scope
    public function scopeAgents($query, $agents)
    {
        return $query->whereIn('user_id', $agents);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'DESC');
    }

    public function scopeOfCreatedBetweenDate($query, $from = null, $to = null)
    {
        $date_format = config("app.php_date_format");

        $from   = is_null($from) ? Carbon::today()->firstOfMonth() : Carbon::createFromFormat($date_format, $from)->startOfDay();
        $to     = is_null($to) ? Carbon::today()->lastOfMonth()->addHours(23)->addMinutes(59)->addSeconds(59) : Carbon::createFromFormat($date_format.' H:i:s', $to." 23:59:59");

        return $query->whereBetween('created_at', [$from, $to]);
    }

    public function scopeOfStatus($query, $status = '')
    {
        if ( $status == '' ) {
            return $query;
        }

        if ( is_array($status) ) {
            return $query->whereIn('status', $status);
        }

        return $query->where('status', $status);
    }

    public function scopeOfReceivingAmount($query, $receiving_amount_status)
    {
        
        switch( $receiving_amount_status ) {
            case 'Overdue':
                return $query->whereRaw('DATEDIFF(CURDATE(), `deposited_at`) > ?',  [$this->overdue_day])   
                             ->where('deposit_amount', '!=', 0)
                             ->where('receiving_amount', 0);
            case 'Unpaid':
                return $query->whereRaw('DATEDIFF(CURDATE(), `deposited_at`) <= ?', [$this->overdue_day])
                             ->where('receiving_amount', 0);                
            case 'Partial':
                return $query->whereRaw('DATEDIFF(CURDATE(), `deposited_at`) <= ?', [$this->overdue_day])
                            ->where('receiving_amount', '>', 0)
                            ->whereColumn('receiving_amount', '<', 'deposit_amount');
            case 'Full':
                return $query->whereColumn('receiving_amount', '>=', 'deposit_amount');
            default:
                return $query;
        }
    }
    // End Model Scope

    // Model Mutator
    public function getSaleManagerStatusAttribute($value)
    {
        if ( !$this->is_selected_payment_option ) {
            $value = "NOT_REQUIRED";
        }
        
        if ( ($this->other_discount_allowance == 0) AND (is_null($this->payment_option_id) == false) ) {
           $value = "NOT_REQUIRED";
        }
        return $value;
    }

    public function getRemarkAttribute($value)
    {
        if ( is_null($value) ) {
            return "";
        } 
        return $value;
    }

    public function getFirstPaymentPercentageAttribute($value)
    {
        if ( is_null($value) ) {
            return 0;
        } 
        return $value;
    }

    public function getFirstPaymentAmountAttribute($value)
    {
        if ( is_null($value) ) {
            return 0;
        } 
        return $value;
    }

    public function getPaymentStatusAttribute() {
        return $this->getPaymentStatus();
    }

    // End Model Mutator

    // helper function 
    public function isStatus($status) 
    {
        return strcasecmp($this->status, $status) == 0 ? true : false;
    }

    public function setDuplicateAttributes(UnitDepositRequest $unit_desposit_request)
    {
        $data = array_only($unit_desposit_request->attributesToArray(), $this->duplicate_attributes);
        $this->fill($data);
        $this->receiving_amount = $unit_desposit_request->receiving_amount;
        return $this;
    }

    public function isFullyPaid()
    {
        return $this->receiving_amount >= $this->deposit_amount;
    }
    // End Helper Function 

    public function getEditableAttribute()
    {
        return $this->isEditable();
    }

    public function getChangeableAttribute()
    {
        return $this->isChangeable();
    }

    public function getContractRequestableAttribute()
    {
        return $this->isContractRequestable();
    }

    public function getCancellableAttribute()
    {
        return $this->isCancellable();
    }

    public function isEditable()
    {
        if ( Auth::id() != $this->user_id ) {
            return false;
        }
        if ( !(is_null($this->unit_controller_id)) ) {
            return false;
        }

        if ( !(is_null($this->sale_manager_id)) ) {
            return false;
        }
        return true;
    }

    public function isChangeable() 
    {
        if ( Auth::id() != $this->user_id ) {
            return false;
        }
        return $this->isStatus(UnitDepositStatus::APPROVED) OR $this->isStatus(UnitDepositStatus::OVERDUE);
    }

    public function isContractRequestable()
    {
        if ( Auth::id() != $this->user_id ) {
            return false;
        }

        return $this->isStatus(UnitDepositStatus::APPROVED) 
                OR $this->isStatus(UnitDepositStatus::OVERDUE);
    }

    public function isCancellable()
    {
        if ( !Auth::user() ) {
            return false;
        }

        if ( !Auth::user()->hasRole([UserRole::UNIT_CONTROLLER, UserRole::ACCOUNTANT]) ) {
            return false;
        }

        return $this->isStatus(UnitDepositStatus::APPROVED) 
                OR $this->isStatus(UnitDepositStatus::OVERDUE);
    }

    public function isVoidable()
    {
        if ( !Auth::user() ) return false;
        
        if ( !Auth::user()->hasRole([UserRole::UNIT_CONTROLLER, UserRole::ACCOUNTANT]) ) return false;
        
        return $this->isStatus(UnitDepositStatus::APPROVED) 
                OR $this->isStatus(UnitDepositStatus::OVERDUE);
    }

    public function setAction($action_status, $action_reason = '') 
    {
        $this->status = $action_status;
        $this->action_reason = $action_reason;
        $this->actioned_user_id = Auth::user()->id;
        $this->actioned_at = now();   
    }

    public function isCustomerPhoneNumber2()
    {
        return !(empty($this->customer_phone_number2));
    }

    public function isContainCustomer2()
    {
        return !(empty($this->customer2_name));
    }

    public function getDueAmount()
    {
        return $this->deposit_amount - $this->receiving_amount;
    }

    public function getPaymentStatus()
    {
        if ( $this->deposited_at->diffInDays(today()) > $this->overdue_day 
             AND $this->receiving_amount == 0  
             AND $this->deposit_amount != 0 )
        {
            return "Overdue";
        } 

        if ( $this->receiving_amount == 0 AND $this->deposit_amount != 0 ) {
            return "Unpaid";
        }

        if ( $this->receiving_amount < $this->deposit_amount ) {
            return "Partial";
        }

        if ( $this->receiving_amount >= $this->deposit_amount ) {
            return "Full";
        }
    }

    public function getPaymentStatusHtml()
    {
        $payment_status = $this->getPaymentStatus();
        $result = '';
        switch ( $payment_status ) {
            case 'Overdue':
                $result = '<span class="badge badge-pill badge-danger">';
                break;                
            case 'Unpaid':
                $result = '<span class="badge badge-pill badge-warning">';
                break;
            case 'Partial':
                $result = '<span class="badge badge-pill badge-info">';
                break;
            case 'Full':
                $result = '<span class="badge badge-pill badge-success">';
                break;
            default:
                $result = '<span class="badge badge-pill badge-secondary">';
                break;
        }

        return $result.$payment_status."</span>";
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

    public function getUserVisibleAttributes()
    {
        return $this->userVisibleAttributes;
    }
  
    protected static function getPaymentStatuses()
    {
        return self::$payment_status;
    }
}


