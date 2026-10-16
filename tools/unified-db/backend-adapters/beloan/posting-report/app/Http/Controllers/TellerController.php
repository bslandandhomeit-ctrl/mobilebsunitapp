<?php
/**
 * Created by PhpStorm.
 * User: SOPHEAK Heng
 * Date: 5/25/2015
 * Time: 5:40 PM
 */

namespace App\Http\Controllers;

use App\Http\Controllers\PrintController;
use App\Models\Cbc\Client;
use App\Models\Client as Clients;
use App\Models\CoaCategory;
use App\Models\Currency;
use App\Models\Loan;
use App\Models\Audit;
//use App\Models\Notification;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Models\DrawdownAccounts;
use App\Models\JournalRequiry;
use App\Models\TransactionsRequiry;
use App\Models\TransactionsPosting;
use App\Models\JournalDetail;
use App\Models\Teller;
use App\Models\Project;
use App\Models\TillTransaction;
use App\Models\CompanyBranch;
use App\Models\UserPermission;
use App\Models\UserRoles;
use App\Models\RepaymentSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Auth\Guard;
use League\Flysystem\Exception;
use App\Models\slips;
use App\Models\Unit;
use App\Models\LoanPayments;
use App\Models\ClientLoanAccounts;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\TillTransactionReject;
use LoanCalculate;
//use Illuminate\Database\ConnectionInterface;
//use Illuminate\Database\MySqlConnection;
use Request;
use Auth;
use Image;

//use Symfony\Component\Security\Core\User\User;

class TellerController extends Controller
{
    protected $tillType;
    private $_return = false;
    private $_till_stat = false;
    private $_isLogin = false;
    private $_branch_id = false;
    public $printServices;

    public function __construct()
    {
        parent::__construct();
        $this->middleware('xss');
        //$this->middleware('auth');
        $this->_till_stat = $this->Till_status();
        $this->_isLogin = auth()->check();
        $this->tillType = $this->CheckPermId_from_session();//true is chief false is teller
        $this->_branch_id = auth()->user()->branch_id;
        $this->user_id = Auth::user()->id;
        $this->signature = Auth::user()->signature;
        $this->is_signature = Auth::user()->is_signature;
        $this->role = Auth::user()->role->role;
    }

    private function checkForm()
    {
        $res = false;
        $rule = [

            'account_no' => 'required',
            'account_name' => 'required',
            'branch_id' => 'required',
            'min_balance' => 'required',
            'max_balance' => 'required',
            'created_by' => 'required',
        ];
        $data = [
            'account_no' => Request::input('accNo'),
            'account_name' => Request::input('accName'),
            'branch_id' => Request::input('branchs'),
            'min_balance' => Request::input('minBalance'),
            'max_balance' => Request::input('maxBalance'),
            'created_by' => Request::input('createdBy'),
            'assign_user_id' => Request::input('assignUser'),
            'create_date' => date("Y-m-d H:i:s", strtotime(Request::input('create_date'))),
            'note' => Request::input('note'),
            'currency_id' => Request::input('currency'),
        ];
        $v = Validator::make($data, $rule);
        if ($v->fails()) {
            $res = false;
        } else {
            $res = true;
        }
        return ['res' => $res, 'data' => $data];
    }

    public function createtill()
    {
        if (!$this->_isLogin) {
            return ['logout' => true];
        }
        if (Request::ajax()) {

            if ($this->CheckPermId_from_session(90) == true) {

                $vals = $this->checkForm();
                if ($vals['res'] == true) {

                    $ins = DB::table('till_account')->insertGetId($vals['data']);

                    return ['res' => true, 'ins' => $ins];
                } else {

                    return ['form' => false];
                }
            }
            return ['users' => false];
        }
        return ['ajax' => false];
    }

    public function get_currency()
    {
        if (Request::ajax()) {

            $currency = Currency::select('id', 'name', 'symbol', 'code')->get();
            return ['res' => true, 'currency' => $currency];
        }
    }

    public function get_till_account()
    {
        if (Request::ajax()) {

            $till = Teller::select('till_account.id as till_id', 'till_account.account_name', 'till_account.account_no', 'till_account.assign_user_id', 'users.id', 'till_account.currency_id', 'users.name as name', 'till_account.balance')
                ->join('users', 'users.id', '=', 'till_account.assign_user_id')
                ->where('till_account.assign_user_id', '=', $this->user_id)
                ->get();
            return ['res' => true, 'till' => $till];
        }
    }

    public function getBrand()
    {
        if (Request::ajax()) {
            if ($this->CheckPermId_from_session(90) == true) {

                $brand = DB::table('users')
                    ->Join('company_branch', 'users.branch_id', '=', 'company_branch.id')
                    ->where('users.id', '=', $this->user_id)
                    ->leftJoin('till_account', 'till_account.branch_id', '=', 'users.branch_id')->orderBy('users.id', 'desc')
                    //->where('till_account.status', '=', 0)
                    ->select('*', 'users.id as uid', 'till_account.id as till_id', 'company_branch.id as bId', 'branch_name')->get();
                $res = true;
                return ['res' => $res, 'branchs' => $brand];

            } else {

                return ['users' => false];
            }
        }
    }

    public function assignTo($branchId, $currency_id)
    {
        if (Request::ajax()) {
            if (!empty($branchId)) {

                $teller = Teller::select('currency_id', 'assign_user_id', 'branch_id')->where('currency_id', '=', $currency_id)->where('branch_id', '=', $branchId)->get();
                $assign_user_id = [];
                foreach ($teller as $items) {
                    $assign_user_id[] = $items->assign_user_id;
                }
                $roles = Role::select('id')->whereIn('role', ['teller', 'chief_of_teller', 'cas'])->get();
                foreach ($roles as $items) {
                    $roles_id[] = $items->id;
                }
                if (empty($assign_user_id)) {

                    $this->_return = DB::table('users')->select('branch_id', 'name', 'id', 'role_id')
                        ->where('users.branch_id', '=', $branchId)
                        ->whereIn('users.role_id', $roles_id)
                        ->get();
                    return ['users' => $this->_return, 'assign' => $assign_user_id, 'rols' => $roles_id];
                }
                $this->_return = DB::table('users')->select('branch_id', 'name', 'id', 'role_id')
                    ->where('branch_id', '=', $branchId)
                    ->whereIn('role_id', $roles_id)
                    ->whereNotIn('id', $assign_user_id)
                    ->get();
                return ['users' => $this->_return, 'ass' => $assign_user_id, 'rols' => $roles_id];

            } else {
                return ['result' => false];
            }
        }
    }

    public function TillerAccountSummary()
    {
        if (Request::ajax()) {
            $teller = new Teller();
            $data = $teller->getTillerAccount($this->user_id, $this->_branch_id);
            $res = true;
            return ['res' => $res, 'account' => $data['account'], 'chief' => $data['chief']];
        }
    }

    public function openTill($id)
    {
        if (Request::ajax()) {
            $res = false;
            $data = [];
            if ($this->CheckPermId_from_session(90)) {

                if (!empty($id)) {
                    $data = Teller::select('*', 'till_account.status as tillstatus', 'currency.name as currency_name')
                        ->Join('company_branch', 'company_branch.id', '=', 'till_account.branch_id')
                        ->Join('currency', 'till_account.currency_id', '=', 'currency.id')
                        ->where('till_account.id', $id)->get();
                    if (count($data) > 0) {
                        $res = ['res' => true, 'permis' => true, 'acc' => $data];
                    } else {
                        $res = ['res' => true, 'permis' => true, 'acc' => $data];
                    }
                }
            } else {
                $res = ['res' => false, 'permis' => false, 'acc' => $data];
            }
            return $res;
        }
    }

    public function updateTill($byId)
    {

        $res = [];
        if (Request::ajax() && !empty($byId)) {
            DB::beginTransaction();

            try {

                if ($this->CheckPermId_from_session(90)) {
                    $status = Request::input('status');
                    if ($status == 0) {
                        $ifUpdate = Teller::where('id', $byId)->update(['status' => $status, 'close_time' => date("Y-m-d H:i:s")]);
                        if ($ifUpdate) {
                            $res = ['res' => true, 'permis' => true];
                        }
                    } else {

                        $ifUpdate = Teller::where('id', $byId)->update(['status' => $status, 'open_time' => date("Y-m-d H:i:s")]);
                        if ($ifUpdate) {
                            $res = ['res' => true, 'permis' => true];
                        }
                    }
                } else {
                    $res = ['res' => false, 'permis' => false];
                }

                DB::commit();
                return $res;
            } catch (Exception $e) {

                DB::rollback();
            }

        } else {
            return redirect()->route('login');
        }
    }


    public function chiefofteller()
    {
        if (!$this->_isLogin) return redirect()->route('login');
        $currency = Currency::select('currency.id as c_id', 'currency.name as c_name', 'currency.symbol as symbol', 'currency.code as c_code', 'currency.status as c_status')
            ->orWhere(function ($query) {

                $currency_id = Teller::select('currency_id')->where('created_by', '=', $this->user_id)->get();
                foreach ($currency_id as $item) {
                    $query->where('id', '!=', $item->currency_id);
                };
            })->get();
        return $this->view('teller.chiefofteller', ['currency' => $currency]);
    }

    /*
     * By: heng Sopheak
     * Function: separate from teller and Chief_of_teller
     */
    public function telloperation()
    {
        if (!auth()->check()) return redirect()->route('login');
        $tellers = Teller::select('till_account.account_name as account_name', 'till_account.balance as balance', 'users.id as uid', 'users.name as name',
            'company_branch.branch_name as branch_name', 'company_branch.id as bid', 'currency_id', 'till_account.status','users.allow_postback_date')
            ->join('users', 'users.id', '=', 'till_account.assign_user_id')
            ->join('company_branch', 'company_branch.id', '=', 'till_account.branch_id')
            ->where('assign_user_id', '=', auth()->user()->id)
            ->get();
        return $this->view('teller.telloperation', ['tellers' => $tellers, 'tillType' => $this->CheckPermId_from_session()]);
    }

    /*
  * By: heng Sopheak
  * Function: transfter amount from Chief of Teller to Teller by chiefOfTeller.Id => Tell.id
  * checking for user type and permission for getting authoright to use the features
  */

    public function issueTill()
    {
        if (Request::ajax()) {
            if (!$this->_isLogin) return ['login' => false];
            if ($this->CheckPermId_from_session(90) == true) {

                $chief = Teller::select('*', 'till_account.status as tillstatus', 'till_account.id as till_account_id')
                    ->Join('users', 'till_account.created_by', '=', 'users.id')
                    ->where('till_account.assign_user_id', '=', $this->user_id)->where('till_account.status', '=', 0)->get();

                $coa = CoaCategory::select('name', 'currency', 'account_code', 'type')
                    ->where('type', '=', 6)
                    ->where('name', '=', 'Cash in Vault')->get();

                $retn = ['loginchief' => $chief, 'coa' => $coa];

            } else {

                $teller_data = Teller::select('assign_user_id', 'created_by')->where('branch_id', $this->_branch_id)->where('assign_user_id', $this->user_id)->first();
                $chief = Teller::select('*', 'till_account.status as tillstatus', 'users.id as chief_user_id', 'till_account.id as chief_till_id')
                    ->join('users', 'users.id', '=', 'till_account.assign_user_id')
                    ->where('assign_user_id', $teller_data->created_by)->get();

                $teller = Teller::select('*', 'till_account.status as tillstatus', 'till_account.id as tell_till_account_id')
                    ->Join('users', 'till_account.assign_user_id', '=', 'users.id')
                    ->orwhere((function ($query) {
                        $query->where('till_account.assign_user_id', '=', $this->user_id)->where('till_account.status', '=', 0);//
                    }))->get();
                $retn = ['nologchief' => $chief, 'teller' => $teller];
            }
            return $retn;
        } else {
            return ['result' => false];//redirect()->route('login');
        }
    }

    private function getChiefData()
    {

        return User::select('*', 'users.id as uid')->join('roles', 'roles.id', '=', 'users.role_id')
            ->orwhere((function ($query) {
                $query->orwhere('users.branch_id', '=', $this->_branch_id)->whereIn('roles.role', ['chief_of_teller','cas']);
            }))->first();
    }

    public function issue_till_post()
    {
        if (Request::ajax()) {
            //for chief only
            $val = $this->issue_till_chief_form();
            $data = $val['data'];
            if ($this->CheckPermId_from_session(90)) {

                if ($val['rules'] === false) {
                    return ['res' => false, 'form' => false, 'permis' => true, 'data' => $data];
                } else {

                    $update = Teller::where('id', '=', $data['till_account_id'])->update(array('balance' => $data['balance']));
                    if ($update) {

                        $data['till_user_id'] = Request::input('operate_by');
                        $data['tran_currency_id'] = Request::input('currency_id');
                        $transId = DB::table('till_transaction')->insertGetId($data);

                        if ($transId) {
                            $record_date = $data['tranx_time'];
                            $journal_arr = [];
                            $coh_coa_id = CoaCategory::where('name', 'Like', '%in Vault%')->where('currency', Request::input('currency_id'))->first()->id;
                            $teller_coa_id = CoaCategory::where('name', 'Like', '%Teller%')->where('currency', Request::input('currency_id'))->first()->id;
                            array_push($journal_arr, [$teller_coa_id, $data['cash_in'], $data['description'],
                                                      $coh_coa_id, $data['cash_in'], $data['description'],
                                                      $data['description']]);
                            record_journal_no_trans(null, $record_date, $journal_arr, $data['branch_id'], Request::input('operate_by'), 1);

                            return ['res' => true, 'form' => true, 'permis' => true, 'data' => $data, 'in' => $transId, 'up' => true];
                        } else {
                            return ['res' => true, 'form' => true, 'permis' => true, 'data' => $data, 'in' => false, 'up' => true];
                        }
                    } else {
                        return ['res' => false, 'form' => true, 'permis' => true, 'data' => $data, 'in' => true, 'up' => false];
                    }
                }
            } else {
                /*for teller*/

                $vals = $this->issue_till_form();
                $data = $vals['data'];
                if ($vals['rules'] === false) {

                    $this->_return = ['form' => false, 'permis' => true];
                } else {

                    DB::beginTransaction();
                    try {
                        $data['tran_currency_id'] = Request::input('currency_id');
                        $data['till_user_id'] = $this->user_id;

                        $res['trans_id'] = DB::table('till_transaction')->insertGetId($data);
                        if (!empty($res['trans_id'])) {

                            $res['del_notify'] = $this->notification->setNotification(
                                [
                                    Request::input('chief_user_id'),
                                    json_encode($this->group_code),
                                    $res['trans_id'],
                                    Request::input('chief_till_account_id'),
                                    Request::input('teller_till_account_id'),
                                    $data['type'],
                                    $data['tranx_time'],
                                    //date("Y-m-d H:i:s"),
                                    $data['type'],
                                    Request::input('cash_in')
                                ]
                            );
                        }
                        if (!empty($res['del_notify'])) {
                            DB::commit();
                            return $res;
                        }
                    } catch (Exception $e) {
                        // var_dump($e->getMessage());
                    }
                }
            }
        }

    }

    private function issue_till_chief_form()
    {

        $res = false;
        $rule = [
            'till_account_id' => 'required',
            'from_account' => 'required',
            'to_account' => 'required',
            'branch_id' => 'required',
            'operate_by' => 'required',
            'tranx_time' => 'required',
            'type' => 'required',
            'cash_in' => 'required',
            'balance' => 'required',
            'description' => 'required',
        ];
        $d = date("Y-m-d H:i:s");
        if(Request::has('till_date')){
          $d = Request::input('till_date')!=""?Request::input('till_date'):$d;
        }

        $data = [
            'till_account_id' => Request::input('till_account_id'),
            'from_account' => Request::input('from_acc'),
            'to_account' => Request::input('to_acc'),
            'branch_id' => Request::input('branch_id'),
            'operate_by' => Request::input('operate_by'),
            'tranx_time' => $d,
            'type' => Request::input('type'),
            'cash_in' => Request::input('cash_in'),
            'balance' => Request::input('balance'),
            'description' => Request::input('descr'),
            'status' => 1
        ];
        $v = Validator::make($data, $rule);
        if ($v->fails()) {
            $res = false;
        } else {
            $res = true;
        }
        return ['rules' => $res, 'data' => $data];

    }

    private function issue_till_form()
    {
        $res = false;
        $rule = [
            'till_account_id' => 'required',
            'from_account' => 'required',
            'to_account' => 'required',
            'operate_by' => 'required',
            'type' => 'required',
            'cash_in' => 'required',
            'balance' => 'required',
            'description' => 'required',
        ];

        $d = date("Y-m-d H:i:s");
        if(Request::has('till_date')){
          $d = Request::input('till_date')!=""?Request::input('till_date'):$d;
        }

        $data = [
            'till_account_id' => Request::input('teller_till_account_id'),
            'from_account' => Request::input('from_account'),
            'to_account' => Request::input('to_account'),
            'branch_id' => Request::input('branch_id'),
            'operate_by' => $this->user_id,
            'tranx_time' => $d,
            'type' => Request::input('type'),
            'cash_in' => Request::input('cash_in'),
            'balance' => Request::input('tell_balance'),
            'description' => Request::input('description'),
            'till_user_id' => $this->user_id
        ];
        $v = Validator::make($data, $rule);
        if ($v->fails()) {
            $res = false;
        } else {
            $res = true;
        }
        return ['rules' => $res, 'data' => $data];
    }

