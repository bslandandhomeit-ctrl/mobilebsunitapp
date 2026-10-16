<?php
/**
 * Created by PhpStorm.
 * User: SOPHEAK
 * Date: 5/25/201000000
 * Time: 5:40 PM
 */

namespace App\Http\Controllers;

use App\Models\Cbc\ClientCbcAddress;
use App\Models\Cbc\ClientCbcContact;
use App\Models\Cbc\ClientCbcEmployer;
use App\Models\Cbc\ClientCbcFamily;
use App\Models\Cbc\ClientCbcGeneral;
use App\Models\Cbc\ClientCbcIdentification;
use App\Models\Cbc\ClientCbcSpouse;
use App\Models\Cbc\EconomicCbcSector;
use App\Models\Client;
use App\Models\ClientLoanAccounts;
use App\Models\Country\Districts;
use App\Models\Country\Provinces;
use App\Models\Currency;
use App\Models\Industries\Industries;
use App\Models\Loan;
use App\Models\Audit;
use App\Models\Role;
use App\Models\save\SaveDraft;
use App\Models\Teller;
use App\Models\Cbc\ClientCbcIdentificationTypes;
use App\Models\User;
use App\Models\Country\Countries;

use Request;
use Auth;
use Image;
use DB;

class ClientController extends Controller
{
    private $_data;

    public function __construct()
    {
        parent::__construct();
        if(config('unified_customer_ui.enabled')) config(array('unified_customer_ui.active'=>true));
        $this->middleware('xss');
        if (!Request::ajax()) {
            $this->middleware('auth');
        }
    }

    public function getClient($id)
    {
        $this->_data['clientData'] = [0];
        if (!empty($id)) {

            $this->_data['clientData'] = Client::with([
                'general' => function ($g) {
                    $g->with(['country', 'country.description', 'province', 'district', 'commune', 'village']);
                }, 'user',
                'Employer.EconomicSector',
                'Employer' => function ($em) {
                    $em->with(['country', 'country.description', 'province', 'District', 'commune', 'Village']);
                },
                'Address' => function ($add) {
                    $add->with(['country', 'country.description', 'province', 'District', 'Commune', 'Village']);
                }, 'Contact', 'Identification', 'Spouse'])->find($id);
            $this->_data['audit'] = Audit::where('tbl_id', $id)->where('user_id', $this->user_id)->where('tbl', 'clients')->with(['audit1', 'audit2'])->first();
        }
        $this->_data['identification'] = ClientCbcIdentificationTypes::where('status', 1)->get();
        $this->_data['economic_sector'] = EconomicCbcSector::all();
        $this->_data['currency'] = Currency::all();
        $this->_data['officer'] = collect(Role::with('users')->where('role_name', 'Credit Officer')->get()->toArray())->lists('users');
        $this->_data['clientDraft'] = SaveDraft::with(['Client.general', 'Client.Identification', 'Client.Contact'])->where('users_id', $this->user_id)->get()->lists('Client');
        $this->_data['Industries'] = Industries::all();
        return $this->view('clients.add', $this->_data);
    }

    public function getCountry()
    {
        return parent::getCountry();
    }

