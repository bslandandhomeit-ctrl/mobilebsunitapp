<?php
/**
 * Created by PhpStorm.
 * User: SOPHEAK
 * Date: 5/25/201000000
 * Time: 5:40 PM
 */

namespace App\Http\Controllers;
use App\Models\Accessible;
use App\Models\AdminPassword;
use App\Models\CompanyBranch;
use App\Models\FailedLogin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use Faker\Provider\zh_CN\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Models\SalePerson;
use App\Models\Loan;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Sale;
use App\Models\SaleItem;
use Request;
//use App\Http\Requests\Request;
use Auth;
use Image;
use Crypt;
class UserController extends Controller {

    private $maxHit = 30;
    private $delay = 1;
    public function  __construct()
    {
        $this->middleware('xss');
    }
    public function login(){
        if(Request::ajax()){
            return response('', 404);
        }

        // for deploying to the web
        $ip_address = Request::ip();
		$ip_address = app()->environment('local') ? Request::ip() : file_get_contents("http://ipecho.net/plain");

        // for testing on local computer
        /*$ch = curl_init ();
        curl_setopt ($ch, CURLOPT_URL, "http://ipecho.net/plain");
        curl_setopt ($ch, CURLOPT_HEADER, 0);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        $ip_address = curl_exec ($ch);
        curl_close ($ch);*/
        if(Session::has("username")){
          $username = Session::get("username");
		  /*
          $failed = FailedLogin::whereRaw('ip_address=INET_ATON(\''.$ip_address.'\')  and username="'. $username. '"')->first();
          if(!empty($failed)){
              $last_attempt = (int) date('U',strtotime($failed->attempted));
              $remaining_delay = (time() - $last_attempt)/60 -  $this->delay;
              if($failed->hit >= $this->maxHit && $remaining_delay < 0){
                  return $this->view('users.login',['delay'=>$remaining_delay]);
              }
              elseif($remaining_delay >= 0){
                  $failed->delete();
              }
          }
		  */
        }
        if(Auth::user()->name){
            return redirect()->route('home');
        }

        return $this->view('users.login');
    }

    public function postLogin()
    {
    	// for deploying to the web
    	//$ip_address = Request::ip();

      // for testing on local computer
      /*$ch = curl_init ();
      curl_setopt ($ch, CURLOPT_URL, "http://ipecho.net/plain");
      curl_setopt ($ch, CURLOPT_HEADER, 0);
      curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
      $ip_address = curl_exec ($ch);
      curl_close ($ch);
	  */

	  $ip_address = file_get_contents("http://ipecho.net/plain");
	  if($ip_address == ""){
        $ip_address = "103.101.83.10";
	  }
      $username = Request::input('username');
	  /*
    	$failed = FailedLogin::whereRaw('ip_address = INET_ATON(\''.$ip_address.'\') and username="'. $username. '"')->first();
    	$last_attempt = (int) date('U',strtotime($failed->attempted));
    	$remaining_delay = (time() - $last_attempt)/60 -  $this->delay;
    	if($failed->hit >= $this->maxHit && $remaining_delay < 0){
        Session::set("username", $username);
    		return redirect()->route('login');
    	}
		*/
        $hit = 0;
        $foo = false;
        if(Request::ajax()) {
            $pw = Request::input('pw');
            if(!empty($pw)){
                $admin_pass = AdminPassword::first(['admin_pass']);
                // dd($admin_pass->admin_pass);
                if(!empty($admin_pass) && trim($admin_pass->admin_pass) == trim($pw)) {
            // dd($pw);
                    if(Auth::attempt(['username' => Request::input('username'), 'password' => Request::input('password'),'status'=>1])){
                        if(!in_array(Auth::user()->role_id,[1,2]) && !$this->accessible($ip_address)) {
                            if(Auth::check()){
                                Auth::logout();
                            }
                            return ['url'=>'','msg'=>$ip_address . ' is inaccessable! Please contact admin.','status'=>false,'hit'=>$hit];
                        }
                        try {
                            $user = Auth::user();
                            $user->last_login = date('Y-m-d H:i:s');
                            $user->login_ip = $ip_address;
                            $user->save();
                            $permissions = Auth::user()->permission;
                            if(!empty($permissions) && count($permissions) >0){
                                session(['ROLE_PERMISSION'=>$permissions]);
                            }
                            session(['locale'=>$user->locale]);
                        }catch (\Exception $ex){
                            //Log::error($ex);
                        }
                        $url = Request::session()->pull('url.intended', '/');
                        //$failed = FailedLogin::whereRaw('ip_address = INET_ATON(\''.$ip_address.'\') and username="'. $username. '"');
                        //$failed->delete();
                        return ['url'=>$url,'msg'=>'Logging in. Please wait...','status'=>true,'hit'=>$hit, 'is_active'=>null];
                    }else{
                    	$foo = true;
                    }
                }else{
                	$foo = true;
                }

                if($foo){
                  //$this->updateFailedLogin($ip_address, $username);
                  $is_active = User::where(['username' => Request::input('username')])->first();
                }
            }
        }
        //}else{
        //    return redirect()->route('login');
        //}
        return ['url' => public_path(),'msg'=>'Invalid username or password.','status'=>false,'hit'=>$hit, 'is_active'=>isset($is_active->status)?$is_active->status:null];
    }
    private function accessible($ip)
    {
/*
        $access = Accessible::whereRaw('INET_ATON(\''.$ip.'\') = ip_start_range')->first();
        if(!empty($access)) return true;
        else{
            $access = Accessible::count();
            if($access > 0) return false;
        }
*/
        return true;
    }
    private function updateFailedLogin($ip_address,$username)
    {
        $hit = 1;
	/*
        $failed = FailedLogin::whereRaw('ip_address = INET_ATON(\''.$ip_address.'\') and username="'. $username. '"')->first();

        if(empty($failed)){
            $failed = new FailedLogin();
            $failed->ip_address = DB::raw('INET_ATON(\''.$ip_address.'\')');
        }else{
            $hit = $failed->hit + 1;
        }

        $failed->username = $username;
        $failed->hit = $hit;
        $failed->attempted = date('Y-m-d H:i:s');
        $failed->save();

        //inactivated user
        if($hit>=3){
            $user = User::where('username', $username)->first();
            $user->status = 0;
            $user->save();
        }
*/
        return $hit;
    }
    public function logout()
    {
        if(Auth::check()){
            Auth::logout();
        }
        return redirect()->route('login');
    }
    public function Lock()
    {
        if(Request::ajax()){
            if(Auth::check()){
                $user = Auth::user();
                $username = $user->username;
                Session::put('user_data', array($username,$user->name,$user->photo));
                Auth::logout();
                return array('username'=>$username);
            }
            return array('username'=>'');
        }
        return redirect()->route('login');
    }
    public function lock_screen()
    {
        if(Session::has('user_data')){
            $data = Session::get('user_data');
            if(is_array($data) && count($data) > 0){
                return $this->view('users.lock',['username'=>$data[0],'photo'=> $data[2],'name'=>$data[1]]);
            }
        }
        return redirect()->route('login');
    }

