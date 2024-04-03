<?php

/**
 * @package    Grav\Plugin\LoginSkautis
 *
 * @copyright  Copyright (C) 2024 PraserX
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Config\Config;
use Grav\Common\Data;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Plugin;
use Grav\Common\Language\Language;
use Grav\Common\Session;
use Grav\Common\User\User;
use Grav\Plugin\Login\Events\UserLoginEvent;
use Grav\Plugin\Login\Login;
use Grav\Plugin\Login\OAuth2\OAuth2;
use Grav\Plugin\Login\OAuth2\ProviderFactory;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\Session\Message;

use Skautis;

/**
 * Class LoginSkautisPlugin
 * @package Grav\Plugin\LoginSkautis
 */
class LoginSkautisPlugin extends Plugin
{
    /**
     * @property 
     */
    private $skautisGateway = null;

    private $wsdlManager = null;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized'      => [['autoload', 1000], ['onPluginsInitialized', 0]],
            'onUserLoginAuthenticate'   => ['userLoginAuthenticate', 100],
            'onUserLoginFailure'        => ['userLoginFailure', 100],
            'onUserLogin'               => ['userLogin', 0],
            'onUserLogout'              => ['userLogout', 0],
            'onTask.redirect.skautis'   => ['userRedirectSkautis', 0],
            'onTask.login.skautis'      => ['userSkautisLogin', 0],
            'onTask.logout.skautis'     => ['userSkautisLogout', 0],
        ];
    }

    /**
     * [onPluginsInitialized:100] Composer autoload.
     *
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * [onPluginsInitialized] Plugin initialization.
     */
    public function onPluginsInitialized()
    {
        // Check to ensure login plugin is enabled.
        if (!$this->grav['config']->get('plugins.login.enabled')) {
            throw new \RuntimeException('The Login SkautIS plugin needs to be installed and enabled.');
        }

        if (!$this->config->get('plugins.login-skautis.provider_url')) {
            throw new \RuntimeException('The Login SkautIS plugin provider URL need to be configured.');
        }

        $appid = $this->config->get('plugins.login-skautis.app_id');
        if (!$appid) {
            throw new \RuntimeException('The Login SkautIS plugin App ID need to be configured.');
        }

        if (!$this->config->get('plugins.login-skautis.parent_unit')) {
            throw new \RuntimeException('The Login SkautIS plugin parent unit need to be configured.');
        }

        $this->wsdlManager = new Skautis\Wsdl\WsdlManager(new Skautis\Wsdl\WebServiceFactory(), new Skautis\Config($appid, false));
    }

    /**
     * [onUserLoginAuthenticate]
     */
    public function userLoginAuthenticate(UserLoginEvent $event)
    {        
        /** @var Array $data */
        $data = array();

        /** @var UserCollectionInterface $users */
        $users = $this->grav['accounts'];

        /** @var Config $config */
        $config = $this->grav['config'];

        /** @var Session $session */
        $session = $this->grav['session'];

        /** @var Uri $uri */
        $uri = $this->grav['uri'];

        $loginDetail = null;
        $personDetail = null;

        $options = $event->getOptions();
        if (isset($options['skautis'])) {
            $success = false;

            $token = $_POST["skautIS_Token"];
            $idRole = $_POST["skautIS_IDRole"];
            $idUnit = $_POST["skautIS_IDUnit"];
            $dateLogout = $_POST["skautIS_DateLogout"];

            try {
                // Get user essential information
                $loginDetail = $this->wsdlManager->getWebService('UserManagement', $token)->LoginDetail(["ID_Login" => $token]);
                $personDetail = $this->wsdlManager->getWebService('OrganizationUnit', $token)->PersonDetail(
                    array(
                        'ID' => $loginDetail->ID_Person,
                    )
                );
                $success = true;
            } catch (Exception $e) {
                $event->setStatus($event::AUTHENTICATION_FAILURE);
                $event->stopPropagation();
                return;
            }


            if ((!$success) || ($loginDetail === null) || ($personDetail === null)) {
                $event->setStatus($event::AUTHENTICATION_FAILURE);
                $event->stopPropagation();
                return;
            }

            $email = $personDetail->Email;
            $username = strval($loginDetail->ID_User);
            $nickname = $personDetail->NickName;
            $fullname = $loginDetail->Person;

            // Determine if user is already registered in or not
            $registered = $users->load($username)->exists();

            // Get user object for update or create
            $user = $users->load($username);

            if (!$registered) {
                $data = $this->getUserData(true, $email, $nickname, $fullname);
            } else {
                $data = $this->getUserData(false, $email, $nickname, $fullname);
            }

            $user->update($data);
            $user->save();

            $event->setUser($user);
            $event->setStatus($event::AUTHENTICATION_SUCCESS);
            $event->stopPropagation();
            return;
        }
    }

    /**
     * [onUserLoginFailure]
     */
    public function userLoginFailure(UserLoginEvent $event)
    {
        $user = $event->getUser();
        if ($user->username !== "") {
            $event->setStatus($event::AUTHORIZATION_DENIED);
            $event->stopPropagation();
            return;
        }
    }

    /**
     * [onUserLogin]
     */
    public function userLogin(UserLoginEvent $event)
    {
    }

    /**
     * [onUserLogout]
     */
    public function userLogout(UserLoginEvent $event)
    {
    }

    /**
     * [onTask.redirect.skautis]
     */
    public function userRedirectSkautis()
    {
        $provider = $this->config->get('plugins.login-skautis.provider_url');
        $appid = $this->config->get('plugins.login-skautis.app_id');

        $redirect = $provider."?appid=".$appid;
        $this->grav->redirect($redirect, 303);
    }

    /**
     * [onTask.login.skautis]
     */
    public function userSkautisLogin()
    {
        /** @var Language $t */
        $t = $this->grav['language'];

        /** @var Login $login */
        $login = $this->grav['login'];

        /** @var Session $session */
        $session = $this->grav['session'];

        /** @var Messages $messages */
        $messages = $this->grav['messages'];

        $event = $login->login([], ['remember_me' => true, 'skautis' => true], ['return_event' => true]);
        $user = $event->getUser();

        if ($user->authenticated) {
            $session->user = $user;

            $event->defMessage('PLUGIN_LOGIN.LOGIN_SUCCESSFUL', 'info');
            $event->defRedirect(
                $this->grav['session']->redirect_after_login
                    ?: $this->grav['config']->get('plugins.login.route_after_login')
                    ?: $this->grav['uri']->referrer('/')
            );
        } else {
            if ($user->username) {
                $event->defMessage('PLUGIN_LOGIN.ACCESS_DENIED', 'error');
                $event->defRedirect($this->grav['config']->get('plugins.login.route_unauthorized', '/'));
            } else {
                $event->defMessage('PLUGIN_LOGIN.LOGIN_FAILED', 'error');
            }
        }

        $message = $event->getMessage();
        if ($message) {
            $messages->add($t->translate($message), $event->getMessageType());
        }

        $redirect = $event->getRedirect();
        if ($redirect) {
            $this->grav->redirect($redirect, $event->getRedirectCode());
        }    
    }

    /**
     * [onTask.logout.skautis]
     */
    public function userSkautisLogout() {
        $userLogoutUrl = $this->config->get('plugins.login-skautis.user_logout_url');
        
        $this->grav['session']->invalidate()->start();
        $this->grav->redirect($userLogoutUrl);
    }

    /**
     * User data struct transform.
     *
     * @return Array
     */
    private function getUserData($new = false, $email = "", $nickname = "", $fullname = "") {
        /** @var Array $data */
        $data = array();

        if ($new) {
            $data['state'] = "enabled";
            $data['language'] = "cs";
            $data['twofa_enabled'] = false;
            $data['twofa_secret'] = "";

            $data['access'] = [];
            $data["access"] = array_merge($data["access"], ["site" => ["login" => 'true']]);
            $data["access"] = array_merge($data["access"], ["admin" => ["login" => 'true']]);
        }

        $data['email'] = $email;
        $data['title'] = $fullname;
        $data['fullname'] = $nickname;

        return $data;
    }
}
