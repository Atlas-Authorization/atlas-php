<?php

declare(strict_types=1);

namespace Atlas;

/**
 * Webhook event names: the canonical spelling (`snake_case` nouns, `noun.verb`) and the deprecated aliases that endpoints with
 * `event_naming: "legacy"` still receive. {@see canonical()} maps any spelling to the canonical name; an endpoint subscribed to
 * either spelling gets the event.
 */
final class WebhookEvents
{
    /** A secret key is about to expire (payload `days_left`, `threshold_days`). */
    public const SECRET_KEY_EXPIRING = 'secret_key.expiring';
    /** A secret key was revoked. */
    public const SECRET_KEY_REVOKED = 'secret_key.revoked';
    /** A privacy request changed state / finished. */
    public const PRIVACY_REQUEST_UPDATED = 'privacy_request.updated';
    public const PRIVACY_REQUEST_COMPLETED = 'privacy_request.completed';
    /** A deletion was scheduled / withdrawn (the withdrawal's old spelling is `user.deletion_cancelled`). */
    public const USER_DELETION_SCHEDULED = 'user.deletion_scheduled';
    public const USER_DELETION_CANCELED = 'user.deletion_canceled';
    /** A webhook endpoint is failing / was disabled / recovered. */
    public const WEBHOOK_ENDPOINT_FAILING = 'webhook_endpoint.failing';
    public const WEBHOOK_ENDPOINT_DISABLED = 'webhook_endpoint.disabled';
    public const WEBHOOK_ENDPOINT_RECOVERED = 'webhook_endpoint.recovered';
    /** An outbound delivery target (back-channel receiver, referral connection, integration) is failing / disabled / recovered. */
    public const DELIVERY_TARGET_FAILING = 'delivery_target.failing';
    public const DELIVERY_TARGET_DISABLED = 'delivery_target.disabled';
    public const DELIVERY_TARGET_RECOVERED = 'delivery_target.recovered';
    /** The synthetic event of an endpoint test. */
    public const WEBHOOK_TEST = 'webhook.test';
    /** Status incidents (payload: a status incident). */
    public const STATUS_INCIDENT_CREATED = 'status.incident.created';
    public const STATUS_INCIDENT_UPDATED = 'status.incident.updated';
    public const STATUS_INCIDENT_RESOLVED = 'status.incident.resolved';
    /** Renamed events, canonical spellings. */
    public const SESSION_REVOKED = 'session.revoked';
    public const ORGANIZATION_MEMBERSHIP_CREATED = 'organization_membership.created';
    public const ORGANIZATION_MEMBERSHIP_UPDATED = 'organization_membership.updated';
    public const ORGANIZATION_MEMBERSHIP_DELETED = 'organization_membership.deleted';
    public const ORGANIZATION_INVITATION_CREATED = 'organization_invitation.created';
    public const ORGANIZATION_INVITATION_ACCEPTED = 'organization_invitation.accepted';
    public const ORGANIZATION_INVITATION_REVOKED = 'organization_invitation.revoked';
    public const ORGANIZATION_INVITATION_DECLINED = 'organization_invitation.declined';
    public const ORGANIZATION_INVITATION_RESENT = 'organization_invitation.resent';
    public const TEAM_MEMBERSHIP_CREATED = 'team_membership.created';
    public const TEAM_MEMBERSHIP_DELETED = 'team_membership.deleted';
    public const APP_ROLE_CREATED = 'app_role.created';
    public const APP_ROLE_UPDATED = 'app_role.updated';
    public const APP_ROLE_DELETED = 'app_role.deleted';
    public const ORGANIZATION_DOMAIN_CREATED = 'organization_domain.created';
    public const ORGANIZATION_DOMAIN_UPDATED = 'organization_domain.updated';
    public const ORGANIZATION_DOMAIN_DELETED = 'organization_domain.deleted';
    public const ORGANIZATION_SETTINGS_UPDATED = 'organization_settings.updated';
    public const WAITLIST_ENTRY_CREATED = 'waitlist_entry.created';
    public const WAITLIST_ENTRY_UPDATED = 'waitlist_entry.updated';
    public const BILLING_SUBSCRIPTION_CREATED = 'billing_subscription.created';
    public const BILLING_SUBSCRIPTION_UPDATED = 'billing_subscription.updated';
    public const BILLING_SUBSCRIPTION_CANCELED = 'billing_subscription.canceled';
    public const BILLING_INVOICE_PAYMENT_FAILED = 'billing_invoice.payment_failed';
    public const BILLING_TRIAL_STARTED = 'billing_trial.started';
    public const BILLING_TRIAL_WILL_END = 'billing_trial.will_end';
    public const BILLING_TRIAL_ENDED = 'billing_trial.ended';
    public const ENTITLEMENT_OVERRIDE_CREATED = 'entitlement_override.created';
    public const ENTITLEMENT_OVERRIDE_DELETED = 'entitlement_override.deleted';
    public const ENTITLEMENT_OVERRIDE_EXPIRED = 'entitlement_override.expired';
    public const SCIM_TOKEN_CREATED = 'scim_token.created';
    public const SCIM_TOKEN_REVOKED = 'scim_token.revoked';
    public const SSO_CONNECTION_CREATED = 'sso_connection.created';
    public const SSO_CONNECTION_UPDATED = 'sso_connection.updated';
    public const SSO_CONNECTION_DELETED = 'sso_connection.deleted';
    public const USER_DATA_EXPORT_REQUESTED = 'user.data_export_requested';

