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
    name: 'app:debug-team-member',
    description: 'Debug team member data and statuses',
)]
class DebugTeamMemberCommand extends Command
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
            ->setHelp('This command helps debug team member data and statuses');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $memberId = $input->getArgument('member-id');

        $teamMember = $this->teamMemberRepository->find($memberId);
        if (!$teamMember) {
            $io->error("Team member with ID {$memberId} not found");
            return Command::FAILURE;
        }

        $io->title("Debug Team Member: {$teamMember->getName()}");
        $io->text("Jira ID: {$teamMember->getJiraId()}");

        // Récupérer les données Jira
        $periodStart = new \DateTimeImmutable('-12 months');
        $periodEnd = new \DateTimeImmutable();
        
        $io->section("Fetching Jira data...");
        $jiraData = $this->analyticsService->fetchJiraData($teamMember->getJiraId(), $periodStart, $periodEnd);
        
        $io->text("Total issues found: " . count($jiraData));

        if (empty($jiraData)) {
            $io->warning("No Jira data found for this team member");
            return Command::SUCCESS;
        }

        // Analyser les statuts
        $statusCounts = [];
        $issueTypes = [];
        $validatedStatuses = [
            'Done', 'Validé', 'Closed', 'Terminé', 'Resolved', 'Completed',
            'Fermé', 'Validated', 'Approved', 'Accepté', 'Accepté en recette'
        ];

        foreach ($jiraData as $issue) {
            $status = $issue['fields']['status']['name'];
            $issueType = $issue['fields']['issuetype']['name'];
            
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            $issueTypes[$issueType] = ($issueTypes[$issueType] ?? 0) + 1;
        }

        $io->section("Status Distribution:");
        foreach ($statusCounts as $status => $count) {
            $isValidated = in_array($status, $validatedStatuses) ? ' ✓' : '';
            $io->text("  {$status}: {$count}{$isValidated}");
        }

        $io->section("Issue Type Distribution:");
        foreach ($issueTypes as $type => $count) {
            $io->text("  {$type}: {$count}");
        }

        // Compter les tickets validés
        $totalValidated = 0;
        foreach ($jiraData as $issue) {
            $status = $issue['fields']['status']['name'];
            if (in_array($status, $validatedStatuses)) {
                $totalValidated++;
            }
        }

        $io->section("Quality Analysis:");
        $io->text("Total validated tickets: {$totalValidated}");
        $io->text("Validation rate: " . round(($totalValidated / count($jiraData)) * 100, 1) . "%");

        return Command::SUCCESS;
    }
} 