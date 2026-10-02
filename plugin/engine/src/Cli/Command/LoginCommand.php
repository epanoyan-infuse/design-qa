<?php

declare(strict_types=1);

namespace DesignQa\Cli\Command;

use DesignQa\Infrastructure\Secrets\KeychainTokenStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'login', description: 'Save the read-only Figma key in the macOS keychain')]
final class LoginCommand extends Command
{
    public function __construct(private readonly KeychainTokenStore $keychain)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->isInteractive()) {
            $output->writeln(sprintf('Run this in your terminal instead: %s', $this->keychain->saveCommandHint()));

            return Command::INVALID;
        }

        $output->writeln('Paste the read-only Figma key when asked (the text stays hidden), then confirm it.');
        if (!$this->keychain->promptAndSave()) {
            $output->writeln('The key was not saved.');

            return Command::FAILURE;
        }

        $output->writeln('Saved. Run "design-qa doctor" to check that Figma accepts it.');

        return Command::SUCCESS;
    }
}
