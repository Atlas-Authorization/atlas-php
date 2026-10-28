<?php

declare(strict_types=1);

namespace Drupal\atlas_auth\Core;

/**
 * Raised when a verified Atlas identity cannot be mapped onto a Drupal user:
 * no linked account and auto-provisioning is off, a provision without an email,
 * or a role not on the allowlist. Distinct from {@see OidcException},
 * which is about the token itself.
 */
final class LoginException extends \RuntimeException
{
}
