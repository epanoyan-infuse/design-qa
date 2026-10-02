<?php

declare(strict_types=1);

namespace DesignQa\Output\Html;

use Closure;
use DateTimeInterface;
use DesignQa\Application\Check\CheckReport;
use DesignQa\Output\CheckReportSerializer;
use DesignQa\Output\ConsoleText;
use RuntimeException;

/**
 * Writes the check result as one self-contained HTML file: no external files, fonts or requests,
 * so it opens offline and can be sent as an attachment. The report data is embedded as JSON for
 * future tools (e.g. the QA website) to reuse.
 */
final readonly class HtmlReportWriter
{
    public function __construct(
        private string $templateDir,
        private ReportViewBuilder $builder = new ReportViewBuilder(),
        private CheckReportSerializer $serializer = new CheckReportSerializer(),
    ) {}

    public function render(CheckReport $report, DateTimeInterface $checkedAt): string
    {
        $data = json_encode(
            $this->serializer->serialize($report),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE,
        );
        $view = $this->builder->build($report, $checkedAt, $data);

        /** @var Closure(string): string $e */
        $e = static fn(string $text): string => htmlspecialchars(ConsoleText::clean($text), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $css = $this->asset('report.css');
        $js = $this->asset('report.js');

        // The CSP allows exactly this script (by its hash), so injected markup could never run code.
        $scriptHash = base64_encode(hash('sha256', $js, true));

        ob_start();
        try {
            (static function (string $template, ReportView $view, string $css, string $js, string $scriptHash, Closure $e): void {
                require $template;
            })($this->templateDir . '/report.html.php', $view, $css, $js, $scriptHash, $e);

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Writes the report and returns the file path.
     */
    public function write(CheckReport $report, DateTimeInterface $checkedAt, string $path): string
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Could not create the report folder %s.', $dir));
        }
        if (file_put_contents($path, $this->render($report, $checkedAt)) === false) {
            throw new RuntimeException(sprintf('Could not write the report to %s.', $path));
        }

        return $path;
    }

    private function asset(string $name): string
    {
        $contents = file_get_contents($this->templateDir . '/' . $name);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Report asset %s is missing from the engine.', $name));
        }

        // Inlined in <style>/<script>: make sure an asset can never close its own tag.
        return str_ireplace(['</style', '</script'], ['<\/style', '<\/script'], $contents);
    }
}
