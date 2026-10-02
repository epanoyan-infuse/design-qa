<?php

declare(strict_types=1);

namespace DesignQa\Cli\Command;

use DesignQa\Application\Doctor\Doctor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'doctor', description: 'Check that PHP, Chrome and the Figma key are ready')]
final class DoctorCommand extends Command
{
    public function __construct(private readonly Doctor $doctor)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $allPassed = true;
        foreach ($this->doctor->examine() as ['name' => $name, 'result' => $result]) {
            $output->writeln(sprintf('%s %s: %s', $result->passed ? '✅' : '❌', $name, $result->detail), OutputInterface::OUTPUT_RAW);
            if ($result->fix !== null) {
                $output->writeln(sprintf('   Fix: %s', $result->fix), OutputInterface::OUTPUT_RAW);
            }
            $allPassed = $allPassed && $result->passed;
        }

        $output->writeln('');
        $output->writeln($allPassed ? 'design-qa is ready.' : 'design-qa is not ready yet. Apply the fixes above, then run doctor again.');

        return $allPassed ? Command::SUCCESS : Command::FAILURE;
    }
}
