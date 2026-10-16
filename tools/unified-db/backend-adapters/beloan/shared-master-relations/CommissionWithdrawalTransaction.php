<?php
/**
 * Created by PhpStorm.
 * User: SOPHEAK
 * Date: 6/4/2015
 * Time: 11:22 AM
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class CommissionWithdrawalTransaction extends Model {
    use UnifiedMasterReadRelations;
    protected $table = 'commission_withdrawal_transaction';
    protected $hidden = ['created_at', 'updated_at'];
} 