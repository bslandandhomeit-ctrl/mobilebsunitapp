<?php
require '/app/vendor/autoload.php';
function pricingCheck($ok,$label){if(!$ok)throw new RuntimeException('Pricing check failed: '.$label);}
try {
 $pricing=new App\Services\ApprovedDepositSalePricing;
 $r=['status'=>'APPROVED','unit_controller_status'=>'APPROVED','sale_manager_status'=>'APPROVED','unit_sale_price'=>'90000.25','discount_promotion'=>'3000.10','other_discount_allowance'=>'500.05','deposit_amount'=>'200.25','special_discount'=>'5.50'];
 $p=$pricing->calculate($r);
 pricingCheck($p['price_before_payment_discount']==='86500.10'&&$p['amount_discount_payment_option']==='4757.51'&&$p['final_price']==='81742.59','cents and fractional percentage');
 pricingCheck($p['diposit_amount']==='200.25','deposit preserved');
 $r['sale_manager_status']='NOT_REQUIRED';pricingCheck($pricing->calculate($r)===$p,'manager not required');
 $r['unit_sale_price']='0.01';$r['discount_promotion']='0';$r['other_discount_allowance']='0';$r['special_discount']='50';
 pricingCheck($pricing->calculate($r)['final_price']==='0.00','half-cent rounding');
 $invalid=[['status','PENDING'],['unit_controller_status','PENDING'],['sale_manager_status','REJECTED'],['deleted_at','2026-10-07'],['to_unit_deposit_request_id',1],['special_discount','100.01'],['discount_promotion','2'],['unit_sale_price',90000.25]];
 foreach($invalid as $case){$q=$r;$q[$case[0]]=$case[1];$blocked=false;try{$pricing->calculate($q);}catch(LogicException $e){$blocked=true;}pricingCheck($blocked,'invalid request blocked');}
 $r['unit_sale_price']='9999999999.99';$r['special_discount']='100';pricingCheck($pricing->calculate($r)['final_price']==='0.00','large value no overflow');
 echo json_encode(['status'=>'PASSED','fractional_discount'=>true,'exact_cents'=>true,'half_up_rounding'=>true,'approval_gates'=>true,'inactive_or_superseded_rejected'=>true,'float_inputs_rejected'=>true,'large_amount_no_overflow'=>true,'writes'=>false],JSON_PRETTY_PRINT)."\n";
}catch(Throwable $e){fwrite(fopen('php://stderr','w'),'Pricing tests failed: '.get_class($e)."\n");exit(1);}