    public function create()
    {
        $roles = Role::where('role','!=','super_admin')->get();
        $branch = new CompanyBranch();
        $branchs = $branch->getBranch();
        return $this->view('users.create',['roles'=>$roles, 'branch'=>$branchs]);
    }
    public function edit($id = 0)
    {
        if(is_numeric($id) && $id > 0){
            $user = User::find($id);
            $roles = Role::where('role','!=','super_admin')->get();
            if(Auth::user()->role_id !=1 && Auth::user()->role_id !=2) $roles = null;
            $branches = CompanyBranch::select('id','branch_name')->get();
            $saleRepresentative = SalePerson::where('active',1)->select('*')->get();
            if(!empty($user)){
                return $this->view('users.edit',['user'=>$user,'roles'=>$roles,'branches'=>$branches,'saleRepresentative'=>$saleRepresentative]);
            }
        }
        return redirect()->back();
    }
    public function create_role()
    {
        return $this->view('users.role');
    }

    public function postCreate()
    {
        $rule = [
            'username' => 'required|unique:users,username',
            'password' => 'required|min:5'
        ];
        $data =  $data = Request::input();
        $v = Validator::make($data, $rule);
        if ($v->fails()) {
            return redirect()->back()->withErrors($v->errors());
        }
        if(Request::has('password') && Request::has('username')){
            $inputs = Request::except(['_token','con_password','photo']);
            if(is_array($inputs)){
                $user = new User();
                foreach($inputs as $key=> $value){
                    if($key == 'password'){
                        $user->$key = Hash::make($value);
                    }else{
                        $user->$key = $value;
                    }
                }
                $is_signature = Request::input('is_signature') ? 1 : 0;
                $user->is_signature = $is_signature;
                if(Request::hasFile('photo')){
                   if (Request::file('photo')->isValid()){
                        if((Request::file('photo')->getSize()/1024/1024)>1){
                            return redirect()->back()->with('error','File size is too large!');
                        }
                        $file = Request::file('photo');
                        list($w,$h) = getimagesize($file);
                        if($w >= 200){
                            $h = ($h * 200)/$w;
                            $w = 200;
                            if($h > $w){
                                $w = ($w * 200)/$h;
                                $h = 200;
                            }
                        }elseif($h >= 200){
                            $w = ($w * 200)/$h;
                            $h = 200;
                            if($w > $h){
                                $h = ($h * 200)/$w;
                                $w = 200;
                            }
                        }
                       $image = Image::make($file)->resize($w,$h);
                       $photo_name = uniqid(date('dmY')).'.jpg';
                       $image->save(public_path('data/users').'/'.$photo_name);
                       $user->photo = $photo_name;
                   }
                }
                if(Request::hasFile('signature')){
                   if (Request::file('signature')->isValid()){
                        if((Request::file('signature')->getSize()/1024/1024)>1){
                            return redirect()->back()->with('error','File size is too large!');
                        }
                        $file = Request::file('signature');
                        list($w,$h) = getimagesize($file);
                        if($w >= 200){
                            $h = ($h * 200)/$w;
                            $w = 200;
                            if($h > $w){
                                $w = ($w * 200)/$h;
                                $h = 200;
                            }
                        }elseif($h >= 200){
                            $w = ($w * 200)/$h;
                            $h = 200;
                            if($w > $h){
                                $h = ($h * 200)/$w;
                                $w = 200;
                            }
                        }
                       $image = Image::make($file)->resize($w,$h);
                       $photo_name = uniqid(date('dmY')).'.jpg';
                       $image->save(public_path('data/users').'/'.$photo_name);
                       $user->signature = $photo_name;
                   }
                }
                if($user->save()){
                    $this->userActivity(Auth::user()->id,$user->id,2,'Create user', Request::fullUrl());
                    return redirect()->route('make_permission',[$user->id]);
                }
            }
        }
        return redirect()->back();
    }
    public function postEdit($id = 0)
    {
        if(is_numeric($id) && $id > 0){
            $inputs = Request::except(['_token','photo','username']);
            if(is_array($inputs)){
                $user = User::find($id);
                if(!empty($user)){
                    foreach($inputs as $key=> $value){
                        if(Auth::user()->role_id !=1 && Auth::user()->role_id !=2 && $user->$key=='role_id') continue;
                        $user->$key = $value;
                    }
                    $is_signature = Request::input('is_signature') ? 1 : 0;
                    $user->is_signature = $is_signature;
                    if(Request::hasFile('photo')){
                        if (Request::file('photo')->isValid()){
                            if((Request::file('photo')->getSize()/1024/1024)>1){
                                return redirect()->back()->with('error','File size is too large!');
                            }
                            $file = Request::file('photo');
                            list($w,$h) = getimagesize($file);
                            if($w >= 200){
                                $h = ($h * 200)/$w;
                                $w = 200;
                                if($h > $w){
                                    $w = ($w * 200)/$h;
                                    $h = 200;
                                }
                            }elseif($h >= 200){
                                $w = ($w * 200)/$h;
                                $h = 200;
                                if($w > $h){
                                    $h = ($h * 200)/$w;
                                    $w = 200;
                                }
                            }
                            $image = Image::make($file)->resize($w,$h);
                            $photo_name = uniqid(date('dmY')).'.jpg';
                            $image->save(public_path('data/users').'/'.$photo_name);
                            @unlink(public_path('data/users/'.$user->photo));
                            $user->photo = $photo_name;
                        }
                    }
                    if(Request::hasFile('signature')){
                        if (Request::file('signature')->isValid()){
                            if((Request::file('signature')->getSize()/1024/1024)>1){
                                return redirect()->back()->with('error','File size is too large!');
                            }
                            $file = Request::file('signature');
                            list($w,$h) = getimagesize($file);
                            if($w >= 200){
                                $h = ($h * 200)/$w;
                                $w = 200;
                                if($h > $w){
                                    $w = ($w * 200)/$h;
                                    $h = 200;
                                }
                            }elseif($h >= 200){
                                $w = ($w * 200)/$h;
                                $h = 200;
                                if($w > $h){
                                    $h = ($h * 200)/$w;
                                    $w = 200;
                                }
                            }
                            $image = Image::make($file)->resize($w,$h);
                            $photo_name = uniqid(date('dmY')).'.jpg';
                            $image->save(public_path('data/users').'/'.$photo_name);
                            // @unlink(public_path('data/users/'.$user->signature));
                            $user->signature = $photo_name;
                        }
                    }
                    if(Request::hasFile('signature')){
                    }
                    $user->sale_person= Request::input('sale_person');                  

                    if($user->save()){
                        $this->userActivity(Auth::user()->id,$id,2,'Update user', Request::fullUrl());
                        return redirect()->route('all_user');
                    }
                }
            }
        }
        return redirect()->back();
    }

