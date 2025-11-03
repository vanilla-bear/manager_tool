<?php

require_once 'vendor/autoload.php';

use App\Repository\SprintRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;

// Charger les variables d'environnement
$dotenv = new Dotenv();
$dotenv->load('.env');

// Configuration Doctrine
$config = \Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration(
    ['src/Entity'],
    true,
    null,
    null,
    false
);

$connectionParams = [
    'driver' => 'pdo_mysql',
    'host' => $_ENV['DATABASE_HOST'] ?? 'localhost',
    'port' => $_ENV['DATABASE_PORT'] ?? 3306,
    'dbname' => $_ENV['DATABASE_NAME'] ?? 'manager_tools',
    'user' => $_ENV['DATABASE_USER'] ?? 'root',
    'password' => $_ENV['DATABASE_PASSWORD'] ?? '',
];

try {
    $entityManager = \Doctrine\ORM\EntityManager::create($connectionParams, $config);
    $sprintRepository = $entityManager->getRepository(\App\Entity\Sprint::class);
    
    echo "=== ANALYSE DES DONNÉES DE SPRINT ===\n\n";
    
    // Récupérer les derniers sprints
    $sprints = $sprintRepository->findLastSprints(10);
    
    echo "Nombre de sprints trouvés: " . count($sprints) . "\n\n";
    
    if (empty($sprints)) {
        echo "Aucun sprint trouvé dans la base de données.\n";
        exit(1);
    }
    
    echo "=== DÉTAILS DES SPRINTS ===\n";
    foreach ($sprints as $sprint) {
        echo sprintf(
            "Sprint: %s\n" .
            "  - Début: %s\n" .
            "  - Fin: %s\n" .
            "  - Points Engagés: %s\n" .
            "  - Points Terminés: %s\n" .
            "  - Points Ajoutés: %s\n" .
            "  - Capacité Planifiée: %s\n" .
            "  - Capacité Réelle: %s\n\n",
            $sprint->getName(),
            $sprint->getStartDate() ? $sprint->getStartDate()->format('Y-m-d') : 'N/A',
            $sprint->getEndDate() ? $sprint->getEndDate()->format('Y-m-d') : 'N/A',
            $sprint->getCommittedPoints(),
            $sprint->getCompletedPoints(),
            $sprint->getAddedPointsDuringSprint(),
            $sprint->getPlannedCapacityDays() ?? 'N/A',
            $sprint->getCapacityDays()
        );
    }
    
    // Calculer les statistiques
    $completedPoints = array_map(function($sprint) {
        return $sprint->getCompletedPoints();
    }, $sprints);
    
    $committedPoints = array_map(function($sprint) {
        return $sprint->getCommittedPoints();
    }, $sprints);
    
    echo "=== STATISTIQUES ===\n";
    echo "Moyenne des points terminés: " . round(array_sum($completedPoints) / count($completedPoints), 1) . "\n";
    echo "Moyenne des points engagés: " . round(array_sum($committedPoints) / count($committedPoints), 1) . "\n";
    echo "Min points terminés: " . min($completedPoints) . "\n";
    echo "Max points terminés: " . max($completedPoints) . "\n";
    echo "Écart-type: " . round(sqrt(array_sum(array_map(function($x) use ($completedPoints) {
        $mean = array_sum($completedPoints) / count($completedPoints);
        return pow($x - $mean, 2);
    }, $completedPoints)) / count($completedPoints)), 1) . "\n";
    
} catch (\Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}














