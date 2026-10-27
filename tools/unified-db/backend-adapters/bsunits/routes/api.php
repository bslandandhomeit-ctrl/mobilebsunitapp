<?php

use Illuminate\Http\Request;

Route::post('/customers/register', 'Api\CustomerRegistrationController@store')->middleware(\App\Http\Middleware\ThrottleCustomerRegistration::class);

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
//Route Need Authentication 
Route::namespace("Api")->name("api.")->middleware(['auth:api', 'apiAccountEnabled'])->group( function () {
	Route::get('/whoami/home', 'HomeController@index')->name('user.home');
	Route::get('/whoami', "UserController@getAuthenticatedUser")->name('user.authenticated');		
	Route::get('/whoami/members', "UserController@getMembers")->name('user.authenticated.members');
	Route::get('/whoami/notifications', "UserController@getNotification")->name('user.authenticated.notifications');
	Route::post('/whoami/change_password', "UserController@changePassword")->name('user.authenticated.change_password');
	Route::post('/whoami/logout', "UserController@logout")->name('user.authenticated.logout');
	Route::put('/whoami', "UserController@update")->name("user.update");
	Route::put('/whoami/onesignal_player_id', "OnesignalController@updateAuthUserPlayerId")->name("user.update.onesignal_player_id");
	Route::get('/whoami/unit_hold_requests', 'UserController@getUnitHoldRequest')->name('user.relationship.unit_hold_request');

	// user route
	Route::get('/users/{id}', 'UserController@get')->name('user.show');
	Route::post('/users/{id}/verify', 'VerifyUserController@verifyRequest')->name('user.verify');
	Route::post('/users/{id}/activate', 'ActivateUserController@activateRequest')->name('user.activate');
	Route::get('/users/{id}/members', 'UserController@getMembers')->name('user.members');
	Route::get('/users/{id}/unitHoldRequests', 'UserController@getUnitHoldRequest')->name('user.unit_hold_request');	
	Route::get('/users/{id}/unitDepositRequests', 'UserController@getUnitDepositRequest')->name('user.unit_deposit_request');
	Route::get('/users/{id}/unitContractRequests', 'UserController@getUnitContractRequest')->name('user.unit_contract_request');
	Route::get('/users/{id}/unitRequestStatistic', 'UserController@getUnitRequestStatistic')->name('user.unit_request_statistic');
	//

	// Role Route
	Route::get('/roles', 'RoleController@index')->name('role.index');
	// End Role Route 

	// Notification
	Route::get('/notifications', 'NotificationController@index')
		 ->name('user.notification.index');
	Route::post('/notifications/{id?}', 'NotificationController@update')
		 ->name('user.notification.update');
	//

	Route::get('/unit_types', "UnitTypeController@all")->name('unit_type.all');
	Route::get('/unit_types/{id}', "UnitTypeController@get")->name('unit_type.get');
	Route::get('/unit_types/{unit_type_id}/payment_options', 'PaymentOptionController@getByUnitTypeId')->name('unit_type.get.payment_option');
	Route::post('/unit_types', "UnitTypeController@create")->name('unit_type.create');
	Route::put('/unit_types/{id}', "UnitTypeController@update")->name('unit_type.update');
	Route::delete('/unit_types/{id}', "UnitTypeController@remove")->name('unit_type.remove');
	Route::get("/unit_types/{id}/units", "UnitTypeController@getUnits")->name("unit_type.get.units");
	Route::get("/unit_types/{id}/discountPromotion", "UnitTypeController@getDiscountPromotion")->name("unit_type.get.discount_promotions");

	// Unit
	Route::get("/units", "UnitController@all")->name('units.all');
	Route::get('/units/{id}', 'UnitController@show')->name('units.show');
	Route::get('/units/{id}/actions', 'UnitActionController@getAllByUnitId')->name('units.actions.all');
	Route::get('/units/{id}/comments', 'UnitController@getComments')->name('units.comments.all');
	Route::post('/units/{id}/comments', 'UnitController@storeComment')->name('units.comments.store');
	// Route::get('/units/{id}/unit_construction', 'UnitController@getUnitConstruction')->name('units.unit_construction.get');
	// Route::post('/units/{id}/unit_construction', 'UnitController@storeUnitConstruction')->name('units.unit_construction.store')
	// 	 ->middleware(['permission:create-unit-construction','permission:update-unit-construction']);
	Route::get('/units/{unit_id}/construction_procedures', 'UnitConstructionProcedureController@get')->name('units.construction_procedure.get');
	Route::post('/units/{unit_id}/construction_procedures/{id}', 'UnitConstructionProcedureController@update')->name('units.construction_procedure.update');
	Route::get('/units/report/availabilityStatistic', 'UnitController@getAvailabilityStatistic')
		   ->name('units.report.availability_statistic')
		   ->middleware(['role:contract_controller|sale_manager|report|administrator']);
	// End Unit

	// Unit Action
	Route::get('/unit_actions', "UnitActionController@get")->name('unit_actions.index');
	Route::get('/unit_requests/statistic', "UnitActivityController@getStatistic")
		 ->name('unit_request.statistic')
		 ->middleware(['role:contract_controller|sale_manager|report|administrator']);
	// End Unit Action

	// Unit Hold Request
	Route::get("/unit_hold_requests", "UnitHoldController@get")->name('unit_hold_request.all');
	Route::get("/unit_hold_requests/{id}", "UnitHoldController@show")->name('unit_hold_request.show');
	Route::post("/unit_hold_requests", "UnitHoldController@hold")->name('unit_hold_request.create');
	Route::post("/unit_hold_requests/{id}/approve", "UnitHoldController@approve")->name('unit_hold_request.approve');
	Route::post("/unit_hold_requests/{id}/cancel", "UnitHoldController@cancel")->name('unit_hold_request.cancel');
	Route::post("/unit_hold_requests/{id}/reject", "UnitHoldController@reject")->name('unit_hold_request.reject');
	// End Unit Hold Request

	// Unit Hold Request
	Route::get("/unit_deposit_requests", "UnitDepositRequestController@all")->name('unit_deposit_request.all');
	Route::get("/unit_deposit_requests/customer-capabilities", "UnitDepositRequestController@customerCapabilities");
	Route::get("/unit_deposit_requests/{id}", "UnitDepositRequestController@get")->name('unit_deposit_request.show');
	Route::post("/unit_deposit_requests", "UnitDepositRequestController@create")->name('unit_deposit_request.create');
	Route::put("/unit_deposit_requests/{id}", "UnitDepositRequestController@update")->name('unit_deposit_request.update');
	Route::post("/unit_deposit_requests/{id}/approve", "UnitDepositRequestController@approve")->name('unit_deposit_request.approve');
	Route::post("/unit_deposit_requests/{id}/reject", "UnitDepositRequestController@reject")->name('unit_deposit_request.reject');	
	Route::post("/unit_deposit_requests/{id}/cancel", "UnitDepositRequestController@cancel")->name('unit_deposit_request.cancel');
	Route::post("/unit_deposit_requests/{id}/changeUnit", "UnitDepositRequestController@changeUnit")->name('unit_deposit_request.cancel');
	// End Unit Hold Request

	// Unit Contract Request
	Route::get('/unit_contract_requests', "UnitContractRequestController@index")->name('unit_contract_request.index');
	Route::get('/unit_contract_requests/{id}', "UnitContractRequestController@show")->name('unit_contract_request.show');
	Route::post('/unit_contract_requests', "UnitContractRequestController@create")->name('unit_contract_request.create');	
	Route::PUT('/unit_contract_requests/{id}', "UnitContractRequestController@update")->name('unit_contract_request.update');
	Route::post('/unit_contract_requests/{id}/cancel', "UnitContractRequestController@cancel")->name('unit_contract_request.cancel');
	// End Unit Contract Request

	// Contract Request
	Route::get('/contract/{id}/pdf', "ViewContractPdfController@get")->name('contract.view.pdf');
	// End Contract Request 

	// Payment Option
	Route::get('/payment_options', "PaymentOptionController@all")->name('payment_option.all');
	Route::get('/payment_options/{id}', "PaymentOptionController@get")->name('payment_option.get');
	Route::post('/payment_options', "PaymentOptionController@create")->name('payment_option.create');
	Route::put('/payment_options/{id}', "PaymentOptionController@update")->name('payment_option.update');
	Route::delete('/payment_options/{id}', "PaymentOptionController@remove")->name('payment_option.remove');

	Route::get('/projects', "ProjectController@all")->name('project.all');
	Route::get('/projects/{id}', "ProjectController@get")->name('project.get');
	Route::get('/projects/{id}/units_for_plan', "ProjectController@getUnitsForFloorPlan")->name('project.units.index');
	Route::get('/projects/{project_id}/unit_type', "UnitTypeController@getByProjectId")->name('project.get.unit_type');
	Route::get('/projects/{project_id}/unit_types', "UnitTypeController@getByProjectId");
	Route::get('/projects/{project_id}/units', "ProjectController@getUnits");
	Route::post('/projects', "ProjectController@create")->name('project.create');
	Route::put('/projects/{id}', "ProjectController@update")->name('project.update');
	Route::delete('/projects/{id}', "ProjectController@remove")->name('project.remove');	

	Route::get('/contract_requests', "ContractRequestController@all")->name('contract_request.all');
	Route::get('/contract_requests/{id}', "ContractRequestController@get")->name('contract_request.get');
	Route::get('/contract_requests/{contract_request_id}/attachments', "ContractRequestAttachmentController@getByContractRequestId")
		 ->name('contract_request.get.attachments');
	Route::get('/contract_requests/reference_data', "ContractRequestController@getReferenceData")->name('contract_request.reference_data');
	Route::post('/contract_requests', "ContractRequestController@create")->name('contract_request.create');
	Route::put('/contract_requests/{id}', "ContractRequestController@update")->name('contract_request.update');
	Route::put('/contract_requests/{id}/sale_manager_edit', "ContractRequestController@updateBySaleManager")->name('contract_request.update');
	Route::post('/contract_requests/{id}/approve', "ContractRequestController@approve")->name('contract_request.approve');
	Route::post('/contract_requests/{id}/reject', "ContractRequestController@reject")->name('contract_request.approve');

	// Miscellanous Route
	Route::get('/identifications', "UserIdentificationController@all")->name('identification.all');

	Route::get('/sale_representatives', 'SaleRepresentativeController@index')->name('sale_representative.all');
	Route::get('/sale_representatives/{id}', 'SaleRepresentativeController@show')->name('sale_representative.all');

	// Project Statistic
	Route::get('/project_statistics', 'ProjectStatisticController@index')->middleware('role:report');
	Route::get('/project_statistics/{project_id}', 'ProjectStatisticController@show')->middleware('role:report');
	Route::get('/project_statistics/{project_id}/units_by_status', 'ProjectStatisticController@filter')->middleware('role:report');

	// Zone	
	Route::get('/zones/{id}/site_engineers', 'ZoneController@getSiteEngineers')->name('zones.site_engineers');

	Route::get('/purchase_requests/{id}/comments', 'PurchaseRequestController@getComments')->name('purchase_requests.comments.index');
	Route::post('/purchase_requests/{id}/comments', 'PurchaseRequestController@storeComment')->name('purchase_requests.comments.store');

	Route::get('/unit_handovers/{id}/comments', 'UnitHandoverController@getComments')->name('unit_handovers.comments.index');
	Route::post('/unit_handovers/{id}/comments', 'UnitHandoverController@storeComment')->name('unit_handovers.comments.store');

	// Audit Log
	Route::get('/audits', 'AuditController@index')->name('audits.all');
	// End Audit Log 
});

