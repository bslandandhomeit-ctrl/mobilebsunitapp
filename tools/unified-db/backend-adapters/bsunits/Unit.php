<?php

namespace App;

use App\Helpers\NumberFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\UnitStatus;
use App\Helpers\UnitHoldStatus;

class Unit extends Model
{
    use SoftDeletes;

    protected static function boot()
    {
        parent::boot();

        static::created(function($model) {
            $action = UnitAction::create([
                'user_id' => $model->user_id,
                'unit_id' => $model->id,
                'action'  => 'AVAILABLE',
                'status_action'  => '',
                'actionable_type' => "",
                'actionable_id' => 0
            ]);
        });

        static::addGlobalScope('sortByCode', function (Builder $builder) {
            $builder->orderBy("code","ASC");
           
        });
    }

    protected static $actions = ["available", "contracted", "hold", 'deposit'];
    protected static $status = ["available", "contracted", "hold", 'deposit'];

    protected $perPage = 20;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
    */
    protected $fillable = [
        'user_id',
        'unit_type_id',
        'code',
        'price',
        'saleable',
        'street',
        'street_corner',
        'street_size',
        'floor',
        'land_size_width',
        'land_size_length',
        'land_area',
        'building_size_width',
        'building_size_length',
        'building_area',
        'gross_area',
        'living_room',
        'kitchen',
        'bedroom',
        'bathroom',
        'swimming_pool',
        'block',
        'unit_zone',
        'construction_overall_progress',
        'deadline',
        'deadline_status'
    ];

    protected $guarded = ['construction_overall_progress'];

    protected $with = ['action','action.createdBy','unitType','unitType.project'];

    protected $appends = ['availability_status', 'availability_status_color_code'];

    /**
     * The attributes that are needed to cast to Carbon Date Object.
     *
     * @var array
    */
    protected $dates = ["deleted_at", "updated_at", "created_at"];

    protected $casts = [
        'saleable' => 'boolean',
        'street_size' => 'string'
    ];

    public function getPriceAttribute($value)
    {
        return config('unified_catalog.request_property_reads_enabled', false)
            ? ($this->attributes['list_price'] ?? null) : $value;
    }

    public function getUnitActionIdAttribute($value)
    {
        return config('unified_catalog.request_property_reads_enabled', false)
            ? ($this->attributes['current_action_id'] ?? null) : $value;
    }

    // Model Relationship
    public function createdBy()
    {
        return $this->belongsTo('App\User', "user_id", "id")->select(['id','name','avatar','phone_number']);
    }

    public function unitType()
    {
        return $this->belongsTo('App\UnitType', "unit_type_id", "id")->withTrashed();
    }

    public function action()
    {
        return $this->belongsTo('App\UnitAction','unit_action_id',"id");
    }

    public function actions()
    {
        return $this->hasMany('App\UnitAction');
    }

