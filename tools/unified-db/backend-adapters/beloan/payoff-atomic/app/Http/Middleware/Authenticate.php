<?php 
namespace App\Http\Middleware;

use App\Models\Accessible;
use Closure;
use Illuminate\Contracts\Auth\Guard;
//use Illuminate\Support\Facades\Auth;
use Auth;

class Authenticate {

	/**
	 * The Guard implementation.
	 *
	 * @var Guard
	 */
	protected $auth;

	/**
	 * Create a new filter instance.
	 *
	 * @param  Guard  $auth
	 * @return void
	 */
	public function __construct(Guard $auth)
	{
		$this->auth = $auth;
	}

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
        if ($this->auth->guest())
		{
			if ($request->ajax())
			{
                return response('Unauthorized.', 401);
			}
			else
			{
				return redirect()->guest('user/login');
			}
		}else{
            if ((int)$this->auth->user()->status !== 1) {
                $this->auth->logout();
                if ($request->ajax()) return response('Unauthorized.', 401);
                return redirect()->guest('user/login');
            }
            if(!in_array($this->auth->user()->role_id,[1,2])){
                $acc = $this->accessible(Auth::user()->login_ip);
                if(!$acc){
					dd($acc . ' > unaccessable');
                    $this->auth->logout();
                    return redirect()->route('login');
                }
            }
            $default = ['home','permission','logout','user_profile','user_setting','change_pass','upload_user','locale',
                'getLatestRate','getExchangeRate','getExchangeRateJD','enable_till','accrued_verify', 'ajax_add_bank',
                'ajax_add_role', 'do_ajax_upload', 'ajax_disburse', 'reset_session', 'print_deposit', 'print_withdraw'];
            $static = config('static_data');
            $ajax_permission = [];
            if(isset($static['ajax_permission'])){
                $ajax_permission = $static['ajax_permission'];
            }
            $default = array_merge($default,$ajax_permission);
            if($request->ajax()){
                $action = $request->route()->getAction();
                if(!empty($action)){
                    $action_name = isset($action['0']) ? $action['0'] : (isset($action['as']) ? $action['as'] : null);
                    if(!empty($action_name)){
                        if(!in_array($action_name,$default)){
                            $user = $this->auth->user();
                            if(!$user->checkPermission($action_name)){
                                return response('No permission access.', 401);
                            }
                        }
                    }else{
                        return response('No permission access.', 401);
                    }
                }else{
                    return response('No permission access.', 401);
                }
            }else{
                $action_name = $request->route()->getName();
                if(!empty($action_name)){
                    if(!in_array($action_name,$default)){
                        $user = $this->auth->user();
                        if(!$user->checkPermission($action_name)){
                            $request->session()->put('NO_PERMISSION',true);
                            return redirect()->route('permission');
                        }
                    }
                }else{
                    return redirect('/');
                }
            }
        }
		return $next($request);
	}
    private function accessible($ip)
    {
        $access = Accessible::whereRaw('INET_ATON(\''.$ip.'\') = ip_start_range')->first();
        if(!empty($access)) return true;
        else{
            $access = Accessible::count();
            if($access > 0) return false;
        }
        return true;
    }
}