<?php namespace App\Models;
/**
 * Created by PhpStorm.
 * User: ChamroeunDeab
 * Date: 5/29/2015
 * Time: 4:24 PM
 */

use Illuminate\Database\Eloquent\Model;

class LoanPayments extends Model {
    use UnifiedMasterReadRelations;
    protected $table = 'loan_payments';

    public function loan(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\Loan','loan_id','loans','loan');
    }

    protected $hidden = ['created_at','updated_at'];    
}
