<?php

namespace Drupal\Tests\infomaniak_connect\Unit\Plugin\OpenIDConnectClient;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\externalauth\ExternalAuth;
use Drupal\infomaniak_connect\Plugin\OpenIDConnectClient\OpenIDConnectInfomaniakClient;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\infomaniak_connect\Plugin\OpenIDConnectClient\OpenIDConnectInfomaniakClientInterface;
use Drupal\openid_connect\OpenIDConnectStateTokenInterface;
use Drupal\openid_connect\OpenIDConnectAutoDiscover;
use Drupal\user\Entity\User;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Test case for Infomaniak Connect plugin.
 *
 * @coversDefaultClass \Drupal\infomaniak_connect\Plugin\OpenIDConnectClient\OpenIDConnectInfomaniakClient
 * @group infomaniak_connect
 */
class OpenIDConnectInfomaniakClientTest extends TestCase {

  const PLUGIN_ID = 'infomaniak';

  const LOGIN_ALLOW = TRUE;

  const LOGIN_DENY = FALSE;

  const USER_EMAIL = 'valid@infomaniak.com';

  const USER_NEW_EMAIL = 'new@infomaniak.com';

  const INVALID_EMAIL = 'invalid';

  const DUPLICATE_EMAIL = 'duplicate@infomaniak.com';

  const ERROR_INVALID_EMAIL = 'The e-mail address is not valid: @email';

  const ERROR_DUPLICATE_EMAIL = 'The e-mail address is already taken: @email';

  /**
   * Mock of the request stack service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Symfony\Component\HttpFoundation\RequestStack
   */
  private MockObject|RequestStack $requestStack;

  /**
   * Mock of the HTTP client.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\GuzzleHttp\ClientInterface
   */
  private MockObject|ClientInterface $httpClient;

  /**
   * Mock of the logger factory.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private MockObject|LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Mock of the datetime.time service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Component\Datetime\TimeInterface
   */
  private MockObject|TimeInterface $datetimeTime;

  /**
   * Mock of the Page cache kill switch.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  private MockObject|KillSwitch $pageCacheKillSwitch;

  /**
   * Mock of the language manager.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Core\Language\LanguageManagerInterface
   */
  private MockObject|LanguageManagerInterface $languageManager;

  /**
   * Mock of the OpenID state token service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\openid_connect\OpenIDConnectStateTokenInterface
   */
  private MockObject|OpenIDConnectStateTokenInterface $stateToken;

  /**
   * OpenID Connect well-known URI discovery service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\openid_connect\OpenIDConnectAutoDiscover
   */
  private MockObject|OpenIDConnectAutoDiscover $autoDiscover;

  /**
   * Mock the email validator service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Component\Utility\EmailValidatorInterface
   */
  private MockObject|EmailValidatorInterface $emailValidator;

  /**
   * Mock of the externalAuth service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\externalauth\ExternalAuth
   */
  private MockObject|ExternalAuth $externalAuth;

  /**
   * Mock of the user storage service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Core\Entity\EntityStorageInterface
   */
  private MockObject|EntityStorageInterface $userStorage;

  /**
   * Mock of the entity_type.manager service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private MockObject|EntityTypeManagerInterface $entityTypeManager;

  /**
   * Mock of the messenger service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Core\Messenger\MessengerInterface
   */
  private MockObject|MessengerInterface $messenger;

  /**
   * Mock the translation service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Core\StringTranslation\TranslationInterface
   */
  protected MockObject|TranslationInterface $stringTranslation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Init mocks.
    $this->initializeMocks();
  }

  /**
   * Initialize all required mocks.
   */
  private function initializeMocks(): void {
    // Initialize mocks.
    $this->requestStack = $this->createMock(RequestStack::class);
    $this->httpClient = $this->createMock(ClientInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->datetimeTime = $this->createMock(TimeInterface::class);
    $this->pageCacheKillSwitch = $this->createMock(KillSwitch::class);
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->stateToken = $this->createMock(OpenIDConnectStateTokenInterface::class);
    $this->autoDiscover = $this->createMock(OpenIDConnectAutoDiscover::class);
    $this->emailValidator = $this->createMock(EmailValidatorInterface::class);
    $this->externalAuth = $this->createMock(ExternalAuth::class);
    $this->userStorage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager->method('getStorage')
      ->with('user')
      ->willReturn($this->userStorage);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->stringTranslation = $this->createMock(TranslationInterface::class);
  }

  /**
   * Creates a client instance with given configuration.
   */
  private function createClient(array $config): OpenIDConnectInfomaniakClientInterface {
    $container = new ContainerBuilder();
    $this->setContainerServices($container);
    \Drupal::setContainer($container);

    return OpenIDConnectInfomaniakClient::create(
      $container,
      $config,
      self::PLUGIN_ID,
      []
    );
  }

  /**
   * Sets up container services.
   */
  private function setContainerServices(ContainerBuilder $container): void {
    $container->set('email.validator', $this->emailValidator);
    $container->set('externalauth.externalauth', $this->externalAuth);
    $container->set('request_stack', $this->requestStack);
    $container->set('http_client', $this->httpClient);
    $container->set('logger.factory', $this->loggerFactory);
    $container->set('datetime.time', $this->datetimeTime);
    $container->set('page_cache_kill_switch', $this->pageCacheKillSwitch);
    $container->set('language_manager', $this->languageManager);
    $container->set('openid_connect.state_token', $this->stateToken);
    $container->set('openid_connect.autodiscover', $this->autoDiscover);
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('messenger', $this->messenger);
    $container->set('string_translation', $this->stringTranslation);
  }

  /**
   * Test the method getEmailsFilterType() and getEmailsFilter.
   *
   * @covers ::getEmailsFilterType
   * @covers ::getEmailsFilter
   * @dataProvider filterConfigurationProvider
   */
  public function testFilterConfiguration(
    array $config,
    int $expectedFilterType,
    array $expectedFilters,
  ): void {
    $client = $this->createClient($config);
    $this->assertEquals($expectedFilterType, $client->getEmailsFilterType());
    $this->assertEquals($expectedFilters, $client->getEmailsFilter());
  }

  /**
   * Data provider for testFilterConfiguration.
   */
  public static function filterConfigurationProvider(): array {
    return [
      'allow_mode_with_filters' => [
        [
          'emails_filter' => "admin@example.com\n*@infomaniak.com",
          'emails_filter_type' => OpenIDConnectInfomaniakClient::FILTER_TYPE_ALLOW,
        ],
        OpenIDConnectInfomaniakClient::FILTER_TYPE_ALLOW,
        [
          "admin@example.com",
          "*@infomaniak.com",
        ],
      ],
      'allow_mode_empty_filters' => [
        [
          'emails_filter' => "",
          'emails_filter_type' => OpenIDConnectInfomaniakClient::FILTER_TYPE_ALLOW,
        ],
        OpenIDConnectInfomaniakClient::FILTER_TYPE_ALLOW,
        [],
      ],
      'deny_mode_with_filters' => [
        [
          'emails_filter' => "blocked@example.com\n*@blocked.com",
          'emails_filter_type' => OpenIDConnectInfomaniakClient::FILTER_TYPE_DENY,
        ],
        OpenIDConnectInfomaniakClient::FILTER_TYPE_DENY,
        [
          "blocked@example.com",
          "*@blocked.com",
        ],
      ],
      'deny_mode_empty_filters' => [
        [
          'emails_filter' => "",
          'emails_filter_type' => OpenIDConnectInfomaniakClient::FILTER_TYPE_DENY,
        ],
        OpenIDConnectInfomaniakClient::FILTER_TYPE_DENY,
        [],
      ],
    ];
  }

  /**
   * Test the method isLoginAuthorized()
   *
   * @covers ::isLoginAuthorized
   * @dataProvider loginAuthorizationProvider
   */
  public function testLoginAuthorization(
    array $config,
    string $email,
    bool $expectedResult,
  ): void {
    $client = $this->createClient($config);
    $client->isLoginAuthorized($email);
    $this->assertEquals($expectedResult, $client->isLoginAuthorized($email));
  }

  /**
   * Data provider for testLoginAuthorization.
   */
  public static function loginAuthorizationProvider(): array {
    return [
      'mode_allow_specific_email' => [
        [
          'emails_filter' => "admin@infomaniak.com\n*@infomaniak.com",
          'emails_filter_type' => OpenIDConnectInfomaniakClient::FILTER_TYPE_ALLOW,
        ],
        'admin@infomaniak.com',
        self::LOGIN_ALLOW,
      ],
      'mode_allow_wildcard_match' => [
        [
          'emails_filter' => "admin@infomaniak.com\n*@infomaniak.com",
          'emails_filter_type' => OpenIDConnectInfomaniakClient::FILTER_TYPE_ALLOW,
        ],
        'user@infomaniak.com',
        self::LOGIN_ALLOW,
      ],
      'mode_allow_unauthorized_email' => [
        [
          'emails_filter' => "admin@infomaniak.com\n*@infomaniak.com",
          'emails_filter_type' => OpenIDConnectInfomaniakClient::FILTER_TYPE_ALLOW,
        ],
        'user@gmail.com',
        self::LOGIN_DENY,
      ],
      'mode_allow_all_emails' => [
        [
          'emails_filter' => "",
          'emails_filter_type' => OpenIDConnectInfomaniakClient::FILTER_TYPE_ALLOW,
        ],
        'user@gmail.com',
        self::LOGIN_ALLOW,
      ],
      'mode_deny_specific_email' => [
        [
          'emails_filter' => "blocked@infomaniak.com\n*@blocked.com",
          'emails_filter_type' => OpenIDConnectInfomaniakClient::FILTER_TYPE_DENY,
        ],
        'blocked@infomaniak.com',
        self::LOGIN_DENY,
      ],
      'mode_deny_wildcard_match' => [
        [
          'emails_filter' => "blocked@infomaniak.com\n*@blocked.com",
          'emails_filter_type' => OpenIDConnectInfomaniakClient::FILTER_TYPE_DENY,
        ],
        'user@blocked.com',
        self::LOGIN_DENY,
      ],
      'mode_deny_authorized_email' => [
        [
          'emails_filter' => "blocked@infomaniak.com\n*@blocked.com",
          'emails_filter_type' => OpenIDConnectInfomaniakClient::FILTER_TYPE_DENY,
        ],
        'user@infomaniak.com',
        self::LOGIN_ALLOW,
      ],
      'mode_deny_all_emails' => [
        [
          'emails_filter' => "",
          'emails_filter_type' => OpenIDConnectInfomaniakClient::FILTER_TYPE_DENY,
        ],
        'user@infomaniak.com',
        self::LOGIN_DENY,
      ],
      'mode_deny_all_emails_admin_only' => [
        [
          'emails_filter' => "",
          'emails_filter_type' => OpenIDConnectInfomaniakClient::FILTER_TYPE_DENY,
          'admin_emails' => "admin@infomaniak.com",
        ],
        'admin@infomaniak.com',
        self::LOGIN_ALLOW,
      ],
    ];
  }

  /**
   * @covers ::synchroniseEmail
   * @dataProvider emailSynchronizationProvider
   */
  public function testEmailSynchronization(
    array $userInfo,
    bool $isValidEmail,
    bool $hasExistingUser,
    bool $shouldUpdateUser,
    ?string $expectedError = NULL,
  ): void {
    // Create a mock user.
    $mockUser = $this->createMock(User::class);
    $mockUser->method('getEmail')->willReturn(self::USER_EMAIL);

    // Test invalid email.
    $this->emailValidator
      ->method('isValid')
      ->with($userInfo['email'])
      ->willReturn($isValidEmail);

    // Test duplicate user.
    if ($hasExistingUser) {
      $mockExistingUser = $this->createMock(User::class);
      $mockExistingUser->method('id')->willReturn(2);
      $this->userStorage
        ->method('loadByProperties')
        ->with(['mail' => $userInfo['email']])
        ->willReturn([$mockExistingUser]);
    }

    // Test update user.
    if ($shouldUpdateUser) {
      $this->userStorage
        ->method('loadByProperties')
        ->with(['mail' => $userInfo['email']])
        ->willReturn([$mockUser]);
      $mockUser->expects($this->once())
        ->method('setEmail')
        ->with($userInfo['email']);
      $mockUser->expects($this->once())->method('save');
    }

    // Mock messenger behavior for errors.
    if ($expectedError) {
      $this->messenger
        ->expects($this->once())
        ->method('addError')
        ->with($this->callback(function ($markup) use ($expectedError) {
          return $markup->getUntranslatedString() === $expectedError;
        }));
    }
    else {
      // No errors raised.
      $this->messenger
        ->expects($this->never())
        ->method('addError');
    }

    // Test that setEmail() and save() are not called.
    if (!$isValidEmail || $hasExistingUser || !$shouldUpdateUser || $expectedError) {
      $mockUser->expects($this->never())
        ->method('setEmail')
        ->with($userInfo['email']);
      $mockUser->expects($this->never())->method('save');
    }

    $client = $this->createClient([]);
    $client->synchroniseEmail($mockUser, $userInfo);
  }

  /**
   * Data provider for testEmailSynchronization.
   */
  public static function emailSynchronizationProvider(): array {
    return [
      'valid_email' => [
        ['email' => self::USER_NEW_EMAIL],
        TRUE,
        FALSE,
        TRUE,
        NULL,
      ],
      'invalid_email' => [
        ['email' => self::INVALID_EMAIL],
        FALSE,
        FALSE,
        FALSE,
        self::ERROR_INVALID_EMAIL,
      ],
      'duplicate_email' => [
        ['email' => self::DUPLICATE_EMAIL],
        TRUE,
        TRUE,
        FALSE,
        self::ERROR_DUPLICATE_EMAIL,
      ],
      'empty_email' => [
        ['email' => NULL],
        FALSE,
        FALSE,
        FALSE,
        self::ERROR_INVALID_EMAIL,
      ],
    ];
  }

  /**
   * @covers ::isAdminEmail
   * @dataProvider adminEmailProvider
   */
  public function testIsAdminEmail(
    array $config,
    string $email,
    bool $isAdmin,
  ): void {
    $client = $this->createClient($config);
    $this->assertEquals($isAdmin, $client->isAdminEmail($email));
  }

  /**
   * Data provider for testIsAdminEmail.
   */
  public static function adminEmailProvider(): array {
    return [
      'valid_admin_email' => [
        [
          'admin_emails' => "admin@infomaniak.com\nwebmaster@infomaniak.com",
        ],
        'admin@infomaniak.com',
        TRUE,
      ],
      'valid_webmaster_email' => [
        [
          'admin_emails' => "admin@infomaniak.com\nwebmaster@valid.com",
        ],
        'webmaster@valid.com',
        TRUE,
      ],
      'not_valid_email' => [
        [
          'admin_emails' => "admin@infomaniak.com\nwebmaster@infomaniak.com",
        ],
        'admin@blocked.com',
        FALSE,
      ],
      'invalid_email' => [
        [
          'admin_emails' => "admin@infomaniak.com\nwebmaster@infomaniak.com",
        ],
        '',
        FALSE,
      ],
    ];
  }

}
