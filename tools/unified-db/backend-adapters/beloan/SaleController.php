<?php 
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Auth\Guard;
use League\Flysystem\Exception;
use App\Models\Loan;
use Illuminate\Support\Collection;
use App\Models\Client as Clients;
use App\Models\Client;
use App\Models\Currency;
use App\Models\PaymentTerm;
use App\Models\CoaCategory;
use App\Models\CompanyBranch;
use App\Models\ClientLoanAccounts;
use App\Models\CommissionRate;
use App\Models\CommissionWithdrawalTransaction;
use App\Models\CommissionWithdrawal;
use App\Models\SaleCommissionSetting;
use App\Models\Project;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ChangeUnit;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Models\SalePerson;
use App\Models\ImageType;
use App\Models\SystemDate;
use App\Models\DrawdownAccounts;
use Illuminate\Support\Facades\Input;
use Maatwebsite\Excel\Facades\Excel;
use Request;
use Auth;
use Image;
use File;
use DB;
use App;

class SaleController extends Controller {

	public function __construct() {
        $this->middleware('xss');
        $this->middleware('auth');
    }

    public function create() {
        $parents = CoaCategory::where(function($q){
            $q->where('name','like','Stand-L%')->orWhere('name','like','Financial Lease%');
        })->where('type',6)->get();
        if(count($parents) == 0){
            return redirect()->back();
        }
        $branches = CompanyBranch::where('status', 1)->get();
    	$projects = Project::select('id','dealer','short_code')->get();

        $currency = Currency::select('id', 'code')->get();
		$saleRepresentative = SalePerson::where('active',1)->select('*')->get();
        return $this->view('sale.create',compact('parents','branches','users','clients', 'dd_parent_accs', 'currency', 'saleRepresentative','projects'));

    }
	public function change_unit() {
        $parents = CoaCategory::where(function($q){
            $q->where('name','like','Stand-L%')->orWhere('name','like','Financial Lease%');
        })->where('type',6)->get();
        if(count($parents) == 0){
            return redirect()->back();
        }
        $branches = CompanyBranch::where('status', 1)->get();
    	$projects = Project::select('id','dealer','short_code')->get();

        $currency = Currency::select('id', 'code')->get();
		$saleRepresentative = SalePerson::where('active',1)->select('*')->get();
        return $this->view('sale.change_unit',compact('parents','branches','users','clients', 'dd_parent_accs', 'currency', 'saleRepresentative','projects'));

    }

    public function post_create(Request $request) {
			DB::beginTransaction();
			try {
			$sale                                 = new Sale;
			$sale->order_no                       = 'SO-'.date('dmYHis').'-'.rand(10,100);
			$sale->user_id                        = Auth::user()->id;
			$sale->client_id                      = Request::input('client_id');
			$sale->currency                       = Request::input('currency');
			$sale->created_on                     = Request::input('created_on');
			$sale->sale_person                    = Request::input('sale_person');
			$sale->clearance_amount               = Request::input('clearance_amount');
			$sale->discount_promotion             = Request::input('discount_promotion');
			$sale->discount_other                 = Request::input('discount_other');
			$sale->discount_payment_option        = Request::input('discount_payment_option');
			$sale->amount_discount_payment_option = Request::input('amount_discount_payment_option');
			$sale->price_after_discount           = Request::input('price_after_discount');
			$sale->vat                            = 0;
			$sale->diposit_amount                 = Request::input('diposit_amount');
			$sale->final_price                    = Request::input('final_price');
			$sale->status                         = 'Ordered';
			$sale->authorization                  = 'Authorized';
			$sale->payment_option                 = Request::input('payment_option');
			$sale->payment_option_type            = Request::input('payment_option_type');
			$sale->payment_status                 = 'Unpaid';
			$sale->invoice_status                 = 'First Created';
			$sale->sale_status                    = Request::input('sale_status');
			$sale->remark                         = Request::input('remark');

            if($sale->save()) {
				$item                                 = new SaleItem();
				$item->sale_id                        = $sale->id;
				$item->company_branch_id              = Request::input('branch');
				$item->project_id                     = Request::input('project_id');
				$item->unit_type_id                   = Request::input('unit_type_id');
				$item->unit_id                        = Request::input('unit_id');
				$item->discount_promotion             = Request::input('discount_promotion');
				$item->discount_other                 = Request::input('discount_other');
				$item->discount_payment_option        = Request::input('discount_payment_option');
				$item->amount_discount_payment_option = Request::input('amount_discount_payment_option');
				$item->qty                            = 1;
				$item->unit_sale_price                = Request::input('unit_sale_price');
				$item->save();
				if(Request::input('sale_status')==='Change_Unit'){


			$dd = DrawdownAccounts::with( ['projects','unitType','units'])->where('unit_id',Request::input('from_unit_id'))->first(); 
				if($dd){
					$loan = Loan::select(['loans.*'])->with(['payment','transaction_req'])->where('loans.drawdown_acc', $dd->account_no)->first();
					if($loan){
						$total_discount=$loan->total_discount?$loan->total_discount:0;
						$change_unit                       = new ChangeUnit();
						$change_unit->sale_id              = $sale->id;
						$change_unit->loan_id              = $loan->id;
						$change_unit->last_posting_date    = $loan->transaction_req?$loan->transaction_req->trans_date:null;
						$change_unit->client_id            = $dd->client_id;
						$change_unit->customer_no          = $loan->cus_no;
						$change_unit->customer_name        = $dd->account_name;
						$change_unit->old_project_code     = $dd->projects->short_code;
						$change_unit->old_unit_id          = $dd->unit_type_id;
						$change_unit->old_unit_code        = $loan->units->code;
						$change_unit->contract_sign_date   = $loan->contract_date;
						$change_unit->selling_price        = $loan->unit_sale_price;
						$change_unit->discount             = $total_discount;
						$change_unit->net_selling_price    = $loan->price_after_discount?$loan->price_after_discount:floatval($loan->unit_sale_price) - floatval($total_discount);
						$change_unit->total_interest       = $loan->schedule->sum('interest');
						$change_unit->interest_paid        = $loan->payment->sum('paid_interest');
						$change_unit->principle_paid       = $loan->payment->sum('paid_principal');
						$change_unit->oustanding_principle = $loan->schedule->sum('principal')-$loan->payment->sum('paid_principal');
						$change_unit->save();

						// Free the old unit so it can be re-sold
						if ($loan->unit_id) {
							Unit::where('id', $loan->unit_id)->update([
								'status'   => 'available',
								'saleable' => 1,
							]);
						}
						// Terminate the old loan (it's no longer active — customer moved to the new unit)
						$loan->status = 11; // Terminate
						$loan->save();

					}
					}
				}

            }
			DB::commit();
			$datas['success'] = 1;
			return response()->json($datas);
		} catch (Exception $e) {
			DB::rollback();
			Session::flash('message', 'Save not successfully');
			return redirect()->back();
		}
        
    }
    
    public function getEdit($id = 0) {
        if ($id > 0) {
			$parents = CoaCategory::where(function($q){
				$q->where('name','like','Stand-L%')->orWhere('name','like','Financial Lease%');
			})->where('type',6)->get();
			if(count($parents) == 0){
				return redirect()->back();
			}	
	
			$sale             = Sale::find($id);
			$data['sale']     = $sale;
			$saleItem         = SaleItem::where('sale_id',$sale->id)->first();
			$data['saleItem'] = $saleItem;
			$data['project']  = Project::where('active',1)->get();
			$data['branches'] = CompanyBranch::where('status', 1)->get();
			$data['currency'] = Currency::select('id', 'code')->get();
			$data['parents']  = $parents;

			$client = Clients::find($sale->client_id);

			$data['client'] = $client;
			$data['saleRepresentative']= SalePerson::where('active',1)->select('*')->get();
            $data['contract_template'] = config('static_data.contract_template');
            if (!empty($data['sale'])) {
                return $this->view('sale.edit', $data);
            }
        }
        return redirect()->back();
    }

	public function postEdit($id=0) {
		DB::beginTransaction();
		try {
		$sale = Sale::find($id);
		if($sale){
			$sale->user_id                 = Auth::user()->id;
			$sale->client_id               = Request::input('client_id');
			$sale->currency                = Request::input('currency');
			$sale->sale_person             = Request::input('sale_person');
			$sale->sale_person_parent_l1   = Request::input('sale_person_parent_l1');
			$sale->sale_person_parent_l2   = Request::input('sale_person_parent_l2');
			$sale->clearance_amount        = Request::input('clearance_amount');
			$sale->discount_promotion      = Request::input('discount_promotion');
			$sale->discount_other          = Request::input('discount_other');
			$sale->discount_payment_option = Request::input('discount_payment_option');
			$sale->price_after_discount    = Request::input('price_after_discount');
			$sale->vat                     = 0;
			$sale->diposit_amount          = Request::input('diposit_amount');
			$sale->final_price             = Request::input('final_price');
			$sale->payment_option          = Request::input('payment_option');
			$sale->payment_option_type     = Request::input('payment_option_type');
			$sale->invoice_status          = 'Modified';
			$sale->sale_status             = Request::input('sale_status');
			$sale->remark                  = Request::input('remark');
	
			if($sale->save()) {
				$item = SaleItem::where('sale_id',$sale->id)->first();
				if($item){					
					$item->company_branch_id       = Request::input('branch');
					$item->project_id              = Request::input('project_id');
					$item->unit_type_id            = Request::input('unit_type_id');
					$item->unit_id                 = Request::input('unit_id');
					$item->unit_id                 = Request::input('unit_id');
					$item->discount_promotion      = Request::input('discount_promotion');
					$item->discount_other          = Request::input('discount_other');
					$item->discount_payment_option = Request::input('discount_payment_option');
					$item->qty                     = 1;
					$item->unit_sale_price         = Request::input('unit_sale_price');
					$item->save();	
				}	
				
			}
		}

		DB::commit();
		$datas['success'] = 1;
		return response()->json($datas);
	} catch (Exception $e) {
		DB::rollback();
		Session::flash('message', 'Save not successfully');
		return redirect()->back();
	}
	
	}

	public function getSaleAcceptOrder($id = 0)
    {
		if ($id > 0) {
        	$sale = Sale::find($id);           
			$saleItem = SaleItem::where('sale_id',$sale->id)->first();   
			// $exist_contract_id = ClientLoanAccounts::select('loan_ref')->get();
			// $exist_contract_id_array = [];
			// foreach($exist_contract_id as $key=>$value){
			// 	array_push($exist_contract_id_array,$value->loan_ref);
			// }
			$parents = CoaCategory::where(function($q){
				$q->where('name','like','Stand-L%')->orWhere('name','like','Financial Lease%');
			})->where('type',6)->get();
			if(count($parents) == 0){
				return redirect()->back();
			}
			$branches = CompanyBranch::where('status', 1)->get();
			$users = User::where('role_id','>',7)->get();

			$clients = Client::select('id', 'status','client_name')->with('general');
			if($sale){
				$clients = $clients->where('id', $sale->client_id)->where('status',1);
			}
			$clients        = $clients->get();
			$currency       = Currency::select('id', 'code')->get();
			$projects       = Project::select('id','dealer','short_code')->get();
			$loans          = '';
			$dd_parent_accs = CoaCategory::select('id', 'account_code', 'currency', 'name')->where('type', '=', '6')->where('name', 'like', 'Voluntary Deposits%')->where('name', 'like', '%Drawdown Accounts')->get();
			$exist_cla      = ClientLoanAccounts::select('id', 'account_no', 'parent_id')->get();
		
            $saleTermsHash = null;
            if (config('unified_posting.sale_handoff_enabled', false)) {
                $saleTermsHash = (new \App\Services\UnifiedOriginalSaleFinanceHandoff(DB::connection()))->termsHash($sale->id);
            }
			return $this->view('sale.accept_sale',compact('sale','saleItem','parents','branches','users','clients','loans', 'dd_parent_accs', 'currency', 'exist_cla','projects','saleTermsHash'));
		}
	}


	public function saleAcceptOrder($id=0) {
        if (config('unified_posting.sale_handoff_enabled', false)) {
            try {
                (new \App\Services\UnifiedOriginalSaleFinanceHandoff(DB::connection()))->accept($id, Request::input('sale_terms_hash'));
                return redirect()->route('list_sale');
            } catch (\Exception $e) {
                Session::flash('message', 'Unable to accept this sale. Reload the sale and verify its current information.');
                return redirect()->back();
            }
        }
        DB::beginTransaction();
        try {
            $sale = Sale::where('id', $id)->lockForUpdate()->first();
            if (!$sale || $sale->status !== 'Ordered') {
                throw new \LogicException('Sale is no longer available for acceptance.');
            }
            if ((string) Request::input('sale_id') !== (string) $sale->id) {
                throw new \LogicException('Submitted sale does not match the selected order.');
            }
            if (ClientLoanAccounts::where('sale_id', $sale->id)->exists()) {
                throw new \LogicException('Sale already has a loan account.');
            }
            $item = SaleItem::where('sale_id', $sale->id)->lockForUpdate()->first();
            if (!$item || (string) Request::input('client_id') !== (string) $sale->client_id
                || (string) Request::input('unit_id') !== (string) $item->unit_id) {
                throw new \LogicException('Submitted customer or unit does not match the current sale.');
            }
            app('App\Http\Controllers\AccountingController')->post_add_client_loan_account($sale->client_id, '/sale/list');
            $accounts = ClientLoanAccounts::where('sale_id', $sale->id)->get();
            if (count($accounts) !== 1 || $accounts[0]->client_id != $sale->client_id || $accounts[0]->unit_id != $item->unit_id) {
                throw new \LogicException('Account creation did not complete for this sale.');
            }
            $sale->status = 'Accepted';
            $sale->save();
            DB::commit();
            return redirect()->route('list_sale');
        } catch (\Exception $e) {
            DB::rollback();
            Session::flash('message', 'Save not successfully');
            return redirect()->back();
        }
	}


	public function markInvoice($id=0) {
		DB::beginTransaction();
		try {
		$sale = Sale::find($id);
		if($sale){
			$sale->status='Accepted';	
			$sale->invoice_status='Invoice';    
			if($sale->save()) {

				// *** ADD NEW INVOICE
				$invoice                                 = new Invoice();
				$invoice->sale_id                        = $sale->id;
				$invoice->order_no                       = $sale->order_no;
				$invoice->invoice_no                     = 'IV-'.date('dmYHis').'-'.rand(10,100);
				$invoice->user_id                        = Auth::user()->id;
				$invoice->client_id                      = $sale->client_id;
				$invoice->currency                       = $sale->currency;
				$invoice->created_on                     = date('Y-m-d H:i:s');
				$invoice->sale_person_parent_l1          = $sale->sale_person_parent_l1;
				$invoice->sale_person_parent_l2          = $sale->sale_person_parent_l2;
				$invoice->sale_person                    = $sale->sale_person;
				$invoice->clearance_amount               = $sale->clearance_amount;
				$invoice->discount_promotion             = $sale->discount_promotion;
				$invoice->discount_other                 = $sale->discount_other;
				$invoice->discount_payment_option        = $sale->discount_payment_option;
				$invoice->amount_discount_payment_option = $sale->amount_discount_payment_option;
				$invoice->price_after_discount           = $sale->price_after_discount;
				$invoice->vat                            = 0;
				$invoice->diposit_amount                 = $sale->diposit_amount;
				$invoice->final_price                    = $sale->final_price;
				$invoice->paid                           = 0;
				$invoice->balance                        = $sale->price_after_discount;
				$invoice->invoice_status                 = 'Invoice';
				$invoice->payment_option                 = $sale->payment_option;
				$invoice->payment_option_type            = $sale->payment_option_type;
				$invoice->payment_status                 = 'Open Balance';
				$invoice->remark                         = 'Invoice created from order';
				if($invoice->save()) {
					$saleItem                                    = SaleItem::where('sale_id',$sale->id)->first();
					$invoiceitem                                 = new InvoiceItem();
					$invoiceitem->invoice_id                     = $invoice->id;
					$invoiceitem->company_branch_id              = $saleItem->company_branch_id;
					$invoiceitem->project_id                     = $saleItem->project_id;
					$invoiceitem->unit_type_id                   = $saleItem->unit_type_id;
					$invoiceitem->unit_id                        = $saleItem->unit_id;
					$invoiceitem->discount_promotion             = $saleItem->discount_promotion;
					$invoiceitem->discount_other                 = $saleItem->discount_other;
					$invoiceitem->discount_payment_option        = $saleItem->discount_payment_option;
					$invoiceitem->amount_discount_payment_option = $saleItem->amount_discount_payment_option;
					$invoiceitem->qty                            = 1;
					$invoiceitem->unit_sale_price                = $saleItem->unit_sale_price;
					$invoiceitem->save();
				}
		
			}
		}

		DB::commit();
		return redirect()->back()->with('msg','Sale Accepted!');
	} catch (Exception $e) {
		DB::rollback();
		Session::flash('message', 'Save not successfully');
		return redirect()->back();
	}
	
	}
	public function saleperson_list() {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 15;

	$list = new SalePerson();
	$representative = $list->selectRaw('*')->with([
		'parent'
	]
	)->orderBy('lavel');

        $name = null;
        if (Request::has('name')) {
            $name = Request::input('name');
            $representative = $representative->where(function($query) use($name){
                $query->where('name', 'like', '%' . $name . '%')
                ->orWhere('name_en', 'like', '%' . $name . '%');
            });
        }
        $phone = null;
        if (Request::has('phone')) {
            $phone = Request::input('phone');
            $representative = $representative->where('phone', str_replace('-', '', $phone));
        }

		$sale_person = null;
        if(!empty(Auth::user()->sale_person)){
            $sale_person =Auth::user()->sale_person;
            $sale_person_list = SalePerson::find($sale_person);

            if($sale_person_list->lavel==2){
                $sale_person_memberl2 = SalePerson::where('active',1)->where('parent_id',$sale_person_list->id)->get();
                if($sale_person_memberl2){
                    $ids = [];
                    foreach($sale_person_memberl2 as $sale_member2){
                        $ids[] = $sale_member2->id;
                    }
                    $representative = $representative->orWhereIn('id',$ids); 

                }              
            }elseif($sale_person_list->lavel==1){

                $sale_person_memberl2 = SalePerson::where('active',1)->where('parent_id',$sale_person_list->id)->get();

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
                    $representative = $representative->orWhereIn('id',$idsAll); 

                } 

            }
        }

