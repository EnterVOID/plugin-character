<?php namespace Void\Character\Controllers;

use Lang;
use Flash;
use BackendMenu;
use BackendAuth;
use Backend\Classes\Controller;
use System\Classes\SettingsManager;
use Void\Character\Models\Character;
use Void\Character\Models\Settings as CharacterSettings;

class Characters extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController'
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['void.characters.access_characters'];

    public $bodyClass = 'compact-container';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Void.Character', 'character', 'characters');
        SettingsManager::setContext('Void.Character', 'settings');
    }

    /**
     * Manually activate a character
     */
    public function update_onActivate($recordId = null)
    {
        $model = $this->formFindModelObject($recordId);

        $model->attemptActivation($model->activation_code);

        Flash::success(Lang::get('void.character::lang.characters.activated_success'));

        if ($redirect = $this->makeRedirect('update', $model)) {
            return $redirect;
        }
    }

    /**
     * Display charactername field if settings permit
     */
    protected function formExtendFields($form)
    {
        $loginAttribute = CharacterSettings::get('login_attribute', CharacterSettings::LOGIN_EMAIL);
        if ($loginAttribute != CharacterSettings::LOGIN_USERNAME) {
            return;
        }

        if (array_key_exists('charactername', $form->getFields())) {
            $form->getField('charactername')->hidden = false;
        }
    }

    /**
     * Deleted checked characters.
     */
    public function index_onDelete()
    {
        if (($checkedIds = post('checked')) && is_array($checkedIds) && count($checkedIds)) {

            foreach ($checkedIds as $characterId) {
                if (!$character = Character::find($characterId)) continue;
                $character->delete();
            }

            Flash::success(Lang::get('void.character::lang.characters.delete_selected_success'));
        }
        else {
            Flash::error(Lang::get('void.character::lang.characters.delete_selected_empty'));
        }

        return $this->listRefresh();
    }
}
