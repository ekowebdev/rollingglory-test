<?php
namespace App\Http\Services;

use App\Http\Models\User;
use Illuminate\Support\Str;
use App\Http\Traits\PassportToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Jobs\SendEmailVerificationJob;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Repositories\UserRepository;
use App\Http\Repositories\OauthRepository;
use App\Exceptions\AuthenticationException;
use App\Jobs\SendEmailTokenResetPasswordJob;

class AuthService extends BaseService
{  
    use PassportToken;

    private $model, $repository, $oauth_repository, $oauth_client_id;

    public function __construct(User $model, UserRepository $repository, OauthRepository $oauth_repository)
    {
        $this->model = $model;
        $this->repository = $repository;
        $this->oauth_repository = $oauth_repository;
        $this->oauth_client_id = env('OAUTH_CLIENT_ID');
    }

    public function register($locale, $request)
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'birthdate' => 'date',
            'username' => 'required|string|unique:users|max:255',
            'email' => 'required|string|email:rfc,dns|unique:users|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        DB::beginTransaction();
        $user = $this->model->create([
            'username' => $request['username'],
            'email' => $request['email'],
            'password' => Hash::make($request['password']),
        ]);
        $user->assignRole('customer');
        $user->profile()->create(['name' => $request['name'], 'birthdate' => $request['birthdate'] ?? null]);
        SendEmailVerificationJob::dispatch($user);
        DB::commit();

