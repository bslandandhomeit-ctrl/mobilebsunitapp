<?php
namespace App\Services;
use Illuminate\Database\Query\Expression;
class OriginalCustomerUi {
 public static function core($db,$table){return $db->table(new Expression($table));}
 public static function snapshot($id){$db=(new \App\Models\Client)->getConnection();$rows=array();foreach(array('customer_profiles','client_cbc_general','client_cbc_contact','client_cbc_address','client_cbc_identification','client_cbc_employer','client_cbc_spouse')as$table){$rows[$table]=$db->table($table)->where($table==='customer_profiles'?'id':'client_id',$id)->orderBy('id')->get();}$rows['account']=self::core($db,'customer_accounts')->where('id',$id)->first();return $rows;}
 public static function version($id){return hash('sha256',json_encode(self::snapshot($id)));}
 public static function saveModel($model,array $options,array $attributes,$callback){
  if(!config('unified_customer_ui.writes_enabled'))throw new \LogicException('Unified customer writes disabled.');
  $db=$model->getConnection();return $db->transaction(function()use($model,$db,$attributes,$callback){
   $actor=self::core($db,'beloan_actor_aliases')->where('source_user_id',\Auth::id())->pluck('actor_id');if(!$actor)throw new \LogicException('Staff actor mapping missing.');
   if(!$model->exists){$seq=self::core($db,'beloan_ui_account_sequence')->lockForUpdate()->first();if(!$seq)throw new \LogicException('Account sequence missing.');$model->id=$seq->next_id;$model->cus_acc='CU'.str_pad((string)$model->id,7,'0',STR_PAD_LEFT);self::core($db,'beloan_ui_account_sequence')->update(array('next_id'=>$seq->next_id+1));
    self::core($db,'customer_accounts')->insert(array('id'=>$model->id,'account_number'=>$model->cus_acc,'display_name'=>$model->client_name,'account_type'=>isset($attributes['account_cbc_type'])&&$attributes['account_cbc_type']==='J'?'JOINT':'SINGLE','identity_review_state'=>'PENDING','source_system'=>'beloan_ui','source_id'=>$model->id));
    foreach(array('status'=>0,'officer_id'=>0,'user_id'=>\Auth::id(),'if_draft'=>'0','client_type'=>'','phone1'=>'','phone2'=>'','address'=>'')as$key=>$value){if($model->$key===null)$model->$key=$value;}
   }
   $before=self::core($db,'customer_accounts')->where('id',$model->id)->lockForUpdate()->first();if(!$before)throw new \LogicException('Canonical customer account missing.');
   if(!$callback())throw new \LogicException('Profile save failed.');
   self::core($db,'customer_accounts')->where('id',$model->id)->update(array('display_name'=>$model->client_name,'account_number'=>$model->cus_acc,'identity_review_state'=>'PENDING'));
   self::core($db,'customer_account_change_audit')->insert(array('account_id'=>$model->id,'actor_id'=>$actor,'before_snapshot'=>json_encode($before),'after_snapshot'=>json_encode(array('display_name'=>$model->client_name,'account_number'=>$model->cus_acc)),'created_at'=>date('Y-m-d H:i:s')));return true;
  });
 }
 public static function finish($id,array $before){$db=(new \App\Models\Client)->getConnection();$numbers=array();foreach($db->table('client_cbc_contact')->where('client_id',$id)->get()as$row){if($row->contact_number_number!=='')$numbers[]=$row->contact_number_number;}$payload=json_encode(array('phone_numbers'=>array_values(array_unique($numbers))));$q=self::core($db,'beloan_ui_contact_override')->where('source_table','unified_contact_override')->where('source_id',$id);$data=array('account_id'=>$id,'evidence_kind'=>'CONTACT','ownership_state'=>'ACCOUNT_ONLY','source_payload'=>$payload,'source_checksum'=>hash('sha256',$payload));if($q->first())$q->update($data);else self::core($db,'beloan_ui_contact_override')->insert(array('source_table'=>'unified_contact_override','source_id'=>$id)+$data);
  $actor=self::core($db,'beloan_actor_aliases')->where('source_user_id',\Auth::id())->pluck('actor_id');self::core($db,'customer_account_change_audit')->insert(array('account_id'=>$id,'actor_id'=>$actor,'before_snapshot'=>json_encode($before),'after_snapshot'=>json_encode(self::snapshot($id)),'created_at'=>date('Y-m-d H:i:s')));
 }
}
