<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SocialAccountController extends Controller
{
    protected $permission;
    protected $field;

    public function __construct()
    {
        $this->permission = json_decode(env('FACEBOOK_PERMISSION'), true);
        $this->field = json_decode(env('FACEBOOK_FIELD'), true);
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
        try {
            $user = \Socialite::with($provider)->fields($this->field)->user();
        } catch (\Exception $e) {
            return redirect('/login');
        }

        // check if permission granted
        $declinedPermission = [];
        foreach ($user['permissions']['data'] as $permission) {
            if ($permission['status'] == 'declined') {
                array_push($declinedPermission, $permission['permission']);
            }
        }

        if (!empty(array_intersect($declinedPermission, $this->permission))) {
            return redirect('/login');
        }

        $authUser = $accountService->findOrCreate(
            $user,
            $provider
        );

        auth()->login($authUser, true);

        return redirect()->to('/home');
    }
}
