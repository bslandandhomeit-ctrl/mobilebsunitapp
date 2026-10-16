<?php
/**
 * Created by PhpStorm.
 * User: SOPHEAK
 * Date: 1/09/2015
 * Time: 3:34 PM
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepaymentSchedule extends Model{
    use UnifiedMasterReadRelations;
    protected $table = 'repayment_schedule';
    public $timestamps = false;
    protected $guarded = [];

    public static function IndexRaw($index_raw)
    {
        $model = new static();
        $model->setTable(\DB::raw('tb_repayment_schedule' . ' ' . $index_raw));
        return $model;
    }

    public function payment(){
        return $this->unifiedMasterRelation('hasMany','App\Models\LoanPayments','loan_id','loan_payments','payment','loan_id')
        			->where('payment_month', $this->no);
    }

}
