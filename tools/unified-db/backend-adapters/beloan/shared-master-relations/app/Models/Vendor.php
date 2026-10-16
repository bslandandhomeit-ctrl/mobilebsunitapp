<?php
/**
 * Created by PhpStorm.
 * User: heng soheak
 * Date: 9/2/2016
 * Time: 3:31 PM
 */
 namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class Vendor extends Model {
    use UnifiedMasterReadRelations;

    protected $table = 'acc_vendor';
    //protected $fillable = ['role_id','name', 'email','username'];
    protected $hidden = ['created_at','updated_at'];

    final function JournalRequiry() {
        return $this->unifiedMasterRelation('hasMany','App\Models\JournalRequiry','ref_name_id','journal_requiry','JournalRequiry','id');
    }

    final function JournalRequiryWhere() {
        return $this->unifiedMasterRelation('hasMany','App\Models\JournalRequiry','ref_name_id','journal_requiry','JournalRequiry','id')->where('ref_name_type', 1);
    }

}

