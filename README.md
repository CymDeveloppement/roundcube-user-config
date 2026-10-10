Universität Duisburg-Essen User Login plugin
=============================================

This plugin allows to specify configuration settings such as IMAP/SMTP
servers, active plugins, etc. on a per-user level. The settings are read
from a static text file matching the login username.

This is an update of the [original plugin](https://gitlab.com/kolab-roundcube-plugins/ude-login)
by Kolab Systems (last release 2015) for Roundcube 1.6+ and PHP 8:

* option names renamed in Roundcube 1.6 (`imap_host`, `smtp_host`), the old
  names (`default_host`, `smtp_server`) are still accepted in the users file
* works with Dovecot master user logins (`user@domain*master`), e.g. with
  the [roundcube_new_oidc](https://github.com/CymDeveloppement/roundcube-new-oidc) plugin
* an entry for the user wins over an entry for its `@domain`
* no stale settings from a previous login attempt in the same session
* settings also override the config files of other plugins (e.g. `managesieve_host`)

The plugin also offers a way to disable some plugins for certain users.
Since unloading plugins isn't supported by Roundcube itself, this can only
be achieved by letting the `ude_login` plugin load those plugins which
potentially could be disabled on a per-user basis. Thus, instead of
listing the active plugins in the `plugins` config option, they need
to be listed in the `ude_default_plugins` option.

The user settings file is a tab-separated text file with the following
schema:

```
# comment
username1<tab>imap_host=ssl://imap1.domain.tld:993<tab>smtp_host=tls://smtp1.domain.tld:587<tab>enable_plugins=calendar<tab>disable_plugins=acl,managesieve
user2@domain.tld<tab>imap_host=ssl://imap2.domain.tld:1993<tab>smtp_host=ssl://smtp2.domain.tld:2465
@other.tld<tab>imap_host=tls://imap.other.tld:143<tab>disable_plugins=acl
```

The first column identifies the login username (case-insensitive), or a whole
mail domain when it starts with `@`. An entry for the username wins over an
entry for its domain. Empty lines and lines starting with `#` are ignored.

Following columns contain any number of `<key>=<value>` pairs, their order is
not relevant. `<key>` can correspond to any valid Roundcube config option
which is overridden with the given value. `true` and `false` are converted to
booleans, other values are kept as strings. Ports are given in the host value
(`ssl://host:port`), as in the Roundcube config.

`imap_host` is the server the user logs in to: it is applied at login, the
other options for the whole session. The following special keys are handled
separately:

* enable\_plugins: a comma-separated list of plugins to load for this user
* disable\_plugins: a comma-separated list of plugins to disable for this user

Note 1: enable\_plugins only needs to list plugins which are not part of the
standard set defined in the `plugins` and `ude_default_plugins` options.

Note 2: disable\_plugins can only disable plugins which are loaded through
this plugin, namely those listed in the `ude_default_plugins` option.

Note 3: the user keeps the credentials of the login on the other servers:
with a Dovecot master user, the other IMAP server must accept the same
master user and password. Unless `smtp_user` and `smtp_pass` are set, the
SMTP server gets the same credentials too (`%u` and `%p`): with
roundcube_new_oidc, the password sent by the OIDC provider.


Requirements
------------

* Roundcube 1.6 or later
* PHP 8.0 or later


Installation
------------

1. Place the contents of the plugin package into a directory named
`/<path-to-roundcube>/plugins/ude_login/`.

2. Add config options (see below) to the main Roundcube config file.

3. Activate the plugin by adding 'ude\_login' to the `plugins` list
in Roundcube's main config file.


Configuration Options
---------------------

* An absolute path to the static text file providing the individual settings
  for the authenticated users. Defaults to 'plugins/ude\_login/users.txt'.
  Keep this file out of the web root if it contains passwords.

  `$config['ude_login_db'] = '/path/to/user_settings_db.txt';`

* Use the `grep` shell command to read a pre-filtered list of entries from
  the users database. This may increase performance with a very large input file.

  `$config['ude_use_grep'] = true;`

* The default set of plugins which shall be loaded for each user.
  In order to allow "disabling" plugins for individual users, they cannot
  be added to the regular `plugins` list in Roundcube's config but shall be
  listed here. They'll then be loaded by the ude\_login plugin.

  `$config['ude_default_plugins'] = ['acl', 'managesieve'];`

* Separator of Dovecot master user logins (`user@domain*master`): the part
  after it is removed before looking up the user. Defaults to the
  `oidc_master_user_separator` option of roundcube_new_oidc when its
  `oidc_config_master_user` option is set.

  `$config['ude_login_master_separator'] = '*';`


License
-------
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see http://www.gnu.org/licenses/.
