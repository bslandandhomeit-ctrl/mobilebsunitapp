<?php
/**
 * Created by PhpStorm.
 * User: SOPHEAK
 * Date: 5/25/2015
 * Time: 5:40 PM
 */

namespace App\Http\Controllers;
use App\Models\Account;
use App\Models\Cbc\ClientCbcGeneral;
use App\Models\Client;
use App\Models\ClientLoanAccounts;
use App\Models\CoaCategory;
use App\Models\CompanyBranch;
use App\Models\Currency;
use App\Models\Loan;
use App\Models\JournalDetail;
use App\Models\JournalRequiry;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\DrawdownAccounts;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Models\Project;
use App\Models\Unit;
use Request;
//use App\Http\Requests\Request;
use Auth;
use Image;

class AccountingController extends Controller
{

    public function  __construct()
    {
        $this->middleware('xss');
        $this->middleware('auth');
    }

    public function getAccount()
    {
        return $this->view('accounting.add');
    }

    public function postAccount()
    {
        $inputs = Request::except(['_token']);
        if(!empty($inputs) && is_array($inputs)){
            $account_code_arr = Request::input('account_code');
            $category_arr = Request::input('category');
            $sub_category_arr = Request::input('sub_category');
            $type_arr = Request::input('type');
            $account_name_arr = Request::input('account_name');
            for($i = 0; $i < count($account_code_arr); $i++){
                $addNew = new Account();
                $addNew->account_code = $account_code_arr[$i];
                $addNew->category = $category_arr[$i];
                $addNew->sub_category = $sub_category_arr[$i];
                $addNew->type = $type_arr[$i];
                $addNew->account_name = $account_name_arr[$i];
                if(!($addNew->save())) {
                    return redirect()->back()->with($inputs);
                }
                $this->userActivity(Auth::user()->id, $addNew->id, 0, 'Create account', Request::fullUrl());
            }
            return redirect()->route('chart_of_accounts');
        }
    }

    public function getChartOfAccounts()
    {
/*        $account = null;
    	$type1 = CoaCategory::orderBy('nbc_code')->where('type', 6)->with(["parent"=>function($q5){
            $q5->with(["parent"=>function($q4){
                $q4->with(["parent"=>function($q3){
                    $q3->with(["parent"=>function($q2){
                        $q2->with(["parent"=>function($q1){
                            $q1->with("parent");
                        }]);
                    }]);
                }]);
            }]);
        }])->get();
*/
        $account = CoaCategory::whereIn('type', [1,2,3,4,5,6])->orderBy("nbc_code")->get();
        $can_edit_coa = $this->coa_edit_allowed();
    	return $this->view('accounting.chart_account', ['account'=> $account, 'can_edit_coa'=> $can_edit_coa]);
    }

    /**
     * Chart of Accounts is viewable by everyone who reaches the route, but editing and
     * deleting a ledger account is restricted to Super Admin, Admin, Accounting Manager
     * and Finance Manager (role_id 1, 2, 11, 26). Checked here rather than through
     * tb_permission because that table is keyed on route name and would gate the whole
     * screen, not the edit/delete actions on an otherwise-viewable one.
     */
    private function coa_edit_allowed()
    {
        return in_array(Auth::user()->role_id, [1, 2, 11, 26]);
    }

    public function getChartOfAccountsDetail()
    {
        $type1 = CoaCategory::orderBy('nbc_code')->where('type', 1)->get();
        foreach ($type1 as $account1){
            $type2[$account1->id] = CoaCategory::orderBy('nbc_code')->where('type', 2)->where('parent_id', $account1->id)->get();
            foreach ($type2[$account1->id] as $account2){
                $type3[$account2->id] = CoaCategory::orderBy('nbc_code')->where('type', 3)->where('parent_id', $account2->id)->get();
                foreach ($type3[$account2->id] as $account3){
                    $type4[$account3->id] = CoaCategory::orderBy('nbc_code')->where('type', 4)->where('parent_id', $account3->id)->get();
                    foreach ($type4[$account3->id] as $account4){
                        $type5[$account4->id] = CoaCategory::orderBy('id')->where('type', 5)->where('parent_id', $account4->id)->get();
                        foreach ($type5[$account4->id] as $account5){
                            $type6[$account5->id] = CoaCategory::orderBy('id')->where('type', 6)->where('parent_id', $account5->id)->get();
                        }
                    }
                }
            }
        }
        return $this->view('accounting.chart_account_detail', ['type1'=>$type1, 'type2'=>$type2, 'type3'=>$type3, 'type4'=>$type4, 'type5'=>$type5, 'type6'=>$type6]);
    }

    public function getCheckCode($t = 0){
       if(Request::ajax()){
            $code = Request::input('account_code');
           if($t == 0){
                $row = Account::where('account_code',$code)->count();
                if($row > 0){
                    return "false";
                }
            }else{
                $id = Request::input('code',0);
                $acc = Account::where('account_code',$code)->first();
                if(!empty($acc) && $acc->account_code != $id){
                    return "false";
                }
            }
        }
        return "true";
    }

    public function get_edit_account($id = 0)
    {
        $account = Account::where('account_code',$id)->first();
        if(!empty($account)){
            return $this->view('accounting.edit',compact('account'));
        }
        return redirect()->back();
    }

    public function post_edit_account($id = 0)
    {
        $account = Account::where('account_code',$id)->first();
        if(!empty($account)){
            $code = Request::input('account_code');
            if(!empty($code)){
                $account->account_code = $code;
                $account->account_name = Request::input('account_name');
                $account->type = Request::input('type');
                $account->category = Request::input('category');
                $account->sub_category = Request::input('sub_category');
                if($account->save()){
                    $this->userActivity(Auth::user()->id, $account->id, 0, 'Edit account', Request::fullUrl());

                    $j_d = JournalDetail::where('account_code',$id)->get();
                    foreach($j_d as $d){
                        $d->account_code = $code;
                        $d->save();
                    }
                    return redirect()->route('chart_of_accounts');
                }
            }
        }
        return redirect()->back();
    }

    public function post_del_account($id = 0)
    {
        $account = Account::where('account_code',$id)->first();
        if(!empty($account)){
            $account->status = 0;
            $account->save();
            $this->userActivity(Auth::user()->id, $account->id, 0, 'Delete account', Request::fullUrl());
            $j_d = JournalDetail::where('account_code',$id)->get();
            foreach($j_d as $d){
                $d->delete();
            }
        }
        return redirect()->back();
    }

    public function getTrailBalanceDetail(){

        $entries_prev = JournalRequiry::select('id','entry_date');
        $entries_btw = JournalRequiry::select('id','entry_date');

        if(Request::has('start') && Request::has('end')){
            $data['start'] = Request::input('start');
            $data['end'] = Request::input('end');
        }else{
            $data['start'] = date('Y-m-d',strtotime(date('Y-m-d').' -1 month'));
            $data['end'] = date('Y-m-d');
        }

        $entries_prev->where('entry_date','<=',date('Y-m-d',strtotime($data['start'].' -1 day')));
        $entries_btw->whereBetween('entry_date',[$data['start'],$data['end']]);

        $data['entries_prev'] = $entries_prev->with(['detail'=>function($query){
                                            $query->orderBy('id','asc');
                                        }])->get();
        $data['entries_btw'] = $entries_btw->with(['detail'=>function($query){
                                            $query->orderBy('id','asc');
                                        }])->get();

        return $this->view('accounting.trail_balance_detail',$data);
    }

