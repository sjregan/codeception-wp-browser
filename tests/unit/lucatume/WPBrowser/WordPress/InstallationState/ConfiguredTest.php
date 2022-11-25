<?php


namespace lucatume\WPBrowser\WordPress\InstallationState;

use lucatume\WPBrowser\Process\Loop;
use lucatume\WPBrowser\Process\Worker\Result;
use lucatume\WPBrowser\Tests\Traits\UopzFunctions;
use lucatume\WPBrowser\Utils\Env;
use lucatume\WPBrowser\Utils\Filesystem as FS;
use lucatume\WPBrowser\Utils\Random;
use lucatume\WPBrowser\WordPress\ConfigurationData;
use lucatume\WPBrowser\WordPress\Db;
use lucatume\WPBrowser\WordPress\Installation;
use lucatume\WPBrowser\WordPress\InstallationException;

class ConfiguredTest extends \Codeception\Test\Unit
{
    use UopzFunctions;

    /**
     * It should throw when building on non existing root directory
     *
     * @test
     */
    public function should_throw_when_building_on_non_existing_root_directory(): void
    {
        $this->expectException(InstallationException::class);
        $this->expectExceptionCode(InstallationException::ROOT_DIR_NOT_FOUND);

        new Configured('/non-existing-dir', '/non-existing-dir/wp-config.php');
    }

    /**
     * It should throw when building on empty root directory
     *
     * @test
     */
    public function should_throw_when_building_on_empty_root_directory(): void
    {
        $wpRootDir = Fs::tmpDir('configured_',);

        $this->expectException(InstallationException::class);
        $this->expectExceptionCode(InstallationException::STATE_EMPTY);

        new Configured($wpRootDir, $wpRootDir . '/wp-config.php');
    }

    /**
     * It should throw when building on scaffolded root directory
     *
     * @test
     */
    public function should_throw_when_building_on_scaffolded_root_directory(): void
    {
        $wpRootDir = Fs::tmpDir('configured_', [
            'wp-load.php' => '<?php echo "Hello there!";',
            'wp-settings.php' => '<?php echo "Hello there!";',
            'wp-config-sample.php' => '<?php echo "Hello there!";',
        ]);

        $this->expectException(InstallationException::class);
        $this->expectExceptionCode(InstallationException::WP_CONFIG_FILE_NOT_FOUND);

        new Configured($wpRootDir, $wpRootDir . '/wp-config.php');
    }

    /**
     * It should throw if wp-config.php file path does not point to wp-config.php file
     *
     * @test
     */
    public function should_throw_if_wp_config_php_file_path_does_not_point_to_wp_config_php_file(): void
    {

        $wpRootDir = Fs::tmpDir('configured_', [
            'wp-load.php' => '<?php echo "Hello there!";',
            'wp-settings.php' => '<?php echo "Hello there!";',
            'wp-config-sample.php' => '<?php echo "Hello there!";',
        ]);

        $this->expectException(InstallationException::class);
        $this->expectExceptionCode(InstallationException::WP_CONFIG_FILE_NOT_FOUND);

        new Configured($wpRootDir, dirname($wpRootDir) . '/wp-config.php');
    }

    /**
     * It should allow assessing multisite status from files
     *
     * @test
     */
    public function should_allow_assessing_multisite_status_from_files(): void
    {

        $singleRootDir = FS::tmpDir('configured_');
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost);
        Installation::scaffold($singleRootDir)->configure($db);

        $configured = new Configured($singleRootDir, $singleRootDir . '/wp-config.php');

        $this->assertFalse($configured->isMultisite());

        $multisiteRootDir = Fs::tmpDir('configured_');
        Installation::scaffold($multisiteRootDir)->configure($db, InstallationStateInterface::MULTISITE_SUBDOMAIN);

        $configured = new Configured($multisiteRootDir, $multisiteRootDir . '/wp-config.php');