        return response()->json([
            'message' => trans('all.success_register'),
            'status' => 200,
            'error' => 0,
        ]);
    }

    public function login($locale, $request)
    {
        $this->validate($request, [
            'email' => 'required|string|email:rfc,dns|exists:users,email',
            'password' => 'required|string|min:6|max:12'
        ]);

        $user = $this->repository->getDataByMultipleParam(['email' => $request['email']]);

        if(empty($user)){
            throw new AuthenticationException(trans('auth.wrong_email_or_password'));
        }

        if (empty($user) OR !Hash::check($request['password'], $user->password, [])) {
            throw new AuthenticationException(trans('auth.wrong_email_or_password'));
        }

        $token_response = $this->getBearerTokenByUser($user, $this->oauth_client_id, false);

        return response()->json([
            'message' => trans('all.success_login'),
            'data' => [
                'users' => new UserResource($user),
                'token_type' => 'Bearer',
                'expires_in' => $token_response['expires_in'],
                'access_token' => $token_response['access_token'],
                'refresh_token' => $token_response['refresh_token'],
            ],
            'status' => 200,
            'error' => 0
        ]);
    }

    public function refresh_token($locale, $request)
    {
        $this->validate($request, [
            'refresh_token' => 'required|string',
        ]);

        $app_key = env('APP_KEY');
        $enc_key = base64_decode(substr($app_key, 7));

        try {
            $crypto = \Defuse\Crypto\Crypto::decryptWithPassword($request['refresh_token'], $enc_key);
        } catch (\Exception $e){
            throw new AuthenticationException(trans('error.failed_refresh_token'));  
        }
        $crypto = json_decode($crypto, true);

        $check_refresh_token = $this->oauth_repository->checkRefreshToken($crypto['refresh_token_id'], $crypto['access_token_id']);
        if(empty($check_refresh_token)){
            throw new AuthenticationException(trans('error.failed_refresh_token'));
        }
        $check_access_token = $this->oauth_repository->checkAccessToken($crypto['access_token_id']);

        if(empty($check_access_token)){
            throw new AuthenticationException(trans('error.failed_refresh_token'));  
        }

        $user = $this->repository->getSingleData($locale, $check_access_token['user_id']);

        if (empty($user)) {
            throw new AuthenticationException(trans('error.failed_refresh_token'));            
        }

        $check_refresh_token->update(['revoked' => 1]);
        $check_access_token->update(['revoked' => 1]);
                
        $token_response = $this->getBearerTokenByUser($user, $this->oauth_client_id, false);
        
        $data = [
            'message' => trans('all.success_refresh_token'),
            'data' => [
                'users' => new UserResource($user),
                'token_type' => 'Bearer',
                'expires_in' => $token_response['expires_in'],
                'access_token' => $token_response['access_token'],
                'refresh_token' => $token_response['refresh_token'],
            ],
            'status' => 200,
            'error' => 0
        ];

        return response()->json($data);
    }

    public function logout($locale)
	{
        auth()->user()->get_access_token()->delete();
        return response()->json([
                'message' => trans('all.success_logout'), 
                'status' => 200,
                'error' => 0,
            ]);
    }

    public function verify($id, $request)
    {
        if(!$request->hasValidSignature()){
            return response()->json([
                'message' => 'Gagal verifikasi email',
                'status' => 400,
                'error' => 0,
            ], 400);
        }

        $user = User::find($id);

        if(!$user->hasVerifiedEmail()){
            $user->markEmailAsVerified();
        }

        $url = env('FRONT_URL') . '/email-verification-success';

        return redirect()->to($url);
    }

    public function resend($locale, $request)
    {
        $this->validate($request, [
            'email' => 'required|string|email:rfc,dns|exists:users,email',
        ]);

        $user = $this->repository->getDataByMultipleParam(['email' => $request['email']]);

        if($user->email_verified_at != null){
            return response()->json([
                'message' => trans('error.already_verification'), 
                'status' => 409,
                'error' => 0,
            ], 409);
        }

        SendEmailVerificationJob::dispatch($user, $locale);

        return response()->json([
            'message' => trans('all.success_resend_verification'), 
            'status' => 200,
            'error' => 0,
        ]);
    }

    public function forget_password($locale, $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        DB::beginTransaction();

        DB::table('password_resets')->where('email', $request->email)->delete();

        $token = Str::random(16);

        $data['token'] = Hash::make($token);
        $data['email'] = $request->email;
        $data['created_at'] = date('Y-m-d H:i:s');

        DB::table('password_resets')->insert($data);

        SendEmailTokenResetPasswordJob::dispatch($request->email, $data, $locale);

        DB::commit();

        return response()->json([
            'message' => trans('all.success_send_reset_password_link'), 
            'status' => 200,
            'error' => 0,
        ]);
    }

    public function reset_password($locale, $request) 
    {
        $request->validate([
            'token' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $password_reset = DB::table('password_resets')->where('token', $request->token)->first();

        if($password_reset == null){
            return response()->json([
                'message' => trans('error.token_reset_password_is_invalid'),
                'status' => 422,
                'error' => 0,
            ], 422);
        }

        if (strtotime($password_reset->created_at) < strtotime('-60 minutes')) {
            DB::table('password_resets')->where('token', $request->token)->delete();
            return response()->json([
                'message' => trans('error.token_reset_password_is_expired'),
                'status' => 422,
                'error' => 0,
            ], 422);
        }

        DB::beginTransaction();

        $user = User::where('email', $password_reset->email)->first();

        $user->update(['password' => Hash::make($request->password)]);

        DB::table('password_resets')->where('email', $password_reset->email)->delete();

        DB::commit();

        return response()->json([
            'message' => trans('all.success_reset_password'), 
            'status' => 200,
            'error' => 0,
        ]);
    }

    public function redirect_to_google()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handle_google_callback()
    {
        try {
            $user = Socialite::driver('google')->stateless()->user();
            $existing_user = $this->model->where('email', $user->email)->first();
            if ($existing_user) {
                $token_response = $this->getBearerTokenByUser($existing_user, $this->oauth_client_id, false);
                return response()->json([
                    'message' => 'Login success',
                    'data' => [
                        'users' => new UserResource($existing_user),
                        'token_type' => 'Bearer',
                        'expires_in' => $token_response['expires_in'],
                        'access_token' => $token_response['access_token'],
                        'refresh_token' => $token_response['refresh_token'],
                    ],
                    'status' => 200,
                    'error' => 0
                ]);
            } else {
                DB::beginTransaction();
                $username = strstr($user->email, '@', true);
                $created_user = $this->model->create([
                    'username' => $username,
                    'email' => $user->email,
                    'password' => Hash::make('password'),
                    'email_verified_at' => date('Y-m-d H:i:s')
                ]);
                $created_user->assignRole('customer');
                $created_user->profile()->create(['name' => $user->name, 'avatar' => $user->avatar]);
                DB::commit();
                return response()->json([
                    'message' => 'Registration successful, please login to enter',
                    'status' => 200,
                    'error' => 0,
                ]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }
}
