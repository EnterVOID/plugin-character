<?php namespace Void\Character\Components;

use Lang;
use Auth;
use Mail;
use Flash;
use Input;
use Redirect;
use Validator;
use ValidationException;
use ApplicationException;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Void\Character\Models\Settings as CharacterSettings;
use Exception;

class Account extends ComponentBase
{

    public function componentDetails()
    {
        return [
            'name'        => 'void.character::lang.account.account',
            'description' => 'void.character::lang.account.account_desc'
        ];
    }

    public function defineProperties()
    {
        return [
            'redirect' => [
                'title'       => 'void.character::lang.account.redirect_to',
                'description' => 'void.character::lang.account.redirect_to_desc',
                'type'        => 'dropdown',
                'default'     => ''
            ],
            'paramCode' => [
                'title'       => 'void.character::lang.account.code_param',
                'description' => 'void.character::lang.account.code_param_desc',
                'type'        => 'string',
                'default'     => 'code'
            ]
        ];
    }

    public function getRedirectOptions()
    {
        return [''=>'- none -'] + Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    /**
     * Executed when this component is bound to a page or layout.
     */
    public function onRun()
    {
        $routeParameter = $this->property('paramCode');

        /*
         * Activation code supplied
         */
        if ($activationCode = $this->param($routeParameter)) {
            $this->onActivate(false, $activationCode);
        }

        $this->page['character'] = $this->character();
        $this->page['loginAttribute'] = $this->loginAttribute();
        $this->page['loginAttributeLabel'] = $this->loginAttributeLabel();
    }

    /**
     * Returns the logged in character, if available
     */
    public function character()
    {
        if (!Auth::check())
            return null;

        return Auth::getCharacter();
    }

    /**
     * Returns the login model attribute.
     */
    public function loginAttribute()
    {
        return CharacterSettings::get('login_attribute', CharacterSettings::LOGIN_EMAIL);
    }

    /**
     * Returns the login label as a word.
     */
    public function loginAttributeLabel()
    {
        return $this->loginAttribute() == CharacterSettings::LOGIN_EMAIL
            ? Lang::get('void.character::lang.login.attribute_email')
            : Lang::get('void.character::lang.login.attribute_charactername');
    }

    /**
     * Sign in the character
     */
    public function onSignin()
    {
        /*
         * Validate input
         */
        $data = post();
        $rules = [];

        $rules['login'] = $this->loginAttribute() == CharacterSettings::LOGIN_USERNAME
            ? 'required|between:2,64'
            : 'required|email|between:2,64';

        $rules['password'] = 'required|min:2';

        if (!array_key_exists('login', $data)) {
            $data['login'] = post('charactername', post('email'));
        }

        $validation = Validator::make($data, $rules);
        if ($validation->fails()) {
            throw new ValidationException($validation);
        }

        /*
         * Authenticate character
         */
        $character = Auth::authenticate([
            'login' => array_get($data, 'login'),
            'password' => array_get($data, 'password')
        ], true);

        /*
         * Redirect to the intended page after successful sign in
         */
        $redirectUrl = $this->pageUrl($this->property('redirect'));

        if ($redirectUrl = post('redirect', $redirectUrl))
            return Redirect::intended($redirectUrl);
    }

    /**
     * Register the character
     */
    public function onRegister()
    {
        /*
         * Validate input
         */
        $data = post();

        if (!array_key_exists('password_confirmation', $data)) {
            $data['password_confirmation'] = post('password');
        }

        $rules = [
            'email'    => 'required|email|between:2,64',
            'password' => 'required|min:2'
        ];

        if ($this->loginAttribute() == CharacterSettings::LOGIN_USERNAME) {
            $rules['charactername'] = 'required|between:2,64';
        }

        $validation = Validator::make($data, $rules);
        if ($validation->fails()) {
            throw new ValidationException($validation);
        }

        /*
         * Register character
         */
        $requireActivation = CharacterSettings::get('require_activation', true);
        $automaticActivation = CharacterSettings::get('activate_mode') == CharacterSettings::ACTIVATE_AUTO;
        $characterActivation = CharacterSettings::get('activate_mode') == CharacterSettings::ACTIVATE_USER;
        $character = Auth::register($data, $automaticActivation);

        /*
         * Activation is by the character, send the email
         */
        if ($characterActivation) {
            $this->sendActivationEmail($character);

            Flash::success(Lang::get('void.character::lang.account.activation_email_sent'));
        }

        /*
         * Automatically activated or not required, log the character in
         */
        if ($automaticActivation || !$requireActivation) {
            Auth::login($character);
        }

        /*
         * Redirect to the intended page after successful sign in
         */
        $redirectUrl = $this->pageUrl($this->property('redirect'));

        if ($redirectUrl = post('redirect', $redirectUrl)) {
            return Redirect::intended($redirectUrl);
        }
    }

    /**
     * Activate the character
     * @param  string $code Activation code
     */
    public function onActivate($isAjax = true, $code = null)
    {
        try {
            $code = post('code', $code);

            /*
             * Break up the code parts
             */
            $parts = explode('!', $code);
            if (count($parts) != 2) {
                throw new ValidationException(['code' => Lang::get('void.character::lang.account.invalid_activation_code')]);
            }

            list($characterId, $code) = $parts;

            if (!strlen(trim($characterId)) || !($character = Auth::findCharacterById($characterId))) {
                throw new ApplicationException(Lang::get('void.character::lang.account.invalid_character'));
            }

            if (!$character->attemptActivation($code)) {
                throw new ValidationException(['code' => Lang::get('void.character::lang.account.invalid_activation_code')]);
            }

            Flash::success(Lang::get('void.character::lang.account.success_activation'));

            /*
             * Sign in the character
             */
            Auth::login($character);

        }
        catch (Exception $ex) {
            if ($isAjax) throw $ex;
            else Flash::error($ex->getMessage());
        }
    }

    /**
     * Update the character
     */
    public function onUpdate()
    {
        if (!$character = $this->character())
            return;

        $character->save(post());

        /*
         * Password has changed, reauthenticate the character
         */
        if (strlen(post('password'))) {
            Auth::login($character->reload(), true);
        }

        Flash::success(post('flash', Lang::get('void.character::lang.account.success_saved')));

        /*
         * Redirect to the intended page after successful update
         */
        $redirectUrl = $this->pageUrl($this->property('redirect'));

        if ($redirectUrl = post('redirect', $redirectUrl))
            return Redirect::to($redirectUrl);
    }

    /**
     * Trigger a subsequent activation email
     */
    public function onSendActivationEmail($isAjax = true)
    {
        try {
            if (!$character = $this->character()) {
                throw new ApplicationException(Lang::get('void.character::lang.account.login_first'));
            }

            if ($character->is_activated) {
                throw new ApplicationException(Lang::get('void.character::lang.account.alredy_active'));
            }

            Flash::success(Lang::get('void.character::lang.account.activation_email_sent'));

            $this->sendActivationEmail($character);

        }
        catch (Exception $ex) {
            if ($isAjax) throw $ex;
            else Flash::error($ex->getMessage());
        }

        /*
         * Redirect
         */
        $redirectUrl = $this->pageUrl($this->property('redirect'));

        if ($redirectUrl = post('redirect', $redirectUrl))
            return Redirect::to($redirectUrl);
    }

    /**
     * Sends the activation email to a character
     * @param  Character $character
     * @return void
     */
    protected function sendActivationEmail($character)
    {
        $code = implode('!', [$character->id, $character->getActivationCode()]);
        $link = $this->currentPageUrl([
            $this->property('paramCode') => $code
        ]);

        $data = [
            'name' => $character->name,
            'link' => $link,
            'code' => $code
        ];

        Mail::send('void.character::mail.activate', $data, function($message) use ($character)
        {
            $message->to($character->email, $character->name);
        });
    }

}