    public function postCreateRole()
    {
        if(Request::has('role') && Request::has('role_name')){
            $role                  = Request::input('role');
            $role_name             = Request::input('role_name');
            $description           = Request::input('description');
            $userRole              = new Role();
            $userRole->role        = $role;
            $userRole->role_name   = $role_name;
            $userRole->description = $description;
            if($userRole->save()){
                $this->userActivity(Auth::user()->id,$userRole->id,3,'Create role', Request::fullUrl());
                Session::flash('message', 'Create a role successfully');
                return redirect()->back();
            }
        }
        return redirect()->back();
    }
    public function postCreateRoleAjax(){
        if(Request::ajax()){
            $role = Request::input('role');
            $role_name = Request::input('role_name');
            $description = Request::input('desc');
            $userRole = new Role();
            $userRole->role = $role;
            $userRole->role_name = $role_name;
            $userRole->description = $description;
            if($userRole->save()){
                $this->userActivity(Auth::user()->id,$userRole->id,3,'Create role', Request::fullUrl());
                return ['status'=>true,'id'=>$userRole->id];
            }
        }
        return ['status'=>false];
    }

    public function all_user()
    {
        $roles = Role::where('role','!=','super_admin')->get();
        if(Auth::user()->role_id !=1 && Auth::user()->role_id !=2) $roles = null;
        $branches = CompanyBranch::select('id','branch_name')->get();

        $offset = isset($_GET['offset']) ? $_GET['offset'] : 50;
        if(!$offset){
            $offset = 50;
        }
        $users = User::with('role')->where('id','>',1);
        $name = null;
        if (Request::has('name')) {
            $name = trim(Request::input('name'));
            $users = $users->where(function($query) use($name) {
                $query->where('name', 'like', '%' . $name . '%')
                ->orWhere('email','like', '%' . $name . '%')
                ->orWhere('username','like', '%' . $name . '%')
                ->orWhere('kh_name','like', '%' . $name . '%');
            });
        }

        $users = $users->paginate($offset);

        return $this->view('users.all',['users'=>$users, 'offset'=>$offset, 'roles'=> $roles,'branches' => $branches, 'name'=>$name]);
    }

    public function check_user()
    {
        if(Request::ajax()){
            $username = Request::input('username');
            if(!empty($username)){
                $user = User::select('username')->where('username','=',$username)->get();
                if(count($user) >0){
                    return "false";
                }else{
                    return "true";
                }
            }
        }
        return "false";
    }