    /*
     * Declare user types
     */
    private function CheckPermId_from_session($permisId = null)
    {
        $user = User::select("*")->join('roles', 'users.role_id', '=', 'roles.id')->where('users.id', '=', $this->user_id)->first();

        if (count($user) != 0) {

            $role_name = trim($user->role);
            if (trim($role_name) == 'chief_of_teller' || trim($role_name) == 'cas' || trim($role_name) == 'super_admin') {
            //if (trim($role_name) == 'Admin') {
                    return true;
            }if(trim($role_name) == 'teller') {

                return false;

            }
        }
    }

    /*
      * By: heng Sopheak
      *
      */

    public function transaction()
    {   
        if(Request::input('is_excel') == 1 || Request::input('is_csv') == 1){
            $this->trans_result();
        }else{
            return $this->view('teller.transaction');
        }
    }

    public function trans_result()
    { 
        // if (Request::ajax()) {
            $tran = Teller::select('till_account.*', 
                                'till_transaction.*',
                                'till_transaction.type as type',
                                'till_transaction.id as tid',
                                'till_transaction.approve_status as approve_status',
                                'till_transaction.description',
                                'notification.id as not_id',
                                'loans.contract_id'
                            )
                ->join('till_transaction', 'till_account.id', '=', 'till_transaction.till_account_id')
                ->join('currency', 'till_transaction.tran_currency_id', '=', 'currency.id')
                ->leftJoin('drawdown_account', 'till_transaction.drawdown_acc_id', '=', 'drawdown_account.id')
                ->leftJoin('loans', 'drawdown_account.account_no', '=', 'loans.drawdown_acc')
                ->leftJoin('notification', 'notification.n_source_id', '=', 'till_transaction.id');
            // A row is yours if it landed on your till OR if you posted it. BE-CASH resolves the
            // till account itself, so the poster is not always the till owner and would
            // otherwise never see their own posting here.
            $user_id = $this->user_id;
            $mine = function ($q) use ($user_id) {
                $q->where('till_account.assign_user_id', '=', $user_id)
                  ->orWhere('till_transaction.operate_by', '=', $user_id);
            };
            if ($this->CheckPermId_from_session(90) == true) {
                if (Request::input('till_account_id')) {
                    $tran->where('till_account_id', Request::input('till_account_id'));
                } else {
                    $tran->where($mine);
                }
            } else {
                $tran->where($mine);
            }
            if (Request::has('t_from')) {
                $tran->where(DB::raw('DATE(tranx_time)'), '>=', date("Y-m-d", strtotime(Request::input('t_from'))));
            }
            if (Request::has('t_to')) {
                $tran->where(DB::raw('DATE(tranx_time)'), '<', date("Y-m-d", strtotime(Request::input('t_to').'+1 days')));
            }

            if (!Request::has('t_from') && !Request::has('t_from'))
                $tran->where('tranx_time', '>=', date('Y-m-d')); //default from today

            $tran = $tran->groupBy('till_transaction.id')->orderBy('till_transaction.id', 'desc')->get();

            $user = \App\Models\User::select("*")->join('roles', 'users.role_id', '=', 'roles.id')->where('users.id', '=', $this->user_id)->first();
            if (!$user) {
                return null;
            }
            $role_name = trim($user->role);
            if(Request::input('is_excel') == 1 || Request::input('is_csv') == 1){
                $xlsx = 'xlsx';
                if(Request::has('is_csv') == 1){
                    $xlsx = 'csv';
                }
                return Excel::create('teller-'.date('d-M-Y'), function($excel) use ($tran) {
                    $excel->sheet('mySheet', function($sheet) use ($tran)
                    {
                        $sheet->loadView('exports.teller_excel',['tran' => $tran]);
                    });
                })->download($xlsx);
            }else{
                if (Request::ajax()) {
                    return ['res' => true, 'permis' => true, 'data' => $tran,'role_name'=>$role_name];
                } else {
                    return false;
                }
            }
        // } else {
        //     return false;
        // }
    }

    public function selectTransTill()
    {
        if (Request::ajax()) {
            if (Request::has('term')) {

                $name = Request::input('term');
                $retn = ['res' => false, 'permis' => false, 'data' => []];
                if ($this->CheckPermId_from_session() == true) {

                    $tran = Teller::select('*', 'till_account.id as till_id')
                        ->join('users', 'users.id', '=', 'till_account.assign_user_id')
                        ->where('name', 'like', '%' . $name . '%')
                        ->orWhere(function ($q) use ($name) {
                            $q->where('account_no', 'like', '%' . $name . '%')
                                ->where('till_account.account_name', 'like', '%' . $name . '%');
                        })->get();
                    $retn = ['res' => true, 'permis' => true, 'data' => $tran, 'name' => $name];
                } else {

                    $trans = Teller::select('*', 'till_account.id as till_id')
                        ->join('users', 'users.id', '=', 'till_account.assign_user_id')
                        ->join('roles', 'roles.id', '=', 'users.role_id')
                        ->where('name', 'like', '%' . $name . '%')
                        ->where('role', '=', 'teller')
                        ->orWhere(function ($q) use ($name) {
                            $q->where('account_no', 'like', '%' . $name . '%')
                                ->where('till_account.account_name', 'like', '%' . $name . '%');
                        })->get();
                    $retn = ['res' => true, 'permis' => true, 'data' => $trans];
                }
            }

            return $retn;
        } else {
            return false;
        }

    }


    public function TransferTill()
    {
        if (Request::ajax()) {

            if ($this->CheckPermId_from_session(90) == true) {

                $data['chief'] = Teller::select('*', 'till_account.id as id', 'users.id as uid', 'till_account.status as tillstatus')
                    ->join('users', function ($join) {
                        $join->on('assign_user_id', '=', 'users.id')
                            ->where('assign_user_id', '=', $this->user_id);
                    })->get();


                $data['teller'] = Teller::select('*', 'till_account.id as id', 'till_account.status as tillstatus')
                    ->join('users', 'assign_user_id', '=', 'users.id')
                    ->where(function ($where) {

                        $role_id = Role::select('id')->whereIn('role', ['chief_of_teller','cas'])->first();
                        $where->where('created_by', '=', $this->user_id)
                            ->where('users.role_id', '!=', $role_id->id)
                            ->where('till_account.branch_id', '=', $this->_branch_id);
                    })->get();
                if (empty($data)) {
                    return ['res' => false];
                }
                return $data;
            }
        }
    }

    private function Till_status()
    {

        return Teller::select('status')->where(['assign_user_id' => $this->user_id])->first();
    }

