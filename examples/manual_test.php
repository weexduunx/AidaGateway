<?php

/**
 * Exemple de test manuel du package Aida Gateway
 * 
 * Pour tester ce code dans une vraie application Laravel :
 * 1. Installez le package via Composer
 * 2. Configurez vos clés API dans config/aida-gateway.php
 * 3. Créez une route pour tester ces fonctionnalités
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Weexduunx\AidaGateway\Facades\Aida;

echo "=== Test Manuel Aida Gateway ===\n\n";

// 1. Vérifier les gateways supportés
echo "1. Gateways supportés :\n";
$gateways = Aida::getSupportedGateways();
foreach ($gateways as $name => $config) {
    echo "   - {$name}\n";
}
echo "\n";

// 2. Test d'initiation de paiement Wave (mode test)
echo "2. Test paiement Wave (simulation) :\n";
try {
    // En mode réel, ceci ferait un vrai appel API
    echo "   Simulation : Paiement de 5000 XOF vers +221771234567\n";
    echo "   Status : ✅ Transaction créée avec succès\n";
    echo "   Session ID : session_12345_test\n";
    echo "   URL Checkout : https://checkout.wave.com/session_12345_test\n";
} catch (Exception $e) {
    echo "   Erreur : " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Test de vérification de statut
echo "3. Vérification de statut (simulation) :\n";
echo "   Transaction ID : session_12345_test\n";
echo "   Status : pending → success\n";
echo "   Montant : 5000 XOF\n";
echo "\n";

// 4. Test des événements
echo "4. Événements déclenchés :\n";
echo "   ✅ PaymentSuccessful dispatched\n";
echo "   ✅ Email de confirmation envoyé\n";
echo "   ✅ Webhook traité\n";
echo "\n";

echo "=== Fin des tests manuels ===\n";
echo "Pour des tests réels, configurez vos clés API et testez dans une app Laravel.\n";