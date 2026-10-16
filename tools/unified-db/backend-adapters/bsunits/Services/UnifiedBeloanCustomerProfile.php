<?php
namespace App\Services;
use Illuminate\Database\Connection;
class UnifiedBeloanCustomerProfile {
 private $db;
 public function __construct(Connection $db){$this->db=$db;}
 public function create($account,array $request,$joint){
  if(!$this->db->getSchemaBuilder()->hasTable('beloan_ui_customer_profiles'))return;
  $name=$request['customer_name'];$phones=array_values(array_filter([$request['customer_phone_number']??'',$request['customer_phone_number2']??''],function($v){return trim($v)!=='';}));
  $this->db->table('beloan_ui_customer_profiles')->insert(['id'=>$account,'cus_acc'=>'BS-DP-'.$request['id'],'status'=>0,'account_cbc_type'=>$joint?'J':'S','officer_id'=>0,'user_id'=>0,'if_draft'=>'0','client_name'=>$name,'client_type'=>'','phone1'=>$phones[0]??'','phone2'=>$phones[1]??'','address'=>'']);
  $khmer=preg_match('/\p{Khmer}/u',$name)===1;
  $this->db->table('beloan_ui_client_cbc_general')->insert(['client_id'=>$account,'family_name'=>$name,'first_name'=>'','family_name_kh'=>$khmer?$name:null,'gender'=>(int)$request['customer_gender']===1?'M':'F']);
  foreach($phones as$phone)$this->db->table('beloan_ui_client_cbc_contact')->insert(['client_id'=>$account,'contact_number_number'=>$phone]);
 }
}
