<?php
namespace App\Http\Controllers\Api;

use App\User;
use App\Services\FirebasePhoneIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CustomerRegistrationController extends \App\Http\Controllers\Controller
{
    public function store(Request $request, FirebasePhoneIdentity $firebase)
    {
        if (!config('customer_registration.enabled', false)) {
            return response()->json(['message' => 'Customer registration is not available yet.'], 503);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:191',
            'phone_number' => ['required', 'regex:/^0[1-9][0-9]{7,8}$/', 'unique:users,phone_number'],
            'email' => 'nullable|email|max:255|unique:users,email',
            'gender' => 'required|in:Male,Female',
            'birthdate' => 'required|date|after_or_equal:1900-01-01|before_or_equal:today',
            'password' => 'required|string|min:8|max:32|confirmed',
            'firebase_id_token' => 'required|string|max:8192',
            'avatar' => 'nullable|image|max:5120|dimensions:max_width=1024,max_height=1024',
        ]);
        if ($validator->fails()) return response()->json(['message' => $validator->errors()->first()], 422);
        try {
            $identity = $firebase->verify($request->input('firebase_id_token'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Phone verification is temporarily unavailable.'], 503);
        }
        if (!hash_equals($identity['phone_number'], $request->input('phone_number'))) {
            return response()->json(['message' => 'Verify the phone number used for registration.'], 422);
        }
        $avatar = null;
        try {
            if ($request->hasFile('avatar')) $avatar = $request->file('avatar')->store('user_avatar', 'public');
            DB::transaction(function () use ($request, $identity, $avatar) {
                $user = new User();
                $user->name = $request->input('name');
                $user->phone_number = $identity['phone_number'];
                $user->email = $request->input('email');
                $user->gender = $request->input('gender');
                $user->birthdate = $request->input('birthdate');
                $user->password = bcrypt($request->input('password'));
                $user->avatar = $avatar ?: '';
                $user->active = true;
                $user->verified = true;
                $user->need_change_password = false;
                $user->metadata = ['firebase_uid' => $identity['uid'], 'account_type' => 'customer'];
                $user->save();
                // Ignore all submitted roles, permissions, managed_by and account flags.
                $role = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
                $user->syncRoles([$role]);
            });
        } catch (\Throwable $e) {
            if ($avatar) \Illuminate\Support\Facades\Storage::disk('public')->delete($avatar);
            if ($e instanceof \Illuminate\Database\QueryException && (string) $e->getCode() === '23000') {
                return response()->json(['message' => 'An account already exists for this phone or email. Please log in.'], 422);
            }
            report($e);
            return response()->json(['message' => 'Unable to create your account. Please try again.'], 500);
        }
        return response()->json(['message' => 'Customer account created.'], 201);
    }
}
