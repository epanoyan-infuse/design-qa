<?php

declare(strict_types=1);

namespace DesignQa\Cli\Command;

use DateTimeImmutable;
use DateTimeInterface;
use DesignQa\Application\Port\SourceException;
use DesignQa\Application\Read\ReadRequest;
use DesignQa\Cli\ServiceFactory;
use DesignQa\Output\CheckConsoleWriter;
use DesignQa\Output\CheckReportSerializer;
use DesignQa\Output\ConsoleText;
use DesignQa\Output\Html\HtmlReportWriter;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'check', description: 'Compare a live page with its Figma design on every screen size')]
final class CheckCommand extends Command
{
    public const REPORT_DIR = 'design-qa-reports';

    public function __construct(
        private readonly ServiceFactory $services,
        private readonly HtmlReportWriter $html,
        private readonly CheckConsoleWriter $console = new CheckConsoleWriter(),
        private readonly CheckReportSerializer $serializer = new CheckReportSerializer(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('page-url', InputArgument::REQUIRED, 'The live page, e.g. https://invisible-buyer.kesler.com/')
            ->addArgument('figma-url', InputArgument::REQUIRED, 'Figma link to the section or frame with the screens')
            ->addOption('screen', 's', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only this screen, by name or width (repeatable)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the result as JSON')
            ->addOption('refresh', null, InputOption::VALUE_NONE, 'Read the full design from Figma even if the file has not changed')
            ->addOption('report', null, InputOption::VALUE_REQUIRED, sprintf('Where to save the HTML report (default: %s/<site>-<date>.html)', self::REPORT_DIR))
            ->addOption('no-report', null, InputOption::VALUE_NONE, 'Do not save an HTML report')
            ->addOption('figma-response', null, InputOption::VALUE_REQUIRED, 'Development: read the design from a saved Figma response file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $pageUrl */
        $pageUrl = $input->getArgument('page-url');
        /** @var string $figmaUrl */
        $figmaUrl = $input->getArgument('figma-url');
        /** @var list<string> $screens */
        $screens = $input->getOption('screen');
        $savedResponse = $input->getOption('figma-response');
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        try {
            $check = $this->services->runCheck((bool) $input->getOption('refresh'), is_string($savedResponse) ? $savedResponse : null);
            $report = $check->execute(new ReadRequest($pageUrl, $figmaUrl, $screens));
        } catch (SourceException|InvalidArgumentException $e) {
            $errors->writeln(sprintf('<error>%s</error>', ConsoleText::forFormatted($e->getMessage())));

            return Command::FAILURE;
        }

        if ($input->getOption('json') === true) {
            $output->writeln(json_encode($this->serializer->serialize($report), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), OutputInterface::OUTPUT_RAW);
        } else {
            $this->console->write($report, $output);
        }

        if ($input->getOption('no-report') !== true) {
            $now = new DateTimeImmutable();
            $path = $input->getOption('report');
            $path = is_string($path) && $path !== '' ? $path : self::defaultReportPath($pageUrl, $now);
            try {
                $written = $this->html->write($report, $now, $path);
            } catch (RuntimeException|JsonException $e) {
                $errors->writeln(sprintf('<error>The HTML report could not be saved: %s</error>', ConsoleText::forFormatted($e->getMessage())));

                return Command::FAILURE;
            }
            $errors->writeln(sprintf('Report: %s', realpath($written) ?: $written), OutputInterface::OUTPUT_RAW);
        }

        return Command::SUCCESS;
    }

    public static function defaultReportPath(string $pageUrl, DateTimeInterface $now): string
    {
        $host = parse_url($pageUrl, PHP_URL_HOST);
        $site = is_string($host) && $host !== '' ? (string) preg_replace('/[^a-z0-9.-]+/i', '-', $host) : 'page';

        return sprintf('%s/%s-%s.html', self::REPORT_DIR, $site, $now->format('Y-m-d-His'));
    }
}