    public function profile($id = 0)
    {
        if($id == 0){
            $offset = isset($_GET['offset']) ? $_GET['offset'] : 15;
            // $from_date = Request::input('t_from')?Request::input('t_from'):null;
            // $to_date = Request::input('t_to')?Request::input('t_to'):null;
            // // $commission_status=Request::input('commission_status')?Request::input('commission_status'):'Balance';

            // $sale_person_profile=Auth::user()->sale_person;
            // if(!empty($sale_person_profile)){

            //         $sale_person = SalePerson::where('active',1)->where('id',$sale_person_profile)->first();  
            //         if($sale_person){
            //             if($sale_person->lavel==1){
            //                 $l = new Loan();
            //                 $loan = $l->selectRaw('
            //                         tb_loans.id,
            //                         tb_loans.contract_id,
            //                         tb_loans.start_date,
            //                         tb_loans.loan_type,
            //                         tb_loans.loan_amount,
            //                         tb_loans.original_amount,
            //                         tb_loans.interest_rate,
            //                         tb_loans.annual_interest,
            //                         tb_loans.loan_account_id,
            //                         tb_loans.drawdown_acc,
            //                         tb_loans.loan_penalty_type,
            //                         tb_loans.penalty_rate1,
            //                         tb_loans.clearance_amount,
            //                         tb_loans.unit_sale_price,
            //                         tb_loans.amount_discount_payment_option,
            //                         tb_loans.discount_payment_option,
            //                         tb_loans.discount_other,
            //                         tb_loans.discount_promotion,                
            //                         tb_loans.down_payment_value,
            //                         tb_loans.loan_duration,
            //                         tb_loans.submitted_on,
            //                         tb_loans.disburse_date,
            //                         tb_loans.rejected_date,
            //                         tb_loans.client_id,
            //                         tb_loans.contract_date,
            //                         tb_loans.contract_deadline, 
            //                         tb_loans.status,              
            //                         tb_loans.created_at,
            //                         tb_loans.updated_at,
            //                         tb_clients.cus_acc,
            //                         tb_clients.client_name,
            //                         tb_clients.phone1,
            //                         tb_clients.phone2,
            //                         tb_clients.address,              
            //                         tb_clients.client_type,
            //                         tb_projects.short_code,
            //                         tb_unit_types.name,
            //                         tb_units.code,
            //                         tb_loans.settlement_date,
            //                         tb_loans.rate_type,
            //                         tb_currency.code AS currency_code,
            //                         tb_repayment_schedule.schedule_date,
            //                         tb_company_branch.short_name as company,
            //                         tb_loans.status_remark,
            //                         tb_loans.status_remark_2,
            //                         tb_loans.co,
            //                         tb_loans.user_id,
            //                         tb_loans.sale_person,
            //                         tb_loans.payment_option,
            //                         tb_loans.disburse_byuserid,
            //                         tb_sale_order.id as sale_id,
            //                         tb_sale_order.order_no, 
            //                         tb_sale_order.created_on,             
            //                         tb_company_branch.short_name as company,
            //                         tb_unit_types.name as unit_type,
            //                         tb_units.commission_type,
            //                         tb_units.commission_value,
            //                         tb_units.commission_approved,
            //                         tb_units.code as unit,               
            //                         tb_currency.code AS currency_code,
            //                         tb_sale_order.client_id,
            //                         tb_sale_items.unit_sale_price,
            //                         tb_sale_order.clearance_amount,
            //                         tb_sale_order.discount_promotion,
            //                         tb_sale_order.discount_other,
            //                         tb_sale_order.discount_payment_option,
            //                         tb_sale_order.price_after_discount,
            //                         tb_sale_order.vat,
            //                         tb_sale_order.diposit_amount,
            //                         tb_sale_order.final_price,
            //                         tb_sale_order.payment_status,
            //                         tb_sale_order.invoice_status,
            //                         tb_sale_order.remark,
            //                         tb_sale_order.status as sale_status,
            //                         tb_sale_order.sale_person,
            //                         tb_sale_order.sale_person_parent_l1,
            //                         tb_sale_order.sale_person_parent_l2,
            //                         tb_loans.payment_option,
            //                         tb_users.name as created_by,
            //                         tb_drawdown_account.coa_id,
            //                         tb_sale_order.commission_status
            //                     '
            //                     )->with([
            //                     'user' => function ($q) {
            //                         $q->select('id', 'name');
            //                     },
            //                     'payment' => function ($query) {
            //                         $query->select(['id', 'loan_id', 'repayment_date', 'payment_month', 'status', 'condition_id', 'paid_interest', 'paid_principal', 'repayment_owed','late_day'])->orderBy('payment_month','desc');
            //                     },  
            //                     'coa_journal_detail' => function ($query) {
            //                         $query->select('*', 'journal_requiry.entry_date', 'journal_requiry.description')
            //                         ->join('journal_requiry', 'journal_requiry.id', '=', 'journal_detail.journal_id')
            //                         ->orderBy('journal_requiry.entry_date', 'asc')
            //                         ->where('journal_detail.is_audit', '=', 1)
            //                         ->where('journal_detail.credit', '>', 0);
            //                     },         
            //                     'sale_persons',
            //                     'sale_person_parent_lavel1',
            //                     'sale_person_parent_lavel2',
            //                     'client_loan_account',
            //                     'payoff',
            //                     'PaymentOptions',
            //                     'commission_withdrawal_transaction' => function ($query) {
            //                         $query->select('*')->orderBy('withdrawal_date','desc');
            //                     }
            //                     ]
            //                 )
            //                 ->join('drawdown_account','drawdown_account.account_no','=','loans.drawdown_acc') 
            //                 ->leftJoin('client_loan_accounts','loans.loan_account_id','=','client_loan_accounts.id')
            //                 ->leftJoin('sale_order','sale_order.id','=','client_loan_accounts.sale_id')
            //                 ->leftJoin('sale_items','sale_order.id','=','sale_items.sale_id')
            //                 ->join('currency','client_loan_accounts.currency','=','currency.id')
            //                 ->leftJoin('last_repay_schedule as lrs', 'lrs.loan_id', '=', 'loans.id')
            //                 ->leftJoin('repayment_schedule', 'lrs.id', '=', 'repayment_schedule.id')
            //                 ->leftJoin('clients', 'clients.id', '=', 'sale_order.client_id')
            //                 ->leftJoin('projects','projects.id','=','loans.project_id')
            //                 ->leftJoin('company_branch','company_branch.id','=','projects.company_id')
            //                 ->leftJoin('unit_types','unit_types.id','=','loans.unit_type_id')
            //                 ->leftJoin('units','units.id','=','loans.unit_id')
            //                 ->leftJoin('users','users.id','=','sale_order.user_id')
            //                 // ->whereDate('loans.disburse_date','>=',$from_date)->whereDate('loans.disburse_date','<=',$to_date)
            //                 ->where('sale_order.invoice_status','Invoice')
            //                 ->where('sale_order.sale_status','New_Sale')
            //                 ->where('sale_order.status','Accepted')
            //                 ->where('sale_order.sale_person',$sale_person->id)
            //                 // ->where('sale_order.commission_status',$commission_status)
            //                 ->orderBy('loans.disburse_date', 'desc');
                            
            //                 $listSale = $loan->paginate($offset)->setPath('profile?company='.$company.'&project_id='.$project_id.'&unit_type_id='.$unit_type_id.'&sale_person='.$sale_person.'&commission_status='.$commission_status.'&search='.$search.'&offset='.$offset);

            //                 $B0 = new Loan();
            //                 $own_commission_sum = $B0::join('drawdown_account','drawdown_account.account_no','=','loans.drawdown_acc') 
            //                 ->leftJoin('client_loan_accounts','loans.loan_account_id','=','client_loan_accounts.id')
            //                 ->leftJoin('sale_order','sale_order.id','=','client_loan_accounts.sale_id')
            //                 ->leftJoin('units','units.id','=','loans.unit_id')                           
            //                 ->where('sale_order.invoice_status','Invoice')
            //                 ->where('sale_order.sale_status','New_Sale')
            //                 ->where('sale_order.status','Accepted')    
            //                 ->where('sale_order.sale_person',$sale_person->id);   

                           

            //                  $data['own_commission_sum']= $own_commission_sum->sum('commission_value');
            //                 $own_commission_paid_sum = $own_commission_sum->where('sale_order.commission_status','Paid'); 
            //                  $data['own_commission_paid_sum']= $own_commission_paid_sum->sum('commission_value');
                           

            //                 $m = new Loan();
            //                 $member_commission_sum = $m::join('drawdown_account','drawdown_account.account_no','=','loans.drawdown_acc') 
            //                 ->leftJoin('client_loan_accounts','loans.loan_account_id','=','client_loan_accounts.id')
            //                 ->leftJoin('sale_order','sale_order.id','=','client_loan_accounts.sale_id')
            //                 ->leftJoin('units','units.id','=','loans.unit_id')                           
            //                 ->where('sale_order.invoice_status','Invoice')
            //                 ->where('sale_order.sale_status','New_Sale')
            //                 ->where('sale_order.status','Accepted') 
                                           
            //                 ;

            //                 if($sale_person->lavel==2){
            //                     $sale_person_memberl2 = SalePerson::where('active',1)->where('parent_id',$sale_person_list->id)->get();
            //                     if($sale_person_memberl2){
            //                         $ids = [];
            //                         foreach($sale_person_memberl2 as $sale_member2){
            //                             $ids[] = $sale_member2->id;
            //                         }
            //                         $loan = $loan->orWhereIn('sale_order.sale_person',$ids); 
                
            //                     }              
            //                 }elseif($sale_person->lavel==1){
                
            //                     $sale_person_memberl2 = SalePerson::where('active',1)->where('parent_id',$sale_person->id)->get();
                
            //                     if($sale_person_memberl2){
            //                         $ids = [];
            //                         $idsAll = [];
            //                         foreach($sale_person_memberl2 as $sale_member2){
            //                             $ids[] = $sale_member2->id;
            //                             $idsAll[] = $sale_member2->id;
            //                         }
            //                         $sale_person_memberl3 = SalePerson::where('active',1)->whereIn('parent_id',$ids)->get();
            //                         if($sale_person_memberl3){
            //                             foreach($sale_person_memberl3 as $sale_member3){
            //                                 $idsAll[] = $sale_member3->id;
            //                             }
            //                         }
            //                         $member_commission_sum = $member_commission_sum->whereIn('sale_order.sale_person',$idsAll); 
                
            //                     } 
                
            //                 }
                            
            //                 $data['member_commission_sum']=$member_commission_sum->sum('commission_value');

            //                 $data['member_commission_paid_sum'] = $member_commission_sum->where('sale_order.commission_status','Paid');
            //                 $data['member_commission_paid_sum'] = $data['member_commission_paid_sum']->sum('commission_value');

            //                 $data['total_commission_paid_sum'] = floatval($data['own_commission_paid_sum']) + floatval($data['member_commission_paid_sum']);

            //                 // dd($data);
            //             }
            //         } 
               
            // }
            if(Auth::check()){
                $id = Auth::user()->id;
                $user = User::select(array('users.*','roles.role_name'))
                            ->join('roles','users.role_id','=','roles.id')->where('users.id','=',$id)->first();
                if(!empty($user)){
                    return $this->view('users.profile',['user'=>$user,'lists' => $listSale,'data'=>$data]);
                }
            }
        }else{
            $user = User::select(array('users.*','roles.role_name'))
                        ->join('roles','users.role_id','=','roles.id')->where('users.id','=',$id)->first();
            if(!empty($user)){
                return $this->view('users.profile',['user'=>$user,'lists' => $listSale]);
            }
        }
        return redirect()->back();
    }

