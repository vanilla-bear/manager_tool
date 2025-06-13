<?php

namespace App\Command;

use App\Service\SprintDiagnosticService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:analyze-sprint',
    description: 'Analyze a sprint metrics in detail',
)]
class AnalyzeSprintCommand extends Command
{
    public function __construct(
        private readonly SprintDiagnosticService $sprintDiagnosticService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('sprint-name', InputArgument::REQUIRED, 'The name of the sprint to analyze')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sprintName = $input->getArgument('sprint-name');

        try {
            $analysis = $this->sprintDiagnosticService->analyzeSprint($sprintName);

            $io->title("Analysis for sprint: " . $sprintName);
            
            // Display totals
            $io->section('Totals');
            $io->table(
                ['Metric', 'Points'],
                [
                    ['Completed', $analysis['totals']['completed']],
                    ['Initially Committed', $analysis['totals']['committed']],
                    ['Added During Sprint', $analysis['totals']['added']],
                    ['Total Scope', $analysis['totals']['committed'] + $analysis['totals']['added']],
                ]
            );

            // Display all issues
            $io->section('Issues Details');
            
            $issuesTable = [];
            foreach ($analysis['issues'] as $issue) {
                $issuesTable[] = [
                    $issue['key'],
                    $issue['points'],
                    $issue['status'],
                    $issue['isDone'] ? 'Yes' : 'No',
                    $issue['isAdded'] ? 'Added' : 'Initial',
                    $issue['created']
                ];
            }

            $io->table(
                ['Key', 'Points', 'Status', 'Done?', 'Scope', 'Created At'],
                $issuesTable
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
} 