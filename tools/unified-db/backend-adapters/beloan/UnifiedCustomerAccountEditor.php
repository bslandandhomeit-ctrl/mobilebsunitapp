<?php
namespace App\Services;
class UnifiedCustomerAccountEditor
{
    private $db;
    public function __construct(\Illuminate\Database\Connection $db){if($db->getTablePrefix()!=='')throw new \LogicException('Canonical editor requires no prefix.');$this->db=$db;}
    public static function configured(){
        if(!config('unified_sales.customer_writes_enabled'))throw new \LogicException('Canonical customer edits disabled.');
        config(array('database.connections.unified_customer_writer'=>config('unified_sales.write_database')));
        return new static(\Illuminate\Support\Facades\DB::connection('unified_customer_writer'));
    }
    public function state($id){
        $account=$this->db->table('customer_accounts')->where('id',$id)->first();if(!$account)return null;
        $contact=$this->db->table('customer_account_evidence')->where('account_id',$id)->where('source_table','unified_contact_override')->first();
        return array('account'=>$account,'contact'=>$contact,'version'=>hash('sha256',$account->display_name.'|'.($contact?$contact->source_checksum:'')));
    }
    public function update($id,$name,array $phones,$version,$sourceUser){
        $name=trim($name);if($name===''||mb_strlen($name)>255)throw new \InvalidArgumentException('Account name must contain 1–255 characters.');
        if(count($phones)>10)throw new \InvalidArgumentException('Maximum ten contact numbers.');
        $clean=array();foreach($phones as $phone){$phone=trim($phone);if($phone==='')continue;if(!preg_match('/^\+?[0-9 ()-]{6,30}$/D',$phone))throw new \InvalidArgumentException('Invalid contact number.');$clean[]=$phone;}
        $clean=array_values(array_unique($clean));
        return $this->db->transaction(function()use($id,$name,$clean,$version,$sourceUser){
            $row=$this->db->table('customer_accounts')->where('id',$id)->lockForUpdate()->first();if(!$row)throw new \LogicException('Customer account not found.');
            $before=$this->state($id);if(!hash_equals($before['version'],(string)$version))throw new \LogicException('Customer changed; reload before saving.');
            $actor=$this->db->table('beloan_actor_aliases')->where('source_user_id',$sourceUser)->pluck('actor_id');if(!$actor)throw new \LogicException('Staff actor mapping is missing.');
            $payload=json_encode(array('phone_numbers'=>$clean));$checksum=hash('sha256',$payload);
            if($row->display_name===$name&&$before['contact']&&$before['contact']->source_checksum===$checksum)return false;
            $this->db->table('customer_accounts')->where('id',$id)->update(array('display_name'=>$name));
            $evidence=array('account_id'=>$id,'evidence_kind'=>'CONTACT','ownership_state'=>'ACCOUNT_ONLY','source_payload'=>$payload,'source_checksum'=>$checksum);
            if($before['contact'])$this->db->table('customer_account_evidence')->where('source_table','unified_contact_override')->where('source_id',$id)->update($evidence);
            else $this->db->table('customer_account_evidence')->insert(array('source_table'=>'unified_contact_override','source_id'=>$id)+$evidence);
            $this->db->table('customer_account_change_audit')->insert(array('account_id'=>$id,'actor_id'=>$actor,'before_snapshot'=>json_encode(array('display_name'=>$row->display_name,'contact'=>$before['contact']?$before['contact']->source_payload:null)),
                'after_snapshot'=>json_encode(array('display_name'=>$name,'phone_numbers'=>$clean)),'created_at'=>date('Y-m-d H:i:s')));
            return true;
        });
    }
}
