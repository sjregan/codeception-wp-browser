<?php

namespace lucatume\WPBrowser\WordPress;

use lucatume\WPBrowser\Process\ProcessException;
use lucatume\WPBrowser\Process\WorkerException;
use lucatume\WPBrowser\Utils\Filesystem as FS;
use lucatume\WPBrowser\WordPress\InstallationState\EmptyDir;
use lucatume\WPBrowser\WordPress\InstallationState\InstallationChecks;
use lucatume\WPBrowser\WordPress\InstallationState\InstallationStateInterface;
use lucatume\WPBrowser\WordPress\Traits\WordPressChecks;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

class Installation
{
    use WordPressChecks;
    use InstallationChecks;

    /**
     * @var array<string>
     */
    private static array $scaffoldedInstallations = [];
    private ?Db $db;
    private string $wpRootDir;
    private InstallationState\InstallationStateInterface $installationState;

    /**
     * @throws InstallationException
     */
    public function __construct(
        string $wpRootDir,
        ?Db $db = null,
    ) {
        $this->wpRootDir = $this->checkWpRootDir($wpRootDir);
        $this->installationState = $this->setInstallationState();
        $this->db = $db;
    }

    /**
     * @throws InstallationException
     */
    public static function scaffold(string $wpRootDir, string $version = 'latest'): self
    {
        $emptyDir = new EmptyDir($wpRootDir);
        $emptyDir->scaffold($version);

        self::$scaffoldedInstallations[] = $wpRootDir;

        return new self($wpRootDir);
    }

    /**
     * @return array<string>
     */
    public static function getScaffoldedInstallations(): array
    {
        return self::$scaffoldedInstallations;
    }

    public static function forgetScaffoldedInstallation(string $dir): void
    {
        $key = array_search($dir, self::$scaffoldedInstallations, true);
        if ($key === false) {
            return;
        }
        unset(self::$scaffoldedInstallations[$key]);
        self::$scaffoldedInstallations = array_values(self::$scaffoldedInstallations);
    }

    public function configure(
        Db $db,
        ?int $multisite = InstallationStateInterface::SINGLE_SITE,
        ?ConfigurationData $configurationData = null
    ): self {
        $this->installationState = $this->installationState->configure($db, $multisite, $configurationData);

        return $this;
    }

    public function convertToMultisite(bool $subdomainInstall = false): self
    {
        $this->installationState = $this->installationState->convertToMultisite($subdomainInstall);

        return $this;
    }

    public function getAuthKey(): string
    {
        return $this->installationState->getAuthKey();
    }

    public function getSecureAuthKey(): string
    {
        return $this->installationState->getSecureAuthKey();
    }

    public function getLoggedInKey(): string
    {
        return $this->installationState->getLoggedInKey();
    }

    public function getNonceKey(): string
    {
        return $this->installationState->getNonceKey();
    }

    public function getAuthSalt(): string
    {
        return $this->installationState->getAuthSalt();
    }

    public function getSecureAuthSalt(): string
    {
        return $this->installationState->getSecureAuthSalt();
    }

    public function getLoggedInSalt(): string
    {
        return $this->installationState->getLoggedInSalt();
    }

    public function getNonceSalt(): string
    {
        return $this->installationState->getNonceSalt();
    }

    public function getRootDir(): string
    {
        return $this->installationState->getWpRootDir();
    }

    public function getVersion(): ?Version
    {
        return $this->installationState->getVersion();
    }

    public function isMultisite(): bool
    {
        return $this->installationState->isMultisite();
    }

    public function getDb(): ?Db
    {
        return $this->db ?? $this->installationState->getDb();
    }

    public function getWpRootDir(?string $path = null): string
    {
        return empty($path) ? $this->wpRootDir : $this->wpRootDir . FS::unleadslashit($path);
    }

    /**
     * @throws DbException
     * @throws InstallationException
     * @throws ProcessException
     * @throws WpConfigFileException
     * @throws Throwable
     * @throws WorkerException
     */
    private function setInstallationState(): InstallationState\InstallationStateInterface
    {
        if (!is_file($this->wpRootDir . '/wp-load.php')) {
            return new InstallationState\EmptyDir($this->wpRootDir);
        }

        $wpConfigFilePath = $this->findWpConfigFilePath($this->wpRootDir);

        if (!$wpConfigFilePath) {
            return new InstallationState\Scaffolded($this->wpRootDir);
        }

        $installationState = new InstallationState\Configured($this->wpRootDir, $wpConfigFilePath);
        $multisite = $installationState->isMultisite();

        if ($this->db === null || !$this->isInstalled($multisite)) {
            return $installationState;
        }

        return $multisite ?
            new InstallationState\Multisite($this->wpRootDir, $wpConfigFilePath)
            : new InstallationState\Single($this->wpRootDir, $wpConfigFilePath);
    }

    public function isEmpty(): bool
    {
        return $this->installationState instanceof InstallationState\EmptyDir;
    }

    public function install(
        string $url,
        string $adminUser,
        string $adminPassword,
        string $adminEmail,
        string $title
    ): self {
        $this->installationState = $this->installationState->install(
            $url,
            $adminUser,
            $adminPassword,
            $adminEmail,
            $title
        );

        return $this;
    }

    public function getState(): InstallationState\InstallationStateInterface
    {
        return $this->installationState;
    }

    public function isConfigured(): bool
    {
        return $this->installationState->isConfigured();
    }

    /**
     * @return array{AUTH_KEY: mixed, SECURE_AUTH_KEY: mixed, LOGGED_IN_KEY: mixed, NONCE_KEY: mixed, AUTH_SALT: mixed, SECURE_AUTH_SALT: mixed, LOGGED_IN_SALT: mixed, NONCE_SALT: mixed}
     */
    public function getSalts(): array
    {
        return $this->installationState->getSalts();
    }

    /**
     * @param ?array<string> $checkKeys
     * @return array<string, mixed>
     */
    public function report(array $checkKeys = null): array
    {
        $map = [
            'rootDir' => fn(): string => $this->installationState->getWpRootDir(),
            'version' => fn(): array => $this->installationState->getVersion()->toArray(),
            'constants' => fn(): array => $this->installationState->getConstants(),
            'globals' => fn(): array => $this->installationState->getGlobals()
        ];

        $checksMap = $checkKeys === null ? $map : array_intersect_key($map, array_flip($checkKeys));

        foreach ($checksMap as &$value) {
            $value = $value();
        }

        return $checksMap;
    }

    public function getPluginsDir(string $path = ''): string
    {
        return $this->installationState->getPluginsDir($path);
    }

    public function updateOption(string $option, mixed $value): int
    {
        return $this->installationState->updateOption($option, $value);
    }

    public function getThemesDir(string $path = ''): string
    {
        return $this->installationState->getThemesDir($path);
    }

    public function getWpConfigFilePath(): string
    {
        return $this->installationState->getWpConfigPath();
    }

    public function getContentDir(string $path = ''): string
    {
        return $this->installationState->getContentDir($path);
    }

    /**
     * @param string[] $command
     * @throws ProcessFailedException
     */
    public function runWpCliCommandOrThrow(array $command): Process
    {
        return (new CliProcess($command, $this->getRootDir()))->mustRun();
    }
}
