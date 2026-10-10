# Roundcube User Config

Maintained by Yann Challet ([CymDeveloppement](https://github.com/CymDeveloppement)).

Overrides the [Roundcube](https://roundcube.net) configuration for some
users only, from a simple text file: another IMAP or SMTP server (with
other ports), another Sieve server, extra or fewer plugins, or any other
Roundcube option.

```
alice@example.com	imap_host=ssl://imap2.example.com:1993	smtp_host=ssl://smtp2.example.com:2465
@partner.org	imap_host=tls://mail.partner.org:143	smtp_host=tls://mail.partner.org:587
```

Users who are not listed keep the normal configuration.

Based on the [ude_login plugin](https://gitlab.com/kolab-roundcube-plugins/ude-login)
(University Duisburg-Essen User Login) by Kolab Systems, last released in
2015, updated for Roundcube 1.6+ and PHP 8.

## Features

- **Per user or per domain**: one line per login, or per `@domain` for all
  the users of a mail domain. An entry for the user wins over an entry for
  its domain.
- **Other IMAP server**: the user logs in to the server of the file, with
  its own port and encryption (`ssl://`, `tls://`).
- **Any Roundcube option**: SMTP and Sieve servers, SMTP credentials, skin,
  limits... including the options of other plugins (`managesieve_host`...).
- **Per-user plugins**: load extra plugins for some users, or disable some
  plugins for others.
- **Works with OIDC / SSO logins**: Dovecot master user logins
  (`user@domain*master`) are matched on the user part, e.g. with
  [roundcube_new_oidc](https://github.com/CymDeveloppement/roundcube-new-oidc).
- **Read once per session**: the file is read at login only, the settings
  are kept in the session.
- **Large files**: optional pre-filtering with `grep`.

## Requirements

- Roundcube 1.6 or later
- PHP 8.0 or later

## Installation

```bash
composer require cymdeveloppement/roundcube-user-config
```

Or copy the plugin into `plugins/roundcube_user_config/`.

Add the plugin to your Roundcube configuration, with the path of the users
file:
```php
$config['plugins'] = ['roundcube_user_config'];
$config['user_config_db'] = '/etc/roundcube/user_config.txt';
```

The file must be readable by the web server. Keep it out of the web root,
above all if it contains passwords.

## Configuration

Options go in the main Roundcube configuration file.

| Option | Default | Description |
|---|---|---|
| `user_config_db` | `users.txt` | Users file: absolute path, or relative to the plugin folder. |
| `user_config_use_grep` | `false` | Pre-filter the file with the `grep` command before reading it. Only useful with a very large file (many thousands of lines). |
| `user_config_default_plugins` | `[]` | Plugins loaded for every user, that can be disabled per user. See [Plugins](#plugins). |
| `user_config_master_separator` | see below | Separator of master user logins (`user@domain*master`): the part after it is ignored when looking up the user. Defaults to `oidc_master_user_separator` of roundcube_new_oidc when its `oidc_config_master_user` option is set, otherwise none. |

## Users file

A text file, one entry per line, fields separated by **tabs**:

```
# comment
<login or @domain><TAB><option>=<value><TAB><option>=<value>...
```

- **First field**: the login of the user, or `@domain` for all the users of
  that domain. Case-insensitive. If `username_domain` is set in Roundcube,
  logins without domain are also matched with that domain.
- **Next fields**: any number of `option=value` pairs, in any order. The
  option is any Roundcube option (see `config/defaults.inc.php`), or an
  option of a plugin.
- **Values** are strings, except `true` and `false` which become booleans.
  Arrays cannot be given, except for the plugin lists below.
- Empty lines and lines starting with `#` are ignored.

When several lines match, the line of the user wins over the line of its
domain, whatever their order. Between two lines for the same key, the
first one wins.

### Servers

| Option | Example | Description |
|---|---|---|
| `imap_host` | `ssl://imap2.example.com:1993` | IMAP server the user logs in to. `ssl://` = implicit TLS, `tls://` = STARTTLS, no prefix = plain. Default port: 993 with `ssl://`, 143 otherwise. |
| `smtp_host` | `tls://smtp2.example.com:2587` | SMTP server. Default port: 465 with `ssl://`, 587 otherwise. |
| `smtp_user` / `smtp_pass` | `%u` / `%p` | SMTP credentials. By default Roundcube uses the login and password of the session (`%u`, `%p`). |
| `managesieve_host` | `tls://imap2.example.com:4190` | Sieve server of the managesieve plugin. |

The old option names `default_host` and `smtp_server` (renamed in
Roundcube 1.6) are still accepted in the file.

### Plugins

Two special keys take a comma-separated list of plugins:

- `enable_plugins`: plugins loaded for this user only.
- `disable_plugins`: plugins not loaded for this user.

Roundcube cannot unload a plugin, so `disable_plugins` only works with the
plugins loaded by this plugin: list them in `user_config_default_plugins`
instead of `plugins`.

```php
$config['plugins'] = ['roundcube_user_config', 'archive'];
$config['user_config_default_plugins'] = ['managesieve', 'zipdownload'];
```
```
intern@example.com	disable_plugins=managesieve
admin@example.com	enable_plugins=acl
```

For the Kolab calendar and tasklist plugins, the `calendar_enabled`,
`calendar_disabled`, `tasklist_enabled` and `tasklist_disabled` options are
also set.

### Examples

Some users on another mail server, with non-standard ports:
```
alice@example.com	imap_host=ssl://imap2.example.com:1993	smtp_host=ssl://smtp2.example.com:2465	managesieve_host=tls://imap2.example.com:4190
bob@example.com	imap_host=ssl://imap2.example.com:1993	smtp_host=ssl://smtp2.example.com:2465	managesieve_host=tls://imap2.example.com:4190
```

A whole domain on another server, except one user:
```
@partner.org	imap_host=tls://mail.partner.org:143	smtp_host=tls://mail.partner.org:587
boss@partner.org	imap_host=ssl://imap.example.com:993
```

An SMTP relay with its own account:
```
noreply@example.com	smtp_host=tls://relay.example.net:587	smtp_user=relay-account	smtp_pass=secret
```

Other options:
```
kiosk@example.com	skin=elastic	max_message_size=5M	enable_spellcheck=false
```

## With roundcube_new_oidc

[roundcube_new_oidc](https://github.com/CymDeveloppement/roundcube-new-oidc)
logs users in through the `authenticate` hook, like the login form: this
plugin works the same way.

- With a Dovecot master user (`oidc_config_master_user`), the login is
  `user@domain*master`: the file is looked up with `user@domain`, nothing
  to configure.
- The user keeps the same credentials on the other server: the other IMAP
  server must accept the same master user and password, or the password
  sent by the OIDC provider.
- Unless `smtp_user` and `smtp_pass` are set in the file, the SMTP server
  gets the same login and password as IMAP: with a master user, the login
  is `user@domain*master` and the password the master password.

## How it works

1. At login, the `authenticate` hook looks up the user in the file. When
   the entry has an `imap_host`, the user logs in to that server.
2. The settings of the entry are kept in the session (an empty set for
   users not listed, so a failed attempt never leaves the settings of
   another user).
3. On each request, the settings are applied to the configuration, and the
   plugins of the user are loaded.

## Notes

- **The file is read at login**: changes apply at the next login of the
  user.
- **Moving an existing user to another IMAP server**: Roundcube stores its
  users by login and IMAP host. A user logging in to another host gets a
  new Roundcube account: preferences, identities, contacts and responses of
  the old one are not carried over. Changing only the port does not matter.
- **The settings win over the user preferences**: an option set in the file
  (e.g. `skin`) cannot be changed by the user in the settings.
- **Not all options can be set**: options used before the plugins are
  loaded (database, session, `plugins`...) or only on the login page have
  no effect.
- If the file cannot be read, an error is logged and all users get the
  normal configuration.

## Migrating from ude_login

The users file format is unchanged.

1. Replace `ude_login` with `roundcube_user_config` in the `plugins` list.
2. Rename the options: `ude_login_db` to `user_config_db`, `ude_use_grep` to
   `user_config_use_grep`, `ude_default_plugins` to
   `user_config_default_plugins`. The old names are still read when the new
   ones are not set.

Users logged in during the switch lose their settings (SMTP server,
plugins...) until their next login: switch at a quiet time, or close the
open sessions.

## Credits

Based on [ude_login](https://gitlab.com/kolab-roundcube-plugins/ude-login)
by Thomas Bruederli, Kolab Systems AG.

## License

GNU GPLv3 or later, see [LICENSE](LICENSE).