    public function getBalanceSheetAccount(){
        $data = $this->getTB();
        $coa_details = $data['coa_details'];
        $coa_all = $data['coa_all'];

        $pre_inc = $pre_exp = $re = 0;
        foreach($coa_details as $v){
          if($v["nbc_code"] == '500000') {$pre_inc = $v["pre_credit"] - $v["pre_debit"];}
          if($v["nbc_code"] == '600000') {$pre_exp =$v["pre_debit"] - $v["pre_credit"];}
          if($v['type'] != 4) continue;
          switch ($v["initial"]) {
            case '1':
            case '2':
                $array_bal["symbol_".$v['symbol']] += ($v['pre_debit'] - $v['pre_credit']) + ($v['cur_debit'] - $v['cur_credit']);
              break;
            case '6':
                $array_bal["symbol_".$v['symbol']] += Round($v["cur_debit"] - $v["cur_credit"],2);
              break;
            case '3':
            case '4':
                $array_bal["symbol_".$v['symbol']] += -1*(($v['pre_debit'] - $v['pre_credit']) + ($v['cur_debit'] - $v['cur_credit']));
                break;
            case '5':
                $array_bal["symbol_".$v['symbol']] += Round($v["cur_credit"] - $v["cur_debit"],2);
              break;
            default:
              break;
          }
        }
        $re = $pre_inc - $pre_exp;
        //dd($array_bal);
        $data['cce'] = Round($array_bal['symbol_cce'],2);
        $data['bcb'] = Round($array_bal['symbol_bcb'],2);
        $data['sdcb'] = Round($array_bal['symbol_sdcb'],2);
        $data['bbo'] = Round($array_bal['symbol_bbo'],2);
        $data['lac'] = Round($array_bal['symbol_lac'],2);
        $data['ies'] = Round($array_bal['symbol_ies'],2);
        $data['lpdbd'] = Round($array_bal['symbol_lpdbd'],2);
        $data['pe'] = Round($array_bal['symbol_pe'],2);
        $data['ld'] = Round($array_bal['symbol_ld'],2);
        $data['oa'] = Round($array_bal['symbol_oa'],2);
        $data['lostd'] = Round($array_bal['symbol_lostd'],2);
        $data['asset'] = $data['cce'] + $data['bcb'] + $data['sdcb'] + $data['bbo'] + $data['lac'] + $data['ies'] + $data['lpdbd'] + $data['pe'] + $data['ld'] + $data['oa'] + $data['lostd'];
        //Liabilities************** CREDIT ONLY
        $data['dc'] = Round($array_bal['symbol_dc'],2);
        //Subordinated Debt
        $data['sd'] = Round($array_bal['symbol_sd'],2);
        $data['b'] = Round($array_bal['symbol_b'],2);
        $data['ol'] = Round($array_bal['symbol_ol'],2);
        $data['gp'] = Round($array_bal['symbol_gp'],2);
        $data['pitcy'] = Round($array_bal['symbol_pitcy'],2);
        $data['is'] = Round($array_bal['symbol_is'],2);
        $data['liability'] = $data['dc'] + $data['sd'] + $data['b'] + $data['ol'] + $data['gp'] + $data['pitcy'] + $data['is'] ;
        //Shareholder Equities****************
        $data['sc'] = Round($array_bal['symbol_sc'],2);
        $data['r'] = Round($array_bal['symbol_r'],2);

        //Subordinated debt
        $data['se_sd'] = Round($array_bal['symbol_se_sd'],2);
        //$data['re'] = $re;
        $data['re'] = Round($array_bal['symbol_re'],2);

        $data['iil'] = Round($array_bal['symbol_iil'],2);
        $data['oii'] = Round($array_bal['symbol_oii'],2);
        $data['income'] = $data['iil'] + $data['oii'];

        $data['ied'] = Round($array_bal['symbol_ied'],2);
        $data['ieb'] = Round($array_bal['symbol_ieb'],2);
        $data['oie'] = Round($array_bal['symbol_oie'],2);
        $data['expense'] = $data['ied'] + $data['ieb'] + $data['oie'];

        $data['net_interest'] = $data['income'] - $data['expense'];

        $data['ilcf'] = Round($array_bal['symbol_ilcf'],2);
        $data['iofc'] = Round($array_bal['symbol_iofc'],2);
        $data['igfe'] = Round($array_bal['symbol_igfe'],2);
        $data['igdpe'] = Round($array_bal['symbol_igdpe'],2);
        $data['rl'] = Round($array_bal['symbol_rl'],2);
        $data['onii'] = Round($array_bal['symbol_onii'],2);
        $data['non_interest_income'] = $data['ilcf'] + $data['iofc'] + $data['igfe'] + $data['igdpe'] + $data['rl'] + $data['onii'];

        $data['posc'] = Round($array_bal['symbol_posc'],2);
        $data['dpe'] = Round($array_bal['symbol_dpe'],2);
        $data['ooe'] = Round($array_bal['symbol_ooe'],2);
        $data['non_interest_expense'] = $data['posc'] + $data['ooe'];

        $data['operating_profit_bp'] = $data['net_interest'] + $data['non_interest_income'] - $data['non_interest_expense'];

        $data['pdbd'] = Round($array_bal['symbol_pdbd'],2); //expense

        $data['profit_bit'] = $data['operating_profit_bp'] - $data['pdbd'];

        $data['eit'] = Round($array_bal['symbol_eit'],2); //expense

        $data['pcy'] = $data['profit_bit'] - $data['eit'] - $data['dpe'];
        /////*********************************************************************************************

        $data['shareholder'] = $data['sc'] + $data['r'] + $data['se_sd'] + $data['re'] + $data['pcy'];
        $data['total_ls'] = $data['liability'] + $data['shareholder'];
//dd($data);
        return $this->view('accounting.balance_sheet_account', $data);
    }

    public function get_add_coa_category($type = 0)
    {
        $parents = [];
        if($type == 2){ /* sub-category */
            $parents = CoaCategory::where('type',1)->orderBy('nbc_code','asc')->get();
            if(count($parents) == 0){
                return redirect()->back();
            }
        }elseif($type == 3){ /* main-account */
            $parents = CoaCategory::where('type',2)->orderBy('nbc_code','asc')->get();
            if(count($parents) == 0){
                return redirect()->back();
            }
        }elseif($type == 4){ /* sub-account */
            $parents = CoaCategory::whereIn('type',[3,4])->orderBy('nbc_code','asc')->get();
            if(count($parents) == 0){
                return redirect()->back();
            }
        }elseif($type == 5){ /* subsidiary-account */
            $parents = CoaCategory::whereIn('type',[4,5])->orderBy('nbc_code','asc')->get();
            if(count($parents) == 0){
                return redirect()->back();
            }
        }
        $branches = CompanyBranch::select('id','branch_code','branch_name')->where('status',1)->get();
        return $this->view('accounting.add_coa_category',compact('parents','type','branches'));
    }

    public function post_add_coa_category($type = 0)
    {
        $validator = Validator::make(
            Request::only([
                'nbc_code','name'
             ]), [
                'nbc_code' => 'required',
                'name' => 'required',
            ],[
                'nbc_code.required' => 'GL Code is required',
                'name.required' => 'GL Name is required',
            ]);
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator);
        }

        $parent_id = 0;
        $currency_code = 0;
        $parent_coa = null;
        if ($type > 1) {
            $parent_id = Request::input('group');
        }
        $coa = new CoaCategory();
        $coa->parent_id = $parent_id;
        if ($type == 5) { /* subsidiary-account */
            if (!Request::has('currency')) return redirect()->back()->withErrors(['Currency is required']);
            $currency_code = Request::input('currency');
        }
        $coa->currency = $currency_code;
