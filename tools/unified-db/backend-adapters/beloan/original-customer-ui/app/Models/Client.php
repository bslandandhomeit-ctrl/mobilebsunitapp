<?php 
namespace App\Models;

use App\Models\Country\Communes;
use App\Models\Country\Districts;
use App\Models\Country\Provinces;
use App\Models\Country\Villages;
use App\Models\MyModels;
use App\Models\save\SaveDraft;
use App\Models\Country\Countries;

class Client extends MyModels {
  public function save(array $options=array()) {
    if(!config('unified_customer_ui.enabled'))return parent::save($options);
    $table=$this->table;$this->table='customer_profiles';
    try {return \App\Services\OriginalCustomerUi::saveModel($this,$options,$this->getAttributes(),function()use($options){return parent::save($options);});}
    finally {$this->table=$table;}
  }
  protected $table = 'clients';
  public $timestamps = true;
  protected $fillable = array(
    'id',
    'client_type',
    'client_name',
    'client_khmer_name',
    'gender',
    'nationality',
    'birth_date',
    'birth_place',
    'phone1',
    'phone2',
    'address',
    'photo',
    'signature',
    'location_latitude',
    'location_longitude',
    'job',
    'work_place',
    'card_number',
    'card_date',
    'card_issued_by',
    'card_expired_date',
    'letter_type',
    'letter_no',
    'expired_date'
    );
  private $Add_rules = [
        'family_name_en' => 'required',
  ];

  public function clientCBCEmployer(){
    return $this->hasMany('App\Models\ClientCBCEmployer', 'client_id');
  }

  public function loans(){
      return $this->hasMany('App\Models\Loan','client_id');
  }

  public function saveClientInfo($data, $user, $id,$no)
  {
    if($data['draft'] != true) {
      if(is_object($this->Check_validator($data, $this->Add_rules))) return $this->Check_validator($data, $this->Add_rules);
    }

    $clientData = new self();

    if(!empty($id) && $id !=='undefined') {
      $clientData = self::find($id);
      if($data['submit'] == true) {

        $saveDraft = SaveDraft::where('client_id', $id)->first();
        if(!empty($saveDraft)) {

          $saveDraft->where('id', $saveDraft->id)->delete();
        }
      }
    }
    $address = '';
    for($i = 0; $i <= count($data['addr_types'])-1; $i++) {

      if((int)$data['country'][$i] == (int)36 && !empty($data['country'])){ //, 'POST'

        $count = Countries::with(['description'])->where('id', (int)$data['country'][$i])->first();

        $province = Provinces::where('prov_gis', $data['provinces'][$i])->first();
        $district = Districts::where('distr_gis',$data['district'][$i])->first();
        $commune  = Communes::where('comm_gis',$data['commune'][$i])->first();
        $villages = Villages::where('vill_gis',$data['villages'][$i])->first();

        $country = '';
        foreach($count->description as $item){
          if($item->language_id == 2) {
            $country = $item->name_kh;//$item->name;
          }
        }

        // $address = $data['address_en1'][$i].','.
        //     $villages->en_name.','.
        //     $commune->en_name.','.
        //     $district->eng_name.','.
        //     $province->eng_name.','.$country;
        $address = $data['address_en1'][$i].','.
            $villages->kh_name.','.
            $commune->kh_name.','.
            $district->kh_name.','.
            $province->kh_name.','.$country;

      } else {

        $address = ($data['address_en1'][$i]) ? $data['address_en1'][$i]:$data['address_en2'][$i];

      }
    }
    if(config('unified_customer_ui.enabled')){$clientData->if_draft=!empty($data['draft'])?'1':'0';if($clientData->exists)$no=$clientData->cus_acc;}
    $clientData->cus_acc = $no;
    $clientData->client_name = $data['family_name_en'].' '.$data['first_name_en'];
    $clientData->phone1 = ($data['phone_number'][0])?str_replace('-','',$data['phone_number'])[0]:'';
    $clientData->phone2 = ($data['phone_number'][1])?str_replace('-','',$data['phone_number'])[1]:'';
    $clientData->address = $address;
    $clientData->account_cbc_type = $data['account_cbc_type'];
    $clientData->salutation     = $data['salutation'];
    $clientData->resident       = ($data['resident'])?$data['resident']:"N";
    $clientData->education      = $data['education'];
    $clientData->officer_id     = $data['officer_id'];
    $clientData->user_id        = (int)$user->id;
    $clientData->industries_id  = $data['industries'];
    $clientData->latitude       = $data['latitude'];
    $clientData->longitude      = $data['longitude'];
    $clientData->house_own      = $data['house_own'];
    $clientData->family_member_num      = $data['family_member_num'];
    $clientData->active_member_num      = $data['active_member_num'];

    if(!$clientData->save()) {
      return false;
    } else {
      return (int)$clientData->attributes['id'];
    }
  }

  public function general () {
    return $this->hasMany('App\Models\Cbc\ClientCbcGeneral', 'client_id');//->where('id',1); //id is belong to general ID
  }

  public function clientLoan() {

    return $this->hasMany('App\Models\ClientLoanAccounts', 'client_id');
  }
    public function drawdowns() {

    return $this->hasMany('App\Models\DrawdownAccounts', 'client_id');
  }

  public function loan() {

    return $this->hasMany('App\Models\Loan', 'client_id');
  }

  public function Address () {
    return $this->hasMany('App\Models\Cbc\ClientCbcAddress', 'client_id');
  }

  public function Contact () {
    return $this->hasMany('App\Models\Cbc\ClientCbcContact', 'client_id');
  }

  public function Employer () {
    return $this->hasMany('App\Models\Cbc\ClientCbcEmployer', 'client_id');
  }

  public function Identification () {
    return $this->hasMany('App\Models\Cbc\ClientCbcIdentification', 'client_id');
  }

  public function Spouse () {
    return $this->hasMany('App\Models\Cbc\ClientCbcSpouse', 'client_id');
  }

  public function user(){
    return $this->hasOne('App\Models\User','id', 'officer_id');
  }

  public function saveDraft(){

    return self::hasMany('App\Models\save\SaveDraft','client_id');

  }

  public function Industries(){

    return $this->hasOne('App\Models\Industries\Industries', 'client_id');
  }

  public function ClientCbcGeneral(){
    return $this->hasOne('App\Models\Cbc\ClientCbcGeneral','client_id');
  }

  public function ClientCbcIdentifications(){
    return $this->hasOne('App\Models\Cbc\ClientCbcIdentification','client_id');
  }
}
