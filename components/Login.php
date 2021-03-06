<?php namespace Lovata\Buddies\Components;

use Lang;
use Input;
use Lovata\Buddies\Facades\AuthHelper;
use Kharanenka\Helper\Result;

/**
 * Class Login
 * @package Lovata\Buddies\Components
 * @author Andrey Kahranenka, a.khoronenko@lovata.com, LOVATA Group
 */
class Login extends Buddies
{
    /**
     * @return array
     */
    public function componentDetails()
    {
        return [
            'name'        => 'lovata.buddies::lang.component.login',
            'description' => 'lovata.buddies::lang.component.login_desc',
        ];
    }

    /**
     * @return array
     */
    public function defineProperties()
    {
        $arResult = $this->getModeProperty();

        return $arResult;
    }

    /**
     * Auth user
     * @return \Illuminate\Http\RedirectResponse|null
     */
    public function onRun()
    {
        if ($this->sMode != self::MODE_SUBMIT) {
            return null;
        }

        $arUserData = Input::all();
        if (empty($arUserData)) {
            return null;
        }

        $this->login($arUserData);

        return $this->getResponseModeForm();
    }

    /**
     * Ajax auth user
     * @return \Illuminate\Http\RedirectResponse|array
     */
    public function onAjax()
    {
        $arUserData = Input::all();
        $bRemember = (bool) Input::get('remember_me', false);

        $this->login($arUserData, $bRemember);

        return $this->getResponseModeAjax();
    }

    /**
     * User auth
     * @param array $arUserData
     * @param bool  $bRemember
     * @return \Lovata\Buddies\Models\User|null
     */
    public function login($arUserData, $bRemember = false)
    {
        if (empty($arUserData) || !is_array($arUserData)) {
            $sMessage = Lang::get('lovata.toolbox::lang.message.e_not_correct_request');
            Result::setMessage($sMessage);

            return null;
        }

        //Check user auth
        if (!empty($this->obUser)) {
            $sMessage = Lang::get('lovata.buddies::lang.message.e_auth_fail');
            Result::setMessage($sMessage);

            return null;
        }

        $this->obUser = AuthHelper::authenticate($arUserData, $bRemember);
        if (empty($this->obUser)) {
            return null;
        }

        $sMessage = Lang::get('lovata.buddies::lang.message.login_success');
        Result::setMessage($sMessage)->setTrue($this->obUser->id);

        return $this->obUser;
    }
}