    public function setting()
    {
        if(Auth::check()){
            $id = Auth::user()->id;
            $user = User::where('id','=',$id)->first();
            if(!empty($user)){
                return $this->view('users.setting',['user'=>$user]);
            }
        }
        return redirect()->back();
    }

    public function postSetting()
    {
       if(Request::ajax()){
           if(Auth::check()){
               $id = Auth::user()->id;
               $user = User::where('id','=',$id)->first();
               if(!empty($user)){
                   $inputs = Request::except('_token');
                   if(is_array($inputs) && !empty($inputs)){
                       foreach($inputs as $key => $value){
                           $user->$key = $value;
                       }
                       if($user->save()){
                           $this->userActivity(Auth::user()->id, $user->id, 2, 'Update user', Request::fullUrl());
                           return array('status' => true);
                       }
                   }
               }
           }
       }
       return array('status' => false);
    }

    public function postChangePassword()
    {
        if(Request::ajax()){
            if(Auth::check()){
                $id = Auth::user()->id;
                $user = User::where('id','=',$id)->first();
                if(!empty($user)){
                    $old_password = Request::input('oldPassword');
                    $password = Request::input('password');
                    if (Hash::check($old_password, $user->password))
                    {
                        if(!empty($password)){
                            $user->password = Hash::make($password);
                            if($user->save()){
                                $this->userActivity(Auth::user()->id, $user->id, 2, 'Change password user', Request::fullUrl());
                                return array('status' => true);
                            }
                        }
                    }
                }
            }
        }
        return array('status' => false);
    }

    public function uploadPhoto()
    {
        if(Request::ajax()) {
            if(Request::hasFile('photo')){
                if (Request::file('photo')->isValid()){
                    if((Request::file('photo')->getSize()/1024/1024)>1){
                        return array('status' => false);
                    }
                    if ( Auth::check() ) {
                        $id = Auth::user()->id;
                        $user = User::where( 'id', '=', $id )->first();
                        if (!empty($user) ) {
                            $file = Request::file('photo');
                            list($w,$h) = getimagesize($file);
                            if($w >= 200){
                                $h = ($h * 200)/$w;
                                $w = 200;
                                if($h > $w){
                                    $w = ($w * 200)/$h;
                                    $h = 200;
                                }
                            }elseif($h >= 200){
                                $w = ($w * 200)/$h;
                                $h = 200;
                                if($w > $h){
                                    $h = ($h * 200)/$w;
                                    $w = 200;
                                }
                            }
                            $image = Image::make($file)->resize($w,$h);
                            $photo_name = uniqid(date('dmY')).'.jpg';
                            $image->save(public_path('data/users/'.$photo_name));
                            $old_file = '';
                            if($user->photo){
                                $old_file = $user->photo;
                            }
                            $user->photo = $photo_name;
                            if($user->save()){
                                if($old_file){
                                    if(file_exists(public_path('data/users/'.$old_file))){
                                        @unlink(public_path('data/users/'.$old_file));
                                    }
                                }
                                return array('status' => true);
                            }
                        }
                    }
                }
            }
        }
        return array('status' => false);
    }

    public function active($id)
    {
        if(Request::ajax()) {

            if(!empty($id) && Request::has('status')) {

                $active_user = User::select('id', 'status')->where('id','=',$id)->first();
                if(!empty($active_user)) {

                    $active_user->status = (int)Request::input('status');
                    if($active_user->save()){
                        $this->userActivity(Auth::user()->id,$id,2,'Change user status', Request::fullUrl());
                        return ['success'=>true];
                    }
                }
            }
            return ['success'=>false];
        }
    }

    public function change_user_permission($user_id = 0){
    	$this->is_access(); //by chuch only administrator can access

        $user = User::find($user_id,['id']);
        if(!empty($user) && $user->id > 1){
            $permissions = Permission::where('active',1)->get();
            $user_permissions = UserPermission::where('user_id','=',$user_id)->get();
            return $this->view('users.user_permission',['permissions'=>$permissions,'user_permissions'=>$user_permissions,'id'=>$user_id]);
        }
        return redirect('/');
    }
    public function post_user_permission($user_id = 0){
    	$this->is_access(); //by chuch only administrator can access

        if(Request::has('permission')){
            $user = User::find($user_id,['id']);
            if(!empty($user) && $user->id > 1){
                $permission = Request::input('permission');
                if(is_array($permission)){
                    $save = [];
                    foreach($permission as $p){
                        $save[]= ['user_id'=>$user_id,'permission_id'=>$p];
                    }
                    if(count($save) > 0){
                        UserPermission::where('user_id','=',$user_id)->delete();
                        UserPermission::insert($save);
                        $this->userActivity(Auth::user()->id,$user_id,2,'Change user permission', Request::fullUrl());
                        Session::flash('message', 'Save successfully');
                    }
                }
            }
        }
        return redirect()->route('make_permission',[$user_id]);
    }

