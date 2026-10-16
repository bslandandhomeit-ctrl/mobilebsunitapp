<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers;

use App\Models\CompanyBranch;
use App\Models\LoanApproval;
use App\Models\LoanCollateral;
use App\Models\LoanCostFee;
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
use App\Models\Holiday;
use App\Models\TransactionsRequiry;
use App\Models\Account;
use App\Models\CoaCategory;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Models\Loan;
use App\Models\RepaymentSchedule;
use App\Models\ClientLoanAccounts;
use App\Models\PenaltyRecord;
use App\Models\Teller;
use App\Models\DrawdownAccounts;
use App\Models\LoanPaymentsDraft;
use App\Models\Role;
use App\Models\SystemDate;
use DB;

use Request;

//use App\Http\Requests\Request;
use Auth;
use Image;
use App;
use App\Models\FeeCharge;
use URL;
use File;
use LoanCalculate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
/**
 * Description of RepaymentController
 * @author theary
 */
class RepaymentController extends Controller
{
    //put your code here

    public function __construct()
    {
        $this->middleware('xss');
        $this->middleware('auth');
    }

    public function addLoanRepay($loan_id = null)
    {

        $data = [];
        if (!Request::input('key')) $data['loan_row'] = Loan::find($loan_id);
        if (Request::has('key')) {


            //check is already approve draft
            $dpDate = $data['dpDate'] = Request::get('dpDateClone');
            if ($dpDate == "") $dpDate = $data['dpDate'] = date('Y-m-d');
            $data['draft_id'] = $draft_id = Request::has('draft_id') ? Request::input('draft_id') : 0;
            $draft = LoanPaymentsDraft::find($draft_id);
            if ($draft->status == 1) return redirect()->back();
            //$allow_roles = config('static_data.allow_roles');
            //if($draft && $draft->user_id != Auth::user()->id && !in_array(Auth::user()->role_id, $allow_roles)) return redirect()->back();
            $role = Role::find(Auth::user()->role_id)->first();
            if ($draft && !($role->role_lvl >= 2 && ($role->sub_dept == 13) || $role->dept >= 5)) return redirect()->back()->with('msg', 'You have no permission!!');

            $data['key'] = $key = Request::input('key');
            $data['draft'] = $draft;
            $data['user_id'] = Auth::user()->id;
            //if(!$draft){

            $query_arr = array('user_id' => Auth::user()->id, 'company_branch_id' => Auth::user()->branch_id, 'role_id' => Auth::user()->role_id);
            $B0 = new Loan();
            //$B0 = $this->getUserByBranch($B0, 'company_branch_id', $query_arr);

            $loan_repay = $B0->select(['loans.*', 'clients.client_name', 'client_loan_accounts.account_no'])
                ->join('client_loan_accounts', 'client_loan_accounts.id', '=', 'loans.loan_account_id')
                ->join('clients', 'clients.id', '=', 'loans.client_id');
            $loan_repay = $loan_repay->with([
                'payment' => function ($q) {
                    $q->select('id', 'loan_id', 'paid_principal', 'paid_interest', 'paid_fee', 'paid_other_fee', 'penalty_amount', 'repayment_owed', 'repayment_date', 'payment_month', 'status', 'condition_id')->orderBy('id', 'asc');
                },
                'client_loan_account' => function ($q) {
                    $q->select('id', 'account_name', 'account_no', 'air_id', 'coa_id', 'status');
                },
                'schedule' => function ($q) {
                    $q->select('loan_id', 'schedule_date', 'date_num', 'interest', 'principal', 'fee', 'other_fee', 'intraday_rate', 'no', 'type', 'loan_no');
                },
                'transaction' => function ($q) {
                    $q->where('trans_type', 'LIKE', '%Loan Repayment%');
                },
                'penalty_record' => function ($q) {
                    $q->select('id', 'loan_id', 'record_date', 'owed_penalty')->orderBy('id', 'DESC');
                }
            ])->whereIn('loans.status', [3, 8]);

            $loan_repay = $loan_repay->where('loans.contract_id', $key)->orwhere('client_name', $key)->orwhere('account_no', $key);
            $data['search_results'] = $loan = $loan_repay->first();
            $data['repayment_schedule'] = RepaymentSchedule::where('loan_id', $loan->id)->where('type', 'loan')->get();
            $data['downpayment'] = RepaymentSchedule::where('loan_id', $loan->id)->where('type', 'downpayment')->get();
            //for helper
            $branch = CompanyBranch::find($loan->company_branch_id);
            $loan_account = ClientLoanAccounts::find($loan->loan_account_id);
            $coa = CoaCategory::find($loan_account->coa_id);

            //get default select_type 1
            //$coa_coh = CoaCategory::select('id', 'account_code', 'name')->where('type', 5)->where('currency', $loan_account->currency)->where('name', 'Cash in Vault and on Hand')->first();
            $coa_dd = DrawdownAccounts::select('balance', 'currency', 'coa_id', 'client_id')->with('coa')->where('client_id', '=', $loan->client_id)->where('currency', '=', $loan_account->currency)->where('account_no', $loan->drawdown_acc)->first();
            $coa_air = CoaCategory::find($loan_account->air_id);
            $coa_int_inc = CoaCategory::select('id', 'account_code', 'name')->where('id', $loan_account->int_inc_id)->first();
            $coa_ofc = CoaCategory::select('id', 'account_code', 'name')->where('type', 6)->where('currency', $draft ? $draft->currency : $loan_account->currency)->where('name', 'Inc-Other Fees and Commissions from Loans and Leasing')->first();
            $coa_ap = CoaCategory::where('name', 'like', '%Accounts Payable-Other%')
                ->where('currency', '=', $draft ? $draft->currency : $loan_account->currency)->first();
            $coa_pnt = CoaCategory::select('id', 'account_code', 'name')->where('type', 6)->where('currency', $draft ? $draft->currency : $loan_account->currency)->where('name', 'Inc-Fine and Penalty on Repayment of Loans and leasing')->first();
            //$coa_ap = CoaCategory::find($loan_account->ap_id);

            // air amount
            $air_journal = JournalDetail::select('debit', 'credit', 'coa_id', 'id', 'reference')->where('coa_id', $loan_account->air_id)->where('reference', 'LIKE', $loan_repay->first->contract_id . '%')->orderBy('id', 'desc')->get();
            $total_debit = 0.0;
            $total_credit;
            foreach ($air_journal as $tr) {
                $total_debit += $tr->debit;
                $total_credit += $tr->credit;
            }
            $data['air_amount'] = $total_debit - $total_credit;
            if($data['air_amount'] < 0){
                $data['air_amount'] = 0;
            }
            //dd($last_balance);


            //sch_interest
            //$data['sch_interest'] =
            //}else{

            //}
            // $sch_repay_arr = get_auto_repay_array($data['search_results'], $dpDate);
            $sch_repay_arr = get_auto_repay_array_downpayment($data['search_results'], $dpDate);

            $teller = Teller::select('till_account.id', 'account_no', 'account_name', 'till_account.branch_id', 'users.name', 'roles.role', 'assign_user_id', 'till_account.balance', 'till_account.status')
                ->join('users', 'users.id', '=', 'till_account.assign_user_id')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->where('roles.role', 'like', '%teller%')
                ->where('till_account.branch_id', '=', Auth::user()->branch_id)
                ->where('currency_id', '=', $loan_account->currency)
                ->get();
            //get arrear penalty
            $data['branch_name'] = $branch->branch_name;
            $data['branch_code'] = $branch->branch_code;
            $data['currency'] = $loan_account->currency;
            $data['coa'] = $coa;
            $data['coa_dd'] = $coa_dd->coa;
            $data['coa_air'] = $coa_air;
            $data['coa_ofc'] = $coa_ofc;
            $data['coa_pnt'] = $coa_pnt;
            $data['coa_int_inc'] = $coa_int_inc;
            $data['coa_ap'] = $coa_ap;
            $data['drawdown_bal'] = $coa_dd->balance;
            // $data['drawdown_bal'] = abs(get_journal_bal($coa_dd->coa_id, $dpDate)['balance']);
            $data['teller'] = $teller;
            $data['sch_repay_down_loan_arr'] = $sch_repay_arr;
        }
        return $this->view('repay.add', $data);
    }


