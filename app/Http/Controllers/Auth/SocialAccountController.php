<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SocialAccountController extends Controller
{
    protected $permission;
    protected $field;

    public function __construct(Request $request)
    {
        session_start();
        $this->setProviderDetail($request->route('provider'));
    }

    /**
     * Redirect the user to the GitHub authentication page.
     *
     * @return Response
     */
    public function redirectToProvider($provider)
    {
        return \Socialite::driver($provider)->scopes($this->permission)->redirect();
    }

    /**
     * Obtain the user information
     *
     * @return Response
     */
    public function handleProviderCallback(\App\SocialAccountService $accountService, $provider)
    {
        $handleCallback = 'handle'.ucfirst($provider).'Callback';
        try {
            $user = \Socialite::with($provider)->user();
        } catch (\Exception $e) {
            return $e;
        }

        try {
            $this->$handleCallback($user);
        } catch (\Exception $e) {
            return redirect('/login'); // permission not granted, should redirect to explain page
        }

        $authUser = $accountService->findOrCreate(
            $user,
            $provider
        );

        auth()->login($authUser, true);

        return redirect()->to('/home');
    }

    protected function setProviderDetail($provider)
    {
        switch ($provider) {
            case 'facebook':
                $this->permission = json_decode(env('FACEBOOK_PERMISSION'), true);
                $this->field = json_decode(env('FACEBOOK_FIELD'), true);
                break;

            case 'line':
                $this->permission = json_decode(env('LINE_PERMISSION'), true);
                $this->field = json_decode(env('LINE_FIELD'), true);
                break;

            default:
                throw new \Exception("Wrong provider", 1);
                break;
        }
    }

    protected function handleFacebookCallback(\Laravel\Socialite\Two\User $user)
    {
        $this->isFacebookPermissionGranted($user);
    }

    protected function isFacebookPermissionGranted(\Laravel\Socialite\Two\User $user) :bool
    {
        $declinedPermission = [];
        if (!empty($user['permissions']['data'])) {
            foreach ($user['permissions']['data'] as $permission) {
                if ($permission['status'] == 'declined') {
                    array_push($declinedPermission, $permission['permission']);
                }
            }
        }

        return empty(array_intersect($declinedPermission, $this->permission));
    }

    protected function handleLineCallback(\Laravel\Socialite\Two\User $user)
    {
        // $this->isFacebookPermissionGranted($user);
    }
}
