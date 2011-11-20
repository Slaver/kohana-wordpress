<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * This model can be used for actions with WordPress' users
 *
 * @package   WordPress
 * @author    Viacheslav Radionov <radionov@gmail.com>
 * @copyright (c) 2011-2012 Viacheslav Radionov
 * @link      http://slaver.info/
 */
class Model_Auth extends Model_Database {

    /**
     * Get user information by nick, email or ID
     * 
     * @param  string $value
     * @param  mixed  $field
     * @return mixed
     */
    public function get_user($value, $field = '')
    {
        if (preg_match('/^[a-z0-9\.\-_]+@[a-z0-9\-_]+\.([a-z0-9\-_]+\.)*?[a-z]+$/is', $value))
        {
            $field = 'email';
        }

        switch ($field) {
            case 'login':
                $value = UTF8::strtolower(UTF8::clean($value));
                $field = 'user_nicename'; break;
            case 'email':
                $field = 'user_email'; break;
            default:
                $field = 'ID'; break;
        }

        $user = DB::select()
            ->from('users')
            ->where($field, '=', $value)
            ->execute()->current();

        if ( ! empty($user))
        {
            $meta = array();
            $user_meta = DB::select()
                ->from('usermeta')
                ->where('user_id', '=', $user['ID'])
                ->execute()->as_array();

            foreach ($user_meta as $value)
                $meta[$value['meta_key']] = $value['meta_value'];

            return $user+$meta;
        }
    }

    /**
     * Insert information about user into database
     * 
     * @param  array $userdata
     * @return mixed
     */
    public function insert_user($userdata)
    {
        $update = FALSE;

        if ( ! empty($userdata['ID']) ) {
            $id = $userdata['ID'];
            $update = TRUE;
        }
        else
        {
            if ( ! empty($userdata['user_pass']))
            {
                $userdata['user_pass'] = $userdata['user_pass'];
            }
            else
            {
                $userdata['user_pass'] = Text::random();
            }
        }

        if ( ! empty($userdata['user_login']))
        {
            $userdata['user_login'] = UTF8::clean($userdata['user_login']);
        }
        else
        {
            $userdata['user_login'] = $this->_gen_nickname($userdata);
        }

        if (empty($userdata['user_nicename']))
        {
            if (empty($userdata['user_login']))
            {
                $nicename = ( ! empty($data['user_email']) && preg_match('/^(.+)\@/i', $data['user_email'], $nickname));
                $userdata['user_nicename'] = UTF8::strtolower($nicename[1]);
            }
            else
            {
                $userdata['user_nicename'] = UTF8::strtolower($userdata['user_login']);
            }
        }

        if (empty($userdata['display_name']))
        {
            $userdata['display_name'] = $userdata['user_login'];
        }

        // Default meta-data
        $userdata['wp_user_level'] = 0;
        $userdata['wp_capabilities'] = serialize(array('subscriber' => 1));

        if (empty($userdata['user_login']))
        {
            throw new Exception_Wordpress('Cannot create a user with an empty login name.');
        }

        // @TODO Merge accounts?
        if ( ! $update && $id = $this->is_mail_exist($userdata['user_email']))
        {
            throw new Exception_Wordpress('This e-mail is already registered.');
        }

        if ( ! $update && $this->is_username_exist($userdata['user_login']))
        {
            throw new Exception_Wordpress('This username is already registered.');
        }

        if ($update)
        {
            return $this->update_user($id, $userdata);
        }

        return $this->create_user($userdata);
    }

    /**
     * Save information about new user
     * 
     * @param  mixed $data
     * @return mixed
     */
    public function create_user($data)
    {
        // Some fields are NOT NULL in database!
        $new_data = array(
            'user_login'        => $data['user_login'],
            'user_pass'         => $this->wp_hash_password($data['user_pass']),
            'user_nicename'     => UTF8::strtolower($data['user_nicename']),
            'user_email'        => Arr::get($data, 'user_email', ''),
            'user_url'          => Arr::get($data, 'user_url', ''),
            'user_registered'   => gmdate('Y-m-d H:i:s'),
            'display_name'      => $data['display_name'],
        );

        $insert = DB::insert('users', array_keys($new_data))
            ->values(array_values($new_data))->execute();

        if ( ! empty($insert))
        {
            foreach ($data as $field => $value)
            {
                if ( ! in_array($field, array_keys($new_data)) && ! empty($value))
                {
                    DB::insert('usermeta', array('meta_key', 'meta_value', 'user_id'))
                        ->values(array($field, $value, $insert[0]))->execute();
                }
            }
            return $insert[0];
        }
    }

    /**
     * Update information about user
     * 
     * @param type $id
     * @param type $data 
     */
    public function update_user($id, $data)
    {
        $default = array('user_login', 'user_pass', 'user_nicename', 'user_email', 'user_url', 'display_name');
        foreach ($data as $field => $value)
        {
            if (in_array($field, $default))
            {
                DB::update('users')->set(array($field => $value))->where('ID', '=', $id)->execute();
            }
            else
            {
                if ( ! DB::update('usermeta')->set(array('meta_value' => $value))->where('user_id', '=', $id)->and_where('meta_key', '=', $field)->execute())
                {
                    DB::insert('usermeta', array('meta_key', 'meta_value', 'user_id'))
                        ->values(array($field, $value, $id))->execute();
                }
            }
        }
    }

    /**
     * Check if mail exists
     *
     * @param type $user_email
     * @return type 
     */
    public function is_mail_exist($user_email)
    {
        $userdata = self::get_user($user_email, 'email');

        if ( ! empty($userdata['ID']))
        {
            return $userdata['ID'];
        }
    }

    /**
     * Check if username exists
     *
     * @param type $username
     * @return type 
     */
    public function is_username_exist($username)
    {
        $userdata = self::get_user($username, 'login');

        if ( ! empty($userdata['ID']))
        {
            return $userdata['ID'];
        }
    }

    /**
     * Create a hash (encrypt) of a plain text password.
     *
     * For integration with other applications, this function can be overwritten to
     * instead use the other package password checking algorithm.
     *
     * @since 2.5
     * @global object $wp_hasher PHPass object
     * @uses PasswordHash::HashPassword
     *
     * @param string $password Plain text user password to hash
     * @return string The hash string of the password
     */
    private function wp_hash_password($password)
    {
        require_once MODPATH.'kohana-wordpress/classes/vendor/phpass.php';
        $wp_hasher = new PasswordHash(8, TRUE);

        return $wp_hasher->HashPassword($password);
    }

    /**
     * Generate nickname by user e-mail
     *
     * @param  type $input
     * @return type 
     */
    protected function _gen_nickname($input)
    {
        if (isset($input['user_email']) AND ! empty($input['user_email']) AND preg_match('/^(.+)\@/i', $input['user_email'], $nickname))
            return $nickname[1];
    }
}