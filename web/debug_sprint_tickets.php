<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Charger les variables d'environnement
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

// Configuration Jira
$jiraBaseUrl = $_ENV['JIRA_BASE_URL'];
$jiraUsername = $_ENV['JIRA_USERNAME'];
$jiraApiToken = $_ENV['JIRA_API_TOKEN'];

// ID du sprint Poke (S3 PI4 Poke)
$sprintId = 94; // ou 1735 selon votre référence

// Configuration HTTP client
$client = new \GuzzleHttp\Client([
    'base_uri' => $jiraBaseUrl,
    'auth' => [$jiraUsername, $jiraApiToken],
    'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ]
]);

echo "🔍 Debug Sprint Poke (ID: $sprintId)\n";
echo "=====================================\n\n";

// Test 1: Votre requête JQL exacte
echo "📋 Test 1: Votre requête JQL\n";
echo "Query: project = MD AND sprint = $sprintId AND type IN standardIssueTypes() ORDER BY cf[10200] ASC, created DESC\n\n";

try {
    $response = $client->request('POST', '/rest/api/3/search', [
        'json' => [
            'jql' => "project = MD AND sprint = $sprintId AND type IN standardIssueTypes() ORDER BY cf[10200] ASC, created DESC",
            'maxResults' => 200,
            'fields' => ['key', 'summary', 'status', 'customfield_10200', 'created', 'issuetype']
        ]
    ]);
    
    $data = json_decode($response->getBody(), true);
    $issues = $data['issues'] ?? [];
    
    echo "✅ Tickets trouvés: " . count($issues) . "\n";
    
    $totalPoints = 0;
    $ticketsWithPoints = 0;
    $ticketsWithoutPoints = 0;
    
    echo "\n📊 Détail des tickets:\n";
    echo "Key\t\tStory Points\tType\t\tStatus\n";
    echo "---\t\t------------\t----\t\t------\n";
    
    foreach ($issues as $issue) {
        $key = $issue['key'];
        $summary = $issue['fields']['summary'] ?? '';
        $status = $issue['fields']['status']['name'] ?? '';
        $issuetype = $issue['fields']['issuetype']['name'] ?? '';
        $storyPoints = $issue['fields']['customfield_10200'] ?? null;
        
        if ($storyPoints !== null) {
            $totalPoints += (float)$storyPoints;
            $ticketsWithPoints++;
        } else {
            $ticketsWithoutPoints++;
        }
        
        echo sprintf("%-12s\t%s\t\t%-12s\t%s\n", 
            $key, 
            $storyPoints !== null ? $storyPoints : 'N/A',
            $issuetype,
            $status
        );
    }
    
    echo "\n📈 Résumé:\n";
    echo "- Total tickets: " . count($issues) . "\n";
    echo "- Tickets avec story points: $ticketsWithPoints\n";
    echo "- Tickets sans story points: $ticketsWithoutPoints\n";
    echo "- Total story points: $totalPoints\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";

// Test 2: Requête actuelle du code
echo "📋 Test 2: Requête actuelle du code\n";
echo "Query: project = MD AND sprint = $sprintId AND issuetype in (Bug, Epic, Story, Task, Technique) ORDER BY created ASC\n\n";

try {
    $response = $client->request('POST', '/rest/api/3/search', [
        'json' => [
            'jql' => "project = MD AND sprint = $sprintId AND issuetype in (Bug, Epic, Story, Task, Technique) ORDER BY created ASC",
            'maxResults' => 200,
            'fields' => ['key', 'summary', 'status', 'customfield_10200', 'created', 'issuetype']
        ]
    ]);
    
    $data = json_decode($response->getBody(), true);
    $issues = $data['issues'] ?? [];
    
    echo "✅ Tickets trouvés: " . count($issues) . "\n";
    
    $totalPoints = 0;
    foreach ($issues as $issue) {
        $storyPoints = $issue['fields']['customfield_10200'] ?? null;
        if ($storyPoints !== null) {
            $totalPoints += (float)$storyPoints;
        }
    }
    
    echo "- Total story points: $totalPoints\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}



