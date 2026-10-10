<?php

/**
 * Roundcube User Config
 *
 * Allows to specify configuration settings such as IMAP/SMTP servers, active plugins, etc.
 * on a per-user level. The settings are read from a static text file with the login username
 * being stated in the first column.
 *
 * Based on the ude_login plugin (University Duisburg-Essen User Login)
 * by Kolab Systems: https://gitlab.com/kolab-roundcube-plugins/ude-login
 *
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 * @author Yann Challet (CymDeveloppement)
 * @license GNU GPLv3+
 *
 * Copyright (C) 2014, Kolab Systems AG <contact@kolabsys.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 */

class roundcube_user_config extends rcube_plugin
{
    /** Options renamed in Roundcube 1.6, still accepted in the users file (old name => new name) */
    private const LEGACY_PROPS = [
        'default_host' => 'imap_host',
        'smtp_server' => 'smtp_host',
    ];

    /** Options given as a comma-separated list in the users file */
    private const LIST_PROPS = ['enable_plugins', 'disable_plugins'];

    /** Options of this plugin, with their name in ude_login */
    private const UDE_OPTIONS = [
        'user_config_db' => 'ude_login_db',
        'user_config_use_grep' => 'ude_use_grep',
        'user_config_default_plugins' => 'ude_default_plugins',
        'user_config_master_separator' => 'ude_login_master_separator',
    ];

    private $userconfig = [];

    /**
     * Plugin initialization
     */
    public function init()
    {
        $this->add_hook('authenticate', [$this, 'authenticate']);

        // load user-specific config from session data
        $this->startup([]);
    }

    /**
     * Callback for application startup
     */
    public function startup($args)
    {
        $rcmail = rcube::get_instance();

        // set at login, also for users without an entry in the file
        if (!isset($_SESSION['user_config']) || !is_array($_SESSION['user_config']) || $rcmail->task == 'logout') {
            return;
        }

        $this->userconfig = $_SESSION['user_config'];

        // apply user-specific settings to config, immutable so that they
        // survive the config files of plugins loaded afterwards
        foreach ($this->userconfig as $prop => $value) {
            if (!in_array($prop, self::LIST_PROPS)) {
                $rcmail->config->set($prop, $value, true);
            }
        }

        // get the list of default plugins to be loaded for each user
        $user_plugins = (array) $this->_option('user_config_default_plugins', []);

        // enable plugins
        if (!empty($this->userconfig['enable_plugins'])) {
            foreach ($this->userconfig['enable_plugins'] as $plugin) {
                if (!in_array($plugin, $user_plugins)) {
                    $user_plugins[] = $plugin;
                }

                // add support for Kolab plugins
                if ($plugin == 'calendar') {
                    $rcmail->config->set('calendar_enabled', true);
                } elseif ($plugin == 'tasklist') {
                    $rcmail->config->set('tasklist_enabled', true);
                }
            }
        }

        // "disable" plugins
        if (!empty($this->userconfig['disable_plugins'])) {
            foreach ($this->userconfig['disable_plugins'] as $plugin) {
                if (($j = array_search($plugin, $user_plugins)) !== false) {
                    unset($user_plugins[$j]);
                }

                // add support for Kolab plugins
                if ($plugin == 'calendar') {
                    $rcmail->config->set('calendar_disabled', true);
                } elseif ($plugin == 'tasklist') {
                    $rcmail->config->set('tasklist_disabled', true);
                }
            }
        }

        // load (remaining) user-specific plugins
        foreach (array_unique($user_plugins) as $plugin) {
            $rcmail->plugins->load_plugin($plugin);
        }
    }

    /**
     * Callback for 'authenticate' hook
     */
    public function authenticate($args)
    {
        $userconfig = $this->_get_user_data($args['user']);

        // override host parameter to perform user authentication
        if (!empty($userconfig['imap_host'])) {
            $args['host'] = $userconfig['imap_host'];
        }

        // remember config in session to avoid file access on every request,
        // and replace the config of a previous login attempt
        $_SESSION['user_config'] = $userconfig ?: [];

        return $args;
    }

