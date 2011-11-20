<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * WordPress authorization library for Kohana
 *
 * @package   WordPress
 * @author    Viacheslav Radionov <radionov@gmail.com>
 * @copyright (c) 2011-2012 Viacheslav Radionov
 * @link      http://slaver.info/
 */
class Wordpress_Auth {

    // Auth instances
    protected static $_instance;

    /**
     * Singleton pattern
     *
     * @return Wordpress_Auth
     */
    public static function instance()
    {
        if ( ! isset(Wordpress_Auth::$_instance))
        {
            $config = Kohana::$config->load('wordpress');

            Wordpress_Auth::$_instance = new self($config);
        }

        return Wordpress_Auth::$_instance;
    }

    protected $model;
    protected $config;
    protected $options;
    protected $auth_cookie;
    protected $secure_auth_cookie;
    protected $logged_in_cookie;

    /**
     * Loads configuration options
     *
     * @return  void
     */
    public function __construct($config = array())
    {
        $this->config = $config;
        $this->model = new Model_Auth($this->config);
        $this->options = Wordpress_Options::instance()->get_options();

        $this->auth_cookie = 'wordpress_' . md5($this->options['siteurl']);
        $this->secure_auth_cookie = 'wordpress_sec_' . md5($this->wp_siteurl());
        $this->logged_in_cookie = 'wordpress_logged_in_' . md5($this->wp_siteurl());
        $this->cookie_domain = '.'.preg_replace('|https?://([^/]+)|i', '$1', $this->wp_siteurl());
    }

    /**
     * Gets the currently logged in user from the cookie.
     * Returns FALSE if no user is currently logged in.
     *
     * @param  string $cookie
     * @param  string $scheme (auth, secure_auth, logged_in)
     * @return mixed
     */
    public function get_user($cookie = '', $scheme = 'logged_in')
    {
        $cookie_args = $this->wp_parse_auth_cookie($cookie, $scheme);

        if ( ! $cookie_elements = $this->wp_parse_auth_cookie($cookie, $scheme))
        {
            return FALSE;
        }

        extract($cookie_elements, EXTR_OVERWRITE);

        $expired = $expiration;

        // Allow a grace period for POST and AJAX requests
        if (Request::factory()->is_ajax() || 'POST' == Request::factory()->method())
        {
            $expired += 3600;
        }

        // Quick check to see if an honest cookie has expired
        if ($expired < time())
        {
            setcookie($this->auth_cookie, '', time() - 3600);
            return FALSE;
        }

        $user = $this->model->get_user($username, 'login');

        if ( ! $user)
        {
            return FALSE;
        }

        $pass_frag = substr($user['user_pass'], 8, 4);

        $key = $this->wp_hash($username . $pass_frag . '|' . $expiration, $scheme);
        $hash = hash_hmac('md5', $username . '|' . $expiration, $key);

        if ($hmac != $hash)
        {
            return FALSE;
        }

        // AJAX/POST grace period set above
        if ($expiration < time())
        {
            $GLOBALS['login_grace_period'] = 1;
        }

        return $user;
    }

    /**
     * Check if such user already registered
     *
     * @param type $value
     * @return type 
     */
    public function is_user_exist($value)
    {
        if (preg_match('/^[a-z0-9\.\-_]+@[a-z0-9\-_]+\.([a-z0-9\-_]+\.)*?[a-z]+$/is', $value))
        {
            return (bool)$this->model->is_mail_exist($value);
        }
        else
        {
            return (bool)$this->model->is_username_exist($value);
        }
    }

    /**
     * Attempt to log in a user
     *
     * @param   string   username to log in
     * @param   string   password to check against
     * @param   boolean  enable autologin
     * @return  boolean
     */
    public function login($username, $password, $remember = FALSE)
    {
        $username = UTF8::clean($username);
        $password = UTF8::trim($password);
        $secure = Request::factory()->secure();

        if (empty($password) OR empty($username))
        {
            return FALSE;
        }

        // User information
        $user = $this->model->get_user($username, 'login');

        // Check password
        require_once MODPATH . 'kohana-wordpress/classes/vendor/phpass.php';
        $wp_hasher = new PasswordHash(8, TRUE);

        if ($wp_hasher->CheckPassword($password, $user['user_pass']))
        {
            return $this->complete_login($user, $remember, $secure);
        }
    }

