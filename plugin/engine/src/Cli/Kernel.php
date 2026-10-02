<?php

declare(strict_types=1);

namespace DesignQa\Cli;

use DesignQa\Application\Doctor\Check\ChromeCheck;
use DesignQa\Application\Doctor\Check\FigmaKeyCheck;
use DesignQa\Application\Doctor\Check\PhpExtensionsCheck;
use DesignQa\Application\Doctor\Check\PhpVersionCheck;
use DesignQa\Application\Doctor\Check\RulesConfigCheck;
use DesignQa\Application\Doctor\Doctor;
use DesignQa\Cli\Command\CheckCommand;
use DesignQa\Cli\Command\DoctorCommand;
use DesignQa\Cli\Command\LoginCommand;
use DesignQa\Cli\Command\ReadCommand;
use DesignQa\Infrastructure\Chrome\ChromeLocator;
use DesignQa\Infrastructure\Figma\HttpFigmaClient;
use DesignQa\Infrastructure\Secrets\ChainTokenStore;
use DesignQa\Infrastructure\Secrets\EnvTokenStore;
use DesignQa\Infrastructure\Secrets\KeychainTokenStore;
use DesignQa\Infrastructure\Secrets\MemoizingTokenStore;
use DesignQa\Infrastructure\System\ProcessCommandRunner;
use DesignQa\Output\Html\HtmlReportWriter;
use Symfony\Component\Console\Application;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Composition root: the only place where concrete classes are created and wired together.
 */
final readonly class Kernel
{
    public const NAME = 'design-qa';
    public const VERSION = '0.1.0';

    /**
     * @param array<string, mixed> $environment usually getenv()
     */
    public function __construct(
        private string $engineDir,
        private array $environment,
    ) {}

    public function application(): Application
    {
        $runner = new ProcessCommandRunner();
        $keychain = new KeychainTokenStore($runner);
        // The environment wins so CI and non-macOS machines work without a keychain.
        $tokens = new MemoizingTokenStore(new ChainTokenStore(new EnvTokenStore($this->environment), $keychain));
        $figma = new HttpFigmaClient(HttpClient::create(['timeout' => 30]), $tokens);
        $chrome = new ChromeLocator($this->environment);

        $doctor = new Doctor(
            new PhpVersionCheck(),
            new PhpExtensionsCheck(),
            new ChromeCheck($chrome),
            new FigmaKeyCheck($tokens, $figma, $keychain->saveCommandHint()),
            new RulesConfigCheck($this->rulesFile()),
        );

        $app = new Application(self::NAME, self::VERSION);
        $app->addCommand(new DoctorCommand($doctor));
        $app->addCommand(new LoginCommand($keychain));
        $services = new ServiceFactory($this->engineDir, $this->environment, $figma, $chrome);
        $app->addCommand(new CheckCommand($services, new HtmlReportWriter($this->engineDir . '/resources/report')));
        $app->addCommand(new ReadCommand($services));

        return $app;
    }

    private function rulesFile(): string
    {
        return $this->engineDir . '/config/rules.php';
    }
}
