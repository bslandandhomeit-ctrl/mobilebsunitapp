<?php
/**
 * Created by PhpStorm.
 * User: SOPHEAK
 * Date: 5/25/2015
 * Time: 5:41 PM
 */

namespace App\Http\Controllers;


use App\Models\CompanyBranch;
use App\Models\LoanApproval;
use App\Models\LoanCollateral;
use App\Models\LoanCostFee;
use App\Models\LoanHistory;
use App\Models\LoanDocument;
use App\Models\LoanPayments;
use App\Models\LoanWriteOff;
use App\Models\LoanClose;
use App\Models\LoanPayOff;
use App\Models\Product;
use App\Models\GuarantorCollateral;
use App\Models\Guarantor;
use App\Models\User;
use App\Models\JournalRequiry;
use App\Models\JournalDetail;
use App\Models\Client;
use App\Models\Client as Clients;
use App\Models\Holiday;
use App\Models\TransactionsRequiry;
use App\Models\Account;
use App\Models\CoaCategory;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Collection;
use App\Models\Loan;
use App\Models\RepaymentSchedule;
use App\Models\RescheduleRepaymentTemp;
use App\Models\ClientLoanAccounts;
use App\Models\Teller;
use App\Models\DrawdownAccounts;
use App\Models\Audit;
use App\Models\LoanPaymentsDraft;
use App\Models\ScheduleFee;
use App\Models\Notification;
use App\Models\Products\Product_type;
use App\Models\Currency;
use App\Models\TillTransaction;
use App\Models\SaveDraftLoan;
use App\Models\Unit;
use App\Models\PaymentOption;
use App\Models\UnitType;
use App\Models\Promotion;
use App\Models\UnitTypePromotion;
use App\Models\Project;
use App\Models\CompanyDrawdownAccounts;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Invoice;
use App\Models\LoanTypeConfig;
use App\Models\SystemDate;
use App\Models\LoanStatus;
use App\Models\LoanRestructure;
use App\Models\TransferBalance;
use App\Models\TransactionsPosting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Maatwebsite\Excel\Facades\Excel;
use DateTime;
use Illuminate\Support\Facades\Auth;
use Image;
use App;
use App\Models\FeeCharge;
use App\Models\SalePerson;
use URL;
use File;
use LoanCalculate;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    public function __construct()
    {

        $this->user_id = Auth::user()->id;
        $this->middleware('xss');
        $this->middleware('auth');
    }

    public function getCalendar()
    {
        $data['holiday'] = Holiday::all();
        return $this->view('loans.calendar', $data);
    }

    public function postAddCalendar()
    {
        if (Request::ajax()) {
            $click_date = Request::input('click_date');
            $holiday = Holiday::where('holiday_date', '=', $click_date)->first();

            $is_deleted = false;
            if (!empty($holiday)) {
                $holiday->delete();
                $is_deleted = true;
            } else {
                $holiday = new Holiday;
                $holiday->holiday_date = $click_date;
                $holiday->save();
            }
            return ['status' => true, 'is_deleted' => $is_deleted, 'holiday' => Holiday::all()];
        }
        return ['status' => false];
    }

    public function getUploadSheet()
    {
        return $this->view('loans.sheet');
    }

    public function postUploadSheet()
    {
        if (Request::hasFile('csv') && Request::file('csv')->isValid()) {
            $file = fopen(Request::input('csv'), "r");
            $b = false;
            while (!feof($file)) {
                $row = fgetcsv($file);
                if ($b == false) {
                    $b = true;
                } else {
                    $hol = Holiday::where('holiday_date', '=', $row[1])->first();
                    if (empty($hol) && $row[1] != null) {
                        $holiday = new Holiday;
                        $holiday->holiday_date = $row[1];
                        $holiday->save();
                    }
                }
            }
            fclose($file);
            return redirect()->route('date_holiday');
        }
        return redirect()->back()->with('error', 'Trying to upload invalid csv file.');
    }

    public function getApplyLoan()
    {
        $result = [];
        $searchterm = '';
        if (Request::has('searchterm')) {
            $searchterm = Request::input('searchterm');
            $searchterm = trim($searchterm);
            $loan_accounts = ClientLoanAccounts::select(['client_loan_accounts.id', 'units.code','client_id','clients.cus_acc', 'account_no', 'account_name', 'address', 'phone1', 'phone2', 'client_loan_accounts.status'])
                ->join('clients', 'clients.id', '=', 'client_loan_accounts.client_id')
                ->join('units','units.id','=','client_loan_accounts.unit_id')
                ->where('client_loan_accounts.status', '=', '1');
                // ->where('loan_ref', '=', null);
            $result = $loan_accounts->where(function ($q) use ($searchterm) {
                $q->where('client_loan_accounts.account_no', 'LIKE', '%'.$searchterm.'%')
                    ->orWhere('client_loan_accounts.account_name', 'LIKE', '%'.$searchterm.'%')
                    ->orWhere('clients.address', 'LIKE', '%'.$searchterm.'%')
                    ->orWhere('clients.phone1', 'LIKE', '%'.$searchterm.'%')
                    ->orWhere('clients.phone2', 'LIKE', '%'.$searchterm.'%')
                    ->orWhere('clients.client_name', 'LIKE', '%'.$searchterm.'%')
                    ->orWhere('clients.cus_acc', 'LIKE', '%'.$searchterm.'%')
                    ->orWhere('units.code','like','%'.$searchterm.'%');
            })->get();
            return $this->view('loans.apply_loan', ['search_results' => $result, 'searchterm' => $searchterm]);
        }
        return $this->view('loans.apply_loan', ['search_results' => $result]);
    }

    public function postApplyLoan()
    {
        if (Request::has('searchterm')) {
            $searchterm = Request::input('searchterm');
            $loan_accounts = ClientLoanAccounts::select('id', 'client_id', 'account_no', 'account_name');
            $result = $loan_accounts->where('account_no', 'LIKE', '%' . $searchterm . '%')
                ->orWhere('account_name', 'LIKE', '%' . $searchterm . '%')
                ->orWhere('client_id', 'LIKE', '%' . $searchterm . '%')
                ->get();
            return $this->view('loans.apply_loan', $result);
        }
    }

    public function getLoan($client_id = 0, $acc_id = 0)
    {
        $customer_acc = null;
        if ($acc_id > 0) {
            $customer_acc = ClientLoanAccounts::select('id', 'account_no', 'branch','project_id')->where('id', '=', $acc_id)->where('client_id', $client_id)->where('status', '=', 1)->get();
        } else {
            $customer_acc = ClientLoanAccounts::select('id', 'account_no', 'client_id', 'branch', 'currency', 'status','project_id')->where('client_id', $client_id)->where('status', '1')->get();
        }
        // Set Contract ID
        $contract_id_str = "00000000000";
        // $last_contract_id = Loan::select('contract_id')->orderBy('contract_id', 'desc')->first()->contract_id;
        $last_contract_id = Loan::select('contract_id')->orderBy(DB::raw('ABS(contract_id)'), 'desc')->orderBy('id','DESC')->first()->contract_id;


        // if (!empty($last_contract_id) && count($last_contract_id) > 0) {
        //     $cont_year = substr($last_contract_id, 3, 4);
        //     $cont_num = floatval(substr($last_contract_id, 8, 3)) + 1;
        //     if ($cont_year < date('y')) {
        //         $contract_id_str = CONTRACT_PRE . date('Y') . '/' . str_pad(1, 3, '0', STR_PAD_LEFT);
        //     } else {
        //         $contract_id_str = CONTRACT_PRE . date('Y') . '/' . str_pad($cont_num, 3, '0', STR_PAD_LEFT);
        //     }
        // } else {
        //     $contract_id_str = CONTRACT_PRE . date('Y') . '/' . str_pad(1, 3, '0', STR_PAD_LEFT);
        // }
        if ($client_id > 0) {

            //$client = Client::select('id', 'client_name')->where('id', '=', $client_id)->where('status', '=', 1)->first();
            $client = Client::with('general')->where('id', $client_id)->where('status', '=', 1)->first();
            if (empty($client)) {
                return redirect()->route('list_client');
            }

            $product_id = Product::select(['id', 'product_name', 'product_price', 'product_type_id'])->with('product_types')->where('is_loan', '=', 0)->orderBy('id', 'DESC')->get();
            $productsTypes = Product_type::all();
            $project_id = [];
            foreach ($customer_acc as $customer_acc_project) {
                $project_id[$customer_acc_project->project_id] = $customer_acc_project->project_id;
            }
            $units = Unit::select('id','code','price')->get();

            $projects = Project::select('id','dealer','short_code')->whereIn('id',$project_id)->get();

            $query_arr = array('id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);
            $B1 = new CompanyBranch();
            $B1 = $this->getBranchByUser($B1, 'id', $query_arr);
            $branch_name = $B1->select('id', 'branch_name')->where('status', '=', 1)->get();
            $co_name = User::select('users.id', 'name')->join('roles', 'users.role_id', '=', 'roles.id')->whereIn('role', ['co', 'sco'])->get();
            $data['co_id'] = Loan::select('id', 'co')->with(['co_user' => function ($query) {
                $query->select('id', 'phone');
            }])->first();

            //chuch

            return $this->view('loans.add', $data,
                [
                    'client' => $client,
                    'product_id' => $product_id->toArray(),
                    'branch_name' => $branch_name,
                    'co_name' => $co_name,
                    'customer_acc' => $customer_acc,
                    'contract_id_str' => $contract_id_str,
                    'productsTypes' => $productsTypes,
                    'units' => $units,
                    'projects' => $projects,
                    'acc_id' => $acc_id
                ]);
        }
        return redirect()->back();
    }

    public function getUnitInfo(){
        $unit_id = Request::input('unit_id');
        $unitInfo = Unit::where('id',$unit_id)->get()->first();
        $paymentOption = PaymentOption::select('payment_options.*')
                        ->join('unit_types','payment_options.unit_type_id','=','unit_types.id')
                        ->join('units','unit_types.id','=','units.unit_type_id')
                        ->where('payment_options.active',1)
                        ->where('units.id',$unit_id)->get();
        $getPromotion = Promotion::select('promotions.*')
                        ->join('unit_type_promotions','unit_type_promotions.promotion_id','=','promotions.id')
                        ->join('unit_types','unit_type_promotions.unit_type_id','=','unit_types.id')
                        ->join('units','units.unit_type_id','=','unit_types.id')
                        ->where('promotions.active',1)
                        ->where('units.id',$unit_id)
                        ->where('promotions.start_date','<=', date('Y-m-d'))
                        ->where('promotions.end_date','>=', date('Y-m-d'))
                        ->groupBy('promotions.id')
                        ->orderBy('promotions.id','DESC')
                        ->get()->first();

        $option = '<option value=""> - </option>';
        foreach ($paymentOption as $rowop) {
            $option .='<option value="'.$rowop->id.'">'.$rowop->name.'</option>';
            if($paymentOption->last() == $rowop) {
                $option .='<option value="0">Other</option>';
            }
        }
        $data['promotion'] = isset($getPromotion->discount_amount)?$getPromotion->discount_amount:0;
        $data['unitInfo'] = $unitInfo;
        $data['option'] = $option;
        echo json_encode($data);
    }

    public function getUnitType(){
        $project_id = Request::input('project_id');
        $unit_types = UnitType::where('project_id',$project_id)->where('active',1)->get();
        $option = '<option value=""> - </option>';
        foreach ($unit_types as $rowop) {
            // $option .='<option value="'.$rowop->id.'">'.$rowop->short_code.' - '.$rowop->name.'</option>';
            $option .='<option value="'.$rowop->id.'">'.$rowop->name.'</option>';
        }
        $data['option'] = $option;
        echo json_encode($data);
    }
    public function getUnitInfoDetail(){
        $unit_type_id = Request::input('unit_type_id');
        $unit_id = Request::input('unit_id');        
        $unit = Unit::where('unit_type_id',$unit_type_id)->where('id',$unit_id)
        ->where('active',1)
        ->where('status','available')->first();
        echo json_encode($unit);
    }   

    public function getUnitByUnittype(){
        $unit_type_id = Request::input('unit_type_id');
        $unit = Unit::where('unit_type_id',$unit_type_id)->where('active',1)->where('status','available')->get();
        $option = '<option value="0"> - </option>';
        foreach ($unit as $rowop) {
            $option .='<option value="'.$rowop->id.'">'.$rowop->code.' - '.$rowop->price.'</option>';
        }
        $data['option'] = $option;
        echo json_encode($data);
    } 
    public function getAllUnitByUnittype(){
        $unit_type_id = Request::input('unit_type_id');
        $unit = Unit::where('unit_type_id',$unit_type_id)->get();
        $option = '<option value="0"> - </option>';
        foreach ($unit as $rowop) {
            $option .='<option value="'.$rowop->id.'">'.$rowop->code.' - '.$rowop->price.'</option>';
        }
        $data['option'] = $option;
        echo json_encode($data);
    }
    public function getUnittypeConfog(){
        $unit_type_id = Request::input('unit_type_id');
        $config = LoanTypeConfig::where('unit_type_id',$unit_type_id)->first();
        echo json_encode(!empty($config)?$config:array());
    }
    public function getPaymentOption(){
        $payment_option = Request::input('payment_option');
        $paymentInfo = PaymentOption::where('id',$payment_option)->orderBy('id','DESC')->first();
        echo json_encode($paymentInfo);
    }
    public function getRestructurePaymentOption(){
        $payment_option = Request::input('payment_option');
        $paymentInfo = PaymentOption::where('id',$payment_option)->orderBy('id','DESC')->first();
        echo json_encode($paymentInfo);
    }
    public function addFirstCollectionDate(){
        $start_payment_date = Request::input('start_payment_date');
        $down_payment_duration = Request::input('down_payment_duration');
        if($start_payment_date){
            $start_payment_date = date('Y-m-d',strtotime($start_payment_date));
            $final = date("Y-m-d", strtotime($start_payment_date."+".(int)$down_payment_duration." month"));
            $disburse_date = date("Y-m-d", strtotime($final."-1 month"));
            $data['start_payment_date'] = $final;
            $data['disburse_date'] = $disburse_date;
            echo json_encode($data);
        }
    }
    public function getProjectsByAccc(){
        $acc_id       = Request::input('client_acc_id');
        $customer_acc = ClientLoanAccounts::where('id', $acc_id)->where('status', '1')->first();
        $unit_types   = UnitType::where('project_id',$customer_acc->project_id)->where('id',$customer_acc->unit_type_id)->get();
        $companies    = CompanyBranch::where('id',$customer_acc->projects->company_id)->get();
        $projects     = Project::where('id',$customer_acc->project_id)->get();
        $units        = Unit::where('id',$customer_acc->unit_id)->get();
        $last         = isset(Loan::where('year',date('Y'))->orderBy('contrac_no','DESC')->first()->contrac_no)?Loan::where('year',date('Y'))->orderBy('contrac_no','DESC')->first()->contrac_no:null;
        if(!$last){
            $last=0;
        }
        $last = (int)filter_var($last, FILTER_SANITIZE_NUMBER_INT);
        $no = $last+1;
        $no = 'LC'.$companies->first()->branch_code.date('y').$this->getClientNumber($no,5);
        $unitype_option = '<option value="0"> - </option>';
        $company_option = '<option value="0"> - </option>';
        $project_option = '<option value="0"> - </option>';
        $unit_option = '<option value="0"> - </option>';
        foreach ($unit_types as $rowop) {
            $unitype_option ='<option value="'.$rowop->id.'">'.$rowop->name.'</option>';
        }
        foreach ($companies as $row_company) {
            $company_option ='<option value="'.$row_company->id.'">'.$row_company->branch_name.'</option>';
        }

        foreach ($projects as $proj_row) {
            $project_option ='<option value="'.$proj_row->id.'">'.$proj_row->short_code.' - '.$proj_row->dealer.'</option>';
        }

        foreach ($units as $rowunit) {
            $unit_option ='<option value="'.$rowunit->id.'">'.$rowunit->code.' - '.$rowunit->price.'</option>';
        }

        $data['unittypes'] = $unitype_option;
        $data['company'] = $company_option;
        $data['project'] = $project_option;
        $data['unit'] = $unit_option;
        $data['unit_id'] = $customer_acc->unit_id;
        $data['contract_id'] = $no;
        echo json_encode($data);
    }
    public function getLoan_old($client_id = 0, $acc_id = 0)
    {
        $customer_acc = null;
        if ($acc_id > 0) {
            $customer_acc = ClientLoanAccounts::select('id', 'account_no', 'branch')->where('id', '=', $acc_id)->where('status', '=', 1)->get();
        } else {
            $customer_acc = ClientLoanAccounts::select('id', 'account_no', 'client_id', 'branch')->where('client_id', '=', $client_id)->where('status', '=', 1)->get();
        }
        // Set Contract ID
        $contract_id_str = "";
        $last_contract_id = Loan::select('contract_id')->orderBy('contract_id', 'desc')->first()->contract_id;
        if (!empty($last_contract_id) && count($last_contract_id) > 0) {
            $cont_year = substr($last_contract_id, 1, 2);
            $cont_num = floatval(substr($last_contract_id, 3, 4)) + 1;
            if ($cont_year < date('y')) {
                $contract_id_str = CONTRACT_PRE . date('Y') . '/' . str_pad(1, 3, '0', STR_PAD_LEFT);
            } else {
                $contract_id_str = CONTRACT_PRE . date('Y') . '/' . str_pad($cont_num, 3, '0', STR_PAD_LEFT);
            }
        } else {
            $contract_id_str = CONTRACT_PRE . date('Y') . '/' . str_pad(1, 3, '0', STR_PAD_LEFT);
        }
        if ($client_id > 0) {
            $client = Client::select('id', 'client_name')->where('id', '=', $client_id)->where('status', '=', 1)->first();
            if (empty($client)) {
                return redirect()->route('list_client');
            }
            $product_id = Product::select(['id', 'product_name', 'product_price'])->where('is_loan', '=', 0)->orderBy('id', 'DESC')->get();
            $query_arr = array('id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);
            $B1 = new CompanyBranch();
            $B1 = $this->getBranchByUser($B1, 'id', $query_arr);
            $branch_name = $B1->select('id', 'branch_name')->where('status', '=', 1)->get();
            $co_name = User::select('users.id', 'name')->join('roles', 'users.role_id', '=', 'roles.id')->whereIn('role', ['co_user', 'cco_user'])->get();
            $data['co_id'] = Loan::select('id', 'co')->with(['co_user' => function ($query) {
                $query->select('id', 'phone');
            }])->first();

            //chuch
            $data['sacc'] = '';

            return $this->view('loans.add', $data,
                ['client' => $client,
                    'product_id' => $product_id->toArray(),
                    'branch_name' => $branch_name,
                    'co_name' => $co_name,
                    'customer_acc' => $customer_acc,
                    'contract_id_str' => $contract_id_str
                ]);
        }
        return redirect()->back();
    }

    public function getSalePerson(){
        $id = Request::input('sale_person_id');
        $sale_person_id = Request::input('sale_team');
        $option = '<option value="0"> - </option>';
        if($id){
            $sale_person = SalePerson::where(['active' => 1,'sale_team_id' => $id])->get();
            $selected = '';
            foreach($sale_person as $row) {
                if($row->id == $sale_person_id){
                    $selected = 'selected';
                }
                $option .='<option value="'.$row->id.'" '.$selected.'>'.$row->name.' - '.$row->email.'</option>';
            }
        }
        $data['option'] = $option;
        echo json_encode($data);
    }
    public function getClose($loan_id = 0)
    {
        if ($loan_id > 0) {
            // $lc = Loan::with('client_loan_account')->where('id', '=', $loan_id)
            //     ->whereIn('status', [3, 8])
            //     ->first();
            $B0 = new Loan();
            //$B0 = $this->getUserByBranch($B0, 'company_branch_id', $query_arr);
            $lc = $B0->select(['loans.*',
                                    'clients.client_name',
                                    'client_loan_accounts.account_no'
                                ])
                ->join('client_loan_accounts', 'client_loan_accounts.id', '=', 'loans.loan_account_id')
                ->join('clients', 'clients.id', '=', 'loans.client_id');
            $lc = $lc->with([
                'payment' => function ($q) {
                    $q->select('id',
                                'loan_id',
                                'paid_principal',
                                'paid_interest',
                                'paid_fee',
                                'paid_other_fee',
                                'penalty_amount',
                                'repayment_owed',
                                'repayment_date',
                                'payment_month',
                                'status',
                                'condition_id'
                            )->orderBy('id', 'asc');
                },
                'client_loan_account',
                'schedule' => function ($q) {
                        $q->select('loan_id',
                            'schedule_date',
                            'date_num',
                            'interest',
                            'principal',
                            'fee',
                            'other_fee',
                            'intraday_rate',
                            'no',
                            'type',
                            'loan_no'
                        );
                },
                'transaction' => function ($q) {
                    $q->where('trans_type', 'LIKE', '%Loan Repayment%');
                },
                'penalty_record' => function ($q) {
                    $q->select('id', 'loan_id', 'record_date', 'owed_penalty')->orderBy('id', 'DESC');
                }
            ])->whereIn('loans.status', [3, 8]);

            $lc = $lc->where('loans.id', $loan_id)->first();

            $dpDateClone = !is_null(Request::All()) ? Request::input('dpDateClone') : date('Y-m-d');
            if(!$dpDateClone){
                $dpDateClone = date('Y-m-d');
            }
            // $fee_arr = get_sch_fee_array($lc, $dpDateClone, "SCH");
            // $fee_bal = 0;
            // foreach($fee_arr as $f){
            //     $fee_bal += $f[1];
            // }
            $penalty_arr = LoanCalculate::getTotalPenalty($lc, $dpDateClone);
            $sch_repay_arr = get_auto_repay_array($lc, $dpDateClone);
            $penalty = $penalty_arr[4];
            $fee_bal = $penalty_arr[9];
            $other_fee = $penalty_arr[10];
            $find_unit_id = Loan::where('id', '=', $loan_id)->get();
            $unit_code = Unit::find($find_unit_id[0]->unit_id);
    
            $loan_account = DrawdownAccounts::where('id', $find_unit_id[0]->loan_account_id)->first();

            return $this->view('loans.close', ['loan_id' => $loan_id,
                                                'loan'          => $lc,
                                                'dpDateClone'   => $dpDateClone,
                                                'fee_bal'       => $fee_bal,
                                                'penalty'       => $penalty,
                                                'other_fee'     => $other_fee,
                                                'sch_repay_arr' => $sch_repay_arr,
                                                'unit_code'     => $unit_code,
                                                'drawdow_acc'   => $drawdow_acc,
                                                'loan_account'  => $loan_account
                                            ]);
        }
        return redirect()->back();
    }

    public function postLoan($client_id = 0, $acc_id = 0)
    {
        //$client = Client::select('id')->where('id', '=', $client_id)->first();
        // $client = Client::with('egneral')->where('id', $client_id)->first();
        $client = Client::where('clients.id', $client_id)
            ->join('client_cbc_general AS general', 'clients.id', '=', 'general.client_id')
            ->select('clients.*')->first();
        if (empty($client)) {
            return redirect()->back();
        }
        $data = Request::except(['_token', 'annual_yield', 'holiday_flag', 's_ppi', 's_charge', 's_schedule_date', 's_principal', 's_term',
            'total_d', 'repayment_interest', 'repayment_monthly', 'repayment_date', 'repayment_principal', 'repayment_intraday_rate',
            'repayment_fee', 'balance', 'prin_balance', 'loan_type', 'maintain_fee', 'admin_fee', 's_fee', 'digit',
            'has_first_payment_amount', 'first_payment_amount']);
        $rule = [
            // 'product_id' => 'required',
            // 'loan_account_id' => 'required|unique:loans,loan_account_id',
            'down_payment'      => 'required | numeric',
            'submitted_on'      => 'required',
            'company_branch_id' => 'required',
            'loan_amount'       => 'required | numeric',
            'loan_duration'     => 'required',
            'interest_rate'     => 'required | numeric',
            'penalty_rate_type' => 'required',
            'penalty_period1'   => 'required',
            'penalty_rate1'     => 'required',
            'payoff_period1'    => 'required',
            'payoff_period2'    => 'required',
            'pay_off_rate1'     => 'required',
            'pay_off_rate2'     => 'required',
            'days_of_month'     => 'required',
            'start_date'        => 'required',
            'repayment_type'    => 'required',
        ];
        $v = Validator::make($data, $rule);
        if ($v->fails()) {
            return redirect()->back()->withErrors($v->errors());
        }

        $companies = CompanyBranch::where('id',$data['company_branch_id'])->first();
        if(!$companies){
            return redirect()->back()->withErrors('No Company.');
        }
        $check_contract = Loan::where('contract_id',$data['contract_id'])->first();
        if($check_contract){
            $last = isset(Loan::where('year',date('Y'))->orderBy('contrac_no','DESC')->first()->contrac_no)?Loan::where('year',date('Y'))->orderBy('contrac_no','DESC')->first()->contrac_no:null;
            if(!$last){
                $last=0;
            }
            $last = (int)filter_var($last, FILTER_SANITIZE_NUMBER_INT);
            $no = $last+1;
            $no = 'LC'.$companies->branch_code.date('y').$this->getClientNumber($no,5);
            $data['contract_id'] = $no;
        }
        if(!$data['loan_account_id']){
            return redirect()->back()->withErrors('No Account ID for loan.');
        }
        $addNew = new Loan();
        foreach ($data as $key => $value) {
            if ($key == 's_schedule_date' || $key == 's_date_num' || $key == 's_interest' || $key == 's_intraday_rate' || $key == 's_principal' || $key == 's_term') continue;
            if ($key == 'repayment_date' || $key == 'repayment_principal' || $key == 'total_d' || $key == 'repayment_interest' || $key == 'repayment_intraday_rate' || $key == 's_term' || $key == 'repayment_charge' || $key == 'repayment_monthly' || $key == '') continue;

            if ($key == 'balloon_input') {
                $balloon_value = "";
                if (is_array($value)) {
                    $balloon_value = implode(",", $value);
                }
                $addNew->balloon_amount_array = $balloon_value;
            } elseif ($key == 'custom_flag') {
                if ($value == 'on') { 
                    $addNew->$key = 1;
                }
            } else {
                $addNew->$key = $value;
            }
        }
        // if (!empty($data['contract_date'])) {
        //     $addNew->contract_date = date('Y-m-d', strtotime($data['contract_date']));
        // } else {
        //     $addNew->contract_date = date('Y-m-d', strtotime($data['start_date']));
        // }
        
        $addNew->holiday_flag       = Request::input('holiday_flag', 0);
        $addNew->last_schedule_date = date('Y-m-d', strtotime(Request::Input('start_date')));
        $addNew->admin_fee          = floatval(Request::input('admin_fee'));
        $addNew->maintain_fee       = floatval(Request::input('maintain_fee'));
        if (Request::has('loan_type')) {
            //$addNew->loan_type = Product_type::select('id', 'products_type_name')->where('products_type_name', Request::input('loan_type'))->first()->id;
            $addNew->loan_type = Request::input('loan_type'); // we've changed flow. users can defined loan type by select the loan type.
        }
        // get annual_yield
        //$holiday = Holiday::All();
        $holiday = [];
        if ($addNew->holiday_flag == 1) {
            $holiday = $addNew->holiday;
        }
        $admin_fee = floatval(Request::input('admin_fee'));
        $maintain_fee = floatval(Request::input('maintain_fee'));
        $maintain_fee_opt = Request::input('maintain_fee_opt');
        $admin_fee_opt = Request::input('admin_fee_opt');
        $other_fee = floatval(Request::input('other_fee'));
        $digit = Request::input('digit');
        $repayment_array = LoanCalculate::monthly_loan_schedule($addNew->repayment_type, $addNew->start_date,
            $addNew->loan_duration, $addNew->loan_amount, $addNew->interest_rate, $addNew->balloon, $addNew->balloon_month,
            $addNew->monthly_payment, $addNew->balloon_amount_array, $addNew->custom_flag, $addNew->days_of_month,
            $addNew->holiday_flag, $holiday, 0, null, $addNew->disburse_date, null, null, null, null, null, $addNew->frequency, 0, null, $admin_fee,
            $admin_fee_opt, $maintain_fee, $maintain_fee_opt, null, $digit, $addNew->monthly_amount)[0];
            $first_payment_amount = floatval(Request::input('first_payment_amount', 0));
            $downPayment_arrray = LoanCalculate::monthly_loan_schedule_downPayment(1, $addNew->start_payment_date,
            $addNew->down_payment_duration, $addNew->down_payment, 0, $addNew->balloon, $addNew->balloon_month,
            $addNew->monthly_payment, $addNew->balloon_amount_array, $addNew->custom_flag, $addNew->days_of_month,
            $addNew->holiday_flag, $holiday, 0, null, $addNew->disburse_date, null, null, null, null, null, $addNew->frequency, 0, null, $admin_fee,
            $admin_fee_opt, $maintain_fee, $maintain_fee_opt, null, $digit, $addNew->monthly_amount, 'loan', $first_payment_amount)[0];

        // $downPayment_arrray = LoanCalculate::monthly_loan_schedule_downPayment(1,$start_payment_date,$installment_duration,$down_payment,$annual_interest,
        //             $l_balloon_num, $l_balloon_month, $l_monthly_pay, $l_bal_amount_array,$custom_flag,$l_days_of_month,
        //             $holiday_flag, $holiday, 0, $principal_input, $disburse_on, $round, null, $c_principal, $ppi, null,
        //             $frequency, $is_disburse, $loan_id,$admin_fee, $admin_fee_opt, $maintain_fee, $maintain_fee_opt, $c_fee, intval($change_digit), floatval($monthly_amount))[0];

        //intval($change_digit), floatval($monthly_amount)
        /*
                $repayment_array = LoanCalculate::monthly_loan_schedule($l_repayment_type,$l_start_date,$l_tenure,$l_amount,$l_rate,
                    $l_balloon_num, $l_balloon_month, $l_monthly_pay, $l_bal_amount_array,$custom_flag,$l_days_of_month,
                    $holiday_flag, $holiday, 0, $principal_input, $disburse_on, $round, null, $c_principal, $ppi, null,
                    $frequency, $is_disburse, $loan_id,$admin_fee, $admin_fee_opt, $maintain_fee, $maintain_fee_opt)[0];
        */

        $addNew->year = date('Y');
        $addNew->contrac_no = substr($addNew->contract_id, 6, 5);
        $addNew->client_id = $client_id;
        $addNew->admin_fee = $admin_fee;
        $addNew->maintain_fee = $maintain_fee;
        $addNew->other_fee = $other_fee;
        $addNew->workflow_status = 'create';
        $getLoanSch = LoanCalculate::getLoanSch($repayment_array, $admin_fee, $admin_fee_opt, $maintain_fee, $maintain_fee_opt, $other_fee,
            $addNew->repayment_type, 0, 0, 0, $addNew->disburse_date, $addNew->start_date, null, $digit);

        $getDownpayment = LoanCalculate::getDownpayment($downPayment_arrray, $admin_fee, $admin_fee_opt, $maintain_fee, $maintain_fee_opt, $other_fee,
            $addNew->repayment_type, 0, 0, 0, $addNew->disburse_date, $addNew->start_date, null, $digit);

        $tb_sch = $getLoanSch['tb_sch'];
        $tb_sch_down = $getDownpayment['tb_sch'];
        $addNew->annual_yield = $getLoanSch['annual_yield'];
        $sell_price = $data['down_payment'] +  $data['loan_amount'];
        $down_payment = $data['down_payment'];
        $loan_amount = $data['loan_amount'];
        //case manual schedule *****************************
        if ($addNew->repayment_type == 8) {
            $addNew->annual_yield = Request::input('annual_yield');
        }
        // if (!empty($addNew->product_id) && $addNew->product_id > 0) {
            // $ch_loan = Loan::select('id')->where('product_id', '=', $addNew->product_id)->first();
            $ch_loan = Loan::select('id')->where('product_id', '=', 100000)->first();
            if (empty($ch_loan)) {
                if (Auth::check()) {
                    $id = Auth::user()->id;
                    $addNew->user_id = $id;
                } else {
                    return redirect()->route('login');
                }
                // $product = Product::select(['id', 'dealer_id', 'is_loan'])->where('id', '=', $addNew->product_id)->first();
                // if (!empty($product)) {
                //     $addNew->dealer_id = $product->dealer_id;
                //     $product->is_loan = 1; /* product was loan */
                // } else {
                //     return redirect()->back()->withErrors('No product for loan.');
                // }
                $balance_loan = $down_payment;
                if ($addNew->save()) {

                    if($addNew->unit_id){
                        $units = Unit::where('id',$addNew->unit_id)->where('status','deposit')->first();
                        if($units){
                            $units->status = 'contract';
                            $units->save();
                        }
                    }
                    // if ($product->save()) {
                        $this->do_audit($addNew->id, Auth::user()->id, '', 'loans', 0, 'add loan');

                        $this->userActivity($addNew->user_id, $addNew->id, 6, 'Create Loan');
                        // Inset Down Payment 
                        $a =0;
                        for ($i = 1; $i <= count($tb_sch_down); $i++) {
                            $a++;
                            $beginning = $balance_loan;
                            $balance_loan = $balance_loan - $tb_sch_down[$i]['prin'];
                            $data = [
                                'loan_id' => $addNew->id, 
                                'no' => $a, 
                                'loan_no' => $a, 
                                'schedule_date' => $tb_sch_down[$i]['date'],
                                'date_num' => $tb_sch_down[$i]['day'], 
                                'beginning' => $beginning,
                                'interest' => $tb_sch_down[$i]['int'],
                                'principal' => $tb_sch_down[$i]['prin'], 
                                'fee' => isset($tb_sch_down[$i]['fee'])?$tb_sch_down[$i]['fee']:0,
                                'other_fee' => $tb_sch_down[$i]['other_fee'], 
                                'intraday_rate' => $tb_sch_down[$i]['intra_rate'],
                                'balance' => $balance_loan,
                                'type' => 'downpayment',
                            ];
                                RepaymentSchedule::insert($data);
                        }

                        // Insert Repayment Schedule
                        // if ($addNew->repayment_type == 8) {
                        //     //$count_r = count(Request::input('repayment_date'));
                        //     //for ($m = 0; $m < $count_r - 1; $m++) {
                        //     for ($m = 0; $m <= $addNew->loan_duration; $m++) {
                        //         $a++;
                        //         $principal = Request::input('repayment_principal')[$m];
                        //         $schedule_date = Request::input('repayment_date')[$m];
                        //         $intraday_rate = Request::input('repayment_intraday_rate')[$m];
                        //         $sch_fee = Request::input('repayment_fee')[$m];
                        //         $date_num = Request::input('total_d')[$m];
                        //         $interest = Request::input('repayment_interest')[$m];
                        //         $beginning = $balance_loan;
                        //         $balance_loan = $balance_loan - $principal;
                        //         $data = [
                        //             'loan_id' => $addNew->id,
                        //             'no' => $a,
                        //             'schedule_date' => $schedule_date,
                        //             'date_num' => $date_num,
                        //             'beginning' => $beginning,
                        //             'interest' => $interest,
                        //             'principal' => $principal,
                        //             'fee' => $sch_fee,
                        //             'intraday_rate' => $intraday_rate,
                        //             'balance' => $balance_loan,
                        //         ];
                        //         if ($schedule_date && $date_num && $interest) RepaymentSchedule::insert($data);
                        //     }
                        //     $addNew->rate_type = "Manual";
                        // } else {
                            $balance_loan = $loan_amount;
                            $b = 0;
                            for ($i = 1; $i <= count($tb_sch); $i++) {
                                $a++;
                                $b++;
                                $beginning = $balance_loan;
                                $balance_loan = $balance_loan - $tb_sch[$i]['prin'];
                                $data = [
                                    'loan_id' => $addNew->id,
                                    'no' => $a,
                                    'loan_no' => $b,
                                    'schedule_date' => $tb_sch[$i]['date'],
                                    'date_num' => $tb_sch[$i]['day'],
                                    'beginning' => $beginning,
                                    'interest' => $tb_sch[$i]['int'],
                                    'principal' => $tb_sch[$i]['prin'],
                                    'fee' => $tb_sch[$i]['fee'],
                                    'other_fee' => $tb_sch[$i]['other_fee'],
                                    'intraday_rate' => $tb_sch[$i]['intra_rate'],
                                    'balance' => $balance_loan,
                                ];
                                    RepaymentSchedule::insert($data);
                            }
                            if ($addNew->repayment_type == 7) {
                                $addNew->rate_type = "Annuity";
                            } else {
                                $addNew->rate_type = "Declining";
                            }
                        // }
                        $addNew->save();
                        $loan_acc = ClientLoanAccounts::select(['id', 'loan_ref'])->where('id', '=', $addNew->loan_account_id)->first();
                        if ($loan_acc) {
                            $loan_acc->loan_ref = $addNew->contract_id;
                            $loan_acc->activated_on = date("Y-m-d");
                            $loan_acc->status = 2; // Activate(Std)
                            $addNew->drawdown_acc = $loan_acc->drawdown_acc->account_no;
                            $addNew->save();
                            if ($loan_acc->save()) {
                                return redirect()->route('loan_detail', [$addNew->id]);
                            } else {
                                $addNew->delete();
                                $product->is_loan = 0;
                                Session::flash('message', 'Update client loan account information not successfully');
                            }
                        } else {
                            $addNew->delete();
                            $product->is_loan = 0;
                            Session::flash('message', 'Update client loan account information not successfully');
                        }
                    // } else {
                    //     $addNew->delete();
                    //     $product->is_loan = 0;
                    //     Session::flash('message', 'Add new loan information not successfully');
                    // }
                }
            };
        // }
        return redirect()->route('loan_detail', [$addNew->id])->with(['msg' => 'Saved successfully.']);
    }

    public function getEditLoan($loan_id = 0)
    {
        if ($loan_id > 0) {
            $data['loan'] = Loan::where('id', '=', $loan_id)
                ->with(['client' => function ($query) {
                    $query->select('id', 'client_name','cus_acc');
                }, 'product' => function ($query) {
                    $query->select(['id', 'product_name', 'product_price']);
                }])
                ->first();
            //$data['repayment_schedule'] = RepaymentSchedule::where('loan_id', $loan_id)->get();
            $unit_id = $data['loan']->unit_id;
            $project_id = $data['loan']->project_id;
            $unit_type_id = $data['loan']->unit_type_id;
            $projects = Project::select('id','dealer','short_code')->where('id',$project_id)->get();
            $unit_types = UnitType::where('id',$unit_type_id)->get();
            if (!empty($data['loan'])) {
                $data['branch_name'] = CompanyBranch::select('id', 'branch_name')->where('id',$data['loan']->company_branch_id)->where('status',1)->get();

                $data['co_name'] = User::select('users.id', 'name')->join('roles', 'users.role_id', '=', 'roles.id')->whereIn('role', ['sco', 'co'])->get();
                $data['co_id'] = Loan::select('id', 'co')->where('id', '=', $loan_id)->with(['co_user' => function ($query) {
                    $query->select('id', 'phone');
                }])->first();
                $data['loan_type'] = Loan::select('id', 'loan_type')->where('id', '=', $loan_id)->with(['product_type' => function ($q) {
                    $q->select('id', 'products_type_name');
                }])->first();
                $data['units'] = Unit::select('id','code','price')->where('id',$unit_id)->get();
                $data['projects'] = $projects;
                $data['unit_types'] = $unit_types;
                return $this->view('loans.edit', $data);
            }
        }
        return redirect()->back();
    }

    /**
     * Heng sopheak
     * edit on $average_bal
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function postEditLoan($loan_id = 0)
    {
        if ($loan_id > 0) {
            $data = Request::except(['_token', 'client_id', 'product_id', 'annual_yield', 'loan_type']);
            $rule = [
                'contract_id' => 'required',
                'down_payment' => 'required|numeric',
                'submitted_on' => 'required|date',
                'company_branch_id' => 'required',
                'loan_amount' => 'required|numeric',
                'loan_duration' => 'required',
                'interest_rate' => 'required|numeric',
                'penalty_rate_type' => 'required',
                'penalty_period1' => 'required',
                'penalty_rate1' => 'required',
                'payoff_period1' => 'required',
                'payoff_period2' => 'required',
                'pay_off_rate1' => 'required',
                'pay_off_rate2' => 'required',
                'days_of_month' => 'required',
                'start_date' => 'required|date',
                'repayment_type' => 'required',
            ];
            $v = Validator::make($data, $rule);
            if ($v->fails()) {
                return redirect()->back()->withErrors($v->errors());
            }
            $loan = Loan::where('id', '=', $loan_id)->first();
            if (!empty($loan)) {
                if (!empty($data['contract_id'])) {
                    $loan->contract_id = $data['contract_id'];
                }
                if (!empty($data['contract_date'])) {
                    $loan->contract_date = date('Y-m-d', strtotime($data['contract_date']));
                }
                if (Request::has('loan_type')) {
                    $loan->loan_type = Product_type::select('id', 'products_type_name')->where('products_type_name', Request::input('loan_type'))->first()->id;
                }
                $loan->submitted_on = $data['submitted_on'];
                $loan->loan_purpose = $data['loan_purpose'];
                $loan->company_branch_id = $data['company_branch_id'];
                $loan->co = $data['co'];
                $loan->sale_person = $data['sale_person'];
                $loan->doc_location = $data['doc_location'];
                $loan->down_payment = $data['down_payment'];
                $loan->loan_amount = $data['loan_amount'];
                $loan->loan_duration = $data['loan_duration'];
                $loan->interest_rate = $data['interest_rate'];
                $loan->loan_penalty_type = $data['loan_penalty_type'];
                $loan->penalty_rate_type = $data['penalty_rate_type'];
                if ($data['penalty_rate_type'] == 3) { //period
                    $loan->penalty_period2 = $data['penalty_period2'];
                    $loan->penalty_rate2 = $data['penalty_rate2'];
                }
                $loan->penalty_period1 = $data['penalty_period1'];
                $loan->penalty_rate1 = $data['penalty_rate1'];

                $loan->payoff_period1 = $data['payoff_period1'];
                $loan->pay_off_rate1 = $data['pay_off_rate1'];

                $loan->payoff_period2 = $data['payoff_period2'];
                $loan->pay_off_rate2 = $data['pay_off_rate2'];
                $loan->dsr = !empty($data['dsr']) ? $data['dsr'] : 0;
                $loan->mof = !empty($data['mof']) ? $data['mof'] : 0;
                $loan->holiday_flag = !empty($data['holiday_flag']) ? $data['holiday_flag'] : 0;
                $loan->calculate_loan_after_discount =Request::has('calculate_loan_after_discount') ? Request::input('calculate_loan_after_discount') : 0;
                $loan->days_of_month = $data['days_of_month'];
                $loan->start_date = $data['start_date'];
                $loan->last_schedule_date = $data['start_date'];
                $loan->disburse_date = $data['disburse_date'];
                $loan->repayment_type = $data['repayment_type'];

                $loan->unit_sale_price = $data['unit_sale_price'];
                $loan->discount_promotion = $data['discount_promotion'];
                $loan->discount_other = $data['discount_other'];
                $loan->status_remark = $data['status_remark'];
                $loan->price_after_discount = $data['price_after_discount'];
                $loan->amount_discount_payment_option = $data['amount_discount_payment_option'];
                $loan->clearance_amount = $data['clearance_amount'];
                $loan->clearance_remark = $data['clearance_remark'];
                $loan->final_price = $data['final_price'];
                $loan->diposit_amount = $data['diposit_amount'];
                $loan->deposit_date = $data['deposit_date'];
                $loan->start_payment_date = $data['start_payment_date'];
                $loan->start_payment_no = $data['start_payment_no'];
                $loan->payment_option = $data['payment_option'];
                $loan->hase_down_payment = $data['hase_down_payment'];
                $loan->annual_interest = $data['annual_interest'];
                $loan->discount_payment_option = $data['discount_payment_option'];
                $loan->rouding_result = $data['rouding_result'];
                $loan->down_payment_duration = $data['down_payment_duration'];
                $loan->down_payment_value = $data['down_payment_value'];
                $loan->installment_duration = $data['installment_duration'];
                $loan->down_payment_type = $data['down_payment_type'];
                $loan->contract_deadline = $data['contract_deadline'];
                if($loan->workflow_status == 'send_back'){
                    $loan->workflow_status = 'create';
                }
                if ($data['repayment_type'] == 3 || $data['repayment_type'] == 4 || $data['repayment_type'] == 5) { //semi
                    $loan->balloon = $data['balloon'];
                    $loan->balloon_month = $data['balloon_month'];
                    if (isset($data['custom_flag'])) {
                        if ($data['custom_flag'] == 'on') {
                            $loan->monthly_payment = $data['monthly_payment'];
                            $loan->custom_flag = 1;
                        } else {
                            $loan->custom_flag = 0;
                        }
                    }
                    if (isset($data['balloon_input'])) {
                        if (is_array($data['balloon_input'])) {
                            $loan->balloon_amount_array = implode(",", $data['balloon_input']);
                        }
                    }
                }

                // get annual_yield
                //$holiday = Holiday::All();
                $holiday = [];
                if ($loan->holiday_flag == 1) {
                    $holiday = $loan->holiday;
                }
                $repayment_array = LoanCalculate::monthly_loan_schedule(
                    $loan->repayment_type, $loan->start_date, $loan->loan_duration, $loan->loan_amount, $loan->interest_rate, $loan->balloon, $loan->balloon_month,
                    $loan->monthly_payment, $loan->balloon_amount_array, $loan->custom_flag, $loan->days_of_month, $loan->holiday_flag, $holiday, 0, null, $loan->disburse_date, null, null, null, null, null, $addNew->frequency)[0];

                $downPayment_arrray = LoanCalculate::monthly_loan_schedule_downPayment(1, $loan->start_payment_date,$loan->down_payment_duration, $loan->down_payment, 0, $loan->balloon, $loan->balloon_month,
                    $loan->monthly_payment, $loan->balloon_amount_array, $loan->custom_flag, $loan->days_of_month,
                    $loan->holiday_flag, $holiday, 0, null,null, null, null, null, null, null, $addNew->frequency)[0];
                unset($downPayment_arrray[0]);
                unset($repayment_array[0]);
                if (!empty($repayment_array)) {

                    $total_days = 0;
                    $total_interest = 0;
                    $total_principal = 0;
                    $total_monthly = 0;
                    $total_principal_bal = 0;
                    for ($i = 1; $i <= count($repayment_array); $i++) {
                        $total_days = $total_days + $repayment_array[$i][1];
                        $total_interest += $repayment_array[$i][2];
                        $total_principal += $repayment_array[$i][3];
                        $total_monthly += $repayment_array[$i][4];
                        $total_principal_bal += $repayment_array[$i][5];
                    }
                    // Average Balance
                    $average_bal = $total_principal_bal / $loan->loan_duration;
                    if (($average_bal == 0 && $total_principal_bal == 0) && $total_principal == 0) {
                        Session::flash('message', 'Loan duration (Tenure) couldbe  not allow for one month');
                    } else {
                        $count_r = count(Request::input('repayment_date'));
                        $annual_yield = 100 * ($total_interest / $loan->loan_duration / $average_bal * 12);
                        if ($loan->repayment_type != 8) $loan->annual_yield = $annual_yield;

                        //case manual schedule *****************************
                        if ($loan->repayment_type == 8 && $count_r > 1) {
                            $total_days = 0;
                            $total_interest = 0;
                            $total_principal = 0;
                            $total_monthly = 0;
                            $total_principal_bal = 0;
                            $loan_amount = $loan->loan_amount;

                            for ($m = 0; $m < $count_r - 1; $m++) {
                                $principal = Request::input('repayment_principal')[$m];
                                $schedule_date = Request::input('repayment_date')[$m];
                                $intraday_rate = Request::input('repayment_intraday_rate')[$m];

                                $date_num = Request::input('total_d')[$m + 1];
                                $interest = Request::input('repayment_interest')[$m + 1];

                                $loan_amount = $loan_amount - $principal;

                                $total_days = $total_days + $repayment_array[$i][1];
                                $total_interest += $interest;
                                $total_principal += $principal;
                                $total_monthly += $interest + $principal;
                                $total_principal_bal += $loan_amount;
                            }

                            // Average Balance
                            $average_bal = $total_principal_bal / $loan->loan_duration;
                            // Annual Yield
                            $annual_yield = 100 * ($total_interest / $loan->loan_duration / $average_bal * 12);
                            if ($count_r > 1) $loan->annual_yield = $annual_yield;
                        } /***********************/

                        $loan->frequency = $data['frequency'];
                        if ($loan->save()) {
                            $this->userActivity(Auth::user()->id, $loan_id, 6, 'Update Loan');
                            $count_r = count(Request::input('repayment_date'));
                            // if ($loan->repayment_type != 8 || ($loan->repayment_type == 8 && $count_r > 1)){
                                RepaymentSchedule::where('loan_id', '=', $loan_id)->delete();
                            // }
                            // Insert Down Panyment Schedule 
                            $sell_price = $loan->down_payment +  $loan->loan_amount;
                            $balance_loan = $loan->down_payment;
                            $sche = 0;
                            for ($i = 1; $i <= count($downPayment_arrray); $i++) {
                                $sche++;
                                $beginning = $balance_loan;
                                $balance_loan = $balance_loan - $downPayment_arrray[$i][3];
                                $data = [
                                        'loan_id' => $loan_id, 
                                        'no' => $sche,
                                        'loan_no' => $sche, 
                                        'schedule_date' => $downPayment_arrray[$i][0], 
                                        'date_num' => $downPayment_arrray[$i][1], 
                                        'interest' => $downPayment_arrray[$i][2], 
                                        'principal' => $downPayment_arrray[$i][3], 
                                        'intraday_rate' => $downPayment_arrray[$i][6],
                                        'beginning' => $beginning,
                                        'balance' => $balance_loan,
                                        'type' => 'downpayment',
                                    ];
                                    RepaymentSchedule::insert($data);
                            }
                            // Insert Repayment Schedule
                            // if ($loan->repayment_type == 8) {
                            //     for ($m = 0; $m < $count_r - 1; $m++) {
                            //         $principal = Request::input('repayment_principal')[$m];
                            //         $schedule_date = Request::input('repayment_date')[$m];
                            //         $intraday_rate = Request::input('repayment_intraday_rate')[$m];

                            //         $date_num = Request::input('total_d')[$m + 1];
                            //         $interest = Request::input('repayment_interest')[$m + 1];

                            //         $data = [
                            //                 'loan_id' => $loan_id, 
                            //                 'no' => $m + 1, 
                            //                 'schedule_date' => $schedule_date, 
                            //                 'date_num' => $date_num, 
                            //                 'interest' => $interest, 
                            //                 'principal' => $principal, 
                            //                 'intraday_rate' => $intraday_rate
                            //             ];
                            //         if ($schedule_date && $date_num && $interest && $count_r > 1) RepaymentSchedule::insert($data);
                            //     }
                            //     $loan->rate_type = "Manual";
                            // } else {
                                $balance_loan = $loan->loan_amount;
                                $b =0;
                                for ($i = 1; $i <= count($repayment_array); $i++) {
                                    $sche++;
                                    $b++;
                                    $beginning = $balance_loan;
                                    $balance_loan = $balance_loan - $repayment_array[$i][3];
                                    $data = [
                                            'loan_id' => $loan_id,
                                            'no' => $sche,
                                            'loan_no' => $b,
                                            'schedule_date' => $repayment_array[$i][0], 
                                            'date_num' => $repayment_array[$i][1], 
                                            'interest' => $repayment_array[$i][2], 
                                            'principal' => $repayment_array[$i][3], 
                                            'intraday_rate' => $repayment_array[$i][6],
                                            'beginning' => $beginning,
                                            'balance' => $balance_loan
                                        ];
                                        RepaymentSchedule::insert($data);
                                }
                                if ($loan->repayment_type == 7) {
                                    $loan->rate_type = "Annuity";
                                } else {
                                    $loan->rate_type = "Declining";
                                }
                            // }
                            $loan->save();
                            return redirect()->route('loan_detail', [$loan_id])->with(['msg' => 'Updated successfully.']);
                        }
                    }
                }
            }
        }
        return redirect()->back();
    }

    public function getReschedule($loan_id = 0)
    {
        if ($loan_id > 0) {
            $data['loan'] = Loan::where('id', '=', $loan_id)
                ->with(['client' => function ($query) {
                    $query->select('id', 'client_name');
                }, 'product' => function ($query) {
                    $query->select('id', 'product_name', 'product_price');
                }])
                ->first();
            $data['customer_acc'] = ClientLoanAccounts::select('id', 'account_no', 'client_id', 'branch', 'balance', 'currency')->where('client_id', '=', $data['loan']->client_id)->where('id',$data['loan']->loan_account_id)->whereIn('status', [2, 3, 4, 5, 6, 8, 9])->first();
            $data['productsTypes'] = Product_type::all();
            $old_bal = $data['customer_acc']->balance;
            if (round($old_bal) == 0) {
                $drawdown = DrawdownAccounts::where('client_id', $data['customer_acc']->client_id)->where('account_no', '=', $data['loan']->drawdown_acc)->first();
                $old_bal = abs($drawdown->balance);
            }
            $data['old_bal'] = $old_bal;
            if (!empty($data['loan'])) {
                $branches = CompanyBranch::select('id', 'branch_name')->get();
                $branch_arr = [];
                foreach ($branches as $br) {
                    $branch_arr[$br->id] = $br->branch_name;
                }
                $data['branch_arr'] = $branch_arr;
                $currencies = Currency::select('id', 'symbol')->get();
                $currency_arr = [];
                foreach ($currencies as $cur) {
                    $currency_arr[$cur->id] = $cur->symbol;
                }
                $data['currency_list'] = $currency_arr;
                $data['co_name'] = User::select('users.id', 'name')->join('roles', 'users.role_id', '=', 'roles.id')->whereIn('role', ['co', 'sco'])->get();

                $data['co_id'] = Loan::select('id', 'co')->with(['co_user' => function ($query) {
                    $query->select('id', 'phone');
                }])->first();
                $data['trans'] = TransactionsRequiry::select('id', 'amount')->where('loan_id', '=', $loan_id)
                    ->orderBy('id', 'DESC')->first();
                return $this->view('loans.reschedule', $data);
            }
        }
        return redirect()->back();
    }

    public function postReschedule($loan_id = 0)
    {
        //$client = Client::select('id')->where('id', '=', $client_id)->first();
        $loan = Loan::select('id')->where('id', '=', $loan_id)->first();
        if (empty($loan)) {
            return redirect()->back();
        }
        $data = Request::except(['_token', 'annual_yield', 'holiday_flag', 's_ppi', 's_charge', 's_schedule_date', 's_principal', 's_term',
            'total_d', 'repayment_interest', 'repayment_monthly', 'repayment_date', 'repayment_principal', 'repayment_intraday_rate',
            'repayment_fee', 'balance', 'prin_balance', 'loan_type', 'maintain_fee', 'admin_fee', 's_fee', 'digit',
            'has_first_payment_amount', 'first_payment_amount']);
        $rule = [
            // 'product_id' => 'required',
            'down_payment' => 'required | numeric',
            'submitted_on' => 'required',
            'company_branch_id' => 'required',
            'loan_amount' => 'required | numeric',
            'loan_duration' => 'required',
            'interest_rate' => 'required | numeric',
            'penalty_rate_type' => 'required',
            'penalty_period1' => 'required',
            'penalty_rate1' => 'required',
            'payoff_period1' => 'required',
            'payoff_period2' => 'required',
            'pay_off_rate1' => 'required',
            'pay_off_rate2' => 'required',
            'days_of_month' => 'required',
            'start_date' => 'required',
            'repayment_type' => 'required',
        ];

        $v = Validator::make($data, $rule);
        if ($v->fails()) {
            return redirect()->back()->withErrors($v->errors());
        }
        $addNew = new Loan();
        foreach ($data as $key => $value) {
            if ($key == 's_schedule_date' || $key == 's_date_num' || $key == 's_interest' || $key == 's_intraday_rate' || $key == 's_principal' || $key == 's_term') continue;
            if ($key == 'repayment_date' || $key == 'repayment_principal' || $key == 'total_d' || $key == 'repayment_interest' || $key == 'repayment_intraday_rate' || $key == 's_term' || $key == 'repayment_charge' || $key == 'repayment_monthly' || $key == '') continue;

            if ($key == 'balloon_input') {
                $balloon_value = "";
                if (is_array($value)) {
                    $balloon_value = implode(",", $value);
                }
                $addNew->balloon_amount_array = $balloon_value;
            } elseif ($key == 'custom_flag') {
                if ($value == 'on') {
                    $addNew->$key = 1;
                }
            } else {
                $addNew->$key = $value;
            }
        }
        if (!empty($data['contract_date'])) {
            $addNew->contract_date = date('Y-m-d', strtotime($data['contract_date']));
        } else {
            $addNew->contract_date = date('Y-m-d', strtotime($data['start_date']));
        }
        $addNew->holiday_flag = Request::input('holiday_flag', 0);
        $addNew->last_schedule_date = date('Y-m-d', strtotime(Request::Input('start_date')));
        $addNew->admin_fee = floatval(Request::input('admin_fee'));
        $addNew->maintain_fee = floatval(Request::input('maintain_fee'));
        if (Request::has('loan_type')) {
            //$addNew->loan_type = Product_type::select('id', 'products_type_name')->where('products_type_name', Request::input('loan_type'))->first()->id;
            $addNew->loan_type = Request::input('loan_type'); // we've changed flow. users can defined loan type by select the loan type.
        }
        // get annual_yield
        //$holiday = Holiday::All();
        $holiday = [];
        if ($addNew->holiday_flag == 1) {
            $holiday = $addNew->holiday;
        }
        $admin_fee = floatval(Request::input('admin_fee'));
        $maintain_fee = floatval(Request::input('maintain_fee'));
        $maintain_fee_opt = Request::input('maintain_fee_opt');
        $admin_fee_opt = Request::input('admin_fee_opt');
        $digit = Request::input('digit');
        $repayment_array = LoanCalculate::monthly_loan_schedule($addNew->repayment_type, $addNew->start_date,
            $addNew->loan_duration, $addNew->loan_amount, $addNew->interest_rate, $addNew->balloon, $addNew->balloon_month,
            $addNew->monthly_payment, $addNew->balloon_amount_array, $addNew->custom_flag, $addNew->days_of_month,
            $addNew->holiday_flag, $holiday, 0, null, $addNew->disburse_date, null, null, null, null, null, $addNew->frequency, 0, null, $admin_fee,
            $admin_fee_opt, $maintain_fee, $maintain_fee_opt, null, $digit, $addNew->monthly_amount)[0];

        $addNew->admin_fee = $admin_fee;
        $addNew->maintain_fee = $maintain_fee;
        $getLoanSch = LoanCalculate::getLoanSch($repayment_array, $admin_fee, $admin_fee_opt, $maintain_fee, $maintain_fee_opt, 0,
            $addNew->repayment_type, 0, 0, 0, $addNew->disburse_date, $addNew->start_date, null, $digit);
        $tb_sch = $getLoanSch['tb_sch'];
        $addNew->annual_yield = $getLoanSch['annual_yield'];
        //case manual schedule *****************************
        if ($addNew->repayment_type == 8) {
            $addNew->annual_yield = Request::input('annual_yield');
        }
        // if (!empty($addNew->product_id) && $addNew->product_id > 0) {
            //  $ch_loan = Loan::select('id')->where('product_id', '=', $addNew->product_id)->first();
            //  dd($ch_loan);
            //  if (empty($ch_loan)) {
            if (Auth::check()) {
                $id = Auth::user()->id;
                $addNew->user_id = $id;
            } else {
                return redirect()->route('login');
            }
            // $product = Product::select(['id', 'dealer_id', 'is_loan'])->where('id', '=', $addNew->product_id)->first();
            // if (!empty($product)) {
            //     $addNew->dealer_id = $product->dealer_id;
            //     $product->is_loan = 1; /* product was loan */
            // } else {
            //     return redirect()->back()->withErrors('No product for loan.');
            // }
            $addNew->status = 7; //reschedule (unauthorized)
            if ($addNew->save()) {
                // if ($product->save()) {
                    $this->do_audit($addNew->id, Auth::user()->id, '', 'loans', 0, 'add loan');

                    $this->userActivity($addNew->user_id, $addNew->id, 6, 'Reschedule Loan');
                    // Insert Repayment Schedule
                    if ($addNew->repayment_type == 8) {
                        //$count_r = count(Request::input('repayment_date'));
                        //for ($m = 0; $m < $count_r - 1; $m++) {
                        for ($m = 0; $m <= $addNew->loan_duration; $m++) {
                            $principal = Request::input('repayment_principal')[$m];
                            $schedule_date = Request::input('repayment_date')[$m];
                            $intraday_rate = Request::input('repayment_intraday_rate')[$m];
                            $sch_fee = Request::input('repayment_fee')[$m];
                            $date_num = Request::input('total_d')[$m];
                            $interest = Request::input('repayment_interest')[$m];

                            $data = ['loan_id' => $addNew->id,
                                'no' => $m,
                                'schedule_date' => $schedule_date,
                                'date_num' => $date_num,
                                'interest' => $interest,
                                'principal' => $principal,
                                'fee' => $sch_fee,
                                'intraday_rate' => $intraday_rate];
                            if ($schedule_date && $date_num && $interest) RepaymentSchedule::insert($data);
                        }
                        $addNew->rate_type = "Manual";
                    } else {
                        $a=0;
                        for ($i = 1; $i < count($tb_sch); $i++) {
                            $a++;
                            $data = [
                                    'loan_id' => $addNew->id,
                                    'no' => $a, 
                                    'loan_no' => $a, 
                                    'schedule_date' => $tb_sch[$i]['date'],
                                    'date_num' => $tb_sch[$i]['day'],
                                    'interest' => $tb_sch[$i]['int'],
                                    'principal' => $tb_sch[$i]['prin'],
                                    'fee' => $tb_sch[$i]['fee'],
                                    'intraday_rate' => $tb_sch[$i]['intra_rate']
                                ];
                            RepaymentSchedule::insert($data);
                        }
                        if ($addNew->repayment_type == 7) {
                            $addNew->rate_type = "Annuity";
                        } else {
                            $addNew->rate_type = "Declining";
                        }
                    }
                    $addNew->save();
                    $loan_acc = ClientLoanAccounts::select('id', 'loan_ref', 'activated_on')->where('id', '=', $addNew->loan_account_id)->first();
                    $loan_acc->loan_ref = $addNew->contract_id;
                    $loan_acc->activated_on = $addNew->disburse_date;
                    //$loan_acc->status = 2; // Activate(Std)
                    if ($loan_acc->save()) {
                        return redirect()->route('loan_detail', [$addNew->id]);
                    } else {
                        $addNew->delete();
                        $product->is_loan = 0;
                        Session::flash('message', 'Update client loan account information not successfully');
                    }
                // } else {
                //     $addNew->delete();
                //     $product->is_loan = 0;
                //     Session::flash('message', 'Add new loan information not successfully');
                // }
            }
            //}
        // }
        // return redirect()->back();
    }
    /*
        public function postReschedule($loan_id = 0)
        {
            $loan = Loan::select('id')->where('id', '=', $loan_id)->first();
            if (empty($loan)) {
                return redirect()->back();
            }
            if ($loan_id > 0) {
                $data = Request::except(['_token', 'annual_yield', 'holiday_flag']);
                $rule = [
                    'product_id' => 'required',
                    'loan_type' => 'required',
                    'down_payment' => 'required',
                    'submitted_on' => 'required',
                    'company_branch_id' => 'required',
                    'loan_amount' => 'required',
                    'loan_duration' => 'required',
                    'interest_rate' => 'required',
                    'penalty_rate_type' => 'required',
                    'penalty_period1' => 'required',
                    'penalty_rate1' => 'required',
                    'payoff_period1' => 'required',
                    'payoff_period2' => 'required',
                    'pay_off_rate1' => 'required',
                    'pay_off_rate2' => 'required',
                    'days_of_month' => 'required',
                    'start_date' => 'required',
                    'repayment_type' => 'required',
                ];
                $v = Validator::make($data, $rule);
                if ($v->fails()) {
                    return redirect()->back()->withErrors($v->errors());
                }
                $user_id = Auth::user()->id;
                $branch_id = Auth::user()->branch_id;
                $addNew = New Loan();
                foreach ($data as $key => $value) {
                    if ($key == 'balloon_input') {
                        $balloon_value = "";
                        if (is_array($value)) {
                            $balloon_value = implode(",", $value);
                        }
                        $addNew->balloon_amount_array = $balloon_value;
                    } elseif ($key == 'custom_flag') {
                        if ($value == 'on') {
                            $addNew->$key = 1;
                        }
                    } else {
                        $addNew->$key = $value;
                    }
                }
                $addNew->holiday_flag = !empty($data['holiday_flag']) ? $data['holiday_flag'] : 0;
                $addNew->last_schedule_date = date('Y-m-d', strtotime(Request::Input('start_date')));
                // get annual_yield
                $holiday = [];
                if ($addNew->holiday_flag == 1) {
                    $holiday = $addNew->holiday;
                }
                $repayment_array = LoanCalculate::monthly_loan_schedule($addNew->repayment_type, $addNew->start_date, $addNew->loan_duration, $addNew->loan_amount, $addNew->interest_rate, $addNew->balloon, $addNew->balloon_month,
                    $addNew->monthly_payment, $addNew->balloon_amount_array, $addNew->custom_flag, $addNew->days_of_month, $addNew->holiday_flag, $holiday, 0, null, null, null, null, null, null, null, $addNew->frequency)[0];
                if (!empty($repayment_array)) {
                    $total_days = 0;
                    $total_interest = 0;
                    $total_principal = 0;
                    $total_monthly = 0;
                    $total_principal_bal = 0;

                    for ($i = 1; $i < count($repayment_array); $i++) {
                        $total_days = $total_days + $repayment_array[$i][1];
                        $total_interest += $repayment_array[$i][2];
                        $total_principal += $repayment_array[$i][3];
                        $total_monthly += $repayment_array[$i][4];
                        $total_principal_bal += $repayment_array[$i][5];
                    }
                    // Average Balance
                    $average_bal = $total_principal_bal / $addNew->loan_duration;
                    // Annual Yield
                    $annual_yield = 100 * ($total_interest / $addNew->loan_duration / $average_bal * 12);
                    $addNew->annual_yield = $annual_yield;
                }
                if (!empty($addNew->product_id) && $addNew->product_id > 0) {
                    $ch_loan = Loan::select('id')->where('product_id', '=', $addNew->product_id)->first();
                    if (!empty($ch_loan)) {
                        if (Auth::check()) {
                            $id = Auth::user()->id;
                            $addNew->user_id = $id;
                        } else {
                            return redirect()->route('login');
                        }

                        $product = Product::select('id', 'dealer_id', 'is_loan')->where('id', '=', $addNew->product_id)->first();
                        if (!empty($product)) {
                            $addNew->dealer_id = $product->dealer_id;
                            $product->is_loan = 1; /* product was loan */
    /*                  } else {
                          return redirect()->back()->withErrors('No product for loan.');
                      }

                      $addNew->status = 7; // Rescheduled(unauthorized)
                      if ($addNew->save()) {
                          if ($product->save()) {
                              $this->userActivity($addNew->user_id, $addNew->id, 6, 'Rescheduled Loan');
                              // Insert Repayment Schedule
                              for ($i = 1; $i < count($repayment_array); $i++) {
                                  $data = ['loan_id' => $addNew->id, 'schedule_date' => $repayment_array[$i][0], 'date_num' => $repayment_array[$i][1], 'interest' => $repayment_array[$i][2], 'principal' => $repayment_array[$i][3], 'intraday_rate' => $repayment_array[$i][6]];
                                  RepaymentSchedule::insert($data);
                              }
                              $old_loan_acc = Loan::where('id', $loan_id)->with('client_loan_account')->first()->client_loan_account;
                              $loan_acc = ClientLoanAccounts::where('id', '=', $addNew->loan_account_id)->first();
                              // COA
                              $journal_arr = [];
  /*
                              $desc = "";
                              $desc = "Reschedule - ".$old_loan_acc->loan_ref." To "$loan_acc->loan_ref;
                              array_push($journal_arr, [$loan_acc->coa_id, $old_loan_acc->balance, $desc
                                  $old_loan_acc->coa_id, $old_loan_acc->balance, $desc,
                                  $desc);
                              $record_date = date('Y-m-d H:i:s');
                              record_journal_no_trans($loan_acc, $record_date, $journal_arr, $branch_id, $user_id, 1);

                              // air
                              $journal_arr = [];
                              $air_amount = 0;
                              $air_amount = JournalDetail::select()
                              array_push($journal_arr, [$loan_acc->coa_id, $old_loan_acc->balance, $desc
                                  $old_loan_acc->coa_id, $old_loan_acc->balance, $desc,
                                  $desc);
                              $record_date = date('Y-m-d H:i:s');
                              record_journal_no_trans($loan_acc, $record_date, $journal_arr, $branch_id, $user_id, 1);
  */
    /*                            $loan_acc->loan_ref = $addNew->contract_id;
                                if ($loan_acc->save()) {
                                    $this->do_audit($addNew->id, Auth::user()->id, '', 'reschedule_loan', 0, 'reschedule loan');
                                }

                                return redirect()->route('loan_detail', [$addNew->id]);
                            } else {
                                $addNew->delete();
                                Session::flash('message', 'Add new loan information not successfully');
                            }
                        }
                    }
                }
            }
            return redirect()->back();
        }
    */

    public function getWriteOff($loan_id = 0)
    {
        if ($loan_id > 0) {
            $lc = Loan::where('id', '=', $loan_id)
                ->where('status', '=', 3)
                ->count();
            if ($lc > 0) {
                $outstanding = TransactionsRequiry::select('balance')
                    ->where('loan_id', '=', $loan_id)
                    ->orderBy('id', 'DESC')
                    ->first();
                return $this->view('loans.writeoff', ['loan_id' => $loan_id, 'outstanding' => $outstanding]);
            }
        }
        return redirect()->back();
    }

    public function postWriteOff($loan_id = NULL)
    {
        $data = Request::except(['_token']);
        $rules = ['write_off_on' => 'required|date',
            'amount' => 'required|numeric'
        ];
        $attribs = ['write_off_on' => 'Write Off Date',
            'amount' => 'Amount'
        ];
        $validator = Validator::make($data, $rules);
        $validator->setAttributeNames($attribs);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        } else {
            $loan = Loan::where('id', '=', $loan_id)->first();
            if (!empty($loan) && ($loan->status == 3 || $loan->status == 8)) {

                $static = config('static_data');
                if (CLASS_NEW_PRAKAS == 0) {  // old prakas
                    $lc_status = $static['client_loan_account_status'];
                } else {
                    $lc_status = $static['client_loan_account_status_new'];
                }

                $last_transaction = TransactionsRequiry::select('id', 'balance')->where('loan_id', '=', $loan_id)
                    ->orderBy('id', 'DESC')->first();
                if ($last_transaction->is_audit != 1) {
                    Session::flash('danger', 'Can not close this loan, the last transaction need to be auditted!');
                    return redirect()->back();
                }
                $wo = new LoanWriteOff;
                $wo->loan_id = $loan_id;
                $date = date('Y-m-d', strtotime(Request::input('write_off_on')));
                $wo->write_off_date = $date;
                $wo->amount = Request::input('amount');
                $wo->note = Request::input('note');
                $wo->user_id = Auth::user()->id;
                if ($wo->save()) {
                    // Use direct DB update to ensure all fields are saved
                    \DB::table('loans')->where('id', $loan_id)->update([
                        'status' => 5,
                        'settlement_date' => $date,
                        'cut_stock_date' => $date,
                    ]);
                    $loan = Loan::find($loan_id); // reload
                    $record_date = date('Y-m-d H:i:s');
                    $user_id = Auth::user()->id;
                    $branch_id = Auth::user()->branch_id;
                    if ($loan) {
                        $this->userActivity($wo->user_id, $loan_id, 6, 'Write Off Loan');
                        $transaction = new TransactionsRequiry();
                        $transaction->loan_id = $loan_id;
                        $transaction->trans_date = $wo->write_off_date;
                        $transaction->trans_type = "Write-off";
                        $transaction->amount = $wo->amount;
                        $transaction->description = $loan->note;
                        $transaction->balance = $last_transaction->balance - $wo->amount;
                        $transaction->user_id = $wo->user_id;
                        $transaction->write_off_id = $wo->id;
                        if ($transaction->save()) {
                            $this->do_audit($transaction->id, Auth::user()->id, '', 'transactions_requiry', 0, 'writeoff');
                            // Delete Provision
                            $loan_account = Loan::with('client_loan_account')->where('id', $loan_id)->first()->client_loan_account;
                            $desc_str = "Write-off " . $loan_account->loan_ref . "(" . $lc_status[$loan_account->status] . " -> " . $lc_status[$loan_account->status + 1] . ")";
                            $journal_arr = [];
                            $debit_acc = CoaCategory::select('*')->where('name', 'LIKE', '%(Less) Specific Loan Loses%')->where('name', 'LIKE', '%' . $loan_account->acc_key . '%')->where('type', 6)->where('currency', $loan_account->currency)->first();
                            $credit_acc = CoaCategory::select('*')->where('id', $loan_account->coa_id)->first();
                            array_push($journal_arr, [$debit_acc->id, $loan_account->balance, $desc_str, $credit_acc->id, $loan_account->balance, $desc_str, $desc_str]);
                            record_journal_no_trans($loan_account, $record_date, $journal_arr, $branch_id, $user_id);

                            // AIR
                            $journal_arr = [];
                            $debit_acc = CoaCategory::select('*')->where('id', $loan_account->sus_id)->first();
                            $credit_acc = CoaCategory::select('*')->where('id', $loan_account->air_id)->first();
                            $amount = $credit_acc->b_debit - $credit_acc->b_credit;
                            array_push($journal_arr, [$debit_acc->id, $amount, $desc_str, $credit_acc->id, $amount, $desc_str, $desc_str]);
                            record_journal_no_trans($loan_account, $record_date, $journal_arr, $branch_id, $user_id);

                            return redirect()->route('loan_detail', [$loan_id]);
                        } else {
                            $wl = LoanWriteOff::where('id', '=', $wo->id)->first();
                            if (!empty($wl)) {
                                $wl->delete();
                            }
                            $transaction->delete();
                            return redirect()->back();
                        }
                    } else {
                        $wl = LoanWriteOff::where('id', '=', $wo->id)->first();
                        if (!empty($wl)) {
                            $wl->delete();
                        }
                        return redirect()->back();
                    }
                }
            }
            return redirect()->route('loan_detail', [$loan_id]);
        }
    }

    public function getWriteOffPay($loan_id = 0)
    {
        if ($loan_id > 0) {
            $lc = Loan::where('id', '=', $loan_id)
                ->where('status', '=', CLOSE_L_STATUS - 1)
                ->first();
            if (count($lc) > 0) {

                $loan_writeoff = Loan::select('id', 'client_id', 'contract_id', 'loan_account_id', 'start_date', 'loan_amount', 'interest_rate', 'status')
                    ->where('status', CLOSE_L_STATUS - 1) // Write-off = CLOSE_L_STATUS-1
                    ->where('id', $loan_id)
                    ->with(['writeoff', 'client_loan_account'])
                    ->first();
                $coa_charged_off = CoaCategory::select('id', 'name', 'type')->where('name', '=', WO_CREDIT_COA_NAME)->where('type', 6)->get();
                $coa_wo_arr = [];
                foreach ($coa_charged_off as $coa) {
                    $coa_wo_arr[] = $coa->id;
                }
                $jd_wo_all = JournalDetail::whereIn('coa_id', $coa_wo_arr)->get();

                $dd_id = $loan_writeoff->client->drawdowns[0]->coa_id;
                $data['dd_balance'] = -get_journal_bal($dd_id)['balance'];
                $data['wo_prev_paid'] = -get_balance_obj($jd_wo_all, 'reference', $loan_writeoff->contract_id);
                $data['loan_writeoff'] = $loan_writeoff;
                $data['loan_id'] = $loan_id;

                return $this->view('loans.writeoff_pay', $data);
            }
        }
        return redirect()->back();
    }

    public function postWriteOffPay($loan_id = 0)
    {
        $data = Request::except('_token');
        $rules = ['repay_date' => 'required|date',
            'repay_amount' => 'required|numeric'
        ];
        $attribs = ['repay_date' => 'Repayment Date is required.',
            'repay_amount' => 'Amount is not inputted.'
        ];
        $validator = Validator::make($data, $rules);
        $validator->setAttributeNames($attribs);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        } else {
            if (round($data['dd_balance'], 2) < round($data['repay_amount'], 2) || round($data['dd_balance'], 2) < 0) {
                Session::flash('danger', 'Balance in Drawdown Account is not sufficient! Current Drawdown Account balance is ' . $data['dd_balance']);
                return redirect()->back();
            } else {
                // WO Info
                $loan_writeoff = Loan::select('id', 'client_id', 'contract_id', 'loan_account_id', 'start_date', 'loan_amount', 'interest_rate', 'status')
                    ->where('status', CLOSE_L_STATUS - 1) // Write-off = CLOSE_L_STATUS-1
                    ->where('id', $loan_id)
                    ->with(['writeoff', 'client_loan_account'])
                    ->first();
                if (count($loan_writeoff) > 0) {
                    // COA of charged off
                    $coa_charged_off_id = CoaCategory::select('id', 'name', 'type')->where('name', '=', WO_CREDIT_COA_NAME)->where('type', 6)->where('currency', $loan_writeoff->client_loan_account->currency)->first()->id;
                    // Add Journal
                    $journal_arr = [];
                    $desc = "WriteOff Payment : " . $loan_writeoff->contract_id . "-" . $loan_writeoff->client_loan_account->account_name . "-" . $data['note'];
                    // Journal
                    array_push($journal_arr, [$loan_writeoff->client->drawdowns[0]->coa_id, $data['repay_amount'], $desc,
                        $coa_charged_off_id, $data['repay_amount'], $desc,
                        $desc]);

                    $this->userActivity(Auth::user()->id, $loan_id, 6, 'WriteOff Payment');
                    // record journal
                    record_journal($loan_writeoff, date('Y-m-d', strtotime($data['repay_date'])), $type = "WriteOff Payment", $data, $journal_arr, Auth::user()->branch_id, Auth::user()->id);
                    // update drawdown account 
                    $drawdown_acc = DrawdownAccounts::where('coa_id', $loan_writeoff->client->drawdowns[0]->coa_id)->first();
                    $drawdown_acc->balance = floatval($data['dd_balance']) - floatval($data['repay_amount']);
                    $drawdown_acc->save();
                    return redirect()->back();
                }
            }
        }
        return redirect()->back();
    }

    public function getWriteOffDetail($loan_id = 0)
    {
        if ($loan_id > 0) {
            $lc = Loan::where('id', '=', $loan_id)
                ->where('status', '=', CLOSE_L_STATUS - 1)
                ->first();
            if (count($lc) > 0) {

                $loan_writeoff = Loan::select('id', 'client_id', 'contract_id', 'loan_account_id', 'start_date', 'loan_amount', 'interest_rate', 'status')
                    ->where('status', CLOSE_L_STATUS - 1) // Write-off = CLOSE_L_STATUS-1
                    ->where('id', $loan_id)
                    ->with(['writeoff', 'client_loan_account'])
                    ->first();
                $coa_charged_off = CoaCategory::select('id', 'name', 'type')->where('name', '=', WO_CREDIT_COA_NAME)->where('type', 6)->get();
                $coa_wo_arr = [];
                foreach ($coa_charged_off as $coa) {
                    $coa_wo_arr[] = $coa->id;
                }
                $jd_wo_all = JournalDetail::whereIn('coa_id', $coa_wo_arr)->get();
                $jd_wo_all_paid = $jd_wo_all->where('reference', $loan_writeoff->contract_id);

                $dd_id = $loan_writeoff->client->drawdowns[0]->coa_id;
                $data['dd_balance'] = -get_journal_bal($dd_id)['balance'];
                $data['wo_prev_paid'] = -get_balance_obj($jd_wo_all, 'reference', $loan_writeoff->contract_id);
                $data['loan_writeoff'] = $loan_writeoff;
                $data['jd_wo_all_paid'] = $jd_wo_all_paid;
                $data['loan_id'] = $loan_id;
                $data['branch'] = CompanyBranch::select('id', 'branch_code', 'branch_name')->where('status', '=', 1)->get();
                $data['currency_list'] = Currency::select('id', 'name', 'code')->get();

                return $this->view('loans.wo_detail', $data);
            }
        }
        return redirect()->back();
    }


    public function getRestructure($loan_id = null){
        if ($loan_id > 0) {
            $lc = Loan::with('client_loan_account')->where('id', '=', $loan_id)->whereIn('status', [3, 8])->first();
            $dpDateClone = !is_null(Request::input('dpDateClone')) ? Request::input('dpDateClone') : date('Y-m-d');
            $penalty_arr = LoanCalculate::getTotalPenalty($lc, $dpDateClone);
            $penalty = $penalty_arr[4];
            $fee_bal = $penalty_arr[9];
            $other_fee = $penalty_arr[10];
            $data['loan'] = Loan::where('id', '=', $loan_id)
                ->with(['client' => function ($query) {
                    $query->select('id', 'client_name','cus_acc');
                }])
                ->first();
            $unit_id = $data['loan']->unit_id;
            $project_id = $data['loan']->project_id;
            $unit_type_id = $data['loan']->unit_type_id;
            $projects = Project::select('id','dealer','short_code')->where('id',$project_id)->get();
            $unit_types = UnitType::where('id',$unit_type_id)->get();
            if (!empty($data['loan'])) {
                $B0 = new Loan();
                //$B0 = $this->getUserByBranch($B0, 'company_branch_id', $query_arr);
                $loan_repay = $B0->select(['loans.*',
                                        'clients.client_name',
                                        'client_loan_accounts.account_no'
                                    ])
                    ->join('client_loan_accounts', 'client_loan_accounts.id', '=', 'loans.loan_account_id')
                    ->join('clients', 'clients.id', '=', 'loans.client_id');
                $loan_repay = $loan_repay->with([
                    'payment' => function ($q) {
                        $q->select('id',
                                    'loan_id',
                                    'paid_principal',
                                    'paid_interest',
                                    'paid_fee',
                                    'paid_other_fee',
                                    'penalty_amount',
                                    'repayment_owed',
                                    'repayment_date',
                                    'payment_month',
                                    'status',
                                    'condition_id'
                                )->orderBy('id', 'asc');
                    },
                    'client_loan_account' => function ($q) {
                            $q->select('id',
                                    'account_name',
                                    'account_no',
                                    'air_id',
                                    'coa_id',
                                    'status');
                    },
                    'schedule' => function ($q) {
                            $q->select('loan_id',
                                'schedule_date',
                                'date_num',
                                'interest',
                                'principal',
                                'fee',
                                'other_fee',
                                'intraday_rate',
                                'no',
                                'type',
                                'loan_no'
                            );
                    },
                    'transaction' => function ($q) {
                        $q->where('trans_type', 'LIKE', '%Loan Repayment%');
                    },
                    'penalty_record' => function ($q) {
                        $q->select('id', 'loan_id', 'record_date', 'owed_penalty')->orderBy('id', 'DESC');
                    }
                ])->whereIn('loans.status', [3, 8]);

                $loan_repay = $loan_repay->where('loans.id', $loan_id)->first();
                $get_restructure_balance = get_restructure_balance($loan_repay);

                $sum_down_payment = $get_restructure_balance['downpayment'];
                $sum_principal = $get_restructure_balance['balance_loan'];
                $sch_repay_arr = get_auto_repay_array($loan_repay, $dpDateClone);
                $start_payment_date = $dpDateClone;
                $data['branch_name'] = CompanyBranch::select('id', 'branch_name')->where('id',$data['loan']->company_branch_id)->where('status',1)->get();

                $data['co_name'] = User::select('users.id', 'name')->join('roles', 'users.role_id', '=', 'roles.id')->whereIn('role', ['sco', 'co'])->get();
                $data['co_id'] = Loan::select('id', 'co')->where('id', '=', $loan_id)->with(['co_user' => function ($query) {
                    $query->select('id', 'phone');
                }])->first();
                $data['loan_type'] = Loan::select('id', 'loan_type')->where('id', '=', $loan_id)->with(['product_type' => function ($q) {
                    $q->select('id', 'products_type_name');
                }])->first();
                $data['units'] = Unit::select('id','code','price')->where('id',$unit_id)->get();
                $data['projects'] = $projects;
                $data['unit_types'] = $unit_types;
                $data['sum_down_payment'] = $sum_down_payment;
                $data['start_payment_date'] = $start_payment_date;
                $data['sum_principal'] = $sum_principal;
                return $this->view('loans.restructure', ['loan_id' => $loan_id, 'loan' => $lc,'dpDateClone' => $dpDateClone, 'fee_bal' => $fee_bal,'penalty' => $penalty, 'other_fee' => $other_fee,'sch_repay_arr' => $sch_repay_arr,'loan_repay'=>$loan_repay],$data);
            }
        }
        return redirect()->back();
    }

    public function postRestructure($loan_id = NULL){
            $data = Request::except('_token');
        $rules = ['restructure_date' => 'required|date',
            'loan_amount' => 'required|numeric'
        ];
        $attribs = ['restructure_date' => 'Restructure Date',
            'loan_amount' => 'Amount'
        ];
        $validator = Validator::make($data, $rules);
        $validator->setAttributeNames($attribs);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        } else {
            $loan = Loan::select(['id', 'settlement_date', 'client_id', 'status', 'loan_account_id', 'contract_id','drawdown_acc'])
                        ->with('client_loan_account')
                        ->where('id', '=', $loan_id)->first();
            $drawdown_acc = DrawdownAccounts::where('client_id', '=', $loan->client_id)->where('account_no', '=', $loan->drawdown_acc)->first();

            $drawdown_principal_amount = Request::input('drawdown_principal_amount');
            if (round($drawdown_acc->balance, 2) < round($drawdown_principal_amount, 2)) {
                $balance = $drawdown_acc->currency_tbl->symbol . number_format($drawdown_acc->balance, 2, '.', ',');
                Session::flash('danger', 'Balance in Drawdown Account is not sufficient! Current Drawdown Account balance is ' . $balance);
                return redirect()->back();
            }
            $coa_id = $loan->client_loan_account->coa_id;

            $B0 = new Loan();
            //$B0 = $this->getUserByBranch($B0, 'company_branch_id', $query_arr);
            $loan_repay = $B0->select(['loans.*', 'clients.client_name', 'client_loan_accounts.account_no'])
                ->join('client_loan_accounts', 'client_loan_accounts.id', '=', 'loans.loan_account_id')
                ->join('clients', 'clients.id', '=', 'loans.client_id');
            $loan_repay = $loan_repay->with([
                'payment' => function ($q) {
                    $q->select('id',
                                'loan_id',
                                'paid_principal',
                                'paid_interest',
                                'paid_fee',
                                'paid_other_fee',
                                'penalty_amount',
                                'repayment_owed',
                                'repayment_date',
                                'payment_month',
                                'status',
                                'condition_id')->orderBy('id', 'asc');
                },
                'client_loan_account' => function ($q) {
                    $q->select('id',
                                'account_name',
                                'account_no',
                                'air_id',
                                'coa_id',
                                'status');
                },
                'schedule' => function ($q) {
                    $q->select('loan_id',
                                'schedule_date',
                                'date_num',
                                'interest',
                                'principal',
                                'fee',
                                'other_fee',
                                'intraday_rate',
                                'no',
                                'type',
                                'loan_no');
                },
                'transaction' => function ($q) {
                    $q->where('trans_type', 'LIKE', '%Loan Repayment%');
                },
                'penalty_record' => function ($q) {
                    $q->select('id', 'loan_id', 'record_date', 'owed_penalty')->orderBy('id', 'DESC');
                }
            ])->whereIn('loans.status', [3, 8]);
            $loan_repay = $loan_repay->where('loans.id', $loan_id)->first();

            $get_restructure_balance = get_restructure_balance($loan_repay);
            $sum_down_payment = $get_restructure_balance['downpayment'];
            $sum_principal = $get_restructure_balance['balance_loan'];
            $last_no_pay = $get_restructure_balance['last_no_pay'];
            $loan_no = $get_restructure_balance['loan_no'];
            $loan_no_type = $get_restructure_balance['loan_no_type'];
            $loan_paid_principal = $get_restructure_balance['loan_paid_principal'];
            if(((round(Request::input('re_down_payment'),2)) != round($sum_down_payment,2)) || ((round(Request::input('prin_amount'),2)) != round($sum_principal,2))){
                Session::flash('danger', 'Please Try again!');
                return redirect()->back();
            }
              

            if (!empty($loan) && ($loan->status == 3 || $loan->status == 8)) {
                $last_transaction = TransactionsRequiry::select('id', 'balance', 'is_audit')->where('loan_id', '=', $loan_id)->orderBy('id', 'DESC')->first();
                if ($last_transaction->is_audit != 1) {
                    Session::flash('danger', 'Can not close this loan, the last transaction need to be auditted!');
                    return redirect()->back();
                }
                $loan_amount = Request::input('loan_amount');
                $loan_amount_to_update = $loan_paid_principal + $loan_amount;
                $loanUpdate = Loan::find($loan_id);
                $loanBeforUpdate = $loanUpdate;

                $repayment_type = Request::input('repayment_type');
                $loan_duration = Request::input('loan_duration');
                $interest_rate = Request::input('interest_rate');
                $balloon = Request::input('balloon');
                $balloon_month = Request::input('balloon_month');
                $monthly_payment = Request::input('monthly_payment');
                $down_payment_duration = Request::input('down_payment_duration');
                $balloon_amount_array = Request::input('balloon_amount_array');
                $custom_flag = Request::input('custom_flag');
                $days_of_month = Request::input('days_of_month');
                $holiday_flag = Request::input('holiday_flag');
                $frequency = Request::input('frequency');
                $monthly_amount = Request::input('monthly_amount');
                $drawdown_principal_amount = Request::input('drawdown_principal_amount');

                $admin_fee = Request::input('admin_fee');
                $maintain_fee = Request::input('maintain_fee');
                $maintain_fee_opt = Request::input('maintain_fee_opt');
                $admin_fee_opt = Request::input('admin_fee_opt');
                $other_fee = Request::input('other_fee');

                $loanUpdate->annual_interest= floatval(Request::input('interest_rate'))*12; // Improve reschedule 03-05-2023
                $loanUpdate->status = 7; //reschedule (unauthorized)
                
                // if($loanUpdate->repayment_type == 7) {
                //     $loanUpdate->rate_type = "Annuity";
                // }else{
                //     $loanUpdate->rate_type = "Declining";
                // }

                $downPayment_arrray = [];

                $start_payment_date = Request::input('start_payment_date');
                $start_date = Request::input('start_date');
                $interest_rate = Request::input('interest_rate');
                $disburse_date = Request::input('disburse_date');

                $admin_fee = floatval(Request::input('admin_fee'));
                $maintain_fee = floatval(Request::input('maintain_fee'));
                $maintain_fee_opt = Request::input('maintain_fee_opt');
                $admin_fee_opt = Request::input('admin_fee_opt');
                $other_fee = floatval(Request::input('other_fee'));
                $digit = Request::input('digit');

                $re_down_payment = Request::input('down_payment');
                $re_down_payment = isset($re_down_payment)?$re_down_payment:0;
                $restructure_date = Request::input('restructure_date');
                $start_payment_date = Request::input('start_payment_date');

                $repayment_array = LoanCalculate::monthly_loan_schedule($repayment_type,$start_date,$loan_duration, $loan_amount, $interest_rate, $balloon, $balloon_month,$monthly_payment, $balloon_amount_array, $custom_flag, $days_of_month,
                                                                        $holiday_flag, $holiday, 0, null, $disburse_date, null, null, null, null, null, $frequency, 0, null, $admin_fee,$admin_fee_opt, $maintain_fee, $maintain_fee_opt, null, $digit, $monthly_amount)[0];

                $getLoanSch = LoanCalculate::getLoanSch($repayment_array, $admin_fee, $admin_fee_opt, $maintain_fee, $maintain_fee_opt, $other_fee,$repayment_type, 0, 0, 0, $disburse_date, $start_date, null, $digit);

                // ===== Re Schedule ===========
                $restructure_data = Request::except(['_token']);
                $loRestructure = new LoanRestructure;
                $loRestructure->loan_id = $loan_id;
                $date = date('Y-m-d', strtotime(Request::input('restructure_date')));
                $loRestructure->restructure_date = $date;
                $loRestructure->principal = Request::input('prin_amount');
                // $loRestructure->down_payment = Request::input('re_down_payment');
                $loRestructure->interest                  = Request::input('air_amount');
                $loRestructure->fee                       = Request::input('fee_amount');
                $loRestructure->penalty                   = Request::input('penalty');
                $loRestructure->amount                    = Request::input('sell_price');
                $loRestructure->down_payment              = $re_down_payment;
                $loRestructure->drawdown_principal_amount = Request::input('drawdown_principal_amount');
                $loRestructure->old_loan_amount           = $loan_amount_to_update;
                $loRestructure->loan_amount               = Request::input('loan_amount') + Request::input('drawdown_principal_amount');
                $loRestructure->note                      = Request::input('note');

                $loRestructure->loan_repayment_type        = Request::input('repayment_type');
                $loRestructure->loan_installment_duration  = Request::input('loan_duration');
                $loRestructure->loan_interest_rate         = Request::input('interest_rate');
                $loRestructure->loan_balloon               = Request::input('balloon');
                $loRestructure->loan_balloon_month         = Request::input('balloon_month');
                $loRestructure->loan_monthly_payment       = Request::input('monthly_payment');
                $loRestructure->loan_down_payment_duration = Request::input('down_payment_duration');
                $loRestructure->loan_balloon_amount_array  = Request::input('balloon_amount_array');
                $loRestructure->loan_custom_flag           = Request::input('custom_flag');
                $loRestructure->loan_days_of_month         = Request::input('days_of_month');
                $loRestructure->loan_holiday_flag          = Request::input('holiday_flag');
                $loRestructure->loan_frequency             = Request::input('frequency');
                $loRestructure->loan_monthly_amount        = Request::input('monthly_amount');
                $loRestructure->loan_admin_fee             = Request::input('admin_fee');
                $loRestructure->loan_maintain_fee          = Request::input('maintain_fee');
                $loRestructure->loan_maintain_fee_opt      = Request::input('maintain_fee_opt');
                $loRestructure->loan_admin_fee_opt         = Request::input('admin_fee_opt');
                $loRestructure->loan_other_fee             = Request::input('other_fee');
                if($loRestructure->loan_repayment_type == 7){
                    $loRestructure->loan_rate_type = "Annuity";
                }else{
                    $loRestructure->loan_rate_type = "Declining";
                }
                $loRestructure->restructure_data = json_encode($restructure_data);
                $loRestructure->user_id = Auth::user()->id;
                // ====== End Re Schedule ============
                //  If Save Re Schedule
                if($loRestructure->save()){
                // if(1 ==  1){
                    $this->do_audit($loanUpdate->id, Auth::user()->id, '', 'loans', 0, 'Reschedule loan');
                    $this->userActivity(is_null($loanUpdate->user_id)?Auth::user()->id:0, $loanUpdate->id, 6, 'Reschedule Loan');
                    // ===  Check Update Laon ======
                    $restructure_id = $loRestructure->id;
                    $loanUpdate->restructure_id = $restructure_id;
                    if($loanUpdate->save()){
                    // if(1 == 1){
                                // $scheduleToDelete = RepaymentSchedule::select('id')->where('loan_id',$loan_id)->where('no','>',$loanUpdate->loan_duration)->get();
                                // //  Log File
                                // Log::useDailyFiles(storage_path().'/logs/restructure/loan_id-'.$loan_id.'-'.Auth::user()->username.'-'.date('Y-m-d H:i:s').'.log');
                                // $dataToLog = [
                                //             'info'  => [
                                //                     'username'  => Auth::user()->username,
                                //                     'datetime'  => date('Y-m-d H:i:s'),
                                //                         ],
                                //             'beforupdate'   => $loanBeforUpdate,
                                //             'restructure'   => $scheduleToDelete,
                                //             ];

                                // Log::info(json_encode($dataToLog));
                                // //  End Log File
                                // if($scheduleToDelete){
                                //     RepaymentSchedule::whereIn('id',array_column($scheduleToDelete->toArray(),'id'))->where('loan_id',$loan_id)->delete();
                                // }

                        $tb_sch = $getLoanSch['tb_sch'];
                        $a = $last_no_pay;
                        //  ==== Has Downpayment ============
                        if($sum_down_payment > 0){
                            $downPayment_arrray = LoanCalculate::monthly_loan_schedule_downPayment(1,$restructure_date,$down_payment_duration, $re_down_payment, 0, $balloon, $balloon_month,$monthly_payment, $balloon_amount_array, $custom_flag, $days_of_month,
                                                $holiday_flag, $holiday, 0, null, $start_payment_date, null, null, null, null, null, $frequency, 0, null, $admin_fee,$admin_fee_opt, $maintain_fee, $maintain_fee_opt, null, $digit, $monthly_amount)[0];

                            $getDownpayment = LoanCalculate::getDownpayment($downPayment_arrray, $admin_fee, $admin_fee_opt, $maintain_fee, $maintain_fee_opt, $other_fee,$repayment_type, 0, 0, 0, $disburse_date, $start_date, null, $digit);

                            if($loan_no_type == 'downpayment'){
                                $loan_no_dw = $loan_no;
                            }else{
                                $loan_no_dw = 1;
                            }
                            $tb_sch_down = $getDownpayment['tb_sch'];
                            $balance_loan = $re_down_payment;
                            for ($i = 1; $i <= count($tb_sch_down); $i++) {
                                $beginning = $balance_loan;
                                $balance_loan = $balance_loan - $tb_sch_down[$i]['prin'];
                                $data_schedule = [
                                    'loan_id' => $loan_id,
                                    'restructure_id' => $restructure_id,
                                    'no' => $a,
                                    'loan_no' => $loan_no_dw, 
                                    'schedule_date' => $tb_sch_down[$i]['date'],
                                    'date_num' => $tb_sch_down[$i]['day'], 
                                    'beginning' => $beginning,
                                    'interest' => $tb_sch_down[$i]['int'],
                                    'principal' => $tb_sch_down[$i]['prin'], 
                                    'fee' => isset($tb_sch_down[$i]['fee'])?$tb_sch_down[$i]['fee']:0,
                                    'other_fee' => $tb_sch_down[$i]['other_fee'], 
                                    'intraday_rate' => $tb_sch_down[$i]['intra_rate'],
                                    'balance' => $balance_loan,
                                    'type' => 'downpayment',
                                    'is_restructure'=>1
                                ];
                                RescheduleRepaymentTemp::updateOrCreate(['restructure_id' => $restructure_id, 'loan_id' => $loan_id, 'no' => $a],$data_schedule);
                                $a++;
                                $loan_no_dw++;
                            }
                        }
                        
                        //  ==== End Has Downpayment ============
                        // ==== Loan Sch 
                        if($loan_no_type == 'loan'){
                            $loan_no_l = $loan_no;
                            $a;
                        }else{
                            $loan_no_l = 1;
                        }
                        if($drawdown_principal_amount > 0){
                             $data_schedule = [
                                'loan_id' => $loan_id,
                                'restructure_id' => $restructure_id,
                                'no' => $a,
                                'loan_no' => $loan_no_l,
                                'schedule_date' => $restructure_date,
                                'date_num' => 0,
                                'beginning' => $loan_amount + $drawdown_principal_amount,
                                'interest' => 0,
                                'principal' => $drawdown_principal_amount,
                                'fee' => 0,
                                'other_fee' => 0,
                                'status' => 1,
                                'intraday_rate' => 0,
                                'balance' => $loan_amount,
                                'is_restructure'=>1
                            ];
                            RescheduleRepaymentTemp::updateOrCreate(['restructure_id' => $restructure_id, 'loan_id' => $loan_id, 'no' => $a],$data_schedule);
                            $month_idx = $a;
                            $loRestructure->month_idx = $month_idx;
                            $loRestructure->save();
                            $loan_no_l ++;
                            $a++;
                        }
                        $balance_loan = $loan_amount;

                        for ($i = 1; $i <= count($tb_sch); $i++) {
                            $beginning = $balance_loan;
                            $balance_loan = $balance_loan - $tb_sch[$i]['prin'];
                            $data_schedule = [
                                'loan_id' => $loan_id,
                                'restructure_id' => $restructure_id,
                                'no' => $a,
                                'loan_no' => $loan_no_l,
                                'schedule_date' => $tb_sch[$i]['date'],
                                'date_num' => $tb_sch[$i]['day'],
                                'beginning' => $beginning,
                                'interest' => $tb_sch[$i]['int'],
                                'principal' => $tb_sch[$i]['prin'],
                                'fee' => $tb_sch[$i]['fee'],
                                'other_fee' => $tb_sch[$i]['other_fee'],
                                'intraday_rate' => $tb_sch[$i]['intra_rate'],
                                'balance' => $balance_loan,
                                'is_restructure'=>1
                            ];
                            RescheduleRepaymentTemp::updateOrCreate(['restructure_id' => $restructure_id, 'loan_id' => $loan_id, 'no' => $a],$data_schedule);
                            $a++;
                            $loan_no_l++;
                        }

                        // ====== End Loan Sch
                        // journal
                        $coa_pnt = CoaCategory::select('id', 'account_code', 'name')->where('type', 6)->where('currency', $loan->client_loan_account->currency)->where('name', 'Inc-Fine and Penalty on Repayment of Loans and leasing')->first();
                        $coa_fee = CoaCategory::select('id', 'account_code', 'name')->where('type', 6)->where('currency', $loan->client_loan_account->currency)->where('name', 'Inc-Other Fees and Commissions from Loans and Leasing')->first();

                        $desc = "Restructure Loan : " . Request::input('note');
                        $journal_arr = [];


                        // principal
                        array_push($journal_arr, [ 
                                                $drawdown_acc->coa_id,
                                                $loRestructure->principal,
                                                $loRestructure->note,
                                                $coa_id,
                                                $loRestructure->principal,
                                                $loRestructure->note,
                                                $desc
                                            ]);
                        // interest
                        if ($loRestructure->interest > 0)
                            array_push($journal_arr, [
                                                    $drawdown_acc->coa_id,
                                                    $loRestructure->interest,
                                                    $loRestructure->note,
                                                    $loan->client_loan_account->air_id,
                                                    $loRestructure->interest,
                                                    $loRestructure->note,
                                                    $desc
                                                ]);
                        //  Down Payment 
                        if ($loRestructure->down_payment > 0)
                            array_push($journal_arr, [ 
                                                    $drawdown_acc->coa_id,
                                                    $loRestructure->down_payment,
                                                    $loRestructure->note,
                                                    $loan->client_loan_account->air_id,
                                                    $loRestructure->down_payment,
                                                    $loRestructure->note,
                                                    $desc
                                                ]);
                        // fee
                        if ($loRestructure->fee > 0)
                            array_push($journal_arr, [
                                                    $drawdown_acc->coa_id,
                                                    $loRestructure->fee,
                                                    $loRestructure->note,
                                                    $coa_fee->id,
                                                    $loRestructure->fee,
                                                    $loRestructure->note,
                                                    $desc
                                                ]);
                        // penalty
                        if ($loRestructure->penalty > 0)
                            array_push($journal_arr, [ 
                                                    $drawdown_acc->coa_id,
                                                    $loRestructure->penalty,
                                                    $loRestructure->note,
                                                    $coa_pnt->id,
                                                    $loRestructure->penalty,
                                                    $loRestructure->note,
                                                    $desc
                                                ]);

                        // in case of NPL
                        if ($loan->client_loan_account->status > GENERAL_LC_STATUS) {
                            // int-sus to int-inc 
                            if (floatval($loRestructure->interest) > 0) {
                                array_push($journal_arr, [
                                                    $loan->client_loan_account->sus_id,
                                                    floatval($loRestructure->interest),
                                                    $loRestructure->note,
                                                    $loan->client_loan_account->int_inc_id,
                                                    floatval($loRestructure->interest),
                                                    $loRestructure->note,
                                                    $loRestructure->note
                                                ]);
                            }
                        }
                        //provision
                        if (floatval($loRestructure->principal) > 0) {
                            // general or specific
                            // reverse provision   
                            $prov_arr_rev = $prov_arr = [];
                            $prov_arr_rev = get_journal_provision($loan->client_loan_account, $loan->client_loan_account->balance, $loan->client_loan_account->status, GENERAL_LC_STATUS, "up");
                            if ($prov_arr_rev[0] != []){
                                array_push($journal_arr, $prov_arr_rev[0]);
                            }
                            // do provision
                            if (round($loan->client_loan_account->balance - floatval($loRestructure->principal), 2) > 0) {
                                $prov_arr = get_journal_provision($loan->client_loan_account, $loan->client_loan_account->balance - floatval($loRestructure->principal), GENERAL_LC_STATUS, $loan->client_loan_account->status, "down");
                                if ($prov_arr[0] != []){
                                    array_push($journal_arr, $prov_arr[0]);
                                }
                            }
                        }
                        // dd($data);
                        // ========= Journal
                            $this->userActivity($loRestructure->user_id, $loan_id, 6, 'Reschedule Loan');
                            // record_journal($loan, $date, $type = "Reschedule Loan", $data, $journal_arr, Auth::user()->branch_id, $loRestructure->user_id);
                            // update drawdown account
                                    // $drawdown_acc->balance = floatval($drawdown_acc->balance) - floatval($loRestructure->amount);
                                    // $drawdown_acc->save();
                            // update client loan account balance
                                    // $loan_account = ClientLoanAccounts::select('id', 'balance')->where('id', $loan->client_loan_account->id)->first();
                                    // $loan_account->balance = $loan_amount;
                                    // if (CLASS_NEW_PRAKAS == 1) {
                                    //     $loan_account->status = 7; // closed
                                    // } else {
                                    //     $loan_account->status = 7; // closed
                                    // }
                                    // $loan_account->save();
                        // ===== End Journal

                    }
                    // === End Laon Update
                }
                //  End If Save Re Schedule
                return redirect()->route('loan_detail', [$loan_id])->with(['msg' => 'Loan Reschedule Successfully !']);
            }
            Session::flash('message', 'Loan Reschedule Fail !');
            return redirect('/');
        }
    }

    public function postClose($loan_id = NULL)
    {
        $data = Request::except('_token');
        $rules = ['close_date' => 'required|date',
            'total_amount' => 'required|numeric'
        ];
        $attribs = ['close_date' => 'Restructure Date',
            'total_amount' => 'Amount'
        ];

        $check_branch = CompanyBranch::find(Auth::user()->branch_id);
        if (!$check_branch) {
            Session::flash('danger', 'Please check your user Company');
            return redirect()->back();
        }

        $validator = Validator::make($data, $rules);
        $validator->setAttributeNames($attribs);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        } else {
            $loan = Loan::select(['id', 'settlement_date', 'client_id', 'status', 'loan_account_id', 'contract_id','drawdown_acc'])->with('client_loan_account')->where('id', '=', $loan_id)->first();
            $drawdown_acc = DrawdownAccounts::where('client_id', '=', $loan->client_id)->where('account_no', '=', $loan->drawdown_acc)->first();
            $coa_id = $loan->client_loan_account->coa_id;
            if (!empty($loan) && ($loan->status == 3 || $loan->status == 8)) {
                $last_transaction = TransactionsRequiry::select('id', 'balance', 'is_audit')->where('loan_id', '=', $loan_id)
                    ->orderBy('id', 'DESC')->first();
                if ($last_transaction->is_audit != 1) {
                    Session::flash('danger', 'Can not close this loan, the last transaction need to be auditted!');
                    return redirect()->back();
                }
                // Close Loan
                if ($data["transType"] == "Close") {
                    if (floatval($drawdown_acc->balance) - floatval(Request::input('total_amount')) < 0) {
                        Session::flash('danger', 'Balance in Drawdown Account is insufficient!');
                        return redirect()->back();
                    }
                    $cl              = new LoanClose;
                    $cl->loan_id     = $loan_id;
                    $date            = date('Y-m-d', strtotime(Request::input('close_date')));
                    $cl->closed_date = $date;
                    $cl->amount      = Request::input('total_amount');
                    $cl->principal   = Request::input('prin_amount');
                    $cl->interest    = Request::input('air_amount');
                    $cl->fee         = Request::input('fee_amount');
                    $cl->penalty     = Request::input('penalty');
                    $cl->note        = Request::input('note');
                    $cl->user_id     = Auth::user()->id;
                    // journal
                    $coa_pnt = CoaCategory::select('id', 'account_code', 'name')->where('type', 6)->where('currency', $loan->client_loan_account->currency)->where('name', 'Inc-Fine and Penalty on Repayment of Loans and leasing')->first();
                    $coa_fee = CoaCategory::select('id', 'account_code', 'name')->where('type', 6)->where('currency', $loan->client_loan_account->currency)->where('name', 'Inc-Other Fees and Commissions from Loans and Leasing')->first();

                    $journal_arr = [];
                    $desc = "Close Loan : " . Request::input('note');
                    // principal
                    array_push($journal_arr, [$drawdown_acc->coa_id, $cl->principal, $cl->note,
                        $coa_id, $cl->principal, $cl->note,
                        $desc]);
                    // interest
                    if ($cl->interest > 0)
                        array_push($journal_arr, [$drawdown_acc->coa_id, $cl->interest, $cl->note,
                            $loan->client_loan_account->air_id, $cl->interest, $cl->note,
                            $desc]);
                    // fee
                    if ($cl->fee > 0)
                        array_push($journal_arr, [$drawdown_acc->coa_id, $cl->fee, $cl->note,
                            $coa_fee->id, $cl->fee, $cl->note,
                            $desc]);
                    // penalty
                    if ($cl->penalty > 0)
                        array_push($journal_arr, [$drawdown_acc->coa_id, $cl->penalty, $cl->note,
                            $coa_pnt->id, $cl->penalty, $cl->note,
                            $desc]);

                    // in case of NPL
                    if ($loan->client_loan_account->status > GENERAL_LC_STATUS) {
                        // int-sus to int-inc 
                        if (floatval($cl->interest) > 0) {
                            array_push($journal_arr, [$loan->client_loan_account->sus_id, floatval($cl->interest), $cl->note,
                                $loan->client_loan_account->int_inc_id, floatval($cl->interest), $cl->note,
                                $cl->note]);
                        }
                    }
                    //provision
                    if (floatval($cl->principal) > 0) {
                        // general or specific
                        // reverse provision   
                        $prov_arr_rev = $prov_arr = [];
                        $prov_arr_rev = get_journal_provision($loan->client_loan_account, $loan->client_loan_account->balance, $loan->client_loan_account->status, GENERAL_LC_STATUS, "up");
                        if ($prov_arr_rev[0] != []) array_push($journal_arr, $prov_arr_rev[0]);
                        // do provision
                        if (round($loan->client_loan_account->balance - floatval($cl->principal), 2) > 0) {
                            $prov_arr = get_journal_provision($loan->client_loan_account, $loan->client_loan_account->balance - floatval($cl->principal), GENERAL_LC_STATUS, $loan->client_loan_account->status, "down");
                            if ($prov_arr[0] != []) array_push($journal_arr, $prov_arr[0]);
                        }
                    }
                    if ($cl->save()) {
                        $loan->status = 6; //chuch comment out
                        $loan->settlement_date = $date;
                        if ($loan->save()) {
                            $this->userActivity($cl->user_id, $loan_id, 6, 'Close Loan');
                            record_journal($loan, $date, $type = "Close Loan", $data, $journal_arr, Auth::user()->branch_id, $cl->user_id);
                            // update drawdown account
                            $drawdown_acc->balance = floatval($drawdown_acc->balance) - floatval($cl->amount);
                            $drawdown_acc->save();
                            // update client loan account balance
                            $loan_account = ClientLoanAccounts::select('id', 'balance')->where('id', $loan->client_loan_account->id)->first();
                            $loan_account->balance = floatval($loan_account->balance) - floatval($cl->principal);
                            if (CLASS_NEW_PRAKAS == 1) {
                                $loan_account->status = 9; // closed
                            } else {
                                $loan_account->status = 8; // closed
                            }
                            $loan_account->save();
                        } else {
                            $clo = LoanClose::where('id', '=', $cl->id)->first();
                            if (!empty($clo)) {
                                $clo->delete();
                            }
                            return redirect()->back();
                        }
                    }
                } elseif (($data["transType"] == "Write-Off") || ($data["transType"] == "Terminate")) {
                    $wo                          = new LoanWriteOff;
                    $wo->loan_id                 = $loan_id;
                    $date                        = date('Y-m-d', strtotime(Request::input('close_date')));
                    $wo->write_off_date          = $date;
                    // Down payment portion to write off (cleared together with loan principal)
                    $dp_amount                   = floatval(Request::input('dp_amount', 0));
                    // amount = total write-off (loan principal + down payment + interest + fee)
                    $wo->amount                  = floatval(Request::input('prin_amount')) + $dp_amount + floatval(Request::input('air_amount')) + floatval(Request::input('fee_amount'));
                    // write_off_outst_balance stays as loan principal only (for journal compatibility)
                    $wo->write_off_outst_balance = floatval(Request::input('prin_amount'));
                    $wo->wo_interest             = Request::input('air_amount');
                    $wo->wo_fee                  = Request::input('fee_amount');
                    $wo->wo_penalty              = Request::input('penalty');
                    $wo->note                    = Request::input('note');
                    $wo->user_id                 = Auth::user()->id;
                    // Journal
                    $journal_arr = [];
                    if($data["transType"] == "Terminate"){
                        $desc = "Terminate Loan : " . Request::input('note');
                    }else{
                        $desc = "Write-off Loan : " . Request::input('note');
                    }

                    $loan_account = ClientLoanAccounts::where('id', $loan->client_loan_account->id)->first();
                    $pat1 = '%' . substr($loan_account->acc_key, 0, 7) . '%';
                    if (strpos($loan_account->acc_key, '<') !== false) {
                        $pat2 = '%<%';
                    } elseif (strpos($loan_account->acc_key, '>') !== false) {
                        $pat2 = '%>%';
                    }
                    $allowance_acc = CoaCategory::select('*')->where('name', 'LIKE', '(Less) Specific Loan Loses%')->where('name', 'LIKE', $pat1)->where('name', 'LIKE', $pat2)->where('type', 6)->where('currency', $loan_account->currency)->first();
                    // principal
                    if ($wo->write_off_outst_balance > 0)
                        array_push($journal_arr, [$allowance_acc->id, $wo->write_off_outst_balance, $wo->note,
                            $loan_account->coa_id, $wo->write_off_outst_balance, $wo->note,
                            $desc]);
                    // interest
                    if ($wo->wo_interest > 0)
                        array_push($journal_arr, [$loan_account->sus_id, $wo->wo_interest, $wo->note,
                            $loan_account->air_id, $wo->wo_interest, $wo->note,
                            $desc]);
                    // Save transaction
                    if ($wo->save()) {
                        $close_date = date('Y-m-d', strtotime(Request::input('close_date')));
                        if($data["transType"] == "Terminate"){
                            $loan->status = 11;
                        }else{
                            $loan->status = 5;
                        }
                        $loan->settlement_date = $close_date;
                        $loan->cut_stock_date = $close_date;

                        if ($loan->save()) {
                            if($data["transType"] == "Terminate"){
                                $this->userActivity($wo->user_id, $loan_id, 6, 'Terminate Loan');
                                record_journal($loan, $date, $type = "Terminate Loan", $data, $journal_arr, Auth::user()->branch_id, $wo->user_id);
                            }else{
                                $this->userActivity($wo->user_id, $loan_id, 6, 'Write-Off Loan');
                                record_journal($loan, $date, $type = "Write-Off Loan", $data, $journal_arr, Auth::user()->branch_id, $wo->user_id);
                            }

                            // update client loan account balance (loan principal portion)
                            $loan_account->balance = floatval($loan_account->balance) - floatval($wo->write_off_outst_balance);
                            // also clear the down payment balance written off in this transaction
                            if ($dp_amount > 0) {
                                $loan_account->balance_downpayment = max(0, floatval($loan_account->balance_downpayment) - $dp_amount);
                            }
                            $loan_account->status = CLOSE_LC_STATUS - 1; // client_loan_account_status
                            $loan_account->save();
                        } else {
                            $wof = LoanWriteOff::where('id', '=', $wo->id)->first();
                            if (!empty($wof)) {
                                $wof->delete();
                            }
                            return redirect()->back();
                        }
                    }
                }

                /* Reject Drawdown Account */
                if($drawdown_acc){
                    $drawdown_acc->status = 2;
                    if($drawdown_acc->save()){
                         $client_loan_account = ClientLoanAccounts::where('id', '=', $drawdown_acc->client_loan_id)->first();
                        if($client_loan_account){
                            $client_loan_account->status = 9;
                            $client_loan_account->save();
                        }
                    }
                }

                /* Update Sale Order */
                $loan_acc = ClientLoanAccounts::select(['id', 'activated_on', 'status', 'currency', 'account_name', 'client_id'])->where('id', '=', $loan->loan_account_id)->first();
                if($loan_acc){
                    $dd = DrawdownAccounts::select('sale_id')->where('client_id', '=', $loan_acc->client_id)->where('client_loan_id',$loan_acc->id)->first();
                    if($dd){
                        if($dd->sale_id){
                            $sale = Sale::where('id',$dd->sale_id)->first();
                            if($sale){
                                $sale->status = 'Canceled';
                                $sale->remark =  $data["transType"].' Loan =>'.Request::input('note');                        
                                $sale->save();
                            }
                            $invoice = Invoice::where('sale_id',$dd->sale_id)->first();
                            if($invoice){
                                $invoice->invoice_status  = 'Canceled';
                                $invoice->remark          = $data["transType"].' Loan =>'.Request::input('note');
                                $invoice->payment_status == 'Void';
                                $invoice->save();
                            }
                        }

                    }

                }
                
                /* End Updat Sale Order */
            }
            return redirect()->route('loan_detail', [$loan_id]);
        }
    }
    public function getLoanBuyBack($loan_id = 0)
    {
        if ($loan_id > 0) {
            $B0 = new Loan();
            $lc = $B0->select(['loans.*',
                                    'clients.client_name',
                                    'client_loan_accounts.account_no'
                                ])
                ->join('client_loan_accounts', 'client_loan_accounts.id', '=', 'loans.loan_account_id')
                ->join('clients', 'clients.id', '=', 'loans.client_id');
            $lc = $lc->with([
                'payment' => function ($q) {
                    $q->select('id',
                                'loan_id',
                                'paid_principal',
                                'paid_interest',
                                'paid_fee',
                                'paid_other_fee',
                                'penalty_amount',
                                'repayment_owed',
                                'repayment_date',
                                'payment_month',
                                'status',
                                'condition_id'
                            )->orderBy('id', 'asc');
                },
                'client_loan_account',
                'schedule' => function ($q) {
                        $q->select('loan_id',
                            'schedule_date',
                            'date_num',
                            'interest',
                            'principal',
                            'fee',
                            'other_fee',
                            'intraday_rate',
                            'no',
                            'type',
                            'loan_no'
                        );
                },
                'transaction' => function ($q) {
                    $q->where('trans_type', 'LIKE', '%Loan Repayment%');
                },
                'penalty_record' => function ($q) {
                    $q->select('id', 'loan_id', 'record_date', 'owed_penalty')->orderBy('id', 'DESC');
                }
            ])->whereIn('loans.status', [3, 8]);

            $lc = $lc->where('loans.id', $loan_id)->first();

            $dpDateClone = !is_null(Request::All()) ? Request::input('dpDateClone') : date('Y-m-d');
            if(!$dpDateClone){
                $dpDateClone = date('Y-m-d');
            }
            $penalty_arr   = LoanCalculate::getTotalPenalty($lc, $dpDateClone);
            $sch_repay_arr = get_auto_repay_array($lc, $dpDateClone);
            $penalty       = $penalty_arr[4];
            $fee_bal       = $penalty_arr[9];
            $other_fee     = $penalty_arr[10];
            $find_unit_id  = Loan::where('id', '=', $loan_id)->get();
            $unit_code     = Unit::find($find_unit_id[0]->unit_id);
    
            $loan_account = DrawdownAccounts::where('id', $find_unit_id[0]->loan_account_id)->first();

            return $this->view('loans.loan_buy_back', ['loan_id' => $loan_id,
                                                'loan'          => $lc,
                                                'dpDateClone'   => $dpDateClone,
                                                'fee_bal'       => $fee_bal,
                                                'penalty'       => $penalty,
                                                'other_fee'     => $other_fee,
                                                'sch_repay_arr' => $sch_repay_arr,
                                                'unit_code'     => $unit_code,
                                                'drawdow_acc'   => $drawdow_acc,
                                                'loan_account'  => $loan_account
                                            ]);
        }
        return redirect()->back();
    }
    public function postLoanBuyBack($loan_id = NULL)
    {
        $data = Request::except('_token');

        $check_branch = CompanyBranch::find(Auth::user()->branch_id);
        if (!$check_branch) {
            Session::flash('danger', 'Please check your user Company');
            return redirect()->back();
        }
            $loan = Loan::select(['id', 'settlement_date', 'client_id', 'status', 'loan_account_id', 'contract_id','drawdown_acc','unit_id'])
                ->with('client_loan_account')->where('id', '=', $loan_id)->first();
            $drawdown_acc = DrawdownAccounts::where('client_id', '=', $loan->client_id)->where('account_no', '=', $loan->drawdown_acc)->first();
            $coa_id = $loan->client_loan_account->coa_id;

                    $loan->status = 12; //loan buy back
                    $loan->status_remark=Request::input('note');

                        if ($loan->save()) {
                            $units = Unit::where('id',$loan->unit_id)->first();
                            if($units){
                                $units->status = 'available';
                                $units->save();
                            }
                        
                    
                }
            
            return redirect()->route('loan_detail', [$loan_id]);
        
    }

    public function getDisburse($loan_id = NULL)
    {
        Session::flash('pre_url', URL::previous());
        //$loan = Loan::select('id', 'disburse_date', 'loan_amount', 'disburse_note', 'repayment_type', 'contract_id', 'company_branch_id', 'loan_account_id')
        $loan = Loan::with(['client_loan_account', 'schedule'])
            ->where('id', '=', $loan_id)
            ->where('status', '=', 2)
            ->first();

        if (!empty($loan)) {
            $branch = CompanyBranch::find($loan->company_branch_id);
            $loan_account = $loan->client_loan_account;
//        	$coa = CoaCategory::find($loan_account->parent_id);
            $coa_id = CoaCategory::find($loan_account->coa_id);
            $product_id = Product::select(['id', 'product_name', 'product_price'])->where('is_loan', '=', 0)->orderBy('id', 'DESC')->get();

            //get default select_type 1
            $coa_1 = DrawdownAccounts::select('coa_id', 'account_no', 'account_name')
                ->with('coa')
                ->where('account_name', '=', $loan_account->account_name)
                ->where('account_no', '=', $loan->drawdown_acc)
                ->where('currency', $loan_account->currency)->first();
//            $coa_1 = CoaCategory::select('id', 'account_code', 'name')->where('type', 5)->where('currency', $loan_account->currency);
//            $coa_1 = $coa_1->where('name', 'Cash in Vault and on Hand');
//            $coa_1 = $coa_1->first();

            $coa_2 = CoaCategory::select('id', 'account_code', 'name', 'currency')->where('type', 6)->where('currency', $loan_account->currency);
            $coa_2 = $coa_2->where('name', 'Inc-Other Fees and Commissions from Loans and Leasing');
            $coa_2 = $coa_2->first();

            $repayments = RepaymentSchedule::where('loan_id', $loan_id)->get();
            $teller = Teller::select('till_account.id', 'account_no', 'account_name', 'assign_user_id', 'till_account.branch_id', 'users.name', 'roles.role', 'till_account.balance', 'till_account.status')
                ->join('users', 'users.id', '=', 'till_account.assign_user_id')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->where('till_account.branch_id', '=', Auth::user()->branch_id)
                ->Orwhere(function ($w) {
                    $w->whereIn('roles.role', ['chief_of_teller', 'cas']);
                })
                ->where('currency_id', '=', $loan_account->currency)
                ->get();
            return $this->view('loans.disburse',
                [
                    'loan'        => $loan,
                    'branch_name' => $branch->branch_name,
                    'branch_code' => $branch->branch_code,
                    'currency'    => $loan_account->currency,
                    'coa'         => $coa_id,
                    'coa_1'       => $coa_1,
                    'coa_2'       => $coa_2,
                    'product_id'  => $product_id->toArray(),
                    'repayments'  => $repayments,
                    'tellers'     => $teller,
                ]);
        }
        return redirect()->back();
    }

    /**
     * @param null $loan_id
     * @return $this|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function postDisburse($loan_id = NULL)
    {
        $data = Request::except(['_token']);
        $rules = [
            'disburse_on' => 'required|date'
        ];
        $attribs = [
            'disburse_on' => 'Disburse Date',
        ];
        $validator = Validator::make($data, $rules);
        $validator->setAttributeNames($attribs);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        } else {
            $udate = Request::input('repayment_date');
            if(sizeof($udate) <= 0){
                Session::flash('danger', 'Can not close this loan, the last transaction need to be auditted!');
                return redirect()->back();
            }
            $loan = Loan::with('schedule')->where('id', '=', $loan_id)->first();
            // PHP may truncate a large form at max_input_vars. Reject missing rows
            // before changing the loan status, accounts, transactions or schedule.
            $schedule_count = RepaymentSchedule::where('loan_id', $loan_id)->count();
            foreach (['repayment_date', 'total_d', 'repayment_interest', 'repayment_principal', 'repayment_fee', 'repayment_intraday_rate'] as $field) {
                $values = Request::input($field);
                if (!is_array($values) || count($values) != $schedule_count || array_keys($values) !== range(0, $schedule_count - 1) || in_array(null, $values, true) || in_array('', $values, true)) {
                    Session::flash('danger', 'Disbursement was not saved because the repayment schedule is incomplete. Reload the page and try again.');
                    return redirect()->back();
                }
            }
            $loan_schedule = RepaymentSchedule::where('loan_id', $loan_id)->first();
            if (!empty($loan) && $loan->status == 2) {
                $loan->disburse_date     = date('Y-m-d');
                $loan->contract_date     = date('Y-m-d');
                $loan->disburse_note     = Request::input('note');
                $loan->start_date        = Request::input('schedule_date');
                $loan->contract_deadline = Request::input('contract_deadline');
                $loan->disburse_byuserid = Auth::user()->id;
                $loan->status            = 3;
                $loan->annual_yield      = $data['annual_yield'];
                if ($loan->save()) {

                    $this->userActivity($loan->disburse_byuserid, $loan_id, 6, 'Disburse Loan');
                    $transaction              = new TransactionsRequiry();
                    $transaction->loan_id     = $loan_id;
                    $transaction->trans_date  = date('Y-m-d', strtotime(Request::input('disburse_on')));
                    $transaction->trans_type  = "Disbursement";
                    $transaction->amount      = $loan->loan_amount;
                    $transaction->description = $loan->disburse_note;
                    $transaction->balance     = $loan->loan_amount;
                    $transaction->user_id     = $loan->user_id;
                    $transaction->is_audit    = 1;
                    $transaction->save();
                    // Update Client Loan Account
                    $loan_acc = ClientLoanAccounts::select(['id', 'activated_on', 'status', 'currency', 'account_name', 'client_id'])->where('id', '=', $loan->loan_account_id)->first();
                    if (!empty($loan_acc) && count($loan_acc) > 0) {
                        $loan_acc->activated_on = date('Y-m-d', strtotime(Request::input('disburse_on')));
                        //-- $loan_acc->activated_on = date('Y-m-d');
                        $loan_acc->status = 2; // Activate(Std)
                        $loan_acc->balance = $loan->loan_amount;
                        $loan_acc->balance_downpayment = $loan->down_payment;
                        if ($loan_acc->save()) {

                        } else {
                            $loan->delete();
                            $transaction->delete();
                            Session::flash('message', 'Update client loan account information not successfully');
                        }
                    }

                    //Fee Charge and commission fee
                    $transaction1 = [];
                    if (Request::has('admin_fee') && Request::input('admin_fee') > 0) {
                        // loan repayment
                        $new_repay                 = new LoanPayments();
                        $new_repay->loan_id        = $loan_id;
                        $new_repay->condition_id   = 0;
                        $new_repay->user_id        = $loan->user_id;
                        $new_repay->payment_month  = 0;
                        $new_repay->repayment_date = date('Y-m-d', strtotime(Request::input('disburse_on')));
                        //-- $new_repay->repayment_date = date('Y-m-d');
                        $new_repay->paid_fee     = floatval(Request::input('admin_fee'));
                        $new_repay->note         = "Upfront charge for " . $loan->contract_id;
                        $new_repay->status       = 1;
                        $new_repay->payment_type = 'Drawdown Account';

                        $fee_charge                = new FeeCharge();
                        $fee_charge->charge_type   = 4;                                                        // upfront charge
                        $fee_charge->charge_amount = floatval(Request::input('admin_fee'));
                        $fee_charge->charge_date   = date('Y-m-d', strtotime(Request::input('disburse_on')));
                        //-- $fee_charge->charge_date = date('Y-m-d');
                        $fee_charge->note     = "Upfront charge for " . $loan->contract_id;
                        $fee_charge->loan_id  = $loan_id;
                        $fee_charge->is_audit = 1;
                        $fee_charge->user_id  = $loan->user_id;
                        $fee_charge->save();

                        // Transaction
                        $transaction1 = new TransactionsRequiry();
                        $transaction1->id = TransactionsRequiry::select('id')->orderBy('id', 'desc')->first()->id + 1;
                        $transaction1->loan_id = $loan_id;
                        $transaction1->trans_date = date('Y-m-d', strtotime(Request::input('disburse_on')));
                        //-- $transaction1->trans_date = date('Y-m-d');
                        $transaction1->trans_type = "Fee Charge Repayment";
                        $transaction1->amount = floatval(Request::input('admin_fee'));
                        $transaction1->fee = $transaction1->amount;
                        $transaction1->balance = $loan->loan_amount;
                        $transaction1->description = $fee_charge->note;
                        $transaction1->user_id = $loan->user_id;
                        // $transaction1->is_audit = 1;
                        $transaction1->disburs_loan_id = $loan_id;
                        $transaction1->is_audit = 0;
                        if ($transaction1->save()) {

                        } else {
                            $loan->delete();
                            $transaction->delete();
                            $transaction1->delete();
                            $fee_charge->delete();
                            Session::flash('message', 'Update client loan account information not successfully');
                        }
                    }

                    $transaction_arr = array($transaction, $transaction1);

                            // $this->Add_disbursementData_To_notification_new($loan, Request::input('sel_tellers'),$loan_id);
                    //update repayment schedule
                    $ii = 0;
                    $repayments = RepaymentSchedule::where('loan_id', $loan_id)->get();

                    $udate = Request::input('repayment_date');
                    $date_num = Request::input('total_d');
                    $repayment_interest = Request::input('repayment_interest');
                    $repayment_principal = Request::input('repayment_principal');
                    $repayment_fee = Request::input('repayment_fee');
                    $intra_rate = Request::input('repayment_intraday_rate');
                    foreach ($repayments as $rep) {
                        RepaymentSchedule::where('id', $rep->id)
                        ->update(
                            array(
                            'schedule_date' => $udate[$ii], 
                            'date_num' => $date_num[$ii],
                            'interest' => $repayment_interest[$ii], 
                            'principal' => $repayment_principal[$ii], 
                            'fee' => $repayment_fee[$ii], 
                            'intraday_rate' => $intra_rate[$ii]
                            )
                        );
                        $ii++;
                    }

                    //chuch 2016/11/22 save fee
                    $fee_type = Request::input('fee_type');
                    $fee_amount = Request::input('fee_amount');
                    $fee_tenure = Request::input('fee_tenure');
                    $fee_note = Request::input('fee_note');
                    $fee_repayments = RepaymentSchedule::where('loan_id', $loan_id)->get();
                    foreach ($fee_repayments as $fee) {
                        $r_fee[] = $fee;
                    }
                    for ($f = 0; $f < count(Request::input('fee_type')); $f++) {
                        $ex = explode(',', $fee_tenure[$f]);
                        for ($i = 0; $i < count($ex); $i++) {
                            $index = trim($ex[$i]) - 1;
                            $sf = new ScheduleFee();
                            $sf->loan_id = $loan_id;
                            $sf->fee_type = $fee_type[$f];
                            $sf->amount = $fee_amount[$f];
                            $sf->schedule_date = $r_fee[$index]->schedule_date;
                            $sf->note = $fee_note[$f];
                            if ($fee_amount[$f] > 0) $sf->save();
                        }
                    }

                    //journal\
                    $branch_code = Request::input('branch_code');
                    // $entry_date = date('Y-m-d', strtotime(Request::input('disburse_on')));
                    $entry_date = $loan->start_payment_date;
                    //-- $entry_date = date('Y-m-d H:i:s');
                    $contract_id = Request::input('contract_id');

                    for ($m = 0; $m < count(Request::input('debit')); $m++) {
                        $transaction_id = $transaction_arr[$m]->id;
                        if (!$transaction_id || empty($transaction_id)) continue;

                        $description             = Request::input('description')[$m];
                        $journal                 = new JournalRequiry;
                        $journal->tran_id        = $transaction_id;
                        $journal->entry_date     = $entry_date;
                        $journal->description    = $description;
                        $journal->user_id        = $loan->user_id;
                        // $journal->is_audit = 1;
                        $journal->is_audit = 0;
                        $journal->disburs_loan_id = $loan_id;
                        if ($journal->saveWithGeneratedInvoice()) {
                            $tran = TransactionsRequiry::where('id', '=', $transaction_id)->where('flag', '=', 0)->first();
                            if (!empty($tran)) {
                                $tran->flag = 1;
                                $tran->invoice_number = $journal->invoice_number;
                                $tran->save();
                                if ($new_repay->paid_fee == Request::input('debit')[$m]) {
                                    $new_repay->invoice_number = $journal->invoice_number;
                                    $new_repay->save();
                                    // update loan schedule
                                    $loan_schedule = RepaymentSchedule::where('loan_id', $loan_id)->first();
                                    $loan_schedule->status = 1;
                                    $loan_schedule->save();
                                }
                            }
                            for ($k = 0; $k < 2; $k++) { // 0 = debit, 1 = credit
                                $parent_debit = Request::input('parent_debit')[$m];
                                $parent_credit = Request::input('parent_credit')[$m];
                                $debit = Request::input('debit')[$m];
                                $credit = Request::input('credit')[$m];
                                $d_description = Request::input('d_description')[$m];
                                $c_description = Request::input('c_description')[$m];

                                $jd = new JournalDetail;
                                $jd->journal_id = $journal->id;
                                $jd->coa_id = $k == 0 ? $parent_debit : $parent_credit;
                                $jd->reference = $contract_id;
                                $jd->branch_code = $branch_code;

                                $prev_bl = array_fill(0, 2, 0.0);
                                $prev_row = JournalDetail::select('b_debit', 'b_credit')
                                    ->where('coa_id', $jd->coa_id)
                                    ->orderBy('id', 'desc')
                                    ->first();
                                if (!empty($prev_row)) {
                                    $prev_bl[0] = $prev_row->b_debit;
                                    $prev_bl[1] = $prev_row->b_credit;
                                }
                                $jd->p_debit = $prev_bl[0];
                                $jd->p_credit = $prev_bl[1];
                                $jd->debit = $k == 0 ? $debit : 0;
                                $jd->credit = $k == 1 ? $credit : 0;
                                $jd->b_debit = floatval($prev_bl[0]) + floatval($jd->debit); 
                                $jd->b_credit = floatval($prev_bl[1]) + floatval($jd->credit);
                                $jd->description = $k == 0 ? $d_description : $c_description;
                                $jd->is_audit = 1;
                                // $jd->is_audit = 0;
                                $jd->disburs_loan_id = $loan_id;
                                $jd->save();
                            }
                        }
                    }
                    // Update Drawdown Acc. Balance
                    // update 15-05-2021 
                        // $dd = DrawdownAccounts::select('id', 'balance', 'client_id', 'currency')->where('client_id', '=', $loan_acc->client_id)->where('account_no',$loan->drawdown_acc)->where('currency', '=', $loan_acc->currency)->first();
                        // if ($dd) {
                        //     // $dd->balance = 0;
                        //     $dd->balance = $transaction->amount;
                        //     // $dd->balance = $transaction->amount + $loan->down_payment;
                        //     $dd->save();
                        // }

                    /* Mark Invoice from Sale */
                    $dd = DrawdownAccounts::select('sale_id')->where('client_id', '=', $loan_acc->client_id)->where('client_loan_id',$loan_acc->id)->first();
                    if($dd){
                        app('App\Http\Controllers\SaleController')->markInvoice($dd->sale_id);                        
                    }
                }
            }
            return redirect()->route('loan_detail', [$loan_id]);
        }
//            }
//        }
        return redirect('/');
    }


    private function Add_disbursementData_To_notification($loan, $assign_user_id,$loan_id = '')
    {
        $notification = new App\Models\Notification();
        $till_id = App\Models\Teller::select('id', 'created_by')->where('assign_user_id', '=', $assign_user_id)->first();
        $chief_id = App\Models\Teller::select('id')->where('assign_user_id', '=', $till_id->created_by)->first();
        $data = [
            Request::input('sel_tellers'),
            11,
            $loan->id,
            $this->user_id,
            $till_id->id,
            'Disburse Loan',
            date("Y-m-d H:m:s", time()),
            $loan->disburse_note,
            $loan->loan_amount,
            $loan_id
        ];
        return $notification->setNotification($data);
    } 

    private function Add_disbursementData_To_notification_new($loan, $till_acc_id,$loan_id = ''){
        $notification = new App\Models\Notification();
        $till_id = App\Models\Teller::select('id', 'created_by','assign_user_id')->where('id', '=', $till_acc_id)->first();
        $chief_id = '';
        $assign_user_id = $till_id->assign_user_id;
        if($till_id){
            $chief_id = $till_id->created_by;
        }
        $data = [
            $assign_user_id,
            11,
            $loan->id,
            $this->user_id,
            $till_id->id,
            'Disburse Loan',
            date("Y-m-d H:m:s", time()),
            $loan->disburse_note,
            $loan->loan_amount,
            $loan_id
        ];
        return $notification->setNotification($data);
    }

    public function getRescheduleApprove($loan_id = NULL)
    {
        Session::flash('pre_url', URL::previous());
        if ($loan_id > 0) {
            $loan = Loan::select(['id', 'status', 'loan_amount'])->where('id', '=', $loan_id)->first();
            if (!empty($loan) && $loan->status == 7) { /* Reschedule ( unauthorized ) */
                return $this->view('loans.reschedule_approve', ['loan' => $loan]);
            }
        }
        return redirect()->back();
    }

    public function postRescheduleApprove($loan_id = NULL)
    {
        $data = Request::except(['_token']);
        $rules = [
            'approval_date' => 'required|date',
            'amount' => 'required'
        ];
        $attribs = [
            'approval_date' => 'Approval Date',
            'amount' => 'Invalid Amount'
        ];
        $validator = Validator::make($data, $rules);
        $validator->setAttributeNames($attribs);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        } else {
            $loan = Loan::where('id', '=', $loan_id)->with('client_loan_account')->first();
            $drawdown_acc = DrawdownAccounts::where('client_id', '=', $loan->client_id)->where('account_no',$loan->drawdown_acc)->first();
            if (!empty($loan) && $loan->status == 7) {
                $napproval = new LoanApproval();
                $napproval->loan_id = $loan_id;
                if (Auth::check()) {
                    $napproval->user_id = Auth::user()->id;
                } else {
                    return redirect()->route('login');
                }
                if (Request::has('approval_date')) {
                    $date = Request::input('approval_date');
                    $date = date('Y-m-d', strtotime($date));
                    $napproval->approval_date = $date;
                }
                if (Request::has('note')) {
                    $napproval->note = Request::input('note');
                }
                $napproval->created_at = date('Y-m-d H:i:s');
                $loan->status = 8; //Reschedule(approved)
                $loan->disburse_date = $date;
                if ($napproval->save()) {
                    if ($loan->save()) {

                        $loan_acc = ClientLoanAccounts::select(['id', 'loan_ref'])->where('id', '=', $loan->loan_account_id)->first();
                        $loan_acc->status = 2;
                        if ($loan_acc->save()) {
                            $this->do_audit($loan_id, '', Auth::user()->id, 'reschedule_loan', 1, 'authorize reschedule loan');
                        }

                        $this->userActivity($napproval->user_id, $loan_id, 6, 'Reschedule(Approved) Loan');
                        $journal_arr = [];
                        $amount = floatval(Request::input('amount'));
                        // array_push($journal_arr, [$loan->client_loan_account->coa_id, $amount, Request::input('note'),
                        //     $drawdown_acc->coa_id, $amount, Request::input('note'),
                        //     Request::input('note')]);
                        // record_journal($loan, $date, $type = "Reschedule Loan", $data, $journal_arr, Auth::user()->branch_id, Auth::user()->id);
                        // update drawdown account
                        $drawdown_acc->balance = floatval($drawdown_acc->balance) - floatval($cl->amount);
                        $drawdown_acc->save();
                        if (Request::has('amount')) {
                            $transaction              = new TransactionsRequiry();
                            $transaction->loan_id     = $loan_id;
                            $transaction->trans_date  = $napproval->approval_date;
                            $transaction->trans_type  = "Reschedule(Approved)";
                            $transaction->amount      = $amount;
                            $transaction->description = $napproval->note;
                            $transaction->balance     = $amount;
                            $transaction->user_id     = $napproval->user_id;
                            if ($transaction->save()) {
                                return redirect()->route('loan_detail', [$loan_id]);
                            } else {
                                $loan->status = 7;
                                $loan->save();
                                $napproval->delete();
                                redirect('/');
                            }
                        } else {
                            redirect('/');
                        }
                    }
                }
            }
        }
        return redirect('/');
    }

    /**
     * Hand Over — Revenue Recognition
     */
    public function getHandover($loan_id = 0)
    {
        $loan = Loan::with(['client', 'projects', 'units', 'unittypes', 'client_loan_account',
            'payment' => function ($q) { $q->where('status', 1); }
        ])->where('id', $loan_id)->first();

        // Same statuses the Hand Over link offers on the loan detail screen. There was no status
        // check here at all, so /loans/handover/{id} typed by hand would hand over a Rejected or
        // Unauthorized loan -- and a handover cannot be undone from the UI, since both methods
        // refuse once handover_date is set. It also feeds R2, R3, R4 and every BE4/AFT H.O split,
        // so a wrong one is quiet and wide-reaching. Closed (6) and Terminate (11) are excluded
        // deliberately.
        if (empty($loan) || $loan->handover_date
            || !in_array((int) $loan->status, [3, 8, 5, 9, 10])) {
            return redirect()->back();
        }

        $total_principal = 0; $total_interest = 0;
        if ($loan->payment) {
            foreach ($loan->payment as $p) {
                $total_principal += floatval($p->paid_principal);
                $total_interest += floatval($p->paid_interest);
            }
        }

        $data['loan'] = $loan;
        $data['total_principal'] = $total_principal;
        $data['total_interest'] = $total_interest;
        $data['outstanding'] = floatval($loan->loan_amount) - $total_principal;

        return $this->view('loans.handover', $data);
    }

    public function postHandover($loan_id = 0)
    {
        $loan = Loan::where('id', $loan_id)->first();
        // Enforced on the save as well as the form: the form guard only decides what is reachable.
        if (empty($loan) || $loan->handover_date
            || !in_array((int) $loan->status, [3, 8, 5, 9, 10])) {
            return redirect()->back();
        }

        $handover_date = Request::input('handover_date', date('Y-m-d'));

        $loan->handover_date = $handover_date . ' ' . date('H:i:s');
        $loan->handover_by_userid = Auth::user()->id;

        if ($loan->save()) {
            Session::flash('msg', 'Unit handed over successfully on ' . $handover_date);
            return redirect()->route('loan_detail', [$loan_id]);
        }

        Session::flash('danger', 'Failed to hand over unit.');
        return redirect()->back();
    }

    public function getPayOff($loan_id = NULL)
    {
        $dpDateClone = !is_null(Request::input('dpDate')) ? Request::input('dpDate') : date('Y-m-d');
        if(!$dpDateClone){
            $dpDateClone = date('Y-m-d');
        }
        $loan = Loan::where('id', '=', $loan_id)
            ->whereIn('status', [3, 8])
            ->with(['client', 'payment','branch','units', 'unittypes','product' => function ($query) {
                $query->with(['brand']);
            }, 'schedule' => function ($query) {
                $query->orderBy('schedule_date', 'asc');
            }])
            ->first();
        $drawdown_acc = DrawdownAccounts::select('id', 'balance', 'coa_id', 'client_id', 'currency')->with('coa')->with('currency_tbl')->where('client_id', '=', $loan->client_id)->where('account_no', '=', $loan->drawdown_acc)->first();
        if (!empty($loan)) {
            $data['dpDate ']= !is_null(Request::input('dpDate')) ? Request::input('dpDate') : date('Y-m-d');
            //check is already approve draft
            $data['draft_id'] = $draft_id = Request::has('draft_id') ? Request::input('draft_id') : 0;

            $draft = LoanPaymentsDraft::find($draft_id);
            if ($draft->status == 1) return redirect()->back();
            $allow_roles = config('static_data.allow_roles');
            if ($draft && $draft->user_id != Auth::user()->id && !in_array(Auth::user()->role_id, $allow_roles)) return redirect()->back();

            $data['draft'] = $draft;
            $data['user_id'] = Auth::user()->id;

            $loan_account = ClientLoanAccounts::find($loan->loan_account_id);
            $data['loan'] = $loan;
            $data['drawdown_acc'] = $drawdown_acc;
            $data['coa'] = CoaCategory::find($loan_account->coa_id);
            //$data['coa_pcp'] = CoaCategory::select('id', 'account_code', 'name')->where('type', 5)->where('currency', $loan_account->currency)->where('name', 'Cash in Vault and on Hand')->first();
            $data['coa_pcp'] = $drawdown_acc->coa;
            $data['coa_air'] = CoaCategory::find($loan_account->air_id);
            $data['coa_pnt'] = CoaCategory::select('id', 'account_code', 'name')->where('type', 6)->where('currency', $loan_account->currency)->where('name', 'Inc-Fine and Penalty on Repayment of Loans and leasing')->first();
            $data['coa_fee'] = CoaCategory::select('id', 'account_code', 'name')->where('type', 6)->where('currency', $loan_account->currency)->where('name', 'Inc-Other Fees and Commissions from Loans and Leasing')->first();
            $data['loan_fee'] = CoaCategory::select('id', 'account_code', 'name')->where('type', 6)->where('currency', $loan_account->currency)->where('name', 'Inc-Other Fees and Commissions from Loans and Leasing')->first();
            $data['accrued_int_arr'] = JournalDetail::join('journal_requiry', 'journal_requiry.id', '=', 'journal_id')
                ->where('journal_detail.coa_id', $loan_account->air_id)->get();

            $B0 = new Loan();
            $loan_repay = $B0->select(['loans.*',
                                    'clients.client_name',
                                    'client_loan_accounts.account_no'
                                ])
                ->join('client_loan_accounts', 'client_loan_accounts.id', '=', 'loans.loan_account_id')
                ->join('clients', 'clients.id', '=', 'loans.client_id');
            $loan_repay = $loan_repay->with([
                'payment' => function ($q) {
                    $q->select('id',
                                'loan_id',
                                'paid_principal',
                                'paid_interest',
                                'paid_fee',
                                'paid_other_fee',
                                'penalty_amount',
                                'repayment_owed',
                                'repayment_date',
                                'payment_month',
                                'status',
                                'condition_id'
                            )->orderBy('id', 'asc');
                },
                'client_loan_account' => function ($q) {
                        $q->select('id',
                                'account_name',
                                'account_no',
                                'air_id',
                                'coa_id',
                                'status');
                },
                'schedule' => function ($q) {
                        $q->select('loan_id',
                            'schedule_date',
                            'date_num',
                            'interest',
                            'principal',
                            'fee',
                            'other_fee',
                            'intraday_rate',
                            'no',
                            'type',
                            'loan_no'
                        );
                },
                'transaction' => function ($q) {
                    $q->where('trans_type', 'LIKE', '%Loan Repayment%');
                },
                'penalty_record' => function ($q) {
                    $q->select('id', 'loan_id', 'record_date', 'owed_penalty')->orderBy('id', 'DESC');
                }
            ])->whereIn('loans.status', [3, 8]);

            $loan_repay = $loan_repay->where('loans.id', $loan_id)->first();

 
            $data['sch_repay_arr'] = get_auto_repay_array_new($loan_repay, $dpDateClone);
            // dd($loan_repay->payment);
            $get_restructure_balance = get_restructure_balance($loan_repay);
            $sum_principal = $get_restructure_balance['balance_loan'];
            $data['interest'] = get_total_int_till_today_restructure($loan ,$loan->client_loan_account, $dpDateClone,$sum_principal)['interest'];
            $data['interest_day'] = get_total_int_till_today_restructure($loan ,$loan->client_loan_account, $dpDateClone,$sum_principal)['days'];

            // Templary fix interest will remove it back
            // $sch_repay_arr = get_auto_repay_array_downpayment($loan, $dpDateClone);
            // $t_sch_int=0;
            // foreach ($sch_repay_arr as $key => $sch_repay_arrx) {
    		//     foreach($sch_repay_arrx as $ar){
            //         $t_sch_int += $ar['interest'];

            //     }

            // }
            // $data['interest']=$t_sch_int;
            // End



            // Accr.Interest (20-11-2024) new enhancement 
            $data['interest_accruled']=0;
            $data['is_accrual_interest'] = $is_accrual_interest = Request::has('is_accrual_interest') ? Request::input('is_accrual_interest') : 0;
            if($data['is_accrual_interest']>0){

                if(!empty($loan_repay->schedule) && count($loan_repay->schedule) > 0){
                    $r=$this->findObjectByElement($loan_repay->schedule->max('loan_no'),$loan_repay->schedule);
                    if(!empty($r)){

                        $schedule_date=$r->schedule_date;
                        $accul_date=date('Y-m-d',$r->schedule_date);
                        $current_date=new DateTime();
            
                        $maturity_date = new DateTime($schedule_date);
                        if ( $current_date> $maturity_date) {
                            $dpDateClone=$r->schedule_date;
                        }                                                   
                        
                    }
                }
                $accrualDate = !is_null(Request::input('accrualDate')) ? Request::input('accrualDate') : null;
                if($accrualDate){
                    $dpDateClone=$accrualDate;
                }
                $data['interest_accrul'] = get_total_int_till_today_restructure($loan ,$loan->client_loan_account, $dpDateClone,$sum_principal)['interest'];
                $data['interest_accruled'] = floatval($data['interest']) -floatval($data['interest_accrul']);
                $data['interest']=$data['interest_accrul'];
            }
             
            // End

            return $this->view('loans.payoff', $data);
        }
        return redirect()->back();
    }

    private function findObjectByElement($payment_month,$array){

        foreach ( $array as $element ) {
            if ( $payment_month == $element->loan_no ) {
                return $element;
            }
        }
    }

    public function postPayOff($loan_id = NULL)
    {
        
        $data = Request::except(['_token', 'draft_id']);
        $rules = [
            'dpDate' => 'required|date',
            'ipInterest' => 'required|numeric'
        ];
        $attribs = [
            'dpDate' => 'Payoff Date',
            'ipInterest' => 'Interest Rate'
        ];
        $validator = Validator::make($data, $rules);
        $validator->setAttributeNames($attribs);
    
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        } else {

            $payoff_loan_count = LoanPayOff::where('loan_id', '=', $loan_id)->count();
            $loan = Loan::with('client_loan_account')->find($loan_id);
            $branch_code = CompanyBranch::find($loan->company_branch_id)->branch_code;
            $drawdown_acc = DrawdownAccounts::select('id', 'balance', 'coa_id', 'client_id', 'currency')->with('coa')->with('currency_tbl')->where('client_id', '=', $loan->client_id)->where('account_no', '=', $loan->drawdown_acc)->first();
            $total_payoff_amount = Request::input('iPrincipal') + Request::input('iInterest') + Request::input('iPenalty') + Request::input('ipPayOffFee') + Request::input('downpayment');
            if (Request::has('inputTotalPayOff')) {
                $total_payoff_amount=Request::input('inputTotalPayOff');
            }
            
            if (round($drawdown_acc->balance, 2) < round($total_payoff_amount, 2)) {
                $balance = $drawdown_acc->currency_tbl->symbol . number_format($drawdown_acc->balance, 2, '.', ',');
                Session::flash('danger', 'Balance in Drawdown Account is not sufficient! Current Drawdown Account balance is ' . $balance);
                return redirect()->back();
            }
            $journal_arr = [];
            // var_dump($payoff_loan_count);

            if ($payoff_loan_count <= 0) {
                $po = new LoanPayOff;
                $po->loan_id = $loan_id;
                $po->payoff_date = Request::input('dpDate');
                $po->principal = Request::input('iPrincipal');
                $po->interest = Request::input('iInterest');
                $po->penalty = Request::input('iPenalty');
                $po->payoff_fee = Request::input('ipPayOffFee');
                $po->note = Request::input('ipNote');
                $po->user_id = Auth::user()->id;
                $po->ch_accrual_interest = Request::input('ch_accrual_interest');
                $po->accrual_interest_amount = Request::input('accrual_interest_amount');
                if ($po->save()) {
                    $loan = Loan::where('id', '=', $loan_id)
                    ->with(['payment' => function ($query) {
                        $query->select('loan_id', 'condition_id', 'payment_month', 'paid_principal', 'paid_interest', 'repayment_owed')->take(1)->orderBy('payment_month', 'DESC');
                    },
                        'schedule' => function ($query) {
                            $query->orderBy('schedule_date', 'asc');
                        }])->first();
                    $loan->settlement_date = date('Y-m-d', strtotime(Request::input('dpDate')));
                    $loan->status = 9;
                    if ($loan->save()) {

                        //update draft
                        $this->userActivity($po->user_id, $loan_id, 6, 'Payoff Loan');
                        $last_transaction = TransactionsRequiry::select('id', 'balance')->where('loan_id', '=', $loan_id)
                            ->orderBy('id', 'DESC')->first();
                        $transaction = new TransactionsRequiry();
                        $transaction->loan_id = $loan_id;
                        $transaction->trans_date = $po->payoff_date;
                        $transaction->trans_type = "Pay-Off";
                        $total_amount = $po->penalty + $po->interest + $po->principal + $po->payoff_fee;
                        $transaction->amount = $total_amount;
                        $transaction->principal = $po->principal;
                        $transaction->interest = $po->interest;
                        $transaction->penalty = $po->penalty;
                        $transaction->fee = $po->payoff_fee;
                        $transaction->description = Request::input('ipNote');
                        $transaction->balance = $last_transaction->balance - $po->principal;
                        $transaction->downpayment = Request::input('downpayment');
                        $transaction->user_id = $po->user_id;
                        $transaction->is_audit = 1;

                        if (Request::has('ch_waive_panalty') && Request::input('ch_waive_panalty') == 1) {
                            if(Request::hasFile('waive_panalty_receipt')) {
                                if (Request::file('waive_panalty_receipt')->isValid()) {
                                    $is_file = true;
                                    $file = Request::file('waive_panalty_receipt');
                                    $ext = $file->getClientOriginalExtension();
                                    if (in_array(strtolower($ext), ['jpg', 'jpeg', 'bmp', 'png'])) {
                                        $image = Image::make($file);
                                        $photo_name = uniqid(date('dmY')) . '.jpg';
                                        $image->save(public_path('data/loans/documents') . '/' . $photo_name);
                                        $transaction->waive_panalty_receipt = $photo_name;
                                    } else {
                                        $fileName = uniqid(date('dmY')) . '.' . $ext;
                                        $file->move(public_path('data/loans/documents'), $fileName);
                                        $transaction->waive_panalty_receipt = $fileName;
                                    }
                                }
                            }
                        }


                        if ($transaction->save()) {
                            $transaction_id = $transaction->id;

                            $description = Request::input('ipNote');
                            $journal = new JournalRequiry;
                            $journal->tran_id = $transaction_id;
                            $journal->entry_date = (Request::has('dpDate')) ? date('Y-m-d H:i:s', strtotime(Request::input('dpDate'))) : date('Y-m-d H:i:s');
                            $journal->description = $description;
                            $journal->user_id = $po->user_id;
                            $journal->is_audit = 1;
                            if ($journal->saveWithGeneratedInvoice()) {

                                $tran = TransactionsRequiry::where('id', '=', $transaction_id)->where('flag', '=', 0)->first();
                                if (!empty($tran)) {
                                    $tran->flag = 1;
                                    $tran->invoice_number = str_pad($journal->id, 8, '0', STR_PAD_LEFT);
                                    $tran->save();
                                }
                                // Deduct AIR
                                $air_deduct_amount = floatval(Request::input('iAIR_deduct'));
                                $abs_air_deduct_amount = abs($air_deduct_amount);
                                // in case of NPL
                                if ($loan->client_loan_account->status > GENERAL_LC_STATUS) {
                                    // deduct air
                                    if ($air_deduct_amount > 0) {
                                        array_push($journal_arr, [
                                                                $loan->client_loan_account->sus_id,
                                                                $abs_air_deduct_amount,
                                                                $description,
                                                                $loan->client_loan_account->air_id,
                                                                $abs_air_deduct_amount,
                                                                $description,
                                                                $description
                                                            ]
                                                );
                                    } else {
                                        array_push($journal_arr, [
                                                                $loan->client_loan_account->air_id,
                                                                $abs_air_deduct_amount,
                                                                $description,
                                                                $loan->client_loan_account->sus_id,
                                                                $abs_air_deduct_amount,
                                                                $description,
                                                                $description
                                                            ]
                                                    );
                                    }
                                } else {
                                    // deduct air
                                    if ($air_deduct_amount > 0) {
                                        array_push($journal_arr, [
                                                                $loan->client_loan_account->int_inc_id,
                                                                $abs_air_deduct_amount,
                                                                $description,
                                                                $loan->client_loan_account->air_id,
                                                                $abs_air_deduct_amount,
                                                                $description,
                                                                $description
                                                            ]
                                                    );
                                    } else {
                                        array_push($journal_arr, [
                                                                $loan->client_loan_account->air_id,
                                                                $abs_air_deduct_amount,
                                                                $description,
                                                                $loan->client_loan_account->int_inc_id,
                                                                $abs_air_deduct_amount,
                                                                $description,
                                                                $description
                                                            ]
                                                    );
                                    }
                                }
                                for ($m = 0; $m < count(Request::input('debit')); $m++) {

                                    for ($k = 0; $k < 2; $k++) { // 0 = debit, 1 = credit
                                        $parent_debit = Request::input('parent_debit')[$m];
                                        $parent_credit = Request::input('parent_credit')[$m];
                                        $debit = Request::input('debit')[$m];
                                        $credit = Request::input('credit')[$m];
                                        $d_description = Request::input('d_description')[$m];
                                        $c_description = Request::input('c_description')[$m];
                                        if (($debit + $credit) == 0) continue;
                                        $jd = new JournalDetail;
                                        $jd->journal_id = $journal->id;
                                        $jd->coa_id = $k == 0 ? $parent_debit : $parent_credit;
                                        $jd->reference = $loan->contract_id;
                                        $jd->branch_code = $branch_code;

                                        $prev_bl = array_fill(0, 2, 0.0);
                                        $prev_row = JournalDetail::select('b_debit', 'b_credit')
                                            ->where('coa_id', $jd->coa_id)
                                            ->orderBy('id', 'desc')
                                            ->first();
                                        if (!empty($prev_row)) {
                                            $prev_bl[0] = $prev_row->b_debit;
                                            $prev_bl[1] = $prev_row->b_credit;
                                        }
                                        $jd->p_debit = $prev_bl[0];
                                        $jd->p_credit = $prev_bl[1];
                                        $jd->debit = $k == 0 ? $debit : 0;
                                        $jd->credit = $k == 1 ? $credit : 0;
                                        $jd->b_debit = floatval($prev_bl[0]) + floatval($jd->debit);
                                        $jd->b_credit = floatval($prev_bl[1]) + floatval($jd->credit);
                                        $jd->description = $k == 0 ? $d_description : $c_description;
                                        if ($jd->description == "") $jd->description = $description;
                                        $jd->is_audit = 1;
                                        $jd->save();

                                    }
                                    // in case of NPL
                                    if ($loan->client_loan_account->status > GENERAL_LC_STATUS) {
                                        // int-sus to int-inc
                                        if ($m == 1 && floatval(Request::input('debit')[1]) > 0) {
                                            array_push($journal_arr, [$loan->client_loan_account->sus_id, floatval(Request::input('debit')[1]), $description,
                                                $loan->client_loan_account->int_inc_id, floatval(Request::input('debit')[1]), $description,
                                                $description]);
                                        }
                                    }
                                    //provision
                                    if ($m == 0 && floatval(Request::input('debit')[0]) > 0) {
                                        // general or specific
                                        // reverse provision
                                        $prov_arr_rev = $prov_arr = [];
                                        $prov_arr_rev = get_journal_provision($loan->client_loan_account, $loan->client_loan_account->balance, $loan->client_loan_account->status, GENERAL_LC_STATUS, "up");
                                        if ($prov_arr_rev[0] != []){
                                            array_push($journal_arr, $prov_arr_rev[0]);
                                        } 
                                        // do provision
                                        if (round($loan->client_loan_account->balance - floatval(Request::input('debit')[0]), 2) > 0) {
                                            $prov_arr = get_journal_provision($loan->client_loan_account, $loan->client_loan_account->balance - $debit, GENERAL_LC_STATUS, $loan->client_loan_account->status, "down");
                                            if ($prov_arr[0] != []) array_push($journal_arr, $prov_arr[0]);
                                        }
                                    }
                                }
                                record_journal_no_trans($loan->client_loan_account, $journal->entry_date, $journal_arr, $loan->company_branch_id, $po->user_id, 1, $journal->id);

                            } else {
                                $po->delete();
                                $transaction->delete();
                                return redirect('/');
                            }
                            // Update Loan Account Balance
                            $loan_account = $loan->client_loan_account;
                            $loan_account->balance -= Request::input('iPrincipal');
                            // NOTE: a cents-level rounding residual (e.g. -0.07) may remain and is
                            // shown as-is. It is NOT auto-clamped — finance clears it explicitly
                            // with the "Clear" button on the loan detail page, which records the
                            // adjustment in the R15 Clear Balance report.
                            if (round($loan_account->balance * 100) / 100 == 0.00) {
                                $loan_account->status = 8; // completed
                            }
                            $loan_account->save();
                            // Update Drawdown Acc. Balance
                            $drawdown_acc->balance -= $total_payoff_amount;
                            $drawdown_acc->save();
                            //END $m



                        // Add payment when pay-off 01-05-2024

                        $month_index = (!empty($loan->payment) && count($loan->payment) == 1) ? $loan->payment[0]->payment_month + 1 : 1;


                        $loan_payment = new LoanPayments();

                        $loan_payment->user_id =  Auth::user()->id;

                        $loan_payment->loan_id = $loan_id;
                        $loan_payment->invoice_number = Request::input('invoice_number');
                        $loan_payment->repayment_date = Request::input('dpDate');
                        $loan_payment->payment_month = $month_index;
                        
                        $loan_payment->paid_principal = Request::input('iPrincipal');
                        $loan_payment->paid_interest = Request::input('iInterest');
                        $loan_payment->penalty_amount = Request::input('iPenalty');
                        $waived = Request::input('ch_waive_panalty', 0);
                        $loan_payment->waived_penalty = ($waived > 0) ? Request::input('waive_panalty_amount', 0) : 0;
                        $loan_payment->repayment_owed = 0;
                        $loan_payment->payment_type = 6;
                        $loan_payment->condition_id = 0;
                        $loan_payment->reason = 'Auto Pay-Off';
                        $loan_payment->action_taken = null;
                        $loan_payment->todo_payment = Request::input('inputTotalPayOff');

                        $loan_payment->note = 'Auto Pay-Off';

                        $loan_payment->save();
                        
                        // End

                        
                            return redirect()->route('loan_detail', [$loan_id]);

                        } else {
                            $po->delete();
                            return redirect('/');
                        }

                    }
                } else {
                    $po->delete();
                }
            }

        }
    }

    public function get_penalty()
    {
        $data = Request::all();
        $loan = Loan::where('id', $data['id'])->first();
        return LoanCalculate::getTotalPenalty($loan, $data['date']);
    }

    public function getRepayment($loan_id = 0)
    {

        if ($loan_id > 0) {
            $loan = Loan::select([
                'id', 'loan_amount', 'interest_rate', 'start_date',
                'loan_duration', 'repayment_type', 'days_of_month',
                'balloon', 'balloon_month', 'balloon_amount_array',
                'monthly_payment', 'custom_flag', 'days_of_month',
                'penalty_rate_type', 'penalty_rate1', 'penalty_rate2',
                'penalty_period1', 'penalty_period2', 'holiday_flag', 'status'])
                ->with(['payment' => function ($query) {
                    $query->select('loan_id', 'condition_id', 'payment_month', 'paid_principal', 'paid_interest', 'repayment_owed')->take(1)->orderBy('payment_month', 'DESC');
                },
                    'schedule' => function ($query) {
                        $query->orderBy('schedule_date', 'asc');
                    }])
                ->where('id', '=', $loan_id)
                ->whereIn('status', [3, 8])
                ->first();
            if (empty($loan)) {
                return redirect('/');
            }
            $repayment_owed = LoanPayments::select('payment_month', 'condition_id', 'paid_principal', 'paid_interest', 'repayment_owed', 'status')
                ->where('loan_id', '=', $loan_id)
                ->where(function ($query) {
                    $query->where('status', '=', 2)
                        ->orwhere('condition_id', '=', 2);
                })
                ->orderBy('payment_month', 'ASC')
                ->orderBy('id', 'ASC')->get();
            return $this->view('loans.repayment', ['loan' => $loan, 'repayment_owed_tb' => $repayment_owed]);
        }
        return redirect()->back();
    }

    public function postRepayment($loan_id = NULL)
    {
        $data = Request::except(['_token']);
        $rules = [
            'repayment_date' => 'required|date',
            'payment_month' => 'required|numeric',
            'paid_principal' => 'required|numeric'
        ];
        $attribs = [
            'repayment_date' => 'Repayment Date',
            'payment_month' => 'Payment Month',
            'paid_principal' => 'Paid Principal'
        ];
        $validator = Validator::make($data, $rules);
        $validator->setAttributeNames($attribs);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        } else {

            DB::beginTransaction();
            try {
                $loan = Loan::select('id', 'last_schedule_date')->where('id', '=', $loan_id)->first();


                if (!empty($loan)) {
                    $loan_payment = new LoanPayments();
                    if (Auth::check()) {
                        $id = Auth::user()->id;
                        $loan_payment->user_id = $id;
                    } else {
                        return redirect()->route('login');
                    }
                    $loan_payment->loan_id = $loan_id;
                    $loan_payment->invoice_number = Request::input('invoice_number');
                    $loan_payment->repayment_date = Request::input('repayment_date');
                    $loan_payment->payment_month = Request::input('payment_month');
                    $loan_payment->paid_principal = Request::input('paid_principal');
                    $loan_payment->paid_interest = Request::input('paid_interest');
                    $loan_payment->penalty_amount = Request::input('penalty_amount');
                    $waived = Request::input('waived-penalty', 0);
                    $loan_payment->waived_penalty = ($waived > 0) ? Request::input('waived_penalty', 0) : 0;
                    $owed_amount = Request::input('repayment_owed', 0);
                    $loan_payment->repayment_owed = (intval($owed_amount * 100) > 0) ? $owed_amount : 0;
                    $loan_payment->payment_type = Request::input('payment_type');
                    $loan_payment->condition_id = (intval($owed_amount * 100) > 0) ? Request::input('condition') : 0;
                    $loan_payment->reason = Request::input('reason');
                    $loan_payment->action_taken = Request::input('action_taken');
                    $loan_payment->todo_payment = Request::input('todo_payment');
                    $check_payment = LoanPayments::select('id')
                        ->wherePaymentMonth($loan_payment->payment_month)
                        ->where('loan_id', '=', $loan_id)
                        ->first();
                    if (empty($check_payment)) {
                        if (Request::hasFile('repayment_receipt')) {
                            if (Request::file('repayment_receipt')->isValid()) {
                                $file = Request::file('repayment_receipt');
                                $ext = $file->getClientOriginalExtension();
                                if (in_array(strtolower($ext), ['jpg', 'jpeg', 'bmp', 'png'])) {

                                    $image = Image::make($file);
                                    $photo_name = uniqid(date('dmY')) . '.jpg';
                                    $image->save(public_path('data/loans/receipts') . '/' . $photo_name);
                                    $loan_payment->repayment_receipt = $photo_name;
                                } else {
                                    $fileName = uniqid(date('dmY')) . '.' . $ext;
                                    $file->move(public_path('data/loans/receipts'), $fileName);
                                    $loan_payment->repayment_receipt = $fileName;
                                }
                            }
                        }
                        $loan_payment->note = Request::input('note');
                        $loan_payment->status = (intval($owed_amount * 100) > 0) ? 0 : 1; /* 0: Owed, 1: completed */
                        $loan_payment->late_day = Request::input('late_day');
                        if ($loan_payment->late_day < 0) $loan_payment->late_day = 0;
                        $loan_payment->parc_level = Request::input('parc_level');
                        if ($loan_payment->save()) {
                            $loan->last_schedule_date = Request::input('last_schedule_date');

                            $repayment_owed = LoanPayments::select('id', 'status')
                                ->where('loan_id', '=', $loan_id)
                                ->where('status', '=', 0)
                                ->where('condition_id', '=', 2)
                                ->where('payment_month', $loan_payment->payment_month - 1)->latest('id')->first();
                            if (!empty($repayment_owed)) {
                                $repayment_owed->status = 1; /* old next month payment to completed status */
                                if ($repayment_owed->save()) {
                                    $last_transaction = TransactionsRequiry::select('id', 'balance')->where('loan_id', '=', $loan_id)
                                        ->orderBy('id', 'DESC')->first();
                                    $transaction = new TransactionsRequiry();
                                    $transaction->loan_id = $loan_id;
                                    $transaction->invoice_number = Request::input('invoice_number');
                                    $transaction->trans_date = $loan_payment->repayment_date;
                                    $transaction->trans_type = "Loan Repayment";
                                    $transaction->description = $loan_payment->note;
                                    $transaction->amount = Request::input('transaction_amount');
                                    $transaction->penalty = $loan_payment->penalty_amount;
                                    $transaction->principal = $loan_payment->paid_principal;
                                    $transaction->interest = $loan_payment->paid_interest;
                                    $transaction->balance = $last_transaction->balance - $loan_payment->paid_principal;
                                    $transaction->user_id = $loan_payment->user_id;
                                    if (!$transaction->save()) {
                                        $loan_payment->delete();
                                    } else {
                                        if (0 == number_format(($transaction->balance), 2)) {
                                            $loan->status = 10;
                                            $loan->settlement_date = $loan_payment->repayment_date;
                                        }
                                        $loan->save();
                                        $this->userActivity($loan_payment->user_id, $loan_id, 6, 'Make repayment');
                                    }
                                    return redirect()->route('loan_detail', [$loan_id]);
                                } else {
                                    $loan_payment->delete();
                                }
                            } else {
                                $last_transaction = TransactionsRequiry::select('id', 'balance')->where('loan_id', '=', $loan_id)
                                    ->orderBy('id', 'DESC')->first();
                                $transaction = new TransactionsRequiry();
                                $transaction->loan_id = $loan_id;
                                $transaction->trans_date = $loan_payment->repayment_date;
                                $transaction->trans_type = "Loan Repayment";
                                $transaction->description = $loan_payment->note;
                                $transaction->amount = Request::input('transaction_amount');
                                $transaction->penalty = $loan_payment->penalty_amount;
                                $transaction->principal = $loan_payment->paid_principal;
                                $transaction->interest = $loan_payment->paid_interest;
                                $transaction->balance = $last_transaction->balance - $loan_payment->paid_principal;
                                $transaction->user_id = $loan_payment->user_id;
                                if (!$transaction->save()) {
                                    $loan_payment->delete();
                                } else {

                                    $Schedule_interest = array_sum($loan->schedule->lists('interest'));
                                    $Schedule_principal = array_sum($loan->schedule->lists('principal'));

                                    $Loan_Paid_interest = array_sum($loan->payment->lists('paid_interest'));
                                    $Loan_Paid_pricipal = array_sum($loan->payment->lists('paid_principal'));
//                                    "Bal-8000PI12598SI12966"
//                                    " Bal= 0 PI= 12644 SI= 12966"
//                                    " Bal= 0 PI= 12966 SI= 12966"
//                                    " Bal= 0 PI= 12966 SI= 12966 LPr= 24000S Prin= 24000"

                                    if (0 == intval(($transaction->balance) * 100)) {

                                        if ($Loan_Paid_interest >= $Schedule_interest && $Loan_Paid_pricipal >= $Schedule_principal) {

                                            $transaction->balance = 0;
                                            $loan->status = 10;
                                            $loan->settlement_date = $loan_payment->repayment_date;
                                            $loan->save();
                                        }
                                    }

                                    //dd(' Bal= '.$transaction->balance.' PI= '.$Loan_Paid_interest.' SI= '.$Schedule_interest.'LPr'.$Loan_Paid_pricipal.'SPrin'.$Schedule_principal);
                                    $this->userActivity($loan_payment->user_id, $loan_id, 6, 'Make repayment');
                                }
                                return redirect()->route('loan_detail', [$loan_id]);
                            }
                        }
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                //DB::rollBack();
                throw $e;
            }

            return redirect()->back();
        }
    }

    public function getRepaymentOwed($loan_id = 0, $payment_month = 0)
    {
        if ($loan_id > 0 && $payment_month > 0) {
            $loan = Loan::select([
                'id', 'loan_amount', 'interest_rate', 'start_date',
                'loan_duration', 'repayment_type', 'days_of_month',
                'balloon', 'balloon_month', 'balloon_amount_array',
                'monthly_payment', 'custom_flag', 'days_of_month',
                'penalty_rate_type', 'penalty_rate1', 'penalty_rate2',
                'penalty_period1', 'penalty_period2', 'holiday_flag', 'status'])
                ->with(['schedule' => function ($query) {
                    $query->orderBy('schedule_date', 'asc');
                }])->where('id', '=', $loan_id)->first();
            if (!empty($loan)) {
                $repayment_owed = LoanPayments::select('id', 'repayment_date', 'payment_month', 'paid_principal', 'paid_interest', 'condition_id', 'repayment_owed')
                    ->where('loan_id', '=', $loan_id)
                    ->where('status', '!=', 1)
                    ->where('condition_id', '!=', 0)
                    ->where('payment_month', '=', $payment_month)
                    ->orderBy('id', 'DESC')
                    ->get();
                if (!empty($repayment_owed)) {
                    return $this->view('loans.repayment_owed',
                        [
                            'loan' => $loan,
                            'repayment_owed_tb' => $repayment_owed
                        ]);
                }

            }
        }
        return redirect()->back();
    }

    public function postRepaymentOwed($loan_id = 0, $payment_month = 0)
    {
        $data = Request::except(['_token']);
        $rules = [
            'repayment_date' => 'required|date',
            'payment_month' => 'required|numeric',
            'paid_principal' => 'required|numeric'
        ];
        $attribs = [
            'repayment_date' => 'Repayment Date',
            'payment_month' => 'Payment Month',
            'paid_principal' => 'Paid Principal'
        ];
        $validator = Validator::make($data, $rules);
        $validator->setAttributeNames($attribs);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        } else {
            $loan = Loan::select('id')->where('id', '=', $loan_id)->first();
            if (!empty($loan)) {
                $loan_payment = new LoanPayments();
                if (Auth::check()) {
                    $id = Auth::user()->id;
                    $loan_payment->user_id = $id;
                } else {
                    return redirect()->route('login');
                }
                $repayment_owed = LoanPayments::select('id', 'status')
                    ->where('loan_id', '=', $loan_id)
                    ->where('status', '=', 0)
                    ->where('condition_id', '!=', 0)
                    ->where('payment_month', '=', $payment_month)
                    ->orderBy('id', 'DESC')
                    ->first();
                if (!empty($repayment_owed)) {
                    $loan_payment->loan_id = $loan_id;
                    $loan_payment->invoice_number = Request::input('invoice_number');
                    $loan_payment->repayment_date = Request::input('repayment_date');
                    $loan_payment->payment_month = Request::input('payment_month');
                    $loan_payment->paid_principal = Request::input('paid_principal');
                    $loan_payment->paid_interest = Request::input('paid_interest');
                    $loan_payment->penalty_amount = Request::input('penalty_amount');
                    $waived = Request::input('waived-penalty', 0);
                    $loan_payment->waived_penalty = ($waived > 0) ? Request::input('waived_penalty', 0) : 0;
                    $owed_amount = Request::input('repayment_owed', 0);
                    $loan_payment->repayment_owed = (intval($owed_amount * 100) > 0) ? $owed_amount : 0;
                    $loan_payment->payment_type = Request::input('payment_type');
                    $loan_payment->condition_id = (intval($owed_amount * 100) > 0) ? Request::input('condition') : 0;
                    $loan_payment->reason = Request::input('reason');
                    $loan_payment->action_taken = Request::input('action_taken');
                    $loan_payment->todo_payment = Request::input('todo_payment');
                    if (Request::hasFile('repayment_receipt')) {
                        if (Request::file('repayment_receipt')->isValid()) {
                            $file = Request::file('repayment_receipt');
                            $ext = $file->getClientOriginalExtension();
                            if (in_array(strtolower($ext), ['jpg', 'jpeg', 'bmp', 'png'])) {
                                $image = Image::make($file);
                                $photo_name = uniqid(date('dmY')) . '.jpg';
                                $image->save(public_path('data/loans/receipts') . '/' . $photo_name);
                                $loan_payment->repayment_receipt = $photo_name;
                            } else {
                                $fileName = uniqid(date('dmY')) . '.' . $ext;
                                $file->move(public_path('data/loans/receipts'), $fileName);
                                $loan_payment->repayment_receipt = $fileName;
                            }
                        }
                    }
                    $loan_payment->note = Request::input('note');
                    $loan_payment->status = (intval($owed_amount * 100) > 0) ? 0 : 1; /* 0: Owed, 1: completed 2: paid owed*/
                    $loan_payment->late_day = Request::input('late_day');
                    if ($loan_payment->late_day < 0) $loan_payment->late_day = 0;
                    $loan_payment->parc_level = Request::input('parc_level');

                    if ($loan_payment->save()) {
                        $repayment_owed->status = 2; /* old repayment month status */
                        if ($repayment_owed->save()) {
                            $last_transaction = TransactionsRequiry::select('id', 'balance')->where('loan_id', '=', $loan_id)
                                ->orderBy('id', 'DESC')->first();
                            $transaction = new TransactionsRequiry();
                            $transaction->loan_id = $loan_id;
                            $transaction->invoice_number = Request::input('invoice_number');
                            $transaction->trans_type = "Arrears Repayment";
                            $transaction->trans_date = $loan_payment->repayment_date;
                            $transaction->amount = Request::input('transaction_amount');
                            $transaction->principal = $loan_payment->paid_principal;
                            $transaction->interest = $loan_payment->paid_interest;
                            $transaction->penalty = $loan_payment->penalty_amount;
                            $transaction->balance = $last_transaction->balance - $loan_payment->paid_principal;
                            $transaction->description = $loan_payment->note;
                            $transaction->user_id = $loan_payment->user_id;
                            $transaction->save();
                            if (0 == intval(($transaction->balance) * 100)) {
                                $transaction->balance = 0;
                                $loan->status = 10;
                                $loan->save();
                            }
                            $this->userActivity($loan_payment->user_id, $loan_id, 6, 'Make repayment owed');
                            return redirect()->route('loan_detail', [$loan_id]);
                        } else {
                            $loan_payment->delete();
                        }
                    }
                }
            }
            return redirect()->back();
        }
    }

    public function getLoanlist()
    {
        if (config('unified_sales.loans_enabled')) {
            if (!Auth::check()) abort(401);
            $user=Auth::user();$branch=in_array((int)$user->role_id,array(1,2))?null:$user->branch_id;
            if ($branch===null && !in_array((int)$user->role_id,array(1,2))) abort(403);
            $filters=Request::all();$repo=\App\Services\UnifiedLoansRepository::configured();
            return $this->view('loans.unified_list',array('loans'=>$repo->listing($filters,$branch),'filters'=>$filters));
        }
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 15;
        $order = 'id';
        $order_dir = 'asc';
        $order_type = [
            'id' => 'loans.id',
            'contract_id' => 'contract_id',
            'amount' => 'loan_amount',
            'interest_rate' => 'interest_rate',
            'tenure' => 'loan_duration',
            'start_date' => 'start_date'
        ];

        $contractID = null;
        $approvalID = null;
        $name = null;
        $querystringArray = [];

        $query_arr = array('user_id' => Auth::user()->id, 'company_branch_id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);
        $B0 = new Loan();
        $B0 = $this->getUserByBranch($B0, 'company_branch_id', $query_arr);

        $loans = $B0->select(['loans.*', 'loan_approval.id as approval_id', 'loan_approval.approval_date'])
            ->selectRaw("DATE_FORMAT(start_date,'%d-%m-%Y') as date_start")
            ->leftJoin('loan_approval', 'loans.id', '=', 'loan_approval.loan_id')->leftJoin('clients', 'clients.id', '=', 'loans.client_id')
            ->with(['payment' => function ($query) {
                $query->select('id', 'loan_id', 'payment_month', 'repayment_date', 'paid_interest', 'paid_principal', 'condition_id',
                    'repayment_owed', 'status', 'note');
            }, 'schedule' => function ($query) {
                $query->orderBy('schedule_date', 'asc');
            }]);
        $order_class = [
            'contract_id' => ($order == 'contract_id') ? 'fa-sort-' . $order_dir : 'fa-sort',
            'amount' => ($order == 'amount') ? 'fa-sort-' . $order_dir : 'fa-sort',
            'interest_rate' => ($order == 'interest_rate') ? 'fa-sort-' . $order_dir : 'fa-sort',
            'tenure' => ($order == 'tenure') ? 'fa-sort-' . $order_dir : 'fa-sort',
            'start_date' => ($order == 'start_date') ? 'fa-sort-' . $order_dir : 'fa-sort'
        ];

        if (Request::has('contract_id')) {
            $approvalID = Request::input('approval_id');
            $contractID = Request::input('contract_id');
            $loans = $loans->where('contract_id', 'LIKE', '%' . $contractID . '%');
            $querystringArray['contract_id'] = $contractID;
        } elseif (Request::has('approval_id')) {
            $contractID = Request::input('contract_id');
            $approvalID = Request::input('approval_id');
            if (strpos($approvalID, 'C') !== false) {
                $subString = substr($approvalID, 1);
                $loan = ltrim($subString, '0');
            } else {
                $loan = ltrim($approvalID, '0');
            }
            $loans = $loans->where('loan_approval.id', '=', $loan);
            $querystringArray['approval_id'] = $loan;
        }
        if (Request::has('customer_name')) {
            $name = Request::input('customer_name');
            $loans = $loans->where('client_name', 'like', '%' . $name . '%');
            $querystringArray['client_name'] = $name;
        }
        if (Request::has('o')) {
            $order = Request::input('o');
            if (!array_key_exists($order, $order_type)) $order = 'id';
            $querystringArray['o'] = $order;
        }
        if (Request::has('od')) {
            $order_dir = Request::input('od');
            if ($order_dir != 'desc' && $order_dir != 'asc') $order_dir = 'desc';
            $querystringArray['od'] = $order;
        }
        $loans = $loans->whereIn('loans.status', [3, 8])->orderBy($order_type[$order], $order_dir)->paginate($offset);
        $url = url('loans/list');
        $order_url = [
            'contract_id' => ($order == 'contract_id' && $order_dir == 'asc') ? $this->setUrl($url, 'o=contract_id&od=desc') : $this->setUrl($url, 'o=contract_id&od=asc'),
            'amount' => ($order == 'amount' && $order_dir == 'asc') ? $this->setUrl($url, 'o=amount&od=desc') : $this->setUrl($url, 'o=amount&od=asc'),
            'interest_rate' => ($order == 'interest_rate' && $order_dir == 'asc') ? $this->setUrl($url, 'o=interest_rate&od=desc') : $this->setUrl($url, 'o=interest_rate&od=asc'),
            'tenure' => ($order == 'tenure' && $order_dir == 'asc') ? $this->setUrl($url, 'o=tenure&od=desc') : $this->setUrl($url, 'o=tenure&od=asc'),
            'start_date' => ($order == 'start_date' && $order_dir == 'desc') ? $this->setUrl($url, 'o=start_date&od=asc') : $this->setUrl($url, 'o=start_date&od=desc')
        ];
        //dd(['contract_id' => $contractID, 'approval_id' => $approvalID, 'loans' => $loans, 'order_class' => $order_class, 'order_url' => $order_url, 'client_name' => $name, 'offset' => $offset]);
        return $this->view('loans.list', ['contract_id' => $contractID, 'approval_id' => $approvalID, 'loans' => $loans, 'order_class' => $order_class, 'order_url' => $order_url, 'client_name' => $name, 'offset' => $offset]);
    }

    public function loan_detail($id = 0)
    {
        if (config('unified_sales.loans_enabled')) {
            if (!Auth::check()) abort(401);
            $user=Auth::user();$branch=in_array((int)$user->role_id,array(1,2))?null:$user->branch_id;
            if ($branch===null && !in_array((int)$user->role_id,array(1,2))) abort(403);
            $data=\App\Services\UnifiedLoansRepository::configured()->detail($id,$branch,Request::only(array('schedule_page','payment_page')));if(!$data)abort(404);
            return $this->view('loans.unified_detail',$data);
        }
        if (is_numeric($id) && $id > 0) {
            $loan = Loan::select(['loans.*', 'loan_approval.id as approval_id', 'loan_approval.approval_date'])
                ->leftJoin('loan_approval', 'loans.id', '=', 'loan_approval.loan_id')
                ->with(['co_user' => function ($query) {
                    $query->select('id', 'phone', 'name');
                }, 'client', 'branch', 'client_loan_account' => function ($q) {
                    $q->select('id', 'account_no', 'balance','balance_downpayment', 'currency');
                },'unittypes','projects','sale_persons','PaymentOptions'])
                ->where('loans.id', '=', $id)->first();
            $product_type = Product_type::select('id', 'code', 'products_type_name')->get();
            $product_type_arr = [];
            foreach ($product_type as $pt) {
                $product_type_arr[$pt->id] = $pt->products_type_name;
            }
            $audit = Audit::where('tbl', 'loans')->where('tbl_id', $id)->orderBy('id', 'asc')->with(['audit1', 'audit2'])->get();
            $schedule_id = RepaymentSchedule::where('loan_id', $id)->first()->id;
            $loan_history = LoanHistory::where('loan_id', $id)->first();
            $company_branch = CompanyBranch::find($loan->company_branch_id);

            // Balance transfers touching this loan. tb_transfer_balance only stores the two drawdown
            // account numbers, so the loan is matched on its own drawdown_acc: it is the sender when the
            // number appears as from_account_no, the receiver when it appears as to_account_no. The unit
            // on the other side is resolved through tb_drawdown_account for display.
            $transfers = [];
            if (!empty($loan->drawdown_acc)) {
                $transfers = \DB::select(
                    "SELECT tb.id, tb.created_at, tb.status,
                            tb.total_principal_paid, tb.total_interest_paid,
                            CASE WHEN tb.from_account_no = ? THEN 'out' ELSE 'in' END AS direction,
                            CASE WHEN tb.from_account_no = ? THEN tb.to_account_no ELSE tb.from_account_no END AS other_account_no,
                            CASE WHEN tb.from_account_no = ? THEN tu.code ELSE fu.code END AS other_unit_code,
                            CASE WHEN tb.from_account_no = ? THEN tp.short_code ELSE fp.short_code END AS other_project_code,
                            CASE WHEN tb.from_account_no = ? THEN tl.id ELSE fl.id END AS other_loan_id
                       FROM tb_transfer_balance tb
                       LEFT JOIN tb_drawdown_account fd ON fd.account_no = tb.from_account_no
                       LEFT JOIN tb_drawdown_account td ON td.account_no = tb.to_account_no
                       LEFT JOIN tb_units fu ON fu.id = fd.unit_id
                       LEFT JOIN tb_units tu ON tu.id = td.unit_id
                       LEFT JOIN tb_projects fp ON fp.id = fd.project_id
                       LEFT JOIN tb_projects tp ON tp.id = td.project_id
                       LEFT JOIN tb_loans fl ON fl.drawdown_acc = tb.from_account_no
                       LEFT JOIN tb_loans tl ON tl.drawdown_acc = tb.to_account_no
                      WHERE tb.from_account_no = ? OR tb.to_account_no = ?
                      ORDER BY tb.created_at DESC, tb.id DESC",
                    array_fill(0, 7, $loan->drawdown_acc)
                );
            }

            return $this->view('loans.detail', ['loan' => $loan, 'audit' => $audit, 'schedule_id' => $schedule_id, 'product_type_arr' => $product_type_arr,'loan_history'=>$loan_history,'company_branch'=>$company_branch,'transfers'=>$transfers]);
        }
        return $this->view('loans.detail', ['loan' => null]);
    }

    // Clear a cents-level rounding residual on the loan account's outstanding
    // balance (strictly between -1 and +1 USD, e.g. -0.07 left by a pay-off's
    // schedule-rounded principal vs the raw float balance). The adjustment is
    // recorded in tb_clear_balance and reported on R15 (Clear Balance Report).
    public function clearBalance($id = 0)
    {
        if (is_numeric($id) && $id > 0) {
            $loan = Loan::with('client_loan_account')->where('id', '=', $id)->first();
            if (!empty($loan) && !empty($loan->client_loan_account)) {
                $loan_account = $loan->client_loan_account;
                $balance = floatval($loan_account->balance);
                if ($balance != 0 && abs($balance) < 1.00) {
                    DB::beginTransaction();
                    try {
                        DB::insert(
                            'INSERT INTO tb_clear_balance (loan_id, loan_account_id, amount, balance_before, cleared_by, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                            [$loan->id, $loan_account->id, $balance, $balance, Auth::user()->id, date('Y-m-d H:i:s')]
                        );
                        $loan_account->balance = 0;
                        $loan_account->status = '8'; // completed (balance settled at exactly 0.00)
                        $loan_account->save();
                        $this->userActivity(Auth::user()->id, $loan->id, 0, 'Clear Outstanding Balance Residual', Request::fullUrl());
                        DB::commit();
                        Session::flash('msg', 'Outstanding balance residual of ' . number_format($balance, 2) . ' cleared to 0.00 (recorded in R15).');
                    } catch (Exception $e) {
                        DB::rollback();
                        Session::flash('msg', 'Clear balance failed.');
                    }
                } else {
                    Session::flash('msg', 'Balance is not a clearable residual (must be between -1.00 and 1.00 and not zero).');
                }
            }
        }
        return redirect()->route('loan_detail', [$id]);
    }

    public function loan_account($loan_account_id = 0)
    {

        $loan_accounts = ClientLoanAccounts::select('client_loan_accounts.*', 'company_branch.*', 'client_loan_accounts.status', 'client_loan_accounts.id')
            ->join('company_branch', 'client_loan_accounts.branch', '=', 'company_branch.branch_code')
            ->with(['journal_detail_coa' => function ($q) {
                $q->select('coa_id', DB::raw('sum(debit) AS sum_debit, sum(credit) AS sum_credit'));
            }])
            ->with(['journal_detail_air' => function ($q) {
                $q->select('coa_id', DB::raw('sum(debit) AS sum_debit, sum(credit) AS sum_credit'));
            }])
            ->with(['journal_detail_int_inc' => function ($q) {
                $q->select('coa_id', DB::raw('sum(debit) AS sum_debit, sum(credit) AS sum_credit'));
            }])
            ->with(['journal_detail_sus' => function ($q) {
                $q->select('coa_id', DB::raw('sum(debit) AS sum_debit, sum(credit) AS sum_credit'));
            }])
            ->find($loan_account_id);


        //select('client_loan_accounts.*', 'company_branch.*', 'client_loan_accounts.status', 'client_loan_accounts.id')

        //$cate = CoaCategory::where('id',$loan_accounts->coa_id)->orWhere('id',$loan_accounts->air_id)->get();

        $cate = CoaCategory::whereIn('id', [$loan_accounts->coa_id, $loan_accounts->air_id, $loan_accounts->int_inc_id, $loan_accounts->sus_id, $loan_accounts->ap_id])->get();

        $audit = Audit::where('tbl', 'client_loan_accounts')->where('tbl_id', $loan_account_id)->orderBy('id', 'asc')->with(['audit1', 'audit2'])->get();

        $data['loan_accounts'] = $loan_accounts;
        $data['audit'] = $audit;
        $data['created_by'] = User::find($loan_accounts->created_by)->name;
        $data['cate'] = $cate;
        $data['currency_tbl'] = Currency::select('id', 'code')->get();
        return $this->view('loans.detail_account', $data);
    }

    public function getGuarantor($id = 0)
    {
        return $this->view('loans.add_guarantor', ['id' => $id]);
    }

    public function postGuarantor($id = 0)
    {
        if (is_numeric($id) && $id > 0) {
            $loan = Loan::select('id')->where('id', '=', $id)->first();
            $user_id = Auth::user()->id;
            if (!empty($loan)) {
                $inputs = Request::except(['_token', 'photo', 'signature', 'collateral_photo',
                    'collateral_type', 'collateral_no', 'collateral_value', 'birth_date',
                    'card_date', 'card_expired_date', 'expired_date', 'collateral_registration',
                    'collateral_address', 'note']);
                if (!empty($inputs)) {
                    $guarantor = new Guarantor();
                    $guarantor->loan_id = $id;
                    $guarantor->user_id = $user_id;
                    foreach ($inputs as $key => $value) {
                        $guarantor->$key = $value;
                    }

                    if (Request::hasFile('photo')) {
                        if (Request::file('photo')->isValid()) {
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
                            $image->save(public_path('data/guarantors') . '/' . $photo_name);
                            $guarantor->photo = $photo_name;
                        }
                    }
                    if (Request::hasFile('signature')) {
                        if (Request::file('signature')->isValid()) {
                            $file = Request::file('signature');
                            $image = Image::make($file);
                            $photo_name = uniqid(date('dmY')) . '.jpg';
                            $image->save(public_path('data/guarantors_signature') . '/' . $photo_name);
                            $guarantor->signature = $photo_name;
                        }
                    }
                    if (Request::has('birth_date')) {
                        $existDB = Request::input('birth_date');
                        $guarantor->birth_date = $existDB;
                    }
                    if (Request::has('card_date')) {
                        $existDB = Request::input('card_date');
                        $guarantor->card_date = $existDB;
                    }
                    if (Request::has('card_expired_date')) {
                        $existDB = Request::input('card_expired_date');
                        $guarantor->card_expired_date = $existDB;
                    }
                    if (Request::has('expired_date')) {
                        $existDB = Request::input('expired_date');
                        $guarantor->expired_date = $existDB;
                    }
                    if ($guarantor->save()) {
                        $this->userActivity($user_id, $id, 6, 'Create guarantor', 'Guarantor id=' . $guarantor->id);
//                        $inputsCol = Request::only(['collateral_no','collateral_type','collateral_value','collateral_registration','collateral_address','note']);
//                        if(!empty($inputsCol) && count($inputsCol) > 0){
//                            $count = count($inputsCol['collateral_no']);
//                            for($i = 0; $i < $count ; $i++){
//                                $guarantorCollateral = new GuarantorCollateral();
//                                $guarantorCollateral->guarantor_id = $guarantor->id;
//                                foreach ($inputsCol as $key => $value) {
//                                    $guarantorCollateral->$key = $value[$i];
//                                }
//                                if(Request::hasFile('collateral_photo')){
//                                    $files = Request::file('collateral_photo');
//                                    if(count($files)<= $count && $files[$i]->isValid()){
//                                        $image = Image::make($files[$i]);
//                                        $photo_name = uniqid(date('dmY')).'.jpg';
//                                        $image->save(public_path('data/guarantors_collateral').'/'.$photo_name);
//                                        $guarantorCollateral->collateral_photo = $photo_name;
//                                    }
//                                }
//                                $guarantorCollateral->save();
//                            }
//                        }
                        return redirect()->route('loan_detail', [$id]);
                    }
                }
            }
            //Session::flash('message', 'Save successfully');
        }
        return redirect()->back();
    }

    public function getEditGuarantor($id = 0)
    {
        if (is_numeric($id) && $id > 0) {
            $edit = Guarantor::find($id);
            if (!empty($edit)) {
                $collateral = GuarantorCollateral::where('guarantor_id', '=', $edit->id)->get();
                return $this->view('loans.edit_guarantor', ['edits' => $edit, 'collateral' => $collateral]);
            }
        }
    }

    public function postEditGuarantor($id = 0)
    {
        if (is_numeric($id) && $id > 0) {
            $loan_id = Request::input('loan_id');
            $inputs = Request::except(['_token', 'photo', 'signature', 'collateral_photo',
                'collateral_type', 'collateral_no', 'collateral_value', 'birth_date',
                'card_date', 'card_expired_date', 'expired_date', 'collateral_registration',
                'collateral_address', 'note']);
            if (!empty($inputs)) {
                $guarantor = Guarantor::find($id);
                foreach ($inputs as $key => $value) {
                    $guarantor->$key = $value;
                }
                if (Request::hasFile('photo')) {
                    if (Request::file('photo')->isValid()) {
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
                        $image->save(public_path('data/guarantors') . '/' . $photo_name);
                        $guarantor->photo = $photo_name;
                    }
                }
                if (Request::hasFile('signature')) {
                    if (Request::file('signature')->isValid()) {
                        $file = Request::file('signature');
                        $image = Image::make($file);
                        $photo_name = uniqid(date('dmY')) . '.jpg';
                        $image->save(public_path('data/guarantors_signature') . '/' . $photo_name);
                        $guarantor->signature = $photo_name;
                    }
                }
                if (Request::has('birth_date')) {
                    $existDB = Request::input('birth_date');
                    $guarantor->birth_date = $existDB;
                }
                if (Request::has('card_date')) {
                    $existDB = Request::input('card_date');
                    $guarantor->card_date = $existDB;
                }
                if (Request::has('card_expired_date')) {
                    $existDB = Request::input('card_expired_date');
                    $guarantor->card_expired_date = $existDB;
                }
                if (Request::has('expired_date')) {
                    $existDB = Request::input('expired_date');
                    $guarantor->expired_date = $existDB;
                }
                if ($guarantor->save()) {
                    $this->userActivity(Auth::user()->id, $loan_id, 6, 'Update guarantor', 'Guarantor id=' . $id);
//                    $inputsCol = Request::only(['collateral_no','collateral_type','collateral_value','collateral_registration','collateral_address','note']);
//                    $collateral_id = Request::input('id');
//                    if(!empty($inputsCol) && count($inputsCol) > 0){
//                        $count = count($inputsCol['collateral_no']);
//                        for($i = 0; $i < $count ; $i++){
//                            $guarantorCollateral = new GuarantorCollateral();
//                            $guarantorCollateral->guarantor_id = $collateral_id;
//                            foreach ($inputsCol as $key => $value) {
//                                $guarantorCollateral->$key = $value[$i];
//                            }
//                            if(Request::hasFile('collateral_photo')){
//                                $files = Request::file('collateral_photo');
//                                if(count($files)<= $count && $files[$i]->isValid()){
//                                    $image = Image::make($files[$i]);
//                                    $photo_name = uniqid(date('dmY')).'.jpg';
//                                    $image->save(public_path('data/guarantors_collateral').'/'.$photo_name);
//                                    $guarantorCollateral->collateral_photo = $photo_name;
//                                }
//                            }
//                            if($guarantorCollateral->save()){};
//                        }
//                    }
                    return redirect()->route('loan_detail', [$loan_id]);
                }
            }
            Session::flash('message', 'Update successfully');
        }
    }

    public function getGuarantorCollateral($id = 0)
    {
        if (!empty($id) && $id > 0) {
            $collateral = GuarantorCollateral::select('id')->get();
            $guarantor = Guarantor::select(['loan_id'])->where('id', '=', $id)->first();
            return $this->view('loans.add_guarantor_collateral', ['id' => $id, 'guarantor' => $guarantor, 'collateral' => $collateral]);
        }
    }

    public function postGuarantorCollateral($id = 0)
    {
        if (is_numeric($id) && $id > 0) {
            $guarantor = Guarantor::select(['id'])->where('id', '=', $id)->first();
            $loan_id = Request::input('loan_id');
            if (!empty($guarantor)) {
                $inputCollateral = Request::only(['collateral_no', 'collateral_type', 'collateral_value', 'collateral_registration', 'collateral_address', 'note']);
                if (!empty($inputCollateral) && count($inputCollateral) > 0) {
                    $count = count($inputCollateral['collateral_no']);
                    $col_id = '';
                    $success = false;
                    for ($i = 0; $i < $count; $i++) {
                        $addguarantorCollateral = new GuarantorCollateral();
                        $addguarantorCollateral->guarantor_id = $id;
                        foreach ($inputCollateral as $key => $value) {
                            $addguarantorCollateral->$key = $value[$i];
                        }
                        if (Request::hasFile('collateral_photo')) {
                            $files = Request::file('collateral_photo');
                            if (count($files) <= $count && $files[$i]->isValid()) {
                                $image = Image::make($files[$i]);
                                $photo_name = uniqid(date('dmY')) . '.jpg';
                                $image->save(public_path('data/guarantors_collateral') . '/' . $photo_name);
                                $addguarantorCollateral->collateral_photo = $photo_name;
                            }
                        }
                        if ($addguarantorCollateral->save()) {
                            $col_id .= ',' . $addguarantorCollateral->id;
                            $success = true;
                        }
                    }
                    if ($success) {
                        $note = 'Guarantor id=' . $id . '. Collateral id=(' . $col_id . ')';
                        $this->userActivity(Auth::user()->id, $loan_id, 6, 'Create guarantor collateral', $note);
                        Session::flash('message', 'Save successfully');
                        return redirect()->route('loan_detail', [$loan_id]);
                    }
                }
            }
        }
    }

    public function getEditGuarantorCollateral($id = 0)
    {
        if (is_numeric($id) && $id > 0) {
            $edit = GuarantorCollateral::find($id);
            if (!empty($edit)) {
                $collateral = GuarantorCollateral::where('guarantor_id', '=', $edit->guarantor_id)->get();
                $guarantor = Guarantor::select(['loan_id'])->where('id', '=', $edit->guarantor_id)->first();
                return $this->view('loans.edit_guarantor_collateral', ['edits' => $edit, 'collateral' => $collateral, 'guarantor' => $guarantor]);
            }
        }
    }

    public function postEditGuarantorCollateral($id = 0)
    {
        $guarantor_id = Request::input('guarantor_id');
        if (is_numeric($id) && $id > 0) {
            $inputCollateral = Request::only(['collateral_no', 'collateral_type', 'collateral_value', 'collateral_registration', 'collateral_address', 'note']);
            $col_id = Request::input('id');
            $loan_id = Request::input('loan_id');
            $c_id = '';
            if (!empty($inputCollateral) && count($inputCollateral) > 0 && count($col_id) == ($count = count($inputCollateral['collateral_no']))) {
                for ($i = 0; $i < $count; $i++) {
                    $addguarantorCollateral = new GuarantorCollateral;
                    if ($col_id[$i] == $id) {
                        $addguarantorCollateral = GuarantorCollateral::where('id', '=', $id)->first();
                    } else {
                        $addguarantorCollateral->guarantor_id = $guarantor_id;
                    }
                    foreach ($inputCollateral as $key => $value) {
                        $addguarantorCollateral->$key = $value[$i];
                    }
                    if (Request::hasFile('collateral_photo')) {
                        $files = Request::file('collateral_photo');
                        if (count($files) <= $count && $files[$i]->isValid()) {
                            $image = Image::make($files[$i]);
                            $photo_name = uniqid(date('dmY')) . '.jpg';
                            $image->save(public_path('data/guarantors_collateral') . '/' . $photo_name);
                            $addguarantorCollateral->collateral_photo = $photo_name;
                        }
                    }
                    if ($addguarantorCollateral->save()) {
                        $c_id .= ',' . $addguarantorCollateral->id;
                    }
                }
                $note = 'Guarantor id=' . $guarantor_id . '. Collateral id=(' . $c_id . ')';
                $this->userActivity(Auth::user()->id, $loan_id, 6, 'Update or Create guarantor collateral', $note);
                return redirect()->route('loan_detail', [$loan_id]);
            }
        }
    }

    public function get_charge($loan_id = 0)
    {
        if (is_numeric($loan_id) && $loan_id > 0) {
            $loan = Loan::where('id', '=', $loan_id)->first();
            //for helper
            $branch = CompanyBranch::find($loan->company_branch_id);
            $loan_account = ClientLoanAccounts::find($loan->loan_account_id);
            $drawdown_acc = DrawdownAccounts::with('coa')
                    ->where('client_id', $loan->client_id)
                    ->where('account_no', $loan->drawdown_acc)
                    ->where('currency', $loan_account->currency)->first();
            $coa = CoaCategory::find($loan_account->parent_id);
            $coa_2 = CoaCategory::select('id', 'account_code', 'name')->where('type', 6)->where('currency', $loan_account->currency)->where('name', 'like', '%Fees and Commissions on Loans')->first();
            $coa_suspense = CoaCategory::select('id', 'account_code', 'name')->where('type', 6)->where('currency', $loan_account->currency)->where('name', '=', 'Suspense Asset Account')->first();
            $teller = Teller::select('till_account.id', 'account_no', 'account_name', 'assign_user_id', 'till_account.branch_id', 'users.name', 'roles.role', 'till_account.balance', 'till_account.status')
                ->join('users', 'users.id', '=', 'till_account.assign_user_id')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->where((function ($query) {
                    $branch_id = Auth::user()->branch_id;
                    $query->where('roles.role', '=', 'teller')
                        ->where('till_account.branch_id', '=', $branch_id);
                }))->get();
            $data['branch_name'] = $branch->branch_name;
            $data['branch_code'] = $branch->branch_code;
            $data['currency'] = $loan_account->currency;
            $data['coa'] = $coa;
            //$data['coa_1'] = $coa_1;
            $data['coa_1'] = $drawdown_acc->coa;
            $data['coa_2'] = $coa_2;
            $data['coa_suspense'] = $coa_suspense;
            $data['loan'] = $loan;
            $data['loan_id'] = $loan_id;
            $data['teller'] = $teller;
            if (!empty($loan)) {
                if (Request::input('fee_id')) {
                    $data['fee'] = ScheduleFee::find(Request::input('fee_id'));
                }
                return $this->view('loans.charge', $data);
            }
        }
        return redirect()->back();
    }

    public function post_charge($loan_id = 0)
    {
        DB::beginTransaction();
        try {

            if (is_numeric($loan_id) && $loan_id > 0) {
                $inputs = Request::except(['_token', 'sel_tellers', 'payment_type']);
                $validator = Validator::make($inputs, [
                    'charge_type' => 'required',
                    'charge_amount' => 'required|numeric',
                    'charge_date' => 'required'
                ]);
                if ($validator->fails()) {
                    return redirect()->back()->withErrors($validator);
                }
                $loan = Loan::select('id', 'user_id', 'client_id','drawdown_acc')->where('id', '=', $loan_id)->first();
                $drawdown_acc = DrawdownAccounts::with('currency_tbl')
                    ->where('client_id', $loan->client_id)
                    ->where('account_no', $loan->drawdown_acc)
                    ->where('currency', floatval(Request::input('currency')[0]))
                    ->first();
                if ($drawdown_acc->balance < Request::input('charge_amount')) {
                    $balance = $drawdown_acc->currency_tbl->symbol . number_format($drawdown_acc->balance, 2, '.', ',');
                    Session::flash('msg', 'Balance in Drawdown Account is not sufficient! Current Drawdown Account balance is ' . $balance);
                    return redirect()->back();
                }
                if (!empty($loan)) {
                    if (!empty($inputs)) {
                        $charge = new FeeCharge;
                        $charge->loan_id = $loan_id;
                        foreach ($inputs as $key => $value) {
                            if ($key == 'receipt') continue;
                            if ($key == 'charge_date') {
                                $time = strtotime($value);
                                $date = date('Y-m-d', $time);
                                $charge->$key = $date;
                            } else {
                                $charge->$key = $value;
                            }

                            if ($key == 'note') break;
                        }


                        if (Request::hasFile('receipt') && Request::file('receipt')->isValid()) {
                            $extension = Request::file('receipt')->getClientOriginalExtension();
                            $f_name = uniqid(date('dmY')) . '.' . $extension;
                            $upload = Request::file('receipt')->move(public_path('data/loans/charges'), $f_name);
                            if ($upload) $charge->receipt = $f_name;
                        }

                        $charge->user_id = Auth::user()->id;
                        $static = config('static_data.fee_charge');
                        if (is_null($charge->waived_amount) || $charge->waived_amount == '') $charge->waived_amount = 0;
                        $charge->save();
                        $journal_arr = [];
                        array_push($journal_arr, [
                                                Request::input('parent_debit')[0],
                                                Request::input('debit')[0],
                                                Request::input('d_description')[0],
                                                Request::input('parent_credit')[0],
                                                Request::input('credit')[0],
                                                Request::input('c_description')[0],
                                                Request::input('description')[0]
                                            ]);
                        record_journal($loan, $charge->charge_date, $type = "Fee Charge", $inputs, $journal_arr, Auth::user()->branch_id, $charge->user_id);

                        // Update Drawdown Acc. Balance
                        $drawdown_acc->balance = $drawdown_acc->balance - Request::input('charge_amount');
                        $drawdown_acc->save();

                        DB::commit();
                        return redirect()->route('loan_detail', [$loan_id]);
                    }
                }
            }
        } catch (Exception $e) {
            DB::rollback();
            Session::flash('message', 'Save not successfully');
            return redirect()->back();
        }
    }

    private function Add_loan_charge_data_To_notification($datas, $till_acc_id)
    {
        $notification = new App\Models\Notification();
        $till = App\Models\Teller::select('id', 'created_by', 'assign_user_id')->where('id', '=', $till_acc_id)->first();
        $data = [
            $till->assign_user_id,
            11,
            $datas->id,// this is transaction id
            $datas->user_id,//this is the ID of who do loan or loan admin
            $till_acc_id,//this is till account id which used for retrieve this notification for that use
            'Fee Charge & Cost Repayment',
            date("Y-m-d H:m:s", time()),
            $datas->description,
            Request::input('charge_amount') // charge amount
        ];
        return $notification->setNotification($data);
    }

    private function Add_Trans_From_loan_Charge($data, $customer)
    {
        if (!empty($data) && !empty($customer)) {
            $tillData = App\Models\Teller::select('account_name', 'id', 'balance', 'branch_id', 'created_by', 'assign_user_id', 'status')
                ->where('assign_user_id', '=', $this->user_id)->first();
            $tillTrans = ['insert' => [
                'tranx_time' => date('Y-m-d H:m:s', time()),
                'from_account' => $customer->account_no,
                'to_account' => $tillData->account_name,
                'type' => "Fee and Commission",
                'cash_in' => $data->amount,
                'balance' => $tillData->balance + $data->amount,
                'description' => $data->disburse_note
            ]];
            return TellerController::TillTransaction($tillTrans);
        }
    }

    private function Add_Teller_Trans_From_loan_Charge($data, $customer,$transaction_id,$deposit_company=null,$deposit_type=null,$be_cash_item_note=null,$receipt_no=null)
    {
        if (!empty($data) && !empty($customer)) {
            $tillData = App\Models\Teller::select('account_name', 'id', 'balance', 'branch_id', 'created_by', 'assign_user_id', 'status')
                ->where('assign_user_id', '=', $this->user_id)->first();
            $amount=$data->amount?$data->amount:$data['amount'];
            $disburse_note=$data->disburse_note?$data->disburse_note:$data['disburse_note'];
            $record_date = $data['till_date']!=""?date_format(date_create($data['till_date']),"Y-m-d H:i:s"):date("Y-m-d H:i:s");
            if($record_date === 'undefined'){
                $record_date = date("Y-m-d H:i:s");
            }

            $tillTrans = ['insert' => [
                'tranx_time' => $record_date,
                'created_at' => date('Y-m-d H:m:s', time()),
                'drawdown_acc_id'=>$data['id'],
                'from_account' => $customer->account_name,
                'till_account_id'=>$tillData->id,
                'to_account' => $tillData->account_name,
                'drawdown_acc'=>$data['drawdown_acc'],
                'till_user_id'=> $data['users_id'],
                'branch_id'=> $tillData->branch_id,
                'operate_by'=>Auth::user()->id,
                'tran_currency_id'=>$data['currency_id'],
                'type' => "Fee and Commission",
                'slips_id'=>$transaction_id,
                'status'=>0,
                'approve_status'=>0,
                'methode'=>$data['types'],
                'deposit_type'=>$deposit_type,
                'deposit_company'=>$deposit_company,
                'be_cash_item_note'=>$be_cash_item_note,
                'cash_in' => $amount,
                'balance' => $tillData->balance + $amount,
                'description' => $disburse_note,
                'receipt_no' => $receipt_no
            ]];
            return TellerController::TillTransaction($tillTrans);
        }
    }


    public function get_add_collateral($loan_id = 0)
    {
        if (empty($loan_id)) return redirect()->back();

        $collateral = LoanCollateral::where('loan_id', $loan_id)->first();
        return $this->view('loans.add_collateral', ['loan_id' => $loan_id, 'collateral' => $collateral]);
    }

    public function post_add_collateral($loan_id = 0)
    {
        if (is_numeric($loan_id) && $loan_id > 0) {
            $loan = Loan::select('id')->where('id', '=', $loan_id)->first();
            if (!empty($loan)) {
                $inputs = Request::except(['_token', 'photo']);
                if (!empty($inputs)) {
                    $collateral = new LoanCollateral();
                    $collateral->loan_id = $loan_id;
                    foreach ($inputs as $key => $value) {
                        $collateral->$key = $value;
                    }

                    /*if(Request::hasFile('photo') && Request::file('photo')->isValid()){
                        $file = Request::file('photo');
                        $image = Image::make($file);
                        $photo_name = uniqid(date('dmY')).'.jpg';
                        $image->save(public_path('data/loans/collateral').'/'.$photo_name);
                        $collateral->collateral_photo = $photo_name;
                    }*/

                    if (Request::hasFile('photo')) {
                        /*$input = array('attachment' => Request::file('photo'));
                        $rules = array(
                          'extension' => 'in:jpg,jpeg,bmp,png,doc,docx,zip,rar,pdf,rtf,xlsx,xls,txt'
                        );
                        $validator = Validator::make($input, $rules);
                        if (!$validator->fails())
                        {*/
                        if (Request::hasFile('photo') && Request::file('photo')->isValid()) {
                            $extension = Request::file('photo')->getClientOriginalExtension();
                            $f_name = uniqid(date('dmY')) . '.' . $extension;
                            $upload = Request::file('photo')->move(public_path('data/loans/collateral'), $f_name);
                            if ($upload) $collateral->collateral_photo = $f_name;
                        }
                    }

                    if ($collateral->save()) {
                        $this->userActivity(Auth::user()->id, $loan_id, 6, 'Create Loan collateral', 'Collateral id=' . $collateral->id);
                        Session::flash('message', 'Save successfully');
                        return redirect()->route('loan_detail', [$loan_id]);
                    }
                }
            }
        }
        return redirect()->back();
    }

    public function getEditCollateral($id = 0)
    {
        if (is_numeric($id) && $id > 0) {
            $edit = LoanCollateral::find($id);
            if (!empty($edit)) {
                return $this->view('loans.edit_collateral', ['edits' => $edit]);
            }
        }
    }

    public function postEditCollateral($id = 0)
    {
        if (is_numeric($id) && $id > 0) {
            $inputs = Request::except(['_token', 'photo']);
            if (!empty($inputs)) {
                $collateral = LoanCollateral::find($id);
                foreach ($inputs as $key => $value) {
                    $collateral->$key = $value;
                }

                /*if(Request::hasFile('photo') && Request::file('photo')->isValid()){
                    $file = Request::file('photo');
                    $image = Image::make($file);
                    $photo_name = uniqid(date('dmY')).'.jpg';
                    $image->save(public_path('data/loans/collateral').'/'.$photo_name);
                    $collateral->collateral_photo = $photo_name;
                }*/

                if (Request::hasFile('photo')) {
                    if (Request::hasFile('photo') && Request::file('photo')->isValid()) {
                        $extension = Request::file('photo')->getClientOriginalExtension();
                        $f_name = uniqid(date('dmY')) . '.' . $extension;
                        $upload = Request::file('photo')->move(public_path('data/loans/collateral'), $f_name);
                        if ($upload) $collateral->collateral_photo = $f_name;
                    }
                }

                if ($collateral->save()) {
                    $this->userActivity(Auth::user()->id, $collateral->loan_id, 6, 'Update Loan collateral', 'Collateral id=' . $id);
                    Session::flash('message', 'Update successfully');
                    return redirect()->route('loan_detail', [$collateral->loan_id]);
                }
            }
        }
    }

    public function approve_loan($loan_id = 0)
    {
        Session::flash('pre_url', URL::previous());
        if ($loan_id > 0) {
            $loan = Loan::select('id', 'status')->where('id', '=', $loan_id)->first();
            if (!empty($loan) && $loan->status == 1) { /* Unauthorized */
                return $this->view('loans.approval', ['loan' => $loan]);
            }
        }
        return redirect()->back();
    }

    public function post_approve_loan($loan_id = 0)
    {
        //$url = Session::get('pre_url');
        if ($loan_id > 0) {
            $loan = Loan::select('id', 'status')->where('id', '=', $loan_id)->first();
            if (!empty($loan) && $loan->status == 1) { /* Unauthorized */
                $loan->status = 2;
                $loan->workflow_status = 'approve';
                $approval = LoanApproval::where('loan_id', '=', $loan_id)->first();
                if (!empty($approval)) {
                    $approval->loan_id = $loan_id;
                    if (Auth::check()) {
                        $approval->user_id = Auth::user()->id;
                    } else {
                        return redirect()->route('login');
                    }
                    if (Request::has('approval_date')) {
                        $date = Request::input('approval_date');
                        $date = date('Y-m-d', strtotime($date));
                        $approval->approval_date = $date;
                    }
                    if (Request::has('note')) {
                        $approval->note = Request::input('note');
                    }
                    $approval->updated_at = date('Y-m-d H:i:s');
                    if ($approval->save()) {
                        if ($loan->save()) {
                            $this->do_audit($loan_id, '', Auth::user()->id, 'loans', 1, 'approve loan');

                            $this->userActivity($approval->user_id, $loan_id, 6, 'Approval loan');
                            Session::flash('msg','Approve success');
                            return redirect()->route('loan_detail', [$loan_id]);
                        }
                    }
                } else {
                    $napproval = new LoanApproval();
                    $napproval->loan_id = $loan_id;
                    if (Auth::check()) {
                        $napproval->user_id = Auth::user()->id;
                    } else {
                        return redirect()->route('login');
                    }
                    if (Request::has('approval_date')) {
                        $date = Request::input('approval_date');
                        $date = date('Y-m-d', strtotime($date));
                        $napproval->approval_date = $date;
                    }
                    if (Request::has('note')) {
                        $napproval->note = Request::input('note');
                    }
                    $napproval->created_at = date('Y-m-d H:i:s');
                    if ($napproval->save()) {
                        if ($loan->save()) {
                            $this->do_audit($loan_id, '', Auth::user()->id, 'loans', 1, 'approve loan');

                            $this->userActivity($napproval->user_id, $loan_id, 6, 'Approval loan');
                            return redirect()->route('loan_detail', [$loan_id]);
                        }
                    }
                }
            }
        }
        Session::flash('message', 'Something wrong');
        return redirect()->back();
    }

    function do_ajax_upload()
    {
        if (isset($_POST['is_delete'])) {
            $doc = LoanDocument::find($_POST['loan_document_id']);
            if ($doc->delete()) {
                //remove old file
                File::delete(public_path('data/loans/documents/') . $_POST['old_file']);
                echo '1';
            }
            die();
        }

        if (isset($_POST['is_update'])) {
            $doc = LoanDocument::where('id', $_POST['loan_document_id'])->update(array('note' => $_POST['note']));
            echo '1';
            die();
        }

        $validex = array('jpg', 'jpeg', 'bmp', 'png', 'doc', 'docx', 'zip', 'rar', 'pdf', 'rtf', 'xlsx', 'xls', 'txt');
        $file = $_FILES[$_POST['fname']];
        $ex = explode('.', $file['name']);
        if (in_array(end($ex), $validex)) {
            $photo_name = uniqid(date('dmY')) . '.' . end($ex);
            if (move_uploaded_file($file['tmp_name'], public_path('data/loans/documents/') . $photo_name)) {
                $loan_id = $_POST['loan_id'];
                $doc = new LoanDocument();
                $doc->loan_id = $loan_id;
                if (Auth::check()) {
                    $id = Auth::user()->id;
                    $doc->user_id = $id;
                }

                $loan = Loan::select('id')->where('id', '=', $loan_id)->first();
                $doc->doc_photo = $photo_name;
                $doc->note = $_POST['note'];

                $arr_t = $this->doc_type_labe();
                $doc->doc_type = $_POST['fname'];
//dd($doc);
                if (!empty($loan)) {
                    if (isset($_POST['loan_document_id'])) {
                        //remove old file
                        File::delete(public_path('data/loans/documents/') . $_POST['old_file']);

                        LoanDocument::where('id', $_POST['loan_document_id'])->update(array(
                            'note' => $_POST['note'],
                            'doc_photo' => $photo_name
                        ));
                        $id = $_POST['loan_document_id'];
                    } else {
                        $doc->save();
                        $id = $doc->id;
                    }

                    if ($id) {
                        $this->userActivity($doc->user_id, $loan_id, 6, 'Add loan document', 'Document id=' . $doc->id);
                        $arrj = array('loan_document_id' => $doc->id, 'path' => asset('data/loans/documents/'), 'photo_name' => $photo_name);
                        echo json_encode($arrj);
                    } else {
                        $data = array('status' => 'false');
                        echo json_encode($data);
                    }
                }
            }
        } else {
            $data = array('status' => 'false');
            echo json_encode($data);
        }
    }

    public function add_document($loan_id = 0)
    {
        $arr_t = $this->doc_type_labe();
        if ($loan_id > 0) {
            $loan = Loan::select('id')->where('id', '=', $loan_id)->first();
            if (!empty($loan)) {
                $doc = LoanDocument::where('loan_id', '=', $loan_id)->groupBy('doc_type')->get();
                $arr_doc_type = array();
                $arr_doc_photo = array();
                $arr_note = array();
                $arr_docid = array();
                foreach ($doc as $doc) {
                    $arr_docid[$doc->doc_type] = $doc->id;
                    $arr_doc_type[$doc->doc_type] = $doc->doc_type;
                    $arr_doc_photo[$doc->doc_type] = $doc->doc_photo;
                    $arr_note[$doc->doc_type] = $doc->note;
                }

                return $this->view('loans.document', [
                    'loan' => $loan,
                    'arr_docid' => $arr_docid,
                    'arr_doc_type' => $arr_doc_type,
                    'arr_doc_photo' => $arr_doc_photo,
                    'arr_note' => $arr_note,
                    'arr' => $arr_t['arr'],
                    'arrt' => $arr_t['arrt'],
                    'loan_id' => $loan_id,
                    'loan_detail' => route('loan_detail', [$loan_id])
                ]);
            }
        }
        return redirect()->back();
    }

    public function view_loan_add_document($loan_id = 0){
        $arr_t = $this->doc_type_labe();
        if ($loan_id > 0) {
            $loan = Loan::select('id')->where('id', '=', $loan_id)->first();
            if (!empty($loan)) {
                $doc = LoanDocument::where('loan_id', '=', $loan_id)->groupBy('doc_type')->get();
                $arr_doc_type = array();
                $arr_doc_photo = array();
                $arr_note = array();
                $arr_docid = array();
                foreach ($doc as $doc) {
                    $arr_docid[$doc->doc_type] = $doc->id;
                    $arr_doc_type[$doc->doc_type] = $doc->doc_type;
                    $arr_doc_photo[$doc->doc_type] = $doc->doc_photo;
                    $arr_note[$doc->doc_type] = $doc->note;
                }

                return $this->view('loans.view_document', [
                    'loan' => $loan,
                    'arr_docid' => $arr_docid,
                    'arr_doc_type' => $arr_doc_type,
                    'arr_doc_photo' => $arr_doc_photo,
                    'arr_note' => $arr_note,
                    'arr' => $arr_t['arr'],
                    'arrt' => $arr_t['arrt'],
                    'loan_id' => $loan_id
                ]);
            }
        }
        return redirect()->back();
    }

    function doc_type_labe()
    {
        $arr['lad'] = 'Loan Approval Document';
        $arr['es'] = 'Executive Summary (ES)';
        $arr['pi'] = 'Proof of Incomes (Financial Statement) with evidence';
        $arr['cc'] = 'Customer Consent (for CBC checking)';
        $arr['cbc'] = 'CBC Report';
        $arr['cerie'] = 'Collateral Evaluation Report include evidences';
        $arr['dibg'] = 'Draft of information of borrower(s) and guarantor(s)';
        $arr['pbs'] = 'Pictures of business sites';

        $arr['la'] = 'Loan Agreement';
        $arr['ga'] = 'Guarantor Agreement';

        $arr['ha_1'] = 'Hypothec Agreement (1)';
        $arr['ha_2'] = 'Hypothec Agreement (2)';
        $arr['ha_3'] = 'Hypothec Agreement (3)';
        $arr['ha_4'] = 'Hypothec Agreement (4)';
        $arr['ha_5'] = 'Hypothec Agreement (5)';
        $arr['ha_6'] = 'Hypothec Agreement (6)';
        $arr['ha_7'] = 'Hypothec Agreement (7)';

        $arr['lmotd_1'] = 'Letter of maintaining the original title deed(s 1)';
        $arr['lmotd_2'] = 'Letter of maintaining the original title deed(s 2)';
        $arr['lmotd_3'] = 'Letter of maintaining the original title deed(s 3)';
        $arr['lmotd_4'] = 'Letter of maintaining the original title deed(s 4)';
        $arr['lmotd_5'] = 'Letter of maintaining the original title deed(s 5)';
        $arr['lmotd_6'] = 'Letter of maintaining the original title deed(s 6)';
        $arr['lmotd_7'] = 'Letter of maintaining the original title deed(s 7)';


        $arr['ol_1'] = 'Obstructive Letter (Letter to Sangkat 1)';
        $arr['ol_2'] = 'Obstructive Letter (Letter to Sangkat 2)';
        $arr['ol_3'] = 'Obstructive Letter (Letter to Sangkat 3)';
        $arr['ol_4'] = 'Obstructive Letter (Letter to Sangkat 4)';
        $arr['ol_5'] = 'Obstructive Letter (Letter to Sangkat 5)';
        $arr['ol_6'] = 'Obstructive Letter (Letter to Sangkat 6)';
        $arr['ol_7'] = 'Obstructive Letter (Letter to Sangkat 7)';

        $arr['otd_1'] = 'Original title deed(s 1)';
        $arr['otd_2'] = 'Original title deed(s 2)';
        $arr['otd_3'] = 'Original title deed(s 3)';
        $arr['otd_4'] = 'Original title deed(s 4)';
        $arr['otd_5'] = 'Original title deed(s 5)';
        $arr['otd_6'] = 'Original title deed(s 6)';
        $arr['otd_7'] = 'Original title deed(s 7)';

        $arr['vsp'] = 'Vehicle sale purchase letter';
        $arr['ovd'] = 'Original Vehicle document(s)';

        $arr['bid'] = 'Borrower窶冱 identification such as ID card, Passport, est. (hard copy)';
        $arr['gid'] = 'Guarantor窶冱 identification such as ID card, Passport, est. (hard copy)';

        $arr['ord_1'] = 'Other Relevance Documents (1)';
        $arr['ord_2'] = 'Other Relevance Documents (2)';
        $arr['ord_3'] = 'Other Relevance Documents (3)';
        $arr['ord_4'] = 'Other Relevance Documents (4)';
        $arr['ord_5'] = 'Other Relevance Documents (5)';
        $arr['ord_6'] = 'Other Relevance Documents (6)';
        $arr['ord_7'] = 'Other Relevance Documents (7)';
        $arr['ord_8'] = 'Other Relevance Documents (8)';
        $arr['ord_9'] = 'Other Relevance Documents (9)';
        $arr['ord_10'] = 'Other Relevance Documents (10)';

        $arr['dr'] = 'Disbursement Request';
        $arr['dv'] = 'Disbursement Voucher (copy of slip or check)';
        $arr['rs'] = 'Repayment Schedule ';

        $arrt['i_d'] = 'I. DOCUMENTS OF CREDIT ASSESSMENT';
        $arrt['ii_d'] = 'II. DOCUMENTS OF LOAN ADMINISTRATION';
        $arrt['ii_d_a'] = 'a) Agreements';
        $arrt['ii_d_b'] = 'b) Collaterals';
        $arrt['ii_d_c'] = 'c) Identification';
        $arrt['iii_d'] = 'III. DOCUMENTS OF LOAN DISBURSEMENT';
        $arrt['iiii_d'] = 'IV. Document of Contract Team';

        $arr['add'] = 'New contract';
        $arr['add_change_unit'] = 'Change Unit';
        $arr['add_downpayment'] = 'Down Payment';
        $arr['add_pay_off'] = 'Pay Off';
        $arr['add_sub_sale'] = 'Sub Sale';
        $arr['add_terminate_contract'] = 'Terminate Contract';
        $arr['add_restructure_loan'] = 'Restructure Loan';
        $arr['add_add_remove_name'] = 'Add and Remove Name';
        $arr['add_other'] = 'Other';

        return array('arr' => $arr, 'arrt' => $arrt);
    }

    public function post_add_document($loan_id = 0)
    {
        dd(Request::file());
        if ($loan_id > 0) {
            $data = Request::only(['doc_type', 'note']);
            $validator = Validator::make($data, [
                'doc_type' => 'required'
            ]);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator);
            }
            if (!empty($data)) {
                $doc = new LoanDocument();
                $doc->loan_id = $loan_id;
                foreach ($data as $key => $value) {
                    $doc->$key = $value;
                }
                if (Auth::check()) {
                    $id = Auth::user()->id;
                    $doc->user_id = $id;
                } else {
                    return redirect()->route('login');
                }
                if (Request::hasFile('photo') && Request::file('photo')->isValid()) {
                    $photo = Request::file('photo');
                    //$file_name = uniqid(date('dmY')).'.'.$photo->getClientOriginalExtension();
                    $destinationPath = public_path('data/loans/documents');
                    $name = $photo->getClientOriginalName();
                    if ($photo->move($destinationPath, $name)) {
                        $doc->doc_photo = $name;
                    }
                }
                $loan = Loan::select('id')->where('id', '=', $loan_id)->first();
                if (!empty($loan)) {
                    if ($doc->save()) {
                        $this->userActivity($doc->user_id, $loan_id, 6, 'Add loan document', 'Document id=' . $doc->id);
                        return redirect()->route('loan_detail', [$loan_id]);
                    }
                }
            }
        }
        Session::flash('message', 'Save not successfully');
        return redirect()->back();
    }

    public function getEditDocument($did = 0)
    {
        if ($did > 0) {
            $doc = LoanDocument::find($did);
            if (!empty($doc)) {
                return $this->view('loans.edit_doc', ['docu' => $doc]);
            }
        }
        return redirect()->back();
    }

    public function postEditDocument($did = 0)
    {
        if ($did > 0) {
            $doc = LoanDocument::find($did);
            if (!empty($doc)) {
                $doc->doc_type = Request::input('doc_type');
                $doc->note = Request::input('note');
                if (Request::hasFile('photo') && Request::file('photo')->isValid()) {
                    $photo = Request::file('photo');
                    $destinationPath = public_path('data/loans/documents');

                    if ($doc->doc_photo != "") {
                        $oldFile = $destinationPath . '/' . $doc->doc_photo;
                        if (File::exists($oldFile)) {
                            File::delete($oldFile);
                        }
                    }

                    $name = str_random(5) . '_' . $photo->getClientOriginalName();
                    if ($photo->move($destinationPath, $name)) {
                        $doc->doc_photo = $name;
                    }
                }
                if ($doc->save()) {
                    $this->userActivity($doc->user_id, $doc->loan_id, 6, 'Update loan document', 'Document id=' . $doc->id);
                    return redirect()->route('loan_detail', [$doc->loan_id]);
                }
            }
        }
        return redirect()->back();
    }

    public function add_cost($loan_id = 0)
    {
        if ($loan_id > 0) {
            $loan = Loan::where('id', '=', $loan_id)->first();
            if (!empty($loan)) {
                $branch = CompanyBranch::find($loan->company_branch_id);
                $loan_account = ClientLoanAccounts::find($loan->loan_account_id);
                $drawdown_acc = DrawdownAccounts::with('coa')
                    ->where('client_id', $loan->client_id)
                    ->where('account_no', $loan->drawdown_acc)
                    ->where('currency', $loan_account->currency)->first();
                $coa_2 = CoaCategory::where('id', $drawdown_acc->coa_id)->first();
                $coa_1 = CoaCategory::select('id', 'account_code', 'name')->where('type', 6)->where('currency', $loan_account->currency)->where('name', 'like', 'Exp Fee Commissions%')->first();
                $teller = Teller::select('till_account.id', 'account_no', 'account_name', 'assign_user_id', 'till_account.branch_id', 'users.name', 'roles.role', 'till_account.balance', 'till_account.status')
                    ->join('users', 'users.id', '=', 'till_account.assign_user_id')
                    ->join('roles', 'roles.id', '=', 'users.role_id')
                    ->where((function ($query) {
                        $branch_id = Auth::user()->branch_id;
                        $query->where('roles.role', '=', 'teller')
                            ->where('till_account.branch_id', '=', $branch_id);
                    }))->get();
                $data['branch_name'] = $branch->branch_name;
                $data['branch_code'] = $branch->branch_code;
                $data['currency'] = $loan_account->currency;
                //$data['coa_1'] = $coa_1;
                $data['coa_1'] = $coa_1;
                $data['coa_2'] = $coa_2;
                $data['loan'] = $loan;
                $data['teller'] = $teller;

                return $this->view('loans.costfee', $data);
            }
        }

        return redirect()->back();
    }

    public function post_add_cost($loan_id = 0)
    {
        DB::beginTransaction();
        try {
            if ($loan_id > 0) {
                $data = Request::only(['cost_type', 'cost_amount', 'cost_date', 'note']);
                $validator = Validator::make($data, [
                    'cost_type' => 'required',
                    'cost_amount' => 'required|numeric',
                    'cost_date' => 'required'
                ]);
                if ($validator->fails()) {
                    return redirect()->back()->withErrors($validator);
                }
                if (!empty($data)) {
                    $cost = new LoanCostFee();
                    $cost->loan_id = $loan_id;
                    if (Auth::check()) {
                        $uid = Auth::user()->id;
                        $cost->user_id = $uid;
                    } else {
                        return redirect()->route('login');
                    }

                    $loan = Loan::select('id')->where('id', '=', $loan_id)->first();
                    if (!empty($loan)) {
                        foreach ($data as $key => $value) {
                            if ($key == 'cost_date') {
                                $time = strtotime($value);
                                $date = date('Y-m-d', $time);
                                $cost->$key = $date;
                            } else {
                                $cost->$key = $value;
                            }
                        }
                        $static = config('static_data.fee_cost_type');
                        if ($cost->save()) {
                            $last_transaction = TransactionsRequiry::select('id', 'balance')->where('loan_id', '=', $loan_id)
                                ->orderBy('id', 'DESC')->first();
                            $transaction = new TransactionsRequiry();
                            $transaction->loan_id = $loan_id;
                            $transaction->trans_type = "Cost Repayment";
                            $transaction->trans_date = $cost->cost_date;
                            $transaction->description = $static[$cost->cost_type];
                            $transaction->amount = $cost->cost_amount;
                            $transaction->fee = $cost->cost_amount;
                            $transaction->user_id = $cost->user_id;
                            $transaction->balance = $last_transaction->balance;
                            $transaction->cost_id = $cost->id;
                            if ($transaction->save()) {
                                $customer = ClientLoanAccounts::select('account_no')->where('client_id', '=', $loan_id)->first();

                                //journal\
                                $branch_code = Request::input('branch_code')[0];
                                $entry_date = date('Y-m-d H:i:s', strtotime($cost->cost_date));
                                $invoice_number = Request::input('invoice_number')[0];
                                $contract_id = Request::input('contract_id')[0];

                                for ($m = 0; $m < count(Request::input('debit')); $m++) {
                                    $description = Request::input('description')[$m];
                                    $transaction_id = $transaction->id;
                                    $journal = new JournalRequiry;
                                    $journal->tran_id = $transaction_id;
                                    $journal->entry_date = $entry_date;
                                    $journal->invoice_number = $invoice_number;
                                    $journal->description = $description;
                                    $journal->user_id = $cost->user_id;
                                    $journal->is_audit = 1;

                                    if ($journal->save()) {
                                        $this->do_audit($transaction->id, $cost->user_id, '', 'transactions_requiry', 0, 'add charge');

                                        $tran = TransactionsRequiry::where('id', '=', $transaction->id)->where('flag', '=', 0)->first();
                                        if (!empty($tran)) {
                                            $tran->flag = 1;
                                            $tran->jid = $journal->id;
                                            $tran->save();
                                        }
                                        for ($k = 0; $k < 2; $k++) { // 0 = debit, 1 = credit
                                            $parent_debit = Request::input('parent_debit')[$m];
                                            $parent_credit = Request::input('parent_credit')[$m];
                                            $debit = Request::input('debit')[$m];
                                            $credit = Request::input('credit')[$m];
                                            $d_description = Request::input('d_description')[$m];
                                            $c_description = Request::input('c_description')[$m];

                                            $jd = new JournalDetail;
                                            $jd->journal_id = $journal->id;
                                            $jd->coa_id = $k == 0 ? $parent_debit : $parent_credit;
                                            $jd->reference = $contract_id;
                                            $jd->branch_code = $branch_code;
                                            $jd->is_audit = 1;

                                            $prev_bl = array_fill(0, 2, 0.0);
                                            $prev_row = JournalDetail::select('b_debit', 'b_credit')
                                                ->where('coa_id', $jd->coa_id)
                                                ->orderBy('id', 'desc')
                                                ->first();
                                            if (!empty($prev_row)) {
                                                $prev_bl[0] = $prev_row->b_debit;
                                                $prev_bl[1] = $prev_row->b_credit;
                                            }
                                            $jd->p_debit = $prev_bl[0];
                                            $jd->p_credit = $prev_bl[1];
                                            $jd->debit = $k == 0 ? $debit : 0;
                                            $jd->credit = $k == 1 ? $credit : 0;
                                            $jd->b_debit = floatval($prev_bl[0]) + floatval($jd->debit);
                                            $jd->b_credit = floatval($prev_bl[1]) + floatval($jd->credit);
                                            $jd->description = $k == 0 ? $d_description : $c_description;
                                            $jd->save();
                                        }
                                    }
                                }
                            }

                            $this->userActivity($cost->user_id, $loan_id, 6, 'Add loan fee cost', $static[$cost->cost_type]);
                            $this->do_audit($transaction->id, Auth::user()->id, '', 'transactions_requiry', 0, 'cost');
                            //$this->Add_loan_charge_data_To_notification($transaction, Request::input('sel_tellers'));
                            DB::commit();
                            return redirect()->route('loan_detail', [$loan_id]);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            DB::rollback();
            Session::flash('message', 'Save not successfully');
            return redirect()->back();
        }
    }

    public function add_history($loan_id = 0)
    {
        if ($loan_id > 0) {
            $loan = Loan::where('id', '=', $loan_id)->first();
            $loanHistory = LoanHistory::where('loan_id', '=', $loan_id)->first();
            if (!empty($loan)) {
                $data['loan'] = $loan;
                $data['loanHistory'] = $loanHistory;
                return $this->view('loans.add_history', $data);
            }
        }
        return redirect()->back();
    }
    public function post_add_history($loan_id = 0)
    {
        DB::beginTransaction();
        try {

            $loanHistory = LoanHistory::where('loan_id', '=', $loan_id)->first();
 
                if (!empty($loanHistory)) {
                    $loanHistory->interest = Request::input('interest');
                    $loanHistory->principal = Request::input('principal');
                    $loanHistory->total_paid = Request::input('total_paid');
                    $loanHistory->note = Request::input('note');
                    $loanHistory->interest_status = Request::input('interest_status');
                    $loanHistory->principal_status = Request::input('principal_status');
                    if (Auth::check()) {
                        $uid = Auth::user()->id;
                        $loanHistory->user_id = $uid;
                    } else {
                        return redirect()->route('login');
                    }
                    $loanHistory->save();

                }else{
                   $loanHistory= new LoanHistory();
                    $loanHistory->loan_id=$loan_id;
                    $loanHistory->interest = Request::input('interest');
                    $loanHistory->principal = Request::input('principal');
                    $loanHistory->total_paid = Request::input('total_paid');
                    $loanHistory->note = Request::input('note');
                    $loanHistory->interest_status = Request::input('interest_status');
                    $loanHistory->principal_status = Request::input('principal_status');
                    if (Auth::check()) {
                        $uid = Auth::user()->id;
                        $loanHistory->user_id = $uid;
                    } else {
                        return redirect()->route('login');
                    }
                    $loanHistory->save();
                    
                }

                DB::commit();
                return redirect()->route('loan_detail', [$loan_id])->with(['msg' => 'Save success']);
            
        } catch (Exception $e) {
            DB::rollback();
            Session::flash('message', 'Save not successfully');
            return redirect()->back();
        }
    }


    public function get_summary_repayment()
    {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 15;
        $query_arr = array('user_id' => Auth::user()->id, 'company_branch_id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);
        $B0 = new Loan();
        //$B0 = $this->getUserByBranch($B0, 'company_branch_id', $query_arr);

        $loan = $B0->select([
            'loans.id',
            'loans.contract_id',
            'loans.repayment_type',
            'loans.balloon_amount_array',
            'loans.start_date',
            'loans.loan_duration',
            'loans.loan_amount',
            'loans.original_amount',
            'loans.interest_rate',
            'loans.balloon',
            'loans.balloon_month',
            'loans.monthly_payment',
            'loans.balloon_amount_array',
            'loans.custom_flag',
            'loans.days_of_month',
            'loans.client_id',
            'loans.last_schedule_date',
            'loans.penalty_rate_type',
            'loans.penalty_period1',
            'loans.penalty_period2',
            'loans.penalty_rate1',
            'loans.penalty_rate2',
            'loans.holiday_flag',
            'loans.co',
            //'loans.user_id',
            'clients.client_name',
            'clients.address', 'clients.phone1', 'clients.phone2',//, 'clients.city'
            'drawdown_account.balance'])
            ->leftJoin('clients', 'clients.id', '=', 'loans.client_id')
            ->join('drawdown_account', 'drawdown_account.client_id', '=', 'loans.client_id')
            ->with(['schedule' => function ($q) {
                $q->orderBy('schedule_date', 'asc');
            }])
            ->with(['payment' => function ($q) {
                $q->select('loan_id', 'repayment_date', 'payment_month', 'status', 'paid_interest', 'paid_principal', 'paid_fee', 'penalty_amount', 'repayment_owed', 'condition_id');
            }])->with(['co_user' => function ($q) {
                $q->select('id', 'name');
            }])//theary
            ->whereIn('loans.status', [3, 8]);
        if (Request::has('contract_id')) {
            $contractID = Request::input('contract_id');
            $loan = $loan->where('contract_id', '=', $contractID);
        }
        if (Request::has('client_name')) {
            $name = Request::input('client_name');
            $loan = $loan->where('client_name', 'like', '%' . $name . '%');
        }
        if (Request::has('phone')) {
            $phone = Request::input('phone');
            $loan = $loan->where(function ($query) use ($phone) {
                $query->where('phone1', '=', $phone)->orWhere('phone2', '=', $phone);
            });
        }
        if (Request::has('city')) {
            $address = Request::input('city');
            $loan = $loan->where('city', '=', $address);
        }
        $date_search = date('Y-m');
        if (Request::has('date')) {
            $date_search = Request::input('date');
            $loan = $loan->whereRaw("DATE_FORMAT(disburse_date,'%Y-%m') < '$date_search'");
            $loan = $loan->whereRaw("DATE_FORMAT(DATE_ADD(disburse_date,INTERVAL loan_duration MONTH),'%Y-%m') >= '$date_search'");
        }
        //$loan = $loan->whereRaw("DATE_FORMAT(last_schedule_date,'%Y-%m') < '$date_search' ");
        //$loan = $loan->orderBy('start_date','ASC')->take(10)->get();
        $offset = 15000;
        $loan = $loan->orderBy('start_date', 'ASC')->paginate($offset);
        $is_schedule = 0;
        if (Request::has('is_schedule')) {
            $is_schedule = Request::input('is_schedule');
        }
        if (Request::ajax()) {
            if ($is_schedule == 0) {
                return view('partials.summary_schedule', ['loans' => $loan, 'date_search' => $date_search, 'offset' => $offset])->render();
            } else {
                return view('partials.detail_summary_schedule', ['loans' => $loan, 'date_search' => $date_search, 'offset' => $offset])->render();
            }
        } else {
            return $this->view('loans.schedule_monitor', ['loans' => $loan, 'date_search' => $date_search, 'offset' => $offset]);
        }
    }

    public function getRepaymentForToday()
    {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 15;
        $query_arr = array('user_id' => Auth::user()->id, 'company_branch_id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);
        $B0 = new Loan();
        $B0 = $this->getUserByBranch($B0, 'company_branch_id', $query_arr);

        $loans = $B0->select([
            'loans.id',
            'loans.contract_id',
            'loans.start_date',
            'loans.client_id',
            'loans.last_schedule_date',
            'clients.client_name',
            'clients.address', 'clients.phone1', 'clients.phone2'])
            ->leftJoin('clients', 'clients.id', '=', 'loans.client_id')
            ->whereIn('loans.status', [3, 8])
            ->whereRaw("DATEDIFF(DATE_FORMAT(NOW(),'%Y-%m-%d'),DATE_ADD(last_schedule_date,INTERVAL 1 MONTH)) >= 0")
            ->orderBy('loans.contract_id', 'ASC')
            ->paginate($offset);
        return $this->view('loans.today_repayment', ['loans' => $loans]);
    }

    public function get_transaction_list()
    {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 15;
        $order = 'invoice_number';
        $order_dir = 'desc';
        $order_type = [
            'invoice_number' => 'invoice_number'
        ];

        $query_arr = array('user_id' => Auth::user()->id, 'branch_id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);
        $B0 = new TransactionsRequiry();
        //$B0 = $this->getUserByBranch($B0, 'user_id', $query_arr);
        $tr = new TransactionsRequiry();
        $trans = $B0->select(['transactions_requiry.*', 'users.name'])->leftJoin('users', 'users.id', '=', 'transactions_requiry.user_id')
            ->with(['loan' => function ($query) {
                $query->select(['loans.id', 'loans.loan_account_id', 'loans.co', 'loans.contract_id', 'company_branch.branch_name', 'clients.client_name', 'products.category_id', 'product_category.category_name', 'users.name'])
                    ->leftJoin('company_branch', 'company_branch.id', '=', 'loans.company_branch_id')
                    ->leftJoin('clients', 'clients.id', '=', 'loans.client_id')
                    ->leftJoin('products', 'products.id', '=', 'loans.product_id')
                    ->leftJoin('users', 'users.id', '=', 'loans.user_id')
                    ->leftJoin('product_category', 'product_category.id', '=', 'products.category_id')
                    ->with('co_user')
                    ->with('client_loan_account');
            }])->with('audit');
        $selBranch = CompanyBranch::where('status', '=', 1)->get(['id', 'branch_name']);
        $start = '';
        $end = '';
        $type = '';
        $cid = '';
        $by = '';
        $search = 0;
        $tid = '';
        $branch = '';
        $contract_id = '';
        $client_name = '';
        $category_name = '';
        $name = '';
        $breakdown_type = '';
        $query_url = [];
        if (Request::has('start')) {
            $start = date("Y-m-d", strtotime(Request::input('start')));
            $query_url['start'] = $start;
        }

        if (Request::has('end')) {
            $end = Request::input('end');
            $query_url['end'] = $end;
            $end = date("Y-m-d", strtotime($end . "+1 days"));
        }

        if (Request::has('client_name')) {
            $client_name = Request::input('client_name');
            $query_url['client_name'] = $client_name;
        }
        if (Request::has('type')) {
            $type = Request::input('type');
            $query_url['type'] = $type;
        }
        if (Request::has('breakdown_type')) {
            $breakdown_type = Request::input('breakdown_type');
            $query_url['breakdown_type'] = $breakdown_type;
        }
        if (Request::has('contract_id')) {
            $contract_id = Request::input('contract_id');
            $query_url['contract_id'] = $contract_id;
        }
        if (Request::has('category_name')) {
            $category_name = Request::input('category_name');
            $query_url['category_name'] = $category_name;
        }
        if (Request::has('by')) {
            $by = Request::input('by');
            $query_url['by'] = $by;
        }
        if (Request::has('name')) {
            $name = Request::input('name');
            $query_url['name'] = $name;
        }
        if (Request::has('id')) {
            $cid = Request::input('id');
            $query_url['id'] = $cid;
        }
        if (Request::has('search')) {
            $search = Request::input('search');
            $query_url['search'] = $search;
        }
        if (Request::has('t')) {
            $tid = Request::input('t');
            $query_url['t'] = $tid;
        }
        if (Request::has('selBrand')) {
            $branch = Request::input('selBrand');
            $query_url['selBrand'] = $branch;
        }
        if (!empty($tid)) {
            $trans = $trans->where('transactions_requiry.id', '=', $tid);
        } elseif (!empty($cid)) {
            $trans = $trans->whereHas('loan', function ($query) use ($cid) {
                $query->where('contract_id', '=', $cid);
            });
        } else {
            if (!empty($start) && empty($end)) {
                $trans->where('trans_date', '>=', $start);
            } elseif (empty($start) && !empty($end)) {
                $trans->where('trans_date', '<', $end);
            } elseif (!empty($start) && !empty($end)) {
                $trans->where('trans_date', '>=', $start);
                $trans->where('trans_date', '<', $end);
            }
            if (!empty($client_name)) {
                $trans = $trans->whereHas('loan', function ($query) use ($client_name) {

                    $query->where('clients.client_name', 'LIKE', "%" . $client_name . "%")->leftJoin('clients', 'clients.id', '=', 'loans.client_id');
                });
            }
            if (!empty($contract_id)) {
                $trans = $trans->whereHas('loan', function ($query) use ($contract_id) {
                    $query->where('contract_id', '=', $contract_id);
                });
            }
            if (!empty($category_name)) {
                $trans = $trans->whereHas('loan', function ($query) use ($category_name) {
                    $query->leftJoin('products', 'products.id', '=', 'loans.product_id')
                        ->leftJoin('product_category', 'product_category.id', '=', 'products.category_id')
                        ->where('product_category.category_name', 'LIKE', "%$category_name%");
                });
            }
            if (!empty($type)) {
                $trans = $trans->where('trans_type', 'LIKE', '%' . $type . '%');
            }
            if (!empty($breakdown_type)) {
                $trans->where(strtolower($breakdown_type), '>', 0);
            }
            if (!empty($branch)) {
                $trans = $trans->whereHas('loan', function ($query) use ($branch) {
                    $query->where('company_branch_id', '=', $branch);
                });
            }
            if (!empty($name)) {
                $trans = $trans->whereHas('loan', function ($query) use ($name) {
                    $query->where('users.name', 'LIKE', "%" . $name . "%")->leftJoin('users', 'users.id', '=', 'loans.co');
                });
            }
            if (!empty($by)) {
                $trans = $trans->where('invoice_number', '=', $by);
            }
        }

        if (Request::has('od')) {
            $order_dir = Request::input('od');
            if ($order_dir != 'desc' && $order_dir != 'asc') $order_dir = 'desc';
        }

        $order_class = [
            'invoice_number' => ($order == 'invoice_number') ? 'fa-sort-' . $order_dir : 'fa-sort'
        ];

        $order_url = [
            'invoice_number' => ($order == 'invoice_number' && $order_dir == 'asc') ? $this->setUrl($url, 'o=invoice_number&od=desc') : $this->setUrl($url, 'o=invoice_number&od=asc')
        ];

        $trans = $trans->orderBy($order_type[$order], $order_dir)->orderBy('flag', 'ASC')->paginate($offset);
        $trans->appends($query_url);
        $curreny_arr = [];
        $currency_list = Currency::select('id', 'code')->get();
        foreach ($currency_list as $cur_l) {
            $curreny_arr[$cur_l->id] = $cur_l->code;
        }
        return $this->view('loans.trans_list', ['trans' => $trans, 'start' => $start, 'end' => $end, 'id' => $cid, 'type' => $type,
            'contract_id' => $contract_id, 'by' => $by, 'chk' => $search, 'tid' => $tid, 'branch' => $selBranch, 'branch_id' => $branch,
            'client_name' => $client_name, 'name' => $name, 'category_name' => $category_name, 'breakdown_type' => $breakdown_type,
            'offset' => $offset, 'order_class' => $order_class, 'order_url' => $order_url, 'currency_list' => $curreny_arr]);
    }

    public function get_all_trans()
    {
        $trans = TransactionsRequiry::select(['transactions_requiry.*', 'users.name'])->join('users', 'users.id', '=', 'transactions_requiry.user_id')
            ->with(['loan' => function ($query) {
                $query->select(['loans.id', 'loans.contract_id', 'company_branch.branch_name'])->leftJoin('company_branch', 'company_branch.id', '=', 'loans.company_branch_id');
            }])->get();
        return $this->view('loans.transaction.trans_list_all', ['trans' => $trans]);
    }

    public function get_journal_data(){
        $currency_id = Request::input('currency_id');
        $search_account = Request::input('search_account');
        $contract_id = Request::input('contract_id');
        $currency = CoaCategory::where('currency',$currency_id)->select(['id', 'account_code', 'name', 'currency'])->whereIn('type', [6, 7]);
        if(!empty($search_account)){
            $currency = $currency->where(function($q) use($search_account){
                $q->where('account_code','like','%'.$search_account.'%')
                ->orWhere('name','like','%'.$search_account.'%')
                ->orWhere('currency','like','%'.$search_account.'%');
            });
        }
        $currency = $currency->limit(100)->get();
        if ($id > 0) {
            $trans->where('id', '=', $id);
            $data['id'] = $id;
        }
        $loans = Loan::select('id', 'contract_id')->where(function($query){
            $query->where('con_status','Release')
                ->orWhereNull('con_status');
        });
        if(!empty($contract_id)){
            $loans = $loans->where('contract_id','like','%'.$contract_id.'%');
        }
        $loans = $loans->limit(100)->get();
        $data['loans'] = $loans;
        $data['account'] = $currency;
        return response()->json($data);
    }

    public function getAddJournal($id = 0)
    {
        $data['users'] = User::with('role')->get();
        if (Request::ajax()) {
            if (Request::input('usertype') == true) {
                return $data['users'] = User::with(['role' => function ($q) {
                    $q->where('role_lvl', '>', Auth::user()->role->role_lvl);
                }])->get();
            }
            return $data['users'];
        }
        $trans = TransactionsRequiry::select(['id', 'loan_id', 'trans_type', 'amount'])
            ->with(['loan' => function ($query) {
                $query->select('id', 'contract_id', 'company_branch_id');
            }])->where('flag', '=', 0);

        $data['entry_no'] = JournalRequiry::max('id') + 1;
        $data['account'] = CoaCategory::select(['id', 'account_code', 'name', 'currency'])->whereIn('type', [6, 7])->limit(100)->get();
        if ($id > 0) {
            $trans->where('id', '=', $id);
            $data['id'] = $id;
        }
        $data['branches'] = CompanyBranch::select('id', 'branch_name', 'branch_code')->where('status',1)->orderBy('branch_code', 'ASC')->get();
        $data['trans'] = $trans->get();
        $data['loans'] = Loan::select('id', 'contract_id')->where(function($query){
            $query->where('con_status','Release')
                ->orWhereNull('con_status');
        })->limit(100)->get();

        $data['tellers'] = Teller::select('till_account.account_name as account_name', 'till_account.balance as balance', 'users.id as uid', 'users.name as name',
        'company_branch.branch_name as branch_name', 'company_branch.id as bid', 'currency_id', 'till_account.status','users.company_type','users.allow_postback_date')
        ->join('users', 'users.id', '=', 'till_account.assign_user_id')
        ->join('company_branch', 'company_branch.id', '=', 'till_account.branch_id')
        ->where('assign_user_id', '=', $this->user_id)
        ->first();
        return $this->view('loans.journal', $data);
    }

    final function get_user_referral($rnt)
    {

        if (Request::ajax()) {
            $data = [];

            if ((int)$rnt === 1) {//vendor
                $data['vendor'] = App\Models\Vendor::all();
            }
            if ((int)$rnt === 2) {// customer

                $data['customer'] = App\Models\AccountCustomer::all();
            }
            if ((int)$rnt === 3) {  /// staff

                $data['staff'] = App\Models\Staff::all();
            }

            return $data;
        }
    }

    public function postAddJournal($id = 0)
    {
        DB::beginTransaction();
        $rules = [
            'selTran' => 'required',
            'selType' => 'required',
            'ipDebit' => 'required',
            'ipCredit' => 'required',
            'txtDesc' => 'required'
        ];
        $attr = [
            'selTran' => 'Transaction ID',
            'selType' => 'Transaction Type',
            'ipDebit' => 'Debit Amount',
            'ipCredit' => 'Credit Amount',
            'txtDesc' => 'Description'
        ];

        $data = Request::except(['_token']);
        $validator = Validator::make($data, $rules);
        $validator->setAttributeNames($attr);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        } else {
            $optTranId = Request::input('selTran');
            $optTranType = Request::input('selType');
            $ipDebit = Request::input('ipDebit');
            $ipCredit = Request::input('ipCredit');
            $ipDesc = Request::input('ipDesc');
            $contract_id = Request::input('contract_id');
            $desc = Request::input('txtDesc');
            $branch_code = (Request::input('branch') == "") ? "100" : Request::input('branch');
            $user_id = Auth::user()->id;

            $journal = new JournalRequiry;
            $journal->tran_id = intval($optTranId[0]);
            $journal->invoice_number = Request::input('invoice_number');
            $journal->description = $desc[0];
            $journal->user_id = $user_id;
            $journal->entry_date = Request::input('entry_date');
            $journal->ref_name_id = Request::input('referral_name');
            $journal->ref_name_type = Request::input('ref_name_type');
            $journal->trans_type = 11;//general journal

            if (Request::hasFile('receipt')) {
                if (Request::file('receipt')->isValid()) {
                    if ((Request::file('receipt')->getSize() / 1024 / 1024) > 1) {
                        return redirect()->back()->with('error', 'File size is too large!');
                    }
                    $file = Request::file('receipt');
                    $ext = $file->getClientOriginalExtension();
                    if (in_array(strtolower($ext), ['jpg', 'jpeg', 'bmp', 'png'])) {
                        $image = Image::make($file);
                        $photo_name = uniqid(date('dmY')) . '.jpg';
                        $image->save(public_path('data/loans/receipts') . '/' . $photo_name);
                        $journal->receipt = $photo_name;
                    } else {
                        $fileName = uniqid(date('dmY')) . '.' . $ext;
                        $file->move(public_path('data/loans/receipts'), $fileName);
                        $journal->receipt = $fileName;
                    }
                }
            }

            if ($journal->save()) {
                $this->do_audit($journal->id, Auth::user()->id, '', 'journal_requiry', 0, 'add_journal');
                $tran = TransactionsRequiry::where('id', '=', $optTranId[0])->where('flag', '=', 0)->first();
                if (!empty($tran)) {
                    $tran->flag = 1;
                    $tran->jid = $journal->id;
                    $tran->save();
                }
                for ($k = 0; $k < count($optTranType); $k++) {
                    $jd = new JournalDetail;
                    $jd->journal_id = $journal->id;
                    $jd->coa_id = $optTranType[$k];
                    $jd->reference = $contract_id[$k];
                    $jd->branch_code = $branch_code;
                    $prev_bl = array_fill(0, 2, 0.0);
                    $prev_row = JournalDetail::select('b_debit', 'b_credit')
                        ->where('coa_id', $optTranType[$k])
                        ->orderBy('id', 'desc')
                        ->first();
                    if (!empty($prev_row)) {
                        $prev_bl[0] = $prev_row->b_debit;
                        $prev_bl[1] = $prev_row->b_credit;
                    }
                    $jd->p_debit = $prev_bl[0];
                    $jd->p_credit = $prev_bl[1];
                    $jd->debit = $ipDebit[$k];
                    $jd->credit = $ipCredit[$k];
                    $jd->b_debit = floatval($prev_bl[0]) + floatval($jd->debit);
                    $jd->b_credit = floatval($prev_bl[1]) + floatval($jd->credit);
                    $jd->description = $ipDesc[$k];
                    $jd->save();
                }

                if ($id != 0) {
                    Session::flash('back_saved', "yes");
                    return redirect()->route('journal_entry', [$id]);
                } else {
                    Session::flash('msg', 'Journal Requiry have been saved.');
                    // The $data['trans'] and $data['entry_no'] built here used to be handed to
                    // ->with($data) on the redirect below, which flashes every element into the
                    // session. 'trans' is every transaction with flag = 0 -- 50,838 Eloquent
                    // models, each carrying an eager-loaded loan -- and the session driver is
                    // file, so all of it was serialized to disk on every save. That exhausted
                    // memory and returned the blank page users reported. Nothing ever read it
                    // back: getAddJournal re-queries both values on the redirected-to request,
                    // and no view reads them from the session.
                    $this->notification = new Notification();
                    $res['notify'] = $this->notification->setNotification([
                        Auth::user()->id,
                        '',
                        $journal->id,
                        Request::input('notify_user'),
                        '',
                        'Add_Journal',
                        date("Y-m-d H:m:s", time()),
                        '',
                        ''
                    ]);
                    if (empty($res['notify'])) {
                        return redirect()->back()->with('error', 'We can not send notification please try again!');
                    }
                    DB::commit();
                    return redirect()->back();
                }
            }
        }
        return redirect()->back();
    }

    public function getViewJournal()
    {
        $offset = (Request::has('set_offset')) ? Request::input('set_offset') : 10000;
        $query_arr = array('user_id' => Auth::user()->id, 'branch_id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);
        $selBranch = CompanyBranch::where('status', '=', 1)->get(['id', 'branch_name']);
        $jrs = JournalRequiry::select('id', 'tran_id', 'entry_date', 'user_id', 'description', 'invoice_number', 'receipt', 'is_audit')
            ->where('description', 'NOT LIKE', '%Auto Accrued%');
        if (Request::has('status')) {
            if (Request::input('status') != 2) {
                $status = $data['status'] = Request::input('status');
                $jrs->where('is_audit', '=', $status);
            }
        }
        $jrs->with(['detail' => function ($query) {
            $query->select('id', 'journal_id', 'coa_id', 'debit', 'credit', 'is_audit', 'branch_code')
                ->with(['account' => function ($query) {
                    $query->select('id', 'account_code', 'name', 'currency');
                }]);
        },
            'user' => function ($query) {
                $query->select('id', 'name');
            }])->with('audit');
        if (Request::has('status')) {
            if (Request::input('status') != 2) {
                $status = $data['status'] = Request::input('status');
            }
            $data['start'] = (Request::has('dpStart')) ? date('Y-m-d', strtotime(Request::input('dpStart'))) : date('Y-m-d', strtotime("-90 day"));
            $data['end'] = (Request::has('dpEnd')) ? date('Y-m-d', strtotime(Request::input('dpEnd') . "+1 day")) : date('Y-m-d', strtotime("+1 day"));
            $jrs->where('entry_date', '>=', $data['start']);
            $jrs->where('entry_date', '<', $data['end']);
        } else {
            if (Request::has('dpStart') && Request::has('dpEnd')) {
                $data['start'] = Request::input('dpStart');
                $data['end'] = Request::input('dpEnd');
                $jrs->where('entry_date', '>=', $data['start']);
                $jrs->where('entry_date', '<', date('Y-m-d', strtotime(Request::input('dpEnd') . ' +1 day')));
            } else if (Request::has('dpStart')) {
                $data['start'] = Request::input('dpStart');
                $jrs->where('entry_date', '>=', [$data['start']]);
            } else if (Request::has('dpEnd')) {
                $data['end'] = Request::input('dpEnd');
                $jrs->where('entry_date', '<', date('Y-m-d', strtotime(Request::input('dpEnd') . ' +1 day')));
            } else {
                $data['start'] = date('Y-m-d', strtotime(date('Y-m-d') . ' -1 day'));
                $data['end'] = date('Y-m-d', strtotime(date('Y-m-d') . ' +1 day'));
                $jrs->whereBetween('entry_date', [$data['start'], $data['end']]);
            }
        }
        if (Request::has('id')) {
            $data['tran_id'] = Request::input('id');
            $jrs->where('tran_id', $data['tran_id']);
        }

        if (Request::has('entry_id')) {
            $data['entry_id'] = Request::input('entry_id');
            $jrs->where('id', $data['entry_id']);
        }

        if (Request::has('env_no')) {
            $data['env_no'] = Request::input('env_no');
            $jrs->where('invoice_number', $data['env_no']);
        }

        if (Request::has('ref_id')) {
            $data['reference'] = $ref_id = Request::input('ref_id');
            $jrs->whereHas('detail', function ($query) use ($ref_id) {
                $query->where('reference', '=', $ref_id);
            });
        }
        if ($status != 0) {
            if (Request::has('selBrand')) {
                $sbranch = CompanyBranch::where('id', '=', Request::input('selBrand'))->first();
                $branch = $sbranch->id;
                $branch_code = $sbranch->branch_code;
                $data['branch_name'] = $branch = Request::input('selBrand');
                $jrs->whereHas('detail', function ($query) use ($branch_code) {
                    $query->where('branch_code', '=', $branch_code);
                });
            } else {
                $sbranch = CompanyBranch::where('id', '=', Auth::user()->branch_id)->first();
                $branch = Auth::user()->branch_id;
                $branch_code = $sbranch->branch_code;
                $data['branch_name'] = $sbranch->branch_name;
                $jrs->whereHas('detail', function ($query) use ($branch_code) {
                    $query->where('branch_code', '=', $branch_code);
                });
            }
        }
        if (Request::has('note')) {
            $data['description'] = $note = Request::input('note');
            $jrs->where('description', 'LIKE', "%" . $note . "%");
        }
        $color = Request::input('set_color', null);
        if (!empty($color)) {
            $jrs->orderBy('id', 'DESC');
        }
        $data['jrs'] = $jrs->paginate($offset);
        $currency_list = Currency::select('id', 'code')->get();
        $currency_arr = [];
        foreach ($currency_list as $cur) {
            $currency_arr[$cur->id] = $cur->code;
        }
        $data['currency_arr'] = $currency_arr;
        $data['offset'] = $offset;
        $data['branch'] = $selBranch;
        $data['branch_id'] = $branch;
        // dd($data);
        return $this->view('reports.journal', $data);
    }

    public function getFilterJournal()
    {
        $data['account'] = CoaCategory::select(['id', 'account_code', 'name', 'currency'])->where('currency',2)->whereIn('type', [6])->get();
        $offset = (Request::has('set_offset')) ? Request::input('set_offset') : 20;
        $query_arr = array('user_id' => Auth::user()->id, 'branch_id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);
        $selBranch = CompanyBranch::where('status', '=', 1)->get(['id', 'branch_name']);
        $data['start'] = (Request::has('dpStart')) ? date('Y-m-d', strtotime(Request::input('dpStart'))) : date('Y-m-d');
        $data['end'] = (Request::has('dpEnd')) ? date('Y-m-d', strtotime(Request::input('dpEnd'))) : date('Y-m-d');

        $data['coa_category'] = Request::input('coa_category')?Request::input('coa_category'):210450;

        $jrs = JournalRequiry::select('journal_requiry.*')
        ->join('journal_detail', 'journal_requiry.id', '=', 'journal_detail.journal_id')
        ->join('coa_categories', 'coa_categories.id', '=', 'journal_detail.coa_id')
        ->where('journal_requiry.description', 'NOT LIKE', '%Auto Accrued%')
        ->where('coa_categories.id', $data['coa_category'])
        ->whereDate('journal_requiry.created_at', '>=', $data['start'])
        ->whereDate('journal_requiry.created_at', '<=', $data['end']);


        if (Request::has('status')) {
            if (Request::input('status') != 2) {
                $status = $data['status'] = Request::input('status');
                $jrs->where('journal_requiry.is_audit', '=', $status);
            }
        }
        $jrs->with(['detail' => function ($query) {
            $query->select('id', 'journal_id', 'coa_id', 'debit', 'credit', 'is_audit', 'branch_code')
                ->with(['account' => function ($query) {
                    $query->select('id', 'account_code', 'name', 'currency');
                }]);
        },
            'user' => function ($query) {
                $query->select('id', 'name');
            }])->with('audit');


        if (Request::has('note')) {
            $data['description'] = $note = Request::input('note');
            $jrs->where('journal_requiry.description', 'LIKE', "%" . $note . "%");
            
        }
        $color = Request::input('set_color', null);
        if (!empty($color)) {
            $jrs->orderBy('id', 'DESC');
        }
        $data['jrs'] = $jrs->paginate($offset);
        $currency_list = Currency::select('id', 'code')->get();
        $currency_arr = [];
        foreach ($currency_list as $cur) {
            $currency_arr[$cur->id] = $cur->code;
        }
        $data['currency_arr'] = $currency_arr;
        $data['offset'] = $offset;
        $data['branch'] = $selBranch;
        $data['branch_id'] = $branch;
        // dd($data);
        return $this->view('reports.journal_filter', $data);
    }

    public function getListLoan()
    {
        //chuch add flag
        //Chamroeun add Accrued Interest Execution
        DB::enableQueryLog();
        $flag = Request::has('flag') ? Request::input('flag') : null;

        $offset = isset($_GET['offset']) ? $_GET['offset'] : 15;
        $system_date = SystemDate::orderBy('id','DESC')->first();
        if($system_date){
            if($system_date->is_accrued_interest == 1 &&  $system_date->is_auto_payment == 1){
                $date = $system_date->next_date;
            }else{
                $date = $system_date->corrent_date;
            }
        }else{
            $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        }
        $data['start'] = Request::has('start') ? Request::input('start') : date('Y-m-d');
        $data['end'] = Request::has('end') ? Request::input('end') : date('Y-m-d');
        $query_arr = array('id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);
        //$B1 = new CompanyBranch();
        //$B1 = $this->getBranchByUser($B1, 'id', $query_arr);
        $data['company_branch'] = CompanyBranch::where('status', '=', 1)->get(['id', 'branch_name', 'branch_code']);
        // Accrued Interest Execution
        if ($flag == 2) {
            $SystemDate = SystemDate::where('corrent_date',$date)->first();
            if($SystemDate){
                    // $SystemDate->is_accrued_interest = 1;
                    $SystemDate->accrued_interest_by = Auth::user()->username;
                    $SystemDate->accrued_interest_date = date('Y-m-d H:i:s');
                    $SystemDate->save();
            }else{
                $insert_date = new SystemDate;
                $insert_date->previous_date = date('Y-m-d', strtotime($date.'-1 day'));
                $insert_date->corrent_date = date('Y-m-d', strtotime($date));
                $insert_date->next_date = date('Y-m-d', strtotime($date.'+1 day'));
                $insert_date->is_accrued_interest = 1;
                $insert_date->accrued_interest_by = Auth::user()->username;
                $insert_date->accrued_interest_date = date('Y-m-d H:i:s');
                $insert_date->save();
            }
            self::exe_daily_accrued_interest($date);
        }
        /*
                $result = ClientLoanAccounts::select('account_name', 'account_no', 'loans.contract_id', 'loans.disburse_date', 'client_loan_accounts.balance',
                    'interest_rate', 'loan_amount', 'air_id', 'coa_id','loans.id', 'loan_type', 'start_date', 'client_loan_accounts.loan_ref','client_loan_accounts.status' )
                    ->join('loans', 'client_loan_accounts.id', '=', 'loans.loan_account_id')
                    ->with(['journal_detail_air' => function ($q) {
                        $q->select('journal_detail.id', 'debit', 'p_debit', 'p_credit', 'b_credit', 'b_debit', 'coa_id', 'journal_requiry.description', 'entry_date')
                            ->join('journal_requiry', 'journal_requiry.id', '=', 'journal_id')
                            ->orderBy('id', 'DESC');
                    }]);
                $result = $result->whereBetween('client_loan_accounts.status', array(2, 6));
        */
        $result = ClientLoanAccounts::whereBetween('status', array(2, 9));
        // join with tb_loan
        $result = $result->with(['loan' => function ($q) {
            $q->with('schedule', 'transaction', 'collateral', 'product_type');
            // ->with(['collateral'=>function($que){
            //   $que->select('id', 'loan_id','collateral_type')->selectRaw('sum(collateral_value) as t_value')->first();
            // }]);
        }, 'currencies']);
        /*
                $result1 = ClientLoanAccounts::select('')
                            ->join('journal_detail','journal_detail.id','=','coa_id')
                    ->where('status', '>', 1)
        */
        $code = null;
        if (Request::has('code')) {
            $code = Request::input('code');
            $data['code'] = $code;
            $result->where(function ($q) use ($code) {
                $q->where('account_name', 'like', "%" . $code . "%")
                    ->orWhere('loan_ref', '=', $code)
                    ->orWhere('account_no', 'like', "%" . $code . "%");
            });
        }

        if (Request::has('company_branch_code')) {
            $company_branch_code = Request::input('company_branch_code');
            $data['company_branch_code'] = $company_branch_code;
            $result->where('branch', '=', $company_branch_code);
            $data['branch_name'] = CompanyBranch::where('branch_code', $company_branch_code)->first()->branch_name;
        } else {
            $data['company_branch_code'] = CompanyBranch::select('id', 'branch_code', 'branch_name')->get();
        }

        if (Request::has('cur')) {
            $cur = Request::input('cur');
            if ($cur != "100") { // "-" -> "100"
                $data['cur'] = $cur;
                $result->where('currency', '=', $cur);
            }
        }
        $dc_result = [];
        $result = $result->get();
        if ($flag == 1) {
            $dc_result = journalCalculate($result, $data['start'], $data['end'], "coa");
        } else {
            $dc_result = journalCalculate($result, $data['start'], $data['end'], "air");
        }

        $data['result'] = $result;
        $data['dc_result'] = $dc_result;
        if ($flag == 2) return redirect()->route('list_loan');
        $data['flag'] = $flag;

        return $this->view('loans.list_loan', $data, ['offset' => $offset]);
//        return $this->view('loans.list_loan', ['data' => $data]);
    }

    public function getListLoanAccrued()
    {
        DB::enableQueryLog();
        $flag = Request::has('flag') ? Request::input('flag') : null;

        $offset = isset($_GET['offset']) ? $_GET['offset'] : 15;
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        if(!($offset > 0)){
            $offset = 100;
        }
        $offset = 100;

        $system_date = SystemDate::orderBy('id','DESC')->first();
        if($system_date){
            if($system_date->is_accrued_interest == 1 &&  $system_date->is_auto_payment == 1){
                $date = $system_date->next_date;
            }else{
                $date = $system_date->corrent_date;
            }
        }else{
            $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        }
        
        if ($flag == 2) {
            $SystemDate = SystemDate::where('corrent_date',$date)->first();
            if($SystemDate){
                    // $SystemDate->is_accrued_interest = 1;
                    $SystemDate->accrued_interest_by = Auth::user()->username;
                    $SystemDate->accrued_interest_date = date('Y-m-d H:i:s');
                    $SystemDate->save();
            }else{
                $insert_date = new SystemDate;
                $insert_date->previous_date = date('Y-m-d', strtotime($date.'-1 day'));
                $insert_date->corrent_date = date('Y-m-d', strtotime($date));
                $insert_date->next_date = date('Y-m-d', strtotime($date.'+1 day'));
                $insert_date->is_accrued_interest = 1;
                $insert_date->accrued_interest_by = Auth::user()->username;
                $insert_date->accrued_interest_date = date('Y-m-d H:i:s');
                $insert_date->save();
            }
            self::exe_daily_accrued_interest($date,$page);
        }
    }


    public function exe_daily_accrued_interest($date = null,$page = 2)
    {
        if (is_null($date)) $date = date('Y-m-d');
        $offset = 100;
        $loans = Loan::select('loans.id',
                            'loan_amount',
                            'interest_rate',
                            'company_branch_id',
                            'loan_account_id',
                            'contract_id',
                            'loans.status',
                            'disburse_date',
                            'client_loan_accounts.status as lc_status',
                            'air_id',
                            'int_inc_id',
                            'coa_id',
                            'sus_id',
                            'repayment_type',
                            'start_date',
                            'transfer_date',
                            'original_amount',
                            'rate_type',
                            'client_loan_accounts.balance')
                    // ->join('loans', 'client_loan_accounts.id', '=', 'loans.loan_account_id')
                    ->join('client_loan_accounts', 'client_loan_accounts.id', '=', 'loans.loan_account_id')
                    ->with(['schedule', 'transaction'])
                    ->with('coa_journal_detail')
                    ->whereIn('loans.status', [3, 8])
                    ->orderBy('loans.id', 'ASC')->paginate($offset);
        // Only the last accrual date is read from the AIR journal rows, so get it in SQL instead of
        // eager-loading them (~210k rows / ~900 MB for page 24 on UAT; the request died silently and the
        // Accrued Verify page never advanced). Same match rule as getLastAccruedDate().
        $air_ids = [];
        foreach ($loans as $loan) {
            if ($loan->air_id) $air_ids[] = intval($loan->air_id);
        }
        $last_acc = [];
        if (count($air_ids) > 0) {
            $rows = DB::select("SELECT jd.coa_id,
                                       MAX(CASE WHEN jd.debit > 0
                                                 AND (jr.description LIKE '%Auto Accrued Interest%' OR jr.description LIKE '%provision%')
                                                THEN jr.entry_date END) AS last_acc
                                FROM tb_journal_detail jd
                                JOIN tb_journal_requiry jr ON jr.id = jd.journal_id
                                WHERE jd.coa_id IN (" . implode(',', $air_ids) . ")
                                GROUP BY jd.coa_id");
            foreach ($rows as $row) {
                $last_acc[$row->coa_id] = $row->last_acc;
            }
        }
        $user_id = Auth::user()->id;
        $branch_id = Auth::user()->branch_id;
        foreach ($loans as $loan) {
            $schedule = $loan->schedule;
            $air_id = $loan->air_id;
            // substandard needs to be accrued with interest in suspense
            if ($loan->lc_status > GENERAL_LC_STATUS) {
                $int_inc_id = $loan->sus_id;
            } else {
                $int_inc_id = $loan->int_inc_id;
            }
            // AIR
            // one pre-aggregated row stands in for the loan's accrual rows
            $acc_rows = (isset($last_acc[$loan->air_id]) && $last_acc[$loan->air_id]) ? [(object)['debit' => 1, 'description' => 'Auto Accrued Interest', 'entry_date' => $last_acc[$loan->air_id]]] : [];
            $last_accrued_date = getLastAccruedDate($acc_rows, $loan->disburse_date, $loan->transfer_date);
            //$last_balance = floatval($loan->transaction[count($loan->transaction)-1]->balance);
            $t_debit = 0;
            $t_credit = 0;
            $last_balance = 0;
            if ($loan->rate_type == "Flat") {
                $last_balance = $loan->original_amount;
            } else {
                if (count($loan->coa_journal_detail) > 0) {
                    foreach ($loan->coa_journal_detail as $tr) {
                        //$last_balance += $last_balance + $tr->debit - $tr->credit;
                        $t_debit += doubleval($tr->debit);
                        $t_credit += doubleval($tr->credit);
                    }
                    $last_balance = $t_debit - $t_credit;
                } else {
                    $last_balance = $loan->balance;
                }
            }
            $days = date_dif($last_accrued_date, $date, 1, false);
            if ($days <= 0) $days = 0;
            // day loop until current selected date
            $add_str = '';
            $air_amount = round($last_balance * floatval($loan->interest_rate) * 12 / 36000, 4); // accrued day by day
            if ($air_amount <= 0) {
                continue;
            }
            for ($i = 1; $i <= $days; $i++) {
                $add_str = '+' . $i . ' day';
                $record_date = date('Y-m-d H:i:s', strtotime($last_accrued_date . $add_str));
                //$record_date = date('Y-m-d H:i:s', strtotime($acc_date));
                // debit : AIR & credit : Income
                $journal_arr = [];
                array_push($journal_arr, [$air_id, $air_amount, "", $int_inc_id, $air_amount, "", ""]);
                // record_journal($loan, $record_date, "Auto Accrued Interest", null, $journal_arr, $branch_id, $user_id);
                record_journal($loan, $record_date, "Auto Accrued Interest", null, $journal_arr, $loan->company_branch_id, $user_id);
            }
        }
        if($loans->count() > 0){
            $url = asset('loans/accrued_verify?sort=loans.id&flag=2&page='.($page+1).'&date='.(date('Y-m-d',strtotime($date))).'');
            $datas['success'] = 1;
            $datas['page'] = $page + 1;
            $datas['url'] = $url;
            echo json_encode($datas);
        }else{
            $url = '';
            $datas['success'] = 0;
            $datas['page'] = $page + 1;
            $datas['url'] = $url;
            // dd($datas);
            echo json_encode($datas);
        }
    }


    public function getJournal($id = 0)
    {
        if ($id > 0) {
            $journal = JournalRequiry::where('tran_id', '=', $id)
                ->with(['transaction' => function ($query) {
                    $query->select(['id', 'loan_id', 'trans_date'])
                        ->with(['loan' => function ($query) {
                            $query->select(['id', 'company_branch_id', 'user_id'])
                                ->with(['branch' => function ($query) {
                                    $query->select('id', 'branch_name');
                                }]);
                        }]);
                }, 'user' => function ($query) {
                    $query->select('id', 'name');
                }])
                ->with(['detail' => function ($query) {
                    $query->with(['account']);
                }])
                ->get();
            $data['id'] = $id;
            if (count($journal) == 0) {
                $data['no_journal'] = true;
            }
            $data['journals'] = $journal;
            return $this->view('loans.list_journal', $data);
        }
        return redirect()->back();
    }

    public function contract_id_check()
    {
        if (Request::ajax()) {
            $contract_id = Request::input('contract_id');
            if (!empty($contract_id)) {
                $contract = Loan::select('id', 'contract_id')->where('contract_id', '=', $contract_id)->orderBy('id', 'DESC')->first();
                if (!empty($contract)) {
                    $id = Request::input('id', 0);
                    if ($id > 0 && $contract->id == $id) {
                        return "true";
                    }
                    return "false";
                } else {
                    return "true";
                }
            }
        }
        return "false";
    }

    public function getLoanStatus()
    {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 15;
        $query_arr = array('user_id' => Auth::user()->id, 'company_branch_id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);
        $B0 = new Loan();
        //$B0 = $this->getUserByBranch($B0, 'company_branch_id', $query_arr);
        $loan = $B0->selectRaw('
                tb_loans.id,
                tb_loans.contract_id,
                tb_loans.start_date,
                tb_loans.loan_type,
                tb_loans.loan_amount,
                tb_loans.original_amount,
                tb_loans.interest_rate,
                tb_loans.loan_account_id,
                tb_loans.drawdown_acc,
                tb_loans.loan_penalty_type,
                tb_loans.penalty_rate1,
                tb_loans.unit_sale_price,
                tb_loans.amount_discount_payment_option,
                tb_loans.discount_payment_option,
                tb_loans.discount_other,
                tb_loans.down_payment_value,
                tb_loans.status,
                tb_loans.loan_duration,
                tb_loans.submitted_on,
                tb_loans.disburse_date,
                tb_loans.rejected_date,
                tb_loans.client_id,
                tb_loans.contract_date,
                tb_loans.created_at,
                tb_loans.updated_at,
                tb_clients.client_name,
                tb_clients.client_type,
                tb_projects.short_code,
                tb_unit_types.name,
                tb_units.code,
                tb_loans.settlement_date,
                tb_loans.rate_type,
                tb_currency.code AS currency_code,
                tb_repayment_schedule.schedule_date
            '
            )->with(['approval' => function ($query) {
                $query->select('id', 'loan_id', 'approval_date');
            }, 
            'client_loan_account',
            'payoff']
        )
        ->join('client_loan_accounts','loans.loan_account_id','=','client_loan_accounts.id')
        ->join('currency','client_loan_accounts.currency','=','currency.id')
        ->leftJoin('last_repay_schedule as lrs', 'lrs.loan_id', '=', 'loans.id')
        ->leftJoin('repayment_schedule', 'lrs.id', '=', 'repayment_schedule.id')
        ->leftJoin('clients', 'clients.id', '=', 'loans.client_id')
        ->leftJoin('projects','projects.id','=','loans.project_id')
        ->leftJoin('unit_types','unit_types.id','=','loans.unit_type_id')
        ->leftJoin('units','units.id','=','loans.unit_id');
        $loan = $loan->where('workflow_status', 'approve');


        $contract_id = null;
        $query_url = [];
        if (Request::has('contract_id')) {
            $contract_id = Request::input('contract_id');
            $loan = $loan->where('contract_id', '=', $contract_id);
            $query_url['contract_id'] = $contract_id;
        }
        if (Request::has('client_name')) {
            $client_name = Request::input('client_name');
            $loan = $loan->where('clients.client_name', 'LIKE', '%' . $client_name . '%');
            $query_url['client_name'] = $client_name;
        }
        $status = '';
        if (Request::has('status')) {
            $status = Request::input('status');
            $loan = $loan->where('loans.status', '=', $status);
            $query_url['status'] = $status;
        }
        $project_id = null;
        if(Request::has('project_id')){
            $project_id = Request::input('project_id');
            $project = Project::find($project_id);
            $loan = $loan->where('loans.project_id',$project_id);
        }
        $unit_type_id = null;
        if(Request::has('unit_type_id')){
            $unit_type_id = Request::input('unit_type_id');
            $unit_type = UnitType::find($unit_type_id);
            $loan = $loan->where('loans.unit_type_id',$unit_type_id);
        }
        $unit_id = null;
        if(Request::has('unit_id')){
            $unit_id = Request::input('unit_id');
            $unit = Unit::find($unit_id);
            $loan = $loan->where('loans.unit_id',$unit_id);
        }
        // $now = Date('Y-m-d');
        $contract_date = Request::input('contract_date');
        $date = Request::input('date');
        if(Request::has('contract_date')){
            $loan = $loan->where('loans.contract_date',$contract_date);
        }
        if(Request::has('date')){
            $loan = $loan->whereDate('loans.created_at','=',$date);
        }

        $loan = $loan->paginate($offset)->setPath('loan_status_management?client_name='.$client_name.'&contract_id='.$contract_id.'&status='.$status.'&offset='.$offset);//take($offset)->get();
        // dd($loan);

        // $loan->each->append($query_url);
        $product_type = Product_type::get();
        if(Request::input('is_excel') == 1 || Request::input('is_csv') == 1){
            $xlsx = 'xlsx';
            if(Request::has('is_csv') == 1){
                $xlsx = 'csv';
            }
            return Excel::create('loans-'.date('d-M-Y'), function($excel) use ($loan,$product_type) {
                $excel->sheet('mySheet', function($sheet) use ($loan,$product_type)
                {
                    $sheet->loadView('exports.loans.loan_status_management',['loans' => $loan,'product_type'=>$product_type]);
                });
            })->download($xlsx);
        }
        return $this->view('loans.loan_status_management', [
            'loans' => $loan, 'status' => $status,'contract_id' => $contract_id,
            'project_id' => $project_id,'project' => $project, 
            'unit_type_id' => $unit_type_id,'unit_type' => $unit_type,
            'unit_id' => $unit_id,'unit' => $unit,'contract_date' => $contract_date,'date' => $date,
            'offset' => $offset, 'product_type' => $product_type,'client_name'=>$client_name]);
    }

    public function get_project_unit_unittype(){
        $project    = new Project;
        $unit       = new Unit;
        $unit_type  = new UnitType;
        if(Request::has('search')){
            $project = $project->where(function($q){
                $q->where('dealer','like','%'.Request::input('search').'%')
                ->orwhere('dealer_en','like','%'.Request::input('search').'%')
                ->orwhere('dynamic_code','like','%'.Request::input('search').'%')
                ->orwhere('short_code','like','%'.Request::input('search').'%');
            });
        }

        if(Request::has('search_unit_type')){
            $unit_type = $unit_type->where(function($q){
                $q->where('name','like','%'.Request::input('search_unit_type').'%')
                ->orWhere('short_code','like','%'.Request::input('search_unit_type').'%');
            });
        }

        if(Request::has('search_unit')){
            $unit = $unit->where(function($q){
                $q->where('code','like','%'.Request::input('search_unit').'%')
                ->orWhere('price','like','%'.Request::input('search_unit').'%');
            });
        }

        $project    = $project->limit(100)->get();
        $unit       = $unit->limit(100)->get();
        $unit_type  = $unit_type->limit(100)->get();
        $data['project']    = $project;
        $data['unit']       = $unit;
        $data['unit_type']  = $unit_type;
        return response()->json($data);
    }

    public function get_project_unit_unittype_with_loan(){
        $project    = new Project;
        $u= new Unit;
        // loan_status comes along so the picker can label each unit; the join is already here, so it
        // costs nothing. Callers that don't want it just ignore the extra column.
        // Any unit that has ever carried a loan, whatever state that loan is in — the picker prints the
        // status next to each one, so Write-Off / Terminate / Rejected are visible rather than hidden.
        // Was whereIn(3,8,9,10), which listed 12,012 units; this lists 14,877. Still an inner-style join
        // on purpose: the 6,210 units with no loan at all would have no status to show.
        $unit = $u->selectRaw('tb_units.id, tb_units.code, tb_units.price, tb_loans.status as loan_status')
        ->leftJoin('loans','units.id','=','loans.unit_id')
        ->whereNotNull('loans.status');

        


        $unit_type  = new UnitType;
        if(Request::has('search')){
            $project = $project->where(function($q){
                $q->where('dealer','like','%'.Request::input('search').'%')
                ->orwhere('dealer_en','like','%'.Request::input('search').'%')
                ->orwhere('dynamic_code','like','%'.Request::input('search').'%')
                ->orwhere('short_code','like','%'.Request::input('search').'%');
            });
        }

        if(Request::has('search_unit_type')){
            $unit_type = $unit_type->where(function($q){
                $q->where('name','like','%'.Request::input('search_unit_type').'%')
                ->orWhere('short_code','like','%'.Request::input('search_unit_type').'%');
            });
        }

        if(Request::has('search_unit')){
            $unit = $unit->where('units.code','like','%'.Request::input('search_unit').'%');
        }

        // Only the columns a select2 picker needs. SELECT * on all three tables returned 356 kB per
        // keystroke (unit_types alone was 268 kB of 35-column rows the picker never reads), which the
        // 3-second ajax timeout in the blade could not finish over the local SSH tunnel — every request
        // came back "(canceled)" at 3.01s. Trimmed this is ~13 kB.
        $project    = $project->select('id','short_code','dealer','dealer_en','dynamic_code')->limit(100)->get();
        $unit       = $unit->limit(100)->get();
        $unit_type  = $unit_type->select('id','name','short_code')->limit(100)->get();
        $data['project']    = $project;
        $data['unit']       = $unit;
        $data['unit_type']  = $unit_type;
        return response()->json($data);
    }

    public function get_project_unit_unittype_with_loan_all_status(){
        $project    = new Project;
        $u= new Unit;
        $unit = $u->selectRaw('tb_units.*')       
        ->leftJoin('loans','units.id','=','loans.unit_id')
        ->whereIn('loans.status', [3,4,5,6,7,8,9,10,11]);

        


        $unit_type  = new UnitType;
        if(Request::has('search')){
            $project = $project->where(function($q){
                $q->where('dealer','like','%'.Request::input('search').'%')
                ->orwhere('dealer_en','like','%'.Request::input('search').'%')
                ->orwhere('dynamic_code','like','%'.Request::input('search').'%')
                ->orwhere('short_code','like','%'.Request::input('search').'%');
            });
        }

        if(Request::has('search_unit_type')){
            $unit_type = $unit_type->where(function($q){
                $q->where('name','like','%'.Request::input('search_unit_type').'%')
                ->orWhere('short_code','like','%'.Request::input('search_unit_type').'%');
            });
        }

        if(Request::has('search_unit')){
            $unit = $unit->where('units.code','like','%'.Request::input('search_unit').'%');
        }

        $project    = $project->limit(100)->get();
        $unit       = $unit->limit(100)->get();
        $unit_type  = $unit_type->limit(100)->get();
        $data['project']    = $project;
        $data['unit']       = $unit;
        $data['unit_type']  = $unit_type;
        return response()->json($data);
    }

    


    public function getRejectLoan($id = 0)
    {

        if ($id > 0) {
            $loan = Loan::where('id', '=', $id);
            if ($loan->count() > 0) {
                $find_unit_code = Loan::where('id', '=', $id)->get();
                $unit_code = Unit::find($find_unit_code[0]->unit_id);
                return $this->view('loans.reject', ['id' => $loan->first()->id,'unit_code'=>$unit_code]);
            }
        }
        return redirect()->back();
    }

    public function postRejectLoan($id = 0)
    {
        if ($id > 0) {
            $validator = Validator::make(Request::except('_token', 'note'), ['reject_on' => 'required|date']);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator);
            } else {
                $loan = Loan::where('id', '=', $id)->first();
                if (!empty($loan)) {
                    $loan->status = 4;
                    $loan->workflow_status = 'reject';
                    $loan->rejected_date = Request::input('reject_on');
                    $loan->rejected_note = Request::input('note');
                    $loan->rejected_byuserid = Auth::user()->id;
                    if($loan->save()) {
                        
                        /* Update Sale Order */
                        $loan_acc = ClientLoanAccounts::select(['id', 'activated_on', 'status', 'currency', 'account_name', 'client_id'])->where('id', '=', $loan->loan_account_id)->first();
                        if($loan_acc){
                            $dd = DrawdownAccounts::select('sale_id')->where('client_id', '=', $loan_acc->client_id)->where('client_loan_id',$loan_acc->id)->first();
                            if($dd){
                                if($dd->sale_id){
                                    $sale = Sale::where('id',$dd->sale_id)->first();
                                    if($sale){
                                        $sale->status = 'Canceled';
                                        $sale->remark =  Request::input('note');                        
                                        $sale->save();
                                    }
                                }
    
                            }

                        }
                        /* End Updat Sale Order */
                        
                        $this->userActivity($loan->rejected_byuserid, $id, 6, 'Reject Loan');
                        $loan = new LoanStatus;
                        $loan->loan_id = $id;
                        $loan->description = Request::input('note');
                        $loan->created_by = Auth::user()->id;
                        $loan->save();
                    return redirect()->route('loan_detail', [$id])->with(['msg' => 'Reject success']);
                    }
                }
            }
        }
        return redirect()->back();
    }

    public function update_parc()
    {
        if (Request::ajax()) {
            $parc = Request::input('parc_step', 0);
            $id = Request::input('parc_id', 0);
            $loan = Loan::where('id', $id)->first(['id', 'parc_step']);
            if (!empty($loan)) {
                $loan->parc_step = $parc;
                if ($loan->save()) {
                    return ['status' => true];
                }
            }
            return ['status' => false];
        }
        return redirect()->back();
    }

    public function status()
    {

    }

    //chuch
    function pop_retrieve_date()
    {
        if (Request::has('retrieve_type') && Request::input('retrieve_type') == 1) {
            LoanCollateral::where('id', Request::input('retrieve_id'))->update(array('retrieve_date' => Request::input('retrieve_date')));
        } elseif (Request::has('retrieve_type') && Request::input('retrieve_type') == 2) {
            GuarantorCollateral::where('id', Request::input('retrieve_id'))->update(array('retrieve_date' => Request::input('retrieve_date')));
        }
        return redirect()->back();
    }

    function getDisburseType()
    {
        $type = Request::input('select_type');
        $currency = floatval(Request::input('currency'));
        $coa = CoaCategory::select('id', 'account_code', 'name')->where('type', 6)->where('currency', $currency);
        $type_str = '';
        if ($type == 1) {
            $type_str = 'Cash in Vault';

        } elseif ($type == 2) {
            $type_str = 'Current Accounts with RHB Bank(1010001000019913)';
        } elseif ($type == 3) {
            $type_str = 'Demand and Savings Deposits with PPCB bank';
        } elseif ($type == 4) {
            $type_str = 'Current Accounts with ABA Bank(000192302)';
        }elseif($type == 1000){
            // $type_str = 'Project Loan';
            $type_str = 'Deposit Account (All Deposit)';
        }
        $coa = $coa->where('name', 'like', '%' . $type_str . '%');
        $coa = $coa->first();
        $data = array('id' => $coa->id, 'account_code' => $coa->account_code, 'name' => $coa->name);

        echo json_encode($data);
    }


    /* chuch Accrued verify*/
    public function accrued_verify()
    {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 100;
        $offset = 100;
        
        $pagi = isset($_GET['page']) ? ($_GET['page'] - 1) * $offset + 1 : 1;
        $flag = isset($_GET['flag']) ? $_GET['flag'] : 0;
        // $verify_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        $system_date = SystemDate::orderBy('id','DESC')->first();
        if($system_date){
            if($system_date->is_accrued_interest == 1 &&  $system_date->is_auto_payment == 1){
                $verify_date = $system_date->next_date;
            }else{
                $verify_date = $system_date->corrent_date;
            }
        }else{
            $verify_date = date('Y-m-d');
        }

        $result = ClientLoanAccounts::select('client_loan_accounts.account_name',
                                            'client_loan_accounts.account_no',
                                            'loans.contract_id',
                                            'loans.disburse_date',
                                            'loans.start_payment_date',
                                            'client_loan_accounts.balance',
                                            'loans.interest_rate',
                                            'loans.loan_amount',
                                            'client_loan_accounts.air_id',
                                            'client_loan_accounts.coa_id',
                                            'loans.id',
                                            'loans.loan_type',
                                            'loans.start_date',
                                            'loans.transfer_date',
                                            'loans.original_amount',
                                            'loans.rate_type')
            ->join('loans', 'client_loan_accounts.id', '=', 'loans.loan_account_id')
            ->with('schedule')
            ->whereIn('loans.status', [3, 8])
            // ->where('loans.contract_id','TGL2021/009')
            ->orderBy('loans.id', 'ASC')->paginate($offset);
        // The view only needs, per loan, the last accrual date and the AIR debit/credit totals.
        // Eager-loading every journal_detail row of every AIR account put ~210k Eloquent models in
        // memory for page 24 (UAT) and killed the request (blank 500, error_reporting(0)), so
        // aggregate in SQL instead. Same rows, same match rule as getLastAccruedDate().
        $air_ids = [];
        foreach ($result as $r) {
            if ($r->air_id) $air_ids[] = intval($r->air_id);
        }
        $air_sum = [];
        if (count($air_ids) > 0) {
            $rows = DB::select("SELECT jd.coa_id,
                                       SUM(jd.debit) AS p_debit,
                                       SUM(jd.credit) AS p_credit,
                                       MAX(CASE WHEN jd.debit > 0
                                                 AND (jr.description LIKE '%Auto Accrued Interest%' OR jr.description LIKE '%provision%')
                                                THEN jr.entry_date END) AS last_acc
                                FROM tb_journal_detail jd
                                JOIN tb_journal_requiry jr ON jr.id = jd.journal_id
                                WHERE jd.coa_id IN (" . implode(',', $air_ids) . ")
                                GROUP BY jd.coa_id");
            foreach ($rows as $row) {
                $air_sum[$row->coa_id] = $row;
            }
        }
        $data['air_sum'] = $air_sum;
        $data['loans'] = $result;
        $data['pagi'] = $pagi;
        $data['page'] = $_GET['page'];
        $data['flag'] = $flag;
        $data['verify_date'] = $verify_date;
        return $this->view('loans.accrued_verify', $data, ['offset' => $offset]);
    }


    //chuch save draft loan
    public function draft_loan()
    {
        $data['loans'] = SaveDraftLoan::where('users_id', Auth::user()->id)->get();
        return $this->view('loans.draft_loan', $data);
    }

    public function save_draft()
    {
        exit();
        //delete old draft
        $client_id = Request::input('client_id');
        SaveDraftLoan::where('client_id', $client_id)->delete();

        $loan = new SaveDraftLoan();
        $loan->user_id = Auth::user()->id;
        $loan->url = Request::input('url');
        $loan->loan_account_id = Request::input('loan_account_id');
        $loan->client_id = Request::input('client_id');
        $loan->contract_id = Request::input('contract_id');
        $loan->product_id = Request::input('product_id');
        $loan->loan_type = Request::input('loan_type');
        $loan->submitted_on = Request::input('submitted_on');
        $loan->contract_date = Request::input('contract_date');
        $loan->sell_price = Request::input('sell_price');
        $loan->down_payment = Request::input('down_payment');
        $loan->loan_amount = Request::input('loan_amount');
        $loan->loan_duration = Request::input('loan_duration');
        $loan->interest_rate = Request::input('interest_rate');
        $loan->penalty_rate_type = Request::input('penalty_rate_type');
        $loan->penalty_period1 = Request::input('penalty_period1');
        $loan->payoff_period1 = Request::input('payoff_period1');
        $loan->payoff_period2 = Request::input('payoff_period2');
        $loan->penalty_rate1 = Request::input('penalty_rate1');
        $loan->pay_off_rate1 = Request::input('pay_off_rate1');
        $loan->pay_off_rate2 = Request::input('pay_off_rate2');
        $loan->client_name = Request::input('client_name');
        $loan->product_name = Request::input('product_name');
        $loan->company_branch_id = Request::input('company_branch_id');
        $loan->co = Request::input('co');
        $loan->doc_location = Request::input('doc_location');
        $loan->loan_purpose = Request::input('loan_purpose');
        $loan->dsr = Request::input('dsr');
        $loan->mof = Request::input('mof');
        $loan->days_of_month = Request::input('days_of_month');
        $loan->holiday_flag = Request::input('holiday_flag');
        $loan->start_date = Request::input('start_date');
        $loan->repayment_type = Request::input('repayment_type');
        $loan->save();
    }

    public function list_drawdown_account()
    {
        // $dd = DrawdownAccounts::select('account_name','account_no', 'currency', 'drawdown_account.coa_id', 'balance', 'journal_detail.credit', 'journal_detail.debit')
        //	->Leftjoin('journal_detail', 'drawdown_account.coa_id', '=', 'journal_detail.coa_id')

        $offset = Request::has('set_offset') ? Request::input('set_offset') : 15;
        $from_date = Request::input('t_from')?Request::input('t_from'):date("Y-m-d");
        $to_date = Request::input('t_to')?Request::input('t_to'):date("Y-m-d");
        $as_of = Request::has('dpEnd') ? Request::input('dpEnd') : date("Y-m-d");
        $dd = DrawdownAccounts::select('drawdown_account.*','projects.short_code as project','unit_types.name as unit_type','units.code as unit')
        ->leftJoin('projects','projects.id','=','drawdown_account.project_id')
        ->leftJoin('unit_types','unit_types.id','=','drawdown_account.unit_type_id')
        ->leftJoin('units','units.id','=','drawdown_account.unit_id')
        ->whereDate('drawdown_account.created_on','>=',$from_date)->whereDate('drawdown_account.created_on','<=',$to_date);


        $data['branches'] = CompanyBranch::where('status', 1)->get();
    	$data['projects'] = Project::select('id','dealer','short_code')->get();

        $data['selopt'] = 1;
        $data['flash_val'] = Request::input('iptValue');
        $data['selopt'] = Request::input('selOpt');
        // if(Request::input('selOpt') == 1){
        //     $dd->where('account_name','like','%'.$data['flash_val'].'%')
        //         ->orWhere('account_no','like','%'.$data['flash_val'].'%');
        // }
        // if (Request::has('selOpt') && Request::has('iptValue') && Request::input('selOpt') != 1) {
        //     $dd->where($data['selopt'], 'like', '%' . Request::input('iptValue') . '%');
        // }

        $dd->with('company_branch');
        $status=null;
        if (Request::has('status')) {
            $status = Request::input('status');
            $dd=$dd->where('drawdown_account.status', Request::input('status'));
        }
        $company = Request::input('company');
        $data['company']=Request::input('branch');
        if(Request::has('company')){
            $dd->where('drawdown_account.branch', trim($company));			
        }
        $project_id = null;
        $project=null;
        if(Request::has('project_id')){
            $project_id = Request::input('project_id');
            if(!empty($project_id)){
                $dd->where('drawdown_account.project_id',$project_id);
                $project = Project::find($project_id);
            }
        }
        $unit_type_id = null;
        if(Request::has('unit_type_id')){
            $unit_type_id = Request::input('unit_type_id');
            if(!empty($unit_type_id)){
                $dd->where('drawdown_account.unit_type_id',$unit_type_id);
            }
        }
        $unit_id = null;
        if(Request::has('unit_id')){
            $unit_id = Request::input('unit_id');
            if(!empty($unit_id)){
                $dd->where('drawdown_account.unit_id',$unit_id);
            }
        }
        $customer_id = null;
        $client = null;
        if(Request::has('customer_id')){
            $customer_id = Request::input('customer_id');
            if(!empty($customer_id)){
                $dd->where('drawdown_account.client_id',$customer_id);
                $client = Clients::find($customer_id);
            }
        }

        //$data['drawdown_accounts'] = $dd->get();

        /*$journals = [];
        foreach($data['drawdown_accounts'] as $key=>$var){
            $jd = JournalDetail::select('journal_requiry.entry_date', 'journal_detail.*',
                'journal_requiry.description as j_desc')
            ->join('journal_requiry', 'journal_id', '=', 'journal_requiry.id')
            ->where('coa_id', '=', $var->coa_id)
            ->where('journal_requiry.entry_date', '<', date('Y-m-d', strtotime($as_of.' + 1 days')))
            ->orderBy('id', 'Desc')->get();
            $journals[$var->coa_id] = $jd;
        }*/

        $drawdown_accounts = $dd->with(['journal_detail' => function ($query) use ($from_date) {
            $query->select('*')->with(['journal' => function ($q) use ($from_date) {
                $q->select('id', 'entry_date', 'description as j_desc')
                    ->whereDate('entry_date', '<', date('Y-m-d', strtotime($from_date . ' + 1 days')));
            }])->orderBy('id', 'Desc');

        }])->paginate($offset);

        if(Request::input('is_excel') == 1 || Request::input('is_csv') == 1){
            $xlsx = 'xlsx';
            if(Request::has('is_csv') == 1){
                $xlsx = 'csv';
            }
            return Excel::create('drawdown_account-'.date('d-M-Y'), function($excel) use ($drawdown_accounts) {
                $excel->sheet('mySheet', function($sheet) use ($drawdown_accounts)
                {
                    $sheet->loadView('exports.drawdown_accounts',['drawdown_accounts' => $drawdown_accounts]);
                });
            })->download($xlsx);
        }

        $data['drawdown_accounts'] = $drawdown_accounts;
        $data['customer_id'] = $customer_id;
        $data['client'] = $client;
        $data['project_id'] = $project_id;
        $data['project'] = $project;
        $data['status'] = $status;        
        $data['from_date'] = $from_date;
        $data['to_date'] = $to_date;
        
        
        

        //$data['journals'] = $journals;
        $data['set_offset'] = $offset;
        $data['end'] = $as_of;
        return $this->view('loans.drawdown_account', $data);
    }
    public function list_company_drawdown_account()
    {
        $offset =10000;// Request::has('set_offset') ? Request::input('set_offset') : 15;
        $from_date = Request::input('t_from')?Request::input('t_from'):null;
        $to_date = Request::input('t_to')?Request::input('t_to'):null;
        $as_of = Request::has('dpEnd') ? Request::input('dpEnd') : date("Y-m-d");
        $dd = CompanyDrawdownAccounts::select('company_drawdown_account.*','projects.short_code as project','unit_types.name as unit_type','units.code as unit')
        ->leftJoin('projects','projects.id','=','company_drawdown_account.project_id')
        ->leftJoin('unit_types','unit_types.id','=','company_drawdown_account.unit_type_id')
        ->leftJoin('units','units.id','=','company_drawdown_account.unit_id')
        ;

        $data['branches'] = CompanyBranch::where('status', 1)->get();
    	$data['projects'] = Project::select('id','dealer','short_code')->get();

        $data['selopt'] = 1;
        $data['flash_val'] = Request::input('iptValue');
        $data['selopt'] = Request::input('selOpt');


        $dd->with('company_branch');
        if(!empty($from_date) && !empty($to_date)){
            $dd->whereDate('company_drawdown_account.created_at','>=',$from_date)->whereDate('company_drawdown_account.created_at','<=',$to_date);
        
        }
        $status=null;
        if (Request::has('status')) {
            $status = Request::input('status');
            $dd=$dd->where('company_drawdown_account.status', Request::input('status'));
        }
        $company = Request::input('company');
        $data['company']=Request::input('branch');
        if(Request::has('company')){
            $dd->where('company_drawdown_account.branch', trim($company));			
        }
        $project_id = null;
        $project=null;
        if(Request::has('project_id')){
            $project_id = Request::input('project_id');
            if(!empty($project_id)){
                $dd->where('company_drawdown_account.project_id',$project_id);
                $project = Project::find($project_id);
            }
        }
        $unit_type_id = null;
        if(Request::has('unit_type_id')){
            $unit_type_id = Request::input('unit_type_id');
            if(!empty($unit_type_id)){
                $dd->where('company_drawdown_account.unit_type_id',$unit_type_id);
            }
        }
        $unit_id = null;
        if(Request::has('unit_id')){
            $unit_id = Request::input('unit_id');
            if(!empty($unit_id)){
                $dd->where('company_drawdown_account.unit_id',$unit_id);
            }
        }
        $customer_id = null;
        $client = null;
        if(Request::has('customer_id')){
            $customer_id = Request::input('customer_id');
            if(!empty($customer_id)){
                $dd->where('company_drawdown_account.client_id',$customer_id);
                $client = Clients::find($customer_id);
            }
        }

        $drawdown_accounts = $dd->with(['journal_detail' => function ($query) use ($from_date) {
            $query->select('*')->with(['journal' => function ($q) use ($from_date) {
                $q->select('id', 'entry_date', 'description as j_desc')
                    ->whereDate('entry_date', '<', date('Y-m-d', strtotime($from_date . ' + 1 days')));
            }])->orderBy('id', 'Desc');

        }])->paginate($offset);

        if(Request::input('is_excel') == 1 || Request::input('is_csv') == 1){
            $xlsx = 'xlsx';
            if(Request::has('is_csv') == 1){
                $xlsx = 'csv';
            }
            return Excel::create('unit-cut-stock-'.date('d-M-Y'), function($excel) use ($drawdown_accounts) {
                $excel->sheet('mySheet', function($sheet) use ($drawdown_accounts)
                {
                    $sheet->loadView('exports.unit_cut_stock',['drawdown_accounts' => $drawdown_accounts]);
                });
            })->download($xlsx);
        }

        $data['drawdown_accounts'] = $drawdown_accounts;
        $data['customer_id'] = $customer_id;
        $data['client'] = $client;
        $data['project_id'] = $project_id;
        $data['project'] = $project;
        $data['status'] = $status;        
        $data['from_date'] = $from_date;
        $data['to_date'] = $to_date;
        
        
        

        //$data['journals'] = $journals;
        $data['set_offset'] = $offset;
        $data['end'] = $as_of;
        return $this->view('loans.company_drawdown_account', $data);
    }

    public function drawdown_account_detail($id)
    {
        $data['dad'] = DrawdownAccounts::with('company_branch')->with('coa')->find($id);
        // dd( $data['dad']);
        $data['coa'] = CoaCategory::find($data['dad']->coa_id);

        $results = JournalDetail::select('*', 'journal_requiry.entry_date', 'journal_requiry.description')
                                ->join('journal_requiry', 'journal_requiry.id', '=', 'journal_detail.journal_id')
                                ->where('journal_detail.coa_id', '=', $data['dad']->coa_id)
                                ->orderBy('journal_detail.id', 'ASC')
                                ->orderBy('journal_detail.credit', 'desc')
                                ->where('journal_detail.is_audit', '=', 1)
                                ->get();
        $trans_arr = [];
        foreach ($results as $tr) {
            if(!empty($tr->receipt_no)){
                if( $tr->trans_type!="Fee Charge"){
                    $trans_arr[Date("Y-m-d", strtotime($tr->entry_date))][] = $tr;
                }
            }else{
                    $trans_arr[Date("Y-m-d", strtotime($tr->entry_date))][] = $tr;
            }
        }
        ksort($trans_arr);
        $data['trans'] = $trans_arr;
        $data['balance_drawdown_acc'] = balance_drawdown_acc($data['dad']->coa_id);
        $data['audit'] = Audit::where('tbl', 'drawdown_account')->where('tbl_id', $id)->orderBy('id', 'asc')->with(['audit1', 'audit2'])->get();
        $data['branch_name'] = CompanyBranch::where('branch_code', '=', $data['dad']->branch)->first()->branch_name;
        return $this->view('loans.drawdown_account_detail', $data);
    }
    public function drawdown_account_info($id)
    {
        $data['drawdown_account']=DrawdownAccounts::with('loans')->find($id);
        $unit_code = Unit::find($data['drawdown_account']->unit_id);
        $loan_id=0;
        if($data['drawdown_account']->loans){
            $loan_id=$data['drawdown_account']->loans[0]->id;
        }
        if ($loan_id > 0) {
            $B0 = new Loan();
            $lc = $B0->select(['loans.*','clients.client_name','client_loan_accounts.account_no'])
                ->join('client_loan_accounts', 'client_loan_accounts.id', '=', 'loans.loan_account_id')
                ->join('clients', 'clients.id', '=', 'loans.client_id');
            $lc = $lc->with([
                'payment' => function ($q) {
                    $q->select('id',
                                'loan_id',
                                'paid_principal',
                                'paid_interest',
                                'paid_fee',
                                'paid_other_fee',
                                'penalty_amount',
                                'repayment_owed',
                                'repayment_date',
                                'payment_month',
                                'status',
                                'condition_id'
                            )->orderBy('id', 'asc');
                },
                'client_loan_account',
                'schedule' => function ($q) {
                        $q->select('loan_id',
                            'schedule_date',
                            'date_num',
                            'interest',
                            'principal',
                            'fee',
                            'other_fee',
                            'intraday_rate',
                            'no',
                            'type',
                            'loan_no'
                        );
                },
                'transaction' => function ($q) {
                    $q->where('trans_type', 'LIKE', '%Loan Repayment%');
                },
                'penalty_record' => function ($q) {
                    $q->select('id', 'loan_id', 'record_date', 'owed_penalty')->orderBy('id', 'DESC');
                }
            ])->whereIn('loans.status', [3, 8]);

            $lc = $lc->where('loans.id', $loan_id)->first();         
            $dpDateClone = date('Y-m-d');
            
            $penalty_arr = LoanCalculate::getTotalPenalty($lc, $dpDateClone);
            $sch_repay_arr = get_auto_repay_array($lc, $dpDateClone);
            $penalty = $penalty_arr[4];
            $fee_bal = $penalty_arr[9];
            $other_fee = $penalty_arr[10];
            $find_unit_id = Loan::where('id', '=', $loan_id)->get();            
    
            $loan_account = DrawdownAccounts::where('id', $find_unit_id[0]->loan_account_id)->first();
            $data['loan_id']=$loan_id;
            $data['loan']=$lc;
            $data['dpDateClone']=$dpDateClone;
            $data['fee_bal']=$fee_bal;
            $data['penalty']=$penalty;
            $data['other_fee']=$other_fee;
            $data['sch_repay_arr']=$sch_repay_arr;            
            $data['drawdow_acc']=$drawdow_acc;
            $data['loan_account']=$loan_account;
        }
        $data['unit_code']=$unit_code;

        return  $data;
    }
    public function getDrawdownAccountReject($id = 0)
    {
        if ($id > 0) {
            return $this->view('loans.drawdown_account_reject', ['id' => $id]);
            
        }
        return redirect()->back();
    }

    public function postDrawdownAccountReject(){
        DB::beginTransaction();
        try {
            $drawdown_account = DrawdownAccounts::where('id', '=', Request::input('id'))->first();
            if($drawdown_account){
                $current_dd_balance = $drawdown_account->balance;
                $dd_balance = $drawdown_account->balance - floatval($drawdown_account->balance);
                $drawdown_account->balance = $dd_balance;
               $drawdown_account->status = 2;
                if($drawdown_account->save()){
                     $client_loan_account = ClientLoanAccounts::where('id', '=', $drawdown_account->client_loan_id)->first();
                    if($client_loan_account){
                        $client_loan_account->status = 9;
                        $client_loan_account->balance = $dd_balance;
                        $client_loan_account->save();
                    }
                    $units = Unit::where('id',$drawdown_account->unit_id)->first();
                    if($units){
                        $units->status = 'available';
                        $units->save();
                    }

                    if(Request::input('loan_id')){
                        if(Request::input('loan_status')<3){
                            $loan = Loan::where('id',Request::input('loan_id'))->first();
                            if (!empty($loan)) {
                                $loan->status = 4;
                                $loan->workflow_status = 'reject';
                                $loan->rejected_date = date('Y-m-d');
                                $loan->rejected_note = "System Auto Reject Loan from Drawdown =>".Request::input('note');
                                $loan->rejected_byuserid = Auth::user()->id;
                                if($loan->save()) {
                                    $this->userActivity($loan->rejected_byuserid, Request::input('loan_id'), 6, 'Reject Loan');
                                    $loan = new LoanStatus;
                                    $loan->loan_id = Request::input('loan_id');
                                    $loan->description = "System Auto Reject Loan from Drawdown =>".Request::input('note');
                                    $loan->created_by = Auth::user()->id;
                                    $loan->save();
                                }
                            }
                                // Transfer client balance to company balance
                                $newCompanyDrawdownAccounts = new CompanyDrawdownAccounts();
                                $newCompanyDrawdownAccounts->client_id=$drawdown_account->client_id;
                                $newCompanyDrawdownAccounts->client_loan_id=$drawdown_account->client_loan_id;
                                $newCompanyDrawdownAccounts->project_id=$drawdown_account->project_id;
                                $newCompanyDrawdownAccounts->unit_type_id=$drawdown_account->unit_type_id;
                                $newCompanyDrawdownAccounts->unit_id=$drawdown_account->unit_id;
                                $newCompanyDrawdownAccounts->account_no=$drawdown_account->account_no;
                                $newCompanyDrawdownAccounts->account_name=$drawdown_account->account_name;
                                $newCompanyDrawdownAccounts->company_account_no='9999999999';
                                $newCompanyDrawdownAccounts->company_account_name='Company';
                                $newCompanyDrawdownAccounts->currency=$drawdown_account->currency;
                                $newCompanyDrawdownAccounts->branch=$drawdown_account->branch;
                                $newCompanyDrawdownAccounts->parent_id=$drawdown_account->parent_id;
                                $newCompanyDrawdownAccounts->coa_id=$drawdown_account->coa_id;
                                $newCompanyDrawdownAccounts->balance=$drawdown_account->balance;                 
                                $newCompanyDrawdownAccounts->old_balance=0;
                                $newCompanyDrawdownAccounts->created_by=$drawdown_account->created_by;
                                $newCompanyDrawdownAccounts->status="Rejected";
                                $newCompanyDrawdownAccounts->type="Drawdown";
                                $newCompanyDrawdownAccounts->sale_id=$drawdown_account->sale_id;
                                if($newCompanyDrawdownAccounts->save()){
                                $coa = CoaCategory::find($client_loan_account->parent_id);
                                $coa_2 = CoaCategory::select('id', 'account_code', 'name')->where('type', 6)->where('currency', $client_loan_account->currency)->where('name', 'like', '%Non Settle Drawdown Account')->first();
                                $record_date = date('Y-m-d H:m:s', time());

                                $transaction = TillTransaction::where('drawdown_acc',$drawdown_account->account_no)->orderBy('created_at', 'DESC')->first();
                                if($transaction){
                                    $till_transaction = new TillTransaction();
                                    $till_transaction->till_account_id = $transaction->till_account_id;
                                    $till_transaction->drawdown_acc_id = $transaction->drawdown_acc_id;
                                    $till_transaction->till_user_id = $transaction->till_user_id;
                                    $till_transaction->from_account = $transaction->from_account;
                                    $till_transaction->to_account = $transaction->to_account;
                                    $till_transaction->methode = 'Cash on Hand-Teller';
                                    $till_transaction->approve_status = 1;
                                    $till_transaction->balance = floatval($transaction->balance);
                                    $till_transaction->cash_out = 0;//floatval($current_dd_balance);
                                    $till_transaction->drawdown_acc = $transaction->drawdown_acc;
                                    $till_transaction->branch_id = $transaction->branch_id;
                                    $till_transaction->operate_by = Auth::user()->id;
                                    $till_transaction->tranx_time = $record_date;
                                    $till_transaction->created_at=date("Y-m-d H:i:s");
                                    $till_transaction->tran_currency_id = $transaction->tran_currency_id;
                                    $till_transaction->description = 'Drawdown Account Rejected';
                                    $till_transaction->type = 'Return Till';
                                    $till_transaction->deposit_type='Deposit';
                                    $till_transaction->deposit_company='Real Estate';
                                    if($till_transaction->save()){

                                        // Journal Requiry
                                        $journal = new JournalRequiry;
                                        $journal->tran_id = $till_transaction->id;
                                        $journal->entry_date = $record_date;
                                        $journal->description ='Drawdown Account Rejected - (Deposit):'.$drawdown_account->account_name.'('.$drawdown_account->account_no.'-'.$units->code.')';
                                        $journal->user_id = Auth::user()->id;
                                        $journal->trans_type = 'Return Till';
                                        $journal->is_audit = 1;
                                        if ($journal->save()) {
                                                // Journal Detail(Debit)
                                                $jd = new JournalDetail;
                                                $jd->journal_id = $journal->id;
                                                $jd->coa_id =$drawdown_account->coa_id;
                                                $jd->branch_code = $drawdown_account->branch;
                                                $jd->debit = $current_dd_balance;
                                                $jd->credit = 0;
                                                $jd_db->p_debit = 0;
                                                $jd_db->p_credit = 0;        
                                                $jd_db->b_debit = 0;
                                                $jd_db->b_credit = 0;
                                                $jd->description =  $journal->description;
                                                $jd->is_audit = 1;
                                                $jd->save();                           

                                        }
                                    }

                                }   
                    
                                }
                                
                        }else{
                            $this->postClose(Request::input('loan_id'));
                        }
                    }else{
                            // Transfer client balance to company balance
                            $newCompanyDrawdownAccounts = new CompanyDrawdownAccounts();
                            $newCompanyDrawdownAccounts->client_id=$drawdown_account->client_id;
                            $newCompanyDrawdownAccounts->client_loan_id=$drawdown_account->client_loan_id;
                            $newCompanyDrawdownAccounts->project_id=$drawdown_account->project_id;
                            $newCompanyDrawdownAccounts->unit_type_id=$drawdown_account->unit_type_id;
                            $newCompanyDrawdownAccounts->unit_id=$drawdown_account->unit_id;
                            $newCompanyDrawdownAccounts->account_no=$drawdown_account->account_no;
                            $newCompanyDrawdownAccounts->account_name=$drawdown_account->account_name;
                            $newCompanyDrawdownAccounts->company_account_no='9999999999';
                            $newCompanyDrawdownAccounts->company_account_name='Company';
                            $newCompanyDrawdownAccounts->currency=$drawdown_account->currency;
                            $newCompanyDrawdownAccounts->branch=$drawdown_account->branch;
                            $newCompanyDrawdownAccounts->parent_id=$drawdown_account->parent_id;
                            $newCompanyDrawdownAccounts->coa_id=$drawdown_account->coa_id;
                            $newCompanyDrawdownAccounts->balance=$current_dd_balance;                 
                            $newCompanyDrawdownAccounts->old_balance=$current_dd_balance;
                            $newCompanyDrawdownAccounts->created_by=$drawdown_account->created_by;
                            $newCompanyDrawdownAccounts->status="Rejected";
                            $newCompanyDrawdownAccounts->type="Drawdown";
                            $newCompanyDrawdownAccounts->sale_id=$drawdown_account->sale_id;

                            if($newCompanyDrawdownAccounts->save()){
                                $coa = CoaCategory::find($client_loan_account->parent_id);
                                $coa_2 = CoaCategory::select('id', 'account_code', 'name')->where('type', 6)->where('currency', $client_loan_account->currency)->where('name', 'like', '%Non Settle Drawdown Account')->first();
                                $record_date = date('Y-m-d H:m:s', time());

                                $transaction = TillTransaction::where('drawdown_acc',$drawdown_account->account_no)->orderBy('created_at', 'DESC')->first();
                                if($transaction){
                                    $till_transaction = new TillTransaction();
                                    $till_transaction->till_account_id = 0;
                                    $till_transaction->drawdown_acc_id = $transaction->drawdown_acc_id;
                                    $till_transaction->till_user_id = 0;
                                    $till_transaction->from_account = 'N/A';
                                    $till_transaction->to_account = 'N/A';
                                    $till_transaction->methode = 'N/A';
                                    $till_transaction->approve_status = 1;
                                    $till_transaction->balance = floatval($current_dd_balance);
                                    $till_transaction->cash_out = 0;//floatval($current_dd_balance);
                                    $till_transaction->drawdown_acc = $transaction->drawdown_acc;
                                    $till_transaction->branch_id = $transaction->branch_id;
                                    $till_transaction->operate_by = Auth::user()->id;
                                    $till_transaction->tranx_time = $record_date;
                                    $till_transaction->created_at=date("Y-m-d H:i:s");
                                    $till_transaction->tran_currency_id = $transaction->tran_currency_id;
                                    $till_transaction->description = 'Drawdown Account Rejected';
                                    $till_transaction->type = 'Auto Drawdown Account Rejected';
                                    $till_transaction->deposit_type='Deposit';
                                    $till_transaction->deposit_company='Real Estate';
                                    if($till_transaction->save()){

                                        // Journal Requiry
                                        $journal = new JournalRequiry;
                                        $journal->tran_id = $till_transaction->id;
                                        $journal->entry_date = $record_date;
                                        $journal->description ='Drawdown Account Rejected - (Deposit):'.$drawdown_account->account_name.'('.$drawdown_account->account_no.'-'.$units->code.')';
                                        $journal->user_id = Auth::user()->id;
                                        $journal->trans_type = 'Return Till';
                                        $journal->is_audit = 1;
                                        if ($journal->save()) {
                                                // Journal Detail(Debit)
                                                $jd = new JournalDetail;
                                                $jd->journal_id = $journal->id;
                                                $jd->coa_id =$drawdown_account->coa_id;
                                                $jd->branch_code = $drawdown_account->branch;
                                                $jd->debit = $current_dd_balance;
                                                $jd->credit = 0;
                                                $jd_db->p_debit = 0;
                                                $jd_db->p_credit = 0;        
                                                $jd_db->b_debit = 0;
                                                $jd_db->b_credit = 0;
                                                $jd->description =  $journal->description;
                                                $jd->is_audit = 1;
                                                $jd->save();     
                                                
                                                // Journal Detail(Debit)
                                                $jd = new JournalDetail;
                                                $jd->journal_id = $journal->id;
                                                $jd->coa_id =$coa_2->id;
                                                $jd->branch_code = $drawdown_account->branch;
                                                $jd->debit = 0;
                                                $jd->credit =  $current_dd_balance;
                                                $jd_db->p_debit = 0;
                                                $jd_db->p_credit = 0;        
                                                $jd_db->b_debit = 0;
                                                $jd_db->b_credit = 0;
                                                $jd->description =  $journal->description;
                                                $jd->is_audit = 1;
                                                $jd->save();     

                                        }
                                    }

                                }                                                      
                            }
                        }

                    /* Update Sale Order */
                    if($drawdown_account->sale_id){
                        $sale = Sale::where('id',$drawdown_account->sale_id)->first();
                        if($sale){
                            // $sale->status = 'Canceled';
                            // $sale->remark =  "System Auto Reject Drawdown =>".Request::input('note');
                            // $sale->save();
                            $saleNew = new Sale;
                            $saleNew->order_no=$sale->order_no;
                            $saleNew->user_id = $sale->user_id;			
                            $saleNew->client_id=$sale->client_id;
                            $saleNew->currency=$sale->currency;
                            $saleNew->created_on=date('Y-m-d H:i:s');
                            $saleNew->sale_person=$sale->sale_person;
                            $saleNew->sale_person_parent_l1=$sale->sale_person_parent_l1;
                            $saleNew->sale_person_parent_l2=$sale->sale_person_parent_l2;
                            $saleNew->clearance_amount=$sale->clearance_amount;
                            $saleNew->discount_promotion=$sale->discount_promotion;
                            $saleNew->discount_other=$sale->discount_other;
                            $saleNew->discount_payment_option=$sale->discount_payment_option;
                            $saleNew->amount_discount_payment_option=$sale->amount_discount_payment_option;
                            $saleNew->price_after_discount=$sale->price_after_discount;
                            $saleNew->vat=0;
                            $saleNew->diposit_amount=$sale->diposit_amount;
                            $saleNew->final_price=$sale->final_price;
                            $saleNew->status='Canceled';
                            $saleNew->authorization=$sale->authorization;
                            $saleNew->payment_option=$sale->payment_option;
                            $saleNew->payment_option_type=$sale->payment_option_type;
                            $saleNew->payment_status=$sale->payment_status;
                            $saleNew->invoice_status=$sale->invoice_status;
                            $saleNew->remark="System Auto Reject Drawdown =>".$sale->remark;
                
                            if($saleNew->save()) {
                                $saleItem = SaleItem::where('sale_id',$drawdown_account->sale_id)->first();
                                if($saleItem){
                                    $item = new SaleItem();
                                    $item->sale_id = $saleNew->id;
                                    $item->company_branch_id=$saleItem->branch;
                                    $item->project_id=$saleItem->project_id;
                                    $item->unit_type_id=$saleItem->unit_type_id;
                                    $item->unit_id=$saleItem->unit_id;
                                    $item->discount_promotion=$saleItem->discount_promotion;
                                    $item->discount_other=$saleItem->discount_other;
                                    $item->discount_payment_option=$saleItem->discount_payment_option;
                                    $item->amount_discount_payment_option=$saleItem->amount_discount_payment_option;
                                    $item->qty=1;
                                    $item->unit_sale_price=$saleItem->unit_sale_price;
                                    $item->save();

                                }	
                                
                            }
                        }
                        $invoice = Invoice::where('sale_id',$drawdown_account->sale_id)->first();
                        if($invoice){
                            $invoice->invoice_status = 'Canceled';
                            $invoice->payment_status =='Void';
                            $invoice->remark =  "System Auto Reject Drawdown =>".Request::input('note');
                            $invoice->save();
                        }
                    }
                }
            }
            DB::commit();
            $data['success']="success";
            return response()->json($data);
        } catch (Exception $e) {
            DB::rollback();
            Session::flash('message', 'Save not successfully');
            return redirect()->back();
        }
    }

    public function array_sort_by_column(&$arr, $col, $dir = SORT_ASC)
    {
        $sort_col = array();
        foreach ($arr as $key => $row) {
            $sort_col[$key] = $row[$col];
        }

        array_multisort($sort_col, $dir, $arr);
    }

    function audit($id)
    {
        $dd = DrawdownAccounts::find($id);
        if (!empty($dd)) {
            $dd->status = 1;
            $dd->save();
            $this->userActivity(Auth::user()->id, $id, 4, 'Enable Drawdown account');
        }

        $this->do_audit($id, '', Auth::user()->id, 'drawdown_account', 1, 'approve drawdown account');
        return redirect()->route('drawdown_account');
    }

    function co_performance()
    {
        $data['users'] = User::where('role_id', 10)->paginate(25);
        foreach ($data['users'] as $u) {
            $data['client'][$u->id] = Loan::where('status', '>=', 3)->where('co', $u->id)->distinct('client_id')->count();
            $data['loan'][$u->id] = Loan::where('status', '>=', 3)->where('co', $u->id)->count();

            $data['disburse_amount'][$u->id] = Loan::join('client_loan_accounts', 'client_loan_accounts.id', '=', 'loans.loan_account_id')
                ->where('loans.status', '>=', 3)
                ->where('co', $u->id)
                ->selectRaw('SUM(loan_amount) as loan_amount')
                ->where('currency', 2)
                ->first()->loan_amount;

            $data['disburse_amount_kh'][$u->id] = Loan::join('client_loan_accounts', 'client_loan_accounts.id', '=', 'loans.loan_account_id')
                ->where('loans.status', '>=', 3)
                ->where('co', $u->id)
                ->selectRaw('SUM(loan_amount) as loan_amount')
                ->where('currency', 1)
                ->first()->loan_amount;

            //loan balance
            $principal = 0;
            $ls = Loan::where('loans.status', '>=', 3)
                ->join('client_loan_accounts', 'client_loan_accounts.id', '=', 'loans.loan_account_id')
                ->where('currency', 2)
                ->where('co', $u->id)->get();
            foreach ($ls as $l) {
                $principal += TransactionsRequiry::where('loan_id', $l->id)->selectRaw('SUM(principal) as principal')->first()->principal;
            }
            $data['balance'][$u->id] = $data['disburse_amount'][$u->id] - $principal;

            $principal = 0;
            $ls = Loan::where('loans.status', '>=', 3)
                ->join('client_loan_accounts', 'client_loan_accounts.id', '=', 'loans.loan_account_id')
                ->where('currency', 1)
                ->where('co', $u->id)->get();
            foreach ($ls as $l) {
                $principal += TransactionsRequiry::where('loan_id', $l->id)->selectRaw('SUM(principal) as principal')->first()->principal;
            }
            $data['balance_kh'][$u->id] = $data['disburse_amount_kh'][$u->id] - $principal;
        }
        return $this->view('loans.co_performance', $data);
    }

    public function getLoanCo()
    {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 15;
        $query_arr = array('user_id' => Auth::user()->id, 'company_branch_id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);
        $B0 = new Loan();
        $B0 = $this->getUserByBranch($B0, 'company_branch_id', $query_arr);

        $loan = $B0->select([
                'loans.id',
                'loans.contract_id',
                'loans.start_date',
                'loans.loan_type',
                'loans.loan_amount',
                'loans.interest_rate',
                'loans.loan_account_id',
                'loans.status',
                'submitted_on',
                'loans.disburse_date',
                'loans.rejected_date',
                'loans.client_id',
                'loans.updated_at',
                'clients.client_name',
                'clients.client_type',
                'loans.settlement_date',
                'clients.phone1',
                'co',
                'loan_duration']


        )->with(['approval' => function ($query) {
            $query->select('id', 'loan_id', 'approval_date');
        }, 'client_loan_account'])->leftJoin('clients', 'clients.id', '=', 'loans.client_id');
        $contract_id = null;
        $query_url = [];
        if (Request::has('contract_id')) {
            $contract_id = Request::input('contract_id');
            $loan = $loan->where('contract_id', '=', $contract_id);
            $query_url['contract_id'] = $contract_id;
        }
        $status = 0;
        if (Request::has('status')) {
            $status = Request::input('status');
            $loan = $loan->where('loans.status', '=', $status);
            $query_url['status'] = $status;
        }
        $co = null;
        if (Request::has('co')) {
            $co = Request::input('co');
            $loan = $loan->where('co', '=', $co);
            $query_url['co'] = $co;
        }

        //if co see only own records......
        $co_role_list = array(18, 19, 20, 21, 27, 29, 30, 31);
        if (in_array(Auth::user()->role_id, $co_role_list)) $loan = $loan->where('co', '=', Auth::user()->id);
        $loan = $loan->with(['co_user']);
        $loan = $loan->with(['schedule']);
        $total_loan = $loan;

        $loan = $loan->paginate($offset);
        $loan->appends($query_url);

        //get co list
        $users = User::select('users.*', 'roles.role_name')->join('roles', 'roles.id', '=', 'users.role_id')
            ->where(function ($q) {
                $q->where('roles.role_name', 'like', '%Credi%')
                    ->orwhere('roles.role_name', 'like', '%Recover%')
                    ->orwhere('roles.role_name', 'like', 'ML Manager');
            })->orderBy('name')->get();
        $data['loans'] = $loan;
        $data['status'] = $status;
        $data['contract_id'] = $contract_id;
        $data['co'] = $co;
        $data['offset'] = $offset;
        $data['users'] = $users;
        $data['role_id'] = Auth::user()->role_id;

        //rpt_pass_due
        $to_interest = 0;
        $to_principal = 0;
        $to_penalty = 0;
        $total_payment = 0;
        foreach ($total_loan->get() as $l) {
            $result_total_penalty = LoanCalculate::getTotalPenalty($l);
            $result = $result_total_penalty[0];
            $overdue = $result_total_penalty[2];
            $repayment_array_global = $result_total_penalty[1];

            if (count($repayment_array_global) <= 0)
                continue;
            for ($i = 0; $i < $l->loan_duration; $i++) {
                if (empty($result[$i])) {
                    continue;
                }
                $to_principal += $result[$i][1];
                $to_interest += $result[$i][2];
                $to_penalty += $result[$i][8];
                $total_payment += $result[$i][9];
            }
        }
        $data['t_principal'] = $to_principal;
        $data['t_interest'] = $to_interest;
        $data['t_penalty'] = $to_penalty;
        $data['t_payment'] = $total_payment;

        return $this->view('loans.loan_co', $data);
    }


    public function getEditJournal($id = 0)
    {
        $data['entry_no'] = $data['id'] = $id;
        $data['res'] = JournalRequiry::with('detail')->find($id);
        $data['transactions_requiry'] = TransactionsRequiry::where('id', $data['res']->tran_id)->first();
        $data['branch_code'] = $data['res']->detail[0]->branch_code;
        $data['loan'] = Loan::where('loans.id', $data['transactions_requiry']->loan_id)->join('client_loan_accounts', 'loan_account_id', '=', 'client_loan_accounts.id')->first();
        $data['branches'] = CompanyBranch::select('id', 'branch_name', 'branch_code')->orderBy('branch_code', 'ASC')->get();
        $data['currency_id'] = CoaCategory::where('id', $data['res']->detail[0]->coa_id)->first()->currency;
        // $data['account'] = CoaCategory::select(['id', 'account_code', 'name', 'currency'])->where('currency', isset($data['currency_id']))->whereIn('type', [6, 7])->get();
        $account = [];
        foreach ($data['res']->detail as $key => $value) {
            $account[$value->coa_id] = CoaCategory::select(['id', 'account_code', 'name', 'currency'])->where('id','=', $value->coa_id,'and','currency','=',$value->currency_id)->whereIn('type', [6, 7])->get();
        }
        $data['account'] = $account;
        $data['trans'] = TransactionsRequiry::select(['id', 'loan_id', 'trans_type', 'amount'])
            ->with(['loan' => function ($query) {
                $query->select('id', 'contract_id', 'company_branch_id');
            }])->where('flag', '=', 0)->orWhere('id', $data['res']->tran_id)->get();
        return $this->view('loans.edit_journal', $data);
    }

    function postEditJournal($id)
    {
        $desc = Request::input('txtDesc');
        $journal = JournalRequiry::find($id);
        $journal->invoice_number = Request::input('invoice_number');
        $journal->description = $desc[0];
        $journal->entry_date = Request::input('entry_date');
        $journal->ref_name_id = Request::input('referral_name');
        $journal->ref_name_type = Request::input('ref_name_type');

        if (Request::hasFile('receipt')) {
            if (Request::file('receipt')->isValid()) {
                if ((Request::file('receipt')->getSize() / 1024 / 1024) > 1) {
                    return redirect()->back()->with('error', 'File size is too large!');
                }
                $file = Request::file('receipt');
                $ext = $file->getClientOriginalExtension();
                if (in_array(strtolower($ext), ['jpg', 'jpeg', 'bmp', 'png'])) {
                    $image = Image::make($file);
                    $photo_name = uniqid(date('dmY')) . '.jpg';
                    $image->save(public_path('data/loans/receipts') . '/' . $photo_name);
                    $journal->receipt = $photo_name;
                }
            }
        }

        if ($journal->save()) {
            //delete old JD
            $jd = JournalDetail::where('journal_id', $id);
            $jd->delete();

            // $tran = TransactionsRequiry::find();
            // $tran->flag = 1;
            // $tran->save();

            $optTranType = Request::input('selType');
            $ipDebit = Request::input('ipDebit');
            $ipCredit = Request::input('ipCredit');
            $ipDesc = Request::input('ipDesc');
            $contract_id = Request::input('contract_id');
            $branch_code = Request::input('branch');
            $user_id = Auth::user()->id;

            for ($k = 0; $k < count($optTranType); $k++) {
                $jd = new JournalDetail;
                $jd->journal_id = $id;
                $jd->coa_id = $optTranType[$k];
                $jd->reference = $contract_id[$k];
                $jd->branch_code = $branch_code;
                $prev_bl = array_fill(0, 2, 0.0);
                $prev_row = JournalDetail::select('b_debit', 'b_credit')
                    ->where('coa_id', $optTranType[$k])
                    ->orderBy('id', 'desc')
                    ->first();
                if (!empty($prev_row)) {
                    $prev_bl[0] = $prev_row->b_debit;
                    $prev_bl[1] = $prev_row->b_credit;
                }
                $jd->p_debit = $prev_bl[0];
                $jd->p_credit = $prev_bl[1];
                $jd->debit = $ipDebit[$k];
                $jd->credit = $ipCredit[$k];
                $jd->b_debit = floatval($prev_bl[0]) + floatval($jd->debit);
                $jd->b_credit = floatval($prev_bl[1]) + floatval($jd->credit);
                $jd->description = $ipDesc[$k];
                $jd->save();
            }
        }

        return redirect()->route('view_journal');
    }

    function tmp()
    {
        $data = Request::input();
        //if(Request::ajax()){
        return ['hello' => "aa", 'hello2' => "bb"];

        //}
        //return view('loans.add_loan_repayment',$data)->render();
        //return redirect()->route('add_loan_repayment',['dpDate' => $dpDate]);
    }

    public function getScheduleEIR()
    {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 15;
        $query_arr = array('user_id' => Auth::user()->id, 'company_branch_id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);
        $B0 = new Loan();
        //$B0 = $this->getUserByBranch($B0, 'company_branch_id', $query_arr);
        $loan = Loan::with('schedule');
        $contract_id = null;
        $query_url = [];
        if (Request::has('contract_id')) {
            $contract_id = Request::input('contract_id');
            $loan = $loan->where('contract_id', '=', $contract_id);
        }
        // if (Request::has('client_name')) {
        //     $client_name = Request::input('client_name');
        //     $loan = $loan->where('clients.client_name', 'LIKE', '%'.$client_name.'%');
        //     $query_url['client_name'] = $client_name;
        // }
        // $status = 0;
        // if (Request::has('status')) {
        //     $status = Request::input('status');
        //     $loan = $loan->where('loans.status', '=', $status);
        //     $query_url['status'] = $status;
        // }
        $loan = $loan->get();
        //dd($loan);
        // $loan->each->append($query_url);
        $product_type = Product_type::get();
        return $this->view('loans.schedule_eir', ['loans' => $loan,
            'contract_id' => $contract_id,
            'offset' => $offset,
            'product_type' => $product_type]);
    }

    /* --------------------------------------------------
    /* -------- Post BCash Loan Charge ------------------
    /* --------------------------------------------------
    */
    public function postBCashLoanCharge($record_date,$charge_amount = 0,$charge_type=0,$note=null,$transaction_id,$deposit_company=null,$deposit_type=null,$receipt_no,$be_cash_item_note=null)
    {
        DB::beginTransaction();
        try {
            $loan_id = Request::input('loan_id');
            if (is_numeric($loan_id) && $loan_id > 0) {
                $inputs = Request::except(['_token', 'sel_tellers', 'payment_type']);
                $loan = Loan::select('id', 'user_id', 'client_id')->where('id', '=', $loan_id)->first();
                $drawdown_acc = DrawdownAccounts::with('currency_tbl')
                    ->where('client_id', $loan->client_id)
                    ->where('currency', floatval(Request::input('currency')[0]))
                    ->first();
                if (!empty($loan)) {

                    if (!empty($inputs)) {
                        $charge = new FeeCharge;
                        $charge->loan_id = $loan_id;
                        $charge->charge_date =  Request::input('dpDate');
                        $charge->charge_amount =  floatval($charge_amount);
                        $charge->charge_type =  floatval($charge_type);
                        $charge->note =  $note?$note:Request::input('note');
                        $charge->user_id = Auth::user()->id;
                        $static = config('static_data.fee_charge');
                        if (is_null($charge->waived_amount) || $charge->waived_amount == '') $charge->waived_amount = 0;
                        $charge->save();
                        $journal_arr = [];
                        array_push($journal_arr, [
                                                Request::input('parent_debit')[0],
                                                floatval($charge_amount),
                                                Request::input('d_description')[0],
                                                Request::input('parent_credit')[0],
                                                floatval($charge_amount),
                                                Request::input('c_description')[0],
                                                Request::input('description')[0]
                                            ]);
                        $inputs['charge_amount']= floatval($charge_amount);
                        $inputs['note']=  $note?$note:Request::input('note');
                        $inputs['be_cash_receipt_no']=  $receipt_no; 
                        record_journal($loan, $charge->charge_date, $type = "Fee Charge", $inputs, $journal_arr, Auth::user()->branch_id, $charge->user_id);
                        $data = Request::except();
                        $data['amount']=floatval($charge_amount);
                        $data['disburse_note']=$note?$note:Request::input('note'); 
                        $this->Add_Teller_Trans_From_loan_Charge($data,$drawdown_acc,$transaction_id,$deposit_company,$deposit_type,$be_cash_item_note,$receipt_no);

                        $posting_transaction = new TransactionsPosting();
                        $posting_transaction->till_transaction_id=$transaction_id;
                        $posting_transaction->loan_id = $loan_id;
                        $posting_transaction->posting_date = $record_date;
                        $posting_transaction->trans_type ='Fee Charge';
                        $posting_transaction->description = $note?$note:Request::input('note');
                        $posting_transaction->amount =floatval($charge_amount);
                        $posting_transaction->total_penalty = 0;
                        $posting_transaction->total_principal = 0;
                        $posting_transaction->total_interest = 0;
                        $posting_transaction->user_id = Auth::user()->id;
                        $posting_transaction->status=0;
                        $posting_transaction->drawdown_acc_id=$drawdown_acc->id;
                        $posting_transaction->methode= $inputs['types'];
                        $posting_transaction->save();

                        DB::commit();
                    }
                }
            }
        } catch (Exception $e) {
            DB::rollback();
            Session::flash('message', 'Save not successfully');
            return redirect()->back();
        }
    }
    /* --------------------------------------------------
    /* --------- End BCash Loan Charge ------------------
    */


    public function getTransferBalance($id = 0)
    {

		if ($id > 0) {
			$old_drawdown=null;
			// $old_drawdown = DrawdownAccounts::with(
            //     ['projects','unitType','units'])->where('unit_id',$id)->where('status',2)->first(); 

			$new_drawdown = DrawdownAccounts::with( ['projects','unitType','units'])->where('unit_id',$id)->where('status',1)->first(); 

            $loan = Loan::select(['loans.*'])->with([
                'transaction'
            ])->where('loans.drawdown_acc', $old_drawdown->account_no)->first();

            // dd($old_drawdown);
				
			return $this->view('sale.transfer_balance',compact('old_drawdown','new_drawdown','loan'));
		}
	}
    
    public function transferInterProject()
    {

	
			$old_drawdown=null;
			// $old_drawdown = DrawdownAccounts::with(
            //     ['projects','unitType','units'])->where('unit_id',$id)->where('status',2)->first(); 

			$new_drawdown = null;
            // $new_drawdown=DrawdownAccounts::with( ['projects','unitType','units'])->where('unit_id',$id)->where('status',1)->first();

            // Nothing searched yet, so there are no To-side accounts to choose between.
            $new_drawdowns = [];

            $loan = Loan::select(['loans.*'])->with([
                'transaction'
            ])->where('loans.drawdown_acc', $old_drawdown->account_no)->first();

            // dd($old_drawdown);

			return $this->view('sale.transfer_inter_project',compact('old_drawdown','new_drawdown','new_drawdowns','loan'));
		
	}

    public function postTransferInterProject()
    {
        
            $data['branches'] = CompanyBranch::where('status', 1)->get();
            $data['projects'] = Project::select('id','dealer','short_code')->get();


			$old_drawdown=null;
			$dd = DrawdownAccounts::with(
                ['projects','unitType','units'])->where('unit_id',Request::input('unit_id')); 


                $unit_id = null;
                $unit=null;
                if(Request::has('unit_id')){
                    $unit_id = Request::input('unit_id');
                    if(!empty($unit_id)){
                        $dd->where('drawdown_account.unit_id',$unit_id);
                        $unit = Unit::find($unit_id);
                    }
                }

                // Ordered like the To side. Without this the From account was whatever MySQL returned
                // first, which on a unit with several accounts is arbitrary: unit AF06 has three, and
                // the unordered ->first() landed on a written-off loan while a disbursed one sat on the
                // same unit. Drawdown status carries the answer -- 2,999 of the 3,000 disbursed loans
                // hang off a status '1' account, while status '2' holds the written-off and terminated
                // ones -- so status then id picks the live account and keeps the choice stable.
                $old_drawdown = $dd->orderBy('drawdown_account.status')->orderBy('drawdown_account.id')->first();
// dd(Request::input('unit_id'));
            
            $new_drawdown =null;
            // All drawdown statuses, matching the From side, which has never filtered on status.
            // The removed ->where('status', 1) matched nothing anyway: status is ENUM('0','1','2'), and
            // MySQL compares an ENUM against a number by POSITION, so 1 meant the first value '0' —
            // which no row holds. That is why the To side always came back empty.
            // Ordered so a unit with more than one drawdown account picks the same one every time.
            $new_dd = DrawdownAccounts::with( ['projects','unitType','units'])->where('unit_id',Request::input('to_unit_id'))->orderBy('status')->orderBy('id');
            $to_unit_id = null;
            $to_unit=null;
            if(Request::has('to_unit_id')){
                $to_unit_id = Request::input('to_unit_id');
                if(!empty($to_unit_id)){
                    $new_dd->where('drawdown_account.unit_id',$to_unit_id);
                    $to_unit = Unit::find($to_unit_id);
                }
            }
			$new_drawdown  = $new_dd->first();

            // Every drawdown account on the To unit, with whatever loan sits on it, for the picker the
            // blade shows on cross-project and cross-company transfers. 1,196 units carry more than one
            // account, and ->first() above decides silently which of them receives the balance. Inside
            // one project there is nothing to choose; across projects and companies the receiving book
            // is a real decision, so the user makes it. The join cannot multiply rows -- no drawdown
            // account in the data carries more than one loan.
            $new_drawdowns = DrawdownAccounts::leftJoin('loans', 'loans.drawdown_acc', '=', 'drawdown_account.account_no')
                ->where('drawdown_account.unit_id', Request::input('to_unit_id'))
                // select(), not selectRaw(): the tb_ prefix is a connection setting, so the builder adds
                // it to qualified column names but leaves raw SQL exactly as written. selectRaw here
                // asked for drawdown_account.id and got "Unknown column" on every search.
                ->select([
                    'drawdown_account.id',
                    'drawdown_account.account_no',
                    'drawdown_account.account_name',
                    'drawdown_account.balance',
                    'drawdown_account.status',
                    'loans.status as loan_status',
                ])
                ->orderBy('drawdown_account.status')
                ->orderBy('drawdown_account.id')
                ->get();

            // Different projects, and different companies, are both allowed. The old guard rejected any
            // project mismatch on a screen called Transfer Inter Project -- the one thing it is named
            // for -- and did it silently: it flashed an error but blanked $old_drawdown, so the panel
            // just went empty. Six cross-project transfers already exist in the data (ESP-KR -> ESP-KP,
            // all Approved), so the business plainly does them. The blade's "Transfer type" badge now
            // states the scope instead: Internal Project / Cross Project / Cross Company.
            // Only genuinely missing accounts stop the search.
            if (empty($old_drawdown)) {
                Session::flash('error', 'No drawdown account found for the From unit.');
            } elseif (empty($new_drawdown)) {
                Session::flash('error', 'No drawdown account found for the To unit.');
            }

            

            $loan = Loan::select(['loans.*'])->with([
                'payment'
            ])->where('loans.drawdown_acc', $old_drawdown->account_no)->first();

            // The loan on the receiving side, so the comparison table can show its real figures rather
            // than the hardcoded 0 the other rows use. Guarded: $new_drawdown is null when the To unit
            // has no drawdown account.
            $new_loan = !empty($new_drawdown)
                ? Loan::select(['loans.*'])->where('loans.drawdown_acc', $new_drawdown->account_no)->first()
                : null;

            // Only a disbursed loan can have its balance transferred. Moving paid principal and interest
            // off a loan that was never drawn down -- unauthorized, approved-but-not-disbursed, rejected,
            // or already written off, paid off, closed or terminated -- would post figures onto an
            // account that never held them. Status 8 (Reschedule) counts as disbursed: it is a drawn-down
            // loan that was later rescheduled, and four approved transfers in the data already have a
            // status 8 on one side. The search stops here with a message naming the offending side and
            // its real status, rather than rendering a panel that could never be saved correctly.
            if (!empty($old_drawdown) && !empty($new_drawdown)) {

                // A unit cannot transfer to itself. Picking the same unit on both sides resolved to one
                // drawdown account, and the panel then offered to move that account's balance onto
                // itself -- on approval that debits and credits the same account and terminates the loan
                // for nothing. Checked on the account as well as the unit, so two accounts that resolve
                // to the same row are caught too.
                if ($old_drawdown->id == $new_drawdown->id
                    || (int) $old_drawdown->unit_id == (int) $new_drawdown->unit_id) {

                    Session::flash('error', 'Failed! The From and To unit are the same ('
                        . (!empty($old_drawdown->units) ? $old_drawdown->units->code : '?')
                        . '). Choose a different unit to transfer to.');
                    $old_drawdown = null;
                    $new_drawdown = null;

                } else {

                    // Statuses a transfer is allowed from and into. A loan must have been drawn
                    // down for there to be a balance to move, which rules out Unauthorize,
                    // Approved and Rejected; Write-Off, Closed, Terminate and Buy-back are ended
                    // in a way that should not be reopened by a transfer.
                    //   3  Disbursed
                    //   8  Reschedule (approved) -- drawn down, then rescheduled
                    //   9  Pay-Off
                    //  10  Completed
                    $allowed_status = [3, 8, 9, 10];
                    $loan_statuses  = config('static_data.loan_status');
                    $old_status     = !empty($loan)     ? (int) $loan->status     : 0;
                    $new_status     = !empty($new_loan) ? (int) $new_loan->status : 0;

                    $blocked = [];
                    if (!in_array($old_status, $allowed_status)) {
                        $blocked[] = 'From unit ' . (!empty($old_drawdown->units) ? $old_drawdown->units->code : '?')
                            . ' is ' . (isset($loan_statuses[$old_status]) ? $loan_statuses[$old_status] : 'not on any loan');
                    }
                    if (!in_array($new_status, $allowed_status)) {
                        $blocked[] = 'To unit ' . (!empty($new_drawdown->units) ? $new_drawdown->units->code : '?')
                            . ' is ' . (isset($loan_statuses[$new_status]) ? $loan_statuses[$new_status] : 'not on any loan');
                    }

                    if (!empty($blocked)) {
                        Session::flash('error', 'Failed! A transfer is only allowed on a loan that is'
                            . ' Disbursed, Reschedule (approved), Pay-Off or Completed. '
                            . implode('; ', $blocked) . '.');
                        $old_drawdown = null;
                        $new_drawdown = null;
                    }
                }
            }

			return $this->view('sale.transfer_inter_project',compact('old_drawdown','new_drawdown','new_drawdowns','loan','new_loan','unit_id','unit','to_unit_id','to_unit'));
		
	}

    
    public function postGetTransferBalance($id = 0)
    {
        // dd( Request::input());

		if ($id > 0) {
            $data['branches'] = CompanyBranch::where('status', 1)->get();
            $data['projects'] = Project::select('id','dealer','short_code')->get();


			$old_drawdown=null;
			$dd = DrawdownAccounts::with(
                ['projects','unitType','units'])->where('unit_id',Request::input('unit_id')); 


                $unit_id = null;
                $unit=null;
                if(Request::has('unit_id')){
                    $unit_id = Request::input('unit_id');
                    if(!empty($unit_id)){
                        $dd->where('drawdown_account.unit_id',$unit_id);
                        $unit = Unit::find($unit_id);
                    }
                }

                $old_drawdown = $dd->first(); 

			$new_drawdown = DrawdownAccounts::with( ['projects','unitType','units'])->where('unit_id',$id)->where('status',1)->first(); 


            $old_project = Project::where('id',$old_drawdown->project_id)->first();
            $oldProjectCode=$old_project->project_code?$old_project->project_code:'old';

            $new_project = Project::where('id',$new_drawdown->project_id)->first();
            $newProjectCode=$new_project->project_code?$new_project->project_code:'new';
             if($oldProjectCode!=$newProjectCode){
                    if($old_drawdown->project_id!=$new_drawdown->project_id){
                        Session::flash('error', 'Failed! projects could not be different.');
                        $old_drawdown=null;
                        // return redirect()->back()->with(['error' => 'Failed! projects could not be different.']);
                    }

             }


            $loan = Loan::select(['loans.*'])->with([
                'payment'
            ])->where('loans.drawdown_acc', $old_drawdown->account_no)->first();




            // dd($old_drawdown);
				
			return $this->view('sale.transfer_balance',compact('old_drawdown','new_drawdown','loan','unit_id','unit'));
		}
	}


    public function postTransferBalance()
    {

        DB::beginTransaction();
        try {
            if(empty(Request::input('from_account_no'))){
                return redirect()->back()->with(['error' => 'Failed! invalid account transfer']);
            }

            // One transfer out of a unit at a time. Nothing used to stop the same transfer
            // being filed again while an earlier one still waited for a decision, so a rejected
            // attempt that was simply re-submitted left a row behind each time: account
            // 010009622 -> 010015917 has eight rows from a single day, seven Rejected carrying
            // 13.00 and one Approved carrying 13,699.00, and R8 printed all eight as identical
            // lines. 77 of 260 from/to pairs repeat this way. Blocking on the FROM account alone
            // is deliberate: a unit with a decision outstanding should not be able to start a
            // second transfer to a different destination either.
            // Only Pending blocks. Approved and Rejected are settled, and an approved transfer
            // is already unrepeatable because approval leaves the old loan at Terminate, which
            // the search screen does not accept.
            $pending = TransferBalance::where('from_account_no', Request::input('from_account_no'))
                ->where('status', 'Pending')
                ->orderBy('id', 'desc')
                ->first();

            if (!empty($pending)) {
                DB::rollback();
                return redirect()->back()->with(['error' =>
                    'Failed! This unit already has a transfer waiting for approval (#' . $pending->id
                    . ', to ' . $pending->to_account_name . '-' . $pending->to_account_no
                    . '). Approve or reject it first, then transfer again.']);
            }

                $item = new TransferBalance;
                $item->from_drawdown_id =  Request::input('from_drawdown_id');
                $item->from_account_name =  Request::input('from_account_name');
                $item->from_account_no =  Request::input('from_account_no');
                $item->to_drawdown_id =  Request::input('to_drawdown_id');
                $item->to_account_name =  Request::input('to_account_name');
                $item->to_account_no =  Request::input('to_account_no');
                $item->status='Pending';
                $item->created_by = Auth::user()->id;
                $item->total_principal_paid=Request::input('total_principal_paid');
                $item->total_interest_paid=Request::input('total_interest_paid');
                


                $item->save();

                DB::commit();
            
                return redirect()->route('list_sale');
            
        } catch (Exception $e) {
            DB::rollback();
            Session::flash('message', 'Save not successfully');
            return redirect()->back();
        }
    }

    public function getTransferBalanceList() {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 50;
        if(!$offset){
        	$offset = 50;
        }

		$B0 = new TransferBalance();
		$com = $B0->selectRaw('*')
		->where('status','Pending')
		->orderBy('id', 'desc');


		$client = null;
        $query_url = [];
        $company=null;
        if(Request::get('company')){
            $com = $com->where('commission_withdrawal.company_id',Request::get('company'));
            $company = CompanyBranch::find(Request::get('company'));
        }
        $status = '';

        $project_id = null;
        if(Request::has('project_id')){
            $project_id = Request::input('project_id');
            $project = Project::find($project_id);
            $com = $com->where('project_id',$project_id);
        }
        $unit_type_id = null;
        if(Request::has('unit_type_id')){
            $unit_type_id = Request::input('unit_type_id');
            $unit_type = UnitType::find($unit_type_id);
            $com = $com->where('unit_type_id',$unit_type_id);
        }
        $unit_id = null;


       $listSale = $com->paginate($offset)->setPath('sale.list_transfer_balance?company='.$company.'&project_id='.$project_id.'&status='.$status.'&search='.$search.'&offset='.$offset);
// dd($listSale);
		$contract_template = config('static_data.contract_template');
        return $this->view('sale.list_transfer_balance', ['lists' => $listSale, 'company' => $company,'offset'=>$offset,'contract_template' => $contract_template],compact('projects','project_id','unit_type_id','client','from_date','to_date','company','status','sale_person_list','saleRepresentative','sale_person'));

    }

    public function transfer_balance_approve($id = 0)
    {
        if ($id > 0) {

            DB::beginTransaction();
                try {
                    $tranfer_balance = TransferBalance::find($id);
                    if (!empty($tranfer_balance)) {
                        $tranfer_balance->status="Approved";
                        $tranfer_balance->approval_by=Auth::user()->id;
                        if($tranfer_balance->save()){
                            $new_loan = Loan::where('drawdown_acc',$tranfer_balance->to_account_no)->first(); 
                            // No status filter: account_no is unique across all 16,447 drawdown accounts,
                            // so it already identifies one row. The old ->where('status',1) matched
                            // nothing at all — status is ENUM('0','1','2') and MySQL compares an ENUM to
                            // a number by POSITION, so 1 meant '0', which no row holds. $new_drawdown was
                            // therefore always null and the credit below silently never ran, leaving the
                            // balance debited from the old account but never added to the new one.
                            $new_drawdown = DrawdownAccounts::where('account_no',$tranfer_balance->to_account_no)->first();

                            $old_loan = Loan::where('drawdown_acc',$tranfer_balance->from_account_no)->first();
                            $old_drawdown = DrawdownAccounts::where('account_no',$tranfer_balance->from_account_no)->first();

                            // A transfer whose two sides sit in different branches needs a settlement
                            // pair, or the entry balances in total while leaving one branch net debit
                            // and the other net credit. Branch maps 1:1 to company in this data --
                            // company 1 books all 12 of its projects to '01', company 2 all 8 of its
                            // to '02', and no other branch exists -- so this fires on a cross-COMPANY
                            // transfer and never on the 247 internal or cross-project ones already
                            // done, which balance inside their single branch and are untouched here.
                            $old_branch   = !empty($old_drawdown) ? $old_drawdown->branch : null;
                            $new_branch   = !empty($new_drawdown) ? $new_drawdown->branch : null;
                            $cross_branch = !empty($old_branch) && !empty($new_branch) && $old_branch != $new_branch;

                            // Found by account_code, which is unique, rather than by name and currency
                            // like the two loan GLs below. 296502-01-0000 has never been posted to, so
                            // its balance starts clean.
                            $gl_inter_branch = $cross_branch
                                ? CoaCategory::where('account_code', '296502-01-0000')->first()
                                : null;

                            // Refuse rather than post half an entry: without this account the value
                            // would leave one branch and never arrive on the other.
                            if ($cross_branch && empty($gl_inter_branch)) {
                                DB::rollback();
                                Session::flash('error', 'Failed! This transfer crosses branches ('
                                    . $old_branch . ' to ' . $new_branch . ') but the Inter-Branch account'
                                    . ' 296502-01-0000 was not found. Nothing was posted.');
                                return redirect()->back();
                            }

                            // Say in the journal what the transfer was. Neither journal table has a
                            // project column, so the description is the only place the scope can be
                            // recorded -- and until now it named only the two account holders, so
                            // nothing in the ledger showed that a transfer had crossed anything.
                            // Relations are read into variables first: empty($model->relation) is
                            // false-negative on a relation that has not been eager-loaded, because
                            // empty() consults __isset() and Eloquent answers 'not loaded'. These
                            // drawdowns are loaded without with([...]), so testing inline would leave
                            // every project null and file a Cross Company transfer as Cross Project.
                            $old_project = !empty($old_drawdown) ? $old_drawdown->projects : null;
                            $new_project = !empty($new_drawdown) ? $new_drawdown->projects : null;
                            $old_unit    = !empty($old_drawdown) ? $old_drawdown->units : null;
                            $new_unit    = !empty($new_drawdown) ? $new_drawdown->units : null;

                            if (!empty($old_project) && !empty($new_project)
                                && $old_project->company_id != $new_project->company_id) {
                                $transfer_scope = 'Cross Company';
                            } elseif (!empty($old_drawdown) && !empty($new_drawdown)
                                && $old_drawdown->project_id != $new_drawdown->project_id) {
                                $transfer_scope = 'Cross Project';
                            } else {
                                $transfer_scope = 'Internal Project';
                            }

                            // Trimmed to the column, which is varchar(255). The longest transfer
                            // description on file is 115 characters; Khmer account names are the part
                            // that grows, so the cut is a guard rather than something expected to bite.
                            $journal_desc = 'Transfer balance (' . $transfer_scope . ') '
                                . (!empty($old_unit) ? $old_unit->code : '?')
                                . '[' . (!empty($old_project) ? $old_project->short_code : '?') . '] -> '
                                . (!empty($new_unit) ? $new_unit->code : '?')
                                . '[' . (!empty($new_project) ? $new_project->short_code : '?') . ']: '
                                . $tranfer_balance->from_account_name . '-' . $tranfer_balance->from_account_no
                                . ' to ' . $tranfer_balance->to_account_name . '-' . $tranfer_balance->to_account_no;
                            $journal_desc = mb_substr($journal_desc, 0, 255);

                            $transaction = new TransactionsRequiry();
                            $transaction->loan_id = $new_loan->id;
                            $transaction->trans_date =  date('Y-m-d H:i:s');
                            $transaction->trans_type = "Auto Loan Repayment";
                            $transaction->amount = floatval($tranfer_balance->total_principal_paid) + floatval($tranfer_balance->total_interest_paid);
                            $transaction->principal=floatval($tranfer_balance->total_principal_paid);
                            $transaction->interest=floatval($tranfer_balance->total_interest_paid);
                            $transaction->description = "Change Unit: Auto transfer balance  from".$tranfer_balance->from_account_name.'-'.$tranfer_balance->from_account_no.'to'.$tranfer_balance->to_account_name.'-'.$tranfer_balance->to_account_no;
    
                            
                            if($transaction->save()){

                            // Journal for new unit
                            $journal = new JournalRequiry;
                            $journal->tran_id = 0;
                            $journal->entry_date = date('Y-m-d H:i:s');
                            $journal->invoice_number = null;
                            $journal->description = $journal_desc;
                            $journal->user_id = Auth::user()->id;
                            $journal->is_audit = 1;
                            $journal->disburs_loan_id = $new_loan->id;
                            if ($journal->save()) {        
                            
                            $gl_principle = CoaCategory::where('type',6)->where('name','Stand-L-PHL-Indi-RT >1 year')->where('currency',2)->first(); 
                            $gl_interest = CoaCategory::where('type',6)->where('name','AIR-Stand-L-PHL-Indi-RT<=1 year')->where('currency',2)->first(); 

                                if(floatval($tranfer_balance->total_principal_paid)>0){
                                    $old_jd = new JournalDetail;
                                    $old_jd->journal_id = $journal->id;
                                    $old_jd->coa_id=$gl_principle->id;
                                    $old_jd->branch_code=$old_drawdown->branch;
                                    $old_jd->p_debit = 0;
                                    $old_jd->p_credit = 0;
                                    $old_jd->debit = floatval($tranfer_balance->total_principal_paid) ;
                                    $old_jd->credit = 0;
                                    $old_jd->b_debit = 0;
                                    $old_jd->b_credit = 0;
                                    $old_jd->description =   $journal_desc;
                                    $old_jd->is_audit = 1;
                                    $old_jd->disburs_loan_id =  $old_loan->id;
                                    $old_jd->save();
                                    
                                }

                                
                                if(floatval($tranfer_balance->total_interest_paid)>0){
                                    $old_jd = new JournalDetail;
                                    $old_jd->journal_id = $journal->id;
                                    $old_jd->coa_id=$gl_interest->id;
                                    $old_jd->branch_code=$old_drawdown->branch;
                                    $old_jd->p_debit = 0;
                                    $old_jd->p_credit = 0;
                                    $old_jd->debit = floatval($tranfer_balance->total_interest_paid);
                                    $old_jd->credit = 0;
                                    $old_jd->b_debit = 0;
                                    $old_jd->b_credit = 0;
                                    $old_jd->description =   $journal_desc;
                                    $old_jd->is_audit = 1;
                                    $old_jd->disburs_loan_id =  $old_loan->id;
                                    $old_jd->save();
                                    
                                }

 
                                    $jd = new JournalDetail;
                                    $jd->journal_id = $journal->id;
                                    $jd->coa_id=$new_drawdown->coa_id;
                                    $jd->branch_code=$new_drawdown->branch;
                                    $jd->p_debit = 0;
                                    $jd->p_credit = 0;
                                    $jd->debit = 0;
                                    $jd->credit = floatval($tranfer_balance->total_principal_paid) + floatval($tranfer_balance->total_interest_paid);
                                    $jd->b_debit = 0;
                                    $jd->b_credit = 0;
                                    $jd->description =   $journal_desc;
                                    $jd->is_audit = 1;
                                    $jd->disburs_loan_id =  $new_loan->id;
                                    $jd->save();

                                // Settlement pair, cross-branch only. The three lines above leave
                                // the sending branch holding the debits and the receiving branch the
                                // credit; these two give each branch its own counterpart and cancel
                                // each other at group level. Standard due-to / due-from treatment.
                                if ($cross_branch) {
                                    $ib_total = floatval($tranfer_balance->total_principal_paid)
                                              + floatval($tranfer_balance->total_interest_paid);
                                    $ib_desc  = mb_substr('Inter-branch settlement ' . $old_branch
                                              . '->' . $new_branch . ': ' . $journal_desc, 0, 255);

                                    // Due to the receiving branch, raised on the sending branch.
                                    $ib_out = new JournalDetail;
                                    $ib_out->journal_id = $journal->id;
                                    $ib_out->coa_id = $gl_inter_branch->id;
                                    $ib_out->branch_code = $old_branch;
                                    $ib_out->p_debit = 0;
                                    $ib_out->p_credit = 0;
                                    $ib_out->debit = 0;
                                    $ib_out->credit = $ib_total;
                                    $ib_out->b_debit = 0;
                                    $ib_out->b_credit = 0;
                                    $ib_out->description = $ib_desc;
                                    $ib_out->is_audit = 1;
                                    $ib_out->disburs_loan_id = $old_loan->id;
                                    $ib_out->save();

                                    // Due from the sending branch, raised on the receiving branch.
                                    $ib_in = new JournalDetail;
                                    $ib_in->journal_id = $journal->id;
                                    $ib_in->coa_id = $gl_inter_branch->id;
                                    $ib_in->branch_code = $new_branch;
                                    $ib_in->p_debit = 0;
                                    $ib_in->p_credit = 0;
                                    $ib_in->debit = $ib_total;
                                    $ib_in->credit = 0;
                                    $ib_in->b_debit = 0;
                                    $ib_in->b_credit = 0;
                                    $ib_in->description = $ib_desc;
                                    $ib_in->is_audit = 1;
                                    $ib_in->disburs_loan_id = $new_loan->id;
                                    $ib_in->save();
                                }
                                
                            }

                            // Update Drawdown Acc. Balance
                            $new_drawdown->old_balance = $new_drawdown->balance;
                            $new_drawdown->balance = floatval($new_drawdown->balance) + (floatval($tranfer_balance->total_principal_paid) + floatval($tranfer_balance->total_interest_paid));

                            $new_drawdown->save();

                            // The balance has landed on the new unit, so the old loan is finished:
                            // terminate it. Only on approve — postTransferBalance just files a Pending
                            // row, and terminating there would kill the loan before anyone approved it,
                            // with nothing to undo it if the transfer were rejected. This is what staff
                            // have been doing by hand: of 239 approved transfers, 233 old loans already
                            // sit at 11.
                            // Terminate whenever the old loan was in a state a transfer is allowed
                            // FROM -- the same four the search screen accepts. This used to be a
                            // skip list of [5, 6, 9, 11], which silently included 9 Pay-Off: once
                            // Pay-Off became transferable the balance moved but the old loan kept
                            // reading Pay-Off instead of Terminate. Stating the allowed set instead
                            // of a skip list keeps the two rules in step, and still leaves a second
                            // approval alone, since 11 is not in the set.
                            if (!empty($old_loan) && in_array((int) $old_loan->status, [3, 8, 9, 10])) {
                                $old_loan->status = 11; // Terminate
                                $old_loan->save();
                                $this->userActivity(Auth::user()->id, $old_loan->id, 6, 'Terminate Loan (transfer balance approved)');

                                // The balance has left the old unit, so its loan account is finished too:
                                // close it and take the outstanding balance to 0. Terminating the loan alone
                                // left the account at Standard still carrying the old outstanding -- BF05 read
                                // 51,951.00 and MP-818 19,033.83 after their transfers were approved, and the
                                // testers reported exactly that. Staff have been doing this by hand: of 243
                                // approved transfer-outs 235 accounts already sit at 0.00 and 227 of those at
                                // status 9 (Closed). Inside the terminate branch on purpose -- a second
                                // approval finds status 11 and skips the branch, so it cannot re-close.
                                // old_balance is deliberately NOT written: despite its name it is not a
                                // before-this-write slot on this table. Every one of its 13,751 values was
                                // stamped by the 2021-12-31 migration, no account created since carries one,
                                // and on the 244 transfer-out accounts it equals the cleared balance twice
                                // and the original loan amount 119 times. Nothing reads it; writing it would
                                // only overwrite import residue with a figure of a different kind.
                                $old_loan_account = ClientLoanAccounts::where('id', $old_loan->loan_account_id)->first();
                                if (!empty($old_loan_account)) {
                                    $old_loan_account->balance = 0;
                                    $old_loan_account->status = 9; // Closed (client_loan_account_status_new)
                                    $old_loan_account->save();
                                }
                            }


                            }

                        }
                    }

                    DB::commit();
                    Session::flash('message', 'Save not successfully');
                    return redirect()->back();
                } catch (Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            
        }
    }
    public function transfer_balance_reject($id = 0)
    {
        if ($id > 0) {

            DB::beginTransaction();
                try {
                    $tranfer_balance = TransferBalance::find($id);
                    if (!empty($tranfer_balance)) {
                        $tranfer_balance->status="Rejected";
                        $tranfer_balance->approval_by=Auth::user()->id;
                        $tranfer_balance->save();

                    }

                    DB::commit();
                    Session::flash('message', 'Save not successfully');
                    return redirect()->back();
                } catch (Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            
        }
    }

    
    
}
