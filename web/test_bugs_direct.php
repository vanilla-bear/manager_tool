<?php

// Test direct de la requête JQL
$jiraHost = 'https://studi-pedago.atlassian.net';
$jiraUser = 'your-email@example.com'; // Remplacez par votre email
$jiraToken = 'your-api-token'; // Remplacez par votre token API

// Configuration de base
$projectKey = 'MD'; // Clé du projet
$startDate = '2025-07-01';
$endDate = '2025-07-31';

echo "=== TEST DIRECT BUGS SERVICE ===\n\n";

// Test 1: Vérifier la clé du projet
echo "1. Test de la clé du projet: $projectKey\n";

// Test 2: Différentes requêtes JQL
$testQueries = [
    // Test avec guillemets (nouveau format)
    "project = \"$projectKey\" AND issuetype = \"Bug\" AND created >= \"$startDate\" AND created <= \"$endDate\"",
    
    // Test sans guillemets (ancien format)
    "project = $projectKey AND issuetype = Bug AND created >= \"$startDate\" AND created <= \"$endDate\"",
    
    // Test tous les tickets du projet
    "project = \"$projectKey\" AND created >= \"$startDate\" AND created <= \"$endDate\"",
    
    // Test bugs sans restriction de date
    "project = \"$projectKey\" AND issuetype = \"Bug\"",
];

foreach ($testQueries as $i => $jql) {
    echo "\n" . ($i + 1) . ". Test JQL: $jql\n";
    
    // Simuler l'appel API (vous devrez remplacer par vos vraies credentials)
    echo "   → Cette requête devrait être testée avec vos credentials Jira\n";
    echo "   → URL: $jiraHost/rest/api/3/search\n";
    echo "   → Méthode: POST\n";
    echo "   → Body: {\"jql\":\"$jql\",\"maxResults\":0,\"fields\":[\"key\"]}\n";
}

echo "\n=== INSTRUCTIONS POUR TESTER ===\n";
echo "1. Remplacez 'your-email@example.com' et 'your-api-token' par vos vraies credentials\n";
echo "2. Exécutez ce script: php test_bugs_direct.php\n";
echo "3. Ou testez manuellement avec curl:\n\n";

$curlExample = "curl -u 'your-email@example.com:your-api-token' \\
  -X POST \\
  -H 'Content-Type: application/json' \\
  -d '{\"jql\":\"project = \\\"MD\\\" AND issuetype = \\\"Bug\\\" AND created >= \\\"2025-07-01\\\" AND created <= \\\"2025-07-31\\\"\",\"maxResults\":0,\"fields\":[\"key\"]}' \\
  '$jiraHost/rest/api/3/search'";

echo $curlExample . "\n\n";

echo "=== QUESTIONS À VÉRIFIER ===\n";
echo "1. La clé du projet est-elle bien 'MD' ?\n";
echo "2. Existe-t-il des bugs dans Jira pour la période juillet 2025 ?\n";
echo "3. Le type d'issue s'appelle-t-il bien 'Bug' ou autre chose ?\n";
echo "4. Les credentials Jira sont-ils corrects ?\n";
echo "5. L'instance Jira est-elle accessible ?\n";