		$representative=$representative->get();

        // $representative = $representative->paginate($offset);
        return $this->view('sale.saleperson_list', ['lists' => $representative, 'name' => $name, 'phone' => $phone,'offset'=>$offset]);
    }

	public function getAddSalePerson() {
        
			$data['saleRepresentativel1'] = SalePerson::where('active',1)->where('lavel', 1)->select('*')->get();
			$data['saleRepresentativel2'] = SalePerson::where('active',1)->where('lavel', 2)->select('*')->get();
            
			return $this->view('sale.add_saleperson', $data);
            
    }
	public function postAddSalePerson() {
    
            $data = Request::all();
            $rules = [
                'name' => 'required|max:255',
                'gender' => 'required',
                'national_id' => 'required|max:50',
                'phone' => 'required',
				'lavel' => 'required',
				'com_rate' => 'required',
				'com_level_type' => 'required',
            ];
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                return redirect()->back()->with(['error' => 'Failed to Created']);
            }else{
				$representative = new SalePerson;
                $representative->name = Request::input('name');
                $representative->lavel = Request::input('lavel');
                $representative->gender = Request::input('gender');
                $representative->dob = Request::input('dob');
                $representative->national_id = Request::input('national_id');
                $representative->phone = Request::input('phone');
				$representative->lavel = Request::input('lavel');
				$representative->com_rate = Request::input('com_rate');
				$representative->com_level_type = Request::input('com_level_type');
                $representative->user_id = Auth::user()->id;

				$parent_id=null;
				if(Request::has('sale_person_parent_l1')){
					$parent_id=Request::input('sale_person_parent_l1');
				}elseif(Request::has('sale_person_parent_l2')){
					$parent_id=Request::input('sale_person_parent_l2');
				}
				
				$representative->parent_id=$parent_id;
				
                if($representative->save()) {
					return redirect()->route('saleperson_list');
                }
                return redirect()->route('saleperson_list');
            }
        
        return redirect()->route('saleperson_list');
    }

    public function getEditSalePerson($id = 0) {
        if ($id > 0) {
			$data['representative']       = SalePerson::find($id);
			$data['saleRepresentativel1'] = SalePerson::where('active',1)->where('lavel', 1)->select('*')->get();
			$data['saleRepresentativel2'] = SalePerson::where('active',1)->where('lavel', 2)->select('*')->get();
            if (!empty($data['representative'])) {
                return $this->view('sale.edit_saleperson', $data);
            }
        }
        return redirect()->back();
    }
	public function postEditSalePerson($id = 0) {
        if ($id > 0) {
            $data = Request::all();
            $rules = [
                'name' => 'required|max:255',
                'gender' => 'required',
                'national_id' => 'required|max:50',
                'phone' => 'required',
				'lavel' => 'required',
				'com_rate' => 'required',
				'com_level_type' => 'required',
            ];
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                return redirect()->back()->with(['error' => 'Failed to Update']);
            }else{
                $representative = SalePerson::find($id);
                $representative->name = Request::input('name');
                $representative->lavel = Request::input('lavel');
                $representative->gender = Request::input('gender');
                $representative->dob = Request::input('dob');
                $representative->national_id = Request::input('national_id');
                $representative->phone = Request::input('phone');
				$representative->lavel = Request::input('lavel');
				$representative->com_rate = Request::input('com_rate');
				$representative->com_level_type = Request::input('com_level_type');
                $representative->user_id = Auth::user()->id;

				$parent_id=null;
				if(Request::has('sale_person_parent_l1')){
					$parent_id=Request::input('sale_person_parent_l1');
				}elseif(Request::has('sale_person_parent_l2')){
					$parent_id=Request::input('sale_person_parent_l2');
				}
				
				$representative->parent_id=$parent_id;
				
                if($representative->save()) {
					return redirect()->route('saleperson_list');
                }
                return redirect()->route('saleperson_list');
            }
        }
        return redirect()->route('saleperson_list');
    }
	public function salePersonEnable($id = 0) {
        if ($id > 0) {
            $representative = SalePerson::find($id);
            if (!empty($representative)) {
                $representative->active = 1;
                $representative->save();
                
            }
        }
        return redirect()->route('saleperson_list');
    }
	public function salePersonDisable($id = 0) {
        if ($id > 0) {
            $representative = SalePerson::find($id);
            if (!empty($representative)) {
                $representative->active = 0;
                $representative->save();
                
            }
        }
        return redirect()->route('saleperson_list');
    }

    public function listSale() {
        if (config('unified_sales.enabled')) {
            $repository=\App\Services\UnifiedSalesRepository::configured();
            $filters=Request::only(array('customer_id','company','project_id','status','t_from','t_to','search','offset'));
            return $this->view('sale.unified_list',array('sales'=>$repository->listing($filters),'branches'=>$repository->branches(),'filters'=>$filters));
        }
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 50;
        if(!$offset){
        	$offset = 50;
        }
        $query_arr = array('user_id'=>Auth::user()->id, 'branch_id'=>Auth::user()->branch_id, 'role_id'=>Auth::user()->role_id);
		$from_date = Request::input('t_from')?Request::input('t_from'):null;
        $to_date = Request::input('t_to')?Request::input('t_to'):null;
        $sale = new Sale();
        $listSale = $sale->selectRaw('
				tb_sale_order.id,
				tb_sale_order.order_no, 
				tb_sale_order.created_on,
				tb_sale_items.unit_sale_price,            
                tb_clients.client_name,
				tb_clients.phone1,
				tb_clients.phone2,
				tb_clients.address,
				tb_company_branch.short_name as company,
                tb_clients.client_type,
                tb_projects.short_code,
                tb_unit_types.name as unit_type,
                tb_units.code as unit,
				tb_units.id as unit_id,                 
                tb_currency.code AS currency_code,
				tb_sale_order.client_id,
				tb_sale_order.clearance_amount,
				tb_sale_order.discount_promotion,
				tb_sale_order.discount_other,
				tb_sale_order.discount_payment_option,
				tb_sale_order.amount_discount_payment_option,				
				tb_sale_order.price_after_discount,
				tb_sale_order.vat,
				tb_sale_order.diposit_amount,
				tb_sale_order.final_price,
				tb_sale_order.status,
				tb_sale_order.sale_status,
				tb_sale_order.authorization,
				tb_sale_order.payment_option,
				tb_sale_order.payment_status,
				tb_sale_order.invoice_status,
				tb_sale_order.remark,
				tb_sale_order.sale_person,
				tb_sale_order.sale_person_parent_l1,
				tb_sale_order.sale_person_parent_l2
            '
            )    
			->with([
				'sale_persons',
				'sale_person_parent_lavel1',
				'sale_person_parent_lavel2'
            ]
        )   
		
		->leftJoin('sale_items','sale_order.id','=','sale_items.sale_id')
		->join('currency','sale_order.currency','=','currency.id')
        ->leftJoin('clients', 'clients.id', '=', 'sale_order.client_id')
		->leftJoin('company_branch', 'company_branch.id', '=', 'sale_items.company_branch_id')		
        ->leftJoin('projects','projects.id','=','sale_items.project_id')
        ->leftJoin('unit_types','unit_types.id','=','sale_items.unit_type_id')
        ->leftJoin('units','units.id','=','sale_items.unit_id')
		// ->whereDate('sale_order.created_on','>=',$from_date)->whereDate('sale_order.created_on','<=',$to_date)
		->orderBy('sale_order.id', 'desc')
		->groupBy('sale_order.order_no')
		;

		
		if(!empty($from_date) && !empty($to_date)){
			$listSale = $listSale->whereDate('sale_order.created_on','>=',$from_date)->whereDate('sale_order.created_on','<=',$to_date);
		}
		$customer_id = Request::input('customer_id');
		$company = Request::input('company');
		$client = null;
		if($customer_id){
            $listSale = $listSale->where('sale_order.client_id',$customer_id);
			$client = Clients::find($customer_id);
        }
        if($company){
            $listSale = $listSale->where('sale_items.company_branch_id',$company);			
            $company_branch = CompanyBranch::find(Request::get('company'));
        }
		$project_id = null;
        if(Request::has('project_id')){
            $project_id = Request::input('project_id');
            $project = Project::find($project_id);
            $listSale = $listSale->where('sale_items.project_id',$project_id);
        }
		$status = null;
        if(Request::has('status')){
            $status = Request::input('status');
            $listSale = $listSale->where('sale_order.status',$status);
        }
		if(Request::get('search')){
            $listSale = $listSale->where('sale_order.order_no','like','%'.Request::get('search').'%');
            $listSale = $listSale->orWhere('projects.dealer_en','like','%'.Request::get('search').'%');
            $listSale = $listSale->orWhere('projects.dealer','like','%'.Request::get('search').'%');
            $listSale = $listSale->orWhere('clients.client_name','like','%'.Request::get('search').'%');
            $listSale = $listSale->orWhere('sale_order.client_id','like','%'.Request::get('search').'%');
            $listSale = $listSale->orWhere('company_branch.short_name','like','%'.Request::get('search').'%');
            $listSale = $listSale->orWhere('unit_types.name','like','%'.Request::get('search').'%');
            $listSale = $listSale->orWhere('units.code','like','%'.Request::get('search').'%');
        }

		$listSale = $listSale->paginate($offset)->setPath('list?client_id='.$customer_id.'&company='.$company.'&project_id='.$project_id.'&status='.$status.'&search='.$search.'&offset='.$offset);
               
        if(Request::input('is_excel') == 1 || Request::input('is_csv') == 1){
            $xlsx = 'xlsx';
            if(Request::has('is_csv') == 1){
                $xlsx = 'csv';
            }
            return Excel::create('sale-order-'.date('d-M-Y'), function($excel) use ($listSale) {
                $excel->sheet('mySheet', function($sheet) use ($listSale)
                {
                    $sheet->loadView('exports.sale.list_sale_order',['lists' => $listSale]);
                });
            })->download($xlsx);
        }
		$contract_template = config('static_data.contract_template');
        return $this->view('sale.list_sale', ['lists' => $listSale, 'customer_id' => $company,'company' => $customer_id,'offset'=>$offset,'contract_template' => $contract_template],compact('projects','project_id','customer_id','client','from_date','to_date','company','status'));

    }
	public function getDetailCommission($id =0){
    	if ($id > 0) {

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
				'user' => function ($q) {
					$q->select('id', 'name');
				},
				'payment' => function ($query) {
					$query->select(['id', 'loan_id', 'repayment_date', 'payment_month', 'status', 'condition_id', 'paid_interest', 'paid_principal', 'repayment_owed','late_day'])->orderBy('payment_month','desc');
				},  
				'coa_journal_detail' => function ($query) {
					$query->select('*', 'journal_requiry.entry_date', 'journal_requiry.description')
					->join('journal_requiry', 'journal_requiry.id', '=', 'journal_detail.journal_id')
					->orderBy('journal_requiry.entry_date', 'asc')
					->where('journal_detail.is_audit', '=', 1)
					->where('journal_detail.credit', '>', 0);
				},         
				'sale_persons',
				'sale_person_parent_lavel1',
				'sale_person_parent_lavel2',
				'client_loan_account',
				'payoff',
				'PaymentOptions',
				'commission_withdrawal_transaction' => function ($query) {
					$query->select('commission_withdrawal_transaction.*','saleperson.name as sale_team')
					->join('saleperson', 'saleperson.id', '=', 'commission_withdrawal_transaction.saleperson_id')
					->orderBy('commission_withdrawal_transaction.withdrawal_date','desc');
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
			// ->whereDate('loans.disburse_date','>=',$from_date)->whereDate('loans.disburse_date','<=',$to_date)
			->where('sale_order.invoice_status','Invoice')
			// ->where('sale_order.sale_status','New_Sale')
			->where('sale_order.status','Accepted')
			->where('loans.id',$id)
			->first();
			$commissionRate = CommissionRate::first();
			$sale_person = SalePerson::where('active',1)->where('id',$loan->sale_person)->first();
			// $sale_team_commission = array('sale_team_l1' => '50', 'sale_team_l2' => '30', 'sale_team_l3' => '20');
			$sale_team_commission = [];
			if($sale_person){
				$total_customer_paid=$loan->coa_journal_detail->sum('credit');
				$grand_total_com_paid=$loan->commission_withdrawal_transaction->sum('received_amount');
				if($sale_person->com_type!='Full'){
					if($sale_person->lavel==1){
						$total_com_paid=CommissionWithdrawalTransaction::where('saleperson_id',$loan->sale_person)->where('loan_id',$loan->id)->sum('received_amount');
						array_push($sale_team_commission, [
							'sale_team'                 => $sale_person->name,
							'com_level_type'            => $sale_person->com_level_type,
							'com_rate'                  => $sale_person->com_rate,
							'total_com'                 => $loan->commission_value,
							'total_com_paid'            => $total_com_paid,
							'saleperson_id'             => $loan->sale_person,
							'total_customer_paid'       => $total_customer_paid,
							'total_commission_tobe_pay' => (floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid)
						]);


					}elseif($sale_person->lavel==2){
						
						$parentl1 = SalePerson::where('active',1)->where('id',$sale_person->parent_id)->first();
						$total_com_paidl1=CommissionWithdrawalTransaction::where('saleperson_id',$sale_person->parent_id)->where('loan_id',$loan->id)->sum('received_amount');
						array_push($sale_team_commission, [
							'sale_team'                 => $parentl1->name,
							'com_level_type'            => $parentl1->com_level_type,
							'com_rate'                  => floatval($parentl1->com_rate)-floatval($sale_person->com_rate),
							'total_com'                 => floatval($loan->commission_value) *(floatval($parentl1->com_rate)-floatval($sale_person->com_rate))/100,
							'total_com_paid'            => $total_com_paidl1,
							'saleperson_id'             => $sale_person->parent_id,
							'total_customer_paid'       => $total_customer_paid,
							'total_commission_tobe_pay' => (floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid)
						]);
						$total_com_paidl2=CommissionWithdrawalTransaction::where('saleperson_id',$loan->sale_person)->where('loan_id',$loan->id)->sum('received_amount');
						array_push($sale_team_commission, [
							'sale_team'=>$sale_person->name,
							'com_level_type'=>$sale_person->com_level_type,
							'com_rate'=>$sale_person->com_rate,
							'total_com'=>floatval($loan->commission_value) * floatval($sale_person->com_rate) /100,
							'total_com_paid'=>$total_com_paidl2,
							'saleperson_id'=>$loan->sale_person,
							'total_customer_paid'=>$total_customer_paid,
							'total_commission_tobe_pay'=>(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid)
						]);
					}elseif($sale_person->lavel==3){
					$parentl2 = SalePerson::where('active',1)->where('id',$sale_person->parent_id)->first();
					$parentl1 = SalePerson::where('active',1)->where('id',$parentl2->parent_id)->first();
					$total_com_paidl1=CommissionWithdrawalTransaction::where('saleperson_id',$parentl2->parent_id)->where('loan_id',$loan->id)->sum('received_amount');
					array_push($sale_team_commission, [
						'sale_team'=>$parentl1->name,
						'com_level_type'=>$parentl1->com_level_type,
						'com_rate'=>floatval($parentl1->com_rate)-floatval($parentl2->com_rate),
						'total_com'=>floatval($loan->commission_value) *(floatval($parentl1->com_rate)-floatval($parentl2->com_rate))/100,
						'total_com_paid'=>$total_com_paidl1,
						'saleperson_id'=>$parentl2->parent_id,
						'total_customer_paid'=>$total_customer_paid,
						'total_commission_tobe_pay'=>(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid)
					]);

					$total_com_paidl2=CommissionWithdrawalTransaction::where('saleperson_id',$parentl2->id)->where('loan_id',$loan->id)->sum('received_amount');
					array_push($sale_team_commission, [
						'sale_team'=>$parentl2->name,
						'com_level_type'=>$parentl2->com_level_type,
						'com_rate'=>floatval($parentl2->com_rate)-floatval($sale_person->com_rate),
						'total_com'=>floatval($loan->commission_value) *(floatval($parentl2->com_rate)-floatval($sale_person->com_rate))/100,
						'total_com_paid'=>$total_com_paidl2,
						'saleperson_id'=>$sale_person->parent_id,
						'total_customer_paid'=>$total_customer_paid,
						'total_commission_tobe_pay'=>(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid)
					]);


					$total_com_paidl3=CommissionWithdrawalTransaction::where('saleperson_id',$loan->sale_person)->where('loan_id',$loan->id)->sum('received_amount');
					array_push($sale_team_commission, [
						'sale_team'=>$sale_person->name,
						'com_level_type'=>$sale_person->com_level_type,
						'com_rate'=>$sale_person->com_rate,
						'total_com'=>floatval($loan->commission_value) * floatval($sale_person->com_rate) /100,
						'total_com_paid'=>$total_com_paidl3,
						'saleperson_id'=>$loan->sale_person,
						'total_customer_paid'=>$total_customer_paid,
						'total_commission_tobe_pay'=>(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid)
					]);
				}
				}else{
					$total_com_paid=CommissionWithdrawalTransaction::where('saleperson_id',$loan->sale_person)->where('loan_id',$loan->id)->sum('received_amount');
					array_push($sale_team_commission, [
						'sale_team'=>$sale_person->name,
						'com_level_type'=>$sale_person->com_level_type,
						'com_rate'=>$sale_person->com_rate,
						'total_com'=>$loan->commission_value,
						'total_com_paid'=>$total_com_paid,
						'saleperson_id'=>$loan->sale_person,
						'total_customer_paid'=>$total_customer_paid,
						'total_commission_tobe_pay'=>(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid)
					]);
				}
		
			}
			return $this->view('sale.list_sale_commission_detail', [
				'listSale' => $loan,
				'commissionRate'=>$commissionRate,
				'sale_team_commission'=>$sale_team_commission
			]);
        }
        return redirect()->back();
    }
	public function getRequestWithdrawCommission($id =0){
    	if ($id > 0) {

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
				'user' => function ($q) {
					$q->select('id', 'name');
				},
				'payment' => function ($query) {
					$query->select(['id', 'loan_id', 'repayment_date', 'payment_month', 'status', 'condition_id', 'paid_interest', 'paid_principal', 'repayment_owed','late_day'])->orderBy('payment_month','desc');
				},  
				'coa_journal_detail' => function ($query) {
					$query->select('*', 'journal_requiry.entry_date', 'journal_requiry.description')
					->join('journal_requiry', 'journal_requiry.id', '=', 'journal_detail.journal_id')
					->orderBy('journal_requiry.entry_date', 'asc')
					->where('journal_detail.is_audit', '=', 1)
					->where('journal_detail.credit', '>', 0);
				},         
				'sale_persons',
				'sale_person_parent_lavel1',
				'sale_person_parent_lavel2',
				'client_loan_account',
				'payoff',
				'PaymentOptions',
				'commission_withdrawal_transaction'
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
			// ->whereDate('loans.disburse_date','>=',$from_date)->whereDate('loans.disburse_date','<=',$to_date)
			->where('sale_order.invoice_status','Invoice')
			// ->where('sale_order.sale_status','New_Sale')
			->where('sale_order.status','Accepted')
			->where('loans.id',$id)
			->with([
				'user' => function ($q) {
					$q->select('id', 'name');
				},
				'payment' => function ($query) {
					$query->select(['id', 'loan_id', 'repayment_date', 'payment_month', 'status', 'condition_id', 'paid_interest', 'paid_principal', 'repayment_owed','late_day'])->orderBy('payment_month','desc');
				},  
				'coa_journal_detail' => function ($query) {
					$query->select('*', 'journal_requiry.entry_date', 'journal_requiry.description')
					->join('journal_requiry', 'journal_requiry.id', '=', 'journal_detail.journal_id')
					->orderBy('journal_requiry.entry_date', 'asc')
					->where('journal_detail.is_audit', '=', 1)
					->where('journal_detail.credit', '>', 0);
				},         
				'sale_persons',
				'sale_person_parent_lavel1',
				'sale_person_parent_lavel2',
				'client_loan_account',
				'payoff',
				'PaymentOptions',
				'commission_withdrawal_transaction' => function ($query) {
					$query->select('*')->orderBy('withdrawal_date','desc');
				}]
			)->first();
			$commissionRate = CommissionRate::first();

			$sale_person = SalePerson::where('active',1)->where('id',$loan->sale_person)->first();
			// $sale_team_commission = array('sale_team_l1' => '50', 'sale_team_l2' => '30', 'sale_team_l3' => '20');
			$sale_team_commission = [];
			if($sale_person){
				$total_customer_paid=$loan->coa_journal_detail->sum('credit');
				$grand_total_com_paid=$loan->commission_withdrawal_transaction->sum('received_amount');
				if($sale_person->com_type!='Full'){
					if($sale_person->lavel==1){
						$total_com_paid=CommissionWithdrawalTransaction::where('saleperson_id',$loan->sale_person)->where('loan_id',$loan->id)->sum('received_amount');
						array_push($sale_team_commission, [
							'com_level_type'            => $sale_person->com_level_type,
							'com_rate'                  => $sale_person->com_rate,
							'total_com'                 => $loan->commission_value,
							'total_com_paid'            => $total_com_paid,
							'saleperson_id'             => $loan->sale_person,
							'total_customer_paid'       => $total_customer_paid,
							'total_commission_tobe_pay' => (floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid)
						]);


					}elseif($sale_person->lavel==2){
						
						$parentl1 = SalePerson::where('active',1)->where('id',$sale_person->parent_id)->first();
						$total_com_paidl1=CommissionWithdrawalTransaction::where('saleperson_id',$sale_person->parent_id)->where('loan_id',$loan->id)->sum('received_amount');
						array_push($sale_team_commission, [
							'com_level_type'            => $parentl1->com_level_type,
							'com_rate'                  => floatval($parentl1->com_rate)-floatval($sale_person->com_rate),
							'total_com'                 => floatval($loan->commission_value) *(floatval($parentl1->com_rate)-floatval($sale_person->com_rate))/100,
							'total_com_paid'            => $total_com_paidl1,
							'saleperson_id'             => $sale_person->parent_id,
							'total_customer_paid'       => $total_customer_paid,
							'total_commission_tobe_pay' => (floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid)
						]);
						$total_com_paidl2=CommissionWithdrawalTransaction::where('saleperson_id',$loan->sale_person)->where('loan_id',$loan->id)->sum('received_amount');
						array_push($sale_team_commission, [
							'com_level_type'=>$sale_person->com_level_type,
							'com_rate'=>$sale_person->com_rate,
							'total_com'=>floatval($loan->commission_value) * floatval($sale_person->com_rate) /100,
							'total_com_paid'=>$total_com_paidl2,
							'saleperson_id'=>$loan->sale_person,
							'total_customer_paid'=>$total_customer_paid,
							'total_commission_tobe_pay'=>(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid)
						]);
					}elseif($sale_person->lavel==3){
					$parentl2 = SalePerson::where('active',1)->where('id',$sale_person->parent_id)->first();
					$parentl1 = SalePerson::where('active',1)->where('id',$parentl2->parent_id)->first();
					$total_com_paidl1=CommissionWithdrawalTransaction::where('saleperson_id',$parentl2->parent_id)->where('loan_id',$loan->id)->sum('received_amount');
					array_push($sale_team_commission, [
						'com_level_type'=>$parentl1->com_level_type,
						'com_rate'=>floatval($parentl1->com_rate)-floatval($parentl2->com_rate),
						'total_com'=>floatval($loan->commission_value) *(floatval($parentl1->com_rate)-floatval($parentl2->com_rate))/100,
						'total_com_paid'=>$total_com_paidl1,
						'saleperson_id'=>$parentl2->parent_id,
						'total_customer_paid'=>$total_customer_paid,
						'total_commission_tobe_pay'=>(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid)
					]);

					$total_com_paidl2=CommissionWithdrawalTransaction::where('saleperson_id',$parentl2->id)->where('loan_id',$loan->id)->sum('received_amount');
					array_push($sale_team_commission, [
						'com_level_type'=>$parentl2->com_level_type,
						'com_rate'=>floatval($parentl2->com_rate)-floatval($sale_person->com_rate),
						'total_com'=>floatval($loan->commission_value) *(floatval($parentl2->com_rate)-floatval($sale_person->com_rate))/100,
						'total_com_paid'=>$total_com_paidl2,
						'saleperson_id'=>$sale_person->parent_id,
						'total_customer_paid'=>$total_customer_paid,
						'total_commission_tobe_pay'=>(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid)
					]);


					$total_com_paidl3=CommissionWithdrawalTransaction::where('saleperson_id',$loan->sale_person)->where('loan_id',$loan->id)->sum('received_amount');
					array_push($sale_team_commission, [
						'com_level_type'=>$sale_person->com_level_type,
						'com_rate'=>$sale_person->com_rate,
						'total_com'=>floatval($loan->commission_value) * floatval($sale_person->com_rate) /100,
						'total_com_paid'=>$total_com_paidl3,
						'saleperson_id'=>$loan->sale_person,
						'total_customer_paid'=>$total_customer_paid,
						'total_commission_tobe_pay'=>(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid)
					]);
				}
				}else{
					$total_com_paid=CommissionWithdrawalTransaction::where('saleperson_id',$loan->sale_person)->where('loan_id',$loan->id)->sum('received_amount');
					array_push($sale_team_commission, [
						'com_level_type'=>$sale_person->com_level_type,
						'com_rate'=>$sale_person->com_rate,
						'total_com'=>$loan->commission_value,
						'total_com_paid'=>$total_com_paid,
						'saleperson_id'=>$loan->sale_person,
						'total_customer_paid'=>$total_customer_paid,
						'total_commission_tobe_pay'=>(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid)
					]);
				}
		
			}
			// dd($loan);
			return $this->view('sale.request_withdraw', [
				'listSale' => $loan,
				'commissionRate'=>$commissionRate,
				'sale_team_commission'=>$sale_team_commission,
				'sale_team_commission_json'=>json_encode($sale_team_commission)
			]);
        }
        return redirect()->back();
    }
	public function postRequestWithdrawCommission($id = 0) {
            $data = Request::all();
            $rules = [
                'withdrawal_amount' => 'required',
                'saleperson_id' => 'required',
                'loan_id' => 'required',
            ];
            $validator = Validator::make($data, $rules);

            if ($validator->fails()) {
                return redirect()->back()->with(['error' => 'Failed to Created']);
            }else{
				$item = new CommissionWithdrawalTransaction;
                $item->loan_id = Request::input('loan_id');
                $item->saleperson_id = Request::input('saleperson_id');
                $item->commission_rate = Request::input('commission_rate');
                $item->withdrawal_amount = Request::input('withdrawal_amount');
                $item->status = 'Withdrawal';
                $item->payment_status ='Due';
				$item->withdrawal_date = date('Y-m-d');
                $item->requester = Auth::user()->id;
				$item->noted = Request::input('noted');		
					
				
                if($item->save()) {
					return redirect()->route('list_sale_commission_detail',[$item->loan_id]);
                }
            }
        

			return redirect()->route('list_sale_commission_detail',[$item->loan_id]);
    }
	public function postRequestAllWithdrawCommission() {
		$data = Request::all();
		$rules = [
			'company' => 'required',
			'project_id' => 'required',
			'unit_type_id' => 'required',
			'sale_person' => 'required',
		];
		$validator = Validator::make($data, $rules);

		if ($validator->fails()) {
			return redirect()->back()->with(['error' => 'Failed to Created']);
		}else{
			DB::beginTransaction();
			try {
				$commissionRate = CommissionRate::first();	

				$com_withdrawal = new CommissionWithdrawal;
				$com_withdrawal->withdrawal_number ='RE-'.date('dmY').'-'.rand(10,100).'-'.Request::input('sale_person');
				$com_withdrawal->company_id = Request::input('company');
				$com_withdrawal->project_id = Request::input('project_id');
				$com_withdrawal->unit_type_id = Request::input('unit_type_id');
				$com_withdrawal->saleperson_id = Request::input('sale_person');

				if(Request::has('t_from')){  
					$start_date = date('Y-m-d',strtotime(Request::input('t_from')));                 
					$com_withdrawal->t_from = $start_date;
				}
				if(Request::has('t_to')){     
					$to_date = date('Y-m-d',strtotime(Request::input('t_to')));              
					$com_withdrawal->t_to = $to_date;
				}

				$com_withdrawal->commission_rate =$commissionRate->rate;
				$com_withdrawal->status = 'Withdrawal';
				$com_withdrawal->payment_status ='Due';
				$com_withdrawal->withdrawal_date = date('Y-m-d');
				$com_withdrawal->requester = Auth::user()->id;
				$com_withdrawal->noted = Request::input('noted');
				if($com_withdrawal->save()){
					$comission_request=Request::input('comission_request');		
					if(count($comission_request)>0){
						$B0 = new Loan();
						foreach ($comission_request as $loan_id) {
							$loan = $B0->selectRaw('
									tb_loans.id,								
									tb_loans.loan_account_id,
									tb_loans.drawdown_acc,	
									tb_loans.discount_promotion,
									tb_loans.discount_other,
									tb_loans.amount_discount_payment_option,
									tb_loans.unit_sale_price,
									tb_units.commission_type,
									tb_units.commission_value,
									tb_units.commission_approved,								
									tb_loans.co,								
									tb_sale_order.id as sale_id,
									tb_sale_order.order_no,
									tb_sale_order.sale_person,								
									tb_drawdown_account.coa_id
								'
								)
							->join('drawdown_account','drawdown_account.account_no','=','loans.drawdown_acc') 
							->leftJoin('client_loan_accounts','loans.loan_account_id','=','client_loan_accounts.id')
							->leftJoin('sale_order','sale_order.id','=','client_loan_accounts.sale_id')
							->leftJoin('units','units.id','=','loans.unit_id')
							->where('sale_order.invoice_status','Invoice')
							->where('sale_order.status','Accepted')
							->where('loans.id',$loan_id)
							->with([
								'coa_journal_detail' => function ($query) {
									$query->select('*', 'journal_requiry.entry_date', 'journal_requiry.description')
									->join('journal_requiry', 'journal_requiry.id', '=', 'journal_detail.journal_id')
									->orderBy('journal_requiry.entry_date', 'asc')
									->where('journal_detail.is_audit', '=', 1)
									->where('journal_detail.credit', '>', 0);

									if(Request::has('t_from')){  
										$start_date = date('Y-m-d H:i:s',strtotime(Request::input('t_from')));                 
										$query = $query->whereDate('journal_requiry.entry_date','>=', $start_date);
									}
									if(Request::has('t_to')){     
										$to_date = date('Y-m-d H:i:s',strtotime(Request::input('t_to')));              
										$query = $query->whereDate('journal_requiry.entry_date','<=', $to_date);
									}
								},  
								'commission_withdrawal_transaction' => function ($query) {
									$query->select('*')->orderBy('withdrawal_date','desc');
								}]
							)->first();	
			
							$sale_person = SalePerson::where('active',1)->where('id',$loan->sale_person)->first();
							$sale_team_commission = [];
							if($sale_person){
								$total_customer_paid=$loan->coa_journal_detail->sum('credit');
								$grand_total_com_paid=$loan->commission_withdrawal_transaction->sum('received_amount');						
									
									if($sale_person->lavel==1){
										$sale_person_request = SalePerson::where('active',1)->where('id',Request::input('sale_person'))->first();
										$total_com_paid=CommissionWithdrawalTransaction::where('saleperson_id',$sale_person_request->id)->where('loan_id',$loan->id)->sum('received_amount');
										$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);
	
										if(floatval($loan->commission_value)<=0){
											return redirect()->back()->with(['error' => "Cannot request withdraw with unit not commission!"]);
										}
										
										if(floatval($loan->commission_approved)<=0){
											return redirect()->back()->with(['error' => "Cannot request withdraw with unit commission not yet approved!"]);
										}
										$commission_value=$loan->commission_value;
										if($loan->commission_type=='%'){
											$net_selling_price=0;
											$totalall_discount=floatval($loan->discount_promotion) + floatval($loan->discount_other) + floatval($loan->amount_discount_payment_option);
											$net_selling_price=floatval($loan->unit_sale_price) - floatval($totalall_discount); 
											$commission_value=floatval($net_selling_price)* floatval($loan->commission_value)/100;
										}
										$total_com=floatval($commission_value) *floatval($sale_person_request->com_rate)/100;
										$withdrawal_amount=$total_commission_tobe_pay; 
										$balance=floatval($total_com)-floatval($total_com_paid);
										$withdrawal_amount_request=0;
										if(floatval($withdrawal_amount)>0 && floatval($balance)>0){
											if($withdrawal_amount>$balance){
											$withdrawal_amount_request=$balance;
											}else{
												$withdrawal_amount_request=$withdrawal_amount;
											}
										
												$item = new CommissionWithdrawalTransaction;												
												$item->commission_withdrawal_id=$com_withdrawal->id;
												$item->withdrawal_number=$com_withdrawal->withdrawal_number;
												$item->loan_id = $loan->id;
												$item->saleperson_id = Request::input('sale_person');
												$item->commission_rate =$commissionRate->rate;
												$item->withdrawal_amount = $withdrawal_amount_request;
												$item->total_customer_paid=$total_customer_paid;
												$item->status = 'Withdrawal';
												$item->payment_status ='Due';
												$item->withdrawal_date = date('Y-m-d');
												$item->requester = Auth::user()->id;
												$item->noted = Request::input('noted');											
												
												$item->save();
	
										}else{
											return redirect()->back()->with(['error' => "Sale team request don't have sufficient balance to withdraw commission"]);
										}
				
				
									}elseif($sale_person->lavel==2){
										$sale_person_request = SalePerson::where('active',1)->where('id',Request::input('sale_person'))->first();
										if($sale_person_request->lavel==1){										
											$total_com_paid=CommissionWithdrawalTransaction::where('saleperson_id',$sale_person_request->id)->where('loan_id',$loan->id)->sum('received_amount');
											$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);										
											
											if(floatval($loan->commission_value)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit not commission!"]);
											}
											
											if(floatval($loan->commission_approved)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit commission not yet approved!"]);
											}
											$commission_value=$loan->commission_value;
											if($loan->commission_type=='%'){
												$net_selling_price=0;
												$totalall_discount=floatval($loan->discount_promotion) + floatval($loan->discount_other) + floatval($loan->amount_discount_payment_option);
												$net_selling_price=floatval($loan->unit_sale_price) - floatval($totalall_discount); 
												$commission_value=floatval($net_selling_price)* floatval($loan->commission_value)/100;
											}
	
											$total_com=floatval($commission_value) *(floatval($sale_person_request->com_rate)-floatval($sale_person->com_rate))/100;
											$withdrawal_amount=$total_commission_tobe_pay; 
											$balance=floatval($total_com)-floatval($total_com_paid);
											$withdrawal_amount_request=0;
											if(floatval($withdrawal_amount)>0 && floatval($balance)>0){
												if($withdrawal_amount>$balance){
												$withdrawal_amount_request=$balance;
												}else{
													$withdrawal_amount_request=$withdrawal_amount;
												}
											
													$item                           = new CommissionWithdrawalTransaction;
													$item->commission_withdrawal_id = $com_withdrawal->id;
													$item->withdrawal_number        = $com_withdrawal->withdrawal_number;
													$item->loan_id                  = $loan->id;
													$item->saleperson_id            = Request::input('sale_person');
													$item->commission_rate          = $commissionRate->rate;
													$item->withdrawal_amount        = $withdrawal_amount_request;
													$item->total_customer_paid      = $total_customer_paid;
													$item->status                   = 'Withdrawal';
													$item->payment_status           = 'Due';
													$item->withdrawal_date          = date('Y-m-d');
													$item->requester                = Auth::user()->id;
													$item->noted                    = Request::input('noted');
													
													$item->save();
	
											}else{
												return redirect()->back()->with(['error' => "Sale team request don't have sufficient balance to withdraw commission"]);
											}
										}elseif($sale_person_request->lavel==2){
											$total_com_paid=CommissionWithdrawalTransaction::where('saleperson_id',$sale_person_request->id)->where('loan_id',$loan->id)->sum('received_amount');
											$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);
											
											if(floatval($loan->commission_value)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit not commission!"]);
											}
											
											if(floatval($loan->commission_approved)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit commission not yet approved!"]);
											}
											$commission_value=$loan->commission_value;
											if($loan->commission_type=='%'){
												$net_selling_price=0;
												$totalall_discount=floatval($loan->discount_promotion) + floatval($loan->discount_other) + floatval($loan->amount_discount_payment_option);
												$net_selling_price=floatval($loan->unit_sale_price) - floatval($totalall_discount); 
												$commission_value=floatval($net_selling_price)* floatval($loan->commission_value)/100;
											}
	
											$total_com=floatval($commission_value) *floatval($sale_person_request->com_rate)/100;
											$withdrawal_amount=$total_commission_tobe_pay; 
											$balance=floatval($total_com)-floatval($total_com_paid);
											$withdrawal_amount_request=0;

											if(floatval($withdrawal_amount)>0 && floatval($balance)>0){
												if($withdrawal_amount>$balance){
												$withdrawal_amount_request=$balance;
												}else{
													$withdrawal_amount_request=$withdrawal_amount;
												}

												
													$item                           = new CommissionWithdrawalTransaction;
													$item->commission_withdrawal_id = $com_withdrawal->id;
													$item->withdrawal_number        = $com_withdrawal->withdrawal_number;
													$item->loan_id                  = $loan->id;
													$item->saleperson_id            = Request::input('sale_person');
													$item->commission_rate          = $commissionRate->rate;
													$item->withdrawal_amount        = $withdrawal_amount_request;
													$item->total_customer_paid      = $total_customer_paid;
													$item->status                   = 'Withdrawal';
													$item->payment_status           = 'Due';
													$item->withdrawal_date          = date('Y-m-d');
													$item->requester                = Auth::user()->id;
													$item->noted                    = Request::input('noted');

													$item->save();
	
											}else{
												
													return redirect()->back()->with(['error' => "Sale team request don't have sufficient balance to withdraw commission"]);
												
											}
										}
									}elseif($sale_person->lavel==3){
										$sale_person_request = SalePerson::where('active',1)->where('id',Request::input('sale_person'))->first();
										if($sale_person_request->lavel==1){
											$parentl2 = SalePerson::where('active',1)->where('id',$sale_person->parent_id)->first();
											$total_com_paid=CommissionWithdrawalTransaction::where('saleperson_id',$sale_person_request->id)->where('loan_id',$loan->id)->sum('received_amount');
											$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);										
											
											if(floatval($loan->commission_value)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit not commission!"]);
											}
											
											if(floatval($loan->commission_approved)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit commission not yet approved!"]);
											}
											$commission_value=$loan->commission_value;
											if($loan->commission_type=='%'){
												$net_selling_price=0;
												$totalall_discount=floatval($loan->discount_promotion) + floatval($loan->discount_other) + floatval($loan->amount_discount_payment_option);
												$net_selling_price=floatval($loan->unit_sale_price) - floatval($totalall_discount); 
												$commission_value=floatval($net_selling_price)* floatval($loan->commission_value)/100;
											}
	
											$total_com=floatval($commission_value) *(floatval($sale_person_request->com_rate)-floatval($parentl2->com_rate))/100;
											$withdrawal_amount=$total_commission_tobe_pay; 
											$balance=floatval($total_com)-floatval($total_com_paid);
											$withdrawal_amount_request=0;
											if(floatval($withdrawal_amount)>0 && floatval($balance)>0){
												if($withdrawal_amount>$balance){
												$withdrawal_amount_request=$balance;
												}else{
													$withdrawal_amount_request=$withdrawal_amount;
												}
											
													$item                           = new CommissionWithdrawalTransaction;
													$item->commission_withdrawal_id = $com_withdrawal->id;
													$item->withdrawal_number        = $com_withdrawal->withdrawal_number;
													$item->loan_id                  = $loan->id;
													$item->saleperson_id            = Request::input('sale_person');
													$item->commission_rate          = $commissionRate->rate;
													$item->withdrawal_amount        = $withdrawal_amount_request;
													$item->total_customer_paid      = $total_customer_paid;
													$item->status                   = 'Withdrawal';
													$item->payment_status           = 'Due';
													$item->withdrawal_date          = date('Y-m-d');
													$item->requester                = Auth::user()->id;
													$item->noted                    = Request::input('noted');
													
													$item->save();
	
											}else{
												return redirect()->back()->with(['error' => "Sale team request don't have sufficient balance to withdraw commission"]);
											}
										}elseif($sale_person_request->lavel==2){
											$total_com_paid=CommissionWithdrawalTransaction::where('saleperson_id',$sale_person_request->id)->where('loan_id',$loan->id)->sum('received_amount');
											$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);
											
											if(floatval($loan->commission_value)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit not commission!"]);
											}
											
											if(floatval($loan->commission_approved)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit commission not yet approved!"]);
											}
											$commission_value=$loan->commission_value;
											if($loan->commission_type=='%'){
												$net_selling_price=0;
												$totalall_discount=floatval($loan->discount_promotion) + floatval($loan->discount_other) + floatval($loan->amount_discount_payment_option);
												$net_selling_price=floatval($loan->unit_sale_price) - floatval($totalall_discount); 
												$commission_value=floatval($net_selling_price)* floatval($loan->commission_value)/100;
											}
	
											$total_com=floatval($commission_value) *(floatval($sale_person_request->com_rate)-floatval($sale_person->com_rate))/100;
											$withdrawal_amount=$total_commission_tobe_pay; 
											$balance=floatval($total_com)-floatval($total_com_paid);
											$withdrawal_amount_request=0;
											if(floatval($withdrawal_amount)>0 && floatval($balance)>0){
												if($withdrawal_amount>$balance){
												$withdrawal_amount_request=$balance;
												}else{
													$withdrawal_amount_request=$withdrawal_amount;
												}
											
													$item                           = new CommissionWithdrawalTransaction;
													$item->commission_withdrawal_id = $com_withdrawal->id;
													$item->withdrawal_number        = $com_withdrawal->withdrawal_number;
													$item->loan_id                  = $loan->id;
													$item->saleperson_id            = Request::input('sale_person');
													$item->commission_rate          = $commissionRate->rate;
													$item->withdrawal_amount        = $withdrawal_amount_request;
													$item->total_customer_paid      = $total_customer_paid;
													$item->status                   = 'Withdrawal';
													$item->payment_status           = 'Due';
													$item->withdrawal_date          = date('Y-m-d');
													$item->requester                = Auth::user()->id;
													$item->noted                    = Request::input('noted');
													
													$item->save();
	
											}else{
												return redirect()->back()->with(['error' => "Sale team request don't have sufficient balance to withdraw commission"]);
											}
										}elseif($sale_person_request->lavel==3){
											$total_com_paid=CommissionWithdrawalTransaction::where('saleperson_id',$sale_person_request->id)->where('loan_id',$loan->id)->sum('received_amount');
											$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);
											

											if(floatval($loan->commission_value)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit not commission!"]);
											}
											
											if(floatval($loan->commission_approved)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit commission not yet approved!"]);
											}
											$commission_value=$loan->commission_value;
											if($loan->commission_type=='%'){
												$net_selling_price=0;
												$totalall_discount=floatval($loan->discount_promotion) + floatval($loan->discount_other) + floatval($loan->amount_discount_payment_option);
												$net_selling_price=floatval($loan->unit_sale_price) - floatval($totalall_discount); 
												$commission_value=floatval($net_selling_price)* floatval($loan->commission_value)/100;
											}
	
											$total_com=floatval($commission_value) * floatval($sale_person_request->com_rate) /100;
											$withdrawal_amount=$total_commission_tobe_pay; 
											$balance=floatval($total_com)-floatval($total_com_paid);
											$withdrawal_amount_request=0;
											if(floatval($withdrawal_amount)>0 && floatval($balance)>0){
												if($withdrawal_amount>$balance){
												$withdrawal_amount_request=$balance;
												}else{
													$withdrawal_amount_request=$withdrawal_amount;
												}
											
													$item                           = new CommissionWithdrawalTransaction;
													$item->commission_withdrawal_id = $com_withdrawal->id;
													$item->withdrawal_number        = $com_withdrawal->withdrawal_number;
													$item->loan_id                  = $loan->id;
													$item->saleperson_id            = Request::input('sale_person');
													$item->commission_rate          = $commissionRate->rate;
													$item->withdrawal_amount        = $withdrawal_amount_request;
													$item->total_customer_paid      = $total_customer_paid;
													$item->status                   = 'Withdrawal';
													$item->payment_status           = 'Due';
													$item->withdrawal_date          = date('Y-m-d');
													$item->requester                = Auth::user()->id;
													$item->noted                    = Request::input('noted');
													
													$item->save();
	
											}else{
												return redirect()->back()->with(['error' => "Sale team request don't have sufficient balance to withdraw commission"]);
											}
										}			
								}
	
								$sale = Sale::where('id',$loan->sale_id)->first();
								if (!empty($sale)) {		
										$sale->commission_status = 'Withdrawal';
									$sale->save();								
								}
						
							}
						}
					}

				}									
			DB::commit();
			return redirect()->back();
				
			} catch (Exception $e) {
				//DB::rollBack();
				throw $e;
			}
		}

	return redirect()->back();
	}
	public function postRequestAllWithdrawCommissionPrev() {
		$data = Request::all();
		$rules = [
			'company' => 'required',
			'project_id' => 'required',
			'unit_type_id' => 'required',
			'sale_person' => 'required',
		];
		$validator = Validator::make($data, $rules);

		if ($validator->fails()) {
			return redirect()->back()->with(['error' => 'Failed to Created']);
		}else{
			DB::beginTransaction();
			try {
				$commissionRate = CommissionRate::first();	

				$com_withdrawal = new CommissionWithdrawal;
				$com_withdrawal->withdrawal_number ='RE-'.date('dmY').'-'.rand(10,100).'-'.Request::input('sale_person');
				$com_withdrawal->company_id = Request::input('company');
				$com_withdrawal->project_id = Request::input('project_id');
				$com_withdrawal->unit_type_id = Request::input('unit_type_id');
				$com_withdrawal->saleperson_id = Request::input('sale_person');			

				if(Request::has('t_from')){  
					$start_date = date('Y-m-d',strtotime(Request::input('t_from')));                 
					$com_withdrawal->t_from = $start_date;
				}
				if(Request::has('t_to')){     
					$to_date = date('Y-m-d',strtotime(Request::input('t_to')));              
					$com_withdrawal->t_to = $to_date;
				}

				$com_withdrawal->commission_rate =$commissionRate->rate;
				$com_withdrawal->status = 'Withdrawal';
				$com_withdrawal->payment_status ='Due';
				$com_withdrawal->withdrawal_date = date('Y-m-d');
				$com_withdrawal->requester = Auth::user()->id;
				$com_withdrawal->noted = Request::input('noted');
				if($com_withdrawal->save()){
					$comission_request=Request::input('comission_request');		
					if(count($comission_request)>0){
						$B0 = new Loan();
						foreach ($comission_request as $loan_id) {
							$loan = $B0->selectRaw('
									tb_loans.id,								
									tb_loans.loan_account_id,
									tb_loans.drawdown_acc,	
									tb_loans.discount_promotion,
									tb_loans.discount_other,
									tb_loans.amount_discount_payment_option,
									tb_loans.unit_sale_price,
									tb_units.commission_type,
									tb_units.commission_value,
									tb_units.commission_approved,								
									tb_loans.co,								
									tb_loans.sale_person,								
									tb_drawdown_account.coa_id
								'
								)
							->join('drawdown_account','drawdown_account.account_no','=','loans.drawdown_acc') 
							->leftJoin('client_loan_accounts','loans.loan_account_id','=','client_loan_accounts.id')
							->leftJoin('units','units.id','=','loans.unit_id')
							->where('loans.id',$loan_id)
							->with([
								'coa_journal_detail' => function ($query) {
									$query->select('*', 'journal_requiry.entry_date', 'journal_requiry.description')
									->join('journal_requiry', 'journal_requiry.id', '=', 'journal_detail.journal_id')
									->orderBy('journal_requiry.entry_date', 'asc')
									->where('journal_detail.is_audit', '=', 1)
									->where('journal_detail.credit', '>', 0);
									
									if(Request::has('t_from')){  
										$start_date = date('Y-m-d H:i:s',strtotime(Request::input('t_from')));                 
										$query = $query->whereDate('journal_requiry.entry_date','>=', $start_date);
									}
									if(Request::has('t_to')){     
										$to_date = date('Y-m-d H:i:s',strtotime(Request::input('t_to')));              
										$query = $query->whereDate('journal_requiry.entry_date','<=', $to_date);
									}
								},  
								'commission_withdrawal_transaction' => function ($query) {
									$query->select('*')->orderBy('withdrawal_date','desc');
								}]
							)->first();	
			
							$sale_person = SalePerson::where('active',1)->where('id',$loan->sale_person)->first();
							$sale_team_commission = [];
							if($sale_person){
								$total_customer_paid=$loan->coa_journal_detail->sum('credit');
								$grand_total_com_paid=$loan->commission_withdrawal_transaction->sum('received_amount');						
									
									if($sale_person->lavel==1){
										$sale_person_request = SalePerson::where('active',1)->where('id',Request::input('sale_person'))->first();
										$total_com_paid=CommissionWithdrawalTransaction::where('saleperson_id',$sale_person_request->id)->where('loan_id',$loan->id)->sum('received_amount');
										$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);
	
										if(floatval($loan->commission_value)<=0){
											return redirect()->back()->with(['error' => "Cannot request withdraw with unit not commission!"]);
										}
										
										if(floatval($loan->commission_approved)<=0){
											return redirect()->back()->with(['error' => "Cannot request withdraw with unit commission not yet approved!"]);
										}
										$commission_value=$loan->commission_value;
										if($loan->commission_type=='%'){
											$net_selling_price=0;
											$totalall_discount=floatval($loan->discount_promotion) + floatval($loan->discount_other) + floatval($loan->amount_discount_payment_option);
											$net_selling_price=floatval($loan->unit_sale_price) - floatval($totalall_discount); 
											$commission_value=floatval($net_selling_price)* floatval($loan->commission_value)/100;
										}
										$total_com=floatval($commission_value) *floatval($sale_person_request->com_rate)/100;
										$withdrawal_amount=$total_commission_tobe_pay; 
										$balance=floatval($total_com)-floatval($total_com_paid);
										$withdrawal_amount_request=0;
										if(floatval($withdrawal_amount)>0 && floatval($balance)>0){
											if($withdrawal_amount>$balance){
											$withdrawal_amount_request=$balance;
											}else{
												$withdrawal_amount_request=$withdrawal_amount;
											}
										
												$item = new CommissionWithdrawalTransaction;												
												$item->commission_withdrawal_id=$com_withdrawal->id;
												$item->withdrawal_number=$com_withdrawal->withdrawal_number;
												$item->loan_id = $loan->id;
												$item->saleperson_id = Request::input('sale_person');
												$item->commission_rate =$commissionRate->rate;
												$item->withdrawal_amount = $withdrawal_amount_request;
												$item->total_customer_paid=$total_customer_paid;
												$item->total_customer_paid=$total_customer_paid;
												$item->status = 'Withdrawal';
												$item->payment_status ='Due';
												$item->withdrawal_date = date('Y-m-d');
												$item->requester = Auth::user()->id;
												$item->noted = Request::input('noted');											
												
												$item->save();
	
										}else{
											return redirect()->back()->with(['error' => "Sale team request don't have sufficient balance to withdraw commission"]);
										}
				
				
									}elseif($sale_person->lavel==2){
										$sale_person_request = SalePerson::where('active',1)->where('id',Request::input('sale_person'))->first();
										if($sale_person_request->lavel==1){										
											$total_com_paid=CommissionWithdrawalTransaction::where('saleperson_id',$sale_person_request->id)->where('loan_id',$loan->id)->sum('received_amount');
											$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);										
											
											if(floatval($loan->commission_value)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit not commission!"]);
											}
											
											if(floatval($loan->commission_approved)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit commission not yet approved!"]);
											}
											$commission_value=$loan->commission_value;
											if($loan->commission_type=='%'){
												$net_selling_price=0;
												$totalall_discount=floatval($loan->discount_promotion) + floatval($loan->discount_other) + floatval($loan->amount_discount_payment_option);
												$net_selling_price=floatval($loan->unit_sale_price) - floatval($totalall_discount); 
												$commission_value=floatval($net_selling_price)* floatval($loan->commission_value)/100;
											}
	
											$total_com=floatval($commission_value) *(floatval($sale_person_request->com_rate)-floatval($sale_person->com_rate))/100;
											$withdrawal_amount=$total_commission_tobe_pay; 
											$balance=floatval($total_com)-floatval($total_com_paid);
											$withdrawal_amount_request=0;
											if(floatval($withdrawal_amount)>0 && floatval($balance)>0){
												if($withdrawal_amount>$balance){
												$withdrawal_amount_request=$balance;
												}else{
													$withdrawal_amount_request=$withdrawal_amount;
												}
											
													$item = new CommissionWithdrawalTransaction;
													$item->commission_withdrawal_id=$com_withdrawal->id;
													$item->withdrawal_number=$com_withdrawal->withdrawal_number;
													$item->loan_id = $loan->id;
													$item->saleperson_id = Request::input('sale_person');
													$item->commission_rate =$commissionRate->rate;
													$item->withdrawal_amount = $withdrawal_amount_request;
													$item->total_customer_paid=$total_customer_paid;
													$item->status = 'Withdrawal';
													$item->payment_status ='Due';
													$item->withdrawal_date = date('Y-m-d');
													$item->requester = Auth::user()->id;
													$item->noted = Request::input('noted');													
													
													$item->save();
	
											}else{
												return redirect()->back()->with(['error' => "Sale team request don't have sufficient balance to withdraw commission"]);
											}
										}elseif($sale_person_request->lavel==2){
											$total_com_paid=CommissionWithdrawalTransaction::where('saleperson_id',$sale_person_request->id)->where('loan_id',$loan->id)->sum('received_amount');
											$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);
											
											if(floatval($loan->commission_value)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit not commission!"]);
											}
											
											if(floatval($loan->commission_approved)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit commission not yet approved!"]);
											}
											$commission_value=$loan->commission_value;
											if($loan->commission_type=='%'){
												$net_selling_price=0;
												$totalall_discount=floatval($loan->discount_promotion) + floatval($loan->discount_other) + floatval($loan->amount_discount_payment_option);
												$net_selling_price=floatval($loan->unit_sale_price) - floatval($totalall_discount); 
												$commission_value=floatval($net_selling_price)* floatval($loan->commission_value)/100;
											}
	
											$total_com=floatval($commission_value) *floatval($sale_person_request->com_rate)/100;
											$withdrawal_amount=$total_commission_tobe_pay; 
											$balance=floatval($total_com)-floatval($total_com_paid);
											$withdrawal_amount_request=0;

											if(floatval($withdrawal_amount)>0 && floatval($balance)>0){
												if($withdrawal_amount>$balance){
												$withdrawal_amount_request=$balance;
												}else{
													$withdrawal_amount_request=$withdrawal_amount;
												}

												
													$item = new CommissionWithdrawalTransaction;
													$item->commission_withdrawal_id=$com_withdrawal->id;
													$item->withdrawal_number=$com_withdrawal->withdrawal_number;
													$item->loan_id = $loan->id;
													$item->saleperson_id = Request::input('sale_person');
													$item->commission_rate =$commissionRate->rate;
													$item->withdrawal_amount = $withdrawal_amount_request;
													$item->total_customer_paid=$total_customer_paid;
													$item->status = 'Withdrawal';
													$item->payment_status ='Due';
													$item->withdrawal_date = date('Y-m-d');
													$item->requester = Auth::user()->id;
													$item->noted = Request::input('noted');										

													$item->save();
	
											}else{
												
													return redirect()->back()->with(['error' => "Sale team request don't have sufficient balance to withdraw commission"]);
												
											}
										}
									}elseif($sale_person->lavel==3){
										$sale_person_request = SalePerson::where('active',1)->where('id',Request::input('sale_person'))->first();
										if($sale_person_request->lavel==1){
											$parentl2 = SalePerson::where('active',1)->where('id',$sale_person->parent_id)->first();
											$total_com_paid=CommissionWithdrawalTransaction::where('saleperson_id',$sale_person_request->id)->where('loan_id',$loan->id)->sum('received_amount');
											$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);										
											
											if(floatval($loan->commission_value)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit not commission!"]);
											}
											
											if(floatval($loan->commission_approved)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit commission not yet approved!"]);
											}
											$commission_value=$loan->commission_value;
											if($loan->commission_type=='%'){
												$net_selling_price=0;
												$totalall_discount=floatval($loan->discount_promotion) + floatval($loan->discount_other) + floatval($loan->amount_discount_payment_option);
												$net_selling_price=floatval($loan->unit_sale_price) - floatval($totalall_discount); 
												$commission_value=floatval($net_selling_price)* floatval($loan->commission_value)/100;
											}
	
											$total_com=floatval($commission_value) *(floatval($sale_person_request->com_rate)-floatval($parentl2->com_rate))/100;
											$withdrawal_amount=$total_commission_tobe_pay; 
											$balance=floatval($total_com)-floatval($total_com_paid);
											$withdrawal_amount_request=0;
											if(floatval($withdrawal_amount)>0 && floatval($balance)>0){
												if($withdrawal_amount>$balance){
												$withdrawal_amount_request=$balance;
												}else{
													$withdrawal_amount_request=$withdrawal_amount;
												}
											
													$item = new CommissionWithdrawalTransaction;
													$item->commission_withdrawal_id=$com_withdrawal->id;
													$item->withdrawal_number=$com_withdrawal->withdrawal_number;
													$item->loan_id = $loan->id;
													$item->saleperson_id = Request::input('sale_person');
													$item->commission_rate =$commissionRate->rate;
													$item->withdrawal_amount = $withdrawal_amount_request;
													$item->total_customer_paid=$total_customer_paid;
													$item->status = 'Withdrawal';
													$item->payment_status ='Due';
													$item->withdrawal_date = date('Y-m-d');
													$item->requester = Auth::user()->id;
													$item->noted = Request::input('noted');													
													
													$item->save();
	
											}else{
												return redirect()->back()->with(['error' => "Sale team request don't have sufficient balance to withdraw commission"]);
											}
										}elseif($sale_person_request->lavel==2){
											$total_com_paid=CommissionWithdrawalTransaction::where('saleperson_id',$sale_person_request->id)->where('loan_id',$loan->id)->sum('received_amount');
											$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);
											
											if(floatval($loan->commission_value)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit not commission!"]);
											}
											
											if(floatval($loan->commission_approved)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit commission not yet approved!"]);
											}
											$commission_value=$loan->commission_value;
											if($loan->commission_type=='%'){
												$net_selling_price=0;
												$totalall_discount=floatval($loan->discount_promotion) + floatval($loan->discount_other) + floatval($loan->amount_discount_payment_option);
												$net_selling_price=floatval($loan->unit_sale_price) - floatval($totalall_discount); 
												$commission_value=floatval($net_selling_price)* floatval($loan->commission_value)/100;
											}
	
											$total_com=floatval($commission_value) *(floatval($sale_person_request->com_rate)-floatval($sale_person->com_rate))/100;
											$withdrawal_amount=$total_commission_tobe_pay; 
											$balance=floatval($total_com)-floatval($total_com_paid);
											$withdrawal_amount_request=0;
											if(floatval($withdrawal_amount)>0 && floatval($balance)>0){
												if($withdrawal_amount>$balance){
												$withdrawal_amount_request=$balance;
												}else{
													$withdrawal_amount_request=$withdrawal_amount;
												}
											
													$item = new CommissionWithdrawalTransaction;
													$item->commission_withdrawal_id=$com_withdrawal->id;
													$item->withdrawal_number=$com_withdrawal->withdrawal_number;
													$item->loan_id = $loan->id;
													$item->saleperson_id = Request::input('sale_person');
													$item->commission_rate =$commissionRate->rate;
													$item->withdrawal_amount = $withdrawal_amount_request;
													$item->total_customer_paid=$total_customer_paid;
													$item->status = 'Withdrawal';
													$item->payment_status ='Due';
													$item->withdrawal_date = date('Y-m-d');
													$item->requester = Auth::user()->id;
													$item->noted = Request::input('noted');													
													
													$item->save();
	
											}else{
												return redirect()->back()->with(['error' => "Sale team request don't have sufficient balance to withdraw commission"]);
											}
										}elseif($sale_person_request->lavel==3){
											$total_com_paid=CommissionWithdrawalTransaction::where('saleperson_id',$sale_person_request->id)->where('loan_id',$loan->id)->sum('received_amount');
											$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);
											

											if(floatval($loan->commission_value)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit not commission!"]);
											}
											
											if(floatval($loan->commission_approved)<=0){
												return redirect()->back()->with(['error' => "Cannot request withdraw with unit commission not yet approved!"]);
											}
											$commission_value=$loan->commission_value;
											if($loan->commission_type=='%'){
												$net_selling_price=0;
												$totalall_discount=floatval($loan->discount_promotion) + floatval($loan->discount_other) + floatval($loan->amount_discount_payment_option);
												$net_selling_price=floatval($loan->unit_sale_price) - floatval($totalall_discount); 
												$commission_value=floatval($net_selling_price)* floatval($loan->commission_value)/100;
											}
	
											$total_com=floatval($commission_value) * floatval($sale_person_request->com_rate) /100;
											$withdrawal_amount=$total_commission_tobe_pay; 
											$balance=floatval($total_com)-floatval($total_com_paid);
											$withdrawal_amount_request=0;
											if(floatval($withdrawal_amount)>0 && floatval($balance)>0){
												if($withdrawal_amount>$balance){
												$withdrawal_amount_request=$balance;
												}else{
													$withdrawal_amount_request=$withdrawal_amount;
												}
											
													$item = new CommissionWithdrawalTransaction;
													$item->commission_withdrawal_id=$com_withdrawal->id;
													$item->withdrawal_number=$com_withdrawal->withdrawal_number;
													$item->loan_id = $loan->id;
													$item->saleperson_id = Request::input('sale_person');
													$item->commission_rate =$commissionRate->rate;
													$item->withdrawal_amount = $withdrawal_amount_request;
													$item->total_customer_paid=$total_customer_paid;
													$item->status = 'Withdrawal';
													$item->payment_status ='Due';
													$item->withdrawal_date = date('Y-m-d');
													$item->requester = Auth::user()->id;
													$item->noted = Request::input('noted');													
													
													$item->save();
	
											}else{
												return redirect()->back()->with(['error' => "Sale team request don't have sufficient balance to withdraw commission"]);
											}
										}			
								}
	
								$sale = Sale::where('id',$loan->sale_id)->first();
								if (!empty($sale)) {		
										$sale->commission_status = 'Withdrawal';
									$sale->save();								
								}
						
							}
						}
					}

				}									
			DB::commit();
			return redirect()->back();
				
			} catch (Exception $e) {
				//DB::rollBack();
				throw $e;
			}
		}

	return redirect()->back();
	}


	public function getRequestWithdrawCommissionList() {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 50;
        if(!$offset){
        	$offset = 50;
        }
        $query_arr = array('user_id'=>Auth::user()->id, 'branch_id'=>Auth::user()->branch_id, 'role_id'=>Auth::user()->role_id);

		$B0 = new CommissionWithdrawal();
		$com = $B0->selectRaw('
			           
					tb_commission_withdrawal.id,
					tb_commission_withdrawal.withdrawal_number,
					tb_commission_withdrawal.company_id,
					tb_commission_withdrawal.project_id,
					tb_commission_withdrawal.unit_type_id,					
					tb_commission_withdrawal.saleperson_id,
					tb_commission_withdrawal.commission_rate,
					tb_commission_withdrawal.withdrawal_date,
					tb_commission_withdrawal.status,
					tb_commission_withdrawal.payment_status,
					tb_commission_withdrawal.requester,
					tb_commission_withdrawal.sales_manager_approval,
					tb_commission_withdrawal.accountant_approval,
					tb_commission_withdrawal.hof_approval,
					tb_commission_withdrawal.chairman_approval,
					tb_commission_withdrawal.paid_by,
					tb_commission_withdrawal.noted,
					tb_company_branch.short_name as company,
					tb_projects.short_code as project,
					tb_unit_types.name as unit_type,
					tb_saleperson.name as sale_person
		')->with([
			'commissionWithdrawalTransaction'
			]
		)		
		->join('projects','projects.id','=','commission_withdrawal.project_id')
		->join('company_branch','company_branch.id','=','commission_withdrawal.company_id')
		->join('unit_types','unit_types.id','=','commission_withdrawal.unit_type_id')
		->join('saleperson','saleperson.id','=','commission_withdrawal.saleperson_id')
		->where('commission_withdrawal.status','Withdrawal')
		->orderBy('commission_withdrawal.id', 'desc');


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
            $com = $com->where('commission_withdrawal.project_id',$project_id);
        }
        $unit_type_id = null;
        if(Request::has('unit_type_id')){
            $unit_type_id = Request::input('unit_type_id');
            $unit_type = UnitType::find($unit_type_id);
            $com = $com->where('commission_withdrawal.unit_type_id',$unit_type_id);
        }
        $unit_id = null;

        $sale_person = null;
        if(Request::has('sale_person')){
            $sale_person = Request::input('sale_person');
            $sale_person_list = SalePerson::find($sale_person);
            $com = $com->where('commission_withdrawal.saleperson_id',$sale_person);
            $offset=10000;
        }

		if(Request::get('search')){
            $com = $com->where('commission_withdrawal.withdrawal_number','like','%'.Request::get('search').'%');
        }

        $saleRepresentative = SalePerson::where('active',1)->select('*')->get();


       $listSale = $com->paginate($offset)->setPath('list_request_withdraw_commission?company='.$company.'&project_id='.$project_id.'&status='.$status.'&search='.$search.'&offset='.$offset);
