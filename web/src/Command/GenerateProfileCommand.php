<?php

namespace App\Command;

use App\Entity\TeamMember;
use App\Repository\TeamMemberRepository;
use App\Service\TeamMemberAnalyticsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-profile',
    description: 'Generate profile for a team member in background',
)]
class GenerateProfileCommand extends Command
{
    public function __construct(
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly TeamMemberAnalyticsService $analyticsService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('member-id', InputArgument::REQUIRED, 'Team member ID')
            ->setHelp('This command generates a profile for a team member in background');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $memberId = $input->getArgument('member-id');

        // Augmenter la limite mémoire pour cette commande
        ini_set('memory_limit', '512M');
        $io->info("Memory limit set to: " . ini_get('memory_limit'));

        $teamMember = $this->teamMemberRepository->find($memberId);
        
        if (!$teamMember) {
            $io->error("Team member with ID {$memberId} not found");
            return Command::FAILURE;
        }

        if (!$teamMember->getJiraId()) {
            $io->error("Team member {$teamMember->getName()} has no Jira ID configured");
            return Command::FAILURE;
        }

        $io->info("Starting profile generation for {$teamMember->getName()} ({$teamMember->getJiraId()})");

        try {
            $profile = $this->analyticsService->generateProfile($teamMember);
            $io->success("Profile generated successfully for {$teamMember->getName()}");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Error generating profile for {$teamMember->getName()}: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 