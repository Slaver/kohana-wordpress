<?php defined('SYSPATH') OR die('No direct access allowed.');

defined('SYSPATH') or die('No direct script access.');

class Controller_Auth extends Controller {

    public function before()
    {
        parent::before();

        if (array_key_exists('wordpress', Kohana::modules()))
        {
            // Авторизация
            $this->auth = Wordpress_Auth::instance()->get_user('', 'logged_in');
        }
        else
        {
            throw new Kohana_Exception('Module wordpress is not loaded');
        }
    }

    public function action_index()
    {
        if (!$this->auth)
        {
            echo '<form action="/auth/login" method="post">
                <input type="text" name="log" value="" />
                <input type="password" name="pwd" value="" />
                <input type="submit" />
            </form>';
            echo '<a href="/auth/register">Register</a>';
        }
        else
        {
            var_dump($this->auth);
            echo '<a href="/auth/logout">Logout</a>';
        }
    }

    public function action_login()
    {
        if (!empty($_POST))
        {
            $user = Wordpress_Auth::instance()->login($_POST['log'], $_POST['pwd'], TRUE);

            if (is_array($user))
            {
                if (isset($_REQUEST['redirect_to']))
                {
                    $redirect_to = $_REQUEST['redirect_to'];
                    // Redirect to https if user wants ssl
                    if ($secure && FALSE !== strpos($redirect_to, 'wp-admin'))
                    {
                        $redirect_to = preg_replace('|^http://|', 'https://', $redirect_to);
                    }
                }
                else
                {
                    $redirect_to = '/auth';
                }

                Request::factory()->redirect($redirect_to);
            }
        }
    }

    public function action_register()
    {
        echo '<script src="//loginza.ru/js/widget.js" type="text/javascript"></script>
        <form action="/auth/register" method="post">
            <!--<input type="text" name="user_login" value="" />-->
            <input type="text" name="user_email" value="" />
            <input type="submit" />
        </form><a href="https://loginza.ru/api/widget?token_url=http://new.ultra-music.dev/auth/register" class="loginza">Войти через OpenID</a>';

        if (!empty($_POST))
        {
            if (!empty($_POST['user_email']))
            {
                if (Wordpress_Auth::instance()->register($_POST['user_email']))
                {
                    Request::factory()->redirect('/auth/ok');
                }
            }
        }
    }

    public function action_logout()
    {
        Wordpress_Auth::instance()->logout();

        Request::factory()->redirect('/auth');
    }

    public function action_ok()
    {
        var_dump($this->auth);
        echo '<a href="/auth/logout">Logout</a>';
    }

}