    public function postClient($save_id)
    {
        if(config('unified_customer_ui.enabled') && !config('unified_customer_ui.writes_enabled')) return response()->json(array('result'=>false,'message'=>'Customer write adapter is being verified.'),503);
        if(config('unified_customer_ui.enabled') && (!Auth::check() || !Auth::user()->checkPermission('add_client')))abort(403);
        $customerDb=config('unified_customer_ui.enabled')?(new Client)->getConnection():DB::connection();
        $beforeProfile=array();
        try {
            $customerDb->beginTransaction();
            if(config('unified_customer_ui.enabled') && Request::input('draft_client_id')) {
                if($save_id && (int)Request::input('draft_client_id')!==(int)$save_id)throw new \LogicException('Customer target does not match the edit route.');
                $save_id=(int)Request::input('draft_client_id');
            }
            if(config('unified_customer_ui.enabled') && $save_id){
                Client::where('id',$save_id)->lockForUpdate()->firstOrFail();
                $beforeProfile=\App\Services\OriginalCustomerUi::snapshot($save_id);
                foreach(array('addr_id'=>'client_cbc_address','cnt_id'=>'client_cbc_contact','iden_id'=>'client_cbc_identification')as$field=>$table){
                    foreach((array)Request::input($field)as$rowId){if($rowId && !$customerDb->table($table)->where('id',$rowId)->where('client_id',$save_id)->exists())throw new \LogicException('Related customer record does not belong to this account.');}
                }

                if(!hash_equals(hash('sha256',json_encode($beforeProfile)),(string)Request::input('_unified_customer_version')))throw new \LogicException('Customer changed; reload before saving.');
            }
            $last = isset(Client::latest()->first()->id)?Client::latest()->first()->id:null;
            if(!$last){
                $last=0;
            }
            $last = (int)filter_var($last, FILTER_SANITIZE_NUMBER_INT);
            $no = $last+1;
            $no = 'CU'.$this->getClientNumber($no,7);

            $clientData = Request::except(['_token']);
            $client = new Client();
            $general = new ClientCbcGeneral();
            // $spouse = new ClientCbcSpouse();
            $address = new ClientCbcAddress();
            // $employer = new ClientCbcEmployer();
            $contact = new ClientCbcContact();
            $iden = new ClientCbcIdentification();
            $result = false;
            $user = Auth::user();
            $draft_client_id = isset($clientData['draft_client_id']) ? $clientData['draft_client_id'] : false;
            if (!empty($draft_client_id) && $draft_client_id != false) {
                $save_id = $clientData['draft_client_id'];
            }

            $info = $client->saveClientInfo($clientData, $user, $save_id,$no);
            if ($clientData['draft'] == true) {
                $saveDraft = new SaveDraft();
                $save = $saveDraft->SaveData($info);
                $client_id = $save;
            }
            if (!empty($info) && is_int($info)) {

                $ident_id = $iden->where('client_id', $save_id)->get();
                $Iden = $iden->SaveClientIden($clientData, $info, $ident_id->lists('id'));
                if (!empty($Iden) && is_array($Iden)) {

                    $photo = $this->UploadImageFile('photo', 'data/clients');
                    $signature = $this->UploadImageFile('signature', 'data/signatures');
                    if (is_array($photo) && array_key_exists('error', $photo) && is_array($signature) && array_key_exists('error', $signature)) {
                        $result['error'] = [$photo, $signature];
                    }

                    $general_id = $general->where('client_id', $save_id)->first();

                    $General = $general->SaveClientGeneral($clientData, $info, $photo['uploaded'], $signature['uploaded'], $general_id->id);
                    if (!empty($General) && is_int($General)) {

                        $addres_id = $address->where('client_id', $save_id)->get();
                        $Address = $address->SaveClientAddress($clientData, $info, $addres_id->lists('id'));

                    }
                    if (!empty($Address) && is_array($Address)) {

                        $contact_id = $contact->where('client_id', $save_id)->get();
                        $Contact = $contact->SaveClientContact($clientData, $info, $contact_id->lists('id'));

                    }
                    if (!empty($Contact) && is_array($Contact)) {
                        $result = true;
                    }
                    // if (!empty($Contact) && is_array($Contact)) {

                    //     $spouse_id = $spouse->where('client_id', $save_id)->first();
                    //     $Spouse = $spouse->SaveClientSpouse($clientData, $info, $spouse_id->id);

                    // }
                    // if (!empty($Spouse) && is_int($Spouse)) {

                    //     $employer_id = $employer->where('client_id', $save_id)->get();
                    //     $Employer = $employer->SaveClientEmployer($clientData, $info, $employer_id->lists('id'));
                    //     if (!empty($Employer) && is_array($Employer)) {
                    //         $result = true;
                    //     }

                    // }
                }
            }
            if (is_object($info) || is_object($General)  || is_object($Contact) || is_object($Iden)) {
                $result['error_valide'] = [$info, $General, $Contact, $Iden];
            }
            if ($result == false || array_key_exists('error', $result)) {

                $customerDb->rollback();
                return ['result' => false, $result, 'draft' => Request::input('draft')];

            } else {
                $exist_audit = Audit::where(['tbl' => 'clients', 'tbl_id' => $info, 'user_id' => $this->user_id])->first();
                if (empty($exist_audit) || is_null($exist_audit)) {
                    $this->do_audit($info, $this->user_id, '', 'clients', 0, 'add client');
                }
                if(config('unified_customer_ui.enabled')) \App\Services\OriginalCustomerUi::finish($info,$beforeProfile);
                $customerDb->commit();
                return [
                    $result,
                    'client_id' => ($client_id) ? $client_id : false,
                    'addr_id' => count(Request::input('addr_id')),
                    'Address' => $Address,
                    'Contact' => $Contact,
                    'Iden' => $Iden,
                    // 'Employer' => $Employer,
                    //'employer_id'=>$employer_id->lists('id')
                ];
            }
        } catch (\Exception $e) {
            if(config('unified_customer_ui.enabled')){ $customerDb->rollback(); return response()->json(array('result'=>false,'message'=>$e instanceof \LogicException?$e->getMessage():'Customer could not be saved; no changes committed.'),422); }
            $customerDb->rollback();
        }

    }

