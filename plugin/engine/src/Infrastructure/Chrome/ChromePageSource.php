<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Chrome;

use Closure;
use DesignQa\Application\Port\PageSource;
use DesignQa\Application\Port\SourceException;
use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\ScreenSpec;
use DesignQa\Infrastructure\Chrome\Step\AccordionNotOpened;
use DesignQa\Infrastructure\Chrome\Step\MenuNotOpened;
use DesignQa\Infrastructure\Chrome\Step\PageStep;
use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Page;
use Throwable;

/**
 * Renders the page in headless Chrome once per screen width and reads its texts.
 *
 * Chrome runs headless with a fresh temporary profile (no cookies or logins of the developer),
 * which chrome-php deletes afterwards. Only web addresses are opened, never local files.
 */
final readonly class ChromePageSource implements PageSource
{
    public const WEB_SCHEMES = ['http', 'https'];

    /** @var list<PageStep> */
    private array $steps;

    /**
     * @param list<PageStep>                          $steps          run after load, in order
     * @param (Closure(ScreenSpec, mixed): void)|null $onRawCapture   receives the raw collector output (fixture recording)
     * @param list<string>                            $allowedSchemes tests add "file" to open local fixture pages
     */
    public function __construct(
        private ChromeLocator $locator,
        private string $collectorScript,
        array $steps,
        private PageSnapshotMapper $mapper = new PageSnapshotMapper(),
        private int $navigationTimeoutMs = 45_000,
        private ?Closure $onRawCapture = null,
        private array $allowedSchemes = self::WEB_SCHEMES,
    ) {
        $this->steps = $steps;
    }

    public function capture(string $pageUrl, array $specs): array
    {
        $scheme = strtolower((string) parse_url($pageUrl, PHP_URL_SCHEME));
        if (!in_array($scheme, $this->allowedSchemes, true)) {
            throw new SourceException(sprintf('Not a web page address (http or https): "%s".', $pageUrl));
        }

        $chrome = $this->locator->locate()
            ?? throw new SourceException('Google Chrome was not found. Run "design-qa doctor" for help.');

        try {
            $browser = (new BrowserFactory($chrome))->createBrowser([
                'headless' => true,
                'windowSize' => [$specs[0]->width, DeviceProfile::DEFAULT_VIEWPORT_HEIGHT],
                'customFlags' => ['--hide-scrollbars', '--mute-audio', '--disable-extensions'],
                'sendSyncDefaultTimeout' => 30_000,
            ]);
        } catch (Throwable $e) {
            throw new SourceException(sprintf('Could not start Chrome: %s', $e->getMessage()), 0, $e);
        }

        try {
            return array_map(fn(ScreenSpec $spec): Screen => $this->captureOne($browser, $pageUrl, $spec), $specs);
        } finally {
            try {
                $browser->close();
            } catch (Throwable) {
                // a failed close must not hide the result or the error that made us stop
            }
        }
    }

    private function captureOne(Browser $browser, string $pageUrl, ScreenSpec $spec): Screen
    {
        try {
            $page = $browser->createPage();
            try {
                $this->emulate($page, DeviceProfile::for($spec));
                $page->navigate($pageUrl)->waitForNavigation(Page::LOAD, $this->navigationTimeoutMs);
                try {
                    foreach ($this->steps as $step) {
                        $step->prepare($page, $spec);
                    }
                } catch (MenuNotOpened $e) {
                    return new Screen($spec, 0.0, [], [], sprintf('the menu could not be opened: %s', $e->getMessage()));
                } catch (AccordionNotOpened $e) {
                    return new Screen($spec, 0.0, [], [], sprintf('the open item could not be opened: %s', $e->getMessage()));
                }
                // Menu screens: only the first screenful counts, and texts under the open menu are covered.
                $options = json_encode(['viewportOnly' => $spec->viewportHeight !== null], JSON_THROW_ON_ERROR);
                $raw = $page->evaluate(sprintf('(%s)(%s)', trim($this->collectorScript), $options))->getReturnValue($this->navigationTimeoutMs);
            } finally {
                try {
                    $page->close();
                } catch (Throwable) {
                    // a failed close must not hide the error that made us stop
                }
            }
        } catch (SourceException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new SourceException(sprintf('Could not read the page at %dpx (%s): %s', $spec->width, $spec->name, $e->getMessage()), 0, $e);
        }

        if ($this->onRawCapture !== null) {
            ($this->onRawCapture)($spec, $raw);
        }

        return $this->mapper->map($raw, $spec);
    }

    private function emulate(Page $page, DeviceProfile $device): void
    {
        $page->setDeviceMetricsOverride([
            'width' => $device->width,
            'height' => $device->height,
            'deviceScaleFactor' => 1,
            'mobile' => $device->mobile,
        ])->await();

        $session = $page->getSession();
        $session->sendMessageSync(new Message('Emulation.setTouchEmulationEnabled', ['enabled' => $device->mobile]));
        if ($device->userAgent !== null) {
            $session->sendMessageSync(new Message('Network.setUserAgentOverride', ['userAgent' => $device->userAgent]));
        }
    }
}