//   dd($listSale);
		$contract_template = config('static_data.contract_template');
        return $this->view('sale.list_request_withdraw_commission', ['lists' => $listSale, 'company' => $company,'offset'=>$offset,'contract_template' => $contract_template],compact('projects','project_id','unit_type_id','client','from_date','to_date','company','status','sale_person_list','saleRepresentative','sale_person'));

    }

	public function getCommissionSettingList() {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 50;
        if(!$offset){
        	$offset = 50;
        }
        $query_arr = array('user_id'=>Auth::user()->id, 'branch_id'=>Auth::user()->branch_id, 'role_id'=>Auth::user()->role_id);

		UnitType::join('sale_commission_setting', 'sale_commission_setting.unit_type_id', '=', 'unit_types.id')->where('sale_commission_setting.approval','pending')->count();

		$B0 = new UnitType();
		$list = $B0->selectRaw('
			           
					tb_unit_types.id,
					tb_sale_commission_setting.commission_type,
					tb_sale_commission_setting.commission_value,
					tb_sale_commission_setting.commission_approved,
					tb_sale_commission_setting.commission_approve_by,					
					tb_sale_commission_setting.start_date,
					tb_sale_commission_setting.end_date,
					tb_sale_commission_setting.is_active,
					tb_sale_commission_setting.approval,	
					tb_company_branch.short_name as company,				
					tb_projects.short_code as project,
					tb_unit_types.name as unit_type
		')	
		->join('projects','projects.id','=','unit_types.project_id')
		->join('company_branch','company_branch.id','=','projects.company_id')
		->join('sale_commission_setting','sale_commission_setting.unit_type_id','=','unit_types.id')
		->where('sale_commission_setting.is_active',1)
		->where('sale_commission_setting.approval','pending')
		;


		$client = null;
        $query_url = [];
        $status = '';
        $company=null;
        if(Request::get('company')){
            $list = $list->where('projects.company_id',Request::get('company'));
            $company = CompanyBranch::find(Request::get('company'));
        }
        $project_id = null;
        if(Request::has('project_id')){
            $project_id = Request::input('project_id');
            $project = Project::find($project_id);
            $list = $list->where('unit_types.project_id',$project_id);
        }
        $unit_type_id = null;
        if(Request::has('unit_type_id')){
            $unit_type_id = Request::input('unit_type_id');
            $unit_type = UnitType::find($unit_type_id);
            $list = $list->where('unit_types.id',$unit_type_id);
        }
        $unit_id = null;


		if(Request::get('search')){
            $list = $list->where('unit_types.name','like','%'.Request::get('search').'%');
        }


       $listSale = $list->paginate($offset)->setPath('sale_commission_setting_list?company='.$company.'&project_id='.$project_id.'&status='.$status.'&search='.$search.'&offset='.$offset);
//   dd($listSale);
		$contract_template = config('static_data.contract_template');
        return $this->view('sale.sale_commission_setting_list', ['lists' => $listSale, 'offset'=>$offset,'contract_template' => $contract_template],compact('projects','project_id','unit_type_id','company','client','from_date','to_date','status'));

    }

	public function getCommissionSettingSendBackList() {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 50;
        if(!$offset){
        	$offset = 50;
        }
        $query_arr = array('user_id'=>Auth::user()->id, 'branch_id'=>Auth::user()->branch_id, 'role_id'=>Auth::user()->role_id);

		UnitType::join('sale_commission_setting', 'sale_commission_setting.unit_type_id', '=', 'unit_types.id')->where('sale_commission_setting.approval','pending')->count();

		$B0 = new UnitType();
		$list = $B0->selectRaw('
			           
					tb_unit_types.id,
					tb_sale_commission_setting.commission_type,
					tb_sale_commission_setting.commission_value,
					tb_sale_commission_setting.commission_approved,
					tb_sale_commission_setting.commission_approve_by,					
					tb_sale_commission_setting.start_date,
					tb_sale_commission_setting.end_date,
					tb_sale_commission_setting.is_active,
					tb_sale_commission_setting.approval,	
					tb_company_branch.short_name as company,				
					tb_projects.short_code as project,
					tb_unit_types.name as unit_type
		')	
		->join('projects','projects.id','=','unit_types.project_id')
		->join('company_branch','company_branch.id','=','projects.company_id')
		->join('sale_commission_setting','sale_commission_setting.unit_type_id','=','unit_types.id')
		->where('sale_commission_setting.is_active',1)
		->where('sale_commission_setting.approval','send_back')
		;


		$client = null;
        $query_url = [];
        $status = '';
        $company=null;
        if(Request::get('company')){
            $list = $list->where('projects.company_id',Request::get('company'));
            $company = CompanyBranch::find(Request::get('company'));
        }
        $project_id = null;
        if(Request::has('project_id')){
            $project_id = Request::input('project_id');
            $project = Project::find($project_id);
            $list = $list->where('unit_types.project_id',$project_id);
        }
        $unit_type_id = null;
        if(Request::has('unit_type_id')){
            $unit_type_id = Request::input('unit_type_id');
            $unit_type = UnitType::find($unit_type_id);
            $list = $list->where('unit_types.id',$unit_type_id);
        }
        $unit_id = null;


		if(Request::get('search')){
            $list = $list->where('unit_types.name','like','%'.Request::get('search').'%');
        }


       $listSale = $list->paginate($offset)->setPath('sale_commission_setting_list?company='.$company.'&project_id='.$project_id.'&status='.$status.'&search='.$search.'&offset='.$offset);
//   dd($listSale);
		$contract_template = config('static_data.contract_template');
        return $this->view('sale.sale_commission_setting_send_back_list', ['lists' => $listSale, 'offset'=>$offset,'contract_template' => $contract_template],compact('projects','project_id','unit_type_id','company','client','from_date','to_date','status'));

    }

	public function SaleCommissionWithdrawList() {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 50;
        if(!$offset){
        	$offset = 50;
        }
        $query_arr = array('user_id'=>Auth::user()->id, 'branch_id'=>Auth::user()->branch_id, 'role_id'=>Auth::user()->role_id);

		$B0 = new CommissionWithdrawal();
		$com = $B0->selectRaw('
			           
					tb_commission_withdrawal.id,
					tb_commission_withdrawal.withdrawal_number,
					tb_commission_withdrawal.company_id,
					tb_commission_withdrawal.project_id,
					tb_commission_withdrawal.unit_type_id,					
					tb_commission_withdrawal.saleperson_id,
					tb_commission_withdrawal.commission_rate,
					tb_commission_withdrawal.withdrawal_date,
					tb_commission_withdrawal.status,
					tb_commission_withdrawal.payment_status,
					tb_commission_withdrawal.requester,
					tb_commission_withdrawal.sales_manager_approval,
					tb_commission_withdrawal.accountant_approval,
					tb_commission_withdrawal.hof_approval,
					tb_commission_withdrawal.chairman_approval,
					tb_commission_withdrawal.paid_by,
					tb_commission_withdrawal.noted,
					tb_company_branch.short_name as company,
					tb_projects.short_code as project,
					tb_unit_types.name as unit_type,
					tb_saleperson.name as sale_person
		')->with([
			'commissionWithdrawalTransaction'
			]
		)		
		->join('projects','projects.id','=','commission_withdrawal.project_id')
		->join('company_branch','company_branch.id','=','commission_withdrawal.company_id')
		->join('unit_types','unit_types.id','=','commission_withdrawal.unit_type_id')
		->join('saleperson','saleperson.id','=','commission_withdrawal.saleperson_id')
		// ->where('commission_withdrawal.status','Withdrawal')
		->where('commission_withdrawal.payment_status','Due')
		->orderBy('commission_withdrawal.id', 'desc');


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
            $com = $com->where('commission_withdrawal.project_id',$project_id);
        }
        $unit_type_id = null;
        if(Request::has('unit_type_id')){
            $unit_type_id = Request::input('unit_type_id');
            $unit_type = UnitType::find($unit_type_id);
            $com = $com->where('commission_withdrawal.unit_type_id',$unit_type_id);
        }
        $unit_id = null;

        $sale_person = null;
        if(Request::has('sale_person')){
            $sale_person = Request::input('sale_person');
            $sale_person_list = SalePerson::find($sale_person);
            $com = $com->where('commission_withdrawal.saleperson_id',$sale_person);
            $offset=10000;
        }

		if(Request::get('search')){
            $com = $com->where('commission_withdrawal.withdrawal_number','like','%'.Request::get('search').'%');
        }

        $saleRepresentative = SalePerson::where('active',1)->select('*')->get();


       $listSale = $com->paginate($offset)->setPath('list_request_withdraw_commission?company='.$company.'&project_id='.$project_id.'&status='.$status.'&search='.$search.'&offset='.$offset);
//   dd($listSale);
		$contract_template = config('static_data.contract_template');
        return $this->view('sale.list_request_withdraw_commission', ['lists' => $listSale, 'company' => $company,'offset'=>$offset,'contract_template' => $contract_template],compact('projects','project_id','unit_type_id','client','from_date','to_date','company','status','sale_person_list','saleRepresentative','sale_person'));

    }

	public function SaleCommissionHistoryList() {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 50;
        if(!$offset){
        	$offset = 50;
        }
        $query_arr = array('user_id'=>Auth::user()->id, 'branch_id'=>Auth::user()->branch_id, 'role_id'=>Auth::user()->role_id);

		$B0 = new CommissionWithdrawal();
		$com = $B0->selectRaw('
			           
					tb_commission_withdrawal.id,
					tb_commission_withdrawal.withdrawal_number,
					tb_commission_withdrawal.company_id,
					tb_commission_withdrawal.project_id,
					tb_commission_withdrawal.unit_type_id,					
					tb_commission_withdrawal.saleperson_id,
					tb_commission_withdrawal.commission_rate,
					tb_commission_withdrawal.withdrawal_date,
					tb_commission_withdrawal.status,
					tb_commission_withdrawal.payment_status,
					tb_commission_withdrawal.requester,
					tb_commission_withdrawal.sales_manager_approval,
					tb_commission_withdrawal.accountant_approval,
					tb_commission_withdrawal.hof_approval,
					tb_commission_withdrawal.chairman_approval,
					tb_commission_withdrawal.paid_by,
					tb_commission_withdrawal.noted,
					tb_company_branch.short_name as company,
					tb_projects.short_code as project,
					tb_unit_types.name as unit_type,
					tb_saleperson.name as sale_person
		')->with([
			'commissionWithdrawalTransaction'
			]
		)		
		->join('projects','projects.id','=','commission_withdrawal.project_id')
		->join('company_branch','company_branch.id','=','commission_withdrawal.company_id')
		->join('unit_types','unit_types.id','=','commission_withdrawal.unit_type_id')
		->join('saleperson','saleperson.id','=','commission_withdrawal.saleperson_id')
		// ->where('commission_withdrawal.status','Withdrawal')
		->where('commission_withdrawal.payment_status','Piad')
		->orderBy('commission_withdrawal.id', 'desc');


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
            $com = $com->where('commission_withdrawal.project_id',$project_id);
        }
        $unit_type_id = null;
        if(Request::has('unit_type_id')){
            $unit_type_id = Request::input('unit_type_id');
            $unit_type = UnitType::find($unit_type_id);
            $com = $com->where('commission_withdrawal.unit_type_id',$unit_type_id);
        }
        $unit_id = null;

        $sale_person = null;
        if(Request::has('sale_person')){
            $sale_person = Request::input('sale_person');
            $sale_person_list = SalePerson::find($sale_person);
            $com = $com->where('commission_withdrawal.saleperson_id',$sale_person);
            $offset=10000;
        }

		if(Request::get('search')){
            $com = $com->where('commission_withdrawal.withdrawal_number','like','%'.Request::get('search').'%');
        }

        $saleRepresentative = SalePerson::where('active',1)->select('*')->get();


       $listSale = $com->paginate($offset)->setPath('list_request_withdraw_commission?company='.$company.'&project_id='.$project_id.'&status='.$status.'&search='.$search.'&offset='.$offset);