    /**
     * Helper method to parse the users database file and return config options
     * for the matching user. An entry for the full username wins over an
     * entry for its '@domain'.
     */
    private function _get_user_data($username)
    {
        $rcmail = rcube::get_instance();

        $fn = $dbfile = $this->_option('user_config_db', 'users.txt');
        if ($fn[0] != '/') {
            $fn = realpath($dbfile = (__DIR__ . '/' . $fn));
        }

        if (!$fn || !is_readable($fn)) {
            rcube::raise_error([
                'code' => 500,
                'type' => 'php',
                'file' => __FILE__,
                'line' => __LINE__,
                'message' => "roundcube_user_config plugin warning: failed to open users file '$dbfile'"], true);
            return [];
        }

        $username = $this->_strip_master_user($username);

        // append 'username_domain' to login name if configured
        $username_domain = $rcmail->config->get('username_domain');
        if (is_string($username_domain) && strlen($username_domain) && strpos($username, '@') === false) {
            $username_full = $username . '@' . $username_domain;
        } else {
            $username_full = $username;
        }

        $users = array_unique([mb_strtolower($username), mb_strtolower($username_full)]);
        $pos = strrpos($username_full, '@');
        $domain = $pos !== false ? mb_strtolower(substr($username_full, $pos)) : null;

        // pre-filter the user database file using 'grep'
        if ($this->_option('user_config_use_grep', false)) {
            $patterns = '';
            foreach (array_filter(array_merge($users, [$domain])) as $pattern) {
                $patterns .= ' -e ' . escapeshellarg($pattern);
            }
            $fp = popen('grep -i -F' . $patterns . ' ' . escapeshellarg($fn), 'r');
            $use_grep = true;
        } else {  // just open the file for reading
            $fp = fopen($fn, 'r');
            $use_grep = false;
        }

        $domain_rec = null;
        $user_rec = null;
        while (($line = fgets($fp)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '' || $line[0] == '#') {
                continue;
            }

            $rec = explode("\t", $line);
            $key = mb_strtolower(trim($rec[0]));

            if (in_array($key, $users)) {
                $user_rec = $rec;
                break;
            }
            if ($domain !== null && $key === $domain && $domain_rec === null) {
                $domain_rec = $rec;
            }
        }
        if ($use_grep) {
            pclose($fp);
        } else {
            fclose($fp);
        }

        $this->userconfig = [];
        foreach (array_slice($user_rec ?? $domain_rec ?? [], 1) as $arg) {
            if (strpos($arg, '=') === false) {
                continue;
            }

            // split parameters
            [$prop, $value] = explode('=', $arg, 2);
            $prop = self::LEGACY_PROPS[trim($prop)] ?? trim($prop);
            $value = trim($value);

            if (in_array($prop, self::LIST_PROPS)) {
                $value = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
            } elseif ($value === 'true' || $value === 'false') {
                $value = $value === 'true';
            }
            $this->userconfig[$prop] = $value;
        }

        return $this->userconfig;
    }

    /**
     * Remove the master user part of a Dovecot master user login
     * ("user@domain*master"), as sent by roundcube_new_oidc
     */
    private function _strip_master_user($username)
    {
        $rcmail = rcube::get_instance();

        $separator = $this->_option('user_config_master_separator');
        if ($separator === null && strlen((string) $rcmail->config->get('oidc_config_master_user'))) {
            $separator = $rcmail->config->get('oidc_master_user_separator');
        }

        if (strlen((string) $separator) && ($pos = strrpos($username, $separator)) !== false) {
            $username = substr($username, 0, $pos);
        }

        return $username;
    }

    /**
     * Get an option of this plugin, falling back to its ude_login name
     */
    private function _option($name, $default = null)
    {
        $config = rcube::get_instance()->config;

        return $config->get($name, $config->get(self::UDE_OPTIONS[$name], $default));
    }
}
