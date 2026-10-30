<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/messaging_providers` — use your own email or SMS provider. Provider secrets
 * are write-only: a response reports only which secret keys are set
 * (`secrets_set`), never their values.
 *
 * Each transport accepts an exact set of `config` keys (typed below); the API
 * refuses any other key with a 422 naming `config.<key>`. Keys marked
 * "required on first setup" must be sent the first time you configure a
 * transport (or switch the channel to it). On an update of the SAME transport,
 * an omitted (or `''`) key keeps its stored value — including a stored secret —
 * so a from-address change need not re-send the API key.
 *
 * Email transports (required on first setup → optional; * = write-only secret):
 *   resend: api_key* | brevo: api_key* | mailgun: api_key*, domain → region (us|eu)
 *   ses: region, access_key_id*, secret_access_key* → session_token*
 *   smtp: host → port (int), secure (bool), username, password*
 * SMS transports (all also accept an optional `from`):
 *   twilio: account_sid, auth_token* → whatsapp_from, voice_from
 *   vonage: api_key, api_secret* | messagebird: access_key* | plivo: auth_id, auth_token*
 *   telnyx: api_key* | sinch: service_plan_id, api_token* → region
 *   infobip: base_url (https), api_key* | clicksend: username, api_key*
 *   bandwidth: account_id, application_id, username, password*
 *   aws_sns: region, access_key_id, secret_access_key* → session_token*
 *
 * @phpstan-type ResendConfig array{api_key?:string}
 * @phpstan-type BrevoConfig array{api_key?:string}
 * @phpstan-type MailgunConfig array{api_key?:string,domain?:string,region?:'us'|'eu'}
 * @phpstan-type SesConfig array{region?:string,access_key_id?:string,secret_access_key?:string,session_token?:string}
 * @phpstan-type SmtpConfig array{host?:string,port?:int,secure?:bool,username?:string,password?:string}
 * @phpstan-type EmailProviderBody array{transport:'resend',from_address?:string,mode?:'standard'|'development',config?:ResendConfig}
 *     |array{transport:'brevo',from_address?:string,mode?:'standard'|'development',config?:BrevoConfig}
 *     |array{transport:'mailgun',from_address?:string,mode?:'standard'|'development',config?:MailgunConfig}
 *     |array{transport:'ses',from_address?:string,mode?:'standard'|'development',config?:SesConfig}
 *     |array{transport:'smtp',from_address?:string,mode?:'standard'|'development',config?:SmtpConfig}
 * @phpstan-type TwilioConfig array{account_sid?:string,auth_token?:string,from?:string,whatsapp_from?:string,voice_from?:string}
 * @phpstan-type VonageConfig array{api_key?:string,api_secret?:string,from?:string}
 * @phpstan-type MessagebirdConfig array{access_key?:string,from?:string}
 * @phpstan-type PlivoConfig array{auth_id?:string,auth_token?:string,from?:string}
 * @phpstan-type TelnyxConfig array{api_key?:string,from?:string}
 * @phpstan-type SinchConfig array{service_plan_id?:string,api_token?:string,region?:string,from?:string}
 * @phpstan-type InfobipConfig array{base_url?:string,api_key?:string,from?:string}
 * @phpstan-type ClicksendConfig array{username?:string,api_key?:string,from?:string}
 * @phpstan-type BandwidthConfig array{account_id?:string,application_id?:string,username?:string,password?:string,from?:string}
 * @phpstan-type AwsSnsConfig array{region?:string,access_key_id?:string,secret_access_key?:string,session_token?:string,from?:string}
 * @phpstan-type SmsProviderBody array{transport:'twilio',from_address?:string,config?:TwilioConfig}
 *     |array{transport:'vonage',from_address?:string,config?:VonageConfig}
 *     |array{transport:'messagebird',from_address?:string,config?:MessagebirdConfig}
 *     |array{transport:'plivo',from_address?:string,config?:PlivoConfig}
 *     |array{transport:'telnyx',from_address?:string,config?:TelnyxConfig}
 *     |array{transport:'sinch',from_address?:string,config?:SinchConfig}
 *     |array{transport:'infobip',from_address?:string,config?:InfobipConfig}
 *     |array{transport:'clicksend',from_address?:string,config?:ClicksendConfig}
 *     |array{transport:'bandwidth',from_address?:string,config?:BandwidthConfig}
 *     |array{transport:'aws_sns',from_address?:string,config?:AwsSnsConfig}
 * Email bodies accept `mode`: `development` lets a development instance send without a verified
 * domain (Atlas sign-in templates only, capped); omitted on an update keeps the stored mode.
 * @phpstan-type MessagingProvider array{object:'messaging_provider',id?:string,channel:'email'|'sms',mode?:'standard'|'development'|'managed_domain',transport:string,from_address:string|null,status:string,config:array<string,string>,secrets_set:array<string,bool>,updated_at:int}
 */
final class Messaging extends Resource
{
    /** The instance's configured email and SMS providers, secrets omitted. @return array<string,mixed> */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/messaging_providers');
    }

    /**
     * Configure (or update) the email provider. `config` keys depend on `transport`.
     *
     * @param EmailProviderBody $body
     *
     * @return MessagingProvider
     */
    public function setEmail(array $body): array
    {
        return $this->http->request('PUT', '/v1/messaging_providers/email', body: $body);
    }

    /**
     * Configure (or update) the SMS provider. `config` keys depend on `transport`.
     *
     * @param SmsProviderBody $body
     *
     * @return MessagingProvider
     */
    public function setSms(array $body): array
    {
        return $this->http->request('PUT', '/v1/messaging_providers/sms', body: $body);
    }

    /**
     * Remove the channel's provider. Returns `{object: 'messaging_provider', channel, deleted: true}`.
     *
     * Removing the email provider is refused with a 409 `EMAIL_PROVIDER_IN_USE` while nothing else
     * would deliver email; the error's `meta.features` lists the email-dependent features that are on
     * (e.g. `emailCode`, `emailLink`, `emailVerification`, `passwordReset`). Pass `force: true` to
     * remove it anyway.
     *
     * @return array<string,mixed>
     */
    public function deleteChannel(string $channel, bool $force = false): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/messaging_providers/' . rawurlencode($channel),
            query: $force ? ['force' => true] : null,
        );
    }

    /**
     * `GET /v1/messaging_providers/email/managed` — the managed sending domain: `status`,
     * `verified`, the DNS `records` to publish and `usage` (`limits` is null: your provider
     * account's own limits apply), plus `backend` and `provider_id`. 404 when none is configured.
     * `dns` (when Atlas writes the records) is `{provider, token_set, status (pending|verifying|verified|failed|null),
     * last_error, attempts, next_check_at, started_at}`; `feedback_webhook_id` / `feedback_webhook_error` report the
     * bounce / complaint webhook on your provider account.
     *
     * @return array<string,mixed> the managed_email object
     */
    public function getManagedEmail(): array
    {
        return $this->http->request('GET', '/v1/messaging_providers/email/managed');
    }

    /**
     * `POST /v1/messaging_providers/email/managed` — Atlas sets your sending domain up on YOUR OWN
     * Resend or Brevo account: pass `transport` with `config.api_key` (write-only), or `provider_id`
     * of your configured Resend or Brevo provider. Returns the DNS `records` to publish; with `dns`
     * (`provider: cloudflare`, write-only `api_token`, optional `overwrite`) Atlas writes them and verifies in the
     * background: the call answers at once (202) and `dns.status` tracks the progress.
     * On Brevo the response says whether the bounce webhook was registered (`feedback_webhook`).
     *
     * @param array{from_address:string,transport?:'resend'|'brevo',config?:array{api_key:string},provider_id?:string,region?:string,dns?:array{provider:string,api_token:string,overwrite?:bool}} $body
     *
     * @return array<string,mixed> the managed_email object
     */
    public function createManagedEmail(array $body): array
    {
        return $this->http->request('POST', '/v1/messaging_providers/email/managed', body: $body);
    }

    /**
     * `POST /v1/messaging_providers/email/managed/verify` — re-check the DNS records now.
     *
     * @return array<string,mixed> the managed_email object
     */
    public function verifyManagedEmail(): array
    {
        return $this->http->request('POST', '/v1/messaging_providers/email/managed/verify');
    }

    /**
     * `DELETE /v1/messaging_providers/email/managed` — stop using managed email (DNS records stay).
     * Refused with 409 `EMAIL_PROVIDER_IN_USE` while it is the only way to send email; pass
     * `$force = true` to remove it anyway.
     *
     * @return array{object:string,domain:string,deleted:bool,provider_removed?:bool}
     */
    public function deleteManagedEmail(bool $force = false): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/messaging_providers/email/managed',
            query: $force ? ['force' => true] : null,
        );
    }

    /**
     * `GET /v1/messaging_providers/email/development` — your email provider in development mode:
     * `provider_id`, `active`, `transport`, `from_address`, `subject_prefix`, `footer`, `templates`
     * and `limits` (`daily`, `per_recipient_hourly`). 404 when it is not in development mode.
     *
     * @return array<string,mixed> the development_email object
     */
    public function getDevelopmentEmail(): array
    {
        return $this->http->request('GET', '/v1/messaging_providers/email/development');
    }

    /**
     * `POST /v1/messaging_providers/email/development` — put your own email provider in development
     * mode, so a development instance sends Atlas's sign-in emails without a verified domain
     * (`[Atlas test] ` subjects, capped). 409 `DEVELOPMENT_EMAIL_NOT_ALLOWED` on a production instance.
     *
     * @param array{provider_id?:string,transport?:'resend'|'ses'|'smtp'|'brevo'|'mailgun',from_address?:string,config?:array<string,mixed>} $body
     *
     * @return array<string,mixed> the development_email object
     */
    public function enableDevelopmentEmail(array $body): array
    {
        return $this->http->request('POST', '/v1/messaging_providers/email/development', body: $body);
    }

    /**
     * `DELETE /v1/messaging_providers/email/development` — back to standard mode (the provider and
     * its key stay).
     *
     * @return array{object:string,deleted:bool,provider_id:string,mode:string}
     */
    public function disableDevelopmentEmail(): array
    {
        return $this->http->request('DELETE', '/v1/messaging_providers/email/development');
    }

    /**
     * Send a fixed, clearly-marked test message through the channel's provider.
     *
     * @param array{to:string} $body
     *
     * @return array<string,mixed>
     */
    public function test(string $channel, array $body): array
    {
        return $this->http->request('POST', '/v1/messaging_providers/' . rawurlencode($channel) . '/test', body: $body);
    }

    /**
     * `GET /v1/messaging_providers/:channel` — one channel's provider (`email`|`sms`). `configured` is false, not a 404, when none is set.
     *
     * @return array<string,mixed>
     */
    public function getChannel(string $channel): array
    {
        return $this->http->request('GET', '/v1/messaging_providers/' . rawurlencode($channel));
    }
}
