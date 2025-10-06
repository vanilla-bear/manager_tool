<?php

namespace App\Command;

use App\Repository\SprintRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:sprint-range',
    description: 'Test sprint range queries',
)]
class TestSprintRangeCommand extends Command
{
    public function __construct(
        private readonly SprintRepository $sprintRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("🧪 Test Sprint Range Queries");

        // Test 1: Tous les sprints
        $allSprints = $this->sprintRepository->findLastSprints(20);
        $io->section("📊 Tous les sprints disponibles");
        $io->text("Total sprints: " . count($allSprints));
        
        foreach ($allSprints as $sprint) {
            $io->text("- {$sprint->getName()} ({$sprint->getStartDate()->format('Y-m-d')} to {$sprint->getEndDate()->format('Y-m-d')})");
        }

        // Test 2: Période large (2024-2026)
        $io->section("📅 Test avec période large (2024-2026)");
        $startDate = new \DateTime('2024-01-01');
        $endDate = new \DateTime('2026-12-31');
        $sprintsInRange = $this->sprintRepository->findByDateRange($startDate, $endDate);
        $io->text("Sprints dans la période: " . count($sprintsInRange));
        
        foreach ($sprintsInRange as $sprint) {
            $io->text("- {$sprint->getName()} ({$sprint->getStartDate()->format('Y-m-d')} to {$sprint->getEndDate()->format('Y-m-d')})");
        }

        // Test 3: Période actuelle (2024)
        $io->section("📅 Test avec période actuelle (2024)");
        $startDate = new \DateTime('2024-01-01');
        $endDate = new \DateTime('2024-12-31');
        $sprintsInRange = $this->sprintRepository->findByDateRange($startDate, $endDate);
        $io->text("Sprints dans 2024: " . count($sprintsInRange));

        // Test 4: Période 2025
        $io->section("📅 Test avec période 2025");
        $startDate = new \DateTime('2025-01-01');
        $endDate = new \DateTime('2025-12-31');
        $sprintsInRange = $this->sprintRepository->findByDateRange($startDate, $endDate);
        $io->text("Sprints dans 2025: " . count($sprintsInRange));
        
        foreach ($sprintsInRange as $sprint) {
            $io->text("- {$sprint->getName()} ({$sprint->getStartDate()->format('Y-m-d')} to {$sprint->getEndDate()->format('Y-m-d')})");
        }

        return Command::SUCCESS;
    }
}