    protected function complete_login($user, $remember = FALSE, $secure = FALSE)
    {
        $auth_cookie_name = $this->auth_cookie;
        $scheme = 'auth';

        if ($secure)
        {
            $auth_cookie_name = $this->secure_auth_cookie;
            $scheme = 'secure_auth';
        }

        if ($remember)
        {
            $expiration = $expire = time() + Date::WEEK*2; // 2 weeks
        }
        else
        {
            $expiration = time() + Date::DAY*2; // 2 days
            $expire = 0;
        }

        $auth_cookie = $this->wp_generate_auth_cookie($user['ID'], $expiration, $scheme);
        $logged_in_cookie = $this->wp_generate_auth_cookie($user['ID'], $expiration, 'logged_in');

        setcookie($auth_cookie_name, $auth_cookie, $expire, Cookie::$path . 'wp-content/plugins', $this->cookie_domain, $secure, TRUE);
        setcookie($auth_cookie_name, $auth_cookie, $expire, Cookie::$path . 'wp-admin', $this->cookie_domain, $secure, TRUE);
        setcookie($this->logged_in_cookie, $logged_in_cookie, $expire, Cookie::$path, $this->cookie_domain, $secure, TRUE);

        setcookie($auth_cookie_name, $auth_cookie, $expire, Cookie::$path . 'forum/bb-admin', $this->cookie_domain, $secure, TRUE);
        setcookie($auth_cookie_name, $auth_cookie, $expire, Cookie::$path . 'forum/bb-plugins', $this->cookie_domain, $secure, TRUE);

        return $user;
    }

    /**
     * Register new user
     * 
     * @return  boolean
     */
    public function register($user_email)
    {
        $user_email = UTF8::trim($user_email);

        if ( ! $this->model->is_mail_exist($user_email))
        {
            $data = array(
                'user_pass' => $this->wp_generate_password(12, FALSE),
                'user_email' => $user_email,
            );

            if ($id = $this->model->insert_user($data))
            {
                $auth = $this->model->get_user($id);

                // @TODO Send mail about registration
                return $this->complete_login($auth, TRUE);
            }
        }
        return FALSE;
    }

    /**
     * Log out a user by removing the related cookie variables
     * 
     * @return  boolean
     */
    public function logout()
    {
        $expire = time() - 31536000;

        setcookie($this->secure_auth_cookie, ' ', $expire, Cookie::$path . 'wp-admin', $this->cookie_domain);
        setcookie($this->secure_auth_cookie, ' ', $expire, Cookie::$path . 'wp-content/plugins', $this->cookie_domain);
        setcookie($this->auth_cookie, ' ', $expire, Cookie::$path . 'wp-admin', $this->cookie_domain);
        setcookie($this->auth_cookie, ' ', $expire, Cookie::$path . 'wp-content/plugins', $this->cookie_domain);
        setcookie($this->logged_in_cookie, ' ', $expire, Cookie::$path, $this->cookie_domain);
        setcookie($this->auth_cookie, ' ', $expire, Cookie::$path . 'forum/bb-admin', $this->cookie_domain);
        setcookie($this->auth_cookie, ' ', $expire, Cookie::$path . 'forum/bb-plugins', $this->cookie_domain);
    }

    /**
     * Get hash of given string.
     * 
     * @param  mixed  $data Plain text to hash
     * @param  mixed  $scheme Authentication scheme
     * @return string Hash of $data
     */
    private function wp_hash($data, $scheme = 'auth')
    {
        $salt = $this->wp_salt($scheme);
        return hash_hmac('md5', $data, $salt);
    }

    /**
     * Get salt to add to hashes to help prevent attacks.
     *
     * The secret key is located in two places: the database in case the secret key
     * isn't defined in the second place, which is in the wp-config.php file. If you
     * are going to set the secret key, then you must do so in the wp-config.php
     * file.
     *
     * The secret key in the database is randomly generated and will be appended to
     * the secret key that is in wp-config.php file in some instances. It is
     * important to have the secret key defined or changed in wp-config.php.
     *
     * If you have installed WordPress 2.5 or later, then you will have the
     * SECRET_KEY defined in the wp-config.php already. You will want to change the
     * value in it because hackers will know what it is. If you have upgraded to
     * WordPress 2.5 or later version from a version before WordPress 2.5, then you
     * should add the constant to your wp-config.php file.
     *
     * Below is an example of how the SECRET_KEY constant is defined with a value.
     * You must not copy the below example and paste into your wp-config.php. If you
     * need an example, then you can have a
     * {@link https://api.wordpress.org/secret-key/1.1/ secret key created} for you.
     *
     * <code>
     * define('SECRET_KEY', 'mAry1HadA15|\/|b17w55w1t3asSn09w');
     * </code>
     *
     * Salting passwords helps against tools which has stored hashed values of
     * common dictionary strings. The added values makes it harder to crack if given
     * salt string is not weak.
     *
     * @param string $scheme Authentication scheme
     * @return string Salt value
     */
    private function wp_salt($scheme = 'auth')
    {
        $wp_default_secret_key = '';
        $secret_key = '';

        if (defined('SECRET_KEY') && ('' != SECRET_KEY) && ($wp_default_secret_key != SECRET_KEY))
            $secret_key = SECRET_KEY;

        if ('auth' == $scheme)
        {
            if (defined('AUTH_KEY') && ('' != AUTH_KEY) && ($wp_default_secret_key != AUTH_KEY))
                $secret_key = AUTH_KEY;

            if (defined('AUTH_SALT') && ('' != AUTH_SALT) && ($wp_default_secret_key != AUTH_SALT))
                $salt = AUTH_SALT;
            elseif (defined('SECRET_SALT') && ('' != SECRET_SALT) && ($wp_default_secret_key != SECRET_SALT))
                $salt = SECRET_SALT;
            else
                $salt = $this->options['auth_salt'];
        }
        elseif ('logged_in' == $scheme)
        {
            if (defined('LOGGED_IN_KEY') && ('' != LOGGED_IN_KEY) && ($wp_default_secret_key != LOGGED_IN_KEY))
                $secret_key = LOGGED_IN_KEY;

            if (defined('LOGGED_IN_SALT') && ('' != LOGGED_IN_SALT) && ($wp_default_secret_key != LOGGED_IN_SALT))
                $salt = LOGGED_IN_SALT;
            else
                $salt = $this->options['logged_in_salt'];
        }
        elseif ('nonce' == $scheme)
        {
            if (defined('NONCE_KEY') && ('' != NONCE_KEY) && ($wp_default_secret_key != NONCE_KEY))
                $secret_key = NONCE_KEY;

            if (defined('NONCE_SALT') && ('' != NONCE_SALT) && ($wp_default_secret_key != NONCE_SALT))
                $salt = NONCE_SALT;
            else
                $salt = $this->options['nonce_salt'];
        }
        else
        {
            // ensure each auth scheme has its own unique salt
            $salt = hash_hmac('md5', $scheme, $secret_key);
        }

        return $secret_key . $salt;
    }

