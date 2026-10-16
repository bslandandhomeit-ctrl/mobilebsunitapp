<?php

namespace App;

use App\Unit;
use App\Helpers\UnitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class UnitAction extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::created(function($model) {           
            if (config('unified_catalog.request_writes_enabled')) {
                \App\Services\UnifiedRequestUnitActionWriter::apply($model);
                return;
            }
            Unit::withTrashed()->where('id', $model->unit_id)->update(["unit_action_id" => $model->id]);
            if (config('unified_catalog.action_sync_enabled')) {
                \App\Services\UnifiedUnitActionSync::enqueue($model);
            }
        });

        static::addGlobalScope('sortByCreatedAt', function (Builder $builder) { 
            $builder->orderBy('created_at', 'DESC');
            $builder->orderBy('id',"DESC");
           
        });
    }

    public function save(array $options = [])
    {
        if (!config('unified_catalog.action_sync_enabled') && !config('unified_catalog.request_writes_enabled')) return parent::save($options);
        // Action, current source pointer and outbox entry must commit together.
        return $this->getConnection()->transaction(function () use ($options) {
            return parent::save($options);
        });
    }

    protected $fillable = [
        'user_id',
        'unit_id',
        'action',
        'status_action',
        'description',
        'meta_data',
        'actionable_type',
        'actionable_id',
        'issue_date',
        'type'
    ];

    /**
     * The attributes that are needed to cast to Carbon Date Object.
     *
     * @var array
    */
    protected $dates = ["updated_at", "created_at"];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'meta_data' => 'array',
    ];

     /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['status_html', 'status_action_html'];

    // protected static $actions = [
    //  'HOLD',
    //  'DEPOSIT',
    //  'CONTRACT',
    //  'AVAILABLE',
    //  'UNAVAILABLE'
    // ];

    // protected static $status_actions = [
    //     'OPEN', 'PENDING', 'OVERDUE', 'RELEASE', 'APPROVED', 'REJECTED'
    // ];

    public static function getActionList() 
    {
        return UnitStatus::getUnitStatuses();
    }

    public function actionable()
    {
        return $this->morphTo();
    }    

    public function getActionCss()
    {
        switch ($this->action) {
            case UnitStatus::UNAVAILABLE:
            case UnitStatus::HOLD:
                return "badge badge-pill badge-danger";
                break;
            case UnitStatus::DEPOSIT:
                return "badge badge-pill badge-info";
                break;
            case UnitStatus::CONTRACT:
                return "badge badge-pill badge-success";
                break;
            case UnitStatus::AVAILABLE:
                return "badge badge-pill badge-primary";
                break;
             case UnitStatus::HANDOVERED:
                return "badge badge-pill badge-cyan";
                break;
            case UnitStatus::REPAIR_MAINTENANCE:
                 return "badge badge-pill bg-dark text-white";
                 break;
            default:
                return "badge badge-secondary";
                break;
        }
    }

    public function getStatusActionCss()
    {
        switch ($this->status_action) {
            case 'PENDING':
                return "badge badge-pill badge-warning";
                break;
            case 'APPROVED':
                return "badge badge-pill badge-success";
                break;
            case 'OVERDUE':
            case 'CANCELLED':
                return "badge badge-pill badge-danger";
                break;
            case 'RELEASE':
                return "badge badge-pill badge-primary";
                break;    
            default:
                return "badge badge-secondary";
                break;
        }
    }

    public function getStatusAction(bool $bracket = false) 
    {
        if ( !$this->status_action ) {
            return "";
        }
        
        if ( $bracket ) {
            return "(".$this->status_action.")";
        }
        return $this->status_action;
    }

    public function getStatusHtmlAttribute()
    {
        return '<span class="'.$this->getActionCss().'">'.$this->action.'</span>';
    }

    public function getStatusActionHtmlAttribute()
    {
        return '<span class="'.$this->getStatusActionCss().'">'.$this->status_action.'</span>';
    }

    public function getDescriptionAttribute($value)
    {
        return $value ?? '';
    }

    // Model Relationship
    public function createdBy()
    {
        return $this->belongsTo('App\User','user_id','id')->select('id','name','avatar','phone_number');
    }

    public function unit()
    {
        return $this->belongsTo("App\Unit", "unit_id", "id")->withTrashed();
    }
    // End Model Relationship

    // Query Scope 
    public function scopeByUnitType($query, $unit_type_id)
    {
        $query->whereHas('unit.unitType', function ($q) use ($unit_type_id) {
            $q->where('id', $unit_type_id);
        });
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'DESC');
    }    
    // End Query Scope

    public static function makeUnitAvailableBySystem($unit_id, $actionable_type = '', int $actionable_id = 0)
    {
        self::create([
            'user_id' => config('app.default_system_user_id'),
            'unit_id' => $unit_id,
            'action' => 'AVAILABLE',
            'status_action' => '',              
            'actionable_type' => $actionable_type,
            'actionable_id' => $actionable_id
        ]);
    }
}
