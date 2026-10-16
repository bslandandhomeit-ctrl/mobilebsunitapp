<?php
/**
 * Created by PhpStorm.
 * User: SOPHEAK
 * Date: 2/11/2015
 * Time: 11:37 AM
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class CoaCategory extends Model
{
    use UnifiedMasterReadRelations;
    protected $table = 'coa_categories';
    public $timestamps = false;

    public function children()
    {
        return $this->unifiedMasterRelation('hasMany','App\Models\CoaCategory','parent_id','coa_categories','children','id');
    }
    public function parent()
    {
        return $this->unifiedMasterRelation('belongsTo','App\Models\CoaCategory','parent_id','coa_categories','parent');
    }

    final function detail () {

        return $this->unifiedMasterRelation('hasMany','App\Models\JournalDetail','coa_id','journal_detail','detail');
    }
    public function currency_i()
    {
        return $this->unifiedMasterRelation('hasOne','App\Models\Currency','id','currency','currency_i','currency');
    }

}

