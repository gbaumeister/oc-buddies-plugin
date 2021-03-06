<?php namespace Lovata\Buddies\Components;

use Lang;
use Input;
use Kharanenka\Helper\Result;
use Lovata\Buddies\Models\User;
use October\Rain\Database\Builder;
use Lovata\Toolbox\Traits\Helpers\TraitComponentNotFoundResponse;

/**
 * Class UserPage
 * @package Lovata\Buddies\Components
 * @author Andrey Kahranenka, a.khoronenko@lovata.com, LOVATA Group
 *
 * @mixin Builder
 * @mixin \Eloquent
 */
class UserPage extends Buddies
{
    use TraitComponentNotFoundResponse;

    /** @var null|User */
    protected $obElement = null;

    /**
     * @return array
     */
    public function componentDetails()
    {
        return [
            'name'        => 'lovata.buddies::lang.component.user_page',
            'description' => 'lovata.buddies::lang.component.user_page_desc',
        ];
    }

    /**
     * Define plugin properties
     * @return array
     */
    public function defineProperties()
    {
        $arProperties = $this->getElementPageProperties();
        $arProperties = array_merge($arProperties, $this->getModeProperty());

        return $arProperties;
    }

    /**
     * Get element object
     * @throws \October\Rain\Exception\AjaxException
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|null
     */
    public function onRun()
    {
        //Get element slug
        $sElementSlug = $this->property('slug');
        if (empty($sElementSlug) || empty($this->obUser) || ($this->obUser->id != $sElementSlug)) {
            return $this->getErrorResponse();
        }

        // Resolve show data or update
        $arUserData = Input::all();
        if (empty($arUserData)) {
            return null;
        }

        $this->updateUserData($arUserData);

        return $this->getResponseModeForm();
    }

    /**
     * Registration (ajax request)
     * @return \Illuminate\Http\RedirectResponse|array
     */
    public function onAjax()
    {
        //Get user data
        $arUserData = Input::all();
        $this->updateUserData($arUserData);

        return $this->getResponseModeAjax();
    }

    /**
     * Update user data
     * @param array $arUserData
     *
     * @return bool
     */
    public function updateUserData($arUserData)
    {
        if (empty($arUserData) || empty($this->obUser)) {
            $sMessage = Lang::get('lovata.toolbox::lang.message.e_not_correct_request');
            Result::setMessage($sMessage);

            return false;
        }

        try {
            $this->obUser->password = null;
            $this->obUser->fill($arUserData);
            $this->obUser->save();
        } catch (\October\Rain\Database\ModelException $obException) {
            $this->processValidationError($obException);

            return false;
        }

        $sMessage = Lang::get('lovata.buddies::lang.message.user_update_success');
        Result::setMessage($sMessage)->setTrue($this->obUser->id);

        return true;
    }
}