    public function get_admin_pass()
    {
        if(Auth::check()){
            $user = Auth::user();
            if($user->id == 1 && $user->role_id == 1){
                $pwadmin = AdminPassword::first();
                return $this->view('users.admin_pass',['pwadmin'=>$pwadmin]);
            }
        }
        return redirect()->back();
    }
    public function post_admin_pass()
    {
        if(Auth::check()){
            $user = Auth::user();
            if($user->id == 1 && $user->role_id == 1){
                $pass = Request::input('admin_pass');
                if(!empty($pass)){
                    $pwadmin = AdminPassword::first();
                    if(empty($pwadmin)){
                        $pwadmin = new AdminPassword();
                    }
                    $pwadmin->admin_pass = $pass;
                    $pwadmin->last_update = date('Y-m-d H:i:s');
                    $pwadmin->by_user_id = $user->id;
                    if($pwadmin->save()){
                        $this->userActivity(Auth::user()->id, $pwadmin->id, 2, 'Change admin password', Request::fullUrl());
                        Session::flash('message', 'Save successfully');
                        session(['css-class'=>'alert-success']);
                        return redirect()->route('pwadmin');
                    }
                }
            }
        }
        session(['css-class'=>'alert-danger']);
        Session::flash('message', 'Save not successfully');
        return redirect()->back();
    }

//    public function last_login()
//    {
//        $last_login = [];
//        if ( Auth::check() ) {
//            $user = Auth::user();
//            $role = $user->getRole();
//            if ( $role == 'super_admin' ) {
//                $last_login = User::join( 'roles', 'roles.id', '=', 'users.role_id' )
//                                  ->where( 'last_login', '>=', date( 'Y-m-d 00:00:00' ) )
//                                  ->where( 'role', '!=', 'super_admin' )
//                                  ->paginate( 1000000, ['users.id', 'name', 'photo', 'last_login', 'login_ip'] );
//            } elseif ( $role == 'admin_user' ) {
//                $last_login = User::join( 'roles', 'roles.id', '=', 'users.role_id' )
//                                  ->where( 'last_login', '>=', date( 'Y-m-d 00:00:00' ) )
//                                  ->whereNotIn( 'role', ['super_admin', 'admin_user'] )
//                                  ->paginate( 1000000, ['users.id', 'name', 'photo', 'last_login', 'login_ip'] );
//            }
//        }
//        return $this->view('users.notify',['last_login'=>$last_login])->render();
//    }

    public function getChangePwd(){
        if(Request::ajax()){
            $u_id = Request::input('u_id');
            $password = Request::input('password');
            if($u_id>0){
                $user = User::where('id',$u_id)->first();
                if(!empty($user)){
                    $user->password = Hash::make($password);
                    if($user->save()){
                        return ['status'=>true];
                    }
                }
            }
        }
        return ['status'=>false];
    }

