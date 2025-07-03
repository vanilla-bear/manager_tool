<?php

namespace App\Command;

use App\Service\BlockersService;
use App\Service\VelocityService;
use App\Service\DeploymentService;
use App\Service\BugsService;
use App\Service\MTTRService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use DateTime;

#[AsCommand(
    name: 'app:export-kpis',
    description: 'Export all KPIs data to JSON file'
)]
class ExportKPIsCommand extends Command
{
    public function __construct(
        private readonly BlockersService $blockersService,
        private readonly VelocityService $velocityService,
        private readonly DeploymentService $deploymentService,
        private readonly BugsService $bugsService,
        private readonly MTTRService $mttrService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'start-date',
                null,
                InputOption::VALUE_REQUIRED,
                'Start date (Y-m-d)',
                (new DateTime('first day of -6 months'))->format('Y-m-d')
            )
            ->addOption(
                'end-date',
                null,
                InputOption::VALUE_REQUIRED,
                'End date (Y-m-d)',
                (new DateTime())->format('Y-m-d')
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file path',
                'kpis_export.json'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $startDate = new DateTime($input->getOption('start-date'));
        $endDate = new DateTime($input->getOption('end-date'));
        $outputFile = $input->getOption('output');

        $io->title('Exporting KPIs data');
        $io->text(sprintf('Period: %s to %s', $startDate->format('Y-m-d'), $endDate->format('Y-m-d')));

        // Export Blockers data
        $io->section('Exporting Blockers data...');
        $blockersData = $this->blockersService->getBlockersData($startDate, $endDate);
        $currentSprintBlockers = $this->blockersService->getCurrentSprintBlockers();

        // Export Velocity data
        $io->section('Exporting Velocity data...');
        $velocityData = $this->velocityService->getVelocityData($startDate, $endDate);

        // Export Deployment data
        $io->section('Exporting Deployment data...');
        $deploymentData = $this->deploymentService->getDeploymentData($startDate, $endDate);

        // Export Bugs data
        $io->section('Exporting Bugs data...');
        $bugsData = $this->bugsService->getBugsData($startDate, $endDate);

        // Export MTTR data
        $io->section('Exporting MTTR data...');
        $mttrData = $this->mttrService->getMTTRData($startDate, $endDate);

        // Structure the export data
        $exportData = [
            'export_date' => (new DateTime())->format('Y-m-d H:i:s'),
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'kpis' => [
                'blockers' => [
                    'historical' => $blockersData,
                    'current_sprint' => $currentSprintBlockers
                ],
                'velocity' => $velocityData,
                'deployment' => $deploymentData,
                'bugs' => $bugsData,
                'mttr' => $mttrData
            ]
        ];

        // Save to file
        $jsonData = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($outputFile, $jsonData) === false) {
            $io->error(sprintf('Failed to write to file: %s', $outputFile));
            return Command::FAILURE;
        }

        $io->success(sprintf('Data exported successfully to: %s', $outputFile));
        $io->text(sprintf('File size: %s', $this->formatBytes(strlen($jsonData))));

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
} 