    public function listClient()
    {
        if(config('unified_sales.customers_enabled')) {
            $filters=Request::only(array('name','phone','customer_id','sel_offset'));
            return $this->view('clients.unified_list',array('accounts'=>\App\Services\UnifiedCustomersRepository::configured()->listing($filters),'filters'=>$filters));
        }
        $allFields = [
            'name' => Request::input('name'),
            // 'phone' =>Request::input('phone'),
            'phone' => str_replace("-", "", Request::input('phone')),
            'identify_id' => Request::input('identify_id'),
            'customer_id' => Request::input('customer_id'),
            'sel_offset' => Request::input('sel_offset'),
            'status' => Request::get('status')
        ];
        //$offset = ((int)Request::input('sel_offset')) ? (int)Request::input('sel_offset') : 15000;
        $this->_data = Client::with(
            [
                'general',
                'Identification',
                'Contact'
            ]);

        if (Request::has('phone')) {
            // $cont = ClientCbcContact::where('contact_number_number', $allFields['phone'])->get();
            $cont = ClientCbcContact::where('contact_number_number','like','%'.$allFields['phone'].'%')->get();
        }
        if (Request::has('name')) {
            $name = trim(Request::input('name'));
            $general = ClientCbcGeneral::where(function ($w) use ($name) {
                    $w->where(DB::raw("CONCAT(`family_name`, ' ', `first_name`)"), 'LIKE', "%".$name."%")
                    ->orwhere(DB::raw("CONCAT(`family_name_kh`, ' ', `first_name_kh`)"), 'LIKE', "%".$name."%");
            })->get();
        }
        // if (Request::has('customer_id')) {
        //     $client[] = Client::where('id', $allFields['customer_id'])->get();
        // }
        if (Request::has('customer_id')) {
            $client[] = Client::where('cus_acc','like','%'.$allFields['customer_id'].'%')->get();
        }
        if (Request::has('status')) {
            $client[] = Client::where('status', $allFields['status'])->get();
        }
        if (Request::has('identify_id')) {
            $identification = ClientCbcIdentification::where('id_number', $allFields['identify_id'])->get();
        }

        if (!empty($cont) || !empty($general) || !empty($client) || !empty($identification)) {

            $client_id = $this->GetClientID([$cont, $general, $client, $identification]);

            $this->_data->whereIn('id', $client_id);
        }
        $client = $this->_data;
        $offset = $allFields['sel_offset'] ? $allFields['sel_offset'] : 100;
        $client = $client->paginate($offset)->setPath('list?name='.$allFields['name'].'&phone='.$allFields['phone'].'&customer_id='.$allFields['customer_id'].'&identify_id='.$allFields['identify_id'].'&offset'.$offset);
        return $this->view('clients.list_new', ['client_list' => $client, 'fields' => $allFields,'offset'=>$offset]);
    }

    private function GetClientID($data)
    {
        $result = [];
        if (!is_null($data)) {
            foreach ($data as $key => $item) {
                if (!is_null($item)) {
                    if (is_array($item)) {
                        foreach ($item as $arr_vals) {
                            foreach ($arr_vals as $dd) {
                                $result[] = $dd->id;
                            }
                        }
                    } else {
                        foreach ($item as $vals) {
                            $result[] = $vals->client_id;
                        }
                    }
                }
            }
        }
        return array_unique(array_filter($result, function ($value) {
            return $value !== null;
        }));
    }

