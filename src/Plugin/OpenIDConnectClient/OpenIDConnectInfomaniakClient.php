<?php

namespace Drupal\infomaniak_connect\Plugin\OpenIDConnectClient;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\externalauth\ExternalAuthInterface;
use Drupal\openid_connect\Plugin\OpenIDConnectClient\OpenIDConnectGenericClient;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Infomaniak OpenID Connect client.
 *
 * @OpenIDConnectClient(
 *   id = "infomaniak",
 *   label = @Translation("Infomaniak OAuth 2.0")
 * )
 */
class OpenIDConnectInfomaniakClient extends OpenIDConnectGenericClient implements OpenIDConnectInfomaniakClientInterface {

  const FILTER_TYPE_DENY = 0;

  const FILTER_TYPE_ALLOW = 1;

  /**
   * The email validator.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  protected EmailValidatorInterface $emailValidator;

  /**
   * The external auth service.
   *
   * @var \Drupal\externalauth\ExternalAuthInterface
   */
  protected ExternalAuthInterface $externalAuth;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'emails_filter' => '',
      'admin_emails' => '',
      'userinfo_update_email' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->emailValidator = $container->get('email.validator');
    $instance->externalAuth = $container->get('externalauth.externalauth');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['infomaniak_settings_access'] = [
      '#markup' => '<h2>' . $this->t('Login Access Settings') . '</h2>',
    ];

    $form['userinfo_update_email'] = [
      '#title' => $this->t('Update email address in user profile'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['userinfo_update_email'] ?? FALSE,
      '#description' => $this->t("If the email address has been changed for an existing user, update the user's profile with the new email address"),
    ];

    $form['emails_filter_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Access mode'),
      '#description' => $this->t('Choose whether you want to allow or block user logins through Infomaniak Auth based on the email addresses provided.'),
      '#options' => [
        self::FILTER_TYPE_DENY => $this->t('Deny'),
        self::FILTER_TYPE_ALLOW => $this->t('Allow'),
      ],
      '#default_value' => $this->configuration['emails_filter_type'] ?? self::FILTER_TYPE_ALLOW,
    ];

    $form['emails_filter'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Email Addresses'),
      '#default_value' => $this->configuration['emails_filter'] ?? '',
      '#description' => $this->t('Enter email addresses that are allowed or blocked from logging in. You can use wildcards (e.g., *@infomaniak.ch) to specify domains. Enter one email address per line.'),
      '#rows' => 10,
    ];

    $form['infomaniak_settings_permission'] = [
      '#markup' => '<h2>' . $this->t('Administrator Role Settings') . '</h2>',
    ];