//   dd($listSale);
		$contract_template = config('static_data.contract_template');
        return $this->view('sale.list_request_withdraw_commission', ['lists' => $listSale, 'company' => $company,'offset'=>$offset,'contract_template' => $contract_template],compact('projects','project_id','unit_type_id','client','from_date','to_date','company','status','sale_person_list','saleRepresentative','sale_person'));

    }

	public function getRequestWithdrawCommissionDetail($com_withdrawal_id) {
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
		tb_commission_withdrawal_transaction.noted,
		tb_sale_order.final_price
		')->with([
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
		->leftJoin('sale_order','sale_order.id','=','client_loan_accounts.sale_id')
		->leftJoin('clients', 'clients.id', '=', 'loans.client_id')
		->leftJoin('projects','projects.id','=','loans.project_id')
		->leftJoin('company_branch','company_branch.id','=','projects.company_id')
		->leftJoin('unit_types','unit_types.id','=','loans.unit_type_id')
		->leftJoin('units','units.id','=','loans.unit_id')
		->where('commission_withdrawal_transaction.status','Withdrawal')
		->orderBy('commission_withdrawal_transaction.id', 'desc');


		$client = null;
        $query_url = [];
        $company=null;
        if(Request::get('company')){
            $loan = $loan->where('projects.company_id',Request::get('company'));
            $company = CompanyBranch::find(Request::get('company'));
        }
        $status = '';

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

        $sale_person = null;
        if(Request::has('sale_person')){
            $sale_person = Request::input('sale_person');
            $sale_person_list = SalePerson::find($sale_person);
            $loan = $loan->where('sale_order.sale_person',$sale_person);
            $offset=10000;
        }

        $saleRepresentative = SalePerson::where('active',1)->select('*')->get();


       $listSale = $loan->paginate($offset)->setPath('sale_report_detail?company='.$company.'&project_id='.$project_id.'&status='.$status.'&search='.$search.'&offset='.$offset);
//   dd($listSale);
		$contract_template = config('static_data.contract_template');
        return $this->view('sale.list_request_withdraw_commission', ['lists' => $listSale, 'company' => $company,'offset'=>$offset,'contract_template' => $contract_template],compact('projects','project_id','unit_type_id','client','from_date','to_date','company','status','sale_person_list','saleRepresentative','sale_person'));

    }


	public function allCommissionApprove() {
		$data = Request::all();

			DB::beginTransaction();
			try {

				if (!empty(Request::input('type'))) {
					$approve_types = trim(Request::input('type'));
					$commissionRate = CommissionRate::first();
					if($approve_types==='sm'){
						if(!Auth::user()->allow_approved_sm){
							return redirect()->back()->with(['error' => "Failed to Aproved! You don't have permission to approved for this option. Please contact your administrator."]);
						}
					}elseif($approve_types==='acc'){
						if(!Auth::user()->allow_approved_acc){
							return redirect()->back()->with(['error' => "Failed to Aproved! You don't have permission to approved for this option. Please contact your administrator."]);
						}
					}elseif($approve_types==='hof'){
							if(!Auth::user()->allow_approved_hof){
								return redirect()->back()->with(['error' => "Failed to Aproved! You don't have permission to approved for this option. Please contact your administrator."]);
							}
					}elseif($approve_types==='chairman'){
						if(!Auth::user()->allow_approved_chairman){
							return redirect()->back()->with(['error' => "Failed to Aproved! You don't have permission to approved for this option. Please contact your administrator."]);
						}
					}
						$comission_request=Request::input('comission_request');		
						if(count($comission_request)>0){
						foreach ($comission_request as $loan_id) {
							if ($loan_id > 0) {
								$item = CommissionWithdrawalTransaction::find($loan_id);
								if (!empty($item)) {
									$B0 = new Loan();
									$loan = $B0->selectRaw('tb_loans.id,tb_drawdown_account.coa_id'	)
									->join('drawdown_account','drawdown_account.account_no','=','loans.drawdown_acc') 
									->leftJoin('client_loan_accounts','loans.loan_account_id','=','client_loan_accounts.id')
									->join('currency','client_loan_accounts.currency','=','currency.id')
									->leftJoin('projects','projects.id','=','loans.project_id')
									->leftJoin('company_branch','company_branch.id','=','projects.company_id')
									->leftJoin('unit_types','unit_types.id','=','loans.unit_type_id')
									->leftJoin('units','units.id','=','loans.unit_id')
									->where('loans.id',$item->loan_id)
									->with([
										'coa_journal_detail' => function ($query) {
											$query->select('*', 'journal_requiry.entry_date', 'journal_requiry.description')
											->join('journal_requiry', 'journal_requiry.id', '=', 'journal_detail.journal_id')
											->orderBy('journal_requiry.entry_date', 'asc')
											->where('journal_detail.is_audit', '=', 1)
											->where('journal_detail.credit', '>', 0);
										},     
										'commission_withdrawal_transaction' => function ($query) {
											$query->select('*')->orderBy('withdrawal_date','desc');
										}]
									)->first();
									$total_customer_paid=$loan->coa_journal_detail->sum('credit');
									$grand_total_com_paid=$loan->commission_withdrawal_transaction->sum('received_amount');
									
									$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);
								   
									if(floatval($item->withdrawal_amount)>floatval($total_commission_tobe_pay)){
										return redirect()->back()->with(['error' => 'Failed to Aproved! Withdrawal amount is greater than commission to be pay.']);
									}
									
									if($approve_types==='sm'){
										$item->sales_manager_approval = Auth::user()->name;
									}elseif($approve_types==='acc'){
										$item->accountant_approval = Auth::user()->name;
									}elseif($approve_types==='hof'){
										$item->hof_approval = Auth::user()->name;
									}elseif($approve_types==='chairman'){
										$item->chairman_approval = Auth::user()->name;
										$item->status='Approved';
										$item->receipt_no = 'RP' . $id . date('y') . str_pad($no, 5, '0', STR_PAD_LEFT);
									}
									
									$item->save();
									
								}
							}
					

						}
						}else{
							return redirect()->back()->with(['error' => 'Failed to Aproved! Please select an item in the list.']);
						}

					
				}else{
					return redirect()->back()->with(['error' => 'Failed to Aproved! Please select an item in the list.']);
				}
			DB::commit();
			return redirect()->back();
				
			} catch (Exception $e) {
				//DB::rollBack();
				throw $e;
			}
		

	return redirect()->back();
}

