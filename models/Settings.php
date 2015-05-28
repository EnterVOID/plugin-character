<?php namespace Void\Character\Models;

use Lang;
use Model;
use System\Models\MailTemplate;
use Void\Character\Models\Character as CharacterModel;

class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'character_settings';
    public $settingsFields = 'fields.yaml';

    const ACTIVATE_AUTO = 'auto';
    const ACTIVATE_USER = 'character';
    const ACTIVATE_ADMIN = 'admin';

    const LOGIN_EMAIL = 'email';
    const LOGIN_USERNAME = 'charactername';

    public function initSettingsData()
    {
        $this->require_activation = true;
        $this->activate_mode = self::ACTIVATE_AUTO;
        $this->use_throttle = true;
        $this->default_country = 1;
        $this->default_state = 1;
        $this->welcome_template = 'void.character::mail.welcome';
        $this->login_attribute = self::LOGIN_EMAIL;
    }

    public function getDefaultCountryOptions()
    {
        return Country::getNameList();
    }

    public function getDefaultStateOptions()
    {
        return State::getNameList($this->default_country);
    }

    public function getActivateModeOptions()
    {
        return [
            self::ACTIVATE_AUTO => ['void.character::lang.settings.activate_mode_auto', 'void.character::lang.settings.activate_mode_auto_comment'],
            self::ACTIVATE_USER => ['void.character::lang.settings.activate_mode_character', 'void.character::lang.settings.activate_mode_character_comment'],
            self::ACTIVATE_ADMIN => ['void.character::lang.settings.activate_mode_admin', 'void.character::lang.settings.activate_mode_admin_comment'],
        ];
    }

    public function getLoginAttributeOptions()
    {
        return [
            self::LOGIN_EMAIL => ['void.character::lang.login.attribute_email'],
            self::LOGIN_USERNAME => ['void.character::lang.login.attribute_charactername'],
        ];
    }

    public function getActivateModeAttribute($value)
    {
        if (!$value)
            return self::ACTIVATE_AUTO;

        return $value;
    }

    public function getWelcomeTemplateOptions()
    {
        return [''=>'- '.Lang::get('void.character::lang.settings.no_mail_template').' -'] + MailTemplate::orderBy('code')->lists('code', 'code');
    }

}
