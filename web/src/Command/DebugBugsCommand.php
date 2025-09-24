<?php

namespace App\Command;

use App\Service\BugStatsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-bugs',
    description: 'Debug bugs service step by step',
)]
class DebugBugsCommand extends Command
{
    public function __construct(
        private readonly BugStatsService $bugStatsService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('start', null, InputOption::VALUE_REQUIRED, 'Start date (Y-m-d format)', '2025-07-01')
            ->addOption('end', null, InputOption::VALUE_REQUIRED, 'End date (Y-m-d format)', '2025-07-31');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $startDate = new \DateTime($input->getOption('start'));
        $endDate = new \DateTime($input->getOption('end'));
        
        $io->title('🐛 Debug Bugs Service');
        $io->section('📅 Paramètres');
        $io->table(['Paramètre', 'Valeur'], [
            ['Date de début', $startDate->format('Y-m-d')],
            ['Date de fin', $endDate->format('Y-m-d')],
        ]);
        
        try {
            $io->section('🔍 Test 1: Récupération du projet depuis le board');
            $jql = $this->bugStatsService->getBugsJQLForMonth($startDate);
            $io->text("JQL généré: $jql");
            
            $io->section('🔍 Test 2: Synchronisation d\'un mois');
            $io->text("Synchronisation du mois: " . $startDate->format('Y-m'));
            
            $stats = $this->bugStatsService->synchronizeMonth($startDate);
            
            $io->section('📊 Résultats');
            $io->table(['Métrique', 'Valeur'], [
                ['Mois', $stats->getMonth()->format('Y-m')],
                ['Bugs count', $stats->getBugsCount()],
                ['Delivered tickets', $stats->getDeliveredTicketsCount()],
                ['Synced at', $stats->getSyncedAt()->format('Y-m-d H:i:s')],
            ]);
            
            if ($stats->getBugsCount() == 0) {
                $io->warning('⚠️  Aucun bug trouvé pour cette période !');
                $io->text('Cela peut indiquer:');
                $io->listing([
                    'La requête JQL ne trouve pas de bugs',
                    'Le projet/clé n\'est pas correct',
                    'Les dates ne correspondent pas aux bugs existants',
                    'Le type d\'issue "Bug" n\'existe pas ou a un nom différent'
                ]);
            } else {
                $io->success("✅ Trouvé {$stats->getBugsCount()} bugs pour cette période !");
            }
            
        } catch (\Exception $e) {
            $io->error('❌ Erreur: ' . $e->getMessage());
            $io->text('Stack trace:');
            $io->text($e->getTraceAsString());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}