//Route no Need Authentication
Route::namespace("Api")->name("api.")->group( function () {
	Route::get('/app_version/{platform}/latestBuild', "AppVersionController@getLastestBuild")->name('app_version.latest_build');

	Route::post('/users', "UserController@create")->name('user.create');
	Route::get('/users/roles/sale_team_leader', "UserController@getSaleTeamLeaders")->name('user.roles.sale_team_leader');
	Route::get('/users/roles/site_manager', "UserController@getSiteManagers")->name('user.roles.site_manager');

	// Reset Password Route
	Route::post('/users/forgetPassword', "Password\ForgetPasswordController@sendSmsCode")
		 ->name('password.forget');

	Route::post('/users/generateResetPasswordToken', "Password\ForgetPasswordController@getResetToken")
	     ->name('password.token');

	Route::post('/users/resetPassword', "Password\ResetPasswordController@reset")
	     ->name('password.reset');

	Route::get('/banners', "BannerController@index")
		 ->name('banners.index');

	Route::get('/posts', 'PostController@index')
		 ->name('posts.index');

	Route::get('/posts/{id}', 'PostController@get')
		 ->name('posts.show');
 	Route::get('/unit_actions/deposit', 'UnitActionController@createDeposit')->name('unit_actions.createDeposit');
	Route::get('/unit_actions/panding_contract', 'UnitActionController@createPendingContract')->name('unit_actions.createPendingContract');
	Route::get('/unit_actions/contract', 'UnitActionController@createContract')->name('unit_actions.createContract');
	Route::get('/unit_actions/available', 'UnitActionController@createAvailable')->name('unit_actions.createAvailble');

	//Route requst from BE-Loan System
    Route::get('/unit_actions/change_unit_statu_from_be_cash', 'UnitActionController@changeUnitStatusFromBECash')->name('unit_actions.changeUnitStatusFromBECash');
});

// Unified canonical catalog: authenticated, read-only, disabled by default.
Route::namespace('Api')->middleware(['auth:api', 'apiAccountEnabled'])->prefix('unified/catalog')->group(function () {
    Route::get('/projects', 'UnifiedCatalogController@projects');
    Route::get('/unit_types', 'UnifiedCatalogController@unitTypes');
    Route::get('/units', 'UnifiedCatalogController@units');
    Route::get('/units/{id}', 'UnifiedCatalogController@show')->where('id', '[0-9]+');
});
