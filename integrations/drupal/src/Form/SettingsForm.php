<?php

declare(strict_types=1);

namespace Drupal\atlas_auth\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\Role;

/**
 * Configuration → People → Atlas authentication.
 */
final class SettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'atlas_auth_settings';
  }

  protected function getEditableConfigNames(): array {
    return ['atlas_auth.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('atlas_auth.settings');
    $roles = array_map(static fn (Role $role) => $role->label(), Role::loadMultiple());
    unset($roles['anonymous']);

    $form['instance_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instance domain'),
      '#description' => $this->t('Your Atlas frontend API host, for example auth.example.com. Tokens are issued and verified against it.'),
      '#default_value' => $config->get('instance_domain'),
      '#required' => TRUE,
    ];
    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#description' => $this->t('The OAuth client registered in Atlas. Its redirect URI must be the callback URL: @url', [
        '@url' => Url::fromRoute('atlas_auth.callback', [], ['absolute' => TRUE])->toString(),
      ]),
      '#default_value' => $config->get('client_id'),
      '#required' => TRUE,
    ];
    $form['client_secret'] = [
      '#type' => 'password',
      '#title' => $this->t('Client secret'),
      '#description' => $this->t('Leave blank to keep the saved secret. Better still, keep it out of the database: set $config["atlas_auth.settings"]["client_secret"] in settings.php.'),
    ];
    $form['scopes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Scopes'),
      '#description' => $this->t('Space separated. "openid" is always requested.'),
      '#default_value' => implode(' ', $config->get('scopes') ?? ['openid', 'email', 'profile']),
    ];
    $form['allowed_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles allowed to log in with Atlas'),
      '#description' => $this->t('A user must hold at least one of the checked roles. Leave all unchecked to allow everyone.'),
      '#options' => $roles,
      '#default_value' => array_values($config->get('allowed_roles') ?? []),
    ];
    $form['auto_provision'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create an account on first login'),
      '#description' => $this->t('Off: only people who already have an account can log in. An existing account is matched by a verified email address only.'),
      '#default_value' => (bool) $config->get('auto_provision'),
    ];
    $form['default_role'] = [
      '#type' => 'select',
      '#title' => $this->t('Role for new accounts'),
      '#options' => ['' => $this->t('None (authenticated user only)')] + array_diff_key($roles, ['authenticated' => TRUE]),
      '#default_value' => $config->get('default_role') ?? '',
      '#states' => ['visible' => [':input[name="auto_provision"]' => ['checked' => TRUE]]],
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('atlas_auth.settings');
    $secret = (string) $form_state->getValue('client_secret');
    $config
      ->set('instance_domain', trim((string) $form_state->getValue('instance_domain')))
      ->set('client_id', trim((string) $form_state->getValue('client_id')))
      ->set('scopes', preg_split('/\s+/', trim((string) $form_state->getValue('scopes')), -1, PREG_SPLIT_NO_EMPTY) ?: [])
      ->set('allowed_roles', array_values(array_filter($form_state->getValue('allowed_roles') ?? [])))
      ->set('auto_provision', (bool) $form_state->getValue('auto_provision'))
      ->set('default_role', (string) $form_state->getValue('default_role'));
    if ($secret !== '') {
      $config->set('client_secret', $secret);
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