    public function getDetailN($id)
    {
        if(config('unified_sales.customers_enabled')) {
            $detail=\App\Services\UnifiedCustomersRepository::configured()->detail($id);
            if(!$detail)abort(404);
            return $this->view('clients.unified_detail',$detail);
        }

        if (!empty($id)) {

            $this->_data['client'] = Client::where('id', $id)
                ->with([
                        'clientLoan',
                        'general.country',
                        'general.province',
                        'general.District',
                        'general.Commune',
                        'general.Village',
                        'Address.country.description',
                        'Address.province',
                        'Address.District',
                        'Address.Commune',
                        'Address.Village',
                        'Identification.types',
                        'Employer',
                        'user',
                        'Contact',
                        'Spouse',
                        'loan.payment',
                        'loan.approval',
                        'loan.close',
                        'loan.payoff',
                        'loan.writeoff',
                        'loan.schedule' => function ($query) {
                            $query->orderBy('schedule_date', 'asc');
                        }
                    ]
                )->where('id', $id)->first();
            $this->_data['audit'] = Audit::where('tbl_id', $id)->where('tbl', 'clients')->with(['audit1', 'audit2'])->first();
            $this->_data['employer'] = ClientCbcEmployer::with([
                'country.description',
                'province',
                'District',
                'Commune',
                'Village',
                'currency',
                'EconomicSector'
            ])->where('client_id', $id)->get();
            $this->_data['ID_types'] = ClientCbcIdentificationTypes::all();
        }
        return $this->view('clients.detail_new', $this->_data);
    }

    public function geteditClient($id = 0)
    {
        if(config('unified_sales.customer_writes_enabled')) {
            if(!Auth::user() || !Auth::user()->checkPermission('edit_client'))abort(403);
            $detail=\App\Services\UnifiedCustomersRepository::configured()->detail($id);
            $state=\App\Services\UnifiedCustomerAccountEditor::configured()->state($id);
            if(!$detail || !$state)abort(404);
            return $this->view('clients.unified_edit',$detail+array('version'=>$state['version']));
        }
        if ($this->check_audit($id, 'clients')) return redirect()->back();

        if (is_numeric($id) && $id > 0) {
            $editClient = Client::find($id);
            if (!empty($editClient)) {
                return $this->view('clients.edit', compact('editClient'));
            }
        }
        return redirect()->back();
    }

    public function posteditClient($id = 0)
    {
        if(config('unified_sales.customer_writes_enabled')) {
            if(!Auth::user() || !Auth::user()->checkPermission('edit_client'))abort(403);
            try {
                \App\Services\UnifiedCustomerAccountEditor::configured()->update($id,Request::input('display_name'),(array)Request::input('phone_numbers'),Request::input('version'),Auth::user()->id);
                return redirect()->route('client_detail',array($id))->with('message','Customer account updated.');
            } catch(\InvalidArgumentException $e) {
                return redirect()->back()->with('error',$e->getMessage())->withInput();
            } catch(\LogicException $e) {
                return redirect()->back()->with('error',$e->getMessage())->withInput();
            }
        }
        if (is_numeric($id) && $id > 0) {
            $input = Request::except(['_token', 'photo', 'signature', 'birth_date', 'card_date', 'card_expired_date', 'expired_date']);
            if (!empty($input) && is_array($input)) {
                $addNew = Client::find($id);
                foreach ($input as $key => $value) {
                    $addNew->$key = $value;
                }
                if (Request::hasFile('photo')) {
                    if (Request::file('photo')->isValid()) {
                        if ((Request::file('photo')->getSize() / 1024 / 1024) > 1) {
                            return redirect()->back()->with('error', 'File size is too large!');
                        }
                        $file = Request::file('photo');
                        list($w, $h) = getimagesize($file);
                        if ($w >= 200) {
                            $h = ($h * 200) / $w;
                            $w = 200;
                            if ($h > $w) {
                                $w = ($w * 200) / $h;
                                $h = 200;
                            }
                        } elseif ($h >= 200) {
                            $w = ($w * 200) / $h;
                            $h = 200;
                            if ($w > $h) {
                                $h = ($h * 200) / $w;
                                $w = 200;
                            }
                        }
                        $image = Image::make($file)->resize($w, $h);
                        $photo_name = uniqid(date('dmY')) . '.jpg';
                        $image->save(public_path('data/clients') . '/' . $photo_name);
                        @unlink(public_path('data/clients/' . $addNew->photo));
                        $addNew->photo = $photo_name;
                    }
                }
                if (Request::hasFile('signature')) {
                    if (Request::file('signature')->isValid()) {
                        if ((Request::file('signature')->getSize() / 1024 / 1024) > 1) {
                            return redirect()->back()->with('error', 'File size is too large!');
                        }
                        $file = Request::file('signature');
                        $image = Image::make($file);
                        $photo_name = uniqid(date('dmY')) . '.jpg';
                        $image->save(public_path('data/signatures') . '/' . $photo_name);
                        @unlink(public_path('data/signatures/' . $addNew->signature));
                        $addNew->signature = $photo_name;
                    }
                }
                if (Request::has('birth_date')) {
                    $existDB = Request::input('birth_date');
                    $addNew->birth_date = $existDB;
                }

                if (Request::has('card_date')) {
                    $existDB = Request::input('card_date');
                    $addNew->card_date = $existDB;
                }

                if (Request::has('card_expired_date')) {
                    $existDB = Request::input('card_expired_date');
                    $addNew->card_expired_date = $existDB;
                }

                if (Request::has('expired_date')) {
                    $existDB = Request::input('expired_date');
                    $addNew->expired_date = $existDB;
                }
                if ($addNew->save()) {
                    $this->userActivity(Auth::user()->id, $id, 4, 'Update Client Information');
                    return redirect()->route('client_detail', [$id]);
                }
            }
        }
    }

