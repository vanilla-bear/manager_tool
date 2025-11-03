<?php

namespace App\Command;

use App\Repository\SprintRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(
    name: 'app:current-sprint',
    description: 'Affiche les informations du sprint courant',
)]
class CurrentSprintCommand extends Command
{
    public function __construct(
        private readonly SprintRepository $sprintRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // Récupérer le sprint le plus récent (actif ou le plus récent)
            $currentSprint = $this->sprintRepository->findCurrentSprint();
            
            if (!$currentSprint) {
                $io->warning('Aucun sprint trouvé dans la base de données');
                return Command::SUCCESS;
            }

            $io->title('Informations du Sprint Courant');
            $io->section(sprintf('Sprint: %s', $currentSprint->getName()));

            // Informations générales
            $io->table(
                ['Propriété', 'Valeur'],
                [
                    ['ID Jira', $currentSprint->getJiraId()],
                    ['Nom', $currentSprint->getName()],
                    ['Date de début', $currentSprint->getStartDate()?->format('Y-m-d H:i:s') ?? 'N/A'],
                    ['Date de fin', $currentSprint->getEndDate()?->format('Y-m-d H:i:s') ?? 'N/A'],
                    ['Dernière synchronisation', $currentSprint->getSyncedAt()?->format('Y-m-d H:i:s') ?? 'N/A'],
                ]
            );

            // Métriques de points
            $io->section('Métriques de Points');
            $io->table(
                ['Métrique', 'Points', 'Pourcentage'],
                [
                    ['Points engagés initialement', $currentSprint->getCommittedPoints(), '100%'],
                    ['Points ajoutés pendant le sprint', $currentSprint->getAddedPointsDuringSprint(), $currentSprint->getAddedPointsPercentage() ? sprintf('%.1f%%', $currentSprint->getAddedPointsPercentage()) : 'N/A'],
                    ['Points terminés (total)', $currentSprint->getCompletedPoints(), $currentSprint->getCompletionRate() ? sprintf('%.1f%%', $currentSprint->getCompletionRate()) : 'N/A'],
                    ['Points terminés par les devs', $currentSprint->getDevsTerminesPoints(), $currentSprint->getDevCompletionRate() ? sprintf('%.1f%%', $currentSprint->getDevCompletionRate()) : 'N/A'],
                ]
            );

            // Métriques de capacité
            $io->section('Métriques de Capacité');
            $io->table(
                ['Métrique', 'Valeur'],
                [
                    ['Capacité planifiée (jours)', $currentSprint->getPlannedCapacityDays() ?? 'N/A'],
                    ['Capacité réelle (jours)', $currentSprint->getCapacityDays()],
                    ['Vélocité par jour', $currentSprint->getVelocityPerDay() ? sprintf('%.2f points/jour', $currentSprint->getVelocityPerDay()) : 'N/A'],
                    ['Nombre de devs terminés', $currentSprint->getDevsTerminesCount()],
                ]
            );

            // Calculs de scope
            $totalScope = $currentSprint->getCommittedPoints() + $currentSprint->getAddedPointsDuringSprint();
            $io->section('Analyse du Scope');
            $io->table(
                ['Métrique', 'Valeur'],
                [
                    ['Scope total', $totalScope],
                    ['Scope initial', $currentSprint->getCommittedPoints()],
                    ['Ajouts pendant le sprint', $currentSprint->getAddedPointsDuringSprint()],
                    ['Taux d\'ajout', $currentSprint->getAddedPointsPercentage() ? sprintf('%.1f%%', $currentSprint->getAddedPointsPercentage()) : 'N/A'],
                ]
            );

            // Statut du sprint
            $now = new \DateTime();
            $startDate = $currentSprint->getStartDate();
            $endDate = $currentSprint->getEndDate();
            
            $status = 'Inconnu';
            $progress = 'N/A';
            
            if ($startDate && $endDate) {
                $start = $startDate->getTimestamp();
                $end = $endDate->getTimestamp();
                $current = $now->getTimestamp();
                
                if ($current < $start) {
                    $status = 'Pas encore commencé';
                } elseif ($current >= $start && $current <= $end) {
                    $status = 'En cours';
                    $totalDuration = $end - $start;
                    $elapsed = $current - $start;
                    $progress = sprintf('%.1f%%', ($elapsed / $totalDuration) * 100);
                } else {
                    $status = 'Terminé';
                }
            }

            $io->section('Statut du Sprint');
            $io->table(
                ['Propriété', 'Valeur'],
                [
                    ['Statut', $status],
                    ['Progression temporelle', $progress],
                ]
            );

            // Bug Tasks créés pendant le sprint
            $bugTasksCount = $this->getBugTasksCount($currentSprint);
            $io->section('Bug Tasks Créés');
            $io->table(
                ['Métrique', 'Valeur'],
                [
                    ['Bug Tasks créés pendant le sprint', $bugTasksCount],
                ]
            );

            // État des lieux des tickets par statut
            $ticketStatusOverview = $this->getTicketStatusOverview($currentSprint);
            $io->section('État des Lieux des Tickets');
            $io->table(
                ['Statut', 'Nombre de Tickets'],
                $ticketStatusOverview
            );

            // Types de tickets du sprint
            $ticketTypesOverview = $this->getTicketTypesOverview($currentSprint);
            $io->section('Types de Tickets du Sprint');
            $io->table(
                ['Type de Ticket', 'Nombre', 'Pourcentage'],
                $ticketTypesOverview
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Erreur lors de la récupération des informations du sprint : %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    /**
     * Récupère le nombre de Bug Tasks créés pendant le sprint
     */
    private function getBugTasksCount($sprint): int
    {
        try {
            $client = $this->createJiraClient();
            
            // Utiliser l'API Agile pour récupérer les tickets du sprint
            $response = $client->request('GET', '/rest/agile/1.0/sprint/' . $sprint->getJiraId() . '/issue', [
                'query' => [
                    'maxResults' => 100,
                    'fields' => 'issuetype,created'
                ]
            ]);

            $data = $response->toArray();
            $bugTasksCount = 0;
            
            foreach ($data['issues'] ?? [] as $issue) {
                $issueType = $issue['fields']['issuetype']['name'] ?? '';
                $created = $issue['fields']['created'] ?? '';
                
                // Vérifier si c'est un Bug Task et s'il a été créé pendant le sprint
                if (strpos($issueType, 'Sub-task') !== false || strpos($issueType, 'Bug') !== false) {
                    $createdDate = new \DateTime($created);
                    if ($createdDate >= $sprint->getStartDate() && $createdDate <= $sprint->getEndDate()) {
                        $bugTasksCount++;
                    }
                }
            }

            return $bugTasksCount;

        } catch (\Exception $e) {
            error_log('Erreur récupération Bug Tasks pour sprint ' . $sprint->getName() . ': ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Récupère l'état des lieux des tickets par statut jusqu'à "Test PO"
     */
    private function getTicketStatusOverview($sprint): array
    {
        try {
            $client = $this->createJiraClient();
            
            // Récupérer tous les tickets du sprint
            $response = $client->request('GET', '/rest/agile/1.0/sprint/' . $sprint->getJiraId() . '/issue', [
                'query' => [
                    'maxResults' => 100,
                    'fields' => 'status,issuetype'
                ]
            ]);

            $data = $response->toArray();
            
            // Compter les tickets par statut (en excluant les sous-tâches)
            $statusCounts = [];
            foreach ($data['issues'] ?? [] as $issue) {
                $issueType = $issue['fields']['issuetype']['name'] ?? '';
                
                // Exclure les sous-tâches des calculs
                if (stripos($issueType, 'Sub-task') !== false || stripos($issueType, 'Sous-tâche') !== false || stripos($issueType, 'Sous-tache') !== false) {
                    continue;
                }
                
                $status = $issue['fields']['status']['name'] ?? 'Unknown';
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            }

            // Statuts à afficher (dans l'ordre souhaité)
            $statusesToShow = [
                'En attente',
                'Test PO',
                'Devs Terminés',
                'En Cours',
                'A faire',
                'Annulé',
                'Ouverte'
            ];

            // Créer l'overview avec seulement les statuts souhaités
            $overview = [];
            foreach ($statusesToShow as $status) {
                $count = $statusCounts[$status] ?? 0;
                $overview[] = [$status, $count];
            }

            return $overview;

        } catch (\Exception $e) {
            error_log('Erreur récupération statuts pour sprint ' . $sprint->getName() . ': ' . $e->getMessage());
            return [['Erreur', 'Impossible de récupérer les données']];
        }
    }

    /**
     * Récupère les types de tickets du sprint avec répartition
     */
    private function getTicketTypesOverview($sprint): array
    {
        try {
            $client = $this->createJiraClient();
            
            // Récupérer tous les tickets du sprint
            $response = $client->request('GET', '/rest/agile/1.0/sprint/' . $sprint->getJiraId() . '/issue', [
                'query' => [
                    'maxResults' => 100,
                    'fields' => 'issuetype'
                ]
            ]);

            $data = $response->toArray();
            
            // Compter les tickets par type (en excluant les sous-tâches)
            $typeCounts = [];
            $totalTickets = 0;
            
            foreach ($data['issues'] ?? [] as $issue) {
                $issueType = $issue['fields']['issuetype']['name'] ?? 'Unknown';
                
                // Exclure les sous-tâches des calculs
                if (stripos($issueType, 'Sub-task') !== false || stripos($issueType, 'Sous-tâche') !== false || stripos($issueType, 'Sous-tache') !== false) {
                    continue;
                }
                
                $typeCounts[$issueType] = ($typeCounts[$issueType] ?? 0) + 1;
                $totalTickets++;
            }

            // Créer l'overview avec pourcentages
            $overview = [];
            foreach ($typeCounts as $type => $count) {
                $percentage = $totalTickets > 0 ? round(($count / $totalTickets) * 100, 1) : 0;
                $overview[] = [$type, $count, $percentage . '%'];
            }

            // Trier par nombre décroissant
            usort($overview, function($a, $b) {
                return $b[1] - $a[1];
            });

            return $overview;

        } catch (\Exception $e) {
            error_log('Erreur récupération types de tickets pour sprint ' . $sprint->getName() . ': ' . $e->getMessage());
            return [['Erreur', 'Impossible de récupérer les données', 'N/A']];
        }
    }

    /**
     * Crée un client Jira avec connexion directe
     */
    private function createJiraClient()
    {
        return HttpClient::create([
            'base_uri' => 'https://studi-pedago.atlassian.net',
            'headers' => [
                'Authorization' => 'Basic cm9tYWluLmRhbHZlcm55QHN0dWRpLmZyOk9HSHRNdXRCR0NRWnhaMXdrM0RKQkVBMw==',
                'Content-Type' => 'application/json',
            ]
        ]);
    }
}