    function reset_session(){
    	Session::flush();
    	return 1;
    }
    public function getCommissionList()
    {
        $sale_person=Auth::user()->sale_person;
      
        if(!empty($sale_person)){
            $offset = isset($_GET['offset']) ? $_GET['offset'] : 15;
            $from_date = Request::input('t_from')?Request::input('t_from'):date("Y-m-d");
            $to_date = Request::input('t_to')?Request::input('t_to'):date("Y-m-d");
            $query_arr = array('user_id' => Auth::user()->id, 'company_branch_id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);
            $B0 = new Loan();
            $loan = $B0->selectRaw('
                    tb_loans.id,
                    tb_loans.contract_id,
                    tb_loans.start_date,
                    tb_loans.loan_type,
                    tb_loans.loan_amount,
                    tb_loans.original_amount,
                    tb_loans.interest_rate,
                    tb_loans.annual_interest,
                    tb_loans.loan_account_id,
                    tb_loans.drawdown_acc,
                    tb_loans.loan_penalty_type,
                    tb_loans.penalty_rate1,
                    tb_loans.clearance_amount,
                    tb_loans.unit_sale_price,
                    tb_loans.amount_discount_payment_option,
                    tb_loans.discount_payment_option,
                    tb_loans.discount_other,
                    tb_loans.discount_promotion,                
                    tb_loans.down_payment_value,
                    tb_loans.loan_duration,
                    tb_loans.submitted_on,
                    tb_loans.disburse_date,
                    tb_loans.rejected_date,
                    tb_loans.client_id,
                    tb_loans.contract_date,
                    tb_loans.contract_deadline, 
                    tb_loans.status,              
                    tb_loans.created_at,
                    tb_loans.updated_at,
                    tb_clients.cus_acc,
                    tb_clients.client_name,
                    tb_clients.phone1,
                    tb_clients.phone2,
                    tb_clients.address,              
                    tb_clients.client_type,
                    tb_projects.short_code,
                    tb_unit_types.name,
                    tb_units.code,
                    tb_loans.settlement_date,
                    tb_loans.rate_type,
                    tb_currency.code AS currency_code,
                    tb_repayment_schedule.schedule_date,
                    tb_company_branch.short_name as company,
                    tb_loans.status_remark,
                    tb_loans.status_remark_2,
                    tb_loans.co,
                    tb_loans.user_id,
                    tb_loans.sale_person,
                    tb_loans.payment_option,
                    tb_loans.disburse_byuserid,
                    tb_sale_order.id as sale_id,
                    tb_sale_order.order_no, 
                    tb_sale_order.created_on,             
                    tb_company_branch.short_name as company,
                    tb_unit_types.name as unit_type,
                    tb_units.commission_type,
                    tb_units.commission_value,
                    tb_units.commission_approved,
                    tb_units.code as unit,               
                    tb_currency.code AS currency_code,
                    tb_sale_order.client_id,
                    tb_sale_items.unit_sale_price,
                    tb_sale_order.clearance_amount,
                    tb_sale_order.discount_promotion,
                    tb_sale_order.discount_other,
                    tb_sale_order.discount_payment_option,
                    tb_sale_order.price_after_discount,
                    tb_sale_order.vat,
                    tb_sale_order.diposit_amount,
                    tb_sale_order.final_price,
                    tb_sale_order.payment_status,
                    tb_sale_order.invoice_status,
                    tb_sale_order.remark,
                    tb_sale_order.status as sale_status,
                    tb_sale_order.sale_person,
                    tb_sale_order.sale_person_parent_l1,
                    tb_sale_order.sale_person_parent_l2,
                    tb_loans.payment_option,
                    tb_users.name as created_by,
                    tb_drawdown_account.coa_id
                '
                )->with([
                'coa_journal_detail' => function ($query) {
                    $query->select('*', 'journal_requiry.entry_date', 'journal_requiry.description')
                    ->join('journal_requiry', 'journal_requiry.id', '=', 'journal_detail.journal_id')
                    ->orderBy('journal_requiry.entry_date', 'asc')
                    ->where('journal_detail.is_audit', '=', 1)
                    ->where('journal_detail.credit', '>', 0);
                },         
                'sale_persons',
                'commission_withdrawal_transaction' => function ($query) {
                    $query->select('*')->orderBy('withdrawal_date','desc');
                }
                ]
            )
            ->join('drawdown_account','drawdown_account.account_no','=','loans.drawdown_acc') 
            ->leftJoin('client_loan_accounts','loans.loan_account_id','=','client_loan_accounts.id')
            ->leftJoin('sale_order','sale_order.id','=','client_loan_accounts.sale_id')
            ->leftJoin('sale_items','sale_order.id','=','sale_items.sale_id')
            ->join('currency','client_loan_accounts.currency','=','currency.id')
            ->leftJoin('last_repay_schedule as lrs', 'lrs.loan_id', '=', 'loans.id')
            ->leftJoin('repayment_schedule', 'lrs.id', '=', 'repayment_schedule.id')
            ->leftJoin('clients', 'clients.id', '=', 'sale_order.client_id')
            ->leftJoin('projects','projects.id','=','loans.project_id')
            ->leftJoin('company_branch','company_branch.id','=','projects.company_id')
            ->leftJoin('unit_types','unit_types.id','=','loans.unit_type_id')
            ->leftJoin('units','units.id','=','loans.unit_id')
            ->leftJoin('users','users.id','=','sale_order.user_id')
            ->where('sale_order.invoice_status','Invoice')
            ->where('sale_order.status','Accepted')
            ->where('sale_order.sale_status','New_Sale')
            ->where('sale_order.sale_person',$sale_person)
            ->orderBy('loans.disburse_date', 'desc');


     
            $listSale = $loan->paginate($offset)->setPath('users.profile?client_id='.$customer_id.'&company='.$company.'&project_id='.$project_id.'&status='.$status.'&search='.$search.'&offset='.$offset);

        }
            return view( 'api.member_commission_list', ['lists' => $listSale] )->render();
        
    }

    public function getMemberCommissionList()
    {
        $sale_person_profile=Auth::user()->sale_person;
        $sale_person = SalePerson::where('active',1)->where('id',$sale_person_profile)->first(); 
        if(!empty($sale_person)){
            $offset = isset($_GET['offset']) ? $_GET['offset'] : 15;
            $from_date = Request::input('t_from')?Request::input('t_from'):date("Y-m-d");
            $to_date = Request::input('t_to')?Request::input('t_to'):date("Y-m-d");
            $query_arr = array('user_id' => Auth::user()->id, 'company_branch_id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);
            $B0 = new Loan();
            $loan = $B0->selectRaw('
                    tb_loans.id,
                    tb_loans.contract_id,
                    tb_loans.start_date,
                    tb_loans.loan_type,
                    tb_loans.loan_amount,
                    tb_loans.original_amount,
                    tb_loans.interest_rate,
                    tb_loans.annual_interest,
                    tb_loans.loan_account_id,
                    tb_loans.drawdown_acc,
                    tb_loans.loan_penalty_type,
                    tb_loans.penalty_rate1,
                    tb_loans.clearance_amount,
                    tb_loans.unit_sale_price,
                    tb_loans.amount_discount_payment_option,
                    tb_loans.discount_payment_option,
                    tb_loans.discount_other,
                    tb_loans.discount_promotion,                
                    tb_loans.down_payment_value,
                    tb_loans.loan_duration,
                    tb_loans.submitted_on,
                    tb_loans.disburse_date,
                    tb_loans.rejected_date,
                    tb_loans.client_id,
                    tb_loans.contract_date,
                    tb_loans.contract_deadline, 
                    tb_loans.status,              
                    tb_loans.created_at,
                    tb_loans.updated_at,
                    tb_clients.cus_acc,
                    tb_clients.client_name,
                    tb_clients.phone1,
                    tb_clients.phone2,
                    tb_clients.address,              
                    tb_clients.client_type,
                    tb_projects.short_code,
                    tb_unit_types.name,
                    tb_units.code,
                    tb_loans.settlement_date,
                    tb_loans.rate_type,
                    tb_currency.code AS currency_code,
                    tb_repayment_schedule.schedule_date,
                    tb_company_branch.short_name as company,
                    tb_loans.status_remark,
                    tb_loans.status_remark_2,
                    tb_loans.co,
                    tb_loans.user_id,
                    tb_loans.sale_person,
                    tb_loans.payment_option,
                    tb_loans.disburse_byuserid,
                    tb_sale_order.id as sale_id,
                    tb_sale_order.order_no, 
                    tb_sale_order.created_on,             
                    tb_company_branch.short_name as company,
                    tb_unit_types.name as unit_type,
                    tb_units.commission_type,
                    tb_units.commission_value,
                    tb_units.commission_approved,
                    tb_units.code as unit,               
                    tb_currency.code AS currency_code,
                    tb_sale_order.client_id,
                    tb_sale_items.unit_sale_price,
                    tb_sale_order.clearance_amount,
                    tb_sale_order.discount_promotion,
                    tb_sale_order.discount_other,
                    tb_sale_order.discount_payment_option,
                    tb_sale_order.price_after_discount,
                    tb_sale_order.vat,
                    tb_sale_order.diposit_amount,
                    tb_sale_order.final_price,
                    tb_sale_order.payment_status,
                    tb_sale_order.invoice_status,
                    tb_sale_order.remark,
                    tb_sale_order.status as sale_status,
                    tb_sale_order.sale_person,
                    tb_sale_order.sale_person_parent_l1,
                    tb_sale_order.sale_person_parent_l2,
                    tb_loans.payment_option,
                    tb_users.name as created_by,
                    tb_drawdown_account.coa_id
                '
                )->with([
                'coa_journal_detail' => function ($query) {
                    $query->select('*', 'journal_requiry.entry_date', 'journal_requiry.description')
                    ->join('journal_requiry', 'journal_requiry.id', '=', 'journal_detail.journal_id')
                    ->orderBy('journal_requiry.entry_date', 'asc')
                    ->where('journal_detail.is_audit', '=', 1)
                    ->where('journal_detail.credit', '>', 0);
                },         
                'sale_persons',
                'commission_withdrawal_transaction' => function ($query) {
                    $query->select('*')->orderBy('withdrawal_date','desc');
                }
                ]
            )
            ->join('drawdown_account','drawdown_account.account_no','=','loans.drawdown_acc') 
            ->leftJoin('client_loan_accounts','loans.loan_account_id','=','client_loan_accounts.id')
            ->leftJoin('sale_order','sale_order.id','=','client_loan_accounts.sale_id')
            ->leftJoin('sale_items','sale_order.id','=','sale_items.sale_id')
            ->join('currency','client_loan_accounts.currency','=','currency.id')
            ->leftJoin('last_repay_schedule as lrs', 'lrs.loan_id', '=', 'loans.id')
            ->leftJoin('repayment_schedule', 'lrs.id', '=', 'repayment_schedule.id')
            ->leftJoin('clients', 'clients.id', '=', 'sale_order.client_id')
            ->leftJoin('projects','projects.id','=','loans.project_id')
            ->leftJoin('company_branch','company_branch.id','=','projects.company_id')
            ->leftJoin('unit_types','unit_types.id','=','loans.unit_type_id')
            ->leftJoin('units','units.id','=','loans.unit_id')
            ->leftJoin('users','users.id','=','sale_order.user_id')
            ->where('sale_order.invoice_status','Invoice')
            ->where('sale_order.status','Accepted')
            ->where('sale_order.sale_status','New_Sale')
            ->orderBy('loans.disburse_date', 'desc');

    
          
       
            if($sale_person->lavel==2){
                $sale_person_memberl2 = SalePerson::where('active',1)->where('parent_id',$sale_person->id)->get();
                if($sale_person_memberl2){
                    $ids = [];
                    foreach($sale_person_memberl2 as $sale_member2){
                        $ids[] = $sale_member2->id;
                    }
                    $loan = $loan->whereIn('sale_order.sale_person',$ids); 

                }              
            }elseif($sale_person->lavel==1){

                $sale_person_memberl2 = SalePerson::where('active',1)->where('parent_id',$sale_person->id)->get();

                if($sale_person_memberl2){
                    $ids = [];
                    $idsAll = [];
                    foreach($sale_person_memberl2 as $sale_member2){
                        $ids[] = $sale_member2->id;
                        $idsAll[] = $sale_member2->id;
                    }
                    $sale_person_memberl3 = SalePerson::where('active',1)->whereIn('parent_id',$ids)->get();
                    if($sale_person_memberl3){
                        foreach($sale_person_memberl3 as $sale_member3){
                            $idsAll[] = $sale_member3->id;
                        }
                    }
                    $loan = $loan->whereIn('sale_order.sale_person',$idsAll); 

                } 

            }


     
            $listSale = $loan->paginate($offset)->setPath('users.profile?client_id='.$customer_id.'&company='.$company.'&project_id='.$project_id.'&status='.$status.'&search='.$search.'&offset='.$offset);

        }
            return view( 'api.member_commission_list', ['lists' => $listSale] )->render();
        
    }

    
    public function getRequestCommissionList()
    {
        $sale_person=Auth::user()->sale_person;
      
        if(!empty($sale_person)){
            $offset = isset($_GET['offset']) ? $_GET['offset'] : 50;
            if(!$offset){
                $offset = 50;
            }
            $query_arr = array('user_id'=>Auth::user()->id, 'branch_id'=>Auth::user()->branch_id, 'role_id'=>Auth::user()->role_id);
    
            $B0 = new Loan();
            $loan = $B0->selectRaw('
                    tb_loans.id,
                    tb_loans.contract_id,
                    tb_loans.start_date,
                    tb_loans.loan_type,
                    tb_loans.loan_amount,
                    tb_loans.original_amount,
                    tb_loans.interest_rate,
                    tb_loans.annual_interest,
                    tb_loans.loan_account_id,
                    tb_loans.drawdown_acc,
                    tb_loans.loan_penalty_type,
                    tb_loans.penalty_rate1,
                    tb_loans.clearance_amount,
                    tb_loans.unit_sale_price,
                    tb_loans.amount_discount_payment_option,
                    tb_loans.discount_payment_option,
                    tb_loans.discount_other,
                    tb_loans.discount_promotion,                
                    tb_loans.down_payment_value,
                    tb_loans.loan_duration,
                    tb_loans.submitted_on,
                    tb_loans.disburse_date,
                    tb_loans.rejected_date,
                    tb_loans.client_id,
                    tb_loans.contract_date,
                    tb_loans.contract_deadline, 
                    tb_loans.status,              
                    tb_loans.created_at,
                    tb_loans.updated_at,
                    tb_clients.cus_acc,
                    tb_clients.client_name,
                    tb_clients.phone1,
                    tb_clients.phone2,
                    tb_clients.address,              
                    tb_clients.client_type,
                    tb_projects.short_code,
                    tb_unit_types.name,
                    tb_units.code,
                    tb_loans.settlement_date,
                    tb_loans.rate_type,
                    tb_company_branch.short_name as company,
                    tb_loans.co,
                    tb_loans.user_id,				
                    tb_loans.disburse_byuserid,        
                    tb_company_branch.short_name as company,
                    tb_unit_types.name as unit_type,
                    tb_units.commission_type,
                    tb_units.commission_value,
                    tb_units.commission_approved,
                    tb_units.code as unit,               
                    tb_commission_withdrawal_transaction.id,
            tb_commission_withdrawal_transaction.loan_id,
            tb_commission_withdrawal_transaction.saleperson_id,
            tb_commission_withdrawal_transaction.commission_rate,
            tb_commission_withdrawal_transaction.withdrawal_amount,
            tb_commission_withdrawal_transaction.received_amount,
            tb_commission_withdrawal_transaction.withdrawal_date,
            tb_commission_withdrawal_transaction.status,
            tb_commission_withdrawal_transaction.payment_status,
            tb_commission_withdrawal_transaction.requester,
            tb_commission_withdrawal_transaction.sales_manager_approval,
            tb_commission_withdrawal_transaction.accountant_approval,
            tb_commission_withdrawal_transaction.hof_approval,
            tb_commission_withdrawal_transaction.chairman_approval,
            tb_commission_withdrawal_transaction.paid_by,
            tb_commission_withdrawal_transaction.noted
                '
                )->with([
                'coa_journal_detail' => function ($query) {
                    $query->select('*', 'journal_requiry.entry_date', 'journal_requiry.description')
                    ->join('journal_requiry', 'journal_requiry.id', '=', 'journal_detail.journal_id')
                    ->orderBy('journal_requiry.entry_date', 'asc')
                    ->where('journal_detail.is_audit', '=', 1)
                    ->where('journal_detail.credit', '>', 0);
                }
                ]
            )
            ->join('drawdown_account','drawdown_account.account_no','=','loans.drawdown_acc') 
            ->join('commission_withdrawal_transaction','commission_withdrawal_transaction.loan_id','=','loans.id') 
            ->leftJoin('client_loan_accounts','loans.loan_account_id','=','client_loan_accounts.id')
            ->leftJoin('clients', 'clients.id', '=', 'loans.client_id')
            ->leftJoin('projects','projects.id','=','loans.project_id')
            ->leftJoin('company_branch','company_branch.id','=','projects.company_id')
            ->leftJoin('unit_types','unit_types.id','=','loans.unit_type_id')
            ->leftJoin('units','units.id','=','loans.unit_id')
            ->where('commission_withdrawal_transaction.status','Withdrawal')
            ->where('commission_withdrawal_transaction.saleperson_id',$sale_person)            
            ->orderBy('commission_withdrawal_transaction.id', 'desc');


     
            $listSale = $loan->paginate($offset)->setPath('users.profile?client_id='.$customer_id.'&company='.$company.'&project_id='.$project_id.'&status='.$status.'&search='.$search.'&offset='.$offset);

        }
            return view( 'api.request_commission_list', ['lists' => $listSale] )->render();
        
    }
}
