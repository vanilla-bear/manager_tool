<?php

namespace App\Controller;

use App\Entity\TeamMember;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamMemberProfileRepository;
use App\Service\TeamMemberAnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TeamMemberProfileController extends AbstractController
{
    public function __construct(
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly TeamMemberProfileRepository $profileRepository,
        private readonly TeamMemberAnalyticsService $analyticsService
    ) {
    }

    #[Route('/team/profiles', name: 'app_team_profiles')]
    public function index(): Response
    {
        $teamMembers = $this->teamMemberRepository->findAll();
        $profiles = [];

        foreach ($teamMembers as $member) {
            $profile = $this->profileRepository->findLatestByTeamMember($member->getId());
            $profiles[] = [
                'member' => $member,
                'profile' => $profile,
                'hasJiraId' => !empty($member->getJiraId())
            ];
        }

        return $this->render('team_member_profile/index.html.twig', [
            'profiles' => $profiles
        ]);
    }

    #[Route('/team/profile/{id}', name: 'app_team_profile_show')]
    public function show(int $id): Response
    {
        $teamMember = $this->teamMemberRepository->find($id);
        
        if (!$teamMember) {
            throw $this->createNotFoundException('Team member not found');
        }

        $profile = $this->profileRepository->findLatestByTeamMember($id);

        return $this->render('team_member_profile/show.html.twig', [
            'teamMember' => $teamMember,
            'profile' => $profile
        ]);
    }

    #[Route('/team/profile/{id}/generate', name: 'app_team_profile_generate', methods: ['POST'])]
    public function generate(int $id, Request $request): Response
    {
        $teamMember = $this->teamMemberRepository->find($id);
        
        if (!$teamMember) {
            throw $this->createNotFoundException('Team member not found');
        }

        if (!$teamMember->getJiraId()) {
            $this->addFlash('error', 'Team member has no Jira ID configured');
            return $this->redirectToRoute('app_team_profile_show', ['id' => $id]);
        }

        try {
            $profile = $this->analyticsService->generateProfile($teamMember);
            $this->addFlash('success', 'Profile generated successfully');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error generating profile: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_team_profile_show', ['id' => $id]);
    }

    #[Route('/team/profile/{id}/export', name: 'app_team_profile_export')]
    public function export(int $id): Response
    {
        $teamMember = $this->teamMemberRepository->find($id);
        
        if (!$teamMember) {
            throw $this->createNotFoundException('Team member not found');
        }

        $profile = $this->profileRepository->findLatestByTeamMember($id);
        
        if (!$profile) {
            throw $this->createNotFoundException('Profile not found');
        }

        $jsonData = $this->analyticsService->exportToJson($profile);
        
        $response = new Response($jsonData);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $teamMember->getName() . '_profile.json"');
        
        return $response;
    }

    #[Route('/team/profiles/generate-all', name: 'app_team_profiles_generate_all', methods: ['POST'])]
    public function generateAll(): Response
    {
        try {
            $profiles = $this->analyticsService->generateAllProfiles();
            $this->addFlash('success', sprintf('%d profiles generated successfully', count($profiles)));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error generating profiles: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_team_profiles');
    }

    #[Route('/team/profile/{id}/refresh', name: 'app_team_profile_refresh', methods: ['POST'])]
    public function refresh(int $id): JsonResponse
    {
        $teamMember = $this->teamMemberRepository->find($id);
        
        if (!$teamMember) {
            return new JsonResponse(['success' => false, 'message' => 'Team member not found'], 404);
        }

        if (!$teamMember->getJiraId()) {
            return new JsonResponse(['success' => false, 'message' => 'No Jira ID configured'], 400);
        }

        try {
            $profile = $this->analyticsService->generateProfile($teamMember);
            return new JsonResponse([
                'success' => true,
                'message' => 'Profile refreshed successfully',
                'lastSyncAt' => $profile->getLastSyncAt()->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/team/profile/{id}/debug', name: 'app_team_profile_debug')]
    public function debug(int $id): Response
    {
        $teamMember = $this->teamMemberRepository->find($id);
        
        if (!$teamMember) {
            throw $this->createNotFoundException('Team member not found');
        }

        if (!$teamMember->getJiraId()) {
            throw $this->createNotFoundException('Team member has no Jira ID configured');
        }

        // Récupérer les données Jira pour debug
        $periodStart = new \DateTimeImmutable('-12 months');
        $periodEnd = new \DateTimeImmutable();
        $jiraData = $this->analyticsService->fetchJiraData($teamMember->getJiraId(), $periodStart, $periodEnd);

        // Analyser les statuts
        $statusCounts = [];
        $issueTypes = [];
        $totalValidated = 0;
        $validatedStatuses = [
            'Done', 'Validé', 'Closed', 'Terminé', 'Resolved', 'Completed',
            'Fermé', 'Validated', 'Approved', 'Accepté', 'Accepté en recette'
        ];

        foreach ($jiraData as $issue) {
            $status = $issue['fields']['status']['name'];
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            
            $issueType = $issue['fields']['issuetype']['name'];
            $issueTypes[$issueType] = ($issueTypes[$issueType] ?? 0) + 1;
            
            if (in_array($status, $validatedStatuses)) {
                $totalValidated++;
            }
        }

        // Analyser la distribution par sprint
        $sprintDistribution = [];
        foreach ($jiraData as $issue) {
            if (isset($issue['fields']['sprint']) && $issue['fields']['sprint']) {
                $sprintField = $issue['fields']['sprint'];
                if (is_array($sprintField)) {
                    foreach ($sprintField as $sprint) {
                        $sprintName = is_array($sprint) && isset($sprint['name']) ? $sprint['name'] : (string)$sprint;
                        $sprintDistribution[$sprintName] = ($sprintDistribution[$sprintName] ?? 0) + 1;
                    }
                } else {
                    $sprintName = is_array($sprintField) && isset($sprintField['name']) ? $sprintField['name'] : (string)$sprintField;
                    if (!empty($sprintName)) {
                        $sprintDistribution[$sprintName] = ($sprintDistribution[$sprintName] ?? 0) + 1;
                    }
                }
            }
        }

        return $this->render('team_member_profile/debug.html.twig', [
            'teamMember' => $teamMember,
            'totalIssues' => count($jiraData),
            'statusCounts' => $statusCounts,
            'issueTypes' => $issueTypes,
            'totalValidated' => $totalValidated,
            'validatedStatuses' => $validatedStatuses,
            'validationRate' => count($jiraData) > 0 ? round(($totalValidated / count($jiraData)) * 100, 1) : 0,
            'sprintDistribution' => $sprintDistribution
        ]);
    }

    #[Route('/team/profiles/reset-all', name: 'app_team_profiles_reset_all', methods: ['POST'])]
    public function resetAllProfiles(): Response
    {
        try {
            $resetCount = $this->analyticsService->resetAllProfiles();
            $this->addFlash('success', "Successfully reset {$resetCount} profiles for all team members");
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error resetting profiles: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_team_profiles');
    }

    #[Route('/team/profile/{id}/reset', name: 'app_team_profile_reset', methods: ['POST'])]
    public function resetMemberProfile(int $id): Response
    {
        $teamMember = $this->teamMemberRepository->find($id);
        
        if (!$teamMember) {
            throw $this->createNotFoundException('Team member not found');
        }

        try {
            $resetCount = $this->analyticsService->resetMemberProfile($id);
            $this->addFlash('success', "Successfully reset {$resetCount} profiles for {$teamMember->getName()}");
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error resetting profile: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_team_profile_show', ['id' => $id]);
    }
} 