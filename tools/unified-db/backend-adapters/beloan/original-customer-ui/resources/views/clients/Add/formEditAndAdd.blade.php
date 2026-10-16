<section class="panel">
    <?php
    $branch = config('static_data.branch');
    $client_type = config('static_data.client_type');
    $letter_type = config('static_data.letter_type');
    $gender = config('static_data.gender');
    $address_type = config('static_data.address_type');
    $marital_status = config('static_data.marital_status');
    $country_code = config('static_data.country_code');
    $salutation = config('static_data.salutation');
    $applicant_type = config('static_data.applicant_type');
    ?>
    <header class="panel-heading">
        {{ trans('sidebar.sb_add_client') }}
        <button id="checkUser" class="btn btn-info btn-sm" style="float: right;margin-top:-5px;">{{ trans('customer.ifExist') }}</button>
    </header>

    <form action="{{route('add_client')}}" method="POST" class="cmxform form-horizontal" id="clientForm"
          enctype="multipart/form-data" onsubmit="return false">
        @if(config('unified_customer_ui.enabled') && isset($clientData) && is_object($clientData))<input type="hidden" name="_unified_customer_version" value="{{ App\Services\OriginalCustomerUi::version($clientData->id) }}">@endif
        <div class="panel-body text-left">
            <!--Customer Information -->
            <?PHP
            //dd($clientData);
            $generals = isset($clientData->general) ? $clientData->general : [0];
            foreach($generals as $general): ;?>
            <div class="col-lg-10">
                <div class="col-lg-6">
                    <fieldset>

                        <div class="form-group">
                            <label class="control-label col-lg-5">{{trans('multiple.en')}} {{trans('customer.familyName')}} <i class="red">
                                    * </i></label>
                            <div class="col-lg-6">
                                <input type="text" name="family_name_en" value="{{$general->family_name}}"
                                       class="form-control" ​ placeholder=" ">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label col-lg-5">{{trans('multiple.en')}} {{ trans('customer.firstName') }} <i class="red">
                                    * </i></label>
                            <div class="col-lg-6">
                                <input type="text" name="first_name_en" value="{{$general->first_name}}"
                                       class="form-control" placeholder="">
                            </div>

                        </div>

                        <div class="form-group">
                            <label class="control-label col-lg-5">{{trans('multiple.kh')}} {{trans('customer.familyName')}} <i class="red">
                                    * </i></label>
                            <div class="col-lg-6">
                                <input type="text" name="family_name_kh" value="{{$general->family_name_kh}}"
                                       class="form-control" placeholder="">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label col-lg-5">{{trans('multiple.kh')}} {{ trans('customer.firstName') }} <i class="red">
                                    * </i></label>
                            <div class="col-lg-6">
                                <input type="text" name="first_name_kh" value="{{$general->first_name_kh}}"
                                       class="form-control" placeholder="">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label col-sm-5">{{ trans('multiple.m_gender') }} <i class="red">
                                    * </i></label>
                            <div class="col-lg-6">
                                <select class="" name="gender" style="width: 100%">
                                    <option value="">-</option>
                                    @foreach($gender as $key=>$value)
                                        @if(trim(strtoupper($general->gender)) == trim(strtoupper($key)))
                                            <option value="{{$key}}" selected>{{$value}}</option>
                                        @else
                                            <option value="{{$key}}">{{$value}}</option>
                                        @endif

                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label col-sm-5">{{ trans('customer.cus_birth_date') }} <i class="red">
                                    * </i></label>
                            <div data-date-viewmode="years" data-initialize="datepicker"
                                 class="input-append date dpYears col-md-6">
                                <input type="text" name="date_of_birth" value="{{UnEmptyDate($general->date_of_birth)}}"
                                       size="16" class="form-control"/>
                                <span class="add-on ">
                                    <button class="btn btn-primary" type="button"><i
                                                class="fa fa-calendar"></i></button>
                                </span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label col-sm-5"> {{ trans('customer.cus_nationality') }} <i class="red">
                                    * </i></label>
                            <div class="col-lg-6">
                                <select id="national_code" name="national_code" style="width: 100%">
                                    <option value="">-</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label col-sm-5">{{ trans('customer.maritalStatus') }} <i class="red">
                                    * </i></label>
                            <div class="col-lg-6">
                                <select class="" name="marital_status" id="marital_status" style="width: 100%">
                                    <option value="">-</option>
                                    @foreach($marital_status as $key => $value)
                                        @if(mb_strtoupper($general->marital_status) === strtoupper($key))
                                            <option value="{{ $value }}" selected>{{ $value }}</option>
                                        @else
                                            <option value="{{ $value }}">{{ $value }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label col-sm-5">{{ trans('customer.applicant_type') }}<i class="red">
                                    * </i> </label>
                            <div class="col-lg-6">
                                <select class="" name="applicant_type" id="applicant_type" style="width: 100%">
                                    <option value="">-</option>
                                    @foreach($applicant_type as $key => $value)
                                        @if(strtoupper($general->applicant_type) == strtoupper($key))
                                            <option value="{{ $key }}" selected>{{ $value }}</option>
                                        @else
                                            <option value="{{ $key }}">{{ $value }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <?PHP $account_cbc_type = config('static_data.account_cbc_type'); ?>
                        <div class="form-group">
                            <label class="control-label col-sm-5">{{ trans('customer.account_cbc_type') }}<i
                                        class="red"> * </i> </label>
                            <div class="col-lg-6">
                                <select name="account_cbc_type" style="width: 100%">
                                    <option value="">-</option>
                                    @foreach($account_cbc_type as $key => $value)
                                        @if(strtoupper($clientData->account_cbc_type) == strtoupper($key))
                                            <option value="{{$key}}" selected>{{$value}}</option>
                                        @else
                                            <option value="{{ $key }}">{{ $value }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <!--account_cbc_type-->
                        <!--
                        <div class="form-group">
                            <label class="control-label col-sm-5">{{ trans('customer.taxpayer_reg_no') }}<i class="red"> * </i> </label>
                            <div class="col-lg-6">
                                <input type="text" name="taxpayer_reg_no" class="form-control" />
                            </div>
                        </div>
                        -->
                        <div class="form-group">
                            <label class="control-label col-sm-5">{{ trans('customer.resident') }}</label>
                            <div class="col-lg-6">
                                <?PHP
                                $check;
                                $val = 'N';
                                if(!$general){
                                    $val = 'Y';
                                    $check = 'checked';
                                }
                                if (strtoupper($clientData->resident) == 'Y') {
                                    $check = 'checked';
                                    $val = 'Y';
                                }?>
                                <input type="checkbox" id="resident" name="resident" class="form-control"
                                       {{$check}} value="{{$val}}" style="height:32px; width: 32px;"/>
                            </div>
                        </div>

                    </fieldset>
                </div>
                <div class="col-lg-6">

                    <fieldset>
                        <!--Country of birth-->
                        <div class="form-group">
                            <label class="control-label col-sm-5"> {{ trans('customer.country_of_birth') }} <i class="red">
                                    * </i></label>
                            <div class="col-lg-6">
                                <select name="country_of_birth" id="country_of_birth" style="width: 100%">
                                    <option value="">-</option>
                                    <?PHP if(!empty($general->country)):?>
                                    <option value="{{$general->country->id}}" selected data-code="{{$general->country->iso_code_3}}">
                                        {{$general->country->description[1]['name']}}
                                    </option>
                                    <?PHP endif;?>
                                </select>
                            </div>
                        </div>

                        <?PHP ?>
                        <div class="form-group">
                            <label class="control-label col-sm-5"> {{ trans('customer.province_of_birth') }} </label>
                            <div class="col-lg-6">
                                <select class="" id="province_of_birth" name="province_of_birth" style="width: 100%">
                                    <?PHP if(!empty($general->province)):?>
                                    <option value="{{$general->province->prov_gis}}">{{$general->province->eng_name}}</option>
                                    <?PHP endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label col-sm-5"> {{ trans('customer.district_of_birth') }} </label>
                            <div class="col-lg-6">
                                <select class="" id="district_of_birth" name="district_of_birth" style="width: 100%">
                                    <?PHP if(!empty($general->district)):?>
                                    <option value="{{$general->district->distr_gis}}">{{$general->district->eng_name}}</option>
                                    <?PHP endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label col-sm-5"> {{ trans('customer.commune_of_birth') }} </label>
                            <div class="col-lg-6">
                                <select class="" id="commune_of_birth" name="commune_of_birth" style="width: 100%">
                                    <?PHP if(!empty($general->commune)):?>
                                    <option value="{{$general->commune->comm_gis}}">{{$general->commune->en_name}}</option>
                                    <?PHP endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label col-sm-5"> {{ trans('customer.village_of_birth') }} </label>
                            <div class="col-lg-6">
                                <select class="" id="village_of_birth" name="village_of_birth" style="width: 100%">
                                    <?PHP if(!empty($general->village)):?>
                                    <option value="{{$general->village->vill_gis}}">{{$general->village->en_name}}</option>
                                    <?PHP endif; ?>
                                </select>
                            </div>
                        </div>

                        <!--End of Country  -->
                        <?PHP $user = isset($clientData->user) ? $clientData->user : [0];?>
                        <div class="form-group">
                            <label class="control-label col-sm-5">{{ trans('customer.officer') }} <i class="red">
                                    * </i></label>
                            <div class="col-lg-6">
                                <select class="" name="officer_id" style="width: 100%">
                                    <option value="">-</option>
                                    @foreach($officer as $vals)
                                        @foreach($vals as $users)
                                            @if (((int)$user->id )=== ((int)$users['id']))
                                                <option value="{{$users['id']}}"
                                                        selected>{{$users['username']}}</option>
                                            @else
                                                <option value="{{$users['id']}}">{{$users['username']}}</option>
                                            @endif
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label col-sm-5">{{ trans('customer.address') }}</label>
                            <div class="col-lg-6">
                                <input type="text" name="place_of_birth_adds" value="{{$client->place_of_birth_adds}}"
                                       class="form-control" disabled/>
                            </div>
                        </div>
                    </fieldset>
                </div>
            </div>

            <?PHP endforeach;?>
            <div class="col-lg-4">

            </div>

        </div>
        <!--Customer Location-->
        <div class="panel-body">
            <div id="exTab2">
                <ul class="nav nav-tabs">
                    <li class="active"><a data-target="#1" data-toggle="tab"> Current Address<i class="red"> * </i> </a>
                    </li>
                    <li><a data-target="#contact" data-toggle="tab">Contact <i class="red"> * </i> </a></li>
                    <li><a data-target="#gIn" data-toggle="tab">Identification<i class="red"> * </i> </a></li>
                    <li><a data-target="#photo" data-toggle="tab">ID Back & ID Front <i class="red"> * </i> </a></li>
                    <li><a data-target="#Audit" data-toggle="tab"> Audit </a></li>
                </ul>
                <div class="tab-content">
                {{--current address --}}
                @include('clients.Add.Caddress')
                {{--contact--}}
                @include('clients.Add.contact')
                <!--Identification-->
                @include('clients.Add.identification')
                @include('clients.Add.employer')
                    <!--Solution-->
                    <div class="tab-pane" id="3">
                        <h3>add clearfix to tab-content (see the css)</h3>
                    </div>

                    <!--Photo-->
                    <div class="tab-pane" id="photo">
                        <div class="form-group">

                            <div class="col-lg-3">
                                <label>{{ trans('customer.id_back') }}</label>
                                <div class="fileupload fileupload-new" data-provides="fileupload">
                                    <div class="fileupload-new thumbnail" style="width: 200px; height: 150px;">
                                        <?PHP
                                        $files = '';
                                        if (!empty($general->photo)) {
                                            $files = '/data/clients/' . $general->photo;
                                        }
                                        ?>
                                        <img src="{{ asset(isset($files)?$files:'images/noimage.gif',isset($secure)?false:false) }}" alt=""/>
                                    </div>
                                    <div class="fileupload-preview fileupload-exists thumbnail"
                                         style="max-width: 200px; max-height: 150px; line-height: 20px;"></div>
                                    <div>
                                       <span class="btn btn-white btn-file">
                                       <span class="fileupload-new"><i class="fa fa-paper-clip"></i>{{ trans('multiple.m_select_image') }}</span>
                                       <span class="fileupload-exists"><i class="fa fa-undo"></i> {{ trans('multiple.m_change') }}</span>
                                       <input type="file" name="photo" value="{{$general->photo}}" id="photo"
                                              class="default"/>
                                       </span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3">
                                <label>{{ trans('customer.id_front') }}</label>
                                <div class="fileupload fileupload-new" data-provides="fileupload">
                                    <div class="fileupload-new thumbnail" style="width: 200px; height: 150px;">
                                        <?PHP
                                        $files = '';
                                        if (!empty($general->signature)) {
                                            $files = '/data/signatures/' . $general->signature;
                                        }
                                        ?>
                                        <img src="{{ asset(isset($files)?$files:'images/noimage.gif', isset($secure)?false:false) }}"
                                             alt=""/>
                                    </div>
                                    <div class="fileupload-preview fileupload-exists thumbnail"
                                         style="max-width: 200px; max-height: 150px; line-height: 20px;"></div>

                                    <div>
                                        <span class="btn btn-white btn-file">
                                            <span class="fileupload-new"><i class="fa fa-paper-clip"></i>{{ trans('multiple.m_select_image') }}</span>
                                                    <span class="fileupload-exists"><i class="fa fa-undo"></i> {{ trans('multiple.m_change') }}</span>
                                                    <input type="file" name="signature" value="{{$general->signature}}"
                                                           id="signature" class="default"/>
                                                    </span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    @include('clients.audit.audit')
                </div>
            </div>
        </div>
        <div class="panel-body text-right">
            <input type="submit" value="Submit" class="btn btn-group-xs btn-info"/>
            <!--            --><?PHP //if(!$general->id): ?>
            <input type="button" value="Save Draft" id="saveDraft" class="btn btn-group-xs btn-warning"
                   onclick="return false"/>
            <!--            --><?PHP //endif; ?>
        </div>
    </form>
    <!--Models for check user-->
    <div id="ModelCheck" class="modal fade" role="dialog">
        <div class="modal-dialog" style="width: 70%">
            <!-- Modal content-->
            <form id="form_user" class="form-horizontal" method="get" action="{{route('check_client')}}">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Check existing customers </h4>
                    </div>
                    <div class="modal-body">
                        <div class="panel-body">
                            <div class="col-lg-12">
                                <div class="row text-center">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="control-label col-lg-5">Customer name (English) </label>
                                            <div class="col-lg-7">
                                                <input class="form-control" name="name_en" value="" type="text"/>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label col-lg-5">Customer name (Khmer)</label>
                                            <div class="col-lg-7">
                                                <input class="form-control" name="name_kh" value="" type="text" placeholder=""/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="control-label col-lg-5">Date of Birth </label>
                                            <div data-date-viewmode="years" data-initialize="datepicker" class="input-append date dpYears col-md-7">
                                                <input type="text" name="db" value="" size="16" class="form-control"/>
                                                <span class="add-on">
                                                    <button class="btn btn-primary" type="button"><i class="fa fa-calendar"></i></button>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label col-lg-5">Identification Number (ID)</label>
                                            <div class="col-lg-7">
                                                <input class="form-control" name="ident_id" value="" type="text" placeholder=""/>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="resultS">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="submit" class="btn btn-success save" value="Submit"/>
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>