public function allCommissionPrintOrMarkPayment() {
	$data = Request::all();

		DB::beginTransaction();
		try {

			if (!empty(Request::input('type'))) {
				$type = trim(Request::input('type'));
					$comission_request=Request::input('comission_request');		
					if(count($comission_request)>0){

						if($type==='printAll'){

							$items = [];

							foreach ($comission_request as $loan_id) {
								if ($loan_id > 0) {									
							
										$B0 = new Loan();
										$loan = $B0->selectRaw('tb_loans.id,tb_drawdown_account.coa_id'	)
										->join('drawdown_account','drawdown_account.account_no','=','loans.drawdown_acc') 
										->leftJoin('client_loan_accounts','loans.loan_account_id','=','client_loan_accounts.id')
										->join('currency','client_loan_accounts.currency','=','currency.id')
										->leftJoin('projects','projects.id','=','loans.project_id')
										->leftJoin('company_branch','company_branch.id','=','projects.company_id')
										->leftJoin('unit_types','unit_types.id','=','loans.unit_type_id')
										->leftJoin('units','units.id','=','loans.unit_id')
										->where('loans.id',$item->loan_id)
										->with([
											'coa_journal_detail' => function ($query) {
												$query->select('*', 'journal_requiry.entry_date', 'journal_requiry.description')
												->join('journal_requiry', 'journal_requiry.id', '=', 'journal_detail.journal_id')
												->orderBy('journal_requiry.entry_date', 'asc')
												->where('journal_detail.is_audit', '=', 1)
												->where('journal_detail.credit', '>', 0);
											},     
											'commission_withdrawal_transaction' => function ($query) {
												$query->select('*')->orderBy('withdrawal_date','desc');
											}]
										)->first();											
										array_push($items, $loan);								
								}			
		
							}
							return $this->view('sale.commission_receipt_all', [
								'items' => $items
							]);
						}elseif($type==='markPaymentAll'){
							foreach ($comission_request as $loan_id) {
								if ($loan_id > 0) {
									$item = CommissionWithdrawalTransaction::where('loan_id',$loan_id)->first();						
									if (!empty($item)) {
										$item->paid_by = Auth::user()->name;
										$item->received_amount = $item->withdrawal_amount;
										$item->payment_status='Piad';
										$item->noted = "Auto mark payment";	
										$item->save();
										
									}
									
									$B0 = new Loan();
									$loan = $B0->selectRaw('tb_loans.id,tb_drawdown_account.coa_id,tb_drawdown_account.sale_id,
									tb_units.commission_type,
									tb_units.commission_value,									
									tb_loans.unit_sale_price,
									tb_loans.amount_discount_payment_option,
									tb_loans.discount_payment_option,
									tb_loans.discount_other,
									tb_loans.discount_promotion            
									'
									)
									->join('drawdown_account','drawdown_account.account_no','=','loans.drawdown_acc') 
									->leftJoin('client_loan_accounts','loans.loan_account_id','=','client_loan_accounts.id')
									->leftJoin('units','units.id','=','loans.unit_id')
									->where('loans.id',$loan_id)
									->with([
										'coa_journal_detail' => function ($query) {
											$query->select('*', 'journal_requiry.entry_date', 'journal_requiry.description')
											->join('journal_requiry', 'journal_requiry.id', '=', 'journal_detail.journal_id')
											->orderBy('journal_requiry.entry_date', 'asc')
											->where('journal_detail.is_audit', '=', 1)
											->where('journal_detail.credit', '>', 0);
										},     
										'commission_withdrawal_transaction' => function ($query) {
											$query->select('*')->orderBy('withdrawal_date','desc');
										}]
									)
									->first();

									$commission_balance=0;
									$net_selling_price=0;
									$totalall_discount=floatval($loan->discount_promotion) + floatval($loan->discount_other) + floatval($loan->amount_discount_payment_option);
									$net_selling_price=floatval($loan->unit_sale_price) - floatval($totalall_discount); 


									$total_commission_paid=$loan->commission_withdrawal_transaction->sum('received_amount');
									if($loan->commission_type=='$'){
										$commission_balance=floatval($loan->commission_value)-floatval($total_commission_paid);
									}else{
										$commission_balance=floatval($net_selling_price * $d->commission_value/100)-floatval($total_commission_paid);
									}

									$sale = Sale::where('id',$loan->sale_id)->first();
								
									if (!empty($sale)) {	

										if($commission_balance>0){
											$sale->commission_status = 'Balance';
										}else{
											$sale->commission_status = 'Paid';
										   
										}	
										$sale->save();								
									}

								}						
		
							}
							DB::commit();
							return redirect()->route('list_sale_commission');
						}

					}else{
						return redirect()->back()->with(['error' => 'Failed to request! Please select an item in the list.']);
					}

				
			}else{
				return redirect()->back()->with(['error' => 'Failed to request! Please select an item in the list.']);
			}
		return redirect()->back();
			
		} catch (Exception $e) {
			//DB::rollBack();
			throw $e;
		}
	

return redirect()->back();
}


