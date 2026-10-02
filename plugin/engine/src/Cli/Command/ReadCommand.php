<?php

declare(strict_types=1);

namespace DesignQa\Cli\Command;

use DesignQa\Application\Port\SourceException;
use DesignQa\Application\Read\ReadRequest;
use DesignQa\Cli\ServiceFactory;
use DesignQa\Output\ConsoleText;
use DesignQa\Output\ReadConsoleWriter;
use DesignQa\Output\ReadResultSerializer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'read', description: 'Show the texts and styles read from the Figma design and the page, per screen (no comparison)')]
final class ReadCommand extends Command
{
    public function __construct(
        private readonly ServiceFactory $services,
        private readonly ReadConsoleWriter $console = new ReadConsoleWriter(),
        private readonly ReadResultSerializer $serializer = new ReadResultSerializer(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('page-url', InputArgument::REQUIRED, 'The live page, e.g. https://invisible-buyer.kesler.com/')
            ->addArgument('figma-url', InputArgument::REQUIRED, 'Figma link to the section or frame with the screens')
            ->addOption('screen', 's', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only this screen, by name or width (repeatable)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print JSON instead of tables')
            ->addOption('refresh', null, InputOption::VALUE_NONE, 'Read the full design from Figma even if the file has not changed')
            ->addOption('figma-response', null, InputOption::VALUE_REQUIRED, 'Development: read the design from a saved Figma response file')
            ->addOption('save-raw', null, InputOption::VALUE_REQUIRED, 'Save raw Figma and page data to this folder (test fixtures)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $pageUrl */
        $pageUrl = $input->getArgument('page-url');
        /** @var string $figmaUrl */
        $figmaUrl = $input->getArgument('figma-url');
        /** @var list<string> $screens */
        $screens = $input->getOption('screen');
        $saveRaw = $input->getOption('save-raw');
        $savedResponse = $input->getOption('figma-response');

        $useCase = $this->services->readDesignAndPage(
            (bool) $input->getOption('refresh'),
            is_string($saveRaw) ? $saveRaw : null,
            is_string($savedResponse) ? $savedResponse : null,
        );

        try {
            $result = $useCase->execute(new ReadRequest($pageUrl, $figmaUrl, $screens));
        } catch (SourceException $e) {
            $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errors->writeln(sprintf('<error>%s</error>', ConsoleText::forFormatted($e->getMessage())));

            return Command::FAILURE;
        }

        if ($input->getOption('json') === true) {
            $output->writeln(json_encode($this->serializer->serialize($result), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);
        } else {
            $this->console->write($result, $output);
        }

        return Command::SUCCESS;
    }
}
