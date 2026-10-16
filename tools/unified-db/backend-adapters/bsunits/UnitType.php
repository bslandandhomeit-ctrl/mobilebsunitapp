<?php

namespace App;

use Spatie\MediaLibrary\HasMedia\HasMedia;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;
use Spatie\MediaLibrary\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class UnitType extends Model implements HasMedia
{
	use SoftDeletes, HasMediaTrait;

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('sortByName', function (Builder $builder) {
            $builder->orderBy(config('unified_catalog.request_property_reads_enabled', false) ? 'name_km' : 'name', 'asc');
        });
    }
    
	/**
     * Attributes need to cast to Carbon(DateT`ime) object
     *
     * @var array
    */
	protected $dates = ['created_at', 'updated_at', 'deleted_at'];

	/**
     * The attributes that are mass assignable.
     *
     * @var array
    */
    public function getAttribute($key)
    {
        if (config('unified_catalog.request_property_reads_enabled', false)) {
            if ($key === 'name') return $this->attributes['name_km'] ?? null;
            if ($key === 'short_code') return $this->attributes['code'] ?? null;
            $support = json_decode($this->attributes['attributes'] ?? '{}', true) ?: [];
            if (!array_key_exists($key, $this->attributes) && array_key_exists($key, $support)) {
                return $this->hasGetMutator($key) ? $this->mutateAttribute($key, $support[$key]) : $support[$key];
            }
        }
        return parent::getAttribute($key);
    }

    protected $fillable = [
        'user_id',
        'project_id', 
        'name',
        'short_code',
        'is_contractable',
        'annual_management_fee',
        'contract_transfer_fee',
        'management_fee_per_square',
        'deadline',
        'extended_deadline',
        'title_clause_kh',
        'title_clause_en',
        'title_clause_zh',
        'management_service_kh',
        'management_service_en',
        'management_service_zh',
        'contract_template_id', 
        'payment_option_image_url',
        'equipment_text',
        'equipment_text_en',
        'equipment_text_zh',
        'feature_image_url'
    ];

    protected $casts = [
        'is_contractable'
    ];

     /**
     * The attributes that appended to to JSON Response.
     *
     * @var array
     */
    protected $appends = ['contract_template_url', 'category'];

    protected $with = ['contractTemplate'];

    protected $hidden = [
        'annual_management_fee',
        'contract_transfer_fee',
        'management_fee_per_square',
        'deadline',
        'extended_deadline',
        'title_clause_kh',
        'management_service_kh',
        'equipment_text',
        'title_clause_en',
        'management_service_en',
        'equipment_text_en',
        'title_clause_zh',
        'management_service_zh',
        'equipment_text_zh',
    ];

    /**
     * The Media Collection allow for Object.
     *
     * @var array
     */
    protected $media_collection = [
        'FLOOR_PLAN',
        'INTERIOR',
        'EXTERIOR'
    ];

    // Model Relationship go here

    public function createdBy() {
        return $this->belongsTo('App\User', 'user_id', "id")->select(['id','name','avatar','phone_number']);
    }

    public function project() {       
        return $this->belongsTo('App\Project', 'project_id', 'id')->withTrashed();
    }

    public function units()
    {
        return $this->hasMany('App\Unit');
    }
    
    public function paymentOptions() {
        return $this->hasMany('App\PaymentOption');
    }

    public function contractTemplate() {
        return $this->belongsTo('App\ContractTemplate')->select('id','name','template_path');
    }

    public function discountPromotion() {
        return $this->belongsToMany('App\DiscountPromotion', "discount_promotion_items","unit_type_id","discount_promotion_id");
    }

    public function discountPromotions() {
        return $this->belongsToMany('App\DiscountPromotion', "discount_promotion_items","unit_type_id","discount_promotion_id");
    }

    public function activeDiscountPromotions() 
    {
        return $this->belongsToMany('App\DiscountPromotion', "discount_promotion_items", "unit_type_id", "discount_promotion_id")
                    ->where("discount_promotions.start_date", "<=", Carbon::today())
                    ->where('discount_promotions.end_date', ">=", Carbon::today());
    }

    // End Model Relationship

    // Query Scope
    public function scopeOfStatus($query, $status = '' ) 
    {
        switch ($status) {
            case 'all':
                return $query->withTrashed();
            case 'removed':
                return $query->onlyTrashed();
            case 'active':
                return $query;
            default:   
                return $query->withTrashed();
                
        }     
    }
    // End Query Scope

    public function deletePaymentOptionImage() 
    {
        Storage::disk('public')->delete($this->getOriginal('payment_option_image_url'));
    }

    public function deleteFeatureImageUrlImage() 
    {
        Storage::disk('public')->delete($this->getOriginal('feature_image_url'));
    }

    /**
     * Convert to full domain URL base for payment_option_image_url
     *
     * @return string payment_option_image_url
     */
    public function getPaymentOptionImageUrlAttribute($value)
    {
        if ( is_null($value) OR trim($value) == "" ){
            return null;
        }

        return asset('storage/'.$value);
    }

    /**
     * Convert to full domain URL base for feature_image_url
     *
     * @return string feature_image_url
     */
    public function getFeatureImageUrlAttribute($value)
    {
        if ( is_null($value) OR trim($value) == "" ){
            return asset('img/no_image_preview.jpg');
        }

        return asset('storage/'.$value);
    }

    public function getContractTemplateUrlAttribute()
    {
        if ( is_null($this->contract_template_id) ) {
            return null;            
        }        
        return route('admin.contract_templates.preview', [
            'template_path' => $this->contractTemplate->template_path, 
            'unit_type_id'=>$this->id, 
            'version' => 'v2'
        ]);
    }

    public function getCategoryAttribute()
    {
        if ( is_null($this->contract_template_id) ) {
            return null;            
        } 
        return strtoupper($this->contractTemplate->name);
    }

    public function getDiscountPromotionAmountByDate($date)
    {
        $discount_promotion = $this->discountPromotion()
                                       ->where("start_date", "<=",$date)
                                       ->where('end_date', ">=", $date)
                                       ->first();
        return (is_null($discount_promotion) ? 0 : $discount_promotion->amount);
    }

    public static function getDiscountPromotion($unit_type_id, $date)
    {
        $unit_type = self::find($unit_type_id);
        $discount_promotion = $unit_type->discountPromotion()
                                       ->where("start_date", "<=",$date)
                                       ->where('end_date', ">=", $date)
                                       ->first();

        
        return (is_null($discount_promotion) ? 0 : $discount_promotion->amount);
    }

    /** 
     * Get Allow Media Collection
     * 
     * @return Array
     */
    public function getMediaCollection() 
    {
        return $this->media_collection;
    }

    /**
     * Init allow media collection which allow to this Model
     *
     * @return void
     */
    public function registerMediaCollections()
    {
        foreach ($this->media_collection as $val) {
            $this
            ->addMediaCollection($val)
            ->registerMediaConversions(function ( Media $media ) {
                $this
                ->addMediaConversion('thumb')
                ->width(368)
                ->height(232);
            });
        }
    }
}
