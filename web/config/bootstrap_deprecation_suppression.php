<?php

/**
 * Configuration pour supprimer les avertissements de dépréciation Google API Client
 * Ce fichier doit être inclus avant l'autoloader principal
 */

// Handler d'erreur personnalisé pour filtrer les avertissements de dépréciation Google API
set_error_handler(function ($severity, $message, $file, $line) {
    // Ignorer les avertissements de dépréciation spécifiques à Google API Client
    if ($severity === E_DEPRECATED) {
        // Vérifier si le message provient de Google API Client
        if (strpos($file, 'google/apiclient') !== false || 
            strpos($message, 'Google\\') !== false ||
            strpos($message, 'Implicitly marking parameter') !== false) {
            return true; // Ignorer l'erreur
        }
    }
    
    // Pour les autres erreurs, utiliser le handler par défaut
    return false;
}, E_DEPRECATED);

// Alternative: supprimer complètement les avertissements de dépréciation
// error_reporting(E_ALL & ~E_DEPRECATED);