        $this->assertTrue($configured->isMultisite());
    }

    /**
     * It should throw when building on root directory missing wp-load.php file
     *
     * @test
     */
    public function should_throw_when_building_on_root_directory_missing_wp_load_php_file(): void
    {
        $wpRootDir = Fs::tmpDir('configured_', [
            'wp-settings.php' => '<?php echo "Hello there!";',
            'wp-config.php' => '<?php echo "Hello there!";',
        ]);
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost);

        $this->expectException(InstallationException::class);
        $this->expectExceptionCode(InstallationException::STATE_EMPTY);

        new Configured($wpRootDir, dirname($wpRootDir) . '/wp-config.php');
    }

    /**
     * It should throw if trying to configure already configured installation
     *
     * @test
     */
    public function should_throw_if_trying_to_configure_already_configured_installation(): void
    {
        $wpRootDir = Fs::tmpDir('configured_');
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost);
        Installation::scaffold($wpRootDir)->configure($db);

        $configured = new Configured($wpRootDir, $wpRootDir . '/wp-config.php');

        $this->expectException(InstallationException::class);
        $this->expectExceptionCode(InstallationException::STATE_CONFIGURED);

        $configured->configure($db);
    }

    /**
     * It should allow reading variables and constants defined in the wp-config.php file
     *
     * @test
     */
    public function should_allow_reading_variables_and_constants_defined_in_the_wp_config_php_file(): void
    {
        $wpRootDir = Fs::tmpDir('configured_');
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost, 'test_');
        $configurationData = ConfigurationData::fromArray([
            'authKey' => 'auth-key-salt',
            'secureAuthKey' => 'secure-auth-key-salt',
            'loggedInKey' => 'logged-in-key-salt',
            'nonceKey' => 'nonce-key-salt',
            'authSalt' => 'auth-salt-salt',
            'secureAuthSalt' => 'secure-auth-salt',
            'loggedInSalt' => 'logged-in-salt',
            'nonceSalt' => 'nonce-salt-salt'
        ]);
        Installation::scaffold($wpRootDir)->configure($db, InstallationStateInterface::SINGLE_SITE, $configurationData);

        $configured = new Configured($wpRootDir, $wpRootDir . '/wp-config.php');

        $this->assertEquals('auth-key-salt', $configured->getAuthKey());
        $this->assertEquals('secure-auth-key-salt', $configured->getSecureAuthKey());
        $this->assertEquals('logged-in-key-salt', $configured->getLoggedInKey());
        $this->assertEquals('nonce-key-salt', $configured->getNonceKey());
        $this->assertEquals('auth-salt-salt', $configured->getAuthSalt());
        $this->assertEquals('secure-auth-salt', $configured->getSecureAuthSalt());
        $this->assertEquals('logged-in-salt', $configured->getLoggedInSalt());
        $this->assertEquals('nonce-salt-salt', $configured->getNonceSalt());
        $this->assertEquals('test_', $configured->getTablePrefix());
    }

    /**
     * It should throw when installation parameters are invalid
     *
     * @test
     */
    public function should_throw_when_installation_parameters_are_invalid(): void
    {
        $wpRootDir = Fs::tmpDir('configured_');
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost, 'test_');
        Installation::scaffold($wpRootDir)->configure($db);

        $configured = new Configured($wpRootDir, $wpRootDir . '/wp-config.php');
        $defaultInstallationParameters = [
            'url' => 'https://wp.local',
            'adminUser' => 'admin',
            'adminPassword' => 'password',
            'adminEmail' => 'admin@wp.local',
            'title' => 'WP Local Installation'
        ];

        $badInputs = [
            'empty URL' => [['url' => ''], InstallationException::INVALID_URL],
            'bad URL' => [['url' => 'foo.bar:2389'], InstallationException::INVALID_URL],
            'not a URL, just a string' => [['url' => 'lorem dolor'], InstallationException::INVALID_URL],
            'empty admin username' => [['adminUser' => ''], InstallationException::INVALID_ADMIN_USERNAME],
            'admin username with quotes' => [
                ['adminUser' => '"theAdmin"'],
                InstallationException::INVALID_ADMIN_USERNAME
            ],
            'admin username with spaces' => [
                ['adminUser' => 'the admin'],
                InstallationException::INVALID_ADMIN_USERNAME
            ],
            'empty admin password' => [['adminPassword' => ''], InstallationException::INVALID_ADMIN_PASSWORD],
            'empty admin email' => [['adminEmail' => ''], InstallationException::INVALID_ADMIN_EMAIL],
            'not an email' => [['adminEmail' => 'not_an_email'], InstallationException::INVALID_ADMIN_EMAIL],
            'missing email domain' => [['adminEmail' => 'luca@'], InstallationException::INVALID_ADMIN_EMAIL],
            'missing email name' => [
                ['adminEmail' => '@theAverageDev.com'],
                InstallationException::INVALID_ADMIN_EMAIL
            ],
            'empty title' => [['title' => ''], InstallationException::INVALID_TITLE],
        ];

        foreach ($badInputs as [$badInput, $expectedExceptionCode]) {
            $installationParameters = array_values(array_replace($defaultInstallationParameters, $badInput));

            $this->expectException(InstallationException::class);
            $this->expectExceptionCode($expectedExceptionCode);

            $configured->install(...$installationParameters);
        }
    }

    /**
     * It should allow installing single site installation
     *
     * @test
     */
    public function should_allow_installing_single_site_installation(): void
    {
        $wpRootDir = Fs::tmpDir('configured_');
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost, 'test_');
        Installation::scaffold($wpRootDir)->configure($db);

        $configured = new Configured($wpRootDir, $wpRootDir . '/wp-config.php');
        $installed = $configured->install('https://wp.local',
            'admin',
            'password',
            'admin@wp.local',
            'WP Local Installation');

        $this->assertInstanceOf(Single::class, $installed);
    }

    /**
     * It should throw if installation request fails with output
     *
     * @test
     */
    public function should_throw_if_installation_request_fails_with_output(): void
    {
        $wpRootDir = Fs::tmpDir('configured_');
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost, 'test_');
        Installation::scaffold($wpRootDir)->configure($db);

        $configured = new Configured($wpRootDir, $wpRootDir . '/wp-config.php');

        $mockResult = new Result('installation', 1, 'lorem dolor', 'errors occurred', null);
        $this->uopzSetStaticMethodReturn(Loop::class, 'executeClosure', $mockResult);

        $this->expectException(InstallationException::class);
        $this->expectExceptionCode(InstallationException::INSTALLATION_FAIL);
        $this->expectExceptionMessageMatches('/errors occurred/');

        $configured->install('https://wp.local',
            'admin',
            'password',
            'admin@wp.local',
            'WP Local Installation');

        $mockResult = new Result('installation', 1, 'lorem dolor', '', null);
        $this->uopzSetStaticMethodReturn(Loop::class, 'executeClosure', $mockResult);

        $this->expectException(InstallationException::class);
        $this->expectExceptionCode(InstallationException::INSTALLATION_FAIL);
        $this->expectExceptionMessageMatches('/lorem dolor/');

        $configured->install('https://wp.local',
            'admin',
            'password',
            'admin@wp.local',
            'WP Local Installation');

        $mockResult = new Result('installation', 1, '', '', null);
        $this->uopzSetStaticMethodReturn(Loop::class, 'executeClosure', $mockResult);

        $this->expectException(InstallationException::class);
        $this->expectExceptionCode(InstallationException::INSTALLATION_FAIL);
        $this->expectExceptionMessageMatches('/unknown reason/');

        $configured->install('https://wp.local',
            'admin',
            'password',
            'admin@wp.local',
            'WP Local Installation');
    }

    /**
     * It should throw if installation request fails with throwable
     *
     * @test
     */
    public function should_throw_if_installation_request_fails_with_throwable(): void
    {
        $wpRootDir = Fs::tmpDir('configured_');
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost, 'test_');
        Installation::scaffold($wpRootDir)->configure($db);


        $configured = new Configured($wpRootDir, $wpRootDir . '/wp-config.php');

        $mockResult = new Result('installation',
            1,
            'lorem dolor',
            'errors occurred',
            new \Exception('Something is amiss'));
        $this->uopzSetStaticMethodReturn(Loop::class, 'executeClosure', $mockResult);

        $this->expectException(InstallationException::class);
        $this->expectExceptionCode(InstallationException::INSTALLATION_FAIL);
        $this->expectExceptionMessageMatches('/Something is amiss/');

        $configured->install('https://wp.local',
            'admin',
            'password',
            'admin@wp.local',
            'WP Local Installation');
    }

    /**
     * It should throw if trying to convert to multisite
     *
     * @test
     */
    public function should_throw_if_trying_to_convert_to_multisite(): void
    {
        $wpRootDir = FS::tmpDir('configured_');
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost, 'test_');
        Installation::scaffold($wpRootDir)->configure($db);

        $configured = new Configured($wpRootDir, $wpRootDir . '/wp-config.php');

        $this->expectException(InstallationException::class);
        $this->expectExceptionCode(InstallationException::STATE_CONFIGURED);

        $configured->convertToMultisite();
    }

    /**
     * It should throw if trying to scaffold
     *
     * @test
     */
    public function should_throw_if_trying_to_scaffold(): void
    {
        $wpRootDir = FS::tmpDir('configured_');
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost, 'test_');
        Installation::scaffold($wpRootDir)->configure($db);

        $configured = new Configured($wpRootDir, $wpRootDir . '/wp-config.php');

        $this->expectException(InstallationException::class);
        $this->expectExceptionCode(InstallationException::STATE_CONFIGURED);

        $configured->scaffold();
    }

    /**
     * It should allow getting information about the installation
     *
     * @test
     */
    public function should_allow_getting_information_about_the_installation(): void
    {
        $wpRootDir = FS::tmpDir('configured_');
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost, 'test_');
        Installation::scaffold($wpRootDir)->configure($db);

        $configured = new Configured($wpRootDir, $wpRootDir . '/wp-config.php');

        $this->assertEquals($wpRootDir . '/', $configured->getWpRootDir());
        $this->assertEquals($wpRootDir . '/wp-config.php', $configured->getWpConfigPath());
        $this->assertTrue(strlen($configured->getAuthKey()) === 64 && $configured->getAuthKey() !== $configured->getSecureAuthKey());
        $this->assertTrue(strlen($configured->getSecureAuthKey()) === 64 && $configured->getSecureAuthKey() !== $configured->getLoggedInKey());
        $this->assertTrue(strlen($configured->getLoggedInKey()) === 64 && $configured->getLoggedInKey() !== $configured->getNonceKey());
        $this->assertTrue(strlen($configured->getNonceKey()) === 64 && $configured->getNonceKey() !== $configured->getAuthSalt());
        $this->assertTrue(strlen($configured->getAuthSalt()) === 64 && $configured->getAuthSalt() !== $configured->getSecureAuthSalt());
        $this->assertTrue(strlen($configured->getSecureAuthSalt()) === 64 && $configured->getSecureAuthSalt() !== $configured->getLoggedInSalt());
        $this->assertTrue(strlen($configured->getLoggedInSalt()) === 64 && $configured->getLoggedInSalt() !== $configured->getNonceSalt());
        $this->assertSame(64, strlen($configured->getNonceSalt()));
        $this->assertEquals('test_', $configured->getTablePrefix());
        $this->assertTrue($configured->isConfigured());
        $this->assertEquals([
            'authKey' => $configured->getAuthKey(),
            'secureAuthKey' => $configured->getSecureAuthKey(),
            'loggedInKey' => $configured->getLoggedInKey(),
            'nonceKey' => $configured->getNonceKey(),
            'authSalt' => $configured->getAuthSalt(),
            'secureAuthSalt' => $configured->getSecureAuthSalt(),
            'loggedInSalt' => $configured->getLoggedInSalt(),
            'nonceSalt' => $configured->getNonceSalt(),
        ], $configured->getSalts());
        $this->assertEquals($dbName, $configured->getConstant('DB_NAME'));
        $this->assertEquals($dbHost, $configured->getConstant('DB_HOST'));
        $this->assertEquals($dbUser, $configured->getConstant('DB_USER'));
        $this->assertEquals($dbPassword, $configured->getConstant('DB_PASSWORD'));
    }

    /**
     * It should allow getting the db
     *
     * @test
     */
    public function should_allow_getting_the_db(): void
    {
        $wpRootDir = FS::tmpDir('configured_');
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost, 'test_');
        Installation::scaffold($wpRootDir)->configure($db);

        $configured = new Configured($wpRootDir, $wpRootDir . '/wp-config.php');

        $this->assertEquals($dbName, $configured->getDb()->getDbName());
        $this->assertEquals($dbHost, $configured->getDb()->getDbHost());
        $this->assertEquals($dbUser, $configured->getDb()->getDbUser());
        $this->assertEquals($dbPassword, $configured->getDb()->getDbPassword());
    }
}
