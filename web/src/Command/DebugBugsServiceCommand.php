<?php

namespace App\Command;

use App\Service\BugsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-bugs-service',
    description: 'Debug BugsService to check JQL queries and API responses',
)]
class DebugBugsServiceCommand extends Command
{
    public function __construct(
        private readonly BugsService $bugsService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('start', null, InputOption::VALUE_REQUIRED, 'Start date (Y-m-d format)', '2025-07-01')
            ->addOption('end', null, InputOption::VALUE_REQUIRED, 'End date (Y-m-d format)', '2025-09-30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $startDate = new \DateTime($input->getOption('start'));
        $endDate = new \DateTime($input->getOption('end'));
        
        $io->title('Debug BugsService');
        $io->section('Parameters');
        $io->table(['Parameter', 'Value'], [
            ['Start Date', $startDate->format('Y-m-d')],
            ['End Date', $endDate->format('Y-m-d')],
        ]);
        
        try {
            $io->section('Testing BugsService...');
            $data = $this->bugsService->getBugsData($startDate, $endDate);
            
            $io->section('Results');
            $io->table(['Metric', 'Value'], [
                ['Total Bugs', $data['total_bugs']],
                ['Bug Rate', $data['bug_rate'] . '%'],
                ['Monthly Data Count', count($data['monthly_data'])],
                ['Priority Distribution', json_encode($data['priority_distribution'])],
            ]);
            
            if (!empty($data['monthly_data'])) {
                $io->section('Monthly Data');
                $tableData = [];
                foreach ($data['monthly_data'] as $month) {
                    $tableData[] = [
                        $month['month'] ?? 'Unknown',
                        $month['count'] ?? 0,
                        count($month['bugs'] ?? [])
                    ];
                }
                $io->table(['Month', 'Count', 'Bugs Array Count'], $tableData);
            }
            
            $io->success('BugsService test completed successfully!');
            
        } catch (\Exception $e) {
            $io->error('Error testing BugsService: ' . $e->getMessage());
            $io->text('Stack trace:');
            $io->text($e->getTraceAsString());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}
