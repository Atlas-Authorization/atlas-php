# Atlas Authentication for Drupal

"Log in with Atlas" for Drupal 10.3+ and 11: OpenID Connect single sign-on built on the official Atlas PHP SDK. Your Atlas instance is the OpenID Provider; this module is the relying party. It runs the authorization-code flow with PKCE, verifies the ID token locally against your instance's key set (cached, no call to Atlas per login beyond the code exchange), and logs the person in.

## Install

```bash
composer require atlas-auth/atlas-drupal
drush en atlas_auth
```

The module needs [External Authentication](https://www.drupal.org/project/externalauth) (installed by Composer), which keeps the link between a Drupal account and the person's Atlas id.

## Configure

1. In Atlas, register an OAuth client and add `https://your-site.example/user/login/atlas/callback` as a redirect URI.
2. Open **Configuration > People > Atlas authentication** and enter the instance domain, client ID and client secret. Prefer keeping the secret out of the database: `$config['atlas_auth.settings']['client_secret'] = getenv('ATLAS_CLIENT_SECRET');` in `settings.php`.
3. A **Log in with Atlas** button appears on the login form.

## How accounts are matched

1. An account already linked to the person's Atlas id.
2. Otherwise an existing account with the same email address, but only when Atlas reports the address as verified. The account is then linked.
3. Otherwise a new account, if you turned on **Create an account on first login** (and the ID token carries an email).

Restrict who may log in this way with **Roles allowed to log in with Atlas**: a user must hold at least one of the checked roles. A blocked account never logs in. Failures redirect to the login form with a generic message; the reason is only in the log.

## Security

- PKCE (S256), a single-use `state` kept in the session for 10 minutes, and a `nonce` bound to the ID token.
- The ID token's signature (RS256), issuer, audience (your client ID), expiry (5 seconds of skew) and nonce are verified; `alg: none` and other algorithms are refused.
- Unverified email addresses are never used to take over an existing account.

## Tests

`composer install && vendor/bin/phpunit` runs the OIDC, mapping and wiring tests without a Drupal site.
