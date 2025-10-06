<?php

namespace App\Command;

use App\Service\BugsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:bug-tasks',
    description: 'Test Bug Tasks retrieval',
)]
class TestBugTasksCommand extends Command
{
    public function __construct(
        private readonly BugsService $bugsService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("🧪 Test Bug Tasks Retrieval");

        // Test avec les 3 derniers mois
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-3 months');

        $io->text("Période de test: {$startDate->format('Y-m-d')} à {$endDate->format('Y-m-d')}");

        try {
            $data = $this->bugsService->getBugsData($startDate, $endDate);
            
            $io->success("✅ Service fonctionne correctement !");
            
            $io->section("📊 Résumé");
            $io->text("Bugs trouvés: " . $data['total_bugs']);
            $io->text("Bug Tasks trouvés: " . $data['total_bug_tasks']);
            $io->text("Taux de bugs: " . $data['bug_rate'] . "%");
            
            $io->section("📈 Détail des Bug Tasks");
            if (!empty($data['bug_tasks'])) {
                $table = $io->createTable();
                $table->setHeaders(['Key', 'Summary', 'Created', 'Status', 'Parent']);
                
                foreach (array_slice($data['bug_tasks'], 0, 10) as $bugTask) {
                    $fields = $bugTask['fields'] ?? [];
                    $table->addRow([
                        $bugTask['key'] ?? '',
                        substr($fields['summary'] ?? '', 0, 50) . '...',
                        $fields['created'] ?? '',
                        $fields['status']['name'] ?? '',
                        $fields['parent']['key'] ?? ''
                    ]);
                }
                
                $table->render();
                
                if (count($data['bug_tasks']) > 10) {
                    $io->text("... et " . (count($data['bug_tasks']) - 10) . " autres Bug Tasks");
                }
            } else {
                $io->text("Aucun Bug Task trouvé pour cette période");
            }
            
        } catch (\Exception $e) {
            $io->error("❌ Erreur: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