    $form['admin_emails'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Administrator Emails'),
      '#default_value' => $this->configuration['admin_emails'] ?? '',
      '#description' => $this->t('Enter the email addresses of administrators who should have elevated access. One email address per line.'),
      '#rows' => 10,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    if (!empty($form_state->getValue('admin_emails'))) {
      $admin_emails = $form_state->getValue('admin_emails');
      $admin_emails = is_string($admin_emails) ? $admin_emails : '';
      $emails = explode("\n", $admin_emails);
      foreach ($emails as $email) {
        if (!filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
          $form_state->setErrorByName('admin_emails', $this->t('Invalid email address: @email', ['@email' => trim($email)]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveUserInfo($access_token): ?array {
    $userinfo = parent::retrieveUserInfo($access_token);
    $sub = (isset($userinfo['sub'])) ? $userinfo['sub'] : FALSE;

    // Synchronise email address.
    if ($this->configuration['userinfo_update_email'] == 1 && !empty($userinfo) && $sub) {
      // Try finding a connected user profile.
      $account = $this->externalAuth->load($sub, 'openid_connect.' . $this->getPluginId());
      if ($account instanceof UserInterface) {
        $this->synchroniseEmail($account, $userinfo);
      }
    }

    return $userinfo;
  }

  /**
   * {@inheritdoc}
   */
  public function synchroniseEmail(UserInterface $account, array $userinfo): void {
    if (array_key_exists('email', $userinfo) && $account->getEmail() !== $userinfo['email']) {
      // Check whether the e-mail address is valid.
      if (!$this->emailValidator->isValid($userinfo['email'])) {
        $this->messenger()->addError($this->t(
          'The e-mail address is not valid: @email',
          [
            '@email' => $userinfo['email'],
          ]
        ));
        return;
      }

      // Check whether there is an e-mail address conflict.
      try {
        $user_storage = $this->entityTypeManager->getStorage('user');
        $users = $user_storage->loadByProperties(['mail' => $userinfo['email']]);
        $user = $users ? reset($users) : FALSE;

        if ($user && $account->id() != $user->id()) {
          $this->messenger()->addError($this->t(
            'The e-mail address is already taken: @email',
            [
              '@email' => $userinfo['email'],
            ]
          ));
          return;
        }
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        return;
      }

      // Update email if all checks pass.
      try {
        $account->setEmail($userinfo['email']);
        $account->save();
      }
      catch (EntityStorageException $e) {
        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEmailsFilterType(): int {
    return $this->configuration['emails_filter_type'] ?? self::FILTER_TYPE_ALLOW;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmailsFilter(): array {
    $emails = $this->configuration['emails_filter'] ?? '';
    return array_filter(array_map('trim', explode("\n", $emails)));
  }

  /**
   * {@inheritdoc}
   */
  public function isAdminEmail(string $userinfo_email): bool {
    $emails = $this->getAdminEmails();
    if (in_array($userinfo_email, $emails, TRUE)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isLoginAuthorized(string $email): bool {
    // Validate the email address format.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return FALSE;
    }

    // Allow if the email is an administrator user.
    if ($this->isAdminEmail($email)) {
      return TRUE;
    }

    // Get the current filter type (ALLOW or DENY)
    // and the list of email filters.
    $emails_filter_type = $this->getEmailsFilterType();
    $emails_filter = $this->getEmailsFilter();

    // Manage empty emails filter.
    if (empty($emails_filter)) {
      return $emails_filter_type === self::FILTER_TYPE_ALLOW;
    }

    return $this->matchesEmailFilters($email, $emails_filter, $emails_filter_type);
  }

  /**
   * Determines if an email matches the filters based on the filter type.
   *
   * @param string $email
   *   The email address to check.
   * @param array $filters
   *   List of email filters to match against.
   * @param int $filterType
   *   The type of filter (ALLOW or DENY).
   *
   * @return bool
   *   True if the email is authorized, false otherwise.
   */
  private function matchesEmailFilters(string $email, array $filters, int $filterType): bool {
    $isFilterMatch = $this->hasEmailFilterMatch($email, $filters);

    return ($filterType === self::FILTER_TYPE_ALLOW)
      ? $isFilterMatch
      : !$isFilterMatch;
  }

  /**
   * Checks if the email matches any of the given filters.
   *
   * @param string $email
   *   The email address to check.
   * @param array $filters
   *   List of email filters to match against.
   *
   * @return bool
   *   True if the email matches any filter, false otherwise.
   */
  private function hasEmailFilterMatch(string $email, array $filters): bool {
    foreach ($filters as $filter) {
      if ($this->doesEmailMatchPattern($email, $filter)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Get the admin email addresses.
   *
   * @return array
   *   The list of admin email addresses.
   */
  private function getAdminEmails(): array {
    $emails = $this->configuration['admin_emails'] ?? '';
    return array_filter(array_map('trim', explode("\n", $emails)));
  }

  /**
   * Helper function to match an email against a pattern.
   *
   * @param string $email
   *   The email to match.
   * @param string $pattern
   *   The filter pattern.
   *
   * @return bool
   *   TRUE if the email matches the pattern, FALSE otherwise.
   */
  private function doesEmailMatchPattern(string $email, string $pattern): bool {
    // Convert wildcard characters (*) to regex.
    $regexPattern = '/^' . str_replace(
        ['*', '.'], ['.*', '\.'],
        $pattern
      ) . '$/i';

    // Handle special characters and make sure the pattern is correct.
    $regexPattern = str_replace('\.*', '.*', $regexPattern);

    return preg_match($regexPattern, $email) === 1;
  }

}
