@extends('layouts.app')

@section('css')

    <link rel = "stylesheet" type = "text/css" href = "{{ asset('css/loan-style.css',false) }}"/>
    <link rel = "stylesheet" type = "text/css" href = "{{ asset('theme/js/select2/select2.css',false) }}"/>
    <link rel = "stylesheet" type = "text/css" href = "{{ asset('theme/js/bootstrap-datepicker/css/datepicker.css',false)}}"/>
    <style>
        .col-md-3.control-label.text-left {
            text-align: left !important;
        }
        #customer_types, #guarantor {
            max-height: 300px;
        }
    </style>
@endsection

@section('content')
    <section class = "panel">
        <div class = "panel-heading">
            Accept Sale Order
        </div>
        <div class = "panel-body">
            <div class = "col-sm-12">
                <label class = "col-md-2"></label>
                <div class = "col-md-10">
                    @if (count($errors) > 0)
                        <div class = "alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{$error}}</li>
                                @endforeach
                            </ul>
                        </div>
                    @elseif(session('msg_success'))
                        <div class = "alert alert-success">
                            <ul>
                                <li>{{ session('msg_success') }}</li>
                            </ul>
                        </div>
                    @endif
                    @if(Session::has('msg'))
                        <div class = "alert alert-danger fade in">
                            <button class = "close close-sm" data-dismiss = "alert">x</button>
                            {{ Session::get('msg') }}
                        </div>
                    @endif
                </div>
                <?php
                $static = config('static_data');
                $select_branch_id = 0;
                $select_currency_id = 0;
                $auto_selected = $static['auto_select_option'];
                ?>
            </div>

            <form class = "cmxform form-horizontal" method = "post" action = "{{route('saleAccept',[$sale->id])}}" id = "frmCoaAccount">
                <div class = "col-lg-12">
                    <input type = "hidden" name = "_token" value = "{{ csrf_token() }}"/>
                    <input type="hidden" name="sale_id" value="{{ $sale->id }}">
                    @if(isset($saleTermsHash) && $saleTermsHash)
                        <input type="hidden" name="sale_terms_hash" value="{{ $saleTermsHash }}">
                    @endif
                    <div class = "form-group">                       
                        <div class = "col-md-3">
                            <label class = "control-label">Tenure<span class = "red-color">*</span></label>
                            <select class="form-control" id = "tenure">
                                <option value = "">-</option>
                                @foreach($static['tenureType'] as $key => $value)
                                    <option value = "{{ $key }}" {{ $key?$key == $auto_selected['tenure'] ? 'selected':'':old('') }}>{{ $value }}</option>
                                @endforeach
                            </select>
                        </div> 

                        <div class = "col-md-3">
                            <label class = "control-label">Ownership<span class = "red-color">*</span></label>
                            <select class="form-control" id = "ownership">
                                <option value = "">-</option>
                                @foreach($static['ownershipType'] as $key => $value)
                                    <option value = "{{ $key }}" {{ $key?$key == $auto_selected['ownership'] ? 'selected':'':old('') }}>{{ $value }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class = "col-md-3">
                            <label class = "control-label">Currency<span class = "red-color">*</span></label>
                            <select class="form-control" name="currency" id = "currency">
                                <option value = "">-</option>
                                @foreach($static['currency'] as $key => $value)
                                    <option value = "{{ $key }}" {{ $key?$key == $auto_selected['currency'] ? 'selected':'':old('') }}>{{ $value }}</option>
                                    <?php echo 'selected'; $select_currency_id = $key; ?>
                                @endforeach
                            </select>
                        </div>

                        <div class = "col-md-3">
                            <label class = "control-label">Created On</label>
  
                            <div data-date-viewmode = "years" data-initialize = "datepicker" data-date-format = "dd/mm/yyyy" data-date = "{{date('Y-m-d')}}" class = "input-append date dpYears">
                                <input type = "text" name = "created_on" value = "{{date('Y-m-d')}}" size = "16" class = "form-control" id = "created_on">
                                <span class = "add-on birhtdateDatepicker">
                                    <button class = "btn btn-primary" type = "button"><i class = "fa fa-calendar"></i></button>
                                </span>
                            </div>
                    </div>

                    </div>

                    <div class = "form-group">                        
                        <div class = "col-md-3">
                        <label class = "control-label">{{ trans('company.company') }}<span class = "red-color">*</span></label>
                            <select style="width: 100%" name = "branch" id = "branch">
                                @if(isset($branches))
                                    <option value = "">-</option>
                                    @foreach($branches as $b)
                                        <option value = "{{ $b->branch_code }}"  company-id="{{ $b->id }}">{{ $b->branch_name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-sm-3">
                        <label class="control-label">{{ trans('loan.project') }}<span class="red-color"> *</span></label>
                            <div class="input-group">
                                <select id="project_id" style="width: 100%" name="project_id">
                                    <option value="">-</option>
                                </select>
                                <span class="input-group-btn" style="padding-left:5px;vertical-align:top">{{ trans('multiple.m_add') }}</span>
                            </div>
                        </div>
                        <div class="col-sm-3">
                        <label class="control-label">{{ trans('loan.unit_type') }}<span class="red-color"> *</span></label>
                            <div class="input-group">
                                <select id="unit_type_id" style="width: 100%" name="unit_type_id">
                                    <option value="">-</option>
                                </select>
                                <span class="input-group-btn" style="padding-left:5px;vertical-align:top">{{ trans('multiple.m_add') }}</span>
                            </div>
                        </div>
                        <div class="col-sm-3">
                        <label class="control-label">{{ trans('unit.unit') }}<span class="red-color"> *</span></label>
                            <div class="input-group">
                                <select id="unit_id" style="width: 100%" name="unit_id">
                                    <option value="">-</option>
                                </select>
                                <span class="input-group-btn" style="padding-left:5px;vertical-align:top">{{ trans('multiple.m_add') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class = "form-group">                       
                        <div class = "col-md-4">
                        <label class = "control-label">Resident Status<span class = "red-color">*</span></label>
                            <select class="form-control" id = "resident">
                                <option value = "">-</option>
                                @foreach($static['residentType'] as $key => $value)
                                    <option value = "{{ $key }}" {{ $key?$key == $auto_selected['resident_status'] ? 'selected':'':old('') }}>{{ $value }}</option>
                                @endforeach
                            </select>
                        </div>                        
                        <div class = "col-md-4">
                        <label class = "control-label">Loan Category<span class = "red-color">*</span></label>
                            <select class="form-control" id = "category">
                                <option value = "">-</option>
                                @foreach($static['loanCategory'] as $key => $value)
                                    <option value = "{{ $key }}" {{ $key?$key == $auto_selected['laon_category'] ? 'selected':'':old('') }}>{{ $value }}</option>
                                @endforeach
                            </select>
                        </div>
                       
                        <div class = "col-md-4">
                        <label class = "control-label">Parent Account<span class = "red-color">*</span></label>
                            <select class = "select2_type account_select1" style = "width: 100%;" name = "parent_id" id = "parent_account">
                                <option value = "">-</option>
                                {{--@if(isset($parents))--}}
                                    {{--@foreach($parents as $p)--}}
                                        {{--<option value = "{{ $p->id }}">{{$p->account_code}} - {{ $p->name }} ( {{$currency[$p->currency-1]->code}} )</option>--}}
                                    {{--@endforeach--}}
                                {{--@endif--}}
                            </select>
                        </div>
                    </div>

                    <div class = "form-group">                        
                        <div class = "col-md-3">
                        <label class = "control-label">Customer ID<span class = "red-color">*</span></label>
                            <select class = "select2_type customer_select1" style = "width: 100%;" name = "client_id" id = "customer_id">
                                @foreach($clients as $c)
                                    <option value = "{{$c->id}}"> {{str_pad($c->id, 4, '0', STR_PAD_LEFT)}} ( {{ $c->client_name }} )</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class = "col-md-3">
                        <label class = "control-label">Loan Account Number<span class = "red-color">*</span></label>
                            <input type = "text" name = "account_no" id = "account_no" class = "form-control" readonly/>
                        </div>
                      
                        <div class = "col-md-3">
                        <label class = "control-label">Account Name<span class = "red-color">*</span></label>
                            <input type = "text" name = "account_name" id = "account_name" class = "form-control" readonly/>
                        </div>                        
                        <div class = "col-md-3">
                        <label class = "control-label">Drawdown Account Number<span class = "red-color">*</span></label>
                            <input type = "text" name = "dd_account_no" id = "dd_account_no" class = "form-control" readonly/>
                        </div>
                    </div>
                    <input name = "dd_parent_id" id = "dd_parent_id" type = "hidden">
                    <input name = "leasing_flag" id = "leasing_flag" type = "hidden">

                    <div class = "form-group pull-right">                      
                        <div class = "col-md-12">
                            <a href="/sale/list"  class = "btn btn-secondary pull-right"> Cancel </a>
                            <button class = "btn btn-primary">Save</button>
                        </div>
                    </div>
                </div>
                <div class = "col-lg-6 hidden">
                    <div class = "col-lg-12">
                        <div class = "form-group">
                            <label class="col-md-3 control-label text-left">Customer type</label>
                            <div class = "col-md-8">
                                <select class = "form-control" name="acc_type" id = "customertype">
                                    <option value="0">-</option>
                                    @foreach($static['account_cbc_type'] as $key => $value)
                                        @if($key != 'S')
                                            <option value = "{{$key}}">{{$value}}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>

                    </div>
                    <div class = "col-lg-12">
                        <div class = "table-responsive" id="customer_types">
                            <table class = "table table-bordered">
                                <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Name</th>
                                    <th>Gender</th>
                                    <th>Phone</th>
                                </tr>
                                </thead>
                                <tbody id = "_cust_type_result"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <div class = "form-group">
                            <label class = "col-md-3 control-label">Choose any Guarantors</label>
                            <div class = "col-md-8">
                                <select class = "form-control" name="guarantor" id="guarantor">
                                    <option value = "0">-</option>
                                    @foreach($static['applicant_type'] as $key => $value)
                                        @if($key != 'S' && $key != 'P')
                                            <option value = "{{$key}}">{{$value}}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class = "col-lg-12">
                        <div class = "table-responsive">
                            <table class = "table table-bordered">
                                <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Name</th>
                                    <th>Gender</th>
                                    <th>Phone</th>
                                </tr>
                                </thead>
                                <tbody id = "guarantor_result"></tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </section>
@endsection

@section('js')
    <script type = "text/javascript" src = "{{ asset('theme/js/bootstrap-datepicker/js/bootstrap-datepicker.js',false)}}"></script>
    <script src = "{{ asset('theme/js/select2/select2.js',false) }}"></script>
    <script type = "text/javascript" src = "{{ asset('js/print.js',false)}}"></script>
    <script type = "text/javascript">
        $('.dpYears').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            setDate: new Date()
        });

        var parents = <?php echo json_encode($parents);?>;
        var account = <?php echo isset($parents) ? $parents : null; ?>;
        var clients = <?php echo json_encode($clients);?>;
        var branches = <?php echo json_encode($branches);?>;
        var acc_payables = <?php echo json_encode($dd_parent_accs);?>;
        var exist_cla = <?php echo json_encode($exist_cla);?>;
           // select_coa();
        $(document).ready(function () {
            // select_coa();
            // get_account_name();
            $("#project_id").select2();
            $("#unit_type_id").select2();
            $("#unit_id").select2();
            $("#branch").select2();
            var company = '<?php echo $saleItem->company_branch_id;?>';
        var client_name= '<?php echo $clients[0]->client_name;?>';
        @if(isset($saleTermsHash) && $saleTermsHash)
        $.each(branches, function(i, branch) {
            if(String(branch.id) === String(company)) {
                $('#branch').val(branch.branch_code).trigger('change');
            }
        });
        @else
        if(company){
            if(company==1){
                $("#branch").val('01').trigger('change');
            }else{
                $("#branch").val('02').trigger('change');
            }          
        }
        @endif
        });
        $("#branch").on('change', function () {
            get_account_name();
            getProjectByCompany();
        });
        $("#currency").on('change', function () {
            // select_coa();
            get_account_name()
        });
        $("#parent_account").on('change', function () {
            get_account_name();
        });
        $("#customer_id").on('change', function () {
            get_account_name();
        });
        $(document).on('change', '#project_id', function () {
            getUnitType();
        });
        $(document).on('change', '#unit_type_id', function () {
            getUnitByUnittype();
        });
        function getProjectByCompany(){
            var company_id = $('#branch option:selected').attr('company-id');
            $.ajax({
                url: '/getProjectByCompany',
                type: 'GET',
                dataType: "json",
                // processData: false,
                // contentType: false,
                data:{company_id:company_id@if(isset($saleTermsHash) && $saleTermsHash),handoff_sale_id:{{ (int)$sale->id }}@endif},
                success: function (data) {
                    $('#project_id').html(data.option);
                    $("#project_id").select2();
                    var project_id = '<?php echo $saleItem->project_id;?>';
                    if(project_id){
                        $("#project_id").val(project_id).trigger('change');
                    }
                }
            });
        }
        function getUnitType(){
            var project_id = $('#project_id').val();
            $.ajax({
                url: '/getUnitType',
                type: 'GET',
                dataType: "json",
                // processData: false,
                // contentType: false,
                data:{project_id:project_id@if(isset($saleTermsHash) && $saleTermsHash),handoff_sale_id:{{ (int)$sale->id }}@endif},
                success: function (data) {
                    $('#unit_type_id').html(data.option);
                    $("#unit_type_id").select2();
                    var unit_type_id = '<?php echo $saleItem->unit_type_id;?>';
                    if(unit_type_id){
                        $("#unit_type_id").val(unit_type_id).trigger('change');
                    }
                }
            });
        }
        function getUnitByUnittype(){
            var unit_type_id = $('#unit_type_id').val();
            $.ajax({
                url: '/getTillerUnitByUnittype',
                type: 'GET',
                dataType: "json",
                // processData: false,
                // contentType: false,
                data:{unit_type_id:unit_type_id@if(isset($saleTermsHash) && $saleTermsHash),handoff_sale_id:{{ (int)$sale->id }}@endif},
                success: function (data) {
                    $('#unit_id').html(data.option);
                    $("#unit_id").select2();
                    var unit_id = '<?php echo $saleItem->unit_id;?>';
                    if(unit_id){
                        $("#unit_id").val(unit_id).trigger('change');
                    }
                }
            });
        }
        function gennerateDrawdownAcc(){
            var branch_code = $('#branch').val();
            $.ajax({
                url: '/gennerateDrawdownAcc',
                type: 'GET',
                dataType: "json",
                // processData: false,
                // contentType: false,
                data:{branch_code:branch_code},
                success: function (data) {
                    $("#dd_account_no").val(data);
                }
            });
        }
        function gennerateLoanAcc(){
            var branch_code = $('#branch').val();
            $.ajax({
                url: '/gennerateLoanAcc',
                type: 'GET',
                dataType: "json",
                // processData: false,
                // contentType: false,
                data:{branch_code:branch_code},
                success: function (data) {
                    $("#account_no").val(data);
                }
            });
        }
        function get_account_name() {
            var account_id = $("#parent_account").val();
            var branch_code = $("#branch").val();
            var customer_id = $("#customer_id").val();
            var currency_code = $("#currency").val();
            var account_code = "";
            var acc_name = "";
            var acc_num = 0;
            var ex_account_no = "";
            $.each(parents, function (i, v) {
                if (account_id == v.id) {
                    $.each(parents, function (i, v) {
                        if (account_id == v.id) {
                            account_code = v.account_code;
                            acc_name =  v.name;
                            return;
                        }
                    });
                    $.each(exist_cla, function (i, e) {
                        //if (account_id == e.parent_id) {
                        ex_account_no = e.account_no;
                        if (ex_account_no.indexOf(account_code.substr(0, 9)) !== -1) {
                            acc_num = acc_num + 1;
                        }
                    });
                    if (account_code != "") {
                        // $("#account_no").val(branch_code + "-" + account_code.substr(0, 9) + "-" + ("0000" + (acc_num + 1)).slice(-4));
                        gennerateLoanAcc();
                    }
                    var customer_name = "";
                    $.each(clients, function (j, c) {
                        if (customer_id == c.id) {
                            $.each(c.general, function (inx, vals) {

                                customer_name = vals.family_name + ' ' + vals.first_name

                            });
                            return;
                        }
                    });
                    $("#account_name").val(customer_name);
                    if(acc_name.search("Leasing") > 0){
                        $("#leasing_flag").val(1);
                    }


                    // drawdown accounts
                    var dd_acc_name = "";
                    var exp_lease = "Leas";
                    $.each(acc_payables, function (i, ap) { // Voluntary Deposits - Drawdown Accounts
                        if (currency_code == ap.currency) {
                            if((v.name.match(exp_lease) && ap.name.match(exp_lease)) ||(v.name.match("Stand-L") && ap.name.match("Loan")) ){
                                dd_acc_name = branch_code + "-" + ap.account_code.substr(0, 9) + "-" + ("0000" + customer_id).slice(-4);
                                $('#dd_parent_id').val(ap.id);
                                return;
                            }
                        }
                    });
                    // $("#dd_account_no").val(dd_acc_name);
                    gennerateDrawdownAcc();
                }
            });
        }

        function select_coa() {
            var branch_code = $("#branch").val();
            var currency_code = $("#currency").val();
            $(".account_select1").select2('destroy');
            //$(".account_select1").find('option:not(:first)').remove();
            $.each(account, function (i, data) {
                if (data.currency == currency_code) {
                    $(".account_select1").append("<option value=" + data.id + ">" + data.account_code + " ( " + data.name + " ) </option>");
                }
            });
            // $("#account_no").val(branch_code + account_code + "-" + ("000000" + customer_id).slice(-6));
            gennerateLoanAcc();
            $(".account_select1").select2();
            $(".customer_select1").select2();
        }
        $(".select2_type").select2();

        $(document).on('change','#customertype, #guarantor', function () {

            var $this = $(this);
            if (typeof $this.val() != 'undefined') {

                if($this.is('#customertype') ) {
                    $('#_cust_type_result').children().remove();
                    return getClient($this);
                } if($this.is('#guarantor')) {

                    $('#guarantor_result').children().remove();
                    return getGuarantor($this);
                }
            }
        });

        function getClient($this) {

            $.ajax({
                url: '/accounting/getCLientAccountType/' + $this.val(),
                type: 'json',
                method: 'get',
                headers: {'X-CSRF-Token': $('meta[name=_token]').attr('content')},
                contentType: false,
                cache: false,
                processData: false,
                data: {
                    _token: $('meta[name=_token]').attr('content'),
                },
                beforeSend: function () {
                    $this.attr('disabled', true);
                },
                success: function (data, status) {
                    $this.attr('disabled', false);
                    if (typeof status != 'undefined' && status == 'success') {
                        var htmls = '', i = 1;

                        $.each(data, function (inx, vals) {

                            if(inx == 'clients') {
                                $.each(vals, function (inx, valss) {

                                    var name ='N/A', gender= 'N/A',contact = 'N/A';
                                    if (!$.isEmptyObject(valss.general)) {
                                        $.each(valss.contact, function (inx, contacts) {
                                            contact = contacts.contact_number_number;
                                        });
                                    }
                                    if (!$.isEmptyObject(valss.general)) {

                                        $.each(valss.general, function (inx, general) {

                                            name = general.family_name + ' ' + general.first_name;

                                            if(general.applicant_type != 'G') {
                                                htmls += '<tr>';
                                                htmls += '<td><a target="_blank" href="/'+'client/detail'+'/'+valss.id+'">' + i + '</a></td>';
                                                htmls += '<td><a target="_blank"  href="/'+'client/detail'+'/'+valss.id+'">' + name + '</a></td>';
                                                htmls += '<td><a target="_blank"  href="/'+'client/detail'+'/'+valss.id+'">' + general.gender + '</a></td>';
                                                htmls += '<td><a target="_blank"  href="/'+'client/detail'+'/'+valss.id+'">' + contact + '</a></td>';
                                                htmls += '<td><input name="sub_client_id[]" class="form-control sub_client_id" type="checkbox" value="'+valss.id+'"  style="height:30px; width: 30px;" /></td>';
                                                htmls += '</tr>';
                                                i++;

                                            }
                                        });
                                    }
                                });
                            }
                        });
                        $(htmls).appendTo('#_cust_type_result');

                    }

                },
                error: function (requestObject, error, errorThrown) {

                    switch (requestObject.status) {
                        case 401:
                            if (confirm('User\'s session was expired, Please click OK to login again')) {
                                window.location.href = '/user/login';
                            }
                            break;
                        case 500:
                            if (confirm('Internal Server Error, Please contact your technical ')) {
                                window.location.reload()
                            }
                            break;
                        default:
                            console.log(requestObject);
                    }
                }
            });
        }

        function getGuarantor($this) {

            $.ajax({
                url: '/accounting/getCLientAccountType/' + $this.val(),
                type: 'json',
                method: 'get',
                headers: {'X-CSRF-Token': $('meta[name=_token]').attr('content')},
                contentType: false,
                cache: false,
                processData: false,
                data: {
                    _token: $('meta[name=_token]').attr('content'),
                },
                beforeSend: function () {
                    $this.attr('disabled', true);
                },
                success: function (data, status) {

                    $this.attr('disabled', false);
                    if (typeof status != 'undefined' && status == 'success') {
                        var htmls = '', i = 1;

                        $.each(data, function (inx, vals) {

                            if(inx == 'guarantor' && !$.isEmptyObject(vals)) {

                                $.each(vals, function(inx, general) {

                                    var name ='N/A', gender= 'N/A',contact = 'N/A';

                                    if(!$.isEmptyObject(general.clients) && general.clients.status == 1){

                                        $.each(general.clients.contact, function(inxs, cnt) {
                                            contact = cnt.contact_number_number;
                                        });

                                        name = general.family_name + ' ' + general.first_name;
                                        htmls += '<tr>';
                                        htmls += '<td><a target="_blank" href="/'+'client/detail'+'/'+general.client_id+'">' + i + '</a></td>';
                                        htmls += '<td><a target="_blank"  href="/'+'client/detail'+'/'+general.client_id+'">' + name + '</a></td>';
                                        htmls += '<td><a target="_blank"  href="/'+'client/detail'+'/'+general.client_id+'">' + general.gender + '</a></td>';
                                        htmls += '<td><a target="_blank"  href="/'+'client/detail'+'/'+general.client_id+'">' + contact + '</a></td>';
                                        htmls += '<td><input name="guarantor[]" class="form-control guarantor" type="checkbox" value="'+general.client_id+'" style="height:30px; width: 30px;" /></td>';
                                        htmls += '</tr>';
                                        i++;
                                    }
                                });
                            }
                        });
                        $(htmls).appendTo('#guarantor_result');
                    }
                },
                error: function (requestObject, error, errorThrown) {
                    $this.attr('disabled', false);
                    switch (requestObject.status) {
                        case 401:
                            if (confirm('User\'s session was expired, Please click OK to login again')) {
                                window.location.href = '/user/login';
                            }
                            break;
                        case 500:
                            if (confirm('Internal Server Error, Please contact your technical ')) {
                                window.location.reload()
                            }
                            break;
                        default:
                            console.log(requestObject);
                    }
                }
            });
        }
        function parent_account_duplicate(){
            var seen = {};
            jQuery('#parent_account').children().each(function() {
                var txt = jQuery(this).attr('value');
                if (seen[txt]) {
                    jQuery(this).remove();
                } else {
                    seen[txt] = true;
                }
            });
        }
        var childrents = {
            data:[]
        };
        $(document).on('change','#tenure, #ownership, #currency, #branch, #resident, #category', function () {
            var $this = $(this);
            var tenure_str = $('#tenure option:selected').val();
            var ownership_str = $('#ownership option:selected').val();
            var resident_str = $('#resident option:selected').val();
            var category_str = $('#category option:selected').val();
            var currency_str = $('#currency option:selected').val();
            if (typeof $this.val() != 'undefined') {

                if($this.is('#tenure') || $this.is("#category") || $this.is("#branch")) {
                    if(!$this.val()) return;

                    var LoanCategory  = JSON.parse('<?PHP $array = array_keys($static['loanCategory']); echo json_encode($array) ?>');
                  if(parseInt(tenure_str) == 0 ) { 
                        //if the tenure smaller or equal then 1y (<= 1year)
                        //we will filter all coa which smaller or equal then 1 years (<=1 year)
                        $.each(parents, function(inx, vals) {
                             if(vals.name.search('<=1') > 0) { // if the name is contain <=1
                                childrents.data.push(inx, vals);/// greater then 1y
                            }
                        });
                  }
                  if(parseInt(tenure_str) == 1) { //if the tenure greater then 1y (> 1year)

                        $.each(parents, function(inx, vals) {
                             if(vals.name.search('>1') > 0) { // if the name is contain <=1
                               childrents.data.push(inx, vals)
                            }
                        });
                  }
                  if(parseInt(resident_str) == "RT") { //Resident Status

                      $.each(parents, function(inx, vals) {
                           if(vals.name.search('-RT') > 0) { // if the name is contain "-RT"
                             childrents.data.push(inx, vals)
                          }
                      });
                  }else if(parseInt(resident_str) == "NRT"){
                      $.each(parents, function(inx, vals) {
                           if(vals.name.search('-NRT') > 0) { // if the name is contain "-NRT"
                             childrents.data.push(inx, vals)
                          }
                      });
                  }
                  if(!$this.is("#category")){
                    var category = $('#category').val();
                    var auto_selected_parent_account = "{{ $auto_selected['parent_account'] }}";
                    if($.inArray(category, LoanCategory) >= 0 ) {// this is filter for loan categories
                            if(!$.isEmptyObject(childrents.data) && typeof childrents.data != 'defined') {
                                var currencyID = $("#currency option:selected").val();
                                var Ownership  = $('#ownership option:selected').val(); //'Groups' => 'Group', 'Indi' => 'Individual', 'Corperations' => 'Corporation'
                                var Own =JSON.parse( '<?PHP $arr = array_keys($static['ownershipType']); echo json_encode($arr); ?>');
                                var resident_status = "-"+$("#resident option:selected").val();

                                $('#parent_account option').remove();
                                $('#parent_account').append('<option value="">---</option>');
                                //console.log($this.val());
                                // break;
                                // console.log(childrents.data);
                                for(var key in childrents.data) {
                                    var type_str = "-"+category+"-"
                                    if(category == 'EML') type_str = "-Employees-";
                                    if(typeof childrents.data[key] == 'object' && childrents.data[key].name.search(type_str) > 0
                                       && childrents.data[key].name.search(resident_status) > 0 && childrents.data[key].currency == currencyID) {
                                        if(type_str == '-PEL-' || type_str == '-Employees-' || type_str == '-Managers-' || type_str == '-Shareholders-' || type_str == '-External Auditors-'){
                                            $("#parent_account").append('<option value="'+childrents.data[key].id+'"> '+childrents.data[key].name+' </option>');
                                               parent_account_duplicate();
                                        }else{
                                            if(Ownership == 'Groups' || Ownership == 'Indi' ){
                                                if(childrents.data[key].name.search(Ownership) > 0) {
                                                    $("#parent_account").append('<option value="'+childrents.data[key].id+'"> '+childrents.data[key].name+' </option>');
                                                parent_account_duplicate();
                                                }
                                            }else{// is not work yet finding another method
                                                if(childrents.data[key].name.search(Ownership) <= 0) {
                                                    $("#parent_account").append('<option value="'+childrents.data[key].id+'"> '+childrents.data[key].name+' </option>');
                                                    parent_account_duplicate();
                                                }
                                            }
                                        }
                                    }
                                }
                                $('#parent_account').val(auto_selected_parent_account).select2();
                                if(auto_selected_parent_account != ''){
                                    get_account_name();
                                }
                            }
                      }
                  }else{
                      if($.inArray($this.val(), LoanCategory) >= 0 ) {// this is filter for loan categories
                            if(!$.isEmptyObject(childrents.data) && typeof childrents.data != 'defined') {
                                var currencyID = $("#currency option:selected").val();
                                var Ownership  = $('#ownership option:selected').val(); //'Groups' => 'Group', 'Indi' => 'Individual', 'Corperations' => 'Corporation'
                                var Own =JSON.parse( '<?PHP $arr = array_keys($static['ownershipType']); echo json_encode($arr); ?>');
                                var resident_status = "-"+$("#resident option:selected").val();

                                $('#parent_account option').remove();
                                $('#parent_account').append('<option value="">---</option>');
                                //console.log($this.val());
                                for(var key in childrents.data) {
                                    var type_str = "-"+$this.val()+"-"
                                    if($this.val() == 'EML') type_str = "-Employees-";
                                    if(typeof childrents.data[key] == 'object' && childrents.data[key].name.search(type_str) > 0
                                       && childrents.data[key].name.search(resident_status) > 0 && childrents.data[key].currency == currencyID) {
                                        if(type_str == '-PEL-' || type_str == '-Employees-' || type_str == '-Managers-' || type_str == '-Shareholders-' || type_str == '-External Auditors-'){
                                            $("#parent_account").append('<option value="'+childrents.data[key].id+'"> '+childrents.data[key].name+' </option>');
                                            parent_account_duplicate();
                                        }else{
                                            if(Ownership == 'Groups' || Ownership == 'Indi' ){
                                                if(childrents.data[key].name.search(Ownership) > 0) {
                                                    $("#parent_account").append('<option value="'+childrents.data[key].id+'"> '+childrents.data[key].name+' </option>');
                                                    parent_account_duplicate();
                                                }
                                            }else{// is not work yet finding another method
                                                if(childrents.data[key].name.search(Ownership) <= 0) {
                                                    $("#parent_account").append('<option value="'+childrents.data[key].id+'"> '+childrents.data[key].name+' </option>');
                                                    parent_account_duplicate();
                                                }
                                            }
                                        }
                                    }
                                }
                                $('#parent_account').select2();
                            }
                      }
                  }

                }
            }
        });


    </script>
@endsection