    /**
     * Parse a cookie into its components
     *
     * @param string $cookie
     * @param string $scheme Optional. The cookie scheme to use: auth, secure_auth, or logged_in
     * @return array Authentication cookie components
     */
    private function wp_parse_auth_cookie($cookie = '', $scheme = '')
    {
        if (empty($cookie))
        {
            switch ($scheme)
            {
                case 'auth':
                    $cookie_name = $this->auth_cookie;
                    break;
                case 'secure_auth':
                    $cookie_name = $this->secure_auth_cookie;
                    break;
                case 'logged_in':
                    $cookie_name = $this->logged_in_cookie;
                    break;
                default:
                    if (Request::factory()->secure())
                    {
                        $cookie_name = $this->secure_auth_cookie;
                        $scheme = 'secure_auth';
                    }
                    else
                    {
                        $cookie_name = $this->auth_cookie;
                        $scheme = 'auth';
                    }
            }

            if (empty($_COOKIE[$cookie_name]))
                return FALSE;

            $cookie = $_COOKIE[$cookie_name];
        }

        $cookie_elements = explode('|', $cookie);

        if (count($cookie_elements) != 3)
        {
            return FALSE;
        }

        list($username, $expiration, $hmac) = $cookie_elements;

        return compact('username', 'expiration', 'hmac', 'scheme');
    }

    /**
     * Generate authentication cookie contents
     *
     * @param int $user_id User ID
     * @param int $expiration Cookie expiration in seconds
     * @param string $scheme Optional. The cookie scheme to use: auth, secure_auth, or logged_in
     * @return string Authentication cookie contents
     */
    private function wp_generate_auth_cookie($user_id, $expiration, $scheme = 'auth')
    {
        $user = $this->model->get_user($user_id, 'id');

        $pass_frag = substr($user['user_pass'], 8, 4);

        $key = $this->wp_hash($user['user_login'] . $pass_frag . '|' . $expiration, $scheme);
        $hash = hash_hmac('md5', $user['user_login'] . '|' . $expiration, $key);

        $cookie = $user['user_login'] . '|' . $expiration . '|' . $hash;

        return $cookie;
    }

    /**
     * Generates a random password drawn from the defined set of characters
     *
     * @param int $length The length of password to generate
     * @param bool $special_chars Whether to include standard special characters. Default true.
     * @param bool $extra_special_chars Whether to include other special characters. Used when
     *   generating secret keys and salts. Default false.
     * @return string The random password
     * */
    private function wp_generate_password($length = 12, $special_chars = TRUE, $extra_special_chars = FALSE)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($special_chars)
        {
            $chars .= '!@#$%^&*()';
        }
        if ($extra_special_chars)
        {
            $chars .= '-_ []{}<>~`+=,.;:/?|';
        }

        $password = '';
        for ($i = 0; $i < $length; $i++)
        {
            $password .= substr($chars, rand(0, strlen($chars) - 1), 1);
        }

        return $password;
    }

    private function wp_siteurl()
    {
        $siteurl = $this->options['siteurl'];
        $subdomain = explode('.',parse_url($this->options['siteurl'], PHP_URL_HOST));
        if (count($subdomain) > 2)
        {
            $subdomain = array_reverse($subdomain);
            $siteurl = $subdomain[1].'.'.$subdomain[0];
        }

        return $siteurl;
    }
}