    public function postLoanRepay()
    {
        $repaymentTransactionLevel = DB::connection()->transactionLevel();
        DB::beginTransaction();
        try {

            $data = Request::except(['_token']);
            $rules = [
//     			'act_principal' => 'required|numeric',
//     			'act_interest' => 'required|numeric',
//     			'act_penalty' => 'required|numeric',
//     			'amount_payable' => 'required|numeric',
//            'invoice_number' => 'required'
            ];
            // dd(Request::input());
            $attribs = [
                //    'invoice_number' => 'Invoice number required.'
            ];
            
            $validator = Validator::make($data, $rules);
            $validator->setAttributeNames($attribs);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator);
            } else {

                $loan_id = Request::input('loan_id');
                $user_id = Auth::user()->id;
                $branch_id = Auth::user()->branch_id;
                $invoice_number = Request::input('invoice_number');
                $loan = Loan::with('client_loan_account')
                                ->with('schedule')->with('payment')
                                ->with(['penalty_record' => function ($q) {
                                        $q->select('id', 'loan_id', 'record_date', 'owed_penalty')->orderBy('id', 'DESC');
                                    }])
                                ->where('id', $loan_id)->first();

                $coa_dd = DrawdownAccounts::select('id', 'balance', 'coa_id', 'client_id')
                                            ->with('coa')
                                            ->where('client_id', '=', $loan->client_id)
                                            ->where('account_no', $loan->drawdown_acc)->first();
                if (floatval($coa_dd->balance) - floatval(Request::input('repayment_amount')) < 0) {
                    Session::flash('danger', 'Balance in Drawdown Account is insufficient!');
                    return redirect()->back();
                }

                $coa_dd_update = $coa_dd;
                if ($coa_dd) {
                    $coa_dd_update->balance -= floatval(Request::input('repayment_amount'));
                }
                //if($coa_dd->balance < 0) return redirect()->back()->withErrors($validator);
                if (!empty($loan)) {

                    $journal_arr = [];
                    $journal_down_arr = [];
                    $debit_arr = [];
                    $debit_down_arr = [];
                    $credit_arr = [];
                    $credit_down_arr = [];
                    $desc_arr = [];
                    $desc_down_arr = [];
                    if (Request::input('debit')[0] > 0) {
                        $debit_arr[0]['coa'] = Request::input('parent_debit')[0];
                        $debit_arr[0]['amount'] = floatval(Request::input('debit')[0]);
                        $debit_arr[0]['desc'] = Request::input('d_description')[0];
                        $credit_arr[0]['coa'] = Request::input('parent_credit')[0];
                        $credit_arr[0]['amount'] = floatval(Request::input('credit')[0]);
                        $credit_arr[0]['desc'] = Request::input('c_description')[0];
                        $desc_arr[0]['desc'] = Request::input('description')[0];
                    }

                    if(Request::input('debit_down')[0] > 0){
                        $debit_down_arr[0]['coa'] = Request::input('parent_debit_down')[0];
                        $debit_down_arr[0]['amount'] = floatval(Request::input('debit_down')[0]);
                        $debit_down_arr[0]['desc'] = Request::input('d_description_down')[0];
                        $credit_down_arr[0]['coa'] = Request::input('parent_credit_down')[0];
                        $credit_down_arr[0]['amount'] = floatval(Request::input('credit_down')[0]);
                        $credit_down_arr[0]['desc'] = Request::input('c_description_down')[0];
                        $desc_down_arr[0]['desc'] = Request::input('description_down')[0];
                    }

                    for ($m = 0; $m < count(Request::input('debit')); $m++) {
                        if ($debit_arr[0]['coa'] == Request::input('parent_debit')[$m]) {
                            $debit_arr[0]['coa'] += floatval(Request::input('debit')[$m]);
                        }
                        if (Request::input('debit')[$m] > 0) {
                            if (Request::input('parent_debit')[$m]){
                                array_push($journal_arr, [
                                    Request::input('parent_debit')[$m],
                                    Request::input('debit')[$m],
                                    Request::input('d_description')[$m],
                                    Request::input('parent_credit')[$m],
                                    Request::input('credit')[$m],
                                    Request::input('c_description')[$m],
                                    Request::input('description')[$m]
                                ]);
                            }

                        }
                    }
                    $data_down = [];
                    $loan_credit_down = 0;
                    for ($down_pay=0; $down_pay < count(Request::input('debit_down')); $down_pay++) { 
                         if ($debit_down_arr[0]['coa'] == Request::input('parent_debit_down')[$down_pay]) {
                            $debit_down_arr[0]['coa'] += floatval(Request::input('debit_down')[$mdown_pay]);
                        }
                        if (Request::input('debit_down')[$down_pay] > 0) {
                            if (Request::input('parent_debit_down')[$down_pay]){
                                $loan_credit_down += isset(Request::input('credit_down')[$down_pay])?Request::input('credit_down')[$down_pay]:0;
                                array_push($journal_down_arr ,[
                                        Request::input('parent_debit_down')[$down_pay],
                                        Request::input('debit_down')[$down_pay],
                                        Request::input('d_description_down')[$down_pay],
                                        Request::input('parent_credit_down')[$down_pay],
                                        Request::input('credit_down')[$down_pay],
                                        Request::input('c_description_down')[$down_pay],
                                        Request::input('description_down')[$down_pay]
                                    ]);
                            }

                        }
                        
                    }
                    $principal_donw_loan = $data['act_principal'];
                    if($loan_credit_down > 0){
                        $data_down = [
                                    'act_interest'  => 0,
                                    'act_principal' => $loan_credit_down,
                                    'act_fee'       => 0,
                                    'act_other_fee' => 0,
                                    'act_penalty'   => 0,
                                    'act_total'     => $loan_credit_down,
                                    'note'          => $data['note']
                                    ];
                        $data['act_principal'] = $data['act_principal'] - $loan_credit_down;
                        $data['act_total'] = $data['act_total'] - $loan_credit_down;
                    }
                    $total_paid_prin = floatval($data['act_principal']);

                    $total_paid_int = floatval(Request::input('act_interest'));
                    $total_paid_fee = floatval(Request::input('act_fee'));
                    $total_paid_other_fee = floatval(Request::input('act_other_fee'));
                    $total_penalty = floatval(Request::input('act_penalty'));
                    (Request::input('last_paid_date') != "_") ? $last_paid_date = Request::input('last_paid_date') : $last_paid_date = $loan->start_date;
                    $pen_waive_flg = (Request::has('ch_waive_panalty')) ? Request::input('ch_waive_panalty') : 0;

                    // dd($journal_down_arr);

                    // For status from substandard
                    if ($loan->client_loan_account->status > GENERAL_LC_STATUS) {
                        if (floatval(Request::input('act_interest')) > 0) {
                            array_push($journal_arr, [
                                $loan->client_loan_account->sus_id,
                                floatval(Request::input('act_interest')),
                                Request::input('description')[0],
                                $loan->client_loan_account->int_inc_id,
                                floatval(Request::input('act_interest')),
                                Request::input('description')[0],
                                Request::input('description')[0]
                            ]);
                        }
                        //provision
                        if ($total_paid_prin > 0) {
                            // reverse provision
                            $prov_arr_rev = $prov_arr = [];
                            $prov_arr_rev = get_journal_provision($loan->client_loan_account, $loan->client_loan_account->balance, $loan->client_loan_account->status, GENERAL_LC_STATUS, "up");
                            if ($prov_arr_rev[0] != []) array_push($journal_arr, $prov_arr_rev[0]);
                            // do provision
                            $prov_arr = get_journal_provision($loan->client_loan_account, $loan->client_loan_account->balance - $total_paid_prin, GENERAL_LC_STATUS, $loan->client_loan_account->status, "down");
                            if ($prov_arr[0] != []) array_push($journal_arr, $prov_arr[0]);
                        }
                    } else {
                        // general provision
                        if ($total_paid_prin > 0) {
                            // reverse provision
                            $prov_arr_rev = $prov_arr = [];
                            $prov_arr_rev = get_journal_provision($loan->client_loan_account, $loan->client_loan_account->balance, $loan->client_loan_account->status, GENERAL_LC_STATUS, "up");
                            if ($prov_arr_rev[0] != []) array_push($journal_arr, $prov_arr_rev[0]);
                            // do provision
                            $prov_arr = get_journal_provision($loan->client_loan_account, $loan->client_loan_account->balance - $total_paid_prin, GENERAL_LC_STATUS, $loan->client_loan_account->status, "down");
                            if ($prov_arr[0] != []) array_push($journal_arr, $prov_arr[0]);
                        }
                    }

                    // Find owed_penalty
                    $owed_penalty = 0.0;
                    $penalty_record = ($loan->penalty_record);
                    foreach ($penalty_record as $rec) {
                        if ($last_paid_date == date('Y-M-d', strtotime($rec->record_date))) {
                            $owed_penalty = $rec->owed_penalty;
                            break;
                        }
                    }
                    // Record repayment
                    $repay_date = date('Y-m-d', strtotime(Request::input('dpDate')));
                    // $repay_actual_arr = [];
                    $repay_actual_loantype_arr = [];
                    $amount = round(floatval(Request::input('repayment_amount')), 2);
                    //$repay_actual_arr = get_auto_payment_actual($loan, $repay_date, $amount);

                    // $repay_actual_arr = get_manual_payment_actual($loan, $repay_date, $total_paid_int, $total_paid_fee, $total_paid_prin, $total_penalty, $pen_waive_flg, $total_paid_other_fee);
                    $repay_actual_loantype_arr = get_manual_payment_actual($loan, $repay_date, $total_paid_int, $total_paid_fee, $principal_donw_loan, $total_penalty, $pen_waive_flg, $total_paid_other_fee);

                    //$repayment_table = get_repayment_table($loan, $last_paid_date, $total_paid_prin, $total_paid_int, $total_penalty, $owed_penalty, $total_paid_fee, $repay_date, "repay");
                    $record_date = date('Y-m-d H:i:s', strtotime(Request::input('dpDate')));
                    $newPaymentIds = array('loan'=>array(), 'downpayment'=>array());
                    $postedRepayment = null;
                    $total_paid = 0;
                    $total_waive_penalty = 0;
                    if (!empty($repay_actual_loantype_arr) && count($repay_actual_loantype_arr) > 0) {
                        foreach ($repay_actual_loantype_arr as $key => $repay_actual_arr) {
                            foreach ($repay_actual_arr as $repay_table) {
                                // $paid_prin = ($repay_table['principal']) ? $repay_table['principal'] : 0;
                                // $paid_int = ($repay_table['interest']) ? $repay_table['interest'] : 0;
                                // $paid_fee = ($repay_table['fee']) ? $repay_table['fee'] : 0;
                                // $paid_penalty = ($repay_table['penalty']) ? $repay_table['penalty'] : 0;
                                $paid_prin = $repay_table['act_principal'];
                                $paid_int = $repay_table['act_interest'];
                                $paid_fee = $repay_table['act_fee'];
                                $paid_other_fee = $repay_table['act_other_fee'];
                                $paid_penalty = $repay_table['act_penalty'];
                                $total_paid += $repay_table['act_total'];
                                //var_dump($total_paid); dd(round(floatval(Request::input('repayment_amount')),2));
                                //if(round(floatval(Request::input('repayment_amount')),2) - round($total_paid,2) < 0) break;
                                $loan_payment = new LoanPayments();
                                $loan_payment->loan_id = $loan_id;
                                $loan_payment->invoice_number = '00000000';
                                $loan_payment->repayment_date = $repay_date;
                                $loan_payment->payment_month = $repay_table['month_idx'];
                                $loan_payment->paid_principal = $paid_prin;
                                $loan_payment->paid_interest = $paid_int;
                                $loan_payment->paid_fee = $paid_fee;
                                $loan_payment->loan_repayment_type = $repay_table['type'];
                                $loan_payment->paid_other_fee = $paid_other_fee;
                                $loan_payment->penalty_amount = $paid_penalty;
                                $loan_payment->payment_type = Request::input('payment_type');
                                $loan_payment->status = $repay_table['status'];
                                $loan_payment->condition_id = $repay_table['condition'];
                                $loan_payment->repayment_owed = $repay_table['repayment_owed'];
                                $loan_payment->waived_penalty = $repay_table['act_waive_penalty'];
                                $total_waive_penalty += $repay_table['act_waive_penalty'];
                                $month_idx = $repay_table['month_idx'];

                                // if (Request::has('ch_waive_panalty') && Request::input('ch_waive_panalty')==1) {
                                //     $loan_payment->repayment_owed = 0;
                                //     $loan_payment->waived_penalty = (!is_null($repay_table['repayment_owed']))? $repay_table['repayment_owed'] : 0;
                                //     if($paid_prin == $loan->schedule[$month_idx]->principal && $paid_int == $loan->schedule[$month_idx]->interest){
                                //         $loan_payment->status = 1;
                                //         $loan_payment->condition_id = 0;
                                //     }else{
                                //         $loan_payment->status = (!is_null($repay_table['status']))? $repay_table['status'] : 1;
                                //         $loan_payment->condition_id = (!is_null($repay_table['condition']))? $repay_table['condition'] : 0;
                                //     }
                                // }else{
                                //     $loan_payment->repayment_owed = (!is_null($repay_table['repayment_owed']))? $repay_table['repayment_owed'] : 0;
                                //     $loan_payment->status = (!is_null($repay_table['status']))? $repay_table['status'] : 1;
                                //     $loan_payment->condition_id = (!is_null($repay_table['condition']))? $repay_table['condition'] : 0;
                                // }
                                if ($loan_payment->status == 1) {
                                    $exist_loan_repay = LoanPayments::where('loan_id', $loan_id)->where('payment_month', $month_idx)->get();
                                    $total_p_int = $paid_prin;
                                    $total_p_prin = $paid_int;
                                    // dd($exist_loan_repay);
                                    foreach ($exist_loan_repay as $r) {
                                        $total_p_prin += $r->paid_principal;
                                        $total_p_int += $r->paid_interest;
                                    }
                                    $update = ['status' => 1];
                                    $schedule = RepaymentSchedule::where('loan_id', $loan_id)
                                        // ->where('interest', round($total_p_int,2))
                                        // ->where('principal', round($total_p_prin,2))
                                        ->where('no', $month_idx)
                                        ->update($update);
                                    //->update($update);
                                }

                                $loan_payment->note = Request::input('note');
                                $loan_payment->late_day = (!empty($repay_table['overdue']) ? $repay_table['overdue'] : 0);
                                $loan_payment->user_id = $user_id;
                                // Update old payment status
                                $repayment_owed = LoanPayments::select('id', 'status')
                                    ->where('loan_id', '=', $loan_id)
                                    ->where('status', '=', 0)
                                    ->where('condition_id', '!=', 0)
                                    ->where('payment_month', '=', $loan_payment->payment_month)
                                    ->orderBy('id', 'DESC')
                                    ->first();
                                if (!$loan_payment->save()) {
                                    return redirect()->route('loan_detail', [$loan_id]);
                                }
                                $newPaymentIds[$loan_payment->loan_repayment_type][] = $loan_payment->id;
                                // add notifiction data
                                $this->userActivity(Auth::user()->id, $loan_payment->id, 0, 'Add LoanPayments', Request::fullUrl());
                                if (!empty($repayment_owed)) {
                                    $repayment_owed->status = 2; /* old repayment month status */
                                    if (!$repayment_owed->save()) {
                                        return redirect()->route('loan_detail', [$loan_id]);
                                    }
                                }
                            }
                        }
                        // Record Transaction and Journal
                    }
                    if($journal_down_arr){
                        $postedRepayment = record_journal($loan, $record_date, "Loan Repayment", $data_down, $journal_down_arr, $branch_id, $user_id, 1, 'downpayment');
                        \App\Services\OriginalRepaymentInvoiceLinker::attach($postedRepayment, $newPaymentIds['downpayment'], $loan_id, 'downpayment');
                    }
                    if(sizeof($journal_arr) > 0){
                        $postedRepayment = record_journal($loan, $record_date, "Loan Repayment", $data, $journal_arr, $branch_id, $user_id, 1, 'loan');
                        \App\Services\OriginalRepaymentInvoiceLinker::attach($postedRepayment, $newPaymentIds['loan'], $loan_id, 'loan');
                    }

                    // Update Loan Account Balance
                    $loan_account = $loan->client_loan_account;
                    $loan_account->balance -= $total_paid_prin;
                    $loan_account->balance_downpayment -= $loan_credit_down;
                    // Update Drawdown Acc. Balance
                    if ($coa_dd) {
                        $coa_dd_update->save();
                        $this->userActivity(Auth::user()->id, $coa_dd_update->id, 0, 'Update DrawdownAccounts Balance', Request::fullUrl());
                    }

                    // Till Transaction
                    //$last_transaction = TransactionsRequiry::where('id', $loan_id)->orderBy('id', 'DESC')->first();

                    $is_file = false;
                    if (!is_array($postedRepayment)) throw new \RuntimeException('Repayment journal did not complete.');
                    $transaction = TransactionsRequiry::where('id', $postedRepayment['transaction_id'])->where('loan_id', $loan_id)->firstOrFail();
                    if (Request::hasFile('repayment_receipt')) {
                        if (Request::file('repayment_receipt')->isValid()) {
                            $is_file = true;
                            $file = Request::file('repayment_receipt');
                            $ext = $file->getClientOriginalExtension();
                            if (in_array(strtolower($ext), ['jpg', 'jpeg', 'bmp', 'png'])) {
                                $image = Image::make($file);
                                $photo_name = uniqid(date('dmY')) . '.jpg';
                                $image->save(public_path('data/loans/receipts') . '/' . $photo_name);
                                $transaction->repayment_receipt = $photo_name;
                            } else {
                                $fileName = uniqid(date('dmY')) . '.' . $ext;
                                $file->move(public_path('data/loans/receipts'), $fileName);
                                $transaction->repayment_receipt = $fileName;
                            }
                        }
                    }

                    if ($is_file) {
                        $transaction->is_audit = 1;
                        $transaction->save();
                        $this->userActivity(Auth::user()->id, $transaction->id, 0, 'Update TransactionsRequiry', Request::fullUrl());
                    }
                    $this->Add_LoanRepaymentData_To_notification($transaction->id, Request::input('sel_tellers'), Request::input('currency'));

                    // Record penalty owed
                    $sch_penalty = floatval(Request::input('sch_penalty'));
                    if ($sch_penalty > $total_penalty) {
                        $penalty_record_new = new PenaltyRecord();
                        $penalty_record_new->loan_id = $loan_id;
                        $penalty_record_new->record_date = $record_date;
                        $penalty_record_new->start_date = $last_paid_date;
                        $penalty_record_new->end_date = $repay_date;
                        $penalty_record_new->overdue = floatval(Request::input('overdue'));
                        $penalty_record_new->type = "Collection";
                        $penalty_record_new->paid_penalty = $total_penalty;
                        $penalty_record_new->owed_penalty = $sch_penalty - $total_penalty;
                        $penalty_record_new->inputted_by = Auth::user()->id;
                        $penalty_record_new->save();
                    }
                    if ($total_waive_penalty > 0) {
                        $penalty_record_new = new PenaltyRecord();
                        $penalty_record_new->loan_id = $loan_id;
                        $penalty_record_new->record_date = $record_date;
                        $penalty_record_new->start_date = $last_paid_date;
                        $penalty_record_new->end_date = $repay_date;
                        $penalty_record_new->overdue = floatval(Request::input('overdue'));
                        $penalty_record_new->type = "Waive";
                        $penalty_record_new->paid_penalty = $total_waive_penalty;
                        $penalty_record_new->owed_penalty = 0;
                        $penalty_record_new->inputted_by = Auth::user()->id;
                        $penalty_record_new->save();
                    }
                    $this->userActivity($user_id, $loan_id, 6, 'Make repayment');
                    if (0 == round($loan_account->balance, 2)) {
                        $loan_update = Loan::with(['payment', 'schedule'])->find($loan_id);
                        $pass_due = LoanCalculate::getTotalPenalty($loan_update, $repay_date)[4];
                        if (intval($pass_due * 100) <= 0) {
                            $loan_update->status = 10;
                            $loan_update->settlement_date = $repay_date;
                            $loan_update->save();
                            // Update Loan Account Balance
                            $loan_account->status = 8; //completed
                        }
                    }
                    $loan_account->save();
                    $draft_id = Request::input('draft_id');
                    $draft = LoanPaymentsDraft::find($draft_id);
                    if (!empty($draft)) {
                        $draft->status = 1;
                        $draft->save();
                    }
                    DB::commit();
                    return redirect()->route('loan_detail', [$loan_id])->with("msg","Pay successfully.");
                }
            }
        } catch (\Exception $e) {
            if (DB::connection()->transactionLevel() > $repaymentTransactionLevel) DB::rollback();
            return redirect('/');
        } finally {
            // Close only this method's unfinished transaction on early returns.
            while (DB::connection()->transactionLevel() > $repaymentTransactionLevel) DB::rollback();
        }
    }

    private function Add_LoanRepaymentData_To_notification($max_Transaction_id, $till_account_id, $currency_id)
    {
        if (!empty($till_account_id)) {

            $notification = new App\Models\Notification();
            $till_account = App\Models\Teller::select('id', 'assign_user_id', 'currency_id')->where('id', '=', $till_account_id)->where('currency_id', '=', $currency_id)->first();
            $data = [
                $till_account->assign_user_id,
                11,
                $max_Transaction_id,
                Auth::user()->id,
                $till_account->id,
                'Loan Repayment', //Please don't change or edit this type because it is a comparison variable at notification.js
                date("Y-m-d H:m:s", time()),
                Request::input('note'),
                Request::input('repayment_amount'),
            ];
            return $notification->setNotification($data);
        }
    }


    public function postRepaymentDraft($loan_id, $id = null)
    {
        if ($id && $id != 0) {
            $addNew = LoanPaymentsDraft::find($id);
            $addNew->status = 0;
        } else {
            $addNew = new LoanPaymentsDraft();
        }
        //check if already submit
        $chdraft = LoanPaymentsDraft::where('loan_id', $loan_id)->where('type', 2)->where('status', '!=', 2)->first();
        if ($chdraft) $addNew = LoanPaymentsDraft::find($chdraft->id);

        $addNew->loan_id = $loan_id;
        $addNew->user_id = Auth::user()->id;
        $addNew->disburse_date = Request::input('disburse_date');
        $addNew->amount = LoanCalculate::str2number(Request::input('amount'), '$');
        $addNew->loan_amount = LoanCalculate::str2number(Request::input('loan_amount'), '$');
        $addNew->account_no = Request::input('account_no');
        $addNew->account_name = Request::input('account_name');
        $addNew->contract_id = Request::input('contract_id');
        $addNew->branch_name = Request::input('branch_name');
        $addNew->branch_code = Request::input('branch_code');
        $addNew->ch_waive_panalty = Request::input('ch_waive_panalty');
        $addNew->repayment_amount = Request::input('repayment_amount');
        $addNew->note = Request::input('note');
        $addNew->sch_principal = Request::input('sch_principal');
        $addNew->sch_interest = Request::input('sch_interest');
        $addNew->sch_fee = Request::input('sch_fee');
        $addNew->penalty = Request::input('penalty');
        $addNew->payment_type = Request::input('payment_type');
        $addNew->sel_tellers = Request::input('sel_tellers');
        $addNew->loan_duration = Request::input('loan_duration');
        $addNew->principal_paid = LoanCalculate::str2number(Request::input('principal_paid'), '$');
        $addNew->interest_rate = Request::input('interest_rate');
        $addNew->overdue = Request::input('overdue');
        $addNew->last_paid_date = Request::input('last_paid_date');
        $addNew->next_schedule_date = Request::input('next_schedule_date');
        $addNew->currency_name = Request::input('currency_name');
        $addNew->currency = Request::input('currency');

        $addNew->dpDate = Request::input('dpDate');
        $addNew->ipTenure = Request::input('ipTenure');
        $addNew->ipTenureDate = Request::input('ipTenureDate');
        $addNew->ipInterest = Request::input('ipInterest');
        $addNew->ipNote = Request::input('ipNote');
        $addNew->ipInterestOption = Request::input('ipInterestOption');
        $addNew->invoice_number = Request::input('invoice_number');
        $addNew->ip_ch_waive_panalty = Request::input('ch_waive_panalty');
        $addNew->ipStartDate = Request::input('ipStartDate');
        $addNew->type = Request::input('type') ? 2 : 1;

        $addNew->d_description0 = Request::input('d_description')[0];
        $addNew->d_description1 = Request::input('d_description')[1];
        $addNew->d_description2 = Request::input('d_description')[2];
        $addNew->d_description3 = Request::input('d_description')[3];
        $addNew->d_description4 = Request::input('d_description')[4];
        $addNew->c_description0 = Request::input('c_description')[0];
        $addNew->c_description1 = Request::input('c_description')[1];
        $addNew->c_description2 = Request::input('c_description')[2];
        $addNew->c_description3 = Request::input('c_description')[3];
        $addNew->c_description4 = Request::input('c_description')[4];
        $addNew->description0 = Request::input('description')[0];
        $addNew->description1 = Request::input('description')[1];
        $addNew->description2 = Request::input('description')[2];
        $addNew->description3 = Request::input('description')[3];
        $addNew->description4 = Request::input('description')[4];

        if (Request::hasFile('repayment_receipt')) {
            if (Request::file('repayment_receipt')->isValid()) {
                $file = Request::file('repayment_receipt');
                $ext = $file->getClientOriginalExtension();
                if (in_array(strtolower($ext), ['jpg', 'jpeg', 'bmp', 'png'])) {
                    $image = Image::make($file);
                    $photo_name = uniqid(date('dmY')) . '.jpg';
                    $image->save(public_path('data/loans/receipts') . '/' . $photo_name);
                    $addNew->repayment_receipt = $photo_name;
                } else {
                    $fileName = uniqid(date('dmY')) . '.' . $ext;
                    $file->move(public_path('data/loans/receipts'), $fileName);
                    $addNew->repayment_receipt = $fileName;
                }
            }
        }

        if (Request::has('ch_waive_panalty') && Request::input('ch_waive_panalty') == 1) {
            if (Request::has('waive_panalty_receipt') && Request::file('waive_panalty_receipt')->isValid()) {
                $is_file = true;
                $file = Request::file('waive_panalty_receipt');
                $ext = $file->getClientOriginalExtension();
                if (in_array(strtolower($ext), ['jpg', 'jpeg', 'bmp', 'png'])) {
                    $image = Image::make($file);
                    $photo_name = uniqid(date('dmY')) . '.jpg';
                    $image->save(public_path('data/loans/documents') . '/' . $photo_name);
                    $addNew->waive_panalty_receipt = $photo_name;
                } else {
                    $fileName = uniqid(date('dmY')) . '.' . $ext;
                    $file->move(public_path('data/loans/documents'), $fileName);
                    $addNew->waive_panalty_receipt = $fileName;
                }
            }
        }

        if (!Request::input('ch_waive_panalty')) {
            $addNew->ch_waive_panalty = '';
            $addNew->waive_panalty_receipt = '';
            $addNew->ip_ch_waive_panalty = '';
        }
        $addNew->save();
        $this->userActivity(Auth::user()->id, $addNew->id, 0, 'Draft repayment - ' . $addNew->id);

        return redirect()->route('loan_detail', [$loan_id]);
    }


    function getRepaymentDraft()
    {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 15;
        $drafts = LoanPaymentsDraft::orderBy('id', 'DESC')->with('audit');;
        //$allow_roles = config('static_data.allow_roles');
        //if(!in_array(Auth::user()->role_id, $allow_roles)) $drafts = $drafts->where('user_id', Auth::user()->id);
        $drafts = $drafts->paginate($offset);
        $data['drafts'] = $drafts;
        return $this->view('repay.repayment_draft', $data);
    }

    function getRepaymentDraftReject($id)
    {
        $draft = LoanPaymentsDraft::find($id);
        if ($draft->status > 0) return redirect()->back();
        $draft->status = 2;
        $draft->save();
        $this->userActivity(Auth::user()->id, $id, 0, 'Rejected repayment - ' . $id);
        $this->do_audit($id, '', Auth::user()->id, 'repayment_draft', 2, 'reject repayment');

        return redirect()->route('getRepaymentDraft');
    }

    public function GetAutoPayment()
    {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 100;
        if(!($offset>0)){
            $offset = 100;
        }
        $pagi = isset($_GET['page']) ? ($_GET['page'] - 1) * $offset + 1 : 1;

        $system_date = SystemDate::orderBy('id', 'DESC')->first();
        $autopayment_config = config('static_data.autopayment_config');
        $not_owed_auto_pay = $autopayment_config['not_owed_auto_pay'];
        if ($system_date) {
            if ($system_date->is_accrued_interest == 1 && $system_date->is_auto_payment == 1) {
                $verify_date = $system_date->next_date;
            } else {
                $verify_date = $system_date->corrent_date;
            }
        } else {
            $verify_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        }
        $compare_date = date("Y-m-d H:i:s", strtotime($verify_date . ' +1 day'));
        // $results = DrawdownAccounts::with([
        //     'client' => function($q){
        //         $q->with(['loans' => function($que){
        //             $que->with(['schedule', 'payment', 'transaction', 'client_loan_account']);
        //         }]);
        //     }
        // ])
        $results = DrawdownAccounts::with([
                'client',
                'loans' => function ($que) {
                    $que->with(['payment', 'transaction', 'client_loan_account'])->orderBy('id','ASC');
                }
            ])
            // ->where('balance', '>', '0')
            // ->where('id',11645)
            ->orderBy('updated_at', 'DESC');

        $results = $results->where('created_on', '<', $compare_date)->paginate($offset);
        $sch_repay_arr = [];
        $sch_repay_dow_arr = [];
        $auto_repay_arr = [];
        $dd_bal_arr = [];
        $NPL_flg = [];
        foreach ($results as $d) {
            foreach ($d->loans as $l) {
                if ($d->currency != $l->client_loan_account->currency) continue;
                // ORO loan
                if ($l->status != 3 && $l->status != 8) continue;
                $ORO_flg = (strpos($l->contract_id, 'LC') !== FALSE) ? 1 : 0;
                $loan_status = intval($l->client_loan_account->status);
                
                if ($l->status != 3 && $l->status != 8) continue;
                $penalty_arr = LoanCalculate::getTotalPenalty($l, $verify_date);
                $overdue = $penalty_arr[2];
                // dd($penalty_arr);
                // NPL or not
                // $NPL_flg = ($overdue > 30 || $loan_status > GENERAL_LC_STATUS)? 1 : 0;
                if (count($penalty_arr[0]) == 0) continue;
                // if (round($penalty_arr[4] + $penalty_arr[6] + $penalty_arr[7] + $penalty_arr[8] + $penalty_arr[9], 2) <= 0) continue;
                $NPL_flg[$l->id] = ($overdue > 30) ? 1 : 0;
                // $sch_repay_arr = get_auto_repay_array($l, $verify_date);
                $sch_repay_dow_arr = get_auto_repay_array_downpayment($l, $verify_date);
                $act_interest = $act_principal = $act_penalty = $act_fee = $act_other_fee = 0;
                $new_balance = $d->balance;
                // change Date 29042022
                // $dd_bal_arr[$d->id] = - get_journal_bal($d->coa_id, $verify_date)["balance"];
                // $new_balance = $dd_bal_arr[$d->id];
                if ($new_balance <= 0 || $d->balance <= 0) continue;
                if (round($new_balance, 2) == 0 || empty($sch_repay_dow_arr) || is_null($sch_repay_dow_arr) || count($sch_repay_dow_arr) == 0) continue;

                $new_balance = round($new_balance,2);
                $balance_new = $new_balance;
                $is_continue = 0;
                foreach ($sch_repay_dow_arr as $key => $sch_repay_arr) {
                    $repay_arr = [];
                    foreach ($sch_repay_arr as  $sch) {
                        //if($ORO_flg==1){
                        if (1) {
                            $balance = $new_balance;
                            if ($sch['type'] == 'downpayment') {
                                if ($balance < round($sch['principal'], 2)) {
                                    $is_continue = 1;
                                    continue;
                                }
                            }
                            if ($not_owed_auto_pay == 1) {
                                if($is_continue == 1){
                                    continue;
                                }

                                if ($balance < round(($sch['principal'] + $sch['interest']), 2)) {
                                    continue;
                                }
                            }

                            $act_interest = $act_fee = $act_penalty = $act_principal = 0;
                            // if($NPL_flg[$l->id] == 0){  // PL customer
                            // interest
                            if ($balance > 0) {
                                $act_interest = ($balance > round($sch['interest'], 2)) ? round($sch['interest'], 2) : $balance;
                                $balance -= $act_interest;
                            }
                            // other fee
                            if ($balance > 0) {
                                $act_other_fee = ($balance > round($sch['other_fee'], 2)) ? round($sch['other_fee'], 2) : $balance;
                                $balance -= $act_other_fee;
                            }
                            // fee
                            if ($balance > 0) {
                                $act_fee = ($balance > round($sch['fee'], 2)) ? round($sch['fee'], 2) : $balance;
                                $balance -= $act_fee;
                            }
                            // principal
                            if ($balance > 0) {
                                $act_principal = ($balance > round($sch['principal'], 2)) ? round($sch['principal'], 2) : $balance;
                                $balance -= $act_principal;
                            }
                            // }else{ // NPL customer
                            //     // principal
                            //     if($balance > 0){
                            //         $act_principal = ($balance > round($sch['principal'],2))? round($sch['principal'],2) : $balance;
                            //         $balance -= $act_principal;
                            //     }
                            //     // interest
                            //     $act_interest = ($balance > round($sch['interest'],2))? round($sch['interest'],2) : $balance;
                            //     $balance -= $act_interest;
                            // }


                        } else {
                            $balance = $new_balance;
                            $act_interest = $act_fee = $act_penalty = $act_principal = 0;
                            // interest
                            $act_interest = ($balance > round($sch['interest'], 2)) ? round($sch['interest'], 2) : $balance;
                            $balance -= $act_interest;
                            // fee
                            if ($balance > 0) {
                                $act_fee = ($balance > round($sch['fee'], 2)) ? round($sch['fee'], 2) : $balance;
                                $balance -= $act_fee;
                            }
                            // balloon or not
                            $balloon_flag = 0;
                            if ($l->frequency == "M") {
                                $bal_limit = $l->loan_amount / ($l->loan_duration * 3 / 12);
                                if ($sch["principal"] >= $bal_limit) $balloon_flag = 1;
                            }

                            if ($NPL_flg[$l->id] == 0) {  // PL customer
                                // penalty
                                if ($balance > 0) {
                                    $act_penalty = ($balance > round($sch['penalty'], 2)) ? round($sch['penalty'], 2) : $balance;
                                    $balance -= $act_penalty;
                                }
                                // principal
                                if ($balance > 0) {
                                    $act_principal = ($balance > round($sch['principal'], 2)) ? round($sch['principal'], 2) : $balance;
                                    $balance -= $act_principal;
                                }
                            } else {              // NPL customer
                                // principal
                                if ($balance > 0) {
                                    $act_principal = ($balance > round($sch['principal'], 2)) ? round($sch['principal'], 2) : $balance;
                                    $balance -= $act_principal;
                                }
                                // penalty
                                // if($balance > 0){
                                //     $act_penalty = ($balance > round($sch['penalty'],2))? round($sch['penalty'],2) : $balance;
                                //     $balance -= $act_penalty;
                                // }              
                            }
                        }
                        

                        $sch['act_interest'] = $act_interest;
                        $sch['act_other_fee'] = $act_other_fee;
                        $sch['act_fee'] = $act_fee;
                        $sch['act_penalty'] = $act_penalty;
                        $sch['act_principal'] = $act_principal;
                        $sch['act_total'] = $act_interest + $act_fee + $act_penalty + $act_principal + $act_other_fee;

                        //if($ORO_flg == 1){
                        //    if(round(($act_interest + $act_fee + $act_principal),2) == 0) continue;
                        //}else{
                        if (round($sch['act_total'], 2) == 0) continue;
                        //}
                        $sch['balance'] = $balance;

                        array_push($repay_arr, $sch);

                        $new_balance = $balance;
                        if (round($balance, 2) <= 0) break;

                    }

                    if (empty($repay_arr)) continue;
                    
                    array_push($auto_repay_arr, [
                                                "type" => $key,
                                                "dd_id" => $d->id,
                                                "l_id" => $l->id,
                                                'name' => $d->account_name,
                                                "account_no" => $d->account_no,
                                                // "balance" => $dd_bal_arr[$d->id],
                                                "balance" => $balance_new,
                                                'reference' => $l->contract_id,
                                                'repay' => $repay_arr
                                            ]);
                    $balance_new = $balance_new - round($sch['act_total'], 2);
                }
            }
        }
        $data['result'] = $auto_repay_arr;
        $data['flag'] = Request::input("flag");
        $data['page'] = $_GET['page'];
        $data['date'] = $verify_date;
        // When saving
        // if (Request::input("flag") == 1) {
        //     // $acc_date = Request::input("date");
        //     $acc_date = $verify_date;
        //     self::exe_auto_payment($auto_repay_arr, $acc_date, $NPL_flg, $_GET['page'],$results);
        // }
        $data['verify_date'] = $verify_date;
        return $this->view('repay.auto_repay', $data);
    }

    public function GetAutoPaymentAjax(){
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 100;
        if(!($offset>0)){
            $offset = 100;
        }
        $pagi = isset($_GET['page']) ? ($_GET['page'] - 1) * $offset + 1 : 1;

        $system_date = SystemDate::orderBy('id', 'DESC')->first();
        $autopayment_config = config('static_data.autopayment_config');
        $not_owed_auto_pay = $autopayment_config['not_owed_auto_pay'];
        if ($system_date) {
            if ($system_date->is_accrued_interest == 1 && $system_date->is_auto_payment == 1) {
                $verify_date = $system_date->next_date;
            } else {
                $verify_date = $system_date->corrent_date;
            }
        } else {
            $verify_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        }
        $compare_date = date("Y-m-d H:i:s", strtotime($verify_date . ' +1 day'));
        // $results = DrawdownAccounts::with([
        //     'client' => function($q){
        //         $q->with(['loans' => function($que){
        //             $que->with(['schedule', 'payment', 'transaction', 'client_loan_account']);
        //         }]);
        //     }
        // ])
        $results = DrawdownAccounts::with([
                'client',
                'loans' => function ($que) {
                    $que->with(['payment', 'transaction', 'client_loan_account'])->orderBy('id','ASC');
                }
            ])
            // ->where('balance', '>', '0')
            // ->where('id',11645)
            ->orderBy('updated_at', 'DESC');

        $results = $results->where('created_on', '<', $compare_date)->paginate($offset);
        $sch_repay_arr = [];
        $sch_repay_dow_arr = [];
        $auto_repay_arr = [];
        $dd_bal_arr = [];
        $NPL_flg = [];
        foreach ($results as $d) {
            foreach ($d->loans as $l) {
                if ($d->currency != $l->client_loan_account->currency) continue;
                // ORO loan
                if ($l->status != 3 && $l->status != 8) continue;
                $ORO_flg = (strpos($l->contract_id, 'LC') !== FALSE) ? 1 : 0;
                $loan_status = intval($l->client_loan_account->status);
                
                if ($l->status != 3 && $l->status != 8) continue;
                $penalty_arr = LoanCalculate::getTotalPenalty($l, $verify_date);
                $overdue = $penalty_arr[2];
                // NPL or not
                // $NPL_flg = ($overdue > 30 || $loan_status > GENERAL_LC_STATUS)? 1 : 0;
                if (count($penalty_arr[0]) == 0) continue;
                // if (round($penalty_arr[4] + $penalty_arr[6] + $penalty_arr[7] + $penalty_arr[8] + $penalty_arr[9], 2) <= 0) continue;
                $NPL_flg[$l->id] = ($overdue > 30) ? 1 : 0;
                // $sch_repay_arr = get_auto_repay_array($l, $verify_date);
                $sch_repay_dow_arr = get_auto_repay_array_downpayment($l, $verify_date);

                $act_interest = $act_principal = $act_penalty = $act_fee = $act_other_fee = 0;
                $new_balance = $d->balance;
                // change Date 29042022
                // $dd_bal_arr[$d->id] = - get_journal_bal($d->coa_id, $verify_date)["balance"];
                // $new_balance = $dd_bal_arr[$d->id];

                if ($new_balance <= 0 || $d->balance <= 0) continue;
                if (round($new_balance, 2) == 0 || empty($sch_repay_dow_arr) || is_null($sch_repay_dow_arr) || count($sch_repay_dow_arr) == 0) continue;

                $new_balance = round($new_balance,2);
                $balance_new = $new_balance;
                $is_continue = 0;
                foreach ($sch_repay_dow_arr as $key => $sch_repay_arr) {
                    $repay_arr = [];
                    foreach ($sch_repay_arr as  $sch) {
                        //if($ORO_flg==1){
                        if (1) {
                            $balance = $new_balance;
                            if ($sch['type'] == 'downpayment') {
                                if ($balance < round($sch['principal'], 2)) {
                                    $is_continue = 1;
                                    continue;
                                }
                            }
                            if ($not_owed_auto_pay == 1) {
                                if($is_continue == 1){
                                    continue;
                                }

                                if ($balance < round(($sch['principal'] + $sch['interest']), 2)) {
                                    continue;
                                }
                            }

                            $act_interest = $act_fee = $act_penalty = $act_principal = 0;
                            // if($NPL_flg[$l->id] == 0){  // PL customer
                            // interest
                            if ($balance > 0) {
                                $act_interest = ($balance > round($sch['interest'], 2)) ? round($sch['interest'], 2) : $balance;
                                $balance -= $act_interest;
                            }
                            // other fee
                            if ($balance > 0) {
                                $act_other_fee = ($balance > round($sch['other_fee'], 2)) ? round($sch['other_fee'], 2) : $balance;
                                $balance -= $act_other_fee;
                            }
                            // fee
                            if ($balance > 0) {
                                $act_fee = ($balance > round($sch['fee'], 2)) ? round($sch['fee'], 2) : $balance;
                                $balance -= $act_fee;
                            }
                            // principal
                            if ($balance > 0) {
                                $act_principal = ($balance > round($sch['principal'], 2)) ? round($sch['principal'], 2) : $balance;
                                $balance -= $act_principal;
                            }
                            // }else{ // NPL customer
                            //     // principal
                            //     if($balance > 0){
                            //         $act_principal = ($balance > round($sch['principal'],2))? round($sch['principal'],2) : $balance;
                            //         $balance -= $act_principal;
                            //     }
                            //     // interest
                            //     $act_interest = ($balance > round($sch['interest'],2))? round($sch['interest'],2) : $balance;
                            //     $balance -= $act_interest;
                            // }


                        } else {
                            $balance = $new_balance;
                            $act_interest = $act_fee = $act_penalty = $act_principal = 0;
                            // interest
                            $act_interest = ($balance > round($sch['interest'], 2)) ? round($sch['interest'], 2) : $balance;
                            $balance -= $act_interest;
                            // fee
                            if ($balance > 0) {
                                $act_fee = ($balance > round($sch['fee'], 2)) ? round($sch['fee'], 2) : $balance;
                                $balance -= $act_fee;
                            }
                            // balloon or not
                            $balloon_flag = 0;
                            if ($l->frequency == "M") {
                                $bal_limit = $l->loan_amount / ($l->loan_duration * 3 / 12);
                                if ($sch["principal"] >= $bal_limit) $balloon_flag = 1;
                            }

                            if ($NPL_flg[$l->id] == 0) {  // PL customer
                                // penalty
                                if ($balance > 0) {
                                    $act_penalty = ($balance > round($sch['penalty'], 2)) ? round($sch['penalty'], 2) : $balance;
                                    $balance -= $act_penalty;
                                }
                                // principal
                                if ($balance > 0) {
                                    $act_principal = ($balance > round($sch['principal'], 2)) ? round($sch['principal'], 2) : $balance;
                                    $balance -= $act_principal;
                                }
                            } else {              // NPL customer
                                // principal
                                if ($balance > 0) {
                                    $act_principal = ($balance > round($sch['principal'], 2)) ? round($sch['principal'], 2) : $balance;
                                    $balance -= $act_principal;
                                }
                                // penalty
                                // if($balance > 0){
                                //     $act_penalty = ($balance > round($sch['penalty'],2))? round($sch['penalty'],2) : $balance;
                                //     $balance -= $act_penalty;
                                // }              
                            }
                        }
                        

                        $sch['act_interest'] = $act_interest;
                        $sch['act_other_fee'] = $act_other_fee;
                        $sch['act_fee'] = $act_fee;
                        $sch['act_penalty'] = $act_penalty;
                        $sch['act_principal'] = $act_principal;
                        $sch['act_total'] = $act_interest + $act_fee + $act_penalty + $act_principal + $act_other_fee;

                        //if($ORO_flg == 1){
                        //    if(round(($act_interest + $act_fee + $act_principal),2) == 0) continue;
                        //}else{
                        if (round($sch['act_total'], 2) == 0) continue;
                        //}
                        $sch['balance'] = $balance;

                        array_push($repay_arr, $sch);

                        $new_balance = $balance;
                        if (round($balance, 2) <= 0) break;

                    }

                    if (empty($repay_arr)) continue;
                    
                    array_push($auto_repay_arr, [
                                                "type" => $key,
                                                "dd_id" => $d->id,
                                                "l_id" => $l->id,
                                                'name' => $d->account_name,
                                                "account_no" => $d->account_no,
                                                // "balance" => $dd_bal_arr[$d->id],
                                                "balance" => $balance_new,
                                                'reference' => $l->contract_id,
                                                'repay' => $repay_arr
                                            ]);
                    $balance_new = $balance_new - round($sch['act_total'], 2);
                }
            }
        }
        if (Request::input("flag") == 1) {
            $acc_date = $verify_date;
            self::exe_auto_payment($auto_repay_arr, $acc_date, $NPL_flg, $_GET['page'],$results);
        }
    }

    public function exe_auto_payment($auto_repay_arr = null, $date = null, $NPL_flg = null, $page = 1,$results = array())
    {

        DB::beginTransaction();
        try {
        $system_date = SystemDate::orderBy('id', 'DESC')->first();
        if ($system_date) {
            if ($system_date->is_accrued_interest == 1 && $system_date->is_auto_payment == 1) {
                $date = $system_date->next_date;
            } else {
                $date = $system_date->corrent_date;
            }
        } else {
            $date = $date;
        }
        $repay_date = date('Y-m-d', strtotime($date));
        //$repayment_table = get_repayment_table($loan, $last_paid_date, $total_paid_prin, $total_paid_int, $total_penalty, $owed_penalty, $total_paid_fee, $repay_date, "repay");
        $SystemDate = SystemDate::where('corrent_date', $date)->first();
        if ($SystemDate) {
            // $SystemDate->is_auto_payment = 1;
            $SystemDate->auto_payment_by = Auth::user()->username;
            $SystemDate->auto_payment_date = date('Y-m-d H:i:s');
            $SystemDate->save();
        } else {
            $insert_date = new SystemDate;
            $insert_date->previous_date = date('Y-m-d', strtotime($repay_date . '-1 day'));
            $insert_date->corrent_date = date('Y-m-d', strtotime($repay_date));
            $insert_date->next_date = date('Y-m-d', strtotime($repay_date . '+1 day'));
            $insert_date->is_auto_payment = 1;
            $insert_date->auto_payment_by = Auth::user()->username;
            $insert_date->auto_payment_date = date('Y-m-d H:i:s');
            $insert_date->save();
        }
            // Record repayment
            
            $total_paid = 0;
            $test = [];
            $loan_id_arr = [];
            if (!empty($auto_repay_arr) && count($auto_repay_arr) > 0) {
                //dd($auto_repay_arr);
                foreach ($auto_repay_arr as $key => $ar) {
                    $newPaymentIds = array('loan'=>array(), 'downpayment'=>array());
                    $postedRepayment = null;
                    $loan_id_arr[] = $ar["l_id"];
                    $t_principal = $t_interest = $t_fee = $t_other_fee = $t_penalty = $t_total = 0;
                    $note = "Auto Loan Repayment-" . $ar["name"] . '-' . $ar["reference"];
                    $loan_id = $ar["l_id"];
                    // Drawdown account
                    $dd_acc = DrawdownAccounts::where('id', $ar["dd_id"])->first();
                    $dd_bal = floatval($dd_acc->balance);
                    if (floatval($dd_bal) <= 0.0) continue;
                    foreach ($ar["repay"] as $repay) {
                        $month_idx = $repay["month_idx"];
                        $paid_prin = $repay["act_principal"];
                        $paid_int = $repay["act_interest"];
                        $paid_other_fee = $repay["act_other_fee"];
                        $paid_fee = $repay["act_fee"];
                        $paid_penalty = $repay["act_penalty"];
                        // judge balance
                        if (round($dd_bal - floatval($repay["act_total"]), 2) < 0) continue;
                        $dd_bal = round($dd_bal - floatval($repay["act_total"]), 2);
                        $t_principal += $paid_prin;
                        $t_interest += $paid_int;
                        $t_fee += $paid_fee;
                        $t_other_fee += $paid_other_fee;
                        $t_penalty += $paid_penalty;
                        $t_total += $repay["act_total"];
                        $repayment_owed = round($repay["total"], 2) - round($repay["act_total"], 2);
                        $loan_payment = new LoanPayments();
                        $loan_payment->loan_id = $loan_id;
                        $loan_payment->invoice_number = '00000000';
                        $loan_payment->repayment_date = $repay_date;
                        $loan_payment->payment_month = $month_idx;
                        $loan_payment->paid_principal = $paid_prin;
                        $loan_payment->paid_interest = $paid_int;
                        $loan_payment->paid_fee = $paid_fee;
                        $loan_payment->paid_other_fee = $paid_other_fee;
                        $loan_payment->penalty_amount = $paid_penalty;
                        $loan_payment->payment_type = 0;
                        $loan_payment->loan_repayment_type = $repay['type'];
                        $loan_payment->repayment_owed = $repayment_owed;
                        // owed or not
                        // 'loan_payment_status' => [
                        //     0 => 'Owed',
                        //     1 => 'Completed',
                        //     2 => 'Paid Owed'
                        // ],
                        // 'loan_payment_condition' => [
                        //     1 => 'Pay next time',
                        //     2 => 'Pay with next payment date',//pay for next load payment period
                        if ($repayment_owed > 0) {// owed
                            $loan_payment->status = 0;
                            $loan_payment->condition_id = 1;
                        } else {
                            $loan_payment->status = 1;
                            $loan_payment->condition_id = 0;
                            $update = ['status' => 1];
                            $schedule = RepaymentSchedule::where('loan_id', $loan_id)
                                ->where('no', $month_idx)
                                ->update($update);
                        }
                        $loan_payment->note = $note;
                        $loan_payment->late_day = date_dif($repay["schedule_date"], $date, 1, false);
                        //$loan_payment->user_id = $user_id;
                        // Update old payment status
                        $repayment_owed = LoanPayments::select('id', 'status')
                            ->where('loan_id', '=', $loan_id)
                            ->where('status', '=', 0)
                            ->where('condition_id', '!=', 0)
                            ->where('payment_month', '=', $loan_payment->payment_month)
                            ->orderBy('id', 'DESC')
                            ->first();
                        if (!$loan_payment->save()) {
                            return redirect()->back();;
                        }
                        $newPaymentIds[$loan_payment->loan_repayment_type][] = $loan_payment->id;
                        if (!empty($repayment_owed)) {
                            $repayment_owed->status = 2; // old repayment month status
                            if (!$repayment_owed->save()) {
                                return redirect()->back();;
                            }
                        }

                    }
                    if ($t_total <= 0) continue;

                    // Update Drawdown
                    $dd_acc->balance = round($dd_bal, 2);
                    $dd_acc->save();
                    // Record Transaction and Journal
                    $journal_arr = [];
                    $loan = Loan::with('client_loan_account')->where('id', $loan_id)->first();
                    $fee_coa_id = CoaCategory::where('name', '=', 'Inc-Fees on Loans Maintenance')
                        ->where('currency', '=', $loan->client_loan_account->currency)->first()->id;
                    $other_fee_coa_id = CoaCategory::where('name', 'like', '%Accounts Payable-Other%')
                        ->where('currency', '=', $loan->client_loan_account->currency)->first()->id;
                    $penalty_coa_id = CoaCategory::where('name', '=', 'Inc-Fine and Penalty on Repayment of Loans and leasing')
                        ->where('currency', '=', $loan->client_loan_account->currency)->first()->id;

                    // interest
                    if ($t_interest > 0) {
                        $desc = "Interest Repayment-" . $ar["name"] . '-' . $ar["reference"];
                        if ($loan->client_loan_account->status <= GENERAL_LC_STATUS) {
                            array_push($journal_arr,
                                [$dd_acc->coa_id, $t_interest, $desc,
                                    $loan->client_loan_account->air_id, $t_interest, $desc,
                                    $note]);
                        } else {
                            array_push($journal_arr,
                                [$dd_acc->coa_id, $t_interest, $desc,
                                    $loan->client_loan_account->air_id, $t_interest, $desc,
                                    $note]);
                            array_push($journal_arr,
                                [$loan->client_loan_account->sus_id, $t_interest, $desc,
                                    $loan->client_loan_account->int_inc_id, $t_interest, $desc,
                                    $note]);
                        }
                    }
                    // fee
                    if ($t_fee > 0) {
                        $desc = "Fee Repayment-" . $ar["name"] . '-' . $ar["reference"];
                        array_push($journal_arr,
                            [$dd_acc->coa_id, $t_fee, $desc,
                                $fee_coa_id, $t_fee, $desc,
                                $note]);
                    }
                    // other fee
                    if ($t_other_fee > 0) {
                        $desc = "Other Fee Repayment-" . $ar["name"] . '-' . $ar["reference"];
                        array_push($journal_arr,
                            [$dd_acc->coa_id, $t_other_fee, $desc,
                                $other_fee_coa_id, $t_other_fee, $desc,
                                $note]);
                    }
                    // penalty
                    if ($t_penalty > 0) {
                        $desc = "Penalty Repayment-" . $ar["name"] . '-' . $ar["reference"];
                        array_push($journal_arr,
                            [$dd_acc->coa_id, $t_penalty, $desc,
                                $penalty_coa_id, $t_penalty, $desc,
                                $note]);
                    }
                    // principal
                    if ($t_principal > 0) {
                        $desc = "Principal Repayment-" . $ar["name"] . '-' . $ar["reference"];
                        array_push($journal_arr,
                            [$dd_acc->coa_id, $t_principal, $desc,
                                $loan->client_loan_account->coa_id, $t_principal, $desc,
                                $note]);
                        if (!is_null($NPL_flg[$loan_id]) && $NPL_flg[$loan_id] == 1) { // special provision
                            // reverse provision
                            $prov_arr_rev = $prov_arr = [];
                            $prov_arr_rev = get_journal_provision($loan->client_loan_account, $loan->client_loan_account->balance, $loan->client_loan_account->status, GENERAL_LC_STATUS, "up");
                            if ($prov_arr_rev[0] != []) {
                                array_push($journal_arr, $prov_arr_rev[0]);
                            }

                            // do provision
                            $prov_arr = get_journal_provision($loan->client_loan_account, $loan->client_loan_account->balance - $t_principal, GENERAL_LC_STATUS, $loan->client_loan_account->status, "down");
                            if ($prov_arr[0] != []) {
                                array_push($journal_arr, $prov_arr[0]);
                            }
                        } else {// general provision
                            // reverse provision
                            $prov_arr_rev = $prov_arr = [];
                            $prov_arr_rev = get_journal_provision($loan->client_loan_account, $loan->client_loan_account->balance, $loan->client_loan_account->status, GENERAL_LC_STATUS, "up");
                            if ($prov_arr_rev[0] != []) {
                                array_push($journal_arr, $prov_arr_rev[0]);
                            }
                            // do provision
                            $prov_arr = get_journal_provision($loan->client_loan_account, $loan->client_loan_account->balance - $t_principal, GENERAL_LC_STATUS, $loan->client_loan_account->status, "down");
                            if ($prov_arr[0] != []) {
                                array_push($journal_arr, $prov_arr[0]);
                            }
                        }
                    }
                    //print_r($journal_arr);

                    $repay_data = [];
                    array_push($repay_data, ["act_principal" => $t_principal, "act_interest" => $t_interest,
                        "act_fee" => $t_fee, "act_other_fee" => $t_other_fee, "act_penalty" => $t_penalty, "act_total" => $t_total, "note" => $note]);
                    $repay_data = $repay_data[0];

                    $postedRepayment = record_journal($loan, $date, "Auto Loan Repayment", $repay_data, $journal_arr, $loan->company_branch_id, 0, $loan_payment->invoice_number,$ar['type']);
                    foreach ($newPaymentIds as $paymentType=>$paymentIds) {
                        \App\Services\OriginalRepaymentInvoiceLinker::attach($postedRepayment, $paymentIds, $loan_id, $paymentType);
                    }
                    // record_journal($loan, $date, "Auto Loan Repayment", $repay_data, $journal_arr, Auth::user()->branch_id, 0, $loan_payment->invoice_number,$ar['type']);

                    if (!is_array($postedRepayment)) throw new \RuntimeException('Repayment journal did not complete.');
                    $transaction = TransactionsRequiry::where('id', $postedRepayment['transaction_id'])->where('loan_id', $loan_id)->firstOrFail();
                    $transaction->is_audit = 1;
                    $transaction->save();

                    // Update Loan Account Balance
                    $loan_account = $loan->client_loan_account;
                    if($ar['type'] == 'downpayment'){
                        $loan_account->balance_downpayment = $transaction->balance_downpayment;
                    }else{
                        $loan_account->balance = $transaction->balance;
                    }

                    if (0 == intval(($transaction->balance) * 100)) {
                        $loan_update = Loan::with(['payment', 'schedule'])->find($loan_id);
                        $pass_due = LoanCalculate::getTotalPenalty($loan_update, $repay_date)[4];
                        if (intval($pass_due * 100) <= 0) {
                            /* Disable Auto Completed 19-06-2023 Noch Fix error loan completed (tmp fixed)
                            $loan_update->status = 10;
                            $loan_update->settlement_date = $repay_date;
                            $loan_update->save();
                            // Update Loan Account Balance
                            if (round($loan_account->balance, 2) == 0) $loan_account->status = 8; //completed

                            End */
                        }
                    }
                    $loan_account->save();
                    //dd($dd_acc);

                    array_push($test, $ar);
                }
                Log::useDailyFiles(storage_path().'/logs/auto_payment/auto_payment');
                $dataToLog = [
                        'info'  => [
                                'username'  => Auth::user()->username,
                                'datetime'  => date('Y-m-d H:i:s'),
                                    ],
                        'data'   => $loan_id_arr,
                        ];
                Log::info(json_encode($dataToLog));

                //  $this->userActivity(0, $loan_id, 6, 'Make repayment');
                // return redirect()->route('auto_payment');
            }
            DB::commit();
            if($results->count() > 0){
                $url = asset('loans/auto_payment?flag=1&page='.($page+1).'&date='.(date('Y-m-d',strtotime($date))).'');
                $datas['success'] = 1;
                $datas['page'] = $page + 1;
                $datas['url'] = $url;
                // dd($datas);
                echo json_encode($datas);
            }else{
                $url = '';
                $datas['success'] = 0;
                $datas['page'] = $page;
                $datas['url'] = $url;
                echo json_encode($datas);
            }
        } catch (\Exception $e) {
            $failedColumn = '';
            if ($e instanceof \Illuminate\Database\QueryException && isset($e->errorInfo[2]) && preg_match("/Column '([A-Za-z0-9_]+)' cannot be null/", $e->errorInfo[2], $columnMatch)) $failedColumn = $columnMatch[1];
            \Log::error('Automatic repayment rollback: '.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine().($e instanceof \Illuminate\Database\QueryException?' column='.$failedColumn.' driver='.(isset($e->errorInfo[1])?$e->errorInfo[1]:'unknown').' query='.$e->getSql():''));
            DB::rollback();
            // return redirect()->back();
        }
    }

    public function postBCashLoanPenaltyFee($amount=0,$description=null,$drawdown_acc=null,$project_id=null,$unit_type_id=0,$unit_id=0,$drawDownAct_id=0)
    {
        DB::beginTransaction();
        try {

            
            $user_id = Auth::user()->id;
            $branch_id = Auth::user()->branch_id;
            $B0 = new Loan();
            $loanData = $B0->selectRaw('
                    tb_loans.id,
                    tb_loans.contract_id,
                    tb_loans.company_branch_id,
                    tb_loans.start_date,
                    tb_loans.loan_type,
                    tb_loans.penalty_rate_type,
                    tb_loans.loan_amount,
                    tb_loans.original_amount,
                    tb_loans.interest_rate,
                    tb_loans.loan_account_id,
                    tb_loans.drawdown_acc,
                    tb_loans.loan_penalty_type,
                    tb_loans.penalty_rate1,
                    tb_loans.penalty_rate2,
                    tb_loans.penalty_period1,
                    tb_loans.penalty_period2,
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
            $loanData = $loanData->where('workflow_status', 'approve')->whereIn('loans.status', array(3,8));
            if($drawdown_acc){
                // 'loans.', not 'tb_loans.'. The prefix is on the connection, so Laravel wraps this
                // as tb_tb_loans.drawdown_acc and the query throws -- the sibling lines below all say
                // 'loans.'. It never showed because the caller passed a column that does not exist,
                // leaving $drawdown_acc null and this filter skipped.
                $loanData = $loanData->where('loans.drawdown_acc', '=', $drawdown_acc);
            }
            if($project_id){
                $loanData = $loanData->where('loans.project_id', '=', $project_id);
            }
            if($unit_type_id){
                $loanData = $loanData->where('loans.unit_type_id', '=', $unit_type_id);
            }
            if($unit_id){
                $loanData = $loanData->where('loans.unit_id', '=', $unit_id);
            }

            $loanData = $loanData->get();
            $dpDate = date('Y-m-d');
            $data['search_results'] =  $loanData->first();
            if (!$data['search_results']) throw new \RuntimeException('Penalty deposit has no eligible active loan.');
            $loan_id = $data['search_results']->id;
            $dpDate = date('Y-m-d');

                $user_id = Auth::user()->id;
                $branch_id = Auth::user()->branch_id;
                $loan = Loan::with('client_loan_account')
                                ->with('schedule')->with('payment')
                                ->with(['penalty_record' => function ($q) {
                                        $q->select('id', 'loan_id', 'record_date', 'owed_penalty')->orderBy('id', 'DESC');
                                    }])
                                ->where('id', $loan_id)->first();

                $coa_dd = DrawdownAccounts::select('id', 'balance', 'coa_id', 'client_id')
                                            ->with('coa')
                                            // ->where('client_id', '=', $loan->client_id)
                                            // ->where('account_no', $loan->drawdown_acc)
                                            ->where('id', $drawDownAct_id)                                            
                                            ->first();

                $loan_account = ClientLoanAccounts::find($data['search_results']->loan_account_id);

                $coa_pnt = CoaCategory::select('id', 'account_code', 'name')->where('type', 6)->where('currency', $loan_account->currency)->where('name', 'Inc-Fine and Penalty on Repayment of Loans and leasing')->first();

                if (!$coa_dd || !$loan_account || !$coa_pnt) throw new \RuntimeException('Penalty repayment account configuration is incomplete.');
                if (floatval($coa_dd->balance) - floatval($amount) < 0) {
                    throw new \RuntimeException('Balance in Drawdown Account is insufficient.');
                }

                $coa_dd_update = $coa_dd;
                if ($coa_dd) {
                    $coa_dd_update->balance - floatval($amount);
                }
                if (!empty($loan)) {

                    $journal_arr = [];
                    $journal_down_arr = [];
                    $debit_arr = [];
                    $debit_down_arr = [];
                    $credit_arr = [];
                    $credit_down_arr = [];
                    $desc_arr = [];
                    $desc_down_arr = [];

                    $data['coa_dd'] = $coa_dd->coa;

                    array_push($journal_arr, [
                        $data['coa_dd']->id,
                        floatval($amount),
                        "Penalty Repayment - ". $loan_account->account_name . " - ". $data['search_results']->contract_id,
                        $coa_pnt->id,
                        floatval($amount),
                        "Penalty Repayment - ". $loan_account->account_name . " - ". $data['search_results']->contract_id,
                         ""
                     ]);
                    $data_down = [];
                    $loan_credit_down = 0;
                    $principal_donw_loan = 0;
                    $total_paid_prin = 0;

                    $total_paid_int = 0;
                    $total_paid_fee = 0;
                    $total_paid_other_fee = 0;
                    $total_penalty = floatval($amount);
                    $dpDate = date('Y-m-d');
                    $penalty_arr = LoanCalculate::getTotalPenalty($data['search_results'], $dpDate);
                    $data["overdue"] = $penalty_arr[2];
                    $data["last_pay_date"] = $penalty_arr[3];
                    $data["next_sch_date"] = $penalty_arr[5];

                    $last_paid_date = $data["last_pay_date"];
                    $pen_waive_flg = 0;

                    // Find owed_penalty
                    $owed_penalty = 0.0;
                    $penalty_record = ($loan->penalty_record);
                    foreach ($penalty_record as $rec) {
                        if ($last_paid_date == date('Y-M-d', strtotime($rec->record_date))) {
                            $owed_penalty = $rec->owed_penalty;
                            break;
                        }
                    }
                    // Record repayment
                    $repay_date = date('Y-m-d H:i:s');
                    // $repay_actual_arr = [];
                    $repay_actual_loantype_arr = [];
                    $amount = round(floatval($amount), 2);

                    $repay_actual_loantype_arr = get_manual_payment_actual($loan, $repay_date, $total_paid_int, $total_paid_fee, $principal_donw_loan, $total_penalty, $pen_waive_flg, $total_paid_other_fee);

                    $record_date = date('Y-m-d H:i:s');
                    $newPaymentIds = array('loan'=>array(), 'downpayment'=>array());
                    $postedRepayment = null;
                    $total_paid = 0;
                    $total_waive_penalty = 0;
                    if (!empty($repay_actual_loantype_arr) && count($repay_actual_loantype_arr) > 0) {
                        foreach ($repay_actual_loantype_arr as $key => $repay_actual_arr) {
                            foreach ($repay_actual_arr as $repay_table) {

                                $paid_prin = $repay_table['act_principal'];
                                $paid_int = $repay_table['act_interest'];
                                $paid_fee = $repay_table['act_fee'];
                                $paid_other_fee = $repay_table['act_other_fee'];
                                $paid_penalty = $repay_table['act_penalty'];
                                $total_paid += $repay_table['act_total'];

                                $loan_payment = new LoanPayments();
                                $loan_payment->loan_id = $loan_id;
                                $loan_payment->invoice_number = '00000000';
                                $loan_payment->repayment_date = $repay_date;
                                $loan_payment->payment_month = $repay_table['month_idx'];
                                $loan_payment->paid_principal = $paid_prin;
                                $loan_payment->paid_interest = $paid_int;
                                $loan_payment->paid_fee = $paid_fee;
                                $loan_payment->loan_repayment_type = $repay_table['type'];
                                $loan_payment->paid_other_fee = $paid_other_fee;
                                $loan_payment->penalty_amount = $paid_penalty;
                                $loan_payment->payment_type = 0;//Request::input('payment_type');
                                $loan_payment->status = $repay_table['status'];
                                $loan_payment->condition_id = $repay_table['condition'];
                                $loan_payment->repayment_owed = $repay_table['repayment_owed'];
                                $loan_payment->waived_penalty = $repay_table['act_waive_penalty'];
                                $total_waive_penalty += $repay_table['act_waive_penalty'];
                                $month_idx = $repay_table['month_idx'];


                                if ($loan_payment->status == 1) {
                                    $exist_loan_repay = LoanPayments::where('loan_id', $loan_id)->where('payment_month', $month_idx)->get();
                                    $total_p_int = $paid_prin;
                                    $total_p_prin = $paid_int;
     
                                    foreach ($exist_loan_repay as $r) {
                                        $total_p_prin += $r->paid_principal;
                                        $total_p_int += $r->paid_interest;
                                    }
                                    $update = ['status' => 1];
                                    $schedule = RepaymentSchedule::where('loan_id', $loan_id)
                                        ->where('no', $month_idx)
                                        ->update($update);
                                }

                                $loan_payment->note = $description;
                                $loan_payment->late_day = (!empty($repay_table['overdue']) ? $repay_table['overdue'] : 0);
                                $loan_payment->user_id = $user_id;
                                // Update old payment status
                                $repayment_owed = LoanPayments::select('id', 'status')
                                    ->where('loan_id', '=', $loan_id)
                                    ->where('status', '=', 0)
                                    ->where('condition_id', '!=', 0)
                                    ->where('payment_month', '=', $loan_payment->payment_month)
                                    ->orderBy('id', 'DESC')
                                    ->first();
                                if (!$loan_payment->save()) {
                                    throw new \RuntimeException('Penalty repayment could not be saved.');
                                }
                                $newPaymentIds[$loan_payment->loan_repayment_type][] = $loan_payment->id;
                                // add notifiction data
                                $this->userActivity(Auth::user()->id, $loan_payment->id, 0, 'Add LoanPayments', Request::fullUrl());
                                if (!empty($repayment_owed)) {
                                    $repayment_owed->status = 2; /* old repayment month status */
                                    if (!$repayment_owed->save()) {
                                        throw new \RuntimeException('Penalty repayment could not be saved.');
                                    }
                                }
                            }
                        }
                        // Record Transaction and Journal
                    }
                    if (abs(floatval($total_paid)-floatval($amount)) > 0.005) throw new \RuntimeException('Penalty payment could not be fully allocated.');
                    if($journal_down_arr){
                        $postedRepayment = record_journal($loan, $record_date, "Loan Repayment", $data_down, $journal_down_arr, $branch_id, $user_id, 1, 'downpayment');
                        \App\Services\OriginalRepaymentInvoiceLinker::attach($postedRepayment, $newPaymentIds['downpayment'], $loan_id, 'downpayment');
                    }
                    if(sizeof($journal_arr) > 0){
                        $data['act_interest']=0;
                        $data['act_principal']=0;
                        $data['act_fee']=0;
                        $data['act_other_fee']=0;
                        $data['act_penalty']=floatval($amount);
                        $data['act_total'] = floatval($amount);
                        $data['note']=$description;

                        $postedRepayment = record_journal($loan, $record_date, "Loan Repayment", $data, $journal_arr, $branch_id, $user_id, 1, 'loan');
                        \App\Services\OriginalRepaymentInvoiceLinker::attach($postedRepayment, $newPaymentIds['loan'], $loan_id, 'loan');
                    }

                    // Update Loan Account Balance
                    $loan_account = $loan->client_loan_account;
                    $loan_account->balance -= $total_paid_prin;
                    $loan_account->balance_downpayment -= $loan_credit_down;
                    // Update Drawdown Acc. Balance
                    if ($coa_dd) {
                        // $coa_dd_update->save();
                        // $this->userActivity(Auth::user()->id, $coa_dd_update->id, 0, 'Update DrawdownAccounts Balance', Request::fullUrl());
                    }

                    // Till Transaction
                    //$last_transaction = TransactionsRequiry::where('id', $loan_id)->orderBy('id', 'DESC')->first();

                    $is_file = false;
                    if (!is_array($postedRepayment)) throw new \RuntimeException('Repayment journal did not complete.');
                    $transaction = TransactionsRequiry::where('id', $postedRepayment['transaction_id'])->where('loan_id', $loan_id)->firstOrFail();

                    $this->Add_LoanRepaymentData_To_notification($transaction->id, Request::input('sel_tellers'), Request::input('currency'));

                    // Record penalty owed
                    $sch_penalty = floatval(Request::input('sch_penalty'));
                    if ($sch_penalty > $total_penalty) {
                        $penalty_record_new = new PenaltyRecord();
                        $penalty_record_new->loan_id = $loan_id;
                        $penalty_record_new->record_date = $record_date;
                        $penalty_record_new->start_date = $last_paid_date;
                        $penalty_record_new->end_date = $repay_date;
                        $penalty_record_new->overdue = floatval($data["overdue"]);
                        $penalty_record_new->type = "Collection";
                        $penalty_record_new->paid_penalty = $total_penalty;
                        $penalty_record_new->owed_penalty = $sch_penalty - $total_penalty;
                        $penalty_record_new->inputted_by = Auth::user()->id;
                        $penalty_record_new->save();
                    }
                    if ($total_waive_penalty > 0) {
                        $penalty_record_new = new PenaltyRecord();
                        $penalty_record_new->loan_id = $loan_id;
                        $penalty_record_new->record_date = $record_date;
                        $penalty_record_new->start_date = $last_paid_date;
                        $penalty_record_new->end_date = $repay_date;
                        $penalty_record_new->overdue = floatval($data["overdue"]);
                        $penalty_record_new->type = "Waive";
                        $penalty_record_new->paid_penalty = $total_waive_penalty;
                        $penalty_record_new->owed_penalty = 0;
                        $penalty_record_new->inputted_by = Auth::user()->id;
                        $penalty_record_new->save();
                    }
                    $this->userActivity($user_id, $loan_id, 6, 'Make repayment');




                    DB::commit();
                    return true;

                }
            
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

    }
}