    /**
     * Deprecated spelling => canonical name. `session.ended`, `session.removed` and `user.deletion_cancelled` are
     * companions (sent alongside the canonical event to a legacy endpoint); the rest are plain renames.
     */
    public const ALIASES = [
        'session.ended' => 'session.revoked',
        'session.removed' => 'session.revoked',
        'organizationMembership.created' => 'organization_membership.created',
        'organizationMembership.updated' => 'organization_membership.updated',
        'organizationMembership.deleted' => 'organization_membership.deleted',
        'organization.membership.deleted' => 'organization_membership.deleted',
        'organizationInvitation.created' => 'organization_invitation.created',
        'organizationInvitation.accepted' => 'organization_invitation.accepted',
        'organizationInvitation.revoked' => 'organization_invitation.revoked',
        'organizationInvitation.declined' => 'organization_invitation.declined',
        'organizationInvitation.resent' => 'organization_invitation.resent',
        'teamMembership.created' => 'team_membership.created',
        'teamMembership.deleted' => 'team_membership.deleted',
        'appRole.created' => 'app_role.created',
        'appRole.updated' => 'app_role.updated',
        'appRole.deleted' => 'app_role.deleted',
        'organizationDomain.created' => 'organization_domain.created',
        'organizationDomain.updated' => 'organization_domain.updated',
        'organizationDomain.deleted' => 'organization_domain.deleted',
        'organization.settings.updated' => 'organization_settings.updated',
        'waitlistEntry.created' => 'waitlist_entry.created',
        'waitlistEntry.updated' => 'waitlist_entry.updated',
        'billing.subscription.created' => 'billing_subscription.created',
        'billing.subscription.updated' => 'billing_subscription.updated',
        'billing.subscription.canceled' => 'billing_subscription.canceled',
        'billing.invoice.payment_failed' => 'billing_invoice.payment_failed',
        'billing.trial.started' => 'billing_trial.started',
        'billing.trial.will_end' => 'billing_trial.will_end',
        'billing.trial.ended' => 'billing_trial.ended',
        'entitlement.override.created' => 'entitlement_override.created',
        'entitlement.override.deleted' => 'entitlement_override.deleted',
        'entitlement.override.expired' => 'entitlement_override.expired',
        'scimToken.created' => 'scim_token.created',
        'scimToken.revoked' => 'scim_token.revoked',
        'ssoConnection.created' => 'sso_connection.created',
        'ssoConnection.updated' => 'sso_connection.updated',
        'ssoConnection.deleted' => 'sso_connection.deleted',
        'user.data_export.requested' => 'user.data_export_requested',
        'user.deletion_cancelled' => 'user.deletion_canceled',
    ];

    /** The canonical name for any spelling (an unknown name is returned unchanged). */
    public static function canonical(string $name): string
    {
        return self::ALIASES[$name] ?? $name;
    }

    public static function isDeprecated(string $name): bool
    {
        return isset(self::ALIASES[$name]);
    }

    /**
     * Every name an event is known by: the canonical one, then its aliases.
     *
     * @return list<string>
     */
    public static function spellings(string $name): array
    {
        $canonical = self::canonical($name);
        $out = [$canonical];
        foreach (self::ALIASES as $alias => $target) {
            if ($target === $canonical) {
                $out[] = $alias;
            }
        }

        return $out;
    }

    /** True when `$eventType` (any spelling) is `$wanted` (any spelling). */
    public static function matches(string $eventType, string $wanted): bool
    {
        return self::canonical($eventType) === self::canonical($wanted);
    }
}
