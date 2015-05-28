<?php namespace Void\Character;

use App;
use Event;
use Backend;
use System\Classes\PluginBase;
use Illuminate\Foundation\AliasLoader;
use Void\Character\Models\MailBlocker;

class Plugin extends PluginBase
{
    /**
     * @var boolean Determine if this plugin should have elevated privileges.
     */
    public $elevated = true;

    public function pluginDetails()
    {
        return [
            'name'        => 'void.character::lang.plugin.name',
            'description' => 'void.character::lang.plugin.description',
            'author'      => 'Alexey Bobkov, Samuel Georges',
            'icon'        => 'icon-character',
            'homepage'    => 'https://github.com/void/character-plugin'
        ];
    }

    public function register()
    {
        $alias = AliasLoader::getInstance();
        $alias->alias('Auth', 'Void\Character\Facades\Auth');

        App::singleton('character.auth', function() {
            return \Void\Character\Classes\AuthManager::instance();
        });

        /*
         * Apply character-based mail blocking
         */
        Event::listen('mailer.prepareSend', function($mailer, $view, $message){
            return MailBlocker::filterMessage($view, $message);
        });
    }

    public function registerComponents()
    {
        return [
            'Void\Character\Components\Session'       => 'session',
            'Void\Character\Components\Account'       => 'account',
            'Void\Character\Components\ResetPassword' => 'resetPassword'
        ];
    }

    public function registerPermissions()
    {
        return [
            'void.characters.access_characters'  => ['tab' => 'Characters', 'label' => 'Manage Characters'],
        ];
    }

    public function registerNavigation()
    {
        return [
            'character' => [
                'label'       => 'void.character::lang.characters.menu_label',
                'url'         => Backend::url('void/character/characters'),
                'icon'        => 'icon-gamepad',
                'permissions' => ['void.characters.*'],
                'order'       => 500,

                'sideMenu' => [
                    'characters' => [
                        'label'       => 'void.character::lang.characters.all_characters',
                        'icon'        => 'icon-gamepad',
                        'url'         => Backend::url('void/character/characters'),
                        'permissions' => ['void.characters.access_characters']
                    ]
                ]
            ]
        ];
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label'       => 'void.character::lang.settings.menu_label',
                'description' => 'void.character::lang.settings.menu_description',
                'category'    => 'void.character::lang.settings.characters',
                'icon'        => 'icon-cog',
                'class'       => 'Void\Character\Models\Settings',
                'order'       => 500,
                'permissions' => ['void.characters.*'],
            ],
        ];
    }

    public function registerMailTemplates()
    {
        return [
            'void.character::mail.activate' => 'Activation email sent to new characters.',
            'void.character::mail.welcome'  => 'Welcome email sent when a character is activated.',
            'void.character::mail.restore'  => 'Password reset instructions for front-end characters.',
            'void.character::mail.new_character' => 'Sent to administrators when a new character joins.'
        ];
    }

    /**
     * Register new Twig variables
     * @return array
     */
    public function registerMarkupTags()
    {
        return [
            'functions' => [
                'form_select_country' => ['Void\Character\Models\Country', 'formSelect'],
                'form_select_state'   => ['Void\Character\Models\State', 'formSelect'],
            ]
        ];
    }
}
