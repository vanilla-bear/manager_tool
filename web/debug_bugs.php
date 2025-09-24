<?php

require_once 'vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

// Configuration Jira (à adapter selon votre config)
$jiraHost = 'https://studi-pedago.atlassian.net';
$jiraUser = 'your-email@example.com'; // Remplacez par votre email
$jiraToken = 'your-api-token'; // Remplacez par votre token API

$client = HttpClient::create([
    'auth_basic' => [$jiraUser, $jiraToken],
    'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ],
]);

echo "=== DEBUG BUGS SERVICE ===\n\n";

// 1. Tester la récupération du projet depuis le board
echo "1. Test récupération du projet depuis le board...\n";
try {
    $boardId = 123; // Remplacez par votre board ID
    $response = $client->request('GET', "$jiraHost/rest/agile/1.0/board/$boardId");
    $boardData = $response->toArray();
    
    $projectKey = $boardData['location']['projectKey'] ?? 'UNKNOWN';
    echo "   ✓ Projet trouvé: $projectKey\n";
    echo "   ✓ Board ID: " . ($boardData['id'] ?? 'UNKNOWN') . "\n";
} catch (Exception $e) {
    echo "   ✗ Erreur récupération board: " . $e->getMessage() . "\n";
    $projectKey = 'MD'; // Fallback
}

// 2. Tester différentes requêtes JQL
echo "\n2. Test des requêtes JQL...\n";

$testQueries = [
    // Test 1: Bugs avec guillemets
    "project = \"$projectKey\" AND issuetype = \"Bug\" AND created >= \"2025-07-01\" AND created <= \"2025-07-31\"",
    
    // Test 2: Bugs sans guillemets (ancien format)
    "project = $projectKey AND issuetype = Bug AND created >= \"2025-07-01\" AND created <= \"2025-07-31\"",
    
    // Test 3: Tous les tickets du projet
    "project = \"$projectKey\" AND created >= \"2025-07-01\" AND created <= \"2025-07-31\"",
    
    // Test 4: Bugs sans restriction de date
    "project = \"$projectKey\" AND issuetype = \"Bug\"",
];

foreach ($testQueries as $i => $jql) {
    echo "\n   Test " . ($i + 1) . ": $jql\n";
    
    try {
        // Test avec API v3 legacy
        $response = $client->request('POST', "$jiraHost/rest/api/3/search", [
            'json' => [
                'jql' => $jql,
                'maxResults' => 0,
                'fields' => ['key']
            ]
        ]);
        
        $data = $response->toArray();
        $total = $data['total'] ?? 0;
        echo "   ✓ API v3 legacy: $total résultats\n";
        
    } catch (Exception $e) {
        echo "   ✗ API v3 legacy: " . $e->getMessage() . "\n";
    }
    
    try {
        // Test avec enhanced search
        $response = $client->request('GET', "$jiraHost/rest/api/3/search/jql", [
            'query' => [
                'jql' => $jql,
                'maxResults' => 1,
                'fields' => 'key'
            ]
        ]);
        
        $data = $response->toArray();
        $total = $data['total'] ?? 0;
        echo "   ✓ Enhanced search: $total résultats\n";
        
    } catch (Exception $e) {
        echo "   ✗ Enhanced search: " . $e->getMessage() . "\n";
    }
}

// 3. Tester les types d'issues disponibles
echo "\n3. Test des types d'issues disponibles...\n";
try {
    $response = $client->request('GET', "$jiraHost/rest/api/3/issuetype");
    $issueTypes = $response->toArray();
    
    echo "   Types d'issues disponibles:\n";
    foreach ($issueTypes as $type) {
        echo "   - " . $type['name'] . " (id: " . $type['id'] . ")\n";
    }
} catch (Exception $e) {
    echo "   ✗ Erreur récupération types: " . $e->getMessage() . "\n";
}

// 4. Tester les statuts disponibles
echo "\n4. Test des statuts disponibles...\n";
try {
    $response = $client->request('GET', "$jiraHost/rest/api/3/status");
    $statuses = $response->toArray();
    
    echo "   Statuts disponibles:\n";
    foreach ($statuses as $status) {
        echo "   - " . $status['name'] . " (id: " . $status['id'] . ")\n";
    }
} catch (Exception $e) {
    echo "   ✗ Erreur récupération statuts: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DU DEBUG ===\n";
