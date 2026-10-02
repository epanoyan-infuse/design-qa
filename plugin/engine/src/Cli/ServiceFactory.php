<?php

declare(strict_types=1);

namespace DesignQa\Cli;

use DesignQa\Application\Check\RunCheck;
use DesignQa\Application\Read\ReadDesignAndPage;
use DesignQa\Application\RulesConfigLoader;
use DesignQa\Domain\Check\CheckRegistry;
use DesignQa\Domain\Check\PresenceRules;
use DesignQa\Domain\Check\RuleSet;
use DesignQa\Domain\Check\StyleComparator;
use DesignQa\Domain\Matching\PositionalTextMatcher;
use DesignQa\Domain\Model\ScreenSpec;
use DesignQa\Infrastructure\Cache\JsonFileCache;
use DesignQa\Infrastructure\Chrome\ChromeLocator;
use DesignQa\Infrastructure\Chrome\ChromePageSource;
use DesignQa\Infrastructure\Chrome\RawCaptureFile;
use DesignQa\Infrastructure\Chrome\Step\DisableAnimationsStep;
use DesignQa\Infrastructure\Chrome\Step\OpenMenuStep;
use DesignQa\Infrastructure\Chrome\Step\ScrollThroughStep;
use DesignQa\Infrastructure\Chrome\Step\WaitForFontsStep;
use DesignQa\Infrastructure\Figma\CachingFigmaClient;
use DesignQa\Infrastructure\Figma\FigmaClient;
use DesignQa\Infrastructure\Figma\FigmaDesignSource;
use DesignQa\Infrastructure\Figma\RecordingFigmaClient;
use DesignQa\Infrastructure\Figma\SavedResponseFigmaClient;
use InvalidArgumentException;
use RuntimeException;

/**
 * Builds the services a command needs, for options only known when the command runs
 * (--refresh, --save-raw). Part of the composition root.
 */
final readonly class ServiceFactory
{
    /** Cached reads are keyed by file version, so they only need to expire to free disk space. */
    public const FIGMA_CACHE_TTL_SECONDS = 30 * 86400;

    /**
     * @param array<string, mixed> $environment
     */
    public function __construct(
        private string $engineDir,
        private array $environment,
        private FigmaClient $figma,
        private ChromeLocator $chrome,
    ) {}

    /**
     * @param string|null $savedFigmaResponse read the design from this saved response instead of Figma (development)
     */
    public function readDesignAndPage(bool $refreshDesign = false, ?string $recordDir = null, ?string $savedFigmaResponse = null): ReadDesignAndPage
    {
        return new ReadDesignAndPage(
            new FigmaDesignSource($this->figmaClient($refreshDesign, $recordDir, $savedFigmaResponse)),
            $this->pageSource($recordDir),
        );
    }

    /**
     * @throws InvalidArgumentException when config/rules.php is invalid
     */
    public function runCheck(bool $refreshDesign = false, ?string $savedFigmaResponse = null): RunCheck
    {
        $rules = $this->rules();

        return new RunCheck(
            $this->readDesignAndPage($refreshDesign, null, $savedFigmaResponse),
            new PositionalTextMatcher(),
            new StyleComparator(CheckRegistry::standard()->configured($rules)),
            PresenceRules::fromRuleSet($rules),
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    private function rules(): RuleSet
    {
        return RulesConfigLoader::load($this->engineDir . '/config/rules.php');
    }

    private function figmaClient(bool $refresh, ?string $recordDir, ?string $savedFigmaResponse): FigmaClient
    {
        $client = $savedFigmaResponse !== null
            ? new SavedResponseFigmaClient($savedFigmaResponse)
            : new CachingFigmaClient($this->figma, new JsonFileCache($this->cacheDirectory() . '/figma', self::FIGMA_CACHE_TTL_SECONDS), $refresh);

        return $recordDir === null ? $client : new RecordingFigmaClient($client, $recordDir);
    }

    private function pageSource(?string $recordDir): ChromePageSource
    {
        $script = file_get_contents($this->engineDir . '/resources/js/collect-texts.js')
            ?: throw new RuntimeException('The page collector script is missing from the engine.');

        $recorder = $recordDir === null ? null : static function (ScreenSpec $spec, mixed $raw) use ($recordDir): void {
            if (is_dir($recordDir) || mkdir($recordDir, 0o755, true)) {
                file_put_contents(
                    $recordDir . '/' . RawCaptureFile::name($spec),
                    json_encode($raw, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                );
            }
        };

        return new ChromePageSource(
            $this->chrome,
            $script,
            [new DisableAnimationsStep(), new ScrollThroughStep(), new WaitForFontsStep(), new OpenMenuStep()],
            onRawCapture: $recorder,
        );
    }

    private function cacheDirectory(): string
    {
        $xdg = $this->environment['XDG_CACHE_HOME'] ?? null;
        if (is_string($xdg) && $xdg !== '') {
            return $xdg . '/design-qa';
        }
        $home = $this->environment['HOME'] ?? null;

        return (is_string($home) && $home !== '' ? $home . '/.cache' : sys_get_temp_dir()) . '/design-qa';
    }
}
