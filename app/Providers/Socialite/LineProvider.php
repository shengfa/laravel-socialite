<?php

namespace App\Providers\Socialite;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use GuzzleHttp\ClientInterface;

class LineProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The base Facebook Graph URL.
     *
     * @var string
     */
    protected $apiUrl = 'https://api.line.me';

    /**
     * The Graph API version for the request.
     *
     * @var string
     */
    protected $version = 'v2.1';

    /**
     * The user fields being requested.
     *
     * @var array
     */
    protected $fields = ['name', 'email', 'gender', 'verified', 'link'];

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['profile', 'openid'];

    public function __construct(Request $request, $clientId, $clientSecret, $redirectUrl, $guzzle = [])
    {
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl, $guzzle = []);
        $this->scopeSeparator = ' ';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://access.line.me/oauth2/'.$this->version.'/authorize', $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return $this->apiUrl.'/oauth2/'.$this->version.'/token';
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenResponse($code)
    {
        $postKey = (version_compare(ClientInterface::VERSION, '6') === 1) ? 'form_params' : 'body';

        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            $postKey => $this->getTokenFields($code),
        ]);

        $data = json_decode($response->getBody(), true);

        return Arr::add($data, 'expires_in', Arr::pull($data, 'expires'));
    }

    /**
     * {@inheritdoc}
     */
    public function user()
    {
        if ($this->hasInvalidState()) {
            throw new InvalidStateException;
        }

        $response = $this->getAccessTokenResponse($this->getCode());

        $user = $this->mapUserToObject($this->getUserByToken(
            $token = Arr::get($response, 'token_type') . ' ' . Arr::get($response, 'access_token')
        ));

        return $user->setToken($token)
                    ->setRefreshToken(Arr::get($response, 'refresh_token'))
                    ->setExpiresIn(Arr::get($response, 'expires_in'));
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $meUrl = $this->apiUrl.'/v2/profile?access_token='.$token.'&fields='.implode(' ', $this->fields);

        if (! empty($this->clientSecret)) {
            $appSecretProof = hash_hmac('sha256', $token, $this->clientSecret);

            // $meUrl .= '&appsecret_proof='.$appSecretProof;
        }

        $response = $this->getHttpClient()->get($meUrl, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => $token
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['userId'],
            'nickname' => null,
            'name' => $user['displayName'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['pictureUrl'].'/large',
            'avatar_original' => $user['pictureUrl'],
            'profileUrl' => $user['link'] ?? null,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getCodeFields($state = null)
    {
        $fields = parent::getCodeFields($state);
        $fields['response_type'] = 'code';

        return $fields;
    }

    /**
     * Set the user fields to request from Facebook.
     *
     * @param  array  $fields
     * @return $this
     */
    public function fields(array $fields)
    {
        // $this->fields = $fields;

        return $this;
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param  string  $code
     * @return array
     */
    protected function getTokenFields($code)
    {
        return [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUrl,
        ];
    }
}