    public function postsTransferTill()
    {
        if (Request::ajax() && $this->CheckPermId_from_session(90)) {

            $res = null;
            $data = Request::except(['_token']);
            $rules = [
                'till_account_id' => 'required',
                'from_account' => 'required',
                'to_account' => 'required',
                'till_user_id' => 'required',
                'branch_id' => 'required',
                'operate_by' => 'required',
                'type' => 'required',
                'cash_out' => 'required',
                'balance' => 'required',
                'description' => 'required',
            ];
            $data = [
                'till_account_id' => Request::input('till_account_id'),
                'from_account' => Request::input('from_account'),
                'to_account' => Request::input('to_account'),
                'till_user_id' => $this->user_id,//Request::input('assign_user_id')
                'branch_id' => Request::input('branch_id'),
                'operate_by' => Request::input('operate_by'),
                'type' => Request::input('type'),
                'cash_out' => Request::input('cash_out'),
                'balance' => Request::input('last_chief_balance'),
                'description' => Request::input('description'),
            ];
            $data['tranx_time'] = Request::input('till_date')!=""?Request::input('till_date'):date("Y-m-d H:i:s");
            $val = Validator::make($data, $rules);
            if ($val->fails()) {

                return ['form' => false];
            } else {

                DB::beginTransaction();
                try {
                    $data['tran_currency_id'] = Request::input('tran_currency_id');
                    $res['ins_trans'] = DB::table('till_transaction')->insertGetId($data);
                    if (!empty($res['ins_trans'])) {

                        $res['ins_notify'] = $this->notification->setNotification([Request::input('assign_user_id'), json_encode($this->group_code), $res['ins_trans'],
                            Request::input('chief_till_id'), Request::input('teller_till_id'), $data['type'], $data['tranx_time'], $data['type'], Request::input('cash_out')
                        ]);
                    }
                    if (!empty($res['ins_notify'])) {
                        DB::commit();
                        return $res;
                    }
                    return ['res' => false];
                } catch (Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            }
        }
    }

    public function getTableColumns($tables)
    {
        return DB::getSchemaBuilder()->getColumnListing($tables);
    }

    /*
     * Heng Sopheak
     * Return Till (Teller)
     * query data from DB
     * 15/03/31
     */
    public function ReturnTillData()
    {
        $res = false;
        if (Request::ajax()) {
            $this->_return = [];
            if ($this->CheckPermId_from_session(90) == true) {


                $chief1 = Teller::select('*', 'till_account.status as tillstatus', 'till_account.id as chief_till_id', 'currency.symbol as symbol')
                    ->LeftJoin('users', function ($join) {
                        $join->on('till_account.created_by', '=', 'users.id');
                    })->join('currency', 'currency.id', '=', 'till_account.currency_id')
                    ->where('assign_user_id', '=', $this->user_id)
                    ->orwhere((function ($query) {
                        $query->where('balance', '>', 0)->where('assign_user_id', '=', $this->user_id);
                    }))->get();

                foreach ($chief1->lists('currency_id') as $item) {
                    $c_id[] = $item;
                }
                $coa = CoaCategory::select('name', 'currency', 'account_code', 'type')
                    ->whereIn('currency', $c_id)
                    ->where('type', '=', 6)
                    ->where('name', '=', 'Cash in Vault')->get();
                $this->_return = ['res' => true, 'chief' => $chief1, 'coa' => $coa, 'cId' => $c_id];
                return $this->_return;

            } else {
                DB::transaction(function () {
                    $currency_id = null;
                    $teller = Teller::select('*', 'till_account.status as tillstatus', 'users.id as uid', 'till_account.id as till_account_id', 'currency.symbol as symbol')
                        ->Join('users', 'till_account.assign_user_id', '=', 'users.id')
                        ->join('currency', 'currency.id', '=', 'till_account.currency_id')
                        ->orwhere(function ($query) {
                            $query->where('till_account.assign_user_id', '=', $this->user_id)
                                ->where('balance', '>', 0)
                                ->where('till_account.branch_id', '=', $this->_branch_id);
                        })->get();
                    foreach ($teller->lists('created_by') as $item) {
                        $created_by = $item;
                    }
                    $chief = Teller::select('*', 'till_account.status as tillstatus', 'till_account.id as chief_till_id')
                        ->join('users', 'users.id', '=', 'till_account.assign_user_id')
                        ->orwhere(function ($query) use ($created_by) {
                            $query->where('till_account.branch_id', '=', $this->_branch_id)
                                ->where('till_account.assign_user_id', '=', $created_by);
                        })->get();

                    $notification = Notification::where('n_user_id',$this->user_id)->whereIn('n_activity_type',['Transfer till','Cash Deposit'])->count();
                    $this->_return = ['res' => true, 'chief' => $chief, 'teller' => $teller, 'user' => $created_by,'notification'=>$notification];
                });
                return $this->_return;
            }
        }
    }

    public function PostReturnTill()
    {
        if (Request::ajax()) {
            $res = false;
            if ($this->CheckPermId_from_session(90)) {
                $rules = [
                    'till_account_id' => 'required',
                    'from_account' => 'required',
                    'to_account' => 'required',
                    'branch_id' => 'required',
                    'operate_by' => 'required',
                    'type' => 'required',
                    'cash_out' => 'required',
                    'description' => 'required',
                    'tran_currency_id' => 'required',
                ];
                $data = Request::except(['_token']);
                $data['tranx_time'] = Request::input('till_date')!=""?Request::input('till_date'):date("Y-m-d H:i:s");
                $val = Validator::make($data, $rules);
                if ($val->fails()) {

                    $res = ['res' => false, 'form' => false, 'data', $data];
                } else {

                    DB::beginTransaction();
                    try {
                        $update = Teller::where('id', '=', $data['till_account_id'])->update(array('balance' => 0));

                        $data['tran_currency_id'] = Request::input('tran_currency_id');
                        $data['till_user_id'] = $this->user_id;
                        unset($data['till_date']);
                        $insertId = DB::table('till_transaction')->insertGetId($data);
                        if ($update && $insertId) {
                            $journal_arr = [];
                            $teller_coa_id = CoaCategory::where('name', 'Like', '%Teller%')->where('currency', Request::input('tran_currency_id'))->first()->id;
                            $coh_coa_id = CoaCategory::where('name', 'Like', '%in Vault%')->where('currency', Request::input('tran_currency_id'))->first()->id;
                            array_push($journal_arr, [$coh_coa_id, $data['cash_out'], $data['description'],
                                                      $teller_coa_id, $data['cash_out'], $data['description'], $data['description']]);
                            record_journal_no_trans(null, $data['tranx_time'], $journal_arr, $data['branch_id'], $this->user_id, 1);

                            DB::commit();
                            return ['res' => true, 'data', $data];
                        } else {
                            return $res;
                        }

                    } catch (Exception $e) {

                        DB::rollBack();
                        throwException($e);
                    }
                }
            } else {

                $rules = [
                    'till_account_id' => 'required',
                    'from_account' => 'required',
                    'to_account' => 'required',
                    'branch_id' => 'required',
                    'operate_by' => 'required',
                    'type' => 'required',
                    'cash_out' => 'required',
                    'description' => 'required',
                ];
                $data = Request::except(['_token']);
                $data = [
                    'till_account_id' => Request::input('till_account_id'),
                    'from_account' => Request::input('from_account'),
                    'to_account' => Request::input('to_account'),
                    'branch_id' => Request::input('branch_id'),
                    'operate_by' => Request::input('operate_by'),
                    'type' => Request::input('type'),
                    'cash_out' => Request::input('cash_out'),
                    'description' => Request::input('description')
                ];
                $data['tranx_time'] =  Request::input('till_date')!=""?Request::input('till_date'):date("Y-m-d H:i:s");
                $val = Validator::make($data, $rules);
                if ($val->fails()) {

                    $res = ['res' => false, 'form' => false, 'data', $data];
                } else {

                    DB::beginTransaction();
                    try {
                        $res['up_till'] = Teller::where('id', '=', Request::input('till_account_id'))->update(['balance' => 0]);

                        $data['till_user_id'] = Request::input('operate_by');
                        $data['tran_currency_id'] = Request::input('currency_id');
                        unset($data['till_date']);
                        $res['ins_trans'] = DB::table('till_transaction')->insertGetId($data);
                        if (!empty($res['up_till']) && !empty($res['ins_trans'])) {

                            $res['ins_notify'] = $this->notification->setNotification([
                                Request::input('operate_by'),
                                json_encode($this->group_code),
                                $res['ins_trans'],
                                Request::input('chief_till_id'),
                                $data['till_account_id'],
                                $data['type'],
                                $data['tranx_time'],
                                $data['type'],
                                Request::input('cash_out')
                            ]);
                        }
                        if (!empty($res['ins_notify'])) {
                            DB::commit();
                            return $res;
                        }
                    } catch (Exception $e) {
                        DB::rollBack();
                        throwException($e);
                    }

                }
            }
        }
    }

    public function transfer_till_from_chief()
    {
        $res = false;
        if (Request::ajax()) {

            $rules = [
                'till_account_id' => 'required',
                'from_account' => 'required',
                'to_account' => 'required',
                'till_user_id' => 'required',
                'cash_in' => 'required',
                'branch_id' => 'required',
                'balance' => 'required',
                'operate_by' => 'required',
                'description' => 'required',
                'type' => 'required',
            ];
            $data = [
                'till_account_id' => Request::input('till_account_id'),
                'from_account' => Request::input('from_account'),
                'to_account' => Request::input('to_account'),
                'till_user_id' => Request::input('till_user_id'),
                'cash_in' => Request::input('cash_in'),
                'balance' => Request::input('tell_balance'),
                'branch_id' => Request::input('branch_id'),
                'operate_by' => Request::input('operate_by'),
                'description' => Request::input('description'),
                'type' => 'Transfer till'//Request::input('type'),
            ];
            $val = Validator::make($data, $rules);
            if ($val->fails()) {

                $this->_return = ['res' => false, 'form' => false];
            } else {
                //update chief
                $last_chief_balance = Request::input('chief_balance') - Request::input('cash_out');
                Teller::where('id', Request::input('chief_till_account_id'))->update(array('balance' => $last_chief_balance));

                DB::beginTransaction();
                try {
                    $res['upTeller'] = Teller::where('id', '=', $data['till_account_id'])->update(['balance' => Request::input('tell_balance')]);
                    if (!empty($res['upTeller'])) {

                        $data['tranx_time'] = Request::input('till_date')!=""?Request::input('till_date'):date("Y-m-d H:i:s");
                        $data['tran_currency_id'] = Request::input('currency_id');
                        $res['ins_tran'] = TillTransaction::insertGetId($data);
                        $res['del_not'] = $this->notification->deleteNotsById(Request::input('not_id'));
                    }
                    DB::commit();
                    return $res;
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->_return = ['Exception', 'e' => $e];
                }
            }
            return $this->_return;
        } else {
            $this->_return = ['res' => false];
        }

    }

    /**
     * @return array|bool
     * Notification action
     */
    public function Post_Notification()
    {
        if (Request::ajax()) {

            $res = false;
            $rules = [
                'till_account_id' => 'required',
                'from_account' => 'required',
                'to_account' => 'required',
                'balance' => 'required',
                'branch_id' => 'required',
                'operate_by' => 'required',
                'description' => 'required',
                'type' => 'required',
            ];
            $data = [
                'till_account_id' => Request::input('chief_till_account_id'),
                'from_account' => Request::input('from_account'),
                'to_account' => Request::input('to_account'),
                'balance' => Request::input('chief_balance'),
                'cash_out' => Request::input('cash_out'),
                'branch_id' => Request::input('branch_id'),
                'operate_by' => Request::input('operate_by'),
                'description' => Request::input('description'),
                'type' => Request::input('type'),
            ];
            $v = Validator::make($data, $rules);

            if ($v->fails()) {

                $res = ['res' => false, 'data' => $data];
            } else {

                DB::beginTransaction();
                try {

                    if (Request::input('action') == 1) { //approve
                        $d = date("Y-m-d H:i:s");
                        if(Request::has('till_date')){
                          $d = Request::input('till_date')!=""?Request::input('till_date'):$d;
                        }

                        $data['tranx_time'] = $d;
                        $update_teller_balance = Teller::where('id', '=', Request::input('till_account_id'))->update(array('balance' => Request::input('tell_balance')));
                        $update_chief_balance = Teller::where('id', '=', Request::input('chief_till_account_id'))->update(array('balance' => Request::input('chief_balance')));

                        $data['tran_currency_id'] = Request::input('currency_id');
                        $data['till_user_id'] = $this->user_id;
                        $RetnId = $this->InsertTransactionFor('chief', $data);
                        if (!is_null($RetnId)) {

                            $del = $this->notification->deleteNotsById((int)Request::input('not_id'));
                            $res = ['res' => true, 'dels' => $del, 'ins' => $RetnId, 'data' => $data, 'f' => $update_teller_balance, 'd' => $update_chief_balance];
                        } else {

                            $res = ['res' => true, 'insert' => $RetnId, 'act' => Request::input('action')];
                        }
                    } else if (Request::input('action') == 2) { // When chief click to reject teller issue

                        $till_transaction = TillTransaction::where('id', '=', (int)Request::input('n_source_id'))->get()->first()->toArray();
                        $notification_action = array_merge($till_transaction,['reject_by'=>Auth::id(),'reject_id'=>$till_transaction['id']]);
                        unset($notification_action['id']);
                        $till_reject = TillTransactionReject::insert($notification_action);
                        
                        TillTransaction::where('id', '=', (int)Request::input('n_source_id'))->delete();
                        $del = $this->notification->deleteNotsById(Request::input('not_id'));
                        $res = ['res' => true, 'del' => $del, 'act' => Request::input('action')];
                    } else {
                        $res = ['res' => false, 'del' => 0, 'act' => Request::input('action')];
                    }
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    throw($e);
                }
            }
            return $res;
        }
    }

    public function returnTillNotification()
    {
        if (Request::ajax()) {

            DB::beginTransaction();
            try {
                if (Request::input('action') == 1) {

                    $chief_balance = Teller::where('id', '=', Request::input('chief_till_account_id'))->update(['balance' => Request::input('balance')]);
                    if (!empty($chief_balance)) {
                        $this->_return = ['res' => true, 'act' => 1, 'update' => $chief_balance];

                        $d = date("Y-m-d H:i:s");
                        if(Request::has('till_date')){
                          $d = Request::input('till_date')!=""?Request::input('till_date'):$d;
                        }

                        $data = [
                            'till_account_id' => Request::input('chief_till_account_id'),
                            'from_account' => Request::input('from_account'),
                            'to_account' => Request::input('to_account'),
                            'balance' => Request::input('balance'),
                            'cash_in' => Request::input('cash_out'),
                            'branch_id' => Request::input('branch_id'),
                            'till_user_id' => Request::input('operate_by'),
                            'operate_by' => Request::input('operate_by'),
                            'tranx_time' => $d,
                            'description' => Request::input('description'),
                            'type' => Request::input('type'),
                            'tran_currency_id' => Request::input('currency_id'),
                        ];

                        $insertTransaction = TillTransaction::insertGetId($data);
                        if (!empty($insertTransaction)) {
                            $del = $this->notification->deleteNotsById(Request::input('not_id'));
                            $this->_return = ['res' => true, 'act' => 1, 'insertTrans' => $insertTransaction];
                        }
                    } else {
                        $this->_return = ['res' => true, 'act' => 1, 'fails' => true];
                    }
                }
                if (Request::input('action') == 2) {

                    $till_transaction = TillTransaction::where('id', '=', (int)Request::input('n_source_id'))->get()->first()->toArray();
                    $notification_action = array_merge($till_transaction,['reject_by'=>Auth::id(),'reject_id'=>$till_transaction['id']]);
                    unset($notification_action['id']);
                    $till_reject = TillTransactionReject::insert($notification_action);

                    $teller_balance = Teller::where('id', '=', Request::input('till_account_id'))->update(['balance' => Request::input('cash_out')]);
                    $del = $this->notification->deleteNotsById(Request::input('not_id'));
                    $del_trans = TillTransaction::where('id', '=', (int)Request::input('n_source_id'))->delete();
                    $this->_return = ['res' => true, 'act' => 2];
                }
                if (Request::input('action_type') == 1) {
                    // Ok when teller disburse a loan
                    $this->_return = $del = $this->notification->deleteNotsById(Request::input('not_id'));
                }
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                return ['throw' => $e];
            }
            return $this->_return; // return with respond value for users when they success
        }
    }

    /**
     * @param null $type
     * Insert by type of transaction types
     */

    private function InsertTransactionFor($type = null, $data)
    {
        if ($type) {
            $teller = new TillTransaction();
            $returnId = $teller->InsertTransactionFromNotification($data);

            if (is_null($returnId)) {
                return false;
            } else {
                return $returnId;
            }
        }
    }

    public function TillTransaction($data)
    {
        return TillTransaction::insertGetId($data['insert']);
    }

    public function disburse_list()
    {

        if (Request::ajax()) {
            $userdata = array('user_id' => Auth::user()->id, 'company_branch_id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);
            $loanData = new Loan();
            $loanData = $this->getUserByBranch($loanData, 'company_branch_id', $userdata);
            $loans = $loanData->with([
                'approval' => function ($query) {
                    $query->select('id', 'loan_id', 'approval_date');
                },
                'branch' => function ($query) {
                    $query->select('id', 'branch_name');
                },
                'client' => function ($query) {
                    $query->select('id', 'client_name');
                }
            ])->where('status', '=', 2)->get();
            return ['res' => true, 'data' => $loans];
        }
    }

    public function Post_expense()
    {
        if (Request::ajax()) {
            $rules = [
                'type' => 'required',
                'cash_out' => 'required',
                'description' => 'required',
                'till_account_id' => 'required',
            ];
            $teller = Teller::select('*', 'till_account.id as id', 'users.id as uid')
                ->join('users', 'users.id', '=', 'till_account.assign_user_id')
                ->where('assign_user_id', '=', $this->user_id)
                ->where('till_account.id', '=', Request::input('till_account_id'))
                ->first();

            $d = date("Y-m-d H:i:s");
            if(Request::has('till_date')){
              $d = Request::input('till_date')!=""?Request::input('till_date'):$d;
            }

            $data = [
                'till_account_id' => $teller->id,
                'till_user_id' => $this->user_id,
                'tran_currency_id' => $teller->currency_id,
                'balance' => floatval($teller->balance) - floatval(Request::input('cash_out')),
                'cash_out' => Request::input('cash_out'),
                'branch_id' => $teller->branch_id,
                'operate_by' => $this->user_id,
                'tranx_time' => $d,
                'description' => Request::input('description'),
                'type' => 'Expenses',
                'from_account' => $teller->name . ' ( ' . $teller->account_name . ' / ' . $teller->account_no . ' ) ',
                'to_account' => Request::input('type')
            ];
            $val = Validator::make($data, $rules);
            if ($val->fails()) {

                return ['form' => true];
            }
            $res['ins_trans'] = TillTransaction::insertGetId($data);
            if (!empty($res['ins_trans'])) {

                $res['up_bal'] = Teller::where('id', '=', $teller->id)->update(['balance' => $data['balance']]);
                if ($res['up_bal']) {
                    return $res;
                }
            }
        }
    }

    public function getTeller()
    {

        if (Request::ajax()) {
            $teller = Teller::select('*', 'till_account.id as till_id')
                ->join('users', 'users.id', '=', 'till_account.assign_user_id')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->where('roles.role', 'teller')
                ->Orwhere(function($q){
                    $q->where('till_account.branch_id', '=', $this->_branch_id);
                    $q->whereIn('roles.role', ['chief_of_teller', 'cas']);
                })->get();
            return ['teller' => $teller];
        }
    }

    public function deposit_form(){
        return view('teller.deposit');
    }

    public function getDeposit()
    {
        $search = Request::input('term');
        $widthdraw = DrawdownAccounts::with(['Client','Client.general', 'currency_tbl',
                    'projects'=> function ($query) {
                            $query->select('id', 'dealer');
                        }
                    ,
                    'unitType'=> function ($query) {
                            $query->select('id', 'name');
                        }
                    ,
                    'units'=> function ($query) {
                            $query->select('id', 'code','price');
                        }
                    ])
                    ->where('status', 1);
                    // ->limit(1000)
        if(!empty($search)){
            $search=$search;
        }else{
            $search='1';
        }
        $widthdraw = $widthdraw->where(function($q) use($search){
            $q->where('account_no','like','%'.$search.'%')
            ->orWhere('account_name','like','%'.$search.'%');
        })->orWhereHas('units', function($query) use ($search){
            $query->where('code','like','%'.$search.'%');
        })->orWhereHas('projects', function($query) use ($search){
            $query->where('dealer','like','%'.$search.'%');
        })->orWhereHas('unitType', function($query) use ($search){
            $query->where('name','like','%'.$search.'%');
        });

        $widthdraw = $widthdraw->limit(20)->orderBy('id', 'DESC')->get();
        foreach ($widthdraw as $key => $items) {
            if($items->status=='1'){
                $data[] = $items;
            }
           
        }
        /*
        $widthdraw['till_account'] = Teller::where(['assign_user_id'=>auth()->user()->id, 'currency_id'=>2])->where('balance','>',0)->get();
        if(!count($widthdraw['till_account'])) {
            return ['till_account'=>false];
        }
        */
        $roles_id = Role::whereIn('role', ['chief_of_teller', 'bm', 'admin', 'accm', 'acco','cas'])->get();
        $role_arr = [];
        foreach($roles_id as $r){
            array_push($role_arr, $r->id);
        }
        $admin = User::with('role')->whereIn('role_id', $role_arr)
                                    ->where('branch_id', auth()->user()->branch_id)
                                    ->where('status', 1)
                                    ->orderBy('name', 'ASC')->get();
        $currencies = Currency::select('id', 'code')->get();
        $currency_list = [];
        foreach($currencies as $cur){
            $currency_list[$cur->id] = $cur->code;
        }
        return ['withdraw' => $data, 'loan_admin' => $admin, 'role'=>$role_arr, 'currency_list'=>$currency_list];
    }

    public function postDeposit()
    {
        
        $cashDepositSavepoint = DB::transactionLevel() > 0 ? 'cash_deposit_' . DB::transactionLevel() : null;
        if ($cashDepositSavepoint) DB::statement('SAVEPOINT ' . $cashDepositSavepoint);
        DB::beginTransaction();
        try 
        {

            if(Request::input('not_id') == 0)
            {
                $till_account = Teller::select('id', 'assign_user_id', 'balance', 'account_no', 'account_name')->where('assign_user_id', '=', auth()->user()->id)->where('currency_id', '=', Request::input('currency_id'))->first();
                $record_date = Request::input('till_date')!=""?Request::input('till_date'):date("Y-m-d H:i:s");
                if($record_date === 'undefined'){
                    $record_date = date("Y-m-d H:i:s");
                }
                //$record_date = date("Y-m-d H:i:s");
                //if($record_date == "0000-00-00 00:00:00") $record_date = date("Y-m-d H:i:s");
                // dd($till_account);
                if (count($till_account) > 0) 
                {
                    $till_transaction = new TillTransaction();
                    $till_transaction->till_account_id = $till_account->id;
                    $till_transaction->drawdown_acc_id = Request::input('drawdown_acc_id');
                    $till_transaction->till_user_id = $till_account->assign_user_id;
                    $till_transaction->from_account = Request::input('client_name');
                    $till_transaction->to_account = $till_account->account_no . ' / ' . $till_account->account_name;
                    $till_transaction->methode = Request::input('types');
                    $till_transaction->balance = floatval($till_account->balance);
                    // if(Request::input('types')=='Cash on Hand-Teller'){
                    //   $till_transaction->balance += floatval(Request::input('amount'));
                    // }
                    $till_transaction->cash_in = Request::input('amount');
                    $till_transaction->drawdown_acc = Request::input('drawdown_acc');
                    $till_transaction->branch_id = auth()->user()->branch_id;
                    $till_transaction->operate_by = auth()->user()->id;
                    $till_transaction->tranx_time = $record_date;
                    $till_transaction->tran_currency_id = Request::input('currency_id');
                    $till_transaction->description = !empty( Request::input('description') )? Request::input('description') : 'Cash Deposit';
                    $till_transaction->type = 'Cash Deposit';
                    $till_transaction->deposit_type = 'Cash Deposit';

                    $slips = new slips();
                    // $print_cnt = slips::all()->last()->print_cnt;
                    $print_cnt  = slips::latest()->take(1)->first();
                    $print_cnt = $print_cnt->print_cnt;
                    // $drawDownAct = DrawdownAccounts::where('client_id', Request::input('client_id'))->where('currency', Request::input('currency_id'))->first();
                    $drawDownAct = DrawdownAccounts::where('client_id', Request::input('client_id'))->where('account_no', Request::input('drawdown_acc'))->where('currency', Request::input('currency_id'))->first();
                    if(!$drawDownAct){
                        $drawDownAct = DrawdownAccounts::where('account_no', $till_transaction->drawdown_acc)->where('currency', $till_transaction->tran_currency_id)->first();
                    }
                    
                    $slips->draw_acc_id = $drawDownAct->id;
                    $slips->client_id = Request::input('client_id');
                    $slips->user_id = auth()->user()->id;
                    $slips->create_date = $record_date;
                    $slips->currency_id = Request::input('currency_id');
                    $slips->types = Request::input('types');
                    $slips->amount = Request::input('amount');
                    $slips->trans_type = 'Cash Deposit';
                    $slips->check_num = Request::input('check_num');
                    $slips->bank_name = Request::input('bank_name');
                    $slips->description = Request::input('description');
                    $slips->prepared_by = auth()->user()->id;
                    $slips->checked_by = auth()->user()->id;
                    $slips->authorized_by = auth()->user()->id;
                    $slips->signature = auth()->user()->signature;
                    $slips->is_signature = auth()->user()->is_signature;
                    $slips->print_cnt = (int)$print_cnt + 1;
                    // $dd_balance = $drawDownAct->balance + floatval(Request::input('amount'));
                    // $res['up_wd'] = DrawdownAccounts::where('client_id', Request::input('client_id'))->where('currency', Request::input('currency_id'))->update(['balance' => $dd_balance]);
                    if($drawDownAct->unit_id){
                        $units = Unit::where('id',$drawDownAct->unit_id)->where('status','available')->first();
                        if($units){
                            $units->status = 'deposit';
                            $units->save();
                        }
                    }
                    //if (!empty($res['up_wd'])) {
                        // $res['up_till'] = (Request::input('types')=='Cash on Hand-Teller')? Teller::where('id', $till_account->id)->update(['balance' => $till_transaction->balance]) : 1;
                      //  return array(empty($res['up_till']));
                        //if (!empty($res['up_till'])) {
                          if(Request::input('types') == 'Foreign Exchange Position Account'){
                            $teller_coa = CoaCategory::where('name', '=', Request::input('types'))->where('currency', Request::input('currency_id'))->first();
                          }else{
                            $teller_coa = CoaCategory::where('name', 'Like', '%'.Request::input('types').'%')->orderBy('id')->where('currency', Request::input('currency_id'))->first();
                          }
                          // The COA account is matched by the payment method's label text, so a
                          // method with no matching account returns null here. Reading ->id off that
                          // null is only a notice, so it used to reach the journal, where
                          // tb_journal_detail.coa_id (NOT NULL) rejected it - leaving the customer a
                          // receipt and an empty journal header, with no till transaction at all.
                          if (empty($teller_coa)) {
                            throw new \Exception('Payment method "'.Request::input('types').'" has no COA account for this currency, so nothing was posted. Add the account under Accounting > Add COA Category, or choose another payment method.');
                          }
                          $teller_coa_id = $teller_coa->id;
                          $branch_code = $drawDownAct->branch;
                          $journal_arr = [];
                          array_push($journal_arr, [
                                                    $teller_coa_id, 
                                                    Request::input('amount'), 
                                                    Request::input('description'),
                                                    $drawDownAct->coa_id, 
                                                    Request::input('amount'), 
                                                    Request::input('description'),
                                                    Request::input('description')
                                                ]
                                            );
                          //record_journal_no_trans(null, $record_date, $journal_arr, auth()->user()->branch_id, auth()->user()->id, 1);
                          $postedJournal = record_journal_no_trans(null, $record_date, $journal_arr, auth()->user()->branch_id, auth()->user()->id, 0,null,1);

                          if (!is_array($postedJournal)) throw new \RuntimeException('Cashier journal did not complete.');
                          $slips->jr_id = $postedJournal['journal_id'];
                          $slips->jd_id = $postedJournal['journal_detail_id'];
                          if($slips->save()){
                              $till_transaction->slips_id = $slips->id;
                              if($till_transaction->save()) {
                                  $this->userActivity(Auth::user()->id, $slips->jd_id, 0, 'Add JournalDetail', Request::fullUrl());
                                  $res['ins_notify'] = $this->notification->setNotification([
                                      Request::input('users_id'),
                                      false,
                                      $till_transaction->id,
                                      Request::input('users_id'),
                                      $till_account->id,
                                      'Cash Deposit',
                                      $record_date,
                                      $till_transaction->description,
                                      Request::input('amount')
                                  ]);
                              }
                          }
                        //}
                    //}
                      //if(Request::input('isprint')==1){
                    if (!empty($res['ins_notify'])) {
                        if (Request::has('if_print') && Request::input('if_print')==1) {
                            $res['print_url'] = url('teller/print/deposit/'.$slips->id);
                        }
                    }
                }else{
                    $res['ins_notify'] = false;
                }

            }else{
                if(Request::input('flag_button') == "ok"){
                // Approve deposit
                    $notification = Notification::where('id', '=', Request::input('not_id'))->lockForUpdate()->first();
                    if (!$notification) throw new \RuntimeException('Cash deposit notification does not exist.');
                    $till_transaction = TillTransaction::where('id', '=', $notification->n_source_id)->lockForUpdate()->first();
                    if (!$till_transaction || $notification->n_user_id != Auth::user()->id || $till_transaction->branch_id != Auth::user()->branch_id) {
                        throw new \RuntimeException('Cash deposit approval is outside the assigned user or branch.');
                    }
                    if ((int)$till_transaction->approve_status === 1) { DB::commit(); if ($cashDepositSavepoint) DB::statement('RELEASE SAVEPOINT ' . $cashDepositSavepoint); return 'true'; }
                    if ((int)$till_transaction->approve_status !== 0) throw new \RuntimeException('Cash deposit is not pending.');
                    // Update balance
                    $drawDownAct = DrawdownAccounts::where('account_name', $till_transaction->from_account)->where('account_no', $till_transaction->drawdown_acc)->where('currency', $till_transaction->tran_currency_id)->with('units')->lockForUpdate()->first();
                    if(!$drawDownAct){
                        $drawDownAct = DrawdownAccounts::where('account_no', $till_transaction->drawdown_acc)->where('currency', $till_transaction->tran_currency_id)->with('units')->lockForUpdate()->first();
                    }
               
                    if($drawDownAct){ 
                        $dd_balance = $drawDownAct->balance + floatval($notification->n_amount);
                        $drawDownAct->balance = $dd_balance;
                        $drawDownAct->save();
                        $till_account = Teller::where('id', $till_transaction->till_account_id)->first();
                        $till_transaction->approve_status = 1;
                        if($till_transaction->methode=='Cash on Hand-Teller'){
                            $till_account->balance = $till_account->balance + floatval($notification->n_amount);
                        }
                        $till_transaction->balance = $till_account->balance;
                        $till_transaction->save();
                        $till_account->save();

                        $postedSlip = slips::where('id', $till_transaction->slips_id)->first();
                        if (!$postedSlip) throw new \RuntimeException('Cash deposit slip does not exist.');
                        $journal_require = JournalRequiry::where('id', $postedSlip->jr_id)->lockForUpdate()->first();
                        if (!$journal_require || $journal_require->user_id != $till_transaction->operate_by) {
                            throw new \RuntimeException('Cash deposit journal does not match its slip.');
                        }
                        if($journal_require){
                            $journal_require->is_audit = 1;
                            $journal_require->save();
                        }

                        JournalDetail::where('journal_id', '=', $journal_require->id)->update(['is_audit' => 1]);

                        $this->do_audit($journal_require->id, Auth::user()->id, '', 'journal_requiry', 1, 'add_journal');
                        $this->userActivity(Auth::user()->id, $journal_require->id, 6, 'Auth Cash Deposit');
                        if($till_transaction->deposit_type=='Penalty Fee'){
                            /* account_no, not drawdown_acc. tb_drawdown_account has no drawdown_acc
                               column, so this argument arrived null and error_reporting(0) swallowed
                               the notice. postBCashLoanPenaltyFee skips its drawdown filter when the
                               argument is empty and then matches on project + unit type + unit alone,
                               with no status filter, taking first() -- the LOWEST loan id ever written
                               on that unit. On a unit sold twice that is the older, written-off
                               contract of the previous buyer, and get_manual_payment_actual returns
                               immediately because its status is not 3 or 8, so the penalty never
                               reached the loan. LD-R104 aimed VANN SAMNANGCHAKRIYA's 2,000.00 at loan
                               3792 (‘Òš¸ ˆ»“ Ê¶”+ ¶€Ë …Ð“Ò‘“·˜›, written off); LD-H17 aimed 150.00 at 5548.
                               Both units carry two loans; the five penalties that posted correctly are
                               all on units carrying one. account_no holds the same value the loan
                               stores in drawdown_acc, so the filter now selects the right loan. */
                            $penaltyPosted = app('App\Http\Controllers\RepaymentController')->postBCashLoanPenaltyFee(floatval($notification->n_amount),'Penalty Fee',$drawDownAct->account_no,$drawDownAct->project_id,$drawDownAct->unit_type_id,$drawDownAct->unit_id,$drawDownAct->id);
                            if ($penaltyPosted !== true) throw new \RuntimeException('Penalty repayment did not complete.');
                            $dd_balance = $drawDownAct->balance - floatval($notification->n_amount);
                            $drawDownAct->balance = $dd_balance;
                            $drawDownAct->save();
                        }

                        $res = 'true';
                    }else{
                        $res['ins_notify'] = false;
                    }

                    //+ ------------------------ Project Clear up DD ------------------------
                    //+ Record posting records and then auto loan repayment by loan schedule
                    $schedule_date=date('Y-m-d');
                    $loan = Loan::select(['loans.*'])->where('loans.drawdown_acc', $till_transaction->drawdown_acc)->whereIn('loans.status', [2,3, 8])->first();
                    if (in_array($till_transaction->deposit_type, array('Loan Installment','Pay-Off')) && !$loan) throw new \RuntimeException('Loan-linked cash deposit has no eligible loan.');
                    if($till_transaction->deposit_type == "Loan Installment"){
                        if($loan){

                                $total_post_amount=$till_transaction->cash_in;
                                $total_principal = 0;
                                $total_interest = 0; 
                                $tranx_time= date('Y-m-d', strtotime($till_transaction->tranx_time));
                                $schedule_date=$tranx_time?$tranx_time:date('Y-m-d'); 
                                                            
                                $penalty_schedule_date=$schedule_date;

                                $schedule = RepaymentSchedule::where('loan_id',$loan->id)->get();                               
                             
                                if(!empty($schedule) && count($schedule) > 0){                                  
                                    foreach ( $schedule as $element ) {        
                                    
                                        $total_payment=floatval($element->interest) + floatval($element->principal);
                                        $total_tobe_piad=0;
                                       
                                            // ---------- Auto repayment ------------------
                                            $loan_id = $loan->id;
                                            $user_id = Auth::user()->id;
                                            $branch_id = Auth::user()->branch_id;
                            
                                            $coa_dd = DrawdownAccounts::select('id', 'balance', 'coa_id', 'client_id')
                                            ->with('coa')
                                            ->where('client_id', '=', $loan->client_id)
                                            ->where('account_no', $loan->drawdown_acc)->first();
                                            if (!empty($loan)) {
                                                $loan_payment = new LoanPayments();
                                                if (Auth::check()) {
                                                    $id = Auth::user()->id;
                                                    $loan_payment->user_id = $id;
                                                } else {
                                                    return redirect()->route('login');
                                                }


                                                $penalty_arr = LoanCalculate::getTotalPenalty($loan, $schedule_date);
                                                $data["overdue"] = $penalty_arr?$penalty_arr[2]:null;
                                                $penalty = $penalty_arr[4];
                                                $loan_payment->late_day = (!empty($data['overdue']) ? $data['overdue'] : 0);
                                                $penalty = $loan_payment->late_day?$penalty:0;
                                           
                                                


                                                $loan_payment->loan_id = $loan->id;
                                                $loan_payment->repayment_date = $schedule_date;
                                                $loan_payment->payment_month = $element->no;
                                                $loan_payment->penalty_amount = 0;

                                                $loan_payment->waived_penalty = 0;
                                                $loan_payment->repayment_owed =  0;
                                                $loan_payment->payment_type = 0;
                                                $loan_payment->condition_id =  0;
                                                $loan_payment->reason  = "Auto Loan Repayment";


                                                $check_payment = LoanPayments::select('*')
                                                    ->where('payment_month',$element->no)
                                                    ->where('loan_id',  $loan->id)
                                                    ->get();                                                                                              
                                                if (empty($check_payment) || count($check_payment) ===0) {                                                    

                                                    $loan_payment->note = "Auto Loan Repayment";
                                                    $loan_payment->status = 1; 

                                                    if(floatval($total_post_amount)>0){

                                                        if(floatval($total_post_amount)>=floatval($total_payment)){
                                                            $loan_payment->paid_interest = $element->interest;
                                                            $loan_payment->paid_principal = $element->principal;
    
                                                            $total_tobe_piad=floatval($loan_payment->paid_interest) + floatval($loan_payment->paid_principal);
                                                            $total_post_amount= $total_post_amount - floatval($total_tobe_piad);
    
                                                        }else{
                                                            if(floatval($total_post_amount)>=floatval($element->interest)){
                                                                $loan_payment->paid_interest =$element->interest;
                                                            }else{
                                                                $loan_payment->paid_interest =$total_post_amount;
                                                            }                                                      
                                                            
                                                            $total_tobe_piad=floatval($loan_payment->paid_interest);
                                                            $total_post_amount= $total_post_amount - floatval($total_tobe_piad);
    
                                                            
                                                             $loan_payment->paid_principal = floatval($total_post_amount)>0? floatval($total_post_amount):0;                                                        
                                                            
                                                            $total_tobe_piad= floatval($loan_payment->paid_principal);
                                                            $total_post_amount= $total_post_amount - floatval($total_tobe_piad);
                                                            
                                                        }

                                                        
                                                        $total_transaction_amount = floatval($element->principal) + floatval($element->interest) + floatval($penalty);
                                                        $owed_amount = floatval($total_transaction_amount) - (floatval($loan_payment->paid_principal) + floatval($loan_payment->paid_interest));
                                                        $loan_payment->repayment_owed = (intval($owed_amount * 100) > 0) ? $owed_amount : 0;
                                                       
                                                        $loan_payment->condition_id = (intval($owed_amount * 100) > 0) ? 1 : 0;
                                                        $loan_payment->status = (intval($owed_amount * 100) > 0) ? 0 : 1; /* 0: Owed, 1: completed */


                                                        if ($loan_payment->save()) {
                                                            $loan->last_schedule_date =$element->schedule_date;
                                
                                                                $last_transaction = TransactionsRequiry::select('id', 'balance')->where('loan_id', '=', $loan->id)
                                                                    ->orderBy('id', 'DESC')->first();
                                                                
                                                                $transaction = new TransactionsRequiry();
                                                                $transaction->loan_id = $loan->id;
                                                                $transaction->trans_date = $loan_payment->repayment_date;
                                                                $transaction->trans_type = "Auto Loan Repayment";
                                                                $transaction->description = "Auto Loan Repayment";
                                                                $transaction->amount = floatval($loan_payment->paid_principal) + floatval($loan_payment->paid_interest);
                                                                // $transaction->penalty = $loan_payment->penalty_amount;

                                                                $transaction->principal = $loan_payment->paid_principal?$loan_payment->paid_principal:0;
                                                                $transaction->interest = $loan_payment->paid_interest?$loan_payment->paid_interest:0;

                                                                $transaction->balance = $last_transaction->balance - (floatval($loan_payment->paid_interest) + floatval($loan_payment->paid_principal));
                                                                $transaction->user_id = $loan_payment->user_id;
                                                                $transaction->is_audit = 1;
                                                                $transaction->loan_repayment_type='loan';
                                                                if($transaction->save()){
                                                                    $loan->save();
                                                                    
                                                                    $dd_balance = floatval($drawDownAct->balance)- (floatval($loan_payment->paid_interest) + floatval($loan_payment->paid_principal));
                                                                    $drawDownAct->balance = $dd_balance;
                                                                    $drawDownAct->save();
                                                                    
                                                                    $total_principal = floatval($total_principal) + floatval($loan_payment->paid_principal);
                                                                    $total_interest = floatval($total_interest) + floatval($loan_payment->paid_interest);

                                                                    // Update Client Loan Account
                                                                    $clientLoanAccount = ClientLoanAccounts::select(['*'])->where('id', '=', $loan->loan_account_id)->first();
                                                                    if (!empty($clientLoanAccount) && count($clientLoanAccount) > 0) {
                                                                        $clientLoanAccount->activated_on = $schedule_date;
                                                                        $clientLoanAccount->status = 2; // Activate(Std)
                                                                        if($element->type==='loan'){
                                                                        $clientLoanAccount->balance = floatval($clientLoanAccount->balance) - floatval($loan_payment->paid_principal);
                                                                        }
                                                                        if($element->type==='downpayment'){
                                                                            $clientLoanAccount->balance_downpayment = floatval($clientLoanAccount->balance_downpayment) -  floatval($loan_payment->paid_principal);
                                                                        }

                                                                        $clientLoanAccount->save();
        
                                                                    }
                                                                    

                                                                    // Journal
                                                                    $journal = new JournalRequiry();
                                                                    $journal->tran_id = 0;
                                                                    $journal->entry_date = $schedule_date;
                                                                    $journal->invoice_number = null;
                                                                    $journal->description = "Auto Repayment";
                                                                    $journal->user_id = Auth::user()->id;
                                                                    $journal->is_audit = 1;
                                                                    $journal->disburs_loan_id = $loan->id;
                                                                    if ($journal->save()) {        
                                                                    $gl_principle = CoaCategory::where('type',6)->where('name','Stand-L-PHL-Indi-RT >1 year')->where('currency',2)->first(); 
                                                                    $gl_interest = CoaCategory::where('type',6)->where('name','AIR-Stand-L-PHL-Indi-RT<=1 year')->where('currency',2)->first();  

                                                                    $total_jl_credit=0;
                                                                    if(floatval($loan_payment->paid_principal)>0){
                                                                            $old_jd = new JournalDetail();
                                                                            $old_jd->journal_id = $journal->id;
                                                                            $old_jd->coa_id=$gl_principle->id;
                                                                            $old_jd->branch_code=$loan->company_branch_id;
                                                                            $old_jd->p_debit = 0;
                                                                            $old_jd->p_credit = 0;
                                                                            $old_jd->debit = 0;
                                                                            $old_jd->credit = floatval($loan_payment->paid_principal);
                                                                            $old_jd->b_debit = 0;
                                                                            $old_jd->b_credit = 0;
                                                                            $old_jd->description = "Auto Loan Repayment - ".$gl_principle->name . ' (' . $gl_principle->account_code . ')';
                                                                            $old_jd->is_audit = 1;
                                                                            $old_jd->disburs_loan_id =  $loan->id;
                                                                            $old_jd->save();

                                                                            $total_jl_credit=floatval($loan_payment->paid_principal);
                                                                            
                                                                        }

                                                                        if(floatval($loan_payment->paid_interest)>0){
                                                                            $old_jd = new JournalDetail();
                                                                            $old_jd->journal_id = $journal->id;
                                                                            $old_jd->coa_id=$gl_interest->id;
                                                                            $old_jd->branch_code=$loan->company_branch_id;
                                                                            $old_jd->p_debit = 0;
                                                                            $old_jd->p_credit = 0;
                                                                            $old_jd->debit = 0;
                                                                            $old_jd->credit = floatval($loan_payment->paid_interest);
                                                                            $old_jd->b_debit = 0;
                                                                            $old_jd->b_credit = 0;
                                                                            $old_jd->description =   "Auto Loan Repayment - ".$gl_interest->name . ' (' . $gl_interest->account_code . ')';
                                                                            $old_jd->is_audit = 1;
                                                                            $old_jd->disburs_loan_id =  $loan->id;
                                                                            $old_jd->save();

                                                                            $total_jl_credit=floatval($total_jl_credit) + floatval($loan_payment->paid_interest);
                                                                            
                                                                        }

                                                                        if(floatval($total_jl_credit) - (floatval($loan_payment->paid_principal)+floatval($loan_payment->paid_interest)) >=0){
                                                                            $old_jd = new JournalDetail();
                                                                            $old_jd->journal_id = $journal->id;
                                                                            $old_jd->coa_id=$coa_dd->coa_id;
                                                                            $old_jd->branch_code=$loan->company_branch_id;
                                                                            $old_jd->p_debit = 0;
                                                                            $old_jd->p_credit = 0;
                                                                            $old_jd->debit = floatval($loan_payment->paid_principal)+floatval($loan_payment->paid_interest);
                                                                            $old_jd->credit = 0;
                                                                            $old_jd->b_debit = 0;
                                                                            $old_jd->b_credit = 0;
                                                                            $old_jd->description =   "Auto Loan Repayment - ".$coa_dd->coa->name . ' (' . $coa_dd->coa->account_code . ')';
                                                                            $old_jd->is_audit = 1;
                                                                            $old_jd->disburs_loan_id =  $loan->id;
                                                                            $old_jd->save();
                                                                        }
                                                                        
                                                                    }
                                                                    

                                                                }
                                                                                                                        
                                                                
                                                            
                                                        }
                                                    }
                                                }else { 
                                                    
                                                    $total_paid=0;
                                                    if(!empty($check_payment) && count($check_payment) > 0){
                                                        $sum_paid_interest=floatval($check_payment->sum('paid_interest')); 
                                                        $sum_paid_principal=floatval($check_payment->sum('paid_principal')); 
                                                        $total_paid=floatval($sum_paid_interest) + floatval($sum_paid_principal);                                                        
                                                    
                                                    } 
                                               
                                                    $total_payment=floatval($element->interest) + floatval($element->principal);
                                                    $total_tobe_piad=0;   
                                                
                                                    if(round($total_paid,2)<round($total_payment,2)){
                                                        $total_not_paid=floatval($total_payment) - floatval($total_paid);                                                   
                                                        
                                                        $loan_payment->note = "Auto Loan Repayment";
                                                        $loan_payment->status = 1; 
                                                        if(floatval($total_post_amount)>0){

                                                            if(floatval($total_post_amount)>=floatval($total_not_paid)){

                                                                if(floatval($sum_paid_interest)<floatval($element->interest)){
                                                                    $loan_payment->paid_interest =floatval($element->interest)-floatval($sum_paid_interest);
                                                                    $total_tobe_piad=floatval($loan_payment->paid_interest);
                                                                    $total_post_amount= $total_post_amount - floatval($total_tobe_piad);
                                                                }
    
                                                                if(floatval($sum_paid_principal)<floatval($element->principal)){
                                                                $loan_payment->paid_principal =floatval($element->principal)- floatval($sum_paid_principal);
    
                                                                $total_tobe_piad= floatval($loan_payment->paid_principal);
                                                                $total_post_amount= $total_post_amount - floatval($total_tobe_piad);
                                                                }
        
                                                            }else{

                                                                if(floatval($sum_paid_interest)<floatval($element->interest)){
                                                                    $total_not_paid_interest =floatval($element->interest)-floatval($sum_paid_interest);

                                                                    $loan_payment->paid_interest =floatval($total_post_amount)< floatval($total_not_paid_interest)?floatval($total_post_amount):floatval($total_not_paid_interest);
                                                                    $total_tobe_piad=floatval($loan_payment->paid_interest);
                                                                    $total_post_amount= $total_post_amount - floatval($total_tobe_piad);
                                                                    

                                                                }
    
                                                                if(floatval($sum_paid_principal)<floatval($element->principal)){ 
                                                                    $total_not_paid_principal =floatval($element->principal)-floatval($sum_paid_principal);

                                                                    $loan_payment->paid_principal =floatval($total_post_amount)< floatval($total_not_paid_principal)?floatval($total_post_amount):floatval($total_not_paid_principal);
                
                                                                    $total_tobe_piad=floatval($loan_payment->paid_principal);
                                                                    $total_post_amount= $total_post_amount - floatval($total_tobe_piad);
                                                                }



                                                                
                                                            }

                                                            $total_transaction_amount = floatval($element->principal) + floatval($element->interest) + floatval($penalty);
                                                            $owed_amount = floatval($total_transaction_amount) -(floatval($total_paid)) - (floatval($loan_payment->paid_principal) + floatval($loan_payment->paid_interest));
                                                            $loan_payment->repayment_owed = (intval($owed_amount * 100) > 0) ? $owed_amount : 0;
                                                            $loan_payment->condition_id = (intval($owed_amount * 100) > 0) ? 1 : 0;
                                                            $loan_payment->status = (intval($owed_amount * 100) > 0) ? 0 : 1; /* 0: Owed, 1: completed */

                                                            
                                                            if ($loan_payment->save()) {
                                                                $loan->last_schedule_date =$element->schedule_date;
                                    
                                                                    $last_transaction = TransactionsRequiry::select('id', 'balance')->where('loan_id', '=', $loan->id)
                                                                        ->orderBy('id', 'DESC')->first();
                                                                    
                                                                    $transaction = new TransactionsRequiry();
                                                                    $transaction->loan_id = $loan->id;
                                                                    $transaction->trans_date = $loan_payment->repayment_date;
                                                                    $transaction->trans_type = "Loan Repayment";
                                                                    $transaction->description = "Auto Loan Repayment";
                                                                    $transaction->amount = floatval($loan_payment->paid_principal) + floatval($loan_payment->paid_interest);
                                                                    $transaction->penalty = $loan_payment->penalty_amount;
                                                                    $transaction->principal = $loan_payment->paid_principal?$loan_payment->paid_principal:0;
                                                                    $transaction->interest = $loan_payment->paid_interest?$loan_payment->paid_interest:0;
                                                                    $transaction->balance = $last_transaction->balance - (floatval($loan_payment->paid_interest) + floatval($loan_payment->paid_principal));
                                                                    $transaction->user_id = $loan_payment->user_id;
                                                                    $transaction->is_audit = 1;
                                                                    $transaction->loan_repayment_type='loan';
                                                                    if($transaction->save()){
                                                                        $loan->save();                                                                        
                                                                        $dd_balance = floatval($drawDownAct->balance) - (floatval($loan_payment->paid_interest) + floatval($loan_payment->paid_principal));
                                                                        $drawDownAct->balance = $dd_balance;
                                                                        $drawDownAct->save();
        
                                                                        
                                                                        $total_principal = floatval($total_principal) + floatval($loan_payment->paid_principal);
                                                                        $total_interest = floatval($total_interest) + floatval($loan_payment->paid_interest);
                                                                    
                                                                        // Update Client Loan Account
                                                                        $clientLoanAccount = ClientLoanAccounts::select(['*'])->where('id', '=', $loan->loan_account_id)->first();
                                                                        if (!empty($clientLoanAccount) && count($clientLoanAccount) > 0) {
                                                                            $clientLoanAccount->activated_on = $schedule_date;
                                                                            $clientLoanAccount->status = 2; // Activate(Std)
                                                                            if($element->type==='loan'){
                                                                                $clientLoanAccount->balance = floatval($clientLoanAccount->balance) - floatval($loan_payment->paid_principal);
                                                                                }
                                                                            if($element->type==='downpayment'){
                                                                                $clientLoanAccount->balance_downpayment = floatval($clientLoanAccount->balance_downpayment) -  floatval($loan_payment->paid_principal);
                                                                            }

                                                                            $clientLoanAccount->save();
                                                                        }
                                                                            

                                                                        // Journal
                                                                        $journal = new JournalRequiry();
                                                                        $journal->tran_id = 0;
                                                                        $journal->entry_date = $schedule_date;
                                                                        $journal->invoice_number = null;
                                                                        $journal->description = "Auto Repayment";
                                                                        $journal->user_id = Auth::user()->id;
                                                                        $journal->is_audit = 1;
                                                                        $journal->disburs_loan_id = $loan->id;
                                                                        if ($journal->save()) {        
                                                                        $gl_principle = CoaCategory::where('type',6)->where('name','Stand-L-PHL-Indi-RT >1 year')->where('currency',2)->first(); 
                                                                        $gl_interest = CoaCategory::where('type',6)->where('name','AIR-Stand-L-PHL-Indi-RT<=1 year')->where('currency',2)->first();  

                                                                        $total_jl_credit=0;
                                                                        if(floatval($loan_payment->paid_principal)>0){
                                                                                $old_jd = new JournalDetail();
                                                                                $old_jd->journal_id = $journal->id;
                                                                                $old_jd->coa_id=$gl_principle->id;
                                                                                $old_jd->branch_code=$loan->company_branch_id;
                                                                                $old_jd->p_debit = 0;
                                                                                $old_jd->p_credit = 0;
                                                                                $old_jd->debit = 0;
                                                                                $old_jd->credit = floatval($loan_payment->paid_principal);
                                                                                $old_jd->b_debit = 0;
                                                                                $old_jd->b_credit = 0;
                                                                                $old_jd->description = "Auto Loan Repayment - ".$gl_principle->name . ' (' . $gl_principle->account_code . ')';
                                                                                $old_jd->is_audit = 1;
                                                                                $old_jd->disburs_loan_id =  $loan->id;
                                                                                $old_jd->save();
                                                                                $total_jl_credit=floatval($loan_payment->paid_principal);
                                                                                
                                                                            }

                                                                            if(floatval($loan_payment->paid_interest)>0){
                                                                                $old_jd = new JournalDetail();
                                                                                $old_jd->journal_id = $journal->id;
                                                                                $old_jd->coa_id=$gl_interest->id;
                                                                                $old_jd->branch_code=$loan->company_branch_id;
                                                                                $old_jd->p_debit = 0;
                                                                                $old_jd->p_credit = 0;
                                                                                $old_jd->debit = 0;
                                                                                $old_jd->credit = floatval($loan_payment->paid_interest);
                                                                                $old_jd->b_debit = 0;
                                                                                $old_jd->b_credit = 0;
                                                                                $old_jd->description =   "Auto Loan Repayment - ".$gl_interest->name . ' ('. $gl_interest->account_code . ')';
                                                                                $old_jd->is_audit = 1;
                                                                                $old_jd->disburs_loan_id =  $loan->id;
                                                                                $old_jd->save();

                                                                                $total_jl_credit=floatval($total_jl_credit) + floatval($loan_payment->paid_interest);
                                                                                
                                                                            } 
                                                                            if(floatval($total_jl_credit) - (floatval($loan_payment->paid_principal)+floatval($loan_payment->paid_interest)) >=0){
                                                                                $old_jd = new JournalDetail();
                                                                                $old_jd->journal_id = $journal->id;
                                                                                $old_jd->coa_id=$coa_dd->coa_id;
                                                                                $old_jd->branch_code=$loan->company_branch_id;
                                                                                $old_jd->p_debit = 0;
                                                                                $old_jd->p_credit = 0;
                                                                                $old_jd->debit = floatval($loan_payment->paid_principal)+floatval($loan_payment->paid_interest);
                                                                                $old_jd->credit = 0;
                                                                                $old_jd->b_debit = 0;
                                                                                $old_jd->b_credit = 0;
                                                                                $old_jd->description =   "Auto Loan Repayment - ".$coa_dd->coa->name . ' (' . $coa_dd->coa->account_code . ')';
                                                                                $old_jd->is_audit = 1;
                                                                                $old_jd->disburs_loan_id =  $loan->id;
                                                                                $old_jd->save();
                                                                            }
                                                                            
                                                                        }
                                                                        
        
                                                                    }                                                                                                                         
                                                                    
                                                                
                                                            }
                                                        }

                                                    }
                                                    
                                                }  
                                               
                                                $schedule_update = RepaymentSchedule::where('loan_id', $loan->id)->where('no', $element->no)->first();
                                                if($schedule_update){
                                                    $paidSchedule = LoanPayments::where('loan_id', $loan->id)->where('payment_month', $element->no);
                                                    $paidPrincipal = $paidSchedule->sum('paid_principal');
                                                    $paidInterest = $paidSchedule->sum('paid_interest');
                                                    $schedule_update->status = (round($paidPrincipal,2) >= round($schedule_update->principal,2) && round($paidInterest,2) >= round($schedule_update->interest,2)) ? 1 : 0;
                                                    $schedule_update->save();
                                                }




                                            }
                                            // ---------- End -----------------------------
    
                                            
                                        
                                    }
                                                       
                                    $posting_transaction = new TransactionsPosting();
                                    $posting_transaction->till_transaction_id=$till_transaction->id;
                                    $posting_transaction->loan_id = $loan->id;
                                    $posting_transaction->posting_date = $schedule_date;
                                    $posting_transaction->trans_type = $till_transaction->deposit_type;
                                    $posting_transaction->description = $till_transaction->deposit_type.' '.$drawDownAct->units->code.' Date:'.$schedule_date;
                                    $posting_transaction->amount = floatval($till_transaction->cash_in);
                                    $posting_transaction->total_penalty = 0;
                                    $posting_transaction->total_principal = $total_principal;
                                    $posting_transaction->total_interest = $total_interest;
                                    $posting_transaction->user_id = Auth::user()->id;
                                    $posting_transaction->status=1;
                                    $posting_transaction->methode=$till_transaction->methode;
                                    $posting_transaction->drawdown_acc_id=$till_transaction->drawdown_acc_id;
                                    $posting_transaction->save();
                                    
                                }
                                    
                        }

                    }else{
                        $tranx_time= date('Y-m-d', strtotime($till_transaction->tranx_time));
                        $schedule_date=$tranx_time?$tranx_time:date('Y-m-d'); 

                        if($till_transaction->deposit_type == "Pay-Off"){
                            $dpDateClone = date('Y-m-d');
                            $get_restructure_balance = get_restructure_balance($loan);
                            $sum_principal = $get_restructure_balance['balance_loan'];
                            $interest = get_total_int_till_today_restructure($loan ,$loan->client_loan_account, $dpDateClone,$sum_principal)['interest'];

                            $checkLoanPayoff = TransactionsPosting::where('loan_id', $loan->id)->where('trans_type','Pay-Off')->get();
                            $sum_posting_interest=0;
                            if(!empty($checkLoanPayoff) && count($checkLoanPayoff) > 0){
                                $sum_posting_interest=floatval($checkLoanPayoff->sum('total_interest')); 
                            }
                           
                            $total_interest_post=max(0, floatval($interest) - floatval($sum_posting_interest));


                            if(floatval($till_transaction->cash_in)<floatval($total_interest_post)){
                                $total_interest_post=floatval($till_transaction->cash_in);
                            }


                            $total_principal_post=floatval($till_transaction->cash_in) - floatval($total_interest_post);                            

                           


                            $posting_transaction = new TransactionsPosting();
                            $posting_transaction->loan_id = $loan?$loan->id:0;
                            $posting_transaction->posting_date = $schedule_date;
                            $posting_transaction->trans_type = $till_transaction->deposit_type;
                            $posting_transaction->description = $till_transaction->deposit_type.' '.$drawDownAct->units->code.' Date:'.$schedule_date;
                            $posting_transaction->amount = floatval($till_transaction->cash_in);
                            $posting_transaction->total_penalty = 0;
                            $posting_transaction->total_principal =floatval($total_principal_post);
                            $posting_transaction->total_interest = floatval($total_interest_post);
                            $posting_transaction->user_id = Auth::user()->id;
                            $posting_transaction->status=1;
                            $posting_transaction->methode=$till_transaction->methode;
                            $posting_transaction->drawdown_acc_id=$till_transaction->drawdown_acc_id;
                            $posting_transaction->loan_repayment_type='loan';
                            $posting_transaction->save();
                        }else{
                            $posting_transaction = new TransactionsPosting();
                            $posting_transaction->loan_id = $loan?$loan->id:0;
                            $posting_transaction->posting_date = $schedule_date;
                            $posting_transaction->trans_type = $till_transaction->deposit_type;
                            $posting_transaction->description = $till_transaction->deposit_type.' '.$drawDownAct->units->code.' Date:'.$schedule_date;
                            $posting_transaction->amount = floatval($till_transaction->cash_in);
                            $posting_transaction->total_penalty = 0;
                            $posting_transaction->total_principal = 0;
                            $posting_transaction->total_interest = 0;
                            $posting_transaction->user_id = Auth::user()->id;
                            $posting_transaction->status=1;
                            $posting_transaction->methode=$till_transaction->methode;
                            $posting_transaction->drawdown_acc_id=$till_transaction->drawdown_acc_id;
                            $posting_transaction->loan_repayment_type='loan';
                            $posting_transaction->save();
                        }
                        
                    }
                    // ------------------------ End -----------------------------------------


                }elseif(Request::input('flag_button') == "ng"){
                    $notification = Notification::where('id', '=', Request::input('not_id'))->lockForUpdate()->first();
                    if (!$notification) throw new \RuntimeException('Cash deposit notification does not exist.');
                    $till_transaction = TillTransaction::where('id', '=', $notification->n_source_id)->lockForUpdate()->first();
                    if (!$till_transaction || $notification->n_user_id != Auth::user()->id || $till_transaction->branch_id != Auth::user()->branch_id) throw new \RuntimeException('Cash deposit rejection is outside the assigned user or branch.');
                    if ((int)$till_transaction->approve_status !== 0) throw new \RuntimeException('Only a pending cash deposit can be rejected.');
                    $till_transaction_arr = TillTransaction::select("id","till_account_id","drawdown_acc_id","from_account","to_account","drawdown_acc","till_user_id","branch_id","operate_by","tran_currency_id","slips_id", "tranx_time", "type", "methode","cash_in","cash_out","balance","description","status","approve_status")->where('id', '=', $notification->n_source_id)->get()->first()->toArray();
                    $notification_action = array_merge($till_transaction_arr,['reject_by'=>Auth::id(),'reject_id'=>$till_transaction_arr['id']]);
                    unset($notification_action['id']);
                    $till_reject = TillTransactionReject::insert($notification_action);
                    $drawDownAct = DrawdownAccounts::where('account_name', $till_transaction->from_account)->where('account_no', $till_transaction->drawdown_acc)->where('currency', $till_transaction->tran_currency_id)->first();
                    if(!$drawDownAct){
                        $drawDownAct = DrawdownAccounts::where('account_no', $till_transaction->drawdown_acc)->where('currency', $till_transaction->tran_currency_id)->first();
                    }
                    if($drawDownAct){
                        $till_account = Teller::where('id', $till_transaction->till_account_id)->first();
                        $postedSlip = slips::where('id', $till_transaction->slips_id)->first();
                        if (!$postedSlip) throw new \RuntimeException('Cash deposit slip does not exist.');
                        $journal_require = JournalRequiry::where('id', $postedSlip->jr_id)->lockForUpdate()->first();
                        if (!$journal_require || $journal_require->user_id != $till_transaction->operate_by) throw new \RuntimeException('Cash deposit journal does not match its slip.');
                        JournalDetail::where('journal_id', '=', $journal_require->id)->delete();
                        if($journal_require){
                            $journal_require->delete();
                        }
                        if($till_transaction){
                            $till_transaction->delete();
                        }
                        if($notification){
                            $notification->delete();
                        }
                        $this->userActivity(Auth::user()->id, $journal_require->id, 6, 'Reject Cash Deposit');
                        $res = 'true';
                    }else{
                        $res['ins_notify'] = false;
                    }
                }
            }
            DB::commit();
            if ($cashDepositSavepoint) DB::statement('RELEASE SAVEPOINT ' . $cashDepositSavepoint);
          return $res;
        } catch (\Exception $e) {
            \Log::error('postDeposit rolled back, nothing posted. drawdown_acc='.Request::input('drawdown_acc').' amount='.Request::input('amount').' user_id='.Auth::user()->id.' : '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
            DB::rollback();
            if ($cashDepositSavepoint) { DB::statement('ROLLBACK TO SAVEPOINT ' . $cashDepositSavepoint); DB::statement('RELEASE SAVEPOINT ' . $cashDepositSavepoint); }
        }
    }

    public function getWithdraw()
    {
        $search = Request::input('term');
        $widthdraw = DrawdownAccounts::with(['Client','Client.general','currency_tbl','projects','unitType','units'])->where('status', '=', 1);
        if(!empty($search)){
            $widthdraw = $widthdraw->where(function($q) use($search){
               $q->where('account_no','like','%'.$search.'%')
               ->orWhere('account_name','like','%'.$search.'%');
            });
            $widthdraw = $widthdraw->where(function($q) use($search){
                $q->where('account_no','like','%'.$search.'%')
                ->orWhere('account_name','like','%'.$search.'%');
            })->orWhereHas('units', function($query) use ($search){
                $query->where('code','like','%'.$search.'%');
            })->orWhereHas('projects', function($query) use ($search){
                $query->where('dealer','like','%'.$search.'%');
            })->orWhereHas('unitType', function($query) use ($search){
                $query->where('name','like','%'.$search.'%');
            });
        }

        $widthdraw = $widthdraw->limit(100)->orderBy('account_name', 'ASC')->get();
        $widthdraw['till_account'] = Teller::where('assign_user_id', auth()->user()->id)->get();
        if(!count($widthdraw['till_account'])) {
            return ['till_account'=>false];
        }
//        $roles_id = Role::where('role', 'loan_admin')->first();
//        $admin = User::with('role')->where('role_id', '=', $roles_id->id)->where('branch_id', auth()->user()->branch_id)->get();

		$roles_id = Role::whereIn('role', ['chief_of_teller', 'bm', 'admin', 'accm', 'acco','cas'])->get();
        $role_arr = [];
        foreach($roles_id as $r){
            array_push($role_arr, $r->id);
        }
        $admin = User::with('role')->whereIn('role_id', $role_arr)
                                    ->where('branch_id', auth()->user()->branch_id)
                                    ->where('status', 1)
                                    ->orderBy('name', 'ASC')->get();
        
        $currencies = Currency::select('id', 'code')->get();
        $currency_list = [];
        foreach($currencies as $cur){
            $currency_list[$cur->id] = $cur->code;
        }
        return ['withdraw' => $widthdraw, 'loan_admin' => $admin, count($widthdraw['till_account']), 'currency_list'=>$currency_list];
    }

    public function postWithdraw()
    {
        DB::beginTransaction();
        try {
            if(Request::input('not_id') == 0)
            {
                $record_date = Request::input('till_date')!=""?Request::input('till_date'):date("Y-m-d H:i:s");
                if($record_date === 'undefined'){
                    $record_date = date("Y-m-d H:i:s");
                }
                // dd($record_date);
                //$record_date = date("Y-m-d H:i:s");
                $till_account = Teller::select('id', 'assign_user_id', 'balance', 'account_no', 'account_name')
                    ->where('assign_user_id', '=', auth()->user()->id)
                    ->where('currency_id', '=', Request::input('currency_id'))
                    //->where('balance', '>', 0)
                    ->where('status', '=', 0)// till Account is open
                    ->first();
                if (count($till_account) <= 0) {
                    return ['till_state' => false];
                }

                $till_transaction = new TillTransaction();
                $till_transaction->till_account_id = $till_account->id;
                $till_transaction->till_user_id = $till_account->assign_user_id;
                $till_transaction->from_account = Request::input('client_name');
                $till_transaction->to_account = $till_account->account_no . ' / ' . $till_account->account_name;
                $till_transaction->balance = floatval($till_account->balance);
                if(Request::input('types') == "Cash on Hand-Teller"){
                  $till_transaction->balance -= floatval(Request::input('amount'));
                }
                if($till_transaction->balance < 0) return ['till_state' => false];
                $till_transaction->cash_out = Request::input('amount');
                $till_transaction->branch_id = auth()->user()->branch_id;
                $till_transaction->operate_by = auth()->user()->id;
                $till_transaction->tranx_time = $record_date;
                $till_transaction->tran_currency_id = Request::input('currency_id');
                $till_transaction->drawdown_acc = Request::input('drawdown_acc');
                $till_transaction->description = Request::input('description');
                $till_transaction->methode = Request::input('types');
                $till_transaction->type = 'Withdraw';
                $res['up_wd'] = DrawdownAccounts::where('id', '=', Request::input('id'))->first();
                $res['up_till'] = (Request::input('types') == "Cash on Hand-Teller")? Teller::where('id', '=', $till_account->id)->first() : 1;
                if (!empty($res['up_wd']) && !empty($res['up_till'])) {
                    $client = DrawdownAccounts::where('client_id', Request::input('client_id'))->where('account_no', Request::input('drawdown_acc'))->where('currency', $till_transaction->tran_currency_id)->first();
                    $branch_code = $client->branch;

                    $slips = new slips();
                    // $print_cnt = slips::all()->last()->print_cnt;
                    $print_cnt  = slips::latest()->take(1)->first();
                    $print_cnt = $print_cnt->print_cnt;
                    
                    $slips->draw_acc_id = $client->id;
                    $slips->client_id = Request::input('client_id');
                    $slips->user_id = auth()->user()->id;
                    $slips->create_date = $record_date;
                    $slips->currency_id = Request::input('currency_id');
                    $slips->types = Request::input('types');
                    $slips->amount = Request::input('amount');
                    $slips->trans_type = 'withdraw';
                    $slips->check_num = Request::input('check_num');
                    $slips->bank_name = Request::input('bank_name');
                    $slips->description = Request::input('description');
                    $slips->prepared_by = auth()->user()->id;
                    $slips->checked_by = auth()->user()->id;
                    $slips->authorized_by = auth()->user()->id;
                    $slips->print_cnt = (int)$print_cnt + 1;
                    $journal_arr = [];
                    $teller_coa_id = CoaCategory::where('name', 'Like', '%'.Request::input('types').'%')->where('currency', Request::input('currency_id'))->first()->id;
                    array_push($journal_arr, [$client->coa_id, Request::input('amount'), Request::input('description'),
                                              $teller_coa_id, Request::input('amount'), Request::input('description'),
                                               Request::input('description'),
                                               'withdraw'
                                            ]);
                    $postedJournal = record_journal_no_trans(null, $record_date, $journal_arr, auth()->user()->branch_id, auth()->user()->id, 0,null,1);

                    if (!is_array($postedJournal)) throw new \RuntimeException('Cashier journal did not complete.');
                          $slips->jr_id = $postedJournal['journal_id'];
                    $slips->jd_id = $postedJournal['journal_detail_id'];
                    if ($slips->save()) {

                        $till_transaction->slips_id = $slips->id;
                        $till_transaction->drawdown_acc_id = $client->id;
                        if ($till_transaction->save()) {

                            $this->userActivity(Auth::user()->id, $slips->jd_id, 0, 'Add JournalDetail', Request::fullUrl());
                            $res['ins_notify'] = $this->notification->setNotification([
                                Request::input('user_id'),
                                false,
                                $till_transaction->id,
                                Request::input('user_id'),
                                $till_account->id,
                                'withdraw',
                                $record_date,
                                $till_transaction->description,
                                Request::input('amount')
                            ]);
                        }
                    }
                }
                if (!empty($res['ins_notify'])) {
                    if (Request::has('if_print') && Request::input('if_print')==1) {
                        $res['print_url'] = url('teller/print/withdraw/'.$slips->id);
                    }
                }
            }else{
                if(Request::input('flag_button') == "ok"){// Approve withdraw
                    $notification = Notification::where('id', '=', Request::input('not_id'))->first();
                    $till_transaction = TillTransaction::where('id', '=', $notification->n_source_id)->first();
                    // Update balance
                    $drawDownAct = DrawdownAccounts::where('account_name', $till_transaction->from_account)->where('account_no', $till_transaction->drawdown_acc)->where('currency', $till_transaction->tran_currency_id)->first();
                    $dd_balance = $drawDownAct->balance - floatval($notification->n_amount);
                    $drawDownAct->balance = $dd_balance;
                    $drawDownAct->save();

                    $till_account = Teller::where('id', $till_transaction->till_account_id)->first();
                    $till_transaction->approve_status = 1;
                    if($till_transaction->methode=='Cash on Hand-Teller'){
                        $till_account->balance = $till_account->balance - floatval($notification->n_amount);
                    }
                    $till_transaction->balance = $till_account->balance;
                    $till_transaction->save();
                    $till_account->save();

                    $journal_require = JournalRequiry::where('description', '=', $notification->n_description)
                                        ->where('user_id', '=', $till_transaction->operate_by)
                                        ->where('entry_date', '=', $till_transaction->tranx_time)
                                        ->first();
                    $journal_require->is_audit = 1;
                    $journal_require->save();
                    JournalDetail::where('journal_id', '=', $journal_require->id)->update(['is_audit' => 1]);

                    $this->do_audit($journal_require->id, Auth::user()->id, '', 'journal_requiry', 1, 'add_journal');
                    $this->userActivity(Auth::user()->id, $journal_require->id, 6, 'Auth Cash Withdraw');
                    $res = 'true';
                }elseif(Request::input('flag_button') == "ng"){
                    $notification = Notification::where('id', '=', Request::input('not_id'))->first();
                    $till_transaction = TillTransaction::where('id', '=', $notification->n_source_id)->first();

                    $till_transaction_arr = TillTransaction::where('id', '=', $notification->n_source_id)->get()->first()->toArray();
                    $notification_action = array_merge($till_transaction_arr,['reject_by'=>Auth::id(),'reject_id'=>$till_transaction_arr['id']]);
                    unset($notification_action['id']);
                    $till_reject = TillTransactionReject::insert($notification_action);

                    $drawDownAct = DrawdownAccounts::where('account_name', $till_transaction->from_account)->where('account_no', $till_transaction->drawdown_acc)->where('currency', $till_transaction->tran_currency_id)->first();
                    $till_account = Teller::where('id', $till_transaction->till_account_id)->first();
                    $journal_require = JournalRequiry::where('description', '=', $notification->n_description)
                                        ->where('user_id', '=', $till_transaction->operate_by)
                                        ->where('entry_date', '=', $till_transaction->tranx_time)
                                        ->first();
                    JournalDetail::where('journal_id', '=', $journal_require->id)->delete();
                    $journal_require->delete();
                    $till_transaction->delete();
                    $notification->delete();
                    $this->userActivity(Auth::user()->id, $journal_require->id, 6, 'Reject Cash Withdraw');
                    $res = 'true';
                }
            }
            DB::commit();
          return $res;
        } catch (\Exception $e) {
            DB::rollback();
        }
    }

    function teller_dwjournal($type = 1)
    {
        $client = DrawdownAccounts::where('client_id', Request::input('id'))->first();

        $amount = Request::input('amount');
        $name = Request::input('name');
        $branch_code = $client->branch;

        $coa_1 = CoaCategory::select('id', 'account_code', 'name', 'currency')->where('type', 7);
        $coa_1 = $coa_1->where('name', 'Drawdown Account - ' . trim($name))->first();

        $coa_2 = CoaCategory::select('id', 'account_code', 'name');
        $coa_2->where('type', 6)->where('currency', $coa_1->currency);
        $coa_2 = $coa_2->where('name', 'Cash in Vault')->first();

        $output = '<div class="form-group teller_dwjournal">';
        $output .= '<h4>' . trans('multiple.journal_title') . '</h4><hr/>';
        $params = array(
            'debit' => $amount,
            'credit' => $amount
        );

        if ($type == 1) {
            $params['parent_debit'] = $coa_2->id;
            $params['parent_credit'] = $coa_1->id;
            $params['parent_debit_label'] = $coa_2->name . ' (' . $branch_code . $coa_2->account_code . ')';
            $params['parent_credit_label'] = $coa_1->name . ' (' . $branch_code . $coa_1->account_code . ')';
        } else {
            $params['parent_debit'] = $coa_1->id;
            $params['parent_credit'] = $coa_2->id;
            $params['parent_debit_label'] = $coa_1->name . ' (' . $branch_code . $coa_1->account_code . ')';
            $params['parent_credit_label'] = $coa_2->name . ' (' . $branch_code . $coa_2->account_code . ')';
        }
        $output .= getJournalDetail($params);
        $output .= '</div>';

        die($output);
    }

    public function delete_notification($id)
    {

        if (Request::ajax()) {

            if (empty($id)) {
                return ['id' => false];
            }
            DB::beginTransaction();
            $res['del_notify'] = Notification::where('id', '=', (int)$id)->delete();
            if (empty($res['del_notify'])) {
                return ['del' => false];
            }
            DB::commit();
            return $res;
        }
    }
    
    public function teller_receipt_detail(){
        $from_date = Request::input('t_from')?Request::input('t_from'):date("Y-m-d");
        $to_date = Request::input('t_to')?Request::input('t_to'):date("Y-m-d");
        $customer_id = Request::input('customer_id');
        $project_id = Request::input('project_id');
        $teller_id = Request::input('teller_id');
        $projects = Project::get();
        $roles_id = Role::whereIn('role', ['chief_of_teller', 'bm', 'admin', 'accm', 'acco','cas','teller'])->get();
        $role_arr = [];
        foreach($roles_id as $r){
            array_push($role_arr, $r->id);
        }
        $teller = User::with('role')->whereIn('role_id', $role_arr)
                                    ->where('branch_id', auth()->user()->branch_id)
                                    ->where('status', 1)
                                    ->orderBy('name', 'ASC')
                                    ->select('id','name','username')->get();
        $canExport=true;
        if(auth()->user()->role){
            if(auth()->user()->role->role_name==='Teller'){
                $canExport=false;
            }
        }
        $teller_transaction = TillTransaction::leftJoin('drawdown_account','drawdown_account.id','=','till_transaction.drawdown_acc_id')
        ->leftJoin('loans', 'drawdown_account.account_no', '=', 'loans.drawdown_acc')
        ->leftJoin('till_account','till_account.id','=','till_transaction.till_account_id')
        ->leftJoin('users','users.id','=','till_account.assign_user_id')
        ->leftJoin('clients','clients.id','=','drawdown_account.client_id')
        ->leftJoin('projects','projects.id','=','drawdown_account.project_id')
        ->leftJoin('units','units.id','=','drawdown_account.unit_id')
        ->leftJoin('unit_types','unit_types.id','=','drawdown_account.unit_type_id')
        ->where('till_transaction.approve_status',1)
        ->with([
        'feecharge' => function ($query) use ($data) {
            $query->select('*');
        }
        ]  
        )
        // ->where('till_account.assign_user_id',Auth::user()->id)
        ->select(
                'clients.client_name',
                'clients.cus_acc',
                'projects.dealer',
                'projects.dealer_en',
                'projects.short_code as project_name',
                'units.code as unit_code',
                'unit_types.name',
                'unit_types.short_code',
                'till_transaction.tranx_time',
                'till_transaction.created_at',
                'till_transaction.to_account',
                'till_transaction.type as type',
                'till_transaction.deposit_type',
                'till_transaction.deposit_company',
                'till_transaction.be_cash_item_note',    
                'till_transaction.pmt_no',
                'till_transaction.pmt_date',
                'till_transaction.interest',
                'till_transaction.principal',
                'till_transaction.status',
                'till_transaction.approve_status',
                'till_transaction.description',
                'till_transaction.cash_in',
                'till_transaction.cash_out',
                'till_transaction.receipt_no',
                'drawdown_account.client_id',
                'till_transaction.till_account_id',
                'till_transaction.methode',
                'loans.contract_id',
                'users.location',
                'users.name as username',
                'loans.id as loan_id'
                               
        )->whereDate('till_transaction.tranx_time','>=',$from_date)->whereDate('till_transaction.tranx_time','<=',$to_date);


        $client = null;
        $company_branch=null;
        $projects_row=null;
        if($customer_id){
            $teller_transaction = $teller_transaction->where('drawdown_account.client_id',$customer_id);
            $client = Clients::find($customer_id);
        }
        if($project_id){
            $teller_transaction = $teller_transaction->where('drawdown_account.project_id',$project_id);
            $projects_row = Project::find($project_id);
            $company_branch = CompanyBranch::find($projects_row->company_id);
        }
        if($teller_id){
            $teller_acc_id = Teller::where('assign_user_id',$teller_id)->first();
            $teller_transaction = $teller_transaction->where('till_transaction.till_account_id',$teller_acc_id->id);
        }
        if(Request::get('methode')){
            $methode=Request::get('methode');
            $methode_val=Request::get('methode');
            switch ($methode) {
                case "Bank Transfer":
                    $methode_val='Bank Transfer';
                case "Bank China":
                    $methode_val='Lay Sreyleak';
                default:
                $methode_val=Request::get('methode');
              }
            if($methode_val=='Bank Transfer'){
                $teller_transaction = $teller_transaction->where('till_transaction.methode','!=','Cash on Hand-Teller')
                                                        ->where('till_transaction.methode','!=','Lay Sreyleak')
                                                        ->where('till_transaction.methode','!=','Cash In Vault');
            }elseif($methode_val=='Bank China'){
                $teller_transaction = $teller_transaction->where('till_transaction.methode','like','%Lay Sreyleak');
            } else{
                $teller_transaction = $teller_transaction->where('till_transaction.methode','like','%'.$methode_val);
            }
            
        }
        if(Request::get('deposit_type')){
            $teller_transaction = $teller_transaction->where('till_transaction.deposit_type','like','%'.Request::get('deposit_type'));
        }
        if(Request::get('deposit_company')){
            $teller_transaction = $teller_transaction->where('till_transaction.deposit_company','like','%'.Request::get('deposit_company'));
        }
        if(Request::get('company')){
            $teller_transaction = $teller_transaction->where('projects.company_id',Request::get('company'));
            $company_branch = CompanyBranch::find(Request::get('company'));
        }
        if(Request::get('company_type')){
            $teller_transaction = $teller_transaction->where('till_transaction.deposit_company',Request::get('company_type'));
        }
        if(Request::get('search')){
            $teller_transaction = $teller_transaction->where('till_transaction.description','like','%'.Request::get('search').'%');
            $teller_transaction = $teller_transaction->orWhere('till_transaction.deposit_company','like','%'.Request::get('search').'%');
            $teller_transaction = $teller_transaction->orWhere('projects.dealer_en','like','%'.Request::get('search').'%');
            $teller_transaction = $teller_transaction->orWhere('projects.dealer','like','%'.Request::get('search').'%');
            $teller_transaction = $teller_transaction->orWhere('clients.client_name','like','%'.Request::get('search').'%');
            $teller_transaction = $teller_transaction->orWhere('loans.contract_id','like','%'.Request::get('search').'%');
            $teller_transaction = $teller_transaction->orWhere('clients.cus_acc','like','%'.Request::get('search').'%');
            $teller_transaction = $teller_transaction->orWhere('unit_types.name','like','%'.Request::get('search').'%');
            $teller_transaction = $teller_transaction->orWhere('units.code','like','%'.Request::get('search').'%');
        }
        
        

        $teller_transaction = $teller_transaction->get();

        $repayment = array();
        foreach ($teller_transaction as $item) { 
            $date=date_create($item->tranx_time);

            $last_payment = LoanPayments::where('loan_id',$item->loan_id)
            ->orderBy('payment_month','DESC')
            ->orderBy('repayment_date','DESC')->get()->first();

            $last_payment_date=date_create($last_payment->repayment_date);

            // $payment_month= $last_payment->payment_month+1;
            // $repayment_data=RepaymentSchedule::where('loan_id', $item->loan_id)
            //     ->where('no', $payment_month)
            //     //->whereYear('schedule_date', '=', date_format($date,"Y"))
            //     //->whereMonth('schedule_date', '=',date_format($date,"m"))
            //   ->select(
            //     'loan_id',
            //     'schedule_date','interest','principal','no'
            //     )->first();   
                
                

        if(!empty($item->principal)){
            array_push($repayment,array(
                'loan_id' => $item->loan_id,
                'schedule_date' => $item->pmt_date ,
                'interest'=> $item->interest,
                'principal'=>$item->principal,
                'no'=>$item->pmt_no,
                'last_payment'=>$last_payment
            ));
        }else{
            array_push($repayment,array(
                'loan_id' => $item->loan_id,
                'schedule_date' => $last_payment->repayment_date ,
                'interest'=> $last_payment->paid_interest,
                'principal'=>$last_payment->paid_principal,
                'no'=>$last_payment->payment_month,
                'last_payment'=>$last_payment
            ));

        }


  
         }
        $teller_user = Teller::select('users.name as name')
        ->join('users', 'users.id', '=', 'till_account.assign_user_id')
        ->where('assign_user_id', '=', auth()->user()->id)
        ->get();

        if(Request::input('is_excel') == 1 || Request::input('is_csv') == 1){
            $xlsx = 'xlsx';
            if(Request::has('is_csv') == 1){
                $xlsx = 'csv';
            }
            return Excel::create('teller-transaction-detail-'.date('d-M-Y'), function($excel) use ($teller_transaction,$company_branch,$projects_row,$from_date,$to_date,$teller_user,$repayment) {
                $excel->sheet('mySheet', function($sheet) use ($teller_transaction,$company_branch,$projects_row,$from_date,$to_date,$teller_user,$repayment)
                {           
                    $sheet->loadView('exports.teller_transaction_detail_excel',['teller_transaction' => $teller_transaction,'repayment'=>$repayment,'company_branch'=>$company_branch,'projects_row'=>$projects_row,
                    'from_date'=>$from_date,'to_date'=>$to_date,'teller'=>$teller_user]);
                });
            })->download($xlsx);
        }
        return $this->view('teller.teller_receipt_detail',['company_branch'=>$company_branch,'projects_row'=>$projects_row,'teller_user'=>$teller_user],compact('teller_transaction','repayment','projects','project_id','customer_id','client','from_date','to_date','teller','teller_id','canExport'));
    }

    public function teller_receipt_summary(){
        $data['projects'] = Project::get();
        $from_date = Request::input('t_from')?Request::input('t_from'):date("Y-m-d");
        $to_date = Request::input('t_to')?Request::input('t_to'):date("Y-m-d");
        $project_id = Request::input('project_id');
        $teller_id = Request::input('teller_id');
        $roles_id = Role::whereIn('role', ['chief_of_teller', 'bm', 'admin', 'accm', 'acco','cas','teller'])->get();
        $projects = Project::get();
        $role_arr = [];
        foreach($roles_id as $r){
            array_push($role_arr, $r->id);
        }
        $teller = User::with('role')->whereIn('role_id', $role_arr)
                                    ->where('branch_id', auth()->user()->branch_id)
                                    ->where('status', 1)
                                    ->orderBy('name', 'ASC')
                                    ->select('id','name','username')->get();
        $teller_transaction = TillTransaction::leftJoin('drawdown_account','drawdown_account.id','=','till_transaction.drawdown_acc_id')
        ->leftJoin('till_account','till_account.id','=','till_transaction.till_account_id')
        ->leftJoin('currency','currency.id','=','drawdown_account.currency')
        ->leftJoin('users','users.id','=','till_account.assign_user_id')
        ->leftJoin('clients','clients.id','=','drawdown_account.client_id')
        ->leftJoin('projects','projects.id','=','drawdown_account.project_id')
        ->leftJoin('units','units.id','=','drawdown_account.unit_id')
        ->leftJoin('unit_types','unit_types.id','=','drawdown_account.unit_type_id')
        ->leftJoin('loans', 'drawdown_account.account_no', '=', 'loans.drawdown_acc')
        ->where('till_transaction.approve_status',1)
        // ->where('till_account.assign_user_id',Auth::user()->id)
        ->select('clients.client_name','clients.cus_acc','projects.dealer','projects.id as p_id',
            'units.code as unit_code','unit_types.name','unit_types.short_code',
            'till_transaction.tranx_time','till_transaction.to_account','till_transaction.status',
            'till_transaction.cash_in','till_transaction.cash_out','drawdown_account.client_id','till_transaction.till_account_id','till_transaction.id',
            'users.name as teller_name','users.location','currency.symbol','till_transaction.methode'
        )->whereDate('till_transaction.tranx_time','>=',$from_date)->whereDate('till_transaction.tranx_time','<=',$to_date)
        ->orderBy('dealer', 'DESC');
        $company_branch=null;
        $projects_row=null;
        if($project_id){
            $teller_transaction = $teller_transaction->where('drawdown_account.project_id',$project_id);
            $projects_row = Project::find($project_id);
            $company_branch = CompanyBranch::find($projects_row->company_id);
        }
        if($teller_id){
            $teller_acc_id = Teller::where('assign_user_id',$teller_id)->first();
            $teller_transaction = $teller_transaction->where('till_transaction.till_account_id',$teller_acc_id->id);
        }

        $client = null;
        $customer_id = Request::input('customer_id');
        if($customer_id){
            $teller_transaction = $teller_transaction->where('drawdown_account.client_id',$customer_id);
            $client = Clients::find($customer_id);
        }
        if(Request::get('methode')){
            $methode=Request::get('methode');
            $methode_val=Request::get('methode');
            switch ($methode) {
                case "Bank Transfer":
                    $methode_val='Bank Transfer';
                case "Bank China":
                    $methode_val='Lay Sreyleak';
                default:
                $methode_val=Request::get('methode');
              }
            if($methode_val=='Bank Transfer'){
                $teller_transaction = $teller_transaction->where('till_transaction.methode','!=','Cash on Hand-Teller')
                                                        ->where('till_transaction.methode','!=','Lay Sreyleak')
                                                        ->where('till_transaction.methode','!=','Cash In Vault');
            }elseif($methode_val=='Bank China'){
                $teller_transaction = $teller_transaction->where('till_transaction.methode','like','%Lay Sreyleak');
            } else{
                $teller_transaction = $teller_transaction->where('till_transaction.methode','like','%'.$methode_val);
            }
            
        }
        if(Request::get('deposit_type')){
            $teller_transaction = $teller_transaction->where('till_transaction.deposit_type','like','%'.Request::get('deposit_type'));
        }
        if(Request::get('deposit_company')){
            $teller_transaction = $teller_transaction->where('till_transaction.deposit_company','like','%'.Request::get('deposit_company'));
        }
        if(Request::get('company')){
            $teller_transaction = $teller_transaction->where('projects.company_id',Request::get('company'));
            $company_branch = CompanyBranch::find(Request::get('company'));
        }
        if(Request::get('company_type')){
            $teller_transaction = $teller_transaction->where('till_transaction.deposit_company',Request::get('company_type'));
        }
        $teller_transaction = $teller_transaction->get();

        $teller_user = Teller::select('users.name as name')
        ->join('users', 'users.id', '=', 'till_account.assign_user_id')
        ->where('assign_user_id', '=', auth()->user()->id)
        ->get();

        $teller_trans_arr = [];
        foreach ($teller_transaction as $key => $item) {     
            $teller_trans_arr[$item->dealer][$item->p_id][$item->location][$item->id]['dealer'] = $item->dealer;
            $teller_trans_arr[$item->dealer][$item->p_id][$item->location][$item->id]['location'] = !empty($item->location)?$item->location:"";
            $teller_trans_arr[$item->dealer][$item->p_id][$item->location][$item->id]['cash_in'] = floatval($item->cash_in) - floatval($item->cash_out);
            $teller_trans_arr[$item->dealer][$item->p_id][$item->location][$item->id]['symbol'] = $item->symbol;
        }

        return $this->view('teller.teller_receipt_summary',['company_branch'=>$company_branch,'projects_row'=>$projects_row,'teller_user'=>$teller_user],compact('teller_transaction','projects','project_id','customer_id','client','from_date','to_date','teller','teller_id','teller_trans_arr','lineitems'),$data);
    }

    public function get_client_id(){
        $customer_id = Request::input('term');
        $client = new Clients;
        if(!empty($customer_id)){
            $client = $client->where(function($q) use($customer_id){
                $q->where('client_name','like','%'.$customer_id.'%')
                ->orWhere('cus_acc','like','%'.$customer_id.'%');
            });
        }
        $client = $client->limit(100)->get();
        return response()->json($client);
    }

    public function getdepositSchedule()
    {
        if (Request::ajax()) {
            $drawdown_acc_id = Request::input('drawdown_acc_id');
            $data = DrawdownAccounts::select('drawdown_account.id',
                                            'loans.id AS loan_id',
                                            'loans.drawdown_acc'
                    )
                    ->join('loans','drawdown_account.account_no','=','loans.drawdown_acc')
                    ->where('drawdown_account.id',$drawdown_acc_id)
                    ->first();
            if($data){
                $datas['success'] = 1;
                $datas['url'] = asset('loan_detail_schedule/'.$data->loan_id.'/'.$data->drawdown_acc);
                return response()->json($datas);
            }else{
                $datas['success'] = 0;
                $datas['url'] = '#';
                return response()->json($datas);
            }
        }
    }

    public function loan_detail_schedule($id = 0, $drawdown_acc = '0000000')
    {
        if (is_numeric($id) && $id > 0) {
            $loan = Loan::select('loans.*',
                    'loan_approval.id as approval_id',
                    'loan_approval.approval_date'
                )
                ->leftJoin('loan_approval', 'loans.id', '=', 'loan_approval.loan_id')
                ->with(['co_user' => function ($query) {
                        $query->select('id', 'phone', 'name');
                    }, 'client', 'branch', 'client_loan_account' => function ($q) {
                        $q->select('id', 'account_no', 'balance','balance_downpayment', 'currency');
                    },'unittypes','projects','sale_persons','PaymentOptions'])
                ->where('loans.id', '=', $id)
                ->where('loans.drawdown_acc', '=', $drawdown_acc)
                ->first();
            $audit = Audit::where('tbl', 'loans')->where('tbl_id', $id)->orderBy('id', 'asc')->with(['audit1', 'audit2'])->get();
            $schedule_id = RepaymentSchedule::where('loan_id', $id)->first()->id;
            return response()->json($this->view('loans.loan_detail_schedule_modal', ['loan' => $loan, 'audit' => $audit, 'schedule_id' => $schedule_id])->render());
        }
        return $this->view('loans.loan_detail_schedule_modal', ['loan' => null]);
    }

    /**
     * BCash deposit.
     *
     * @return \Illuminate\Http\Response
     */
    public function postBCashDeposit($receipt_no=null,$amount = 0,$deposit_type_label=null,$deposit_type=null,$deposit_company=null)
    {
        DB::beginTransaction();
        try 
        {
            if(Request::input('not_id') == 0)
            {
                $till_account = Teller::select('id', 'assign_user_id', 'balance', 'account_no', 'account_name')->where('assign_user_id', '=', auth()->user()->id)->where('currency_id', '=', Request::input('currency_id'))->first();
                
                $record_date = Request::input('till_date')!=""?date_format(date_create(Request::input('till_date')),"Y-m-d H:i:s"):date("Y-m-d H:i:s");
                if($record_date === 'undefined'){
                    $record_date = date("Y-m-d H:i:s");
                }
                if (count($till_account) > 0) 
                {
                
                    $till_transaction = new TillTransaction();
                    $till_transaction->till_account_id = $till_account->id;
                    $till_transaction->drawdown_acc_id = Request::input('drawdown_acc_id');
                    $till_transaction->till_user_id = $till_account->assign_user_id;
                    $till_transaction->from_account = Request::input('client_name');
                    $till_transaction->to_account = $till_account->account_no . ' / ' . $till_account->account_name;
                    $till_transaction->methode = Request::input('types');
                    $till_transaction->balance = floatval($till_account->balance);
                    // if(Request::input('types')=='Cash on Hand-Teller'){
                    //   $till_transaction->balance += floatval(Request::input('amount'));
                    // }
                    $till_transaction->cash_in = floatval($amount);
                    $till_transaction->drawdown_acc = Request::input('drawdown_acc');
                    $till_transaction->branch_id = auth()->user()->branch_id;
                    $till_transaction->operate_by = auth()->user()->id;
                    $till_transaction->tranx_time = $record_date;
                    $till_transaction->created_at=date("Y-m-d H:i:s");
                    $till_transaction->tran_currency_id = Request::input('currency_id');
                    $till_transaction->description = $deposit_type_label? $deposit_type_label : 'Cash Deposit';
                    $till_transaction->type = 'Cash Deposit';
                    $till_transaction->deposit_type=$deposit_type;
                    $till_transaction->deposit_company=$deposit_company;
                    $till_transaction->receipt_no=$receipt_no?$receipt_no:null;

                    $last_payment = LoanPayments::where('loan_id',Request::input('loan_id'))
                    ->orderBy('payment_month','DESC')
                    ->orderBy('repayment_date','DESC')->get()->first();        
        
                    $payment_month= $last_payment->payment_month+1;
                    $repayment_data=RepaymentSchedule::where('loan_id', Request::input('loan_id'))
                        ->where('no', $payment_month)
                      ->select(
                        'loan_id',
                        'schedule_date','interest','principal','no'
                        )->first();
                    if (!empty($repayment_data)) {
                        $till_transaction->pmt_no=$repayment_data->no;
                        $till_transaction->pmt_date=$repayment_data->schedule_date?date_create($repayment_data->schedule_date):null;
                        $till_transaction->interest=floatval($repayment_data->interest);
                        $till_transaction->principal=floatval($repayment_data->principal);                      
                    }

                    $slips = new slips();
                    // $print_cnt = slips::all()->last()->print_cnt;
                    $print_cnt  = slips::latest()->take(1)->first();
                    $print_cnt = $print_cnt->print_cnt;
                    // $drawDownAct = DrawdownAccounts::where('client_id', Request::input('client_id'))->where('currency', Request::input('currency_id'))->first();
                    $drawDownAct = DrawdownAccounts::where('client_id', Request::input('client_id'))->where('account_no', Request::input('drawdown_acc'))->where('currency', Request::input('currency_id'))->first();
                    if(!$drawDownAct){
                        $drawDownAct = DrawdownAccounts::where('account_no', $till_transaction->drawdown_acc)->where('currency', $till_transaction->tran_currency_id)->first();
                    }
                
                    $slips->draw_acc_id = $drawDownAct->id;
                    $slips->client_id = Request::input('client_id');
                    $slips->user_id = auth()->user()->id;
                    $slips->create_date = $record_date;
                    $slips->currency_id = Request::input('currency_id');
                    $slips->types = Request::input('types');
                    $slips->amount = floatval($amount);
                    $slips->trans_type = 'Cash Deposit';
                    $slips->check_num = Request::input('check_num');
                    $slips->bank_name = Request::input('bank_name');
                    $slips->description = $deposit_type_label? $deposit_type_label : 'Cash Deposit';
                    $slips->prepared_by = auth()->user()->id;
                    $slips->checked_by = auth()->user()->id;
                    $slips->authorized_by = auth()->user()->id;
                    $slips->signature = auth()->user()->signature;
                    $slips->is_signature = auth()->user()->is_signature;
                    $slips->print_cnt = (int)$print_cnt + 1;
                    // $dd_balance = $drawDownAct->balance + floatval(Request::input('amount'));
                    // $res['up_wd'] = DrawdownAccounts::where('client_id', Request::input('client_id'))->where('currency', Request::input('currency_id'))->update(['balance' => $dd_balance]);
                    if($drawDownAct->unit_id){
                        $units = Unit::where('id',$drawDownAct->unit_id)->where('status','available')->first();
                        if($units){
                            $units->status = 'deposit';
                            $units->save();
                        }
                    }
                    //if (!empty($res['up_wd'])) {
                        // $res['up_till'] = (Request::input('types')=='Cash on Hand-Teller')? Teller::where('id', $till_account->id)->update(['balance' => $till_transaction->balance]) : 1;
                      //  return array(empty($res['up_till']));
                        //if (!empty($res['up_till'])) {
                          if(Request::input('types') == 'Foreign Exchange Position Account'){
                            $teller_coa = CoaCategory::where('name', '=', Request::input('types'))->where('currency', Request::input('currency_id'))->first();
                          }else{
                            $teller_coa = CoaCategory::where('name', 'Like', '%'.Request::input('types').'%')->orderBy('id')->where('currency', Request::input('currency_id'))->first();
                          }
                          // The COA account is matched by the payment method's label text, so a
                          // method with no matching account returns null here. Reading ->id off that
                          // null is only a notice, so it used to reach the journal, where
                          // tb_journal_detail.coa_id (NOT NULL) rejected it - leaving the customer a
                          // receipt and an empty journal header, with no till transaction at all.
                          if (empty($teller_coa)) {
                            throw new \Exception('Payment method "'.Request::input('types').'" has no COA account for this currency, so nothing was posted. Add the account under Accounting > Add COA Category, or choose another payment method.');
                          }
                          $teller_coa_id = $teller_coa->id;
                          $branch_code = $drawDownAct->branch;
                          $journal_arr = [];
                          $note=$deposit_type_label? $deposit_type_label : 'Cash Deposit';
                          array_push($journal_arr, [
                                                    $teller_coa_id, 
                                                    floatval($amount), 
                                                    $note,
                                                    $drawDownAct->coa_id, 
                                                    floatval($amount), 
                                                    $note,
                                                    $note,
                                                    $deposit_type
                                                ]
                                            );
                          //record_journal_no_trans(null, $record_date, $journal_arr, auth()->user()->branch_id, auth()->user()->id, 1);
                          $postedJournal = record_journal_no_trans(null, $record_date, $journal_arr, auth()->user()->branch_id, auth()->user()->id, 0,null,1);

                          if (!is_array($postedJournal)) throw new \RuntimeException('Cashier journal did not complete.');
                          $slips->jr_id = $postedJournal['journal_id'];
                          $slips->jd_id = $postedJournal['journal_detail_id'];
                          if($slips->save()){
                              $till_transaction->slips_id = $slips->id;
                              if($till_transaction->save()) {
                                  $this->userActivity(Auth::user()->id, $slips->jd_id, 0, 'Add JournalDetail', Request::fullUrl());
                                  $res['ins_notify'] = $this->notification->setNotification([
                                      Request::input('users_id'),
                                      false,
                                      $till_transaction->id,
                                      Request::input('users_id'),
                                      $till_account->id,
                                      'Cash Deposit',
                                      $record_date,
                                      $till_transaction->description,
                                      floatval($amount)
                                  ]);
                              }
                          }
                        //}
                    //}
                      //if(Request::input('isprint')==1){
                    if (!empty($res['ins_notify'])) {
                        if (Request::has('if_print') && Request::input('if_print')==1) {
                            $res['print_url'] = url('teller/print/deposit/'.$slips->id);
                        }
                    }

                    /* --- Auto Approve deposit ---*/
                    // if(Request::input('flag_button') == "ok"){
                    //     // Approve deposit
                    //         // $notification = Notification::where('id', '=', Request::input('not_id'))->first();
                    //         // $till_transaction = TillTransaction::where('id', '=', $notification->n_source_id)->first();
                    //         // Update balance
                    //         $drawDownAct = DrawdownAccounts::where('account_name', $till_transaction->from_account)->where('account_no', $till_transaction->drawdown_acc)->where('currency', $till_transaction->tran_currency_id)->first();
                    //         if(!$drawDownAct){
                    //             $drawDownAct = DrawdownAccounts::where('account_no', $till_transaction->drawdown_acc)->where('currency', $till_transaction->tran_currency_id)->first();
                    //         }
                    //         if($drawDownAct){  
                    //             $dd_balance = $drawDownAct->balance +  floatval($amount);
                    //             $drawDownAct->balance = $dd_balance;
                    //             $drawDownAct->save();
                    //             $till_account = Teller::where('id', $till_transaction->till_account_id)->first();
                    //             $till_transaction->approve_status = 1;
                    //             if($till_transaction->methode=='Cash on Hand-Teller'){
                    //                 $till_account->balance = $till_account->balance +  floatval($amount);
                    //             }
                    //             $till_transaction->balance = $till_account->balance;
                    //             $till_transaction->save();
                    //             $till_account->save();
        
                    //             $journal_require = JournalRequiry::where('description', '=', $till_transaction->description)
                    //                                 ->where('user_id', '=', $till_transaction->operate_by)
                    //                                 ->where('entry_date', '=', $till_transaction->tranx_time)
                    //                                 ->first();
                    //             if($journal_require){
                    //                 $journal_require->is_audit = 1;
                    //                 $journal_require->save();
                    //             }
        
                    //             JournalDetail::where('journal_id', '=', $journal_require->id)->update(['is_audit' => 1]);
        
                    //             $this->do_audit($journal_require->id, Auth::user()->id, '', 'journal_requiry', 1, 'add_journal');
                    //             $this->userActivity(Auth::user()->id, $journal_require->id, 6, 'Auth Cash Deposit');
                    //             $res = 'true';
                    //         }else{
                    //             $res['ins_notify'] = false;
                    //         }        
        
                    //     }
                        /* --- End ---*/
                }else{
                    $res['ins_notify'] = false;
                }
            }else{
                if(Request::input('flag_button') == "ok"){
                // Approve deposit
                    $notification = Notification::where('id', '=', Request::input('not_id'))->first();
                    $till_transaction = TillTransaction::where('id', '=', $notification->n_source_id)->first();
                    // Update balance
                    $drawDownAct = DrawdownAccounts::where('account_name', $till_transaction->from_account)->where('account_no', $till_transaction->drawdown_acc)->where('currency', $till_transaction->tran_currency_id)->first();
                    if(!$drawDownAct){
                        $drawDownAct = DrawdownAccounts::where('account_no', $till_transaction->drawdown_acc)->where('currency', $till_transaction->tran_currency_id)->first();
                    }
                    if($drawDownAct){  
                        $dd_balance = $drawDownAct->balance + floatval($notification->n_amount);
                        $drawDownAct->balance = $dd_balance;
                        $drawDownAct->save();
                        $till_account = Teller::where('id', $till_transaction->till_account_id)->first();
                        $till_transaction->approve_status = 1;
                        if($till_transaction->methode=='Cash on Hand-Teller'){
                            $till_account->balance = $till_account->balance + floatval($notification->n_amount);
                        }
                        $till_transaction->balance = $till_account->balance;
                        $till_transaction->save();
                        $till_account->save();

                        $journal_require = JournalRequiry::where('description', '=', $notification->n_description)
                                            ->where('user_id', '=', $till_transaction->operate_by)
                                            ->where('entry_date', '=', $till_transaction->tranx_time)
                                            ->first();
                        if($journal_require){
                            $journal_require->is_audit = 1;
                            $journal_require->save();
                        }

                        JournalDetail::where('journal_id', '=', $journal_require->id)->update(['is_audit' => 1]);

                        $this->do_audit($journal_require->id, Auth::user()->id, '', 'journal_requiry', 1, 'add_journal');
                        $this->userActivity(Auth::user()->id, $journal_require->id, 6, 'Auth Cash Deposit');
                        $res = 'true';
                    }else{
                        $res['ins_notify'] = false;
                    }


                }elseif(Request::input('flag_button') == "ng"){
                    $notification = Notification::where('id', '=', Request::input('not_id'))->first();
                    $till_transaction = TillTransaction::where('id', '=', $notification->n_source_id)->first();

                    $till_transaction_arr = TillTransaction::where('id', '=', $notification->n_source_id)->get()->first()->toArray();
                    $notification_action = array_merge($till_transaction_arr,['reject_by'=>Auth::id(),'reject_id'=>$till_transaction_arr['id']]);
                    unset($notification_action['id']);
                    $till_reject = TillTransactionReject::insert($notification_action);


                    $drawDownAct = DrawdownAccounts::where('account_name', $till_transaction->from_account)->where('account_no', $till_transaction->drawdown_acc)->where('currency', $till_transaction->tran_currency_id)->first();
                    if(!$drawDownAct){
                        $drawDownAct = DrawdownAccounts::where('account_no', $till_transaction->drawdown_acc)->where('currency', $till_transaction->tran_currency_id)->first();
                    }
                    if($drawDownAct){
                        $till_account = Teller::where('id', $till_transaction->till_account_id)->first();
                        $journal_require = JournalRequiry::where('description', '=', $notification->n_description)
                                            ->where('user_id', '=', $till_transaction->operate_by)
                                            ->where('entry_date', '=', $till_transaction->tranx_time)
                                            ->first();
                        JournalDetail::where('journal_id', '=', $journal_require->id)->delete();
                        if($journal_require){
                            $journal_require->delete();
                        }
                        if($till_transaction){
                            $till_transaction->delete();
                        }
                        if($notification){
                            $notification->delete();
                        }
                        $this->userActivity(Auth::user()->id, $journal_require->id, 6, 'Reject Cash Deposit');
                        $res = 'true';
                    }else{
                        $res['ins_notify'] = false;
                    }
                }
            }
            DB::commit();
          return $res;
        } catch (\Exception $e) {
            // Was silently discarded: the BE-CASH receipt is saved by BCashController before
            // this runs, so a rollback here leaves a receipt with no till transaction.
            \Log::error('postBCashDeposit rolled back, nothing posted. receipt='.$receipt_no.' deposit_type='.$deposit_type.' amount='.$amount.' user_id='.Auth::user()->id.' : '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
            DB::rollback();
            // BCashController::addBcash owns the outer transaction and has already saved the
            // receipt, so the rollback above is a nested no-op. Rethrow so addBcash rolls the
            // receipt back too and the teller is told, instead of being handed a receipt for
            // money that never posted.
            throw $e;
        }
    }


    public function list_posting_transaction(){
        if(config('unified_posting.enabled')){
            if(!Auth::check())abort(403);
            $user=Auth::user();
            $branch=in_array((int)$user->role_id,array(1,2),true)?null:$user->branch_id;
            if($branch===null&&!in_array((int)$user->role_id,array(1,2),true))abort(403);
            if($branch!==null&&!$branch)abort(403);
            $repo=\App\Services\UnifiedPostingReport::configured();
            $from_date=Request::input('t_from')?:date('Y-m-d');$to_date=Request::input('t_to')?:date('Y-m-d');
            $filters=array('from'=>$from_date,'to'=>$to_date,'customer_id'=>Request::input('customer_id'),'project_id'=>Request::input('project_id'),'teller_id'=>Request::input('teller_id'),'company'=>Request::input('company'));
            try{$teller_transaction=$repo->query($filters,$branch)->get();}catch(\InvalidArgumentException $e){abort(422,$e->getMessage());}
            $projects=$repo->projects();$unified_companies=$repo->companies();
            $project_id=Request::input('project_id');$customer_id=Request::input('customer_id');
            $client=null;$company_branch=null;$projects_row=null;
            if(Request::input('is_excel')==1||Request::input('is_csv')==1){
                $format=Request::input('is_csv')==1?'csv':'xlsx';
                return Excel::create('posting-transactions-'.date('Y-m-d'),function($excel)use($teller_transaction,$from_date,$to_date){
                    $excel->sheet('Postings',function($sheet)use($teller_transaction,$from_date,$to_date){$sheet->loadView('exports.unified_posting_transactions',compact('teller_transaction','from_date','to_date'));});
                })->download($format);
            }
            return $this->view('teller.posting_transaction',array(),compact('teller_transaction','projects','unified_companies','project_id','customer_id','client','company_branch','projects_row','from_date','to_date'));
        }

        $from_date = Request::input('t_from')?Request::input('t_from'):date("Y-m-d");
        $to_date = Request::input('t_to')?Request::input('t_to'):date("Y-m-d");
        $customer_id = Request::input('customer_id');
        $project_id = Request::input('project_id');
        $teller_id = Request::input('teller_id');
        $projects = Project::get();
        $roles_id = Role::whereIn('role', ['chief_of_teller', 'bm', 'admin', 'accm', 'acco','cas','teller'])->get();
        $role_arr = [];
        foreach($roles_id as $r){
            array_push($role_arr, $r->id);
        }

        $teller_transaction = TransactionsPosting::leftJoin('drawdown_account','drawdown_account.id','=','transactions_posting.drawdown_acc_id')
        ->leftJoin('loans', 'drawdown_account.account_no', '=', 'loans.drawdown_acc')
        ->leftJoin('clients','clients.id','=','drawdown_account.client_id')
        ->leftJoin('projects','projects.id','=','drawdown_account.project_id')
        ->leftJoin('units','units.id','=','drawdown_account.unit_id')
        ->leftJoin('unit_types','unit_types.id','=','drawdown_account.unit_type_id')
        ->where('transactions_posting.status',1)
        // ->where('till_account.assign_user_id',Auth::user()->id)
        ->select(
                'clients.client_name',
                'clients.cus_acc',
                'projects.dealer',
                'projects.dealer_en',
                'projects.short_code as project_name',
                'units.code as unit_code',
                'unit_types.name',
                'unit_types.short_code',
                'transactions_posting.*',
                'loans.contract_id',
                'loans.id as loan_id'
                               
        )->whereDate('transactions_posting.posting_date','>=',$from_date)->whereDate('transactions_posting.posting_date','<=',$to_date);


        $client = null;
        $company_branch=null;
        $projects_row=null;
        if($customer_id){
            $teller_transaction = $teller_transaction->where('drawdown_account.client_id',$customer_id);
            $client = Clients::find($customer_id);
        }
        if($project_id){
            $teller_transaction = $teller_transaction->where('drawdown_account.project_id',$project_id);
            $projects_row = Project::find($project_id);
            $company_branch = CompanyBranch::find($projects_row->company_id);
        }
        
        if(Request::get('company')){
            $teller_transaction = $teller_transaction->where('projects.company_id',Request::get('company'));
            $company_branch = CompanyBranch::find(Request::get('company'));
        }
        
        

        $teller_transaction = $teller_transaction->get();

        if(Request::input('is_excel') == 1 || Request::input('is_csv') == 1){
            $xlsx = 'xlsx';
            if(Request::has('is_csv') == 1){
                $xlsx = 'csv';
            }
            return Excel::create('posting-transaction-detail-'.date('d-M-Y'), function($excel) use ($teller_transaction,$company_branch,$projects_row,$from_date,$to_date,$teller_user,$repayment) {
                $excel->sheet('mySheet', function($sheet) use ($teller_transaction,$company_branch,$projects_row,$from_date,$to_date,$teller_user,$repayment)
                {           
                    $sheet->loadView('exports.posting_transaction_detail_excel',['teller_transaction' => $teller_transaction,'repayment'=>$repayment,'company_branch'=>$company_branch,'projects_row'=>$projects_row,
                    'from_date'=>$from_date,'to_date'=>$to_date,'teller'=>$teller_user]);
                });
            })->download($xlsx);
        }
        // dd($teller_transaction);

        return $this->view('teller.posting_transaction',['company_branch'=>$company_branch,'projects_row'=>$projects_row],compact('teller_transaction','projects','project_id','customer_id','client','from_date','to_date'));
    }
}