public function approvedCommissionSetting($id = 0) {

	DB::beginTransaction();
	try {

		if($id > 0) {			
						
			if(!Auth::user()->allow_approved_commission_setting){
				return redirect()->back()->with(['error' => "Failed to Aproved! You don't have permission to approved for this option. Please contact your administrator."]);
			}

			$unit_type = UnitType::find($id);
			if (!empty($unit_type)) {				
				$units = Unit::where('unit_type_id',$unit_type->id)->get();	
				
					
					if(!empty($units)){
						$setting = SaleCommissionSetting::where('unit_type_id',$unit_type->id)->where('is_active',1)->first();	
						if (!empty($setting)) {
							$setting->approval='approved';
							$setting->commission_approve_by = Auth::user()->name;
							$setting->save();
						}
						foreach ($units as $unit) {						
									$item = Unit::find($unit->id);
									if (!empty($item)) {
										$item->commission_approved=1;
										$item->commission_approve_by = Auth::user()->name;	
										$item->save();
									}
						}
					}

				
			}				
		}else{
			return redirect()->back()->with(['error' => 'Failed to Aproved! Please select an item in the list.']);
		}
	DB::commit();
	return redirect()->back();
		
	} catch (Exception $e) {
		DB::rollBack();
		throw $e;
	}	

return redirect()->back();
	
}
public function sendBackCommissionSetting($id = 0) {

	DB::beginTransaction();
	try {

		if($id > 0) {			
						
			if(!Auth::user()->allow_approved_commission_setting){
				return redirect()->back()->with(['error' => "Failed to Aproved! You don't have permission to use for this option. Please contact your administrator."]);
			}

			$unit_type = UnitType::with('activeSaleCommissionSetting')->find($id);
            $data['unit_type'] = $unit_type;
			// dd($data);
            
            if (!empty($data['unit_type'])) {
                return $this->view('unit_type.send_back', $data);
            }

		}
        return redirect()->back();
		
	} catch (Exception $e) {
		DB::rollBack();
		throw $e;
	}	
	
}	
public function postSendBackCommissionSetting($id = 0) {

	DB::beginTransaction();
	try {

		if($id > 0) {
			$setting = SaleCommissionSetting::where('unit_type_id',$id)->where('is_active',1)->first();	
			if (!empty($setting)) {
				$setting->approval='send_back';
				$setting->note=Request::input('note');
				$setting->commission_approve_by = Auth::user()->name;
				$setting->save();
			}
				
		}
	DB::commit();
	return redirect('/commission/getCommissionSettingList');
		
	} catch (Exception $e) {
		DB::rollBack();
		throw $e;
	}	

return redirect()->back();
	
}


	public function sm_commission_approve($id = 0) {

		DB::beginTransaction();
		try {

			if($id > 0) {
				
				$commissionRate = CommissionRate::first();				
				if(!Auth::user()->allow_approved_sm){
					return redirect()->back()->with(['error' => "Failed to Aproved! You don't have permission to approved for this option. Please contact your administrator."]);
				}

				$com = CommissionWithdrawal::find($id);
				if (!empty($com)) {				
					$com->sales_manager_approval = Auth::user()->name;
					if($com->save()){

						$ob = new Loan();
						$listLoan = $ob->selectRaw('
						tb_loans.id as loan_id,				
						tb_loans.unit_sale_price,
						tb_loans.amount_discount_payment_option,
						tb_loans.discount_payment_option,
						tb_loans.discount_other,
						tb_loans.discount_promotion,                
						tb_loans.down_payment_value,				
						tb_loans.co,
						tb_drawdown_account.coa_id,						
						tb_units.commission_type,
						tb_units.commission_value,
						tb_units.commission_approved,						
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
						tb_commission_withdrawal_transaction.chairman_approval
						')->with([
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
						->leftJoin('sale_order','sale_order.id','=','client_loan_accounts.sale_id')
						->leftJoin('units','units.id','=','loans.unit_id')
						->where('commission_withdrawal_transaction.commission_withdrawal_id',$com->id)
						->orderBy('commission_withdrawal_transaction.id', 'desc')->get();						
						
						if(!empty($listLoan)){
							foreach ($listLoan as $loan) {
								if ($loan->loan_id > 0) {
										$total_customer_paid=$loan->coa_journal_detail->sum('credit');
										$grand_total_com_paid=$loan->commission_withdrawal_transaction->sum('received_amount');
										
										$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);
										
										if(floatval($item->withdrawal_amount)>floatval($total_commission_tobe_pay)){
											return redirect()->back()->with(['error' => 'Failed to Aproved! Withdrawal amount is greater than commission to be pay.']);
										}
										$item = CommissionWithdrawalTransaction::find($loan->id);
										if (!empty($item)) {
										$item->sales_manager_approval = Auth::user()->name;					
										
										$item->save();
										}
										
									
								}
						
	
							}
						}

					}
				}				
			}else{
				return redirect()->back()->with(['error' => 'Failed to Aproved! Please select an item in the list.']);
			}
		DB::commit();
		return redirect()->back();
			
		} catch (Exception $e) {
			DB::rollBack();
			throw $e;
		}	

	return redirect()->back();
        
    }

	public function accountant_commission_approve($id = 0) {

		DB::beginTransaction();
		try {

			if($id > 0) {
				
				$commissionRate = CommissionRate::first();				
				if(!Auth::user()->allow_approved_acc){
					return redirect()->back()->with(['error' => "Failed to Aproved! You don't have permission to approved for this option. Please contact your administrator."]);
				}

				$com = CommissionWithdrawal::find($id);
				if (!empty($com)) {				
					$com->accountant_approval = Auth::user()->name;
					if($com->save()){

						$ob = new Loan();
						$listLoan = $ob->selectRaw('
						tb_loans.id as loan_id,				
						tb_loans.unit_sale_price,
						tb_loans.amount_discount_payment_option,
						tb_loans.discount_payment_option,
						tb_loans.discount_other,
						tb_loans.discount_promotion,                
						tb_loans.down_payment_value,				
						tb_loans.co,
						tb_drawdown_account.coa_id,						
						tb_units.commission_type,
						tb_units.commission_value,
						tb_units.commission_approved,						
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
						tb_commission_withdrawal_transaction.chairman_approval
						')->with([
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
						->leftJoin('sale_order','sale_order.id','=','client_loan_accounts.sale_id')
						->leftJoin('units','units.id','=','loans.unit_id')
						->where('commission_withdrawal_transaction.commission_withdrawal_id',$com->id)
						->orderBy('commission_withdrawal_transaction.id', 'desc')->get();						
						
						if(!empty($listLoan)){
							foreach ($listLoan as $loan) {
								if ($loan->loan_id > 0) {
										$total_customer_paid=$loan->coa_journal_detail->sum('credit');
										$grand_total_com_paid=$loan->commission_withdrawal_transaction->sum('received_amount');
										
										$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);
										
										if(floatval($item->withdrawal_amount)>floatval($total_commission_tobe_pay)){
											return redirect()->back()->with(['error' => 'Failed to Aproved! Withdrawal amount is greater than commission to be pay.']);
										}
										$item = CommissionWithdrawalTransaction::find($loan->id);
										if (!empty($item)) {
										$item->accountant_approval = Auth::user()->name;					
										
										$item->save();
										}
										
									
								}
						
	
							}
						}

					}
				}				
			}else{
				return redirect()->back()->with(['error' => 'Failed to Aproved! Please select an item in the list.']);
			}
		DB::commit();
		return redirect()->back();
			
		} catch (Exception $e) {
			DB::rollBack();
			throw $e;
		}	

	return redirect()->back();
        
    }

	public function hof_commission_approve($id = 0) {

		DB::beginTransaction();
		try {

			if($id > 0) {
				
				$commissionRate = CommissionRate::first();				
				if(!Auth::user()->allow_approved_hof){
					return redirect()->back()->with(['error' => "Failed to Aproved! You don't have permission to approved for this option. Please contact your administrator."]);
				}

				$com = CommissionWithdrawal::find($id);
				if (!empty($com)) {				
					$com->hof_approval = Auth::user()->name;
					if($com->save()){

						$ob = new Loan();
						$listLoan = $ob->selectRaw('
						tb_loans.id as loan_id,				
						tb_loans.unit_sale_price,
						tb_loans.amount_discount_payment_option,
						tb_loans.discount_payment_option,
						tb_loans.discount_other,
						tb_loans.discount_promotion,                
						tb_loans.down_payment_value,				
						tb_loans.co,
						tb_drawdown_account.coa_id,						
						tb_units.commission_type,
						tb_units.commission_value,
						tb_units.commission_approved,						
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
						tb_commission_withdrawal_transaction.chairman_approval
						')->with([
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
						->leftJoin('sale_order','sale_order.id','=','client_loan_accounts.sale_id')
						->leftJoin('units','units.id','=','loans.unit_id')
						->where('commission_withdrawal_transaction.commission_withdrawal_id',$com->id)
						->orderBy('commission_withdrawal_transaction.id', 'desc')->get();						
						
						if(!empty($listLoan)){
							foreach ($listLoan as $loan) {
								if ($loan->loan_id > 0) {
										$total_customer_paid=$loan->coa_journal_detail->sum('credit');
										$grand_total_com_paid=$loan->commission_withdrawal_transaction->sum('received_amount');
										
										$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);
										
										if(floatval($item->withdrawal_amount)>floatval($total_commission_tobe_pay)){
											return redirect()->back()->with(['error' => 'Failed to Aproved! Withdrawal amount is greater than commission to be pay.']);
										}
										$item = CommissionWithdrawalTransaction::find($loan->id);
										if (!empty($item)) {
										$item->hof_approval = Auth::user()->name;					
										
										$item->save();
										}								
									
								}
						
	
							}
						}

					}
				}				
			}else{
				return redirect()->back()->with(['error' => 'Failed to Aproved! Please select an item in the list.']);
			}
		DB::commit();
		return redirect()->back();
			
		} catch (Exception $e) {
			DB::rollBack();
			throw $e;
		}	

	return redirect()->back();
        
    }	
	public function chairman_commission_approve($id = 0) {

		DB::beginTransaction();
		try {

			if($id > 0) {
				
				$commissionRate = CommissionRate::first();				
				if(!Auth::user()->allow_approved_chairman){
					return redirect()->back()->with(['error' => "Failed to Aproved! You don't have permission to approved for this option. Please contact your administrator."]);
				}

				$com = CommissionWithdrawal::find($id);
				if (!empty($com)) {				
					$com->chairman_approval = Auth::user()->name;
					$com->status='Approved';
					if($com->save()){

						$ob = new Loan();
						$listLoan = $ob->selectRaw('
						tb_loans.id as loan_id,				
						tb_loans.unit_sale_price,
						tb_loans.amount_discount_payment_option,
						tb_loans.discount_payment_option,
						tb_loans.discount_other,
						tb_loans.discount_promotion,                
						tb_loans.down_payment_value,				
						tb_loans.co,
						tb_drawdown_account.coa_id,						
						tb_units.commission_type,
						tb_units.commission_value,
						tb_units.commission_approved,						
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
						tb_commission_withdrawal_transaction.chairman_approval
						')->with([
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
						->leftJoin('sale_order','sale_order.id','=','client_loan_accounts.sale_id')
						->leftJoin('units','units.id','=','loans.unit_id')
						->where('commission_withdrawal_transaction.commission_withdrawal_id',$com->id)
						->orderBy('commission_withdrawal_transaction.id', 'desc')->get();						
						
						if(!empty($listLoan)){
							foreach ($listLoan as $loan) {
								if ($loan->loan_id > 0) {
										$total_customer_paid=$loan->coa_journal_detail->sum('credit');
										$grand_total_com_paid=$loan->commission_withdrawal_transaction->sum('received_amount');
										
										$total_commission_tobe_pay=(floatval($total_customer_paid) * floatval($commissionRate->rate) /100) - floatval($grand_total_com_paid);
										
										if(floatval($item->withdrawal_amount)>floatval($total_commission_tobe_pay)){
											return redirect()->back()->with(['error' => 'Failed to Aproved! Withdrawal amount is greater than commission to be pay.']);
										}
										$item = CommissionWithdrawalTransaction::find($loan->id);
										if (!empty($item)) {											
										$item->chairman_approval = Auth::user()->name;
										$item->status='Approved';				
										
										$item->save();
										}								
									
								}
						
	
							}
						}

					}
				}				
			}else{
				return redirect()->back()->with(['error' => 'Failed to Aproved! Please select an item in the list.']);
			}
		DB::commit();
		return redirect()->back();
			
		} catch (Exception $e) {
			DB::rollBack();
			throw $e;
		}	

	return redirect()->back();
        
    }
	public function printCommission($id)
    {

        $commission = CommissionWithdrawal::where('id', $id)->first();
        if(empty($commission)){
            return redirect()->back();
        }
    	if ($id > 0) {

			$B0 = new CommissionWithdrawal();
			$com = $B0->selectRaw('
						   
						tb_commission_withdrawal.id,
						tb_commission_withdrawal.withdrawal_number,
						tb_commission_withdrawal.company_id,
						tb_commission_withdrawal.project_id,
						tb_commission_withdrawal.unit_type_id,					
						tb_commission_withdrawal.saleperson_id,
						tb_commission_withdrawal.commission_rate,
						tb_commission_withdrawal.withdrawal_date,
						tb_commission_withdrawal.t_from,
						tb_commission_withdrawal.t_to,
						tb_commission_withdrawal.status,
						tb_commission_withdrawal.payment_status,
						tb_commission_withdrawal.requester,
						tb_commission_withdrawal.sales_manager_approval,
						tb_commission_withdrawal.accountant_approval,
						tb_commission_withdrawal.hof_approval,
						tb_commission_withdrawal.chairman_approval,
						tb_commission_withdrawal.paid_by,
						tb_commission_withdrawal.noted,
						tb_company_branch.short_name as company,
						tb_projects.short_code as project,
						tb_unit_types.name as unit_type,
						tb_saleperson.name as sale_person,
						tb_saleperson.lavel

			')->with([
				'commissionWithdrawalTransaction'
				]
			)		
			->join('projects','projects.id','=','commission_withdrawal.project_id')
			->join('company_branch','company_branch.id','=','commission_withdrawal.company_id')
			->join('unit_types','unit_types.id','=','commission_withdrawal.unit_type_id')
			->join('saleperson','saleperson.id','=','commission_withdrawal.saleperson_id')
			->where('commission_withdrawal.id',$id)
		->first();

		if (!empty($com)) {
			$loan = new Loan();
			$listSale = $loan->selectRaw('
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
			tb_drawdown_account.coa_id,
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
			tb_commission_withdrawal_transaction.noted,
			tb_commission_withdrawal_transaction.total_customer_paid,
			tb_sale_order.final_price
			')->with([
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
			->leftJoin('sale_order','sale_order.id','=','client_loan_accounts.sale_id')
			->leftJoin('clients', 'clients.id', '=', 'loans.client_id')
			->leftJoin('projects','projects.id','=','loans.project_id')
			->leftJoin('company_branch','company_branch.id','=','projects.company_id')
			->leftJoin('unit_types','unit_types.id','=','loans.unit_type_id')
			->leftJoin('units','units.id','=','loans.unit_id')
			->where('commission_withdrawal_transaction.commission_withdrawal_id',$com->id)
			->orderBy('commission_withdrawal_transaction.id', 'desc')->get();

			// dd($listSale);
		}

			$unit_type = UnitType::find($unit->unit_type_id);
			$projects_row = Project::find($unit_type->project_id);
			$company_branch = CompanyBranch::find($projects_row->company_id);

			$sale_person = SalePerson::where('active',1)->where('id',$loan->sale_person)->first();
		

			return $this->view('sale.commission_receipt', [
				'listSale' => $com,
				'lists'=>$listSale
			]);
        }       
    } 
	public function mark_commission_payment($id =0){
    	if ($id > 0) {
			DB::beginTransaction();
			try {

				$com = CommissionWithdrawal::find($id);
				if (!empty($com)) {				
					$com->paid_by = Auth::user()->name;									
					$com->payment_status='Piad';

					if($com->save()){

						$ob = new Loan();
						$listLoan = $ob->selectRaw('
						tb_loans.id,				
						tb_loans.unit_sale_price,						
						tb_loans.amount_discount_payment_option,
						tb_loans.discount_payment_option,
						tb_loans.discount_other,
						tb_loans.discount_promotion,                
						tb_loans.down_payment_value,				
						tb_loans.co,
						tb_drawdown_account.coa_id,						
						tb_units.commission_type,
						tb_units.commission_value,
						tb_units.commission_approved,						
						tb_commission_withdrawal_transaction.id as commission_withdrawal_transaction_id,
						tb_commission_withdrawal_transaction.loan_id,
						tb_drawdown_account.sale_id
						')->with([
							'coa_journal_detail' => function ($query) {
								$query->select('*', 'journal_requiry.entry_date', 'journal_requiry.description')
								->join('journal_requiry', 'journal_requiry.id', '=', 'journal_detail.journal_id')
								->orderBy('journal_requiry.entry_date', 'asc')
								->where('journal_detail.is_audit', '=', 1)
								->where('journal_detail.credit', '>', 0);
							},
							'commission_withdrawal_transaction' => function ($query) {
								$query->select('*')->orderBy('withdrawal_date','desc');
							}]
						)
						->join('drawdown_account','drawdown_account.account_no','=','loans.drawdown_acc') 
						->join('commission_withdrawal_transaction','commission_withdrawal_transaction.loan_id','=','loans.id') 
						->leftJoin('client_loan_accounts','loans.loan_account_id','=','client_loan_accounts.id')
						// ->leftJoin('sale_order','sale_order.id','=','client_loan_accounts.sale_id')
						->leftJoin('units','units.id','=','loans.unit_id')
						->where('commission_withdrawal_transaction.commission_withdrawal_id',$com->id)
						->orderBy('commission_withdrawal_transaction.id', 'desc')->get();						
						
						if(!empty($listLoan)){
							foreach ($listLoan as $loan) {							
								if ($loan->loan_id > 0) {

										$item = CommissionWithdrawalTransaction::find($loan->commission_withdrawal_transaction_id);										
										if (!empty($item)) {											
											$item->paid_by = Auth::user()->name;
											$item->received_amount = $item->withdrawal_amount;
											$item->payment_status='Piad';
											$item->noted = "Auto mark payment";	
											$item->save();
										}

										$commission_balance=0;
										$net_selling_price=0;
										$totalall_discount=floatval($loan->discount_promotion) + floatval($loan->discount_other) + floatval($loan->amount_discount_payment_option);
										$net_selling_price=floatval($loan->unit_sale_price) - floatval($totalall_discount); 
		
		
										$total_commission_paid=$loan->commission_withdrawal_transaction->sum('received_amount');
										if($loan->commission_type=='$'){
											$commission_balance=floatval($loan->commission_value)-floatval($total_commission_paid);
										}else{
											$commission_balance=floatval($net_selling_price * $d->commission_value/100)-floatval($total_commission_paid);
										}
		
										$sale = Sale::where('id',$loan->sale_id)->first();
									
										if (!empty($sale)) {	
		
											if($commission_balance>0){
												$sale->commission_status = 'Balance';
											}else{
												$sale->commission_status = 'Paid';
											   
											}	
											$sale->save();								
										}

										$loan_update = Loan::where('id',$loan->id)->first();
									
										if (!empty($loan_update)) {	
		
											if($commission_balance>0){
												$loan_update->commission_status = 'Balance';
											}else{
												$loan_update->commission_status = 'Paid';
											   
											}	
											$loan_update->save();								
										}
									
								}
						
	
							}
						}

					}
				}	
			DB::commit();
			return redirect()->back();
				
			} catch (Exception $e) {
				DB::rollBack();
				throw $e;
			}
        }
    }

	public function postMark_commission_payment($id = 0) {
		$data = Request::all();
		$rules = [
			'withdrawal_amount' => 'required',
			'id' => 'required',
			'received_amount'=> 'required'
		];
		$validator = Validator::make($data, $rules);

		if ($validator->fails()) {
			return redirect()->back()->with(['error' => 'Failed to Created']);
		}else{	

			$item = CommissionWithdrawalTransaction::find(Request::input('id'));
            if (!empty($item)) {
                $item->paid_by = Auth::user()->name;
				$item->received_amount = Request::input('received_amount');
				$item->payment_status='Piad';
				$item->noted = Request::input('noted');	
                $item->save();
				return redirect()->route('list_sale_commission_detail',[$item->loan_id]);
                
            }
			return redirect()->route('list_sale_commission_detail',[$item->loan_id]);
		}
	

	return redirect()->route('list_sale_commission');
}

    public function getDetail($id =0){
        if (config('unified_sales.enabled')) {
            $detail=\App\Services\UnifiedSalesRepository::configured()->detail($id);
            if (!$detail) abort(404);
            return $this->view('sale.unified_detail',$detail);
        }
    	if ($id > 0) {
			$sale = new Sale();
			$listSale = $sale->selectRaw('
					tb_sale_order.id,
					tb_sale_order.order_no, 
					tb_sale_items.unit_sale_price,   
					tb_sale_order.created_on,               
					tb_clients.client_name,
					tb_company_branch.short_name as company,
					tb_clients.client_type,
					tb_projects.short_code,
					tb_unit_types.name as unit_type,
					tb_units.code as unit,               
					tb_currency.code AS currency_code,
					tb_sale_order.clearance_amount,
					tb_sale_order.discount_promotion,
					tb_sale_order.discount_other,
					tb_sale_order.discount_payment_option,
					tb_sale_order.amount_discount_payment_option,
					tb_sale_order.price_after_discount,
					tb_sale_order.vat,
					tb_sale_order.diposit_amount,
					tb_sale_order.final_price,
					tb_sale_order.status,
					tb_sale_order.authorization,
					tb_sale_order.payment_option,
					tb_sale_order.payment_status,
					tb_sale_order.invoice_status,
					tb_sale_order.remark,
					tb_sale_order.sale_person,
					tb_sale_order.sale_person_parent_l1,
					tb_sale_order.sale_person_parent_l2
				'
				) 
				->with([
					'sale_persons',
					'sale_person_parent_lavel1',
					'sale_person_parent_lavel2'
				])      
			->join('sale_items','sale_order.id','=','sale_items.sale_id')
			->join('currency','sale_order.currency','=','currency.id')
			->leftJoin('clients', 'clients.id', '=', 'sale_order.client_id')
			->leftJoin('company_branch', 'company_branch.id', '=', 'sale_items.company_branch_id')		
			->leftJoin('projects','projects.id','=','sale_items.project_id')
			->leftJoin('unit_types','unit_types.id','=','sale_items.unit_type_id')
			->leftJoin('units','units.id','=','sale_items.unit_id')
			->where('sale_order.id',$id)
			->first();
			return $this->view('sale.detail', [
				'listSale' => $listSale
			]);
        }
        return redirect()->back();
    }
	
	public function getSalePersonParent($lavel=0){
		if ($lavel > 0) {
			$sale_person_id = Request::input('sale_person_id');
			$sale_person = SalePerson::where('active',1)->where('parent_id',$sale_person_id)->where('lavel',$lavel)->get();
			$option = '<option value="0"> - </option>';
			foreach ($sale_person as $rowop) {
				$option .='<option value="'.$rowop->id.'">'.$rowop->name.'</option>';
			}
			$data['option'] = $option;
			echo json_encode($data);
		}
    } 

	public function getPaymentTerm(){
        $paymentTerm = PaymentTerm::where('status',1)->get();
        $option = '<option value="0"> - </option>';
        foreach ($paymentTerm as $rowop) {
            $option .='<option value="'.$rowop->id.'">'.$rowop->name.'</option>';
        }
        $data['option'] = $option;
        echo json_encode($data);
    } 
	public function getPaymentTermById(){
		$payment_option = Request::input('payment_option');
        $paymentTerm = PaymentTerm::where('status',1)->where('id',$payment_option)->first();
        echo json_encode($paymentTerm);
    } 

	public function getSale(){        
        $sale = new Sale();
        $listSale = $sale->selectRaw('
				tb_sale_order.id,
				tb_sale_order.order_no, 				            
                tb_clients.client_name,
				tb_company_branch.short_name as company,               
                tb_projects.short_code,
                tb_unit_types.name as unit_type,
                tb_units.code as unit
            '
            )       
		->join('sale_items','sale_order.id','=','sale_items.sale_id')
		->join('currency','sale_order.currency','=','currency.id')
        ->leftJoin('clients', 'clients.id', '=', 'sale_order.client_id')
		->leftJoin('company_branch', 'company_branch.id', '=', 'sale_items.company_branch_id')		
        ->leftJoin('projects','projects.id','=','sale_items.project_id')
        ->leftJoin('unit_types','unit_types.id','=','sale_items.unit_type_id')
        ->leftJoin('units','units.id','=','sale_items.unit_id')
		->where('sale_order.invoice_status', 'Invoice')->get();
        $option = '<option value=""> - </option>';
        foreach ($listSale as $rowop) {
            $option .='<option value="'.$rowop->id.'">'.$rowop->order_no.' ( '.$rowop->company.'-'.$rowop->client_name.'-'.$rowop->short_code.'-'.$rowop->unit.')</option>';
        }
        $data['option'] = $option;
        echo json_encode($data);
    }
	
	public function getSaleReportDeposit() {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 50;
        if(!$offset){
        	$offset = 50;
        }
        $query_arr = array('user_id'=>Auth::user()->id, 'branch_id'=>Auth::user()->branch_id, 'role_id'=>Auth::user()->role_id);
		$from_date = Request::input('t_from')?Request::input('t_from'):date("Y-m-d");
        $to_date = Request::input('t_to')?Request::input('t_to'):date("Y-m-d");
        $sale = new Sale();
        $listSale = $sale->selectRaw('
				tb_sale_order.id,
				tb_sale_order.order_no, 
				tb_sale_order.created_on, 
				tb_sale_items.unit_sale_price,            
                tb_clients.client_name,
				tb_clients.phone1,
				tb_clients.phone2,
				tb_clients.address,
				tb_company_branch.short_name as company,
                tb_clients.client_type,
                tb_projects.short_code,
                tb_unit_types.name as unit_type,
                tb_units.code as unit,               
                tb_currency.code AS currency_code,
				tb_sale_order.client_id,
				tb_sale_order.clearance_amount,
				tb_sale_order.discount_promotion,
				tb_sale_order.discount_other,
				tb_sale_order.discount_payment_option,
				tb_sale_order.amount_discount_payment_option,				
				tb_sale_order.price_after_discount,
				tb_sale_order.vat,
				tb_sale_order.diposit_amount,
				tb_sale_order.final_price,
				tb_sale_order.status,
				tb_sale_order.authorization,
				tb_sale_order.payment_option,
				tb_sale_order.payment_status,
				tb_sale_order.invoice_status,
				tb_sale_order.remark,
				tb_sale_order.sale_person,
				tb_sale_order.sale_person_parent_l1,
				tb_sale_order.sale_person_parent_l2
            '
            )    
			->with([
				'sale_persons',
				'sale_person_parent_lavel1',
				'sale_person_parent_lavel2'
            ]
        )   
		
		->join('sale_items','sale_order.id','=','sale_items.sale_id')
		->join('currency','sale_order.currency','=','currency.id')
        ->leftJoin('clients', 'clients.id', '=', 'sale_order.client_id')
		->leftJoin('company_branch', 'company_branch.id', '=', 'sale_items.company_branch_id')		
        ->leftJoin('projects','projects.id','=','sale_items.project_id')
        ->leftJoin('unit_types','unit_types.id','=','sale_items.unit_type_id')
        ->leftJoin('units','units.id','=','sale_items.unit_id')
		->where('sale_order.diposit_amount','>',0)
		->where('sale_order.status','Ordered')
		->orWhere('sale_order.status','Accepted')
		->where('sale_order.invoice_status','!=','Invoice')
		->where('sale_order.sale_status','!=','Change_Unit')
		->whereDate('sale_order.created_on','>=',$from_date)->whereDate('sale_order.created_on','<=',$to_date);
		$customer_id = Request::input('customer_id');
		$company = Request::input('company');
		$client = null;
		if($customer_id){
            $listSale = $listSale->where('sale_order.client_id',$customer_id);
			$client = Clients::find($customer_id);
        }
        if($company){
            $listSale = $listSale->where('sale_items.company_branch_id',$company);			
            $company_branch = CompanyBranch::find(Request::get('company'));
        }
		$project_id = null;
        if(Request::has('project_id')){
            $project_id = Request::input('project_id');
            $project = Project::find($project_id);
            $listSale = $listSale->where('sale_items.project_id',$project_id);
        }
		$status = null;
        if(Request::has('status')){
            $status = Request::input('status');
            $listSale = $listSale->where('sale_order.status',$status);
        }

		if(Request::get('search')){
            $listSale = $listSale->where('sale_order.order_no','like','%'.Request::get('search').'%');
            $listSale = $listSale->orWhere('projects.dealer_en','like','%'.Request::get('search').'%');
            $listSale = $listSale->orWhere('projects.dealer','like','%'.Request::get('search').'%');
            $listSale = $listSale->orWhere('clients.client_name','like','%'.Request::get('search').'%');
            $listSale = $listSale->orWhere('sale_order.client_id','like','%'.Request::get('search').'%');
            $listSale = $listSale->orWhere('company_branch.short_name','like','%'.Request::get('search').'%');
            $listSale = $listSale->orWhere('unit_types.name','like','%'.Request::get('search').'%');
            $listSale = $listSale->orWhere('units.code','like','%'.Request::get('search').'%');
        }

		$sale_person = null;
        if(!empty(Auth::user()->sale_person)){
            $sale_person =Auth::user()->sale_person;
            $sale_person_list = SalePerson::find($sale_person);
            $listSale = $listSale->where('sale_order.sale_person',$sale_person);
            if($sale_person_list->lavel==2){
                $sale_person_memberl2 = SalePerson::where('active',1)->where('parent_id',$sale_person_list->id)->get();
                if($sale_person_memberl2){
                    $ids = [];
                    foreach($sale_person_memberl2 as $sale_member2){
                        $ids[] = $sale_member2->id;
                    }
                    $listSale = $listSale->orWhereIn('sale_order.sale_person',$ids); 

                }              
            }elseif($sale_person_list->lavel==1){

                $sale_person_memberl2 = SalePerson::where('active',1)->where('parent_id',$sale_person_list->id)->get();

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
                    $listSale = $listSale->orWhereIn('sale_order.sale_person',$idsAll); 

                } 

            }
        }

		$listSale = $listSale->paginate($offset)->setPath('list?client_id='.$customer_id.'&company='.$company.'&project_id='.$project_id.'&status='.$status.'&search='.$search.'&offset='.$offset);
        $contract_template = config('static_data.contract_template');
        return $this->view('sale.list_sale_deposit', ['lists' => $listSale, 'customer_id' => $company,'company' => $customer_id,'offset'=>$offset,'contract_template' => $contract_template],compact('projects','project_id','customer_id','client','from_date','to_date','company','status'));

    }
}
