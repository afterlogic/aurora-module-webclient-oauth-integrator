<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\OAuthIntegratorWebclient;

use Aurora\Api;

/**
 * Brings oAuth support into Aurora platform.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @internal
 * @property Settings $oModuleSettings
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractWebclientModule
{
    public $oManager = null;

    /**
     * @return Module
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     * @return Settings
     */
    public function getModuleSettings()
    {
        return $this->oModuleSettings;
    }

    /***** private functions *****/
    /**
     * Initializes module.
     *
     * @ignore
     */
    public function init()
    {
        include_once __DIR__ . '/Classes/OAuthClient/http.php';
        include_once __DIR__ . '/Classes/OAuthClient/oauth_client.php';

        $this->aErrors = [
            Enums\ErrorCodes::ServiceNotAllowed			=> $this->i18N('ERROR_SERVICE_NOT_ALLOWED'),
            Enums\ErrorCodes::AccountNotAllowedToLogIn	=> $this->i18N('ERROR_ACCOUNT_NOT_ALLOWED'),
            Enums\ErrorCodes::AccountAlreadyConnected	=> $this->i18N('ERROR_ACCOUNT_ALREADY_CONNECTED'),
        ];

        $this->oManager = new Manager($this);

        $this->AddEntry('oauth', 'OAuthIntegratorEntry');
        $this->includeTemplate('StandardLoginFormWebclient_LoginView', 'Login-After', 'templates/SignInButtonsView.html', self::GetName());
        $this->includeTemplate('StandardRegisterFormWebclient_RegisterView', 'Register-After', 'templates/SignInButtonsView.html', self::GetName());
        $this->subscribeEvent('Core::DeleteUser::after', array($this, 'onAfterDeleteUser'));
        $this->subscribeEvent('Core::GetAccounts', array($this, 'onGetAccounts'));

        $this->denyMethodsCallByWebApi([
            'GetAccessToken',
            'GetAccount'
        ]);
    }

    /**
     * Deletes all oauth accounts which are owened by the specified user.
     *
     * @ignore
     * @param array $aArgs
     * @param mixed $mResult
     */
    public function onAfterDeleteUser($aArgs, &$mResult)
    {
        if ($mResult) {
            $this->oManager->deleteAccountByUserId($aArgs['UserId']);
        }
    }

    /**
     *
     * @param array $aArgs
     * @param array $aResult
     */
    public function onGetAccounts($aArgs, &$aResult)
    {
        if (isset($aArgs['UserId'])) {
            $mAccounts = $this->oManager->getAccounts($aArgs['UserId']);

            foreach ($mAccounts as $oAccount) {
                $aResult[] = array(
                    'Type' => $oAccount->getName(),
                    'Module' => $this->GetName(),
                    'Id' => $oAccount->Id,
                    'Email' => $oAccount->Email
                );
            }
        }
    }
    /***** private functions *****/

    /***** public functions *****/
    /**
     * @ignore
     */
    public function OAuthIntegratorEntry()
    {
        $mResult = false;
        $sOAuthArg = $this->oHttp->GetQuery('oauth', '');
        $sOAuthArg = \explode('-', $sOAuthArg);

        if (isset($sOAuthArg[1]) && $sOAuthArg[1] === 'connect') {
            $aArgs['Service'] = $sOAuthArg[0];
            $this->broadcastEvent(
                'ResetAccessToken',
                $aArgs,
                $mResult
            );
            $mResult = false;
            Api::Location2('./?oauth=' . $sOAuthArg[0]);
        }

        $sService = $this->oHttp->GetQuery('oauth', '');
        $aArgs = array(
            'Service' => $sService
        );

        if (!isset($_SESSION['AuroraUserId'])) {
            $_SESSION['AuroraUserId'] = Api::getAuthenticatedUserId();
        }
        $this->broadcastEvent(
            'OAuthIntegratorAction',
            $aArgs,
            $mResult
        );

        $sOAuthIntegratorRedirect = isset($_COOKIE["oauth-redirect"]) ? $_COOKIE["oauth-redirect"] : 'login';

        $sError = $this->oHttp->GetQuery('error', null);
        if (isset($sError)) {
            $sInvitationLinkHash =  isset($_COOKIE["InvitationLinkHash"]) ? $_COOKIE["InvitationLinkHash"] : null;
            if ($sOAuthIntegratorRedirect === 'register' && isset($sInvitationLinkHash)) {
                Api::Location2(
                    './#register/' . $sInvitationLinkHash
                );
            }
        }
        if (false !== $mResult && \is_array($mResult) && !isset($mResult['error'])) {
            $iAuthUserId = Api::getAuthenticatedUserId();
            if (!$iAuthUserId && isset($_SESSION['AuroraUserId'])) {
                $iAuthUserId = $_SESSION['AuroraUserId'];
                unset($_SESSION['AuroraUserId']);
            }

            $oUser = null;

            $oOAuthAccount = $this->oManager->getAccountById($mResult['id'], $mResult['type']);
            if ($oOAuthAccount) {
                if ($sOAuthIntegratorRedirect === 'register') {
                    Api::Location2(
                        './?error=' . Enums\ErrorCodes::AccountAlreadyConnected . '&module=' . self::GetName()
                    );
                }

                if (!$oOAuthAccount->issetScope('auth') && $sOAuthIntegratorRedirect !== 'connect') {
                    Api::Location2(
                        './?error=' . Enums\ErrorCodes::AccountNotAllowedToLogIn . '&module=' . self::GetName()
                    );
                }

                $oOAuthAccount->AccessToken = isset($mResult['access_token']) ? $mResult['access_token'] : '';
                $oOAuthAccount->RefreshToken = isset($mResult['refresh_token']) ? $mResult['refresh_token'] : '';
                $oOAuthAccount->Name = $mResult['name'];
                $oOAuthAccount->Email = $mResult['email'];
                if ($sOAuthIntegratorRedirect !== 'login') {
                    $oOAuthAccount->Scopes = '';
                    $oOAuthAccount->setScopes(
                        $mResult['scopes']
                    );
                }
                $this->oManager->updateAccount($oOAuthAccount);

                $oUser = Api::getUserById($oOAuthAccount->IdUser);
            } else {
                if ($iAuthUserId) {
                    $aArgs = array(
                        'UserName' => $mResult['name'],
                        'UserId' => $iAuthUserId
                    );
                    $this->broadcastEvent(
                        'CreateAccount',
                        $aArgs,
                        $oUser
                    );
                }

                $aArgs = array();
                $this->broadcastEvent(
                    'CreateOAuthAccount',
                    $aArgs,
                    $oUser
                );

                if (!($oUser instanceof \Aurora\Modules\Core\Models\User) && ($sOAuthIntegratorRedirect === 'register' || $this->oModuleSettings->AllowNewUsersRegister)) {
                    $bPrevState = Api::skipCheckUserRole(true);

                    try {
                        $iUserId = \Aurora\Modules\Core\Module::Decorator()->CreateUser(0, $mResult['email']);
                        if ($iUserId) {
                            $oUser = Api::getUserById($iUserId);
                        }
                    } catch (\Aurora\System\Exceptions\ApiException $oException) {
                        if ($oException->getCode() === \Aurora\System\Notifications::UserAlreadyExists) {
                            \Aurora\System\Api::Location2(
                                './?error=' . Enums\ErrorCodes::AccountAlreadyConnected . '&module=' . self::GetName()
                            );
                        }
                    }

                    Api::skipCheckUserRole($bPrevState);
                }

                if ($oUser instanceof \Aurora\Modules\Core\Models\User) {
                    $oOAuthAccount = new Models\OauthAccount();
                    $oOAuthAccount->IdSocial = $mResult['id'];
                    $oOAuthAccount->IdUser = $oUser->Id;
                    $oOAuthAccount->Type = $mResult['type'];
                    $oOAuthAccount->AccessToken = isset($mResult['access_token']) ? $mResult['access_token'] : '';
                    $oOAuthAccount->RefreshToken = isset($mResult['refresh_token']) ? $mResult['refresh_token'] : '';
                    $oOAuthAccount->Name = $mResult['name'];
                    $oOAuthAccount->Email = $mResult['email'];
                    $oOAuthAccount->setScopes(
                        $mResult['scopes']
                    );
                    $this->oManager->createAccount($oOAuthAccount);
                }
            }

            if ($sOAuthIntegratorRedirect === 'login' || $sOAuthIntegratorRedirect === 'register') {
                if ($oUser) {
                    $sAuthToken = Api::UserSession()->Set(
                        \Aurora\System\UserSession::getTokenData($oOAuthAccount, true),
                        \time() + 60 * 60 * 24 * 30
                    );

                    Api::setAuthTokenCookie($sAuthToken);

                    //this will store user data in static variable of Api class for later usage
                    Api::getAuthenticatedUser($sAuthToken);

                    if ($this->oHttp->GetQuery('mobile', '0') === '1') {
                        return json_encode(
                            array(
                                \Aurora\System\Application::AUTH_TOKEN_KEY => $sAuthToken
                            )
                        );
                    } else {
                        Api::Location2('./');
                    }
                } else {
                    Api::Location2(
                        './?error=' . Enums\ErrorCodes::AccountNotAllowedToLogIn . '&module=' . self::GetName()
                    );
                }
            } else {
                $sResult = $mResult !== false ? \json_encode($mResult) : 'false';
                $sErrorCode = '';

                if ($oUser && $iAuthUserId && $oUser->Id !== $iAuthUserId) {
                    $sResult = 'false';
                    $sErrorCode = Enums\ErrorCodes::AccountAlreadyConnected;
                }

                self::EchoJsCallback($mResult['type'], $sResult, $sErrorCode);
            }
        } else {
            self::EchoJsCallback($sService, 'false', '');
        }
    }

    protected static function EchoJsCallback($sType, $sResult, $sErrorCode)
    {
        if (in_array($sType, self::Decorator()->GetServiceTypes())) {
            echo
            "<script>"
                . "try {"
                . "  if (typeof(window.opener." . $sType . "ConnectCallback) !== 'undefined') {"
                . "    window.opener." . $sType . "ConnectCallback(" . $sResult . ", '" . $sErrorCode . "','" . self::GetName() . "');"
                . "  }"
                . "} finally  {"
                . "  window.close();"
                . "}"
            . "</script>";
        } else {
            http_response_code(404);
        }
        exit;
    }

    /**
     * Returns oauth account with specified type.
     *
     * @param string $Type Type of oauth account.
     * @param string $Email
     * @param int $UserId Id of user.
     * @return \Aurora\Modules\OAuthIntegratorWebclient\Models\OauthAccount
     */
    public function GetAccount($Type, $Email = '', $UserId = null)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        return $this->oManager->getAccount(
            $UserId ? $UserId : \Aurora\System\Api::getAuthenticatedUserId(),
            $Type,
            $Email
        );
    }

    /**
     * Updates oauth acount.
     *
     * @param \Aurora\Modules\OAuthIntegratorWebclient\Models\OauthAccount $oAccount Oauth account.
     * @return boolean
     */
    public function UpdateAccount(\Aurora\Modules\OAuthIntegratorWebclient\Models\OauthAccount $oAccount)
    {
        return $this->oManager->updateAccount($oAccount);
    }
    /***** public functions *****/

    /***** public functions might be called with web API *****/
    /**
     * Returns all oauth services names.
     *
     * @return array
     */
    public function GetServices()
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);

        return [];
    }

    /***** public functions might be called with web API *****/
    /**
     * Returns all oauth services types.
     *
     * @return array
     */
    public function GetServiceTypes()
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);

        return [];
    }

    /**
     * Returns all oauth services settings for authenticated user.
     *
     * @return array
     */
    public function GetSettings()
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);

        $aSettings = array();

        $oUser = \Aurora\System\Api::getAuthenticatedUser();
        if ($oUser && $oUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin) {
            $aArgs = array();
            $aServices = array();
            $this->broadcastEvent(
                'GetServicesSettings',
                $aArgs,
                $aServices
            );
            $aSettings['Services'] = $aServices;
        }

        if ($oUser && $oUser->isNormalOrTenant()) {
            $aSettings['AuthModuleName'] = $this->oModuleSettings->AuthModuleName;
            $aSettings['OnlyPasswordForAccountCreate'] = $this->oModuleSettings->OnlyPasswordForAccountCreate;
        }

        return $aSettings;
    }

    /**
     * Updates all oauth services settings.
     *
     * @param array $Services Array with services settings passed by reference.
     *
     * @return boolean
     */
    public function UpdateSettings($Services)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);

        $aArgs = array(
            'Services' => $Services
        );
        $this->broadcastEvent(
            'UpdateServicesSettings',
            $aArgs
        );

        return true;
    }

    /**
     * Get all oauth accounts.
     *
     * @return array
     */
    public function GetAccounts()
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        $UserId = \Aurora\System\Api::getAuthenticatedUserId();
        $aResult = array();
        $mAccounts = $this->oManager->getAccounts($UserId);

        foreach ($mAccounts as $oAccount) {
            if (!$oAccount->issetScope('mail')) {
                $aResult[] = array(
                    'Id' => $oAccount->Id,
                    'UUID' => '', //TODO
                    'Type' => $oAccount->Type,
                    'Email' => $oAccount->Email,
                    'Name' => $oAccount->Name,
                    'Scopes' => $oAccount->Scopes,
                );
            }
        }

        return $aResult;
    }

    /**
     * Deletes oauth account with specified type.
     *
     * @param string $Type Type of oauth account.
     * @return boolean
     */
    public function DeleteAccount($Type, $Email = '')
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        $aArgs = [
            'Service' => $Type,
            'Email' => $Email
        ];
        $mResult = false;
        $this->broadcastEvent(
            'ResetAccessToken',
            $aArgs,
            $mResult
        );
        return $this->oManager->deleteAccount(
            \Aurora\System\Api::getAuthenticatedUserId(),
            $Type,
            $Email
        );
    }

    public function GetAccessToken($sType, $sEmail)
    {
        $mResult = false;
        $oAccount = $this->GetAccount($sType, $sEmail);
        if ($oAccount) {
            $aArgs = [
                'Service' => $sType,
                'Account' => $oAccount
            ];
            $this->broadcastEvent(
                'GetAccessToken',
                $aArgs,
                $mResult
            );
        }

        return $mResult;
    }

    public function CreateMailAccount($OAuthAccountData)
    {
        $mResult = false;

        $UserId = \Aurora\Api::getAuthenticatedUserId();
        $FriendlyName = $OAuthAccountData['name'];
        $Email = $OAuthAccountData['email'];
        $IncomingLogin = $OAuthAccountData['email'];

        $IncomingPassword = '';

        if (class_exists('\Aurora\Modules\Mail\Module')) {
            $oMailModuleDecorator = Api::GetModuleDecorator('Mail');
            /** @var \Aurora\Modules\Mail\Module $oMailModuleDecorator */
            if ($oMailModuleDecorator) {
                $mResult = $oMailModuleDecorator->CreateAccount($UserId, $FriendlyName, $Email, $IncomingLogin, $IncomingPassword, null, $OAuthAccountData['type']);

                if ($mResult) {
                    if (class_exists('\Aurora\Modules\Mail\Module')) {
                        $oResException = \Aurora\Modules\Mail\Module::getInstance()->getMailManager()->validateAccountConnection($mResult, false);
                        if ($oResException instanceof \Exception) {
                            $oMailModuleDecorator->DeleteAccount($mResult->Id);
                            throw new \Aurora\System\Exceptions\ApiException(0, $oResException, $this->i18N('ERROR_ACCOUNT_IMAP_VALIDATION_FAILED'));
                        }
                    }
                }
            }
        }

        return $mResult;
    }
    /***** public functions might be called with web API *****/
}
