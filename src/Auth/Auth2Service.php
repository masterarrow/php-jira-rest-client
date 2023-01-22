<?php


namespace JiraRestApi\Auth;


use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Http;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use JiraRestApi\JiraException;
use Mrjoops\OAuth2\Client\Provider\Jira;

class Auth2Service extends Jira
{
    /**
     * @var string
     */
    protected $state;

    /**
     * @var array
     */
    protected $scopes;

    /**
     * @var string
     */
    protected $cloudId;

    /**
     * @var AccessToken
     */
    protected $token;

    /**
     * Setup OAuth2 provider
     *
     * @param string $clientId Jira client id
     * @param string $clientSecret Jira secret
     * @param string $redirectUri Full redirect url
     * @param array $scopes Array of Jira permissions
     */
    public function __construct($clientId, $clientSecret, $redirectUri, $scopes = ['read:jira-user', 'read:jira-work'])
    {
        $this->scopes = $scopes;

        parent::__construct([
            'clientId'          => $clientId,
            'clientSecret'      => $clientSecret,
            'redirectUri'       => $redirectUri,
        ]);
    }

    /**
     * Authenticate Jira user
     *
     * @param string $state
     * @return string   Authorization URL
     */
    public function authorizationUrl($state = 'OPTIONAL_CUSTOM_CONFIGURED_STATE')
    {
        $this->state = $state;

        // Scopes
        $options = [
            'state' => $this->state,
            'scope' => $this->scopes
        ];

        // Get an authorization code following this url
        return $this->getAuthorizationUrl($options);
    }

    /**
     * Get access tokens and cloud id
     *
     * @param array $code Code returned to redirect URL by Jira
     * @return array ['cloudId' => string,  'accessToken' => string,  'refreshToken' => string, 'expires' => int]
     * @throws IdentityProviderException
     */
    public function getAccessTokens($code)
    {
        // Try to get an access token (using the authorization code grant)
        $this->token = $this->getAccessToken('authorization_code', [
            'code' => $code
        ]);

        // Get the user's details
        $user = $this->getResourceOwner($this->token);

        // Get cloud id
        $this->cloudId = explode('/', parse_url($user->toArray()['self'])['path'])[3];

        return [
            'cloudId' => $this->cloudId,
            'accessToken' => $this->token->getToken(),
            'refreshToken' => $this->token->getRefreshToken(),
            'expires' => $this->token->getExpires(),
        ];
    }

    /**
     * Refresh access tokens
     *
     * @param $clientId
     * @param $clientSecret
     * @param $refreshToken
     * @return array
     * @throws JiraException
     */
    public function refreshAccessTokens($clientId, $clientSecret, $refreshToken)
    {
        $response = Http::accept('application/json')
            ->post($this->getRefreshTokenUrl(), [
                'grant_type' => 'refresh_token',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken
            ]);

        if ($response->status() === 200) {
            $result = json_decode($response->body(), true);

            $this->token = new AccessToken($result);

            return [
                'accessToken' => $this->token->getToken(),
                'refreshToken' => $this->token->getRefreshToken(),
                'expires' => $this->token->getExpires(),
            ];
        } else {
            throw new JiraException('Cannot get a new access token', $response->status());
        }
    }

    /**
     * @return string
     */
    public function getRefreshTokenUrl()
    {
        return 'https://auth.atlassian.com/oauth/token';
    }

    /**
     * Get authenticated user information
     *
     * @return ResourceOwnerInterface
     */
    public function getOwner()
    {
        return $this->getResourceOwner($this->token);
    }

    /**
     * Make a request to the resource URL
     *
     * @param string $method GET, POST, ...
     * @param string $resource Resource URL (example 'issue/TEST-1')
     * @param array $parameters
     * @return mixed|array
     *
     * @throws \Exception
     */
    public function sendRequest($method, $resource, $parameters = [])
    {
        $url = $this->getRequestUri() . $resource;

        $response = Http::accept('application/json')
            ->withToken($this->token->getToken())
            ->send($method, $url, $parameters);

        if ($response->status() === 200) {
            return json_decode($response->body(), true);
        } else {
            throw new JiraException($response->body(), $response->status());
        }
    }

    /**
     * @return string
     */
    private function getRequestUri()
    {
        return env('JIRA_HOST') . $this->cloudId . '/rest/api/2/';
    }

    /**
     * Get cloud id and access token
     *
     * @return array
     */
    public function getAuthParams()
    {
        return [
            'cloudId' => $this->cloudId,
            'accessToken' => $this->token->getToken()
        ];
    }

    /**
     * Set authentication parameters
     *
     * @param array|Arrayable $parameters ['cloudId', 'accessToken', 'refreshToken', 'expires']
     *
     */
    public function setAuthParameters($parameters = [])
    {
        $this->cloudId = $parameters['cloudId'] ?? $this->cloudId;
        $this->token = new AccessToken([
            'access_token' => $parameters['accessToken'],
            'refresh_token' => $parameters['refreshToken'],
            'expires_in' => $parameters['expires']
        ]);
    }
}
