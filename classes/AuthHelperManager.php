<?php namespace Lovata\Buddies\Classes;

use Lang;
use Cookie;
use Session;
use Kharanenka\Helper\Result;
use Lovata\Buddies\Models\User;
use Backend\Models\UserGroup;
use Lovata\Buddies\Models\Throttle;
use October\Rain\Auth\Manager as AuthManager;

/**
 * Class AuthHelperManager
 * @package Lovata\Buddies\Classes
 * @author Andrey Kharanenka, a.khoronenko@lovata.com, LOVATA Group
 */
class AuthHelperManager extends AuthManager
{
    protected $sessionKey = 'buddies_user_auth';
    protected $userModel = User::class;
    protected $groupModel = UserGroup::class;
    protected $throttleModel = Throttle::class;

    protected $sEmail;
    protected $sPassword;

    /** @var Throttle $obThrottle */
    protected $obThrottle;

    /**
     * Authenticate user
     * @param array $arLoginData
     * @param bool  $bRemember
     *
     * @return User|null
     */
    public function authenticate(array $arLoginData, $bRemember = false)
    {
        $this->prepareLoginData($arLoginData);
        if (!$this->validationLoginData() || !$this->checkUserThrottle()) {
            return null;
        }

        $arCredentials = [
            'email'    => $this->sEmail,
            'password' => $this->sPassword,
        ];

        //Get user object
        $obUser = $this->findUserByCredentials($arCredentials);
        if (empty($obUser)) {
            if ($this->useThrottle && !empty($this->obThrottle)) {
                $this->obThrottle->addLoginAttempt();
            }

            $sMessage = Lang::get('lovata.buddies::lang.message.e_login_not_correct');
            Result::setFalse(['field' => 'email'])->setMessage($sMessage);

            return null;
        }

        //Check user active flag
        if ($this->requireActivation && !$obUser->is_activated) {
            $sMessage = Lang::get('lovata.buddies::lang.message.e_user_not_active');
            Result::setFalse()->setMessage($sMessage);

            return null;
        }

        if ($this->useThrottle && !empty($this->obThrottle)) {
            $this->obThrottle->clearLoginAttempts();
        }

        $obUser->clearResetPassword();
        $this->login($obUser, $bRemember);

        return $this->user;
    }

    /**
     * Logs in the given user and sets properties
     * in the session.
     *
     * @param User $obUser
     * @param bool $bRemember
     */
    public function login($obUser, $bRemember = false)
    {
        $this->user = $obUser;

        //Create session/cookie data to persist the session
        $toPersist = [$obUser->getKey(), $obUser->getPersistCode()];
        Session::put($this->sessionKey, $toPersist);

        if ($bRemember) {
            Cookie::queue(Cookie::forever($this->sessionKey, $toPersist));
        }

        //Fire the 'afterLogin' event
        $obUser->afterLogin();
    }

    /**
     * Find a throttle record by login and ip address
     * @param string $sLoginName
     * @param string $ipAddress
     *
     * @return null|Throttle
     */
    public function findThrottleByLogin($sLoginName, $ipAddress)
    {
        $obUser = $this->findUserByLogin($sLoginName);
        if (empty($obUser)) {
            return null;
        }

        $iUserID = $obUser->getKey();

        return $this->findThrottleByUserId($iUserID, $ipAddress);
    }

    /**
     * Finds a user by the login value.
     * @param string $sLogin
     *
     * @return User|null
     */
    public function findUserByLogin($sLogin)
    {
        $obModel = $this->createUserModel();
        $obQuery = $obModel->newQuery();
        $this->extendUserQuery($obQuery);
        $obUser = $obQuery->where($obModel->getLoginName(), $sLogin)->first();

        return $obUser;
    }

    /**
     * Finds a user by the given credentials.
     * @param array $arCredentials
     *
     * @return null|User
     */
    public function findUserByCredentials(array $arCredentials)
    {
        /** @var User $obModel */
        $obModel = $this->createUserModel();
        $sLoginName = $obModel->getLoginName();

        if (!array_key_exists($sLoginName, $arCredentials)) {
            return null;
        }

        $obQuery = $obModel->newQuery();
        $this->extendUserQuery($obQuery);
        $arHashedAttributes = $obModel->getHashableAttributes();
        $arHashedCredentials = [];

        //Build query from given credentials
        foreach ($arCredentials as $sCredential => $sValue) {
            // All excepted the hashed attributes
            if (in_array($sCredential, $arHashedAttributes)) {
                $arHashedCredentials = array_merge($arHashedCredentials, [$sCredential => $sValue]);
            } else {
                $obQuery = $obQuery->where($sCredential, '=', $sValue);
            }
        }

        /** @var User $obUser */
        $obUser = $obQuery->first();
        if (empty($obUser)) {
            return null;
        }

        //Check the hashed credentials match
        foreach ($arHashedCredentials as $sCredential => $sValue) {
            if (!$obUser->checkHashValue($sCredential, $sValue)) {
                return null;
            }
        }

        return $obUser;
    }

    /**
     * Registers a user by giving the required credentials
     * and an optional flag for whether to activate the user.
     *
     * @param array $arCredentials
     * @param bool  $bActive
     * @return User
     */
    public function register(array $arCredentials, $bActive = false)
    {
        /** @var User $obUser */
        $obUser = $this->createUserModel();
        $obUser->fill($arCredentials);

        if ($bActive) {
            $obUser->activate();
        }

        $obUser->save();

        // Prevents revalidation of the password field
        // on subsequent saves to this model object
        $obUser->password = null;

        return $this->user = $obUser;
    }

    /**
     * Prepare login fields
     * @param array $arLoginData
     */
    protected function prepareLoginData($arLoginData)
    {
        if (empty($arLoginData) || !is_array($arLoginData)) {
            return;
        }

        if (isset($arLoginData['email'])) {
            $this->sEmail = $arLoginData['email'];
        }

        if (isset($arLoginData['password'])) {
            $this->sPassword = $arLoginData['password'];
        }
    }

    /**
     * Validation login data
     * @return bool
     */
    protected function validationLoginData()
    {
        //Check login filed
        if (empty($this->sEmail)) {
            $sMessage = Lang::get(
                'system::validation.required',
                ['attribute' => Lang::get('lovata.toolbox::lang.field.email')]
            );

            Result::setFalse(['field' => 'email'])->setMessage($sMessage);

            return false;
        }

        //Check password field
        if (empty($this->sPassword)) {
            $sMessage = Lang::get(
                'system::validation.required',
                ['attribute' => Lang::get('lovata.toolbox::lang.field.password')]
            );

            Result::setFalse(['field' => 'password'])->setMessage($sMessage);

            return false;
        }

        return true;
    }

    /**
     * Get user throttle object and checking it
     */
    protected function checkUserThrottle()
    {
        if (!$this->useThrottle) {
            return true;
        }

        /** @var Throttle $obThrottle */
        $this->obThrottle = $this->findThrottleByLogin($this->sEmail, $this->ipAddress);
        if (empty($this->obThrottle)) {
            return true;
        }

        return $this->obThrottle->check();
    }
}