    public function listClientOld()
    {

        $offset = isset($_GET['offset']) ? $_GET['offset'] : 1;
        $query_arr = array('user_id' => Auth::user()->id, 'branch_id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);

        $B0 = new Client();

        $B0 = $this->getUserByBranch($B0, 'user_id', $query_arr);
        $listClient = $B0->OrderBy('id', 'ASC');

        $name = null;
        $querystringArray = [];
        if (Request::has('name')) {
            $name = Request::input('name');
            $listClient = $listClient->where('client_name', 'like', '%' . $name . '%');
            $querystringArray['name'] = $name;
        }
        $phone = null;
        if (Request::has('phone')) {
            $phone = Request::input('phone');
            $listClient = $listClient->where(function ($query) use ($phone) {
                $query->where('phone1', '=', $phone)->orWhere('phone2', '=', $phone);
            });
            $querystringArray['phone'] = $phone;
        }
        $cardNumber = null;
        if (Request::has('card')) {
            $cardNumber = Request::input('card');
            $listClient = $listClient->where('card_number', 'like', '%' . $cardNumber . '%');
            $querystringArray['card'] = $cardNumber;
        }
        $code = null;
        if (Request::has('code')) {
            $code = Request::input('code');
            $listClient = $listClient->where('id', '=', $code);
            $querystringArray['code'] = $code;
        }

        if (Request::has('status')) {
            $listClient = $listClient->where('status', Request::input('status'));
        }

        $listClient = $listClient->paginate($offset);
        $listClient->appends($querystringArray);

        return $this->view('clients.list', ['listClient' => $listClient, 'name' => $name, 'phone' => $phone, 'cardNumber' => $cardNumber, 'code' => $code, 'offset' => $offset]);
    }

    /**
     *
     * public function getDetail($id)
     * {
     * if ($id > 0) {
     * $client = Client::where('id', '=', $id)->first();
     * if (!empty($client)) {
     * $loan_account = ClientLoanAccounts::where('client_id', '=', $client->id)->first();
     * $loans = Loan::with(['payment', 'approval', 'close', 'payoff', 'writeoff',
     * 'schedule' => function ($query) {
     * $query->orderBy('schedule_date', 'asc');
     * }])
     * ->with('client_loan_account')
     * ->where('client_id', '=', $id)->paginate(1000000);
     *
     * $audit = Audit::where('tbl', 'clients')->where('tbl_id', $id)->orderBy('id', 'asc')->with(['audit1', 'audit2'])->get();
     *
     *
     * return $this->view('clients.detail', ['loans' => $loans, 'client' => $client,
     * 'loan_accounts' => $loan_account,
     * 'audit' => $audit
     * ]);
     * }
     * }
     * return redirect()->back();
     * }
     */
    public function postDisable($id)
    {
        if ($id > 0) {
            $client = Client::find($id);
            if (!empty($client)) {
                $client->status = 0;
                $client->save();
                $this->userActivity(Auth::user()->id, $id, 4, 'Disable Client');
            }
        }
        return redirect()->back();
    }

    public function postEnable($id)
    {
        if ($id > 0) {
            $client = Client::find($id);
            if (!empty($client)) {
                $client->status = 1;
                $client->save();
                $this->userActivity(Auth::user()->id, $id, 4, 'Enable Client');
            }
        }
        return redirect()->back();
    }

    public function check_client()
    {
        if (Request::has('ident_id')) {
            // $data[] = ClientCbcIdentification::select('client_id')->where('id_number', trim(Request::input('ident_id')))->get()->toArray();
        }
        if (Request::has('db')) {
            $data[] = ClientCbcGeneral::select('client_id')->where('date_of_birth', '=', str_replace(['-'], '', Request::input('db')))->get()->toArray();
        }
        if (Request::has('name_en')) {
            $name = Request::input('name_en');
            // if (!empty($name[0])) {
            //     $firstorfamily[] = ClientCbcGeneral::select('client_id')->where('family_name', 'like', '%' . $name[0] . '%')->get()->toArray();
            // }
            // $names = isset($name[1]) ? $name[1] : $name[0];
            $firstorfamily[] = ClientCbcGeneral::select('client_id')->where(DB::raw("CONCAT(`family_name`, ' ', `first_name`)"), 'LIKE', "%".$name."%")->get()->toArray();

            if (count($firstorfamily) > 0) {
                $data[] = $firstorfamily;
            }
        }
        if (Request::has('name_kh')) {
            $name = Request::input('name_kh');
            // if (!empty($name)) {
            //     $FirstNameOrFamily[] = ClientCbcGeneral::select('client_id')->where('family_name_kh', 'like', '%' . $name . '%')->get()->toArray();
            //     // $FirstNameOrFamily[] = ClientCbcGeneral::select('client_id')->where(DB::raw("CONCAT(`nvp`, ' ', `vpv`)"), 'LIKE', "%".Request::input('name_kh')."%")->get()->toArray();
            // }
            // $names = $name;
            // // $FirstNameOrFamily[] = ClientCbcGeneral::select('client_id')->where('first_name_kh', 'like', '%' . $names . '%')->get()->toArray();
            $FirstNameOrFamily[] = ClientCbcGeneral::select('client_id')->where(DB::raw("CONCAT(`family_name_kh`, ' ', `first_name_kh`)"), 'LIKE', "%".$name."%")->get()->toArray();
            if (count($FirstNameOrFamily) > 0) {
                $data[] = $FirstNameOrFamily;
            }
        }
        foreach ($data as $val) {
            if (is_array($val)) {
                foreach ($val as $key => $array) {
                    if (is_array($array)) {
                        foreach ($array as $k => $array1) {
                            $a[] = $array1;
                            if (is_array($array1)) {
                                foreach ($array1 as $k2 => $arr2) {
                                    $a[] = $arr2;
                                }
                            }
                            if (is_object($array1)) {
                                $a[] = $array1;
                            }
                        }
                    }
                    if (is_object($array)) {
                        $a[] = $array;
                    }
                }
            } else if (is_object($val)) {
                $a[] = $val;
            }
        }
        if (!empty($a)) {
            $val = (array_unique($a)) ? array_unique($a) : $data;
            return Client::whereIn('id', $val)->with(['general', 'Identification', 'Contact'])->get();
        } else {
            return [];
        }
    }

    function audit($id)
    {
        $client = Client::find($id);
        if (!empty($client)) {
            $client->status = 1;
            $client->save();
            $this->userActivity(Auth::user()->id, $id, 4, 'Enable Client');
        }

        $this->do_audit($id, '', Auth::user()->id, 'clients', 1, 'Authorized');
        return redirect()->back();
    }

    function slip_deposit($id)
    {

        $data['teller'] = Teller::find($id);
        return $this->view('clients.slip_deposit', $data);
    }

    public function addNewIterms($types)
    {

        if (!empty($types)) {
            $models_types['types'] = trim($types);
        }
        return $this->view('clients.add_new_items', ['models_types' => $models_types]);
    }
}
