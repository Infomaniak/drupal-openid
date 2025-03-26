<?php

namespace Drupal\infomaniak_connect\Plugin\OpenIDConnectClient;

use Drupal\user\UserInterface;

/**
 * Infomaniak OpenID Connect client.
 *
 * @OpenIDConnectClient(
 *   id = "infomaniak",
 *   label = @Translation("Infomaniak OAuth 2.0")
 * )
 */
interface OpenIDConnectInfomaniakClientInterface {

  /**
   * Synchronize email address with IDP.
   *
   * @param \Drupal\user\UserInterface $account
   *   The loaded Drupal user.
   * @param array $userinfo
   *   An array of user information from the userinfo endpoint.
   */
  public function synchroniseEmail(UserInterface $account, array $userinfo): void;

  /**
   * Get the filter type (Allow or Deny).
   *
   * @return int
   *   The filter type.
   */
  public function getEmailsFilterType(): int;

  /**
   * Get the configured email filters.
   *
   * @return array
   *   The list of email filters.
   */
  public function getEmailsFilter(): array;

  /**
   * Checks if the given email is an admin email.
   *
   * @param string $userinfo_email
   *   The email address to check.
   *
   * @return bool
   *   Returns TRUE if the provided email is an admin email, FALSE otherwise.
   */
  public function isAdminEmail(string $userinfo_email): bool;

  /**
   * Check if the login is authorized for the given email address.
   *
   * @param string $email
   *   The email address to check.
   *
   * @return bool
   *   TRUE if login is authorized, FALSE otherwise.
   */
  public function isLoginAuthorized(string $email): bool;

}