    public function activities()
    {
        return $this->morphMany('App\Activity', 'trackable', 'model_type', 'model_id');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function unitHandovers()
    {
        return $this->hasMany('App\UnitHandover');
    }

    public function UnitHandover()
    {
        return $this->hasOne('App\UnitHandover')->latest();
    }

    public function handovers()
    {
        return $this->hasMany('App\UnitHandoverRequest');
    }
    
    public function zone()
    {
        return $this->belongsTo('App\Zone');
    }

    public function subConstructors()
    {
        return $this->belongsToMany('App\SubConstructor', 'sub_constructor_units');
    }

    public function constructionProcedures()
    {
        return $this->belongsToMany('App\ConstructionProcedure', 'unit_construction_procedures')        
        ->withPivot(['id', 'user_id', 'progress', 'estimate_completed_at', 'actual_completed_at', 'order', 'created_at', 'updated_at'])
        ->using('App\UnitConstructionProcedure');
    }
    // End Model Relationship

    // Query Scope
    public function scopeOfStatus($query, $status = '' ) 
    {
        if ( $status == '' ) {
            return $query;            
        }
        return $query->where('status', $status);     
    }

    public function scopeSaleable($query)
    {
        return $query->where("saleable", true);
    }

    public function scopePublishedProject($query) 
    {
        return $query->whereHas('unitType.project', function ($query) {
            return $query->where('is_published', true);
        });
    }

    public static function getStatuses() 
    {
        return static::$status;
    }

    public function getStatusCss() 
    {
        switch (strtolower($this->status)) {
            case 'available':
                return "text-primary";
                break;
            case 'contracted':
                return "text-success";
                break;
            case 'hold':
                return "text-danger";
                break;            
            default:
                return "text-muted";
                break;
        }
    }

    public function isAvailable() 
    {
        if ( strcasecmp($this->action->action, 'AVAILABLE') == 0 ) {
            return true;
        }
        return false;
    }

    public function allowHold()
    {
        if ( strcasecmp($this->action->action, UnitStatus::AVAILABLE) == 0 ) {
            return true;
        }
        
        if ( strcasecmp($this->action->action , UnitStatus::HOLD) == 0 
             AND strcasecmp($this->action->status_action, UnitHoldStatus::PENDING) == 0 ) {

            return true;
        }

        return false;
    }

    public function allowDeposit()
    {
        switch ($this->action->action) {
            case UnitStatus::AVAILABLE:
                return True;
                break;
            case UnitStatus::HOLD:
                if ( strcasecmp($this->action->action_status , UnitHoldStatus::APPROVED) ) {
                    $unit_hold_request = \App\UnitHoldRequest::find($this->action->actionable_id);
                    
                    if ( $unit_hold_request ) {
                        return $unit_hold_request->user_id == Auth::id();   
                    }
                    return false;
                } else {
                    return false;
                }
            default:
                return false;
                break;
        }       
    }


    public function isHold($action_status = Null)
    {   
        if ( $action_status == Null ) {
            return $this->action->action == UnitStatus::HOLD;
        } else {
            return $this->action->action == UnitStatus::HOLD AND 
                    $this->action->status_action == $action_status;
        }        
    }

    public function getBuildingAreaAttribute( $value ) 
    {
        if ( $this->building_size_width AND $this->building_size_length ) {
            return $this->building_size_width * $this->building_size_length;
        }
        return $value;
    }

    public function getNetAreaAttribute() 
    {
        if ( $this->building_size_width AND $this->building_size_length ) {
            return $this->building_size_width * $this->building_size_length;
        }
        return $this->building_area;
    }

    public function getLandAreaAttribute( $value ) {
        if ( $this->land_size_width AND $this->land_size_length ) {
            return $this->land_size_width * $this->land_size_length;
        }
        return $value;
    }

    public function getAvailabilityStatusAttribute() {
        if( isset($this->relations['action']) ){
            if ( $this->action->action == 'CONTRACT' AND $this->action->status_action == 'PENDING' ) {
                return $this->action->status_action.' '.$this->action->action;
            }
            return $this->action->action;
        }
        return '';
    }

    public function getAvailabilityStatusColorCodeAttribute() {      
        switch ($this->availability_status) {
            case 'AVAILABLE':
                return "#38C172";
                break;
            case 'UNAVAILABLE':
                return "#FF6347";
                break;
            case 'HOLD':
                return "#6C757D";
                break;
            case 'DEPOSIT':
                return "#FFA500";
                break;
            case 'PENDING CONTRACT':
                return "#ffcc2e";
                break;
            case 'CONTRACT':
                return "#3490dc";
                break;
            case 'HANDOVERED':
                return "#4744e1";
                break;
            default:            
                return "#cccccc";
                break;
        }
    }

    // << km language attribute
    public function getSwimmingPoolKmAttribute()
    {
        return NumberFormat::convertToKhmerNumber($this->swimming_pool);
    }

    public function getBathroomKmAttribute()
    {
        return NumberFormat::convertToKhmerNumber($this->bathroom);
    }

    public function getBedroomKmAttribute()
    {
        return NumberFormat::convertToKhmerNumber($this->bedroom);
    }

    public function getKitchenKmAttribute()
    {
        return NumberFormat::convertToKhmerNumber($this->kitchen);
    }
    public function getLivingRoomKmAttribute()
    {
        return NumberFormat::convertToKhmerNumber($this->living_room);
    }

    public function getFloorKmAttribute()
    {
        return NumberFormat::convertToKhmerNumber($this->floor);   
    }

    public function getTotalAreaSizeKmAttribute()
    {
        return NumberFormat::convertToKhmerNumber($this->total_area_size);   
    }

    public function getLandSizeWidthKmAttribute()
    {
        return NumberFormat::convertToKhmerNumber($this->land_size_width);   
    }

    public function getLandSizeLengthKmAttribute()
    {
        return NumberFormat::convertToKhmerNumber($this->land_size_length);   
    }

    public function getLandAreaKmAttribute()
    {
        return NumberFormat::convertToKhmerNumber($this->land_area);
    }

    public function getBuildingSizeWidthKmAttribute()
    {
        return NumberFormat::convertToKhmerNumber($this->building_size_width);   
    }

    public function getBuildingSizeLengthKmAttribute()
    {
        return NumberFormat::convertToKhmerNumber($this->building_size_length);   
    }

    public function getBuildingAreaKmAttribute()
    {
        return NumberFormat::convertToKhmerNumber($this->building_area);
    }
    // >>
}