//            $coa->code = Request::input('code');
//            $pattern = "/^[0-9]{6}-[0-9]{4}-[0-9]{4}-[0-9]{5}$/";
//            $code = [];
//            if (preg_match($pattern, $coa->code)) {
//                $code = explode('-', $coa->code, 2);
//                $coa->nbc_code = $code[0];
//                $coa->account_code = $code[1];
//            } else {
//                $code = $coa->code;
//                $coa->nbc_code = $code;
//            }
        if(!Request::has('nbc_code')) return redirect()->back()->withErrors(['NBC code is required']);
        $coa->nbc_code = Request::input('nbc_code');
       // $code = $coa->nbc_code;
        (Request::has('coa_code'))? $coa->account_code = Request::input('coa_code') : $coa->account_code = Request::input('nbc_code');
        $coa->name = Request::input('name');
        $coa->description = Request::input('description');
        $parent_type = CoaCategory::find($parent_id)->type;
        $coa->type = $type+1;
        if($coa->type == 4){
            (Request::has('symbol'))? $coa->symbol = Request::input('symbol') : $coa->symbol = '';
        }
        //$coa->branch_code = $branch_code;
        (Request::has('sector_id'))? $coa->sector_id = Request::input('sector_id'):$coa->sector_id =0;
        if ($coa->save()) {
            $this->userActivity(Auth::user()->id, $coa->id, 0, 'Create COA', Request::fullUrl());
        }else{
            return redirect()->back()->withErrors(['Save failed']);
        }
        return redirect()->back()->with('msg_success', 'Save successfully');

    }

    /**
     * Edit one COA account.
     *
     * Parent, type and currency are deliberately not editable: they decide where the
     * account sits in the tree and which lookups find it, so changing them on an account
     * that has already been posted to would take its history with it. Create a new
     * account instead.
     */
    public function get_edit_coa_category($id = 0)
    {
        if (!$this->coa_edit_allowed()) {
            return redirect()->route('chart_of_accounts')
                ->withErrors(['You do not have permission to edit or delete chart of accounts.']);
        }
        $coa = CoaCategory::find($id);
        if (empty($coa)) return redirect()->back()->withErrors(['Account not found']);
        $used = JournalDetail::where('coa_id', $coa->id)->count();
        return $this->view('accounting.edit_coa_category', compact('coa', 'used'));
    }

    public function post_edit_coa_category($id = 0)
    {
        if (!$this->coa_edit_allowed()) {
            return redirect()->route('chart_of_accounts')
                ->withErrors(['You do not have permission to edit or delete chart of accounts.']);
        }
        $coa = CoaCategory::find($id);
        if (empty($coa)) return redirect()->back()->withErrors(['Account not found']);

        $validator = Validator::make(
            Request::only(['nbc_code', 'name']), [
                'nbc_code' => 'required',
                'name'     => 'required',
            ], [
                'nbc_code.required' => 'GL Code is required',
                'name.required'     => 'GL Name is required',
            ]);
        if ($validator->fails()) return redirect()->back()->withErrors($validator);

        // TellerController::postBCashDeposit finds the ledger account for a deposit by
        // matching the payment method's label against this name with LIKE. Renaming an
        // account a payment method depends on stops every posting made with that method,
        // so name the ones that would break instead of letting it happen quietly.
        $new_name = trim(Request::input('name'));
        if ($new_name != $coa->name) {
            $broken = array_diff($this->coa_payment_methods($coa->name),
                                 $this->coa_payment_methods($new_name));
            if (count($broken)) {
                return redirect()->back()->withErrors([
                    'Renaming this account would leave these payment methods with no ledger account: '
                    . implode(', ', $broken) . '. Rename the payment method too, or leave the name as it is.'
                ]);
            }
        }

        $coa->nbc_code     = Request::input('nbc_code');
        $coa->account_code = Request::has('coa_code') ? Request::input('coa_code') : Request::input('nbc_code');
        $coa->name         = $new_name;
        $coa->description  = Request::input('description');
        if (Request::has('sector_id')) $coa->sector_id = Request::input('sector_id');
        if (Request::has('symbol'))    $coa->symbol    = Request::input('symbol');

        if (!$coa->save()) return redirect()->back()->withErrors(['Save failed']);
        $this->userActivity(Auth::user()->id, $coa->id, 0, 'Edit COA', Request::fullUrl());
        return redirect()->route('chart_of_accounts')->with('msg_success', 'Save successfully');
    }

    /**
     * Active payment methods whose label is contained in $name -- the ones whose deposits
     * resolve to a COA account called $name.
     */
    private function coa_payment_methods($name)
    {
        $rows = PaymentMethod::where('active', 1)
            ->whereRaw('? LIKE CONCAT("%", label, "%")', [$name])
            ->lists('label');
        return is_array($rows) ? $rows : $rows->toArray();
    }

    /**
     * Delete a COA account, but only when nothing points at it.
     *
     * There are no foreign keys on these columns, so MySQL will happily delete an account
     * that journal lines still reference: the lines survive pointing at an id that no
     * longer exists, and every report joining through it quietly drops those rows. So
     * check each reference first and refuse with the reason.
     */
    public function post_delete_coa_category($id = 0)
    {
        if (!$this->coa_edit_allowed()) {
            return redirect()->route('chart_of_accounts')
                ->withErrors(['You do not have permission to edit or delete chart of accounts.']);
        }
        $coa = CoaCategory::find($id);
        if (empty($coa)) return redirect()->back()->withErrors(['Account not found']);

        $blockers = [];

        $lines = JournalDetail::where('coa_id', $coa->id)->count();
        if ($lines) $blockers[] = $lines . ' journal line(s)';

        $children = CoaCategory::where('parent_id', $coa->id)->count();
        if ($children) $blockers[] = $children . ' account(s) underneath it';

        // Every other column in the schema that holds a COA id.
        $refs = [
            'drawdown_account'         => ['coa_id'],
            'company_drawdown_account' => ['coa_id'],
            'channel_gateway_param'    => ['coa_id'],
            'client_loan_accounts'     => ['coa_id', 'air_id', 'int_inc_id', 'sus_id'],
        ];
        foreach ($refs as $table => $columns) {
            foreach ($columns as $column) {
                $n = DB::table($table)->where($column, $coa->id)->count();
                if ($n) $blockers[] = $n . ' row(s) in ' . $table . '.' . $column;
            }
        }

        $methods = $this->coa_payment_methods($coa->name);
        if (count($methods)) $blockers[] = 'payment method(s) ' . implode(', ', $methods);

        if (count($blockers)) {
            return redirect()->back()->withErrors([
                'This account cannot be deleted because it is still used by: '
                . implode('; ', $blockers) . '.'
            ]);
        }

        $this->userActivity(Auth::user()->id, $coa->id, 0,
            'Delete COA ' . $coa->account_code . ' ' . $coa->name, Request::fullUrl());
        $coa->delete();
        return redirect()->route('chart_of_accounts')->with('msg_success', 'Account deleted');
    }

    /**
     * Edit Heng Sopheak
     * @param int $client_id
     * @return array
     */
    public function getCLientAccountType($type){

        $clients = Client::with(['general', 'contact', 'clientLoan'])->where(['account_cbc_type'=> strtolower(trim($type)),'status'=>1])->get();

        $guarantor = ClientCbcGeneral::with([
            'clients'=>function($q){
                $q->where('status', 1);
            },
            'clients.contact'])->where('applicant_type', 'G')->get();//strtolower(trim($type))

        return ['clients'=>$clients, 'guarantor'=>$guarantor];
    }

    public function get_add_client_loan_account($client_id = 0)
    {
        $exist_contract_id = ClientLoanAccounts::select('loan_ref')->get();
        $exist_contract_id_array = [];
        foreach($exist_contract_id as $key=>$value){
            array_push($exist_contract_id_array,$value->loan_ref);
        }
        $parents = CoaCategory::where(function($q){
            $q->where('name','like','Stand-L%')->orWhere('name','like','Financial Lease%');
        })->where('type',6)->get();
        if(count($parents) == 0){
            return redirect()->back();
        }
        $branches = CompanyBranch::where('status', 1)->get();
        $users = User::where('role_id','>',7)->get();
        /**
            $clients = Client::select('id','client_name');
            if($client_id > 0){
                $clients = $clients->where('id','=',$client_id)->where('status',1);
            }
        **/

        $clients = Client::select('id', 'status','client_name')->with('general');
        if($client_id > 0){
            $clients = $clients->where('id', $client_id)->where('status',1);
        }
        $clients = $clients->get();

        $currency = Currency::select('id', 'code')->get();
        $projects = Project::select('id','dealer','short_code')->get();
        //$loans = Loan::select('id','contract_id')->whereNotIn('contract_id',$exist_contract_id_array)->get();
        $loans = '';
        $dd_parent_accs = CoaCategory::select('id', 'account_code', 'currency', 'name')->where('type', '=', '6')->where('name', 'like', 'Voluntary Deposits%')->where('name', 'like', '%Drawdown Accounts')->get();
        $exist_cla = ClientLoanAccounts::select('id', 'account_no', 'parent_id')->get();
       
        return $this->view('accounting.add_client_loan_account',compact('parents','branches','users','clients','loans', 'dd_parent_accs', 'currency', 'exist_cla','projects'));
    }

    public function post_add_client_loan_account($client_id = 0,$redirect=null)
    {
        // All account-number checks and writes must share this transaction.
        DB::beginTransaction();
        try {
            $guard = CoaCategory::orderBy('id')->lockForUpdate()->first();
            if (!$guard) throw new \LogicException('Accounting chart is not configured.');
            $result = $this->processClientLoanAccountCreation($client_id, $redirect);
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    private function processClientLoanAccountCreation($client_id = 0,$redirect=null)
    {
        $data = Request::except(['_token', 'dd_parent_id', 'dd_account_no','leasing_flag','sale_terms_hash','account_branch_code']);
        $validator = Validator::make(
            Request::only([
                'currency','branch','parent_id','client_id','account_no','account_name','project_id','unit_type_id','unit_id'
            ]), [
                'currency' => 'required',
                'branch' => 'required',
                'parent_id' => 'required',
                'client_id' => 'required',
                'account_no' => 'required',
                'account_name' => 'required',
                'project_id' => 'required',
                'unit_type_id' => 'required',
                'unit_id' => 'required'

            ],[
                'currency' => 'Currency is required',
                'branch' => 'Branch Name is required',
                'parent_id' => 'Parent Account is required',
                'client_id' => 'Client ID is required',
                'account_no' => 'Account Number is required',
                'account_name' => 'Account Name is required',
                'project_id' => 'Project is required',
                'unit_type_id' => 'Uniwt Type is required',
                'unit_id' => 'Unit is required'
            ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $dd_account_no = Request::input('dd_account_no');
        $account_no = Request::input('account_no');
        $check_dd_acc = DrawdownAccounts::where('account_no',$dd_account_no)->first();
        if($check_dd_acc){
            $branch_code = Request::input('account_branch_code', Request::input('branch'));
            // $last = isset(DrawdownAccounts::latest()->first()->id)?DrawdownAccounts::latest()->first()->id:null;
            $last = isset(DrawdownAccounts::orderBy('id','DESC')->first()->id)?DrawdownAccounts::orderBy('id','DESC')->first()->id:null;
            if(!$last){
                $last=0;
            }
            $last = (int)filter_var($last, FILTER_SANITIZE_NUMBER_INT);
            $no = $last+1;
            $no = $branch_code.$this->getClientNumber($no,7);
            $dd_account_no =  $no;
        }
        if($check_dd_acc == $dd_account_no){
            return redirect()->back()->withErrors(['Account No is Aready exist.']);
        }
        $check_client_acc_no = ClientLoanAccounts::where('account_no',$account_no)->first();
        if($check_client_acc_no){
            $branch_code = Request::input('account_branch_code', Request::input('branch'));
            $last = isset(ClientLoanAccounts::where('years',date('Y'))->orderBy('acc_no','DESC')->first()->acc_no)?ClientLoanAccounts::where('years',date('Y'))->orderBy('acc_no','DESC')->first()->acc_no:null;
            if(!$last){
                $last=0;
            }
            $last = (int)filter_var($last, FILTER_SANITIZE_NUMBER_INT);
            $no = $last+1;
            $no = $branch_code.date('y').$this->getClientNumber($no,6);
            $data['account_no'] = $no;
        }
        $new_loan_account = new ClientLoanAccounts();
        foreach ($data as $key => $value) {
            $new_loan_account->$key = $value;
        }
        $new_loan_account->years = date('Y');
        $new_loan_account->acc_no = substr($data['account_no'], 4, 6);
        $static = config('static_data');
        $auto_authorize_drawdown = $static['auto_authorize_drawdown'];
        $auto_authorize_account = $static['auto_authorize_account'];
        $new_loan_account->balance = 0.0;

        $parent_name = CoaCategory::select('*')->where('id','=',$new_loan_account->parent_id)->first();
        // Loan or Leasing
        $static = config('static_data');

        $key_pre = $static['client_loan_account_prefix'];
        $new_loan_account->acc_key = substr($parent_name->name,8,50);
        if(Request::input('leasing_flag') == '1'){
            $key_pre = $static['client_leasing_account_prefix'];
            $new_loan_account->acc_key = substr($parent_name->name,14,50);
        }
        $new_loan_account->prefix = $key_pre[2]; // "Std-"
        // COA
        $pre_prefix = $static['client_loan_account_pre_prefix']['coa']; //"";
        $new_coa = createLoanAccountCoaNew($new_loan_account, $pre_prefix);
        if($new_coa['new_flg'] == 0){ // If account already exist
            // return redirect()->back()->with('msg', 'The Loan Account Exists!!');
        }
        $new_coa = $new_coa['coa'];
        $new_loan_account->coa_id = $new_coa->id;
        // AIR
        $pre_prefix = $static['client_loan_account_pre_prefix']['air']; //"Air-";
        $new_air = createLoanAccountCoaNew($new_loan_account, $pre_prefix);
        if($new_air['new_flg'] == 0){ // If account already exist
            // return redirect()->back()->with('msg', 'The Loan Account Exists!!');
        }
        $new_air = $new_air['coa'];
        $new_loan_account->air_id = $new_air->id;

        // Interest Income
        $pre_prefix = $static['client_loan_account_pre_prefix']['interest']; //"Int-Inc-";
        $new_int = createLoanAccountCoaNew($new_loan_account, $pre_prefix);
        if($new_int['new_flg'] == 0){ // If account already exist
            // return redirect()->back()->with('msg', 'The Loan Account Exists!!');
        }
        $new_int = $new_int['coa'];
        $new_loan_account->int_inc_id = $new_int->id;

        if(Request::has('sub_client_id')) {

            $sub_client_id = '';
            foreach(Request::input('sub_client_id') as $k=>$ins){
                $sub_client_id .= $ins.',';
            }
            $new_loan_account->sub_client_id = '['.rtrim(trim($sub_client_id),',').']';

        }if(Request::has('guarantor')){
            $guarantor = '';
            foreach(Request::input('guarantor') as $k=>$ins){
                $guarantor .= $ins.',';
            }
            $new_loan_account->guarantor = '['.rtrim(trim($guarantor),',').']';
        }

        $new_loan_account->acc_type = (Request::input('acc_type'))?Request::input('acc_type'):'S';
        $new_loan_account->status = 0; // Inactive
        $new_loan_account->created_by = Auth::user()->id;

        // ** Add Sale ID
        if(Request::has('sale_id')){
            $new_loan_account->sale_id = Request::input('sale_id');
        }
        // ** End Sale


        if($new_loan_account->save()){
            $this->userActivity(Auth::user()->id, $new_loan_account->id, 0, 'Create client loan account', Request::fullUrl());
            $this->do_audit($new_loan_account->id, Auth::user()->id, '', 'client_loan_accounts', 0, 'add loan account');

            //Drawdown Account
            if($auto_authorize_account == 1){
                $this->auto_authorize_account($new_loan_account->id);
            }
            if($new_loan_account->prefix == "Stand-L") {
                $pre_prefix = "Drawdown Account Loan";
            }else{
                $pre_prefix = "Drawdown Account Lease";
            }
            // $acc_code = substr(Request::input('dd_account_no'), 4);
            $acc_exist = CoaCategory::select('id', 'account_code')->where('account_code', '=', $acc_code)->first();
            if(is_null($acc_exist)){

                $new_dd_account             = createLoanAccountCoaNew($new_loan_account, $pre_prefix, Request::input('dd_parent_id'), $acc_code, $new_loan_account->account_name);
                $dd_account                 = new DrawdownAccounts;
                $dd_account->client_id      = $new_loan_account->client_id;
                $dd_account->client_loan_id = $new_loan_account->id;
                $dd_account->account_no     = $dd_account_no;
                $dd_account->account_name   = $new_loan_account->account_name;
                $dd_account->currency       = $new_loan_account->currency;
                $dd_account->branch         = $new_loan_account->branch;
                $dd_account->parent_id      = Request::input('dd_parent_id');
                $dd_account->coa_id         = $new_dd_account['coa']->id;
                $dd_account->balance        = 0;
                $dd_account->created_on     = $new_loan_account->created_on;
                $dd_account->created_by     = $new_loan_account->created_by;
                $dd_account->project_id     = Request::input('project_id');
                $dd_account->unit_type_id   = Request::input('unit_type_id');
                $dd_account->unit_id        = Request::input('unit_id');
                $dd_account->status         = 0;                                                                                                                                    // Inactive

                // ** Add Sale ID
                if(Request::has('sale_id')){
                    $dd_account->sale_id = Request::input('sale_id');
                }
                // ** End Sale
                if($dd_account->save()){
                    // BS canonical actions own availability during unified handoff.
                    if($dd_account->unit_id && !config('unified_posting.in_canonical_handoff', false)){
                        $units = Unit::where('id',$dd_account->unit_id)->where('status','available')->first();
                        if($units){
                            $units->status = 'booking';
                            $units->save();
                        }
                    }
                    $this->userActivity(Auth::user()->id, $dd_account->id, 0, 'Add drawdown account', Request::fullUrl());
					$this->do_audit($dd_account->id, Auth::user()->id, '', 'drawdown_account', 0, 'add drawdown account');

                    if($auto_authorize_drawdown == 1){
                        $this->auto_authorize_drawdown($dd_account->id);
                    }
                }
            }
            if($redirect){
                return redirect()->route('list_sale');
            }else{
                return redirect()->back()->with('msg_success','Save successfully');
            }
            

            // $new_loan_account->delete();
            // $new_coa->delete();
            // $new_air->delete();
            // $new_int->delete();
            // return redirect()->back()->withErrors(['Save failed']);

        }else {
            $new_coa->delete();
            $new_air->delete();
            $new_int->delete();
            return redirect()->back()->withErrors(['Save failed']);
        }
    }


    public function account_data()
    {
        echo  json_encode($_POST['account_data']);

    }


    //CHHUCH
    public function getTrailBalance(){
        // $journal_detail = JournalDetail::select('id')->orderBy('id','DESC')->get();
        // foreach ($getRestructure as $key => $value) {
        //     // code...
        // }
    //     $curJournalDetail = DB::table('journal_detail')
    //                             ->select('journal_detail.id as jd_id',
    //                                     'journal_detail.debit',
    //                                     'journal_detail.credit',
    //                                     'journal_detail.branch_code',
    //                                     'journal_detail.coa_id',
    //                                     'journal_detail.journal_id',
    //                                     'journal_requiry.is_audit as audit',
    //                                     'journal_requiry.entry_date',
    //                                     'coa_categories.parent_id as parent_id',
    //                                     'coa_categories.account_code', 'nbc_code',
    //                                     'coa_categories.name as coa_name',
    //                                     'coa_categories.currency',
    //                                     'journal_detail.reference')
    //                         ->join('journal_requiry', 'journal_detail.journal_id', '=', 'journal_requiry.id')
    //                         ->join('coa_categories', 'journal_detail.coa_id', '=', 'coa_categories.id')->limit(10000000)->get();
    // dd('ddd');
    //     dd('ddd');
        $data = $this->getTB();
        return $this->view('accounting.trail_balance',$data);
    }

    public function getTB(){
        define('USDTOKHR', 4100);
  //      define('KHRM', 1000000);
        define('KHRM', 1);

        $data['currency'] = config('static_data.currency');
        $data['report_title'] = 'Trial Balance Report';
        $data['nbc'] = 0;
        $data['type'] = 6;
        $p_branch_code = '000';

        $rate = Request::has('rate')? Request::input('rate') : USDTOKHR;
        if(Request::has('nbc')) $data['nbc'] = 1;
        if(Request::has('type')) $data['type'] = Request::input('type');
        $end_date = Request::has('dpEnd')?Request::input('dpEnd') : date('Y-m-d', strtotime(date('Y-m-t')));
        $data['end_date'] = $end_date;
        $data['end'] = $end_date;
        $start_date = Request::has('dpStart')? Request::input('dpStart') : date('Y-m-d',strtotime(date("Y-m-01")));
        $data['start_date'] = $start_date;
        $data['start'] = $start_date;

        $data['consolidate'] = 0;
        if(Request::has('consolidate') && Request::input('consolidate')==1){
            $data['consolidate'] = 1;
            //$data['rate'] = Request::has('rate')?Request::input('rate'):USDTOKHR;
            if(Request::has('exchange_rate') && Request::input('exchange_rate')==1){
              $data['exchange_rate'] = 1;
            }else{
              $data['exchange_rate'] = 2;
            }
        }
        $data['currency_id'] = Request::has('cur')?Request::input('cur'):2;

        if(Auth::user()->role_id!=1 && Auth::user()->role_id!=2) $data['branch_code'] = $branch_code;

        $branch_code = \App\Support\TrialBalanceScope::branchForUser(Auth::user());
        $query_arr = array('id'=>Auth::user()->branch_id, 'role_id'=>Auth::user()->role_id);
        //$B1 = new CompanyBranch();
        //$B1 = $this->getBranchByUser($B1, 'id', $query_arr);
        $data['branch'] = CompanyBranch::select('id', 'branch_code','branch_name', 'branch_code')->where('status','=',1)->get();
        //  $branch_code = CompanyBranch::select('branch_code')->where('id', Auth::user()->branch_id)->first()->branch_code;
        $branch_code = \App\Support\TrialBalanceScope::branchForUser(Auth::user());
        $query_arr = array('user_id'=>Auth::user()->id, 'branch_id'=>Auth::user()->branch_id, 'role_id'=>Auth::user()->role_id, 'branch_code'=>$branch_code);

        //in journal inquiry
        $JournalRequiry_arr = array();

        $jd_coa = [];
        $jd_coa_pre = [];
        
        $B0 = new JournalDetail();
        $B0 = $this->getUserByBranch($B0, 'branch_code', $query_arr);

        $curJournalDetail = DB::table('journal_detail')
                                ->select('journal_detail.id as jd_id',
                                        DB::raw('SUM(tb_journal_detail.debit) AS debit'),
                                        DB::raw('SUM(tb_journal_detail.credit) AS credit'),
                                        'journal_detail.branch_code',
                                        'journal_detail.coa_id',
                                        'journal_detail.journal_id',
                                        'journal_requiry.is_audit as audit',
                                        'journal_requiry.entry_date',
                                        'coa_categories.parent_id as parent_id',
                                        'coa_categories.account_code',
                                        'coa_categories.nbc_code',
                                        'coa_categories.name as coa_name',
                                        'coa_categories.currency',
                                        'coa_categories.type',
                                        'coa_categories.symbol',
                                        'journal_detail.reference')
                            ->join('journal_requiry', 'journal_detail.journal_id', '=', 'journal_requiry.id')
                            ->join('coa_categories', 'journal_detail.coa_id', '=', 'coa_categories.id');

        $preJournalDetail =  DB::table('journal_detail')
                                    ->select('journal_detail.id as jd_id',
                                        DB::raw('SUM(tb_journal_detail.debit) AS debit'),
                                        DB::raw('SUM(tb_journal_detail.credit) AS credit'),
                                        'journal_detail.branch_code',
                                        'journal_detail.coa_id',
                                        'journal_detail.journal_id',
                                        'journal_requiry.is_audit as audit',
                                        'journal_requiry.entry_date',
                                        'coa_categories.parent_id as parent_id',
                                        'coa_categories.account_code',
                                        'coa_categories.nbc_code',
                                        'coa_categories.name as coa_name',
                                        'coa_categories.currency',
                                        'coa_categories.type',
                                        'coa_categories.symbol',
                                        'journal_detail.reference')
                            ->join('journal_requiry', 'journal_detail.journal_id', '=', 'journal_requiry.id')
                            ->join('coa_categories', 'journal_detail.coa_id', '=', 'coa_categories.id');
        // $preJournalDetail = $curJournalDetail;
        // $curJournalDetail = $curJournalDetail->orderBy('journal_detail.id', 'ASC')->where('journal_detail.is_audit', '1');
        //$JournalDetail = $JournalDetail->get();
//        $JournalDetail = $JournalDetail->where('entry_date', '<', $start_date);
        //$B2 = new JournalDetail();
        //$B2 = $this->getUserByBranch($B2, 'branch_code', $query_arr);
        /*$preJournalDetail = $B2->select('journal_detail.id as jd_id', 'debit', 'credit', 'branch_code', 'coa_id', 'journal_id', 'journal_requiry.is_audit as audit', 'entry_date', 'coa_categories.parent_id as parent_id', 'account_code', 'nbc_code', 'coa_categories.name as coa_name', 'currency', 'reference')
                            ->join('journal_requiry', 'journal_detail.journal_id', '=', 'journal_requiry.id')
                            ->join('coa_categories', 'journal_detail.coa_id', '=', 'coa_categories.id');*/

        $requestedBranch = Request::has('br') ? Request::input('br') : null;
        if (!in_array((int) Auth::user()->role_id, array(1, 2), true)) {
            $data['branch_code'] = $branch_code;
        } elseif ($requestedBranch !== null && $requestedBranch !== '') {
            $data['branch_code'] = $requestedBranch;
        }
        \App\Support\TrialBalanceScope::apply($preJournalDetail, $start_date, $end_date, true,
            Auth::user()->role_id, $branch_code, $requestedBranch);
        \App\Support\TrialBalanceScope::apply($curJournalDetail, $start_date, $end_date, false,
            Auth::user()->role_id, $branch_code, $requestedBranch);
        if($data['consolidate'] == 0){
            $preJournalDetail->where('currency', $data['currency_id']);
            $curJournalDetail->where('currency', $data['currency_id']);

            $coa_all = DB::table('coa_categories')->select('id', 'parent_id', 'account_code', 'nbc_code', 'name', 'currency', 'type', 'symbol')
                ->whereIn('currency', [0,$data['currency_id']])
                ->where(function($q){ 
                    $q->where('nbc_code', 'LIKE', '1%')
                    ->orWhere('nbc_code', 'LIKE', '2%')
                    ->orWhere('nbc_code', 'LIKE', '3%')
                    ->orWhere('nbc_code', 'LIKE', '4%')
                    ->orWhere('nbc_code', 'LIKE', '5%')
                    ->orWhere('nbc_code', 'LIKE', '6%');
                });
            $coa_all_nor = clone $coa_all;
            // $coa_all_nor = $coa_all;

            $coa_all = $coa_all->orderBy('id', 'DESC')->get();

            /*$coa_all_nor = CoaCategory::select('id', 'parent_id', 'account_code', 'nbc_code', 'name', 'currency', 'type', 'symbol')->whereIn('currency', [0,$data['currency_id']])
            ->where(function($q){ $q->where('nbc_code', 'LIKE', '1%')->orWhere('nbc_code', 'LIKE', '2%')->orWhere('nbc_code', 'LIKE', '3%')->orWhere('nbc_code', 'LIKE', '4%')->orWhere('nbc_code', 'LIKE', '5%')->orWhere('nbc_code', 'LIKE', '6%');
            })*/

            $coa_all_nor = $coa_all_nor->where('type', '<=', $data['type'])->orderBy('account_code', 'ASC')->get();

        }else{
            $coa_all = DB::table('coa_categories')->select('id', 'parent_id', 'account_code', 'nbc_code', 'name', 'currency', 'type', 'symbol')
                ->where(function($q){
                    $q->where('nbc_code', 'LIKE', '1%')
                    ->orWhere('nbc_code', 'LIKE', '2%')
                    ->orWhere('nbc_code', 'LIKE', '3%')
                    ->orWhere('nbc_code', 'LIKE', '4%')
                    ->orWhere('nbc_code', 'LIKE', '5%')
                    ->orWhere('nbc_code', 'LIKE', '6%');
                });
            $coa_all_nor = clone $coa_all;
            // $coa_all_nor = $coa_all;

            $coa_all = $coa_all->orderBy('id', 'DESC')->get();

            /*$coa_all_nor = CoaCategory::select('id', 'parent_id', 'account_code', 'nbc_code', 'name', 'currency', 'type', 'symbol')->where(function($q){
              $q->where('nbc_code', 'LIKE', '1%')->orWhere('nbc_code', 'LIKE', '2%')->orWhere('nbc_code', 'LIKE', '3%')->orWhere('nbc_code', 'LIKE', '4%')->orWhere('nbc_code', 'LIKE', '5%')->orWhere('nbc_code', 'LIKE', '6%');
            })*/
            $coa_all_nor = $coa_all_nor->where('type', '<=', $data['type'])->orderBy('account_code', 'ASC')->get();
        }
        /*$q = $curJournalDetail->toSql();
        $db = DB::connection()->getPdo();

        $query = $db->prepare($q);
        $query->execute(); // here's our puppies' real age
        //$result = $query->fetchAll();
        //dd($result);
        while ($name = $query->fetchColumn()) {
            // process data
            dd($name);
        }*/
        $preJournalDetail = $preJournalDetail->groupBy('coa_categories.id')->get();
        $curJournalDetail = $curJournalDetail->groupBy('coa_categories.id')->get();
        $pre_balance = [];
        $pre_balance_val = 0;
        $coa_details = [];
        $coa_details_arr = [];
//        $coa_all = CoaCategory::where('type', '>=', $data['type'])->orderBy('id', 'DESC')->get();
//        $coa_all_nor = CoaCategory::where('type', '>=', $data['type'])->orderBy('account_code', 'ASC')->get();
        if(count($preJournalDetail) > 0){
            $cnt = 0; $old_coa = 0; $new_coa = 0;
            foreach($preJournalDetail as $pre){
                $new_coa = $pre->coa_id;
                if($cnt == 0 && $new_coa != $old_coa){
                $coa_details[$pre->coa_id]["pre_debit"] = 0;
                $coa_details[$pre->coa_id]["pre_credit"] = 0;
                }

                if($data['consolidate'] == 1){
                if($data['currency_id'] == 1){//KHR (show in million)
                  if($pre->currency == 2 ){
                    $coa_details[$pre->coa_id]["pre_debit"] += $pre->debit * $rate / KHRM;
                    $coa_details[$pre->coa_id]["pre_credit"] += $pre->credit * $rate / KHRM;
                  }elseif($pre->currency == 1){//if already KHR
                    $coa_details[$pre->coa_id]["pre_debit"] += $pre->debit / KHRM;
                    $coa_details[$pre->coa_id]["pre_credit"] += $pre->credit / KHRM;
                  }
                }elseif($data['currency_id'] == 2){//USD
                  if($pre->currency == 2 ){
                    $coa_details[$pre->coa_id]["pre_debit"] += $pre->debit;
                    $coa_details[$pre->coa_id]["pre_credit"] += $pre->credit;
                  }elseif($pre->currency == 1){//if KHR convert to USD
                    $coa_details[$pre->coa_id]["pre_debit"] += $pre->debit / $rate;
                    $coa_details[$pre->coa_id]["pre_credit"] += $pre->credit / $rate;
                  }
                }
                }else{ //no consolidate
                  if($data['currency_id'] == 1){//KHR (show in million)
                    if($pre->currency==2){
                        continue;
                    }elseif($pre->currency==1){
                      $coa_details[$pre->coa_id]["pre_debit"] += $pre->debit/KHRM;
                      $coa_details[$pre->coa_id]["pre_credit"] += $pre->credit/KHRM;
                    }
                  }elseif($data['currency_id'] == 2){
                    if($pre->currency==1){
                        continue;
                    }elseif($pre->currency==2){
                      $coa_details[$pre->coa_id]["pre_debit"] += $pre->debit;
                      $coa_details[$pre->coa_id]["pre_credit"] += $pre->credit;
                    }
                  }
                }

                $coa_details[$pre->coa_id]["parent"] = $pre->parent_id;
                $coa_details[$pre->coa_id]["code"] = $pre->account_code;
                $coa_details[$pre->coa_id]["initial"] = substr($pre->account_code, 0, 1);
                $coa_details[$pre->coa_id]["branch"] = $pre->branch_code;
                $coa_details[$pre->coa_id]["currency"] = $pre->currency;
                $coa_details[$pre->coa_id]["nbc_code"] = $pre->nbc_code;
                $coa_details[$pre->coa_id]["cur_debit"] = 0;
                $coa_details[$pre->coa_id]["cur_credit"] = 0;
                $coa_details[$pre->coa_id]["reference"] = ($pre->reference!=" ")?$pre->reference:"-";
                $coa_details[$pre->coa_id]["type"] = $pre->type;
                $coa_details[$pre->coa_id]["symbol"] = $pre->symbol;
                $old_coa = $pre->coa_id;
                $cnt++;
            }
            // dd($coa_details);
            // var_dump(count($coa_all));
            $coa_details = convert_journal_detail($coa_all,$coa_details,7);
            $coa_details = convert_journal_detail($coa_all,$coa_details,6);
            $coa_details = convert_journal_detail($coa_all,$coa_details,5);
            $coa_details = convert_journal_detail($coa_all,$coa_details,4);
            $coa_details = convert_journal_detail($coa_all,$coa_details,3);
            $coa_details = convert_journal_detail($coa_all,$coa_details,2);
            $coa_details = convert_journal_detail($coa_all,$coa_details,1);

            
            // foreach($coa_all as $icoa){
            //     // if($icoa->id == $ipre["parent"]){
            //     //     $coa_details[$icoa->id]["pre_debit"]  += $ipre["pre_debit"];
            //     //     $coa_details[$icoa->id]["pre_credit"]  += $ipre["pre_credit"];
            //     //     $coa_details[$icoa->id]["cur_debit"]  += $ipre["cur_debit"];
            //     //     $coa_details[$icoa->id]["cur_credit"]  += $ipre["cur_credit"];
            //     //     $coa_details[$icoa->id]["parent"]  = $icoa->parent_id;
            //     //     $coa_details[$icoa->id]["code"] = $icoa->account_code;
            //     //     $coa_details[$icoa->id]["nbc_code"] = $icoa->nbc_code;
            //     //     $coa_details[$icoa->id]["initial"] = substr($icoa->account_code, 0, 1);
            //     //     $coa_details[$icoa->id]["branch"] = $ipre->branch_code;
            //     //     $coa_details[$icoa->id]["currency"] = $ipre->currency;
            //     //     $coa_details[$icoa->id]["type"] = $icoa->type;
            //     //     $coa_details[$icoa->id]["symbol"] = $icoa->symbol;

            //     // }
            //     // foreach($coa_details as $ipre){
            //     //     if($icoa->id == $ipre["parent"]){
            //     //         $coa_details[$icoa->id]["pre_debit"]  += $ipre["pre_debit"];
            //     //         $coa_details[$icoa->id]["pre_credit"]  += $ipre["pre_credit"];
            //     //         $coa_details[$icoa->id]["cur_debit"]  += $ipre["cur_debit"];
            //     //         $coa_details[$icoa->id]["cur_credit"]  += $ipre["cur_credit"];
            //     //         $coa_details[$icoa->id]["parent"]  = $icoa->parent_id;
            //     //         $coa_details[$icoa->id]["code"] = $icoa->account_code;
            //     //         $coa_details[$icoa->id]["nbc_code"] = $icoa->nbc_code;
            //     //         $coa_details[$icoa->id]["initial"] = substr($icoa->account_code, 0, 1);
            //     //         $coa_details[$icoa->id]["branch"] = $ipre->branch_code;
            //     //         $coa_details[$icoa->id]["currency"] = $ipre->currency;
            //     //         $coa_details[$icoa->id]["type"] = $icoa->type;
            //     //         $coa_details[$icoa->id]["symbol"] = $icoa->symbol;

            //     //     }
            //     // }
            // }
        }

        if(count($curJournalDetail) > 0){
          $cnt = 0; $old_coa = 0; $new_coa = 0;
            foreach($curJournalDetail as $pre){
              $new_coa = $pre->coa_id;
              if($cnt == 0 && $new_coa != $old_coa){
                $coa_details[$pre->coa_id]["cur_debit"] = 0;
                $coa_details[$pre->coa_id]["cur_credit"] = 0;
              }

              if($data['consolidate'] == 1){
                if($data['currency_id'] == 1){//KHR (show in million)
                  if($pre->currency == 2 ){
                    $coa_details[$pre->coa_id]["cur_debit"] += $pre->debit * $rate / KHRM;
                    $coa_details[$pre->coa_id]["cur_credit"] += $pre->credit * $rate / KHRM;
                  }elseif($pre->currency == 1){//if already KHR
                    $coa_details[$pre->coa_id]["cur_debit"] += $pre->debit / KHRM;
                    $coa_details[$pre->coa_id]["cur_credit"] += $pre->credit / KHRM;
                  }
                }elseif($data['currency_id'] == 2){//USD
                  if($pre->currency == 2 ){
                    $coa_details[$pre->coa_id]["cur_debit"] += $pre->debit;
                    $coa_details[$pre->coa_id]["cur_credit"] += $pre->credit;
                  }elseif($pre->currency == 1){//if KHR convert to USD
                    $coa_details[$pre->coa_id]["cur_debit"] += $pre->debit / $rate;
                    $coa_details[$pre->coa_id]["cur_credit"] += $pre->credit / $rate;
                  }
                }
              }else{ //no consolidate
                  if($data['currency_id'] == 1){//KHR (show in million)
                    if($pre->currency==2){
                        continue;
                    }elseif($pre->currency==1){
                      $coa_details[$pre->coa_id]["cur_debit"]  += $pre->debit/KHRM;
                      $coa_details[$pre->coa_id]["cur_credit"] += $pre->credit/KHRM;
                    }
                  }elseif($data['currency_id'] == 2){
                    if($pre->currency==1){
                        continue;
                    }elseif($pre->currency==2){
                      $coa_details[$pre->coa_id]["cur_debit"]  += $pre->debit;
                      $coa_details[$pre->coa_id]["cur_credit"] += $pre->credit;
                    }
                  }
              }

              $coa_details[$pre->coa_id]["parent"]    = $pre->parent_id;
              $coa_details[$pre->coa_id]["code"]      = $pre->account_code;
              $coa_details[$pre->coa_id]["initial"]   = substr($pre->account_code, 0, 1);
              $coa_details[$pre->coa_id]["branch"]    = $pre->branch_code;
              $coa_details[$pre->coa_id]["currency"]  = $pre->currency;
              $coa_details[$pre->coa_id]["nbc_code"]  = $pre->nbc_code;
              $coa_details[$pre->coa_id]["type"]      = $icoa->type;
              $coa_details[$pre->coa_id]["symbol"]    = $icoa->symbol;
              $coa_details[$pre->coa_id]["reference"] = ($pre->reference!=" ")?$pre->reference:"-";

              $old_coa = $pre->coa_id;
              $cnt++;
            }
            
            $coa_details = convert_journal_detail_cu($coa_all,$coa_details,7);
            $coa_details = convert_journal_detail_cu($coa_all,$coa_details,6);
            $coa_details = convert_journal_detail_cu($coa_all,$coa_details,5);
            $coa_details = convert_journal_detail_cu($coa_all,$coa_details,4);
            $coa_details = convert_journal_detail_cu($coa_all,$coa_details,3);
            $coa_details = convert_journal_detail_cu($coa_all,$coa_details,2);
            $coa_details = convert_journal_detail_cu($coa_all,$coa_details,1);

            // foreach($coa_all as $icoa){
            //   // foreach($coa_details as $v){
            //   //   if($icoa->id == $v["parent"]){
            //   //     $coa_details[$icoa->id]["cur_debit"]  += $v["cur_debit"];
            //   //     $coa_details[$icoa->id]["cur_credit"]  += $v["cur_credit"];
            //   //     $coa_details[$icoa->id]["parent"]  = $icoa->parent_id;
            //   //     $coa_details[$icoa->id]["code"] = $icoa->account_code;
            //   //     $coa_details[$icoa->id]["nbc_code"] = $icoa->nbc_code;
            //   //     $coa_details[$icoa->id]["initial"] = substr($icoa->account_code, 0, 1);
            //   //     $coa_details[$icoa->id]["branch"] = $v->branch_code;
            //   //     $coa_details[$icoa->id]["currency"] = $v->currency;
            //   //     $coa_details[$icoa->id]["type"] = $icoa->type;
            //   //     $coa_details[$icoa->id]["symbol"] = $icoa->symbol;
            //   //   }
            //   // }
            // }
            ksort($coa_details);
/*            foreach ($coa_details as $key => $row)
            {
                $count[$key] = $row['code'];
            }
            array_multisort($count, SORT_ASC, $coa_details);
*/
        }
        $data['coa_details'] = $coa_details;
        $data['coa_all'] = $coa_all;
        $data['coa_all_nor'] = $coa_all_nor;
        return $data;
    }
	/**
	 * column type for sub account
	 * @param $id
	 */
    public function account_his(){

        $users = User::select('branch_id')->where('id','=',Auth::user()->id)->first();
        $data['search'] = CoaCategory::select('id', 'account_code','name')->where('type','>=','6')->get();
        $data['user_branch'] = CompanyBranch::select('id','branch_code')->where('id','=',$users->branch_id)->first();
        $data['branch'] = CompanyBranch::select('id','branch_code', 'short_name')->get();
        return $this->view('accounting.account_his', $data);
    }

    /**
     * tb_journal_detail
     * @param $id
     */
    public function getJD($id){
        $result = []; $p_bal = 0;
    		$result = JournalDetail::select(
                'journal_detail.coa_id',
                'journal_detail.debit',
                'journal_detail.credit',
                'journal_detail.reference',
                'journal_detail.branch_code',
                'journal_detail.id as jd_id',
                'journal_detail.journal_id as jd_jr_id',
                'journal_detail.description as descr',
                'journal_requiry.*',
                'transactions_requiry.id as tran_re_id'
            )->with('account')
    			->join('journal_requiry','journal_requiry.id','=','journal_detail.journal_id')
                ->leftJoin('transactions_requiry','transactions_requiry.id','=','journal_requiry.tran_id')
            	->where('journal_detail.coa_id','=',$id)
          ->orderBy('entry_date', 'ASC');
/*        $journal_side = JournalDetail::select(
            )->with(['journal'=>function($q){
                $q->with(['detail'=>function($que){
                    $que->with('account');
                }]);
            }])
            ->where('coa_id', '=', $id);
*/
        $journal_re = JournalRequiry::with(['detail'=>function($q){
            $q->with('account');
        }])->where('tran_id','>', 0);
        if(Request::has('start_date')){
        $data['start'] = date('Y-m-d', strtotime(Request::input('start_date')));
        $result = $result->where('entry_date', '>=', $data['start']);
        $journal_re = $journal_re->where('entry_date', '>=', $data['start']);
        $pre_jd = JournalDetail::join('journal_requiry', 'journal_requiry.id', '=', 'journal_id')
                    ->where('entry_date', '<', $data['start'])
                    ->where('journal_detail.coa_id', $id);
      }
      if(Request::has('end_date')){
        $data['end'] = date('Y-m-d', strtotime(Request::input('end_date')));
        $end = date('Y-m-d', strtotime(Request::input('end_date') .'+1 day'));
        $result = $result->where('entry_date', '<', $end);
        $journal_re = $journal_re->where('entry_date', '<', $end);
      }
      if(Request::has('branch')){
          $data['branch_code'] = Request::input('branch');
          if($data['branch_code']!=0){
            $result = $result->where('journal_detail.branch_code', '=', $data['branch_code']);
            if(!is_null($pre_jd))
                $pre_jd = $pre_jd->where('journal_detail.branch_code', '=', $data['branch_code']);
          }
      }
      $p_bal = 0;
      if(!is_null($pre_jd)){
          $pre_jd = $pre_jd->get();
          $data['pre_jd'] = $pre_jd;
          foreach($pre_jd as $p){
            $p_bal += $p->debit - $p->credit;
          }
      }
  
      $initial = substr(CoaCategory::select('id', 'nbc_code')->where('id', $id)->first()->nbc_code, 0,1);
      $factor = 1;
      switch ($initial) {
        case '1':
        case '2':
        case '6':
            $factor = 1;
          break;
        case '3':
        case '4':
        case '5':
            $factor = -1;
          break;
        default:
          break;
      }
      $data['factor'] = $factor;
      $data['result'] = $result->get();
      $data['journal_re'] = $journal_re->get();
      $data['p_bal'] = (is_null($p_bal))? 0:$p_bal;
  		return $data;
    }

      public function getReference($id,$b_id){
          $branch_code = CompanyBranch::select('id', 'branch_code')->where('id', '=', $b_id)->first()->branch_code;
      		if(!empty($id)){
      			$result = JournalDetail::select('*')
      				->join('coa_categories','coa_categories.id','=','journal_detail.coa_id')
      				->where('journal_detail.coa_id','=',$id)->where('journal_detail.branch_code','=',$branch_code)->get();
      			return $result;
      		}else{
      			return false;
      		}
  	}

    function client_loan_account(){
        //$offset = isset($_GET['offset']) ? $_GET['offset'] : 15;
        $offset = isset($_GET['set_offset'])? $_GET['set_offset']:15;
        $cl = ClientLoanAccounts::where('id', '>', 0);
        if (Request::has('status')) {
            $cl->where('status', Request::input('status'));
        }
        $data['client_loans'] = $cl->paginate($offset);
        $data['set_offset'] = $offset;
        return $this->view('accounting.client_loan_account', $data);
    }

    function cla_audit($id){
        $ClientLoanAccounts = ClientLoanAccounts::find($id);
        $ClientLoanAccounts->status = 1;
        $ClientLoanAccounts->activated_on = date('Y-m-d');
        $ClientLoanAccounts->save();
        $this->userActivity(Auth::user()->id, $ClientLoanAccounts->id, 0, 'Audit ClientLoanAccount', Request::fullUrl());

        $this->do_audit($id, '', Auth::user()->id, 'client_loan_accounts', 1, 'approve client loan account');
        return redirect()->route('client_loan_account');
    }



    public function getGlReport(){
        define('USDTOKHR', 4100);
        define('KHRM', 1000000);

        $start_date = null;
        $end_date = null;
        $data['nbc'] = 0;
        $data['type'] = 7;
        $p_branch_code = '000';
        if(Request::has('nbc')) $data['nbc'] = 1;

        $start_date = Request::has('dpStart')? Request::input('dpStart'):"2018-10-01";
        $end_date = Request::has('dpEnd')? Request::input('dpEnd'):"2018-10-02";
        $data['start'] = $start_date;
        $data['end'] = $end_date;


        if(Auth::user()->role_id!=1 && Auth::user()->role_id!=2) $data['branch_code'] = Auth::user()->branch_code;
        $query_arr = array('id'=>Auth::user()->branch_id, 'role_id'=>Auth::user()->role_id);
        $B1 = new CompanyBranch();
        //$B1 = $this->getBranchByUser($B1, 'id', $query_arr);
        $data['branch'] = $B1->select('id', 'branch_code','branch_name', 'branch_code')->where('status','=',1)->get();
        $branch_code = CompanyBranch::select('branch_code')->where('id', Auth::user()->branch_id)->first()->branch_code;
        $query_arr = array('user_id'=>Auth::user()->id, 'branch_id'=>Auth::user()->branch_id, 'role_id'=>Auth::user()->role_id, 'branch_code'=>$branch_code);
        $B0 = new JournalDetail();
        $curJournalDetail = $B0->select('journal_detail.id as jd_id', 'debit', 'credit', 'branch_code', 'coa_id', 'journal_id',
        'journal_requiry.is_audit as audit', 'entry_date', 'coa_categories.parent_id as parent_id', 'account_code', 'nbc_code',
        'coa_categories.name as coa_name', 'coa_categories.parent_id', 'currency', 'journal_requiry.description as desc',
        'journal_requiry.invoice_number', 'journal_detail.reference')
        ->join('journal_requiry', 'journal_detail.journal_id', '=', 'journal_requiry.id')
        ->join('coa_categories', 'journal_detail.coa_id', '=', 'coa_categories.id');
        $curJournalDetail = $curJournalDetail->orderBy('entry_date', 'ASC')->where('journal_detail.is_audit', 1);
        if ($start_date || $end_date){
            // start < tr < end
            if ($start_date) $curJournalDetail = $curJournalDetail->where('entry_date', '>=', $start_date );
            if ($end_date) $curJournalDetail = $curJournalDetail->where('entry_date', '<', date('Y-m-d', strtotime($end_date .'+1 days')));
        }
        //$data['currency_id'] = 2;
//        dd(Request::All());
        if(Request::has('cur')){
            $data['currency_id'] = Request::input('cur');
            $curJournalDetail->where('currency', $data['currency_id']);
        }
        if(Request::has('br')){
            $data['branch_code'] = Request::input('br');
            $curJournalDetail->where('branch_code', $data['branch_code']);
        }
        if(Request::has('entry_id')){
            $data['entry_id'] = Request::input('entry_id');
            $curJournalDetail->where('journal_requiry.id', $data['entry_id']);
        }
        if(Request::has('ref_id')){
            $data['ref_id'] = Request::input('ref_id');
            $curJournalDetail->where('journal_detail.reference', $data['ref_id']);
        }
        if(Request::has('inv_no')){
            $data['inv_no'] = Request::input('inv_no');
            $curJournalDetail->where('journal_requiry.invoice_number', $data['inv_no']);
        }

        if(Request::has('note')){
            $data['quote_id'] = (Request::has('quote_id'))? Request::input('quote_id') : 'LIKE';
            $note = $data['note'] = Request::input('note');
            if($data['quote_id'] == "LIKE" || $data['quote_id'] == "NOT LIKE"){
                $note = $note."%";
            }
            $curJournalDetail->where('journal_requiry.description', $data['quote_id'] , $note);
        }
        // $curJournalDetail = $curJournalDetail->where('journal_requiry.description', 'not like', 'Auto Acc%');
        //$quote = "not like";
        //$curJournalDetail = $curJournalDetail->where('journal_requiry.description', $quote, 'Auto Acc%');
        //$curJournalDetail = $curJournalDetail->where('coa_categories.name', 'like', 'AIR-%');
        //$curJournalDetail = $curJournalDetail->where('coa_categories.name', $quote, 'Inc-Int%');

        $curJournalDetail = $curJournalDetail->get();
        // devide by coa_id
        $coa_arr = [];
        foreach($curJournalDetail as $jd){
            $coa_arr[$jd->account_code][] = $jd;
        }
        ksort($coa_arr);
        $data['coa_arr'] = $coa_arr;
        $parent_arr = CoaCategory::select('id', 'account_code', 'name', 'type')->where('type', '=', 6)->get();
        $p_coa_arr = [];
        foreach($parent_arr as $pa){
            $p_coa_arr[$pa->id] = $pa;
        }
        $data['p_coa_arr'] = $p_coa_arr;
        $currency_list = Currency::select('id', 'code')->get();
        $currency_arr = [];
        foreach($currency_list as $cur){
            $currency_arr[$cur->id] = $cur->code;
        }
        $data['currency_arr'] = $currency_arr;
        return $this->view('accounting.gl_report',$data);
    }

    function getGlReportAjax(){

        define('USDTOKHR', 4100);
        define('KHRM', 1000000);

        $start_date = null;
        $end_date = null;
        $data['nbc'] = 0;
        $data['type'] = 6;
        $p_branch_code = '000';
        if(Request::has('nbc')) $data['nbc'] = 1;
        if(Request::has('type')) $data['type'] = Request::input('type');

        $start_date = Request::input('dpStart');
        $end_date = Request::input('dpEnd');
        $data['start'] = $start_date;
        $data['end'] = $end_date;

        //reset select for view
        if(Request::has('consolidate') && Request::input('consolidate')==1){
            $data['consolidate'] = 1;
            $data['rate'] = Request::has('rate')?Request::input('rate'):USDTOKHR;
            if(Request::has('exchange_rate') && Request::input('exchange_rate')==1){
                $data['exchange_rate'] = 1;
            }else{
                $data['exchange_rate'] = 2;
            }
        }

        if(Auth::user()->role_id!=1 && Auth::user()->role_id!=2) $data['branch_code'] = Auth::user()->branch_code;

        $query_arr = array('id'=>Auth::user()->branch_id, 'role_id'=>Auth::user()->role_id);
        $B1 = new CompanyBranch();
        $B1 = $this->getBranchByUser($B1, 'id', $query_arr);
        $data['branch'] = $B1->select('id', 'branch_code','branch_name', 'branch_code')->where('status','=',1)->get();

        //find branch code
        $branch_code = CompanyBranch::select('branch_code')->where('id', Auth::user()->branch_id)->first()->branch_code;
        $query_arr = array('user_id'=>Auth::user()->id, 'branch_id'=>Auth::user()->branch_id, 'role_id'=>Auth::user()->role_id, 'branch_code'=>$branch_code);

        //in journal inquiry
        $JournalRequiry_arr = array();
        if ($start_date || $end_date){
            $JournalRequiry = JournalRequiry::where('id', '>', 0);
            if ($start_date) $JournalRequiry = $JournalRequiry->where('entry_date', '>=', $start_date);
            if ($end_date) $JournalRequiry = $JournalRequiry->where('entry_date', '<=', $end_date);
            $JournalRequiry = $JournalRequiry->get();
            foreach ($JournalRequiry as $ji) {
                $JournalRequiry_arr[] = $ji->id;
            }
        }

        //in journal inquiry before start
        $JournalRequiry_arr_0 = array();
        $JournalRequiry = JournalRequiry::where('id', '>', 0);
        if ($start_date) $JournalRequiry = $JournalRequiry->where('entry_date', '<=', $start_date)->whereNotIn('id', $JournalRequiry_arr);
        $JournalRequiry = $JournalRequiry->get();
        foreach ($JournalRequiry as $ji) {
            $JournalRequiry_arr_0[] = $ji->id;
            $JournalRequiry_arr[] = $ji->id;
        }

        //get income 5xx (5) **********************************************************
        $subsub_in = CoaCategory::select('id', 'parent_id', 'nbc_code','name', 'currency', 'type', 'account_code')
        ->where('type', 6)->where('id', Request::input('coa_id'));

        $data['currency_id'] = 2;
        if(Request::has('cur')){
            $data['currency_id'] = Request::input('cur');
        }
        if($data['currency_id']!=100) $subsub_in->where('currency','=', $data['currency_id']);

        $data['in'] = $in = $subsub_in->first();
        $arr_in['parent_ids'] = array();

        //get type 6
        $t6 = CoaCategory::select('id')->where('type', 7)->where('parent_id', $in->id)->get();
        $tr6 = array();
        $tr6[] = $in->id;
        foreach ($t6 as $t){
            $tr6[] = $t->id;
        }
        $B0 = new JournalDetail();
        $B0 = $this->getUserByBranch($B0, 'branch_code', $query_arr);
        $JournalDetail = $B0->select('journal_detail.*', 'journal_requiry.*', 'journal_requiry.description as description')->orderBy('journal_detail.id', 'DESC');
        if(Request::has('br')){
            $p_branch_code = $data['branch_code'] = Request::input('br');
            $JournalDetail = $JournalDetail->where('branch_code','=', $data['branch_code']);
        }
        $JournalDetail = $JournalDetail->whereIn('coa_id', $tr6);
        if ($start_date || $end_date){
            $JournalDetail = $JournalDetail->whereIn('journal_id', $JournalRequiry_arr);
        }

        $JournalDetail = $JournalDetail->rightJoin('journal_requiry', 'journal_requiry.id', '=', 'journal_id');
        $data['journals'] = $JournalDetail->get();

        return $this->view('accounting.gl_report_ajax',$data);
    }
    public function getTillerUnitByUnittype(){
        if (config('unified_posting.sale_handoff_enabled', false) && Request::input('handoff_sale_id')) {
            echo json_encode((new \App\Services\UnifiedOriginalSaleFinanceHandoff(DB::connection()))->dropdown(Request::input('handoff_sale_id'), 'unit', Request::input('unit_type_id')));
            return;
        }
        $unit_type_id = Request::input('unit_type_id');
        $unit = Unit::where('unit_type_id',$unit_type_id)->where('active',1)->where('status','available')->get();
        $option = '<option value=""> - </option>';
        foreach ($unit as $rowop) {
            $option .='<option value="'.$rowop->id.'">'.$rowop->code.' - '.$rowop->price.'</option>';
        }
        $data['option'] = $option;
        echo json_encode($data);
    }
    public function gennerateDrawdownAcc(){
        $branch_code = Request::input('branch_code');
        $last = isset(DrawdownAccounts::orderBy('id','DESC')->first()->id)?DrawdownAccounts::orderBy('id','DESC')->first()->id:null;
        if(!$last){
            $last=0;
        }
        $last = (int)filter_var($last, FILTER_SANITIZE_NUMBER_INT);
        $no = $last+1;
        $no = $branch_code.$this->getClientNumber($no,7);
        echo json_encode($no);
    }
    public function gennerateLoanAcc(){
        $branch_code = Request::input('branch_code');
        $last = isset(ClientLoanAccounts::where('years',date('Y'))->orderBy('acc_no','DESC')->first()->acc_no)?ClientLoanAccounts::where('years',date('Y'))->orderBy('acc_no','DESC')->first()->acc_no:null;
        if(!$last){
            $last=0;
        }
        $last = (int)filter_var($last, FILTER_SANITIZE_NUMBER_INT);
        $no = $last+1;
        $no = $branch_code.date('y').$this->getClientNumber($no,6);
        echo json_encode($no);
    }
    public function getProjectByCompany(){
        if (config('unified_posting.sale_handoff_enabled', false) && Request::input('handoff_sale_id')) {
            echo json_encode((new \App\Services\UnifiedOriginalSaleFinanceHandoff(DB::connection()))->dropdown(Request::input('handoff_sale_id'), 'project', Request::input('company_id')));
            return;
        }
        $company_id = Request::input('company_id');
        $project = Project::where('company_id',$company_id)->where('active',1)->get();
        $option = '<option value=""> - </option>';
        foreach ($project as $rowop) {
            $option .='<option value="'.$rowop->id.'">'.$rowop->short_code.' - '.$rowop->dealer.'</option>';
        }
        $data['option'] = $option;
        echo json_encode($data);
    }

    // get client for sale
    public function getClientForSale(){       

        $clients = Client::where('status',1)->get();//->with('general');


        $option = '<option value="0"> - </option>';
        foreach ($clients as $rowop) {
            $option .='<option value="'.$rowop->id.'">'.$rowop->name.'</option>';
        }
        $data['option'] = $option;
        echo json_encode($data);
    }
}
