<?php

namespace App\Command;

use App\Repository\SprintRepository;
use App\Repository\BugMTTRRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reset-metrics',
    description: 'Réinitialise toutes les données des métriques',
)]
class ResetMetricsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SprintRepository $sprintRepository,
        private readonly BugMTTRRepository $bugMTTRRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Forcer la réinitialisation sans confirmation'
            )
            ->addOption(
                'sprints',
                null,
                InputOption::VALUE_NONE,
                'Réinitialiser uniquement les données des sprints'
            )
            ->addOption(
                'bugs',
                null,
                InputOption::VALUE_NONE,
                'Réinitialiser uniquement les données des bugs'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Si aucune option spécifique n'est fournie, on réinitialise tout
        $resetSprints = $input->getOption('sprints') || (!$input->getOption('sprints') && !$input->getOption('bugs'));
        $resetBugs = $input->getOption('bugs') || (!$input->getOption('sprints') && !$input->getOption('bugs'));

        // Demander confirmation sauf si --force est utilisé
        if (!$input->getOption('force')) {
            $message = 'Cette commande va réinitialiser ';
            if ($resetSprints && $resetBugs) {
                $message .= 'TOUTES les données des métriques';
            } else {
                $message .= $resetSprints ? 'les données des sprints' : 'les données des bugs';
            }
            $message .= '. Êtes-vous sûr ?';

            if (!$io->confirm($message, false)) {
                $io->warning('Opération annulée');
                return Command::SUCCESS;
            }
        }

        try {
            if ($resetSprints) {
                $io->section('Réinitialisation des données des sprints');
                $connection = $this->entityManager->getConnection();
                $platform = $connection->getDatabasePlatform();
                
                // Désactiver la vérification des clés étrangères temporairement
                $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
                
                // Tronquer la table des sprints
                $connection->executeStatement($platform->getTruncateTableSQL('sprint', true));
                
                // Réactiver la vérification des clés étrangères
                $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
                
                $io->success('Données des sprints réinitialisées');
            }

            if ($resetBugs) {
                $io->section('Réinitialisation des données des bugs');
                $connection = $this->entityManager->getConnection();
                $platform = $connection->getDatabasePlatform();
                
                // Désactiver la vérification des clés étrangères temporairement
                $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
                
                // Tronquer la table des bugs
                $connection->executeStatement($platform->getTruncateTableSQL('bug_mttr', true));
                
                // Réactiver la vérification des clés étrangères
                $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
                
                $io->success('Données des bugs réinitialisées');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Erreur lors de la réinitialisation : %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
} 