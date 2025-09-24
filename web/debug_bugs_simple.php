<?php

// Script simple pour débugger le BugsService
require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Charger les variables d'environnement
$dotenv = new Dotenv();
$dotenv->load('.env.local', '.env');

// Configuration de base
$startDate = new DateTime('2025-07-01');
$endDate = new DateTime('2025-07-31');

echo "=== DEBUG BUGS SERVICE SIMPLE ===\n\n";
echo "Période testée: {$startDate->format('Y-m-d')} à {$endDate->format('Y-m-d')}\n\n";

// Test 1: Vérifier la configuration Jira
echo "1. Configuration Jira:\n";
$jiraHost = $_ENV['JIRA_HOST'] ?? 'Non défini';
$jiraUser = $_ENV['JIRA_USER'] ?? 'Non défini';
$jiraToken = $_ENV['JIRA_TOKEN'] ?? 'Non défini';
$jiraBoardId = $_ENV['JIRA_BOARD_ID'] ?? 'Non défini';

echo "   - Host: $jiraHost\n";
echo "   - User: $jiraUser\n";
echo "   - Token: " . (strlen($jiraToken) > 0 ? 'Défini (' . strlen($jiraToken) . ' caractères)' : 'Non défini') . "\n";
echo "   - Board ID: $jiraBoardId\n\n";

// Test 2: Vérifier la base de données
echo "2. Configuration base de données:\n";
$databaseUrl = $_ENV['DATABASE_URL'] ?? 'Non défini';
echo "   - URL: $databaseUrl\n\n";

// Test 3: Requêtes JQL à tester
echo "3. Requêtes JQL à tester manuellement:\n";
$projectKey = 'MD';
$queries = [
    "project = \"$projectKey\" AND issuetype = \"Bug\" AND created >= \"2025-07-01\" AND created <= \"2025-07-31\"",
    "project = \"$projectKey\" AND created >= \"2025-07-01\" AND created <= \"2025-07-31\"",
    "project = \"$projectKey\" AND issuetype = \"Bug\"",
];

foreach ($queries as $i => $jql) {
    echo "   " . ($i + 1) . ". $jql\n";
}

echo "\n=== INSTRUCTIONS POUR DÉBUGGER ===\n";
echo "1. Vérifiez que les variables d'environnement sont correctes\n";
echo "2. Testez les requêtes JQL dans l'interface Jira\n";
echo "3. Vérifiez que des bugs existent pour la période juillet 2025\n";
echo "4. Vérifiez que le type d'issue s'appelle bien 'Bug'\n";
echo "5. Vérifiez que la clé du projet est bien 'MD'\n\n";

echo "=== COMMANDE CURL POUR TESTER ===\n";
$curlCommand = "curl -u '$jiraUser:$jiraToken' \\
  -X POST \\
  -H 'Content-Type: application/json' \\
  -d '{\"jql\":\"project = \\\"MD\\\" AND issuetype = \\\"Bug\\\" AND created >= \\\"2025-07-01\\\" AND created <= \\\"2025-07-31\\\"\",\"maxResults\":0,\"fields\":[\"key\"]}' \\
  '$jiraHost/rest/api/3/search'";

echo $curlCommand . "\n\n";

echo "=== PROCHAINES ÉTAPES ===\n";
echo "1. Exécutez la commande curl ci-dessus\n";
echo "2. Si elle retourne des résultats, le problème est dans le code Symfony\n";
echo "3. Si elle ne retourne rien, le problème est dans la requête JQL ou les données\n";
echo "4. Vérifiez les logs Symfony: tail -f var/log/dev.log\n";
