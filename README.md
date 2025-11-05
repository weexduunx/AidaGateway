# Laravel Aida Gateway

[![Latest Version on Packagist](https://img.shields.io/packagist/v/weexduunx/laravel-aida-gateway.svg?style=flat-square)](https://packagist.org/packages/weexduunx/laravel-aida-gateway)
[![Total Downloads](https://img.shields.io/packagist/dt/weexduunx/laravel-aida-gateway.svg?style=flat-square)](https://packagist.org/packages/weexduunx/laravel-aida-gateway)
[![PHP Version](https://img.shields.io/packagist/php-v/weexduunx/laravel-aida-gateway.svg?style=flat-square)](https://packagist.org/packages/weexduunx/laravel-aida-gateway)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%20%7C%2011.x%20%7C%2012.x-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/weexduunx/laravel-aida-gateway.svg?style=flat-square)](https://packagist.org/packages/weexduunx/laravel-aida-gateway)
![Made-In-Senegal](https://github.com/GalsenDev221/made.in.senegal/raw/master/assets/badge.svg)

Un package Laravel pour intégrer facilement les paiements Mobile Money (Orange Money, Wave, Free Money, E-money) avec une API unifiée.

## Fonctionnalités

- **Support de plusieurs gateways** : Orange Money, Wave, Free Money, E-money
- **API unifiée** : Utilisez la même interface pour tous les gateways
- **Webhooks automatiques** : Réception automatique des confirmations de paiement
- **Middleware de sécurité** : Protection contre les transactions en double et rate limiting
- **Événements Laravel** : Écoutez les événements de paiement (succès, échec, en attente)
- **Logging complet** : Suivi détaillé de toutes les transactions
- **Gestion des transactions** : Modèle Eloquent pour gérer vos transactions
- **Facades pratiques** : `Aida::pay()`, `Aida::checkStatus()`, `Aida::refund()`

## Installation

Installez le package via Composer :

```bash
composer require weexduunx/laravel-aida-gateway
```

Le package s'enregistrera automatiquement grâce à Laravel Package Discovery.

### Publication des fichiers

Publiez le fichier de configuration :

```bash
php artisan vendor:publish --tag=aida-config
```

Publiez et exécutez les migrations :

```bash
php artisan vendor:publish --tag=aida-migrations
php artisan migrate
```

## Configuration

Ajoutez vos credentials dans le fichier `.env` :

```env
# Configuration par défaut
AIDA_DEFAULT_GATEWAY=orange_money

# Orange Money
AIDA_ORANGE_MONEY_ENABLED=true
AIDA_ORANGE_MONEY_API_URL=https://api.orange.com/orange-money-webpay
AIDA_ORANGE_MONEY_MERCHANT_KEY=your_merchant_key
AIDA_ORANGE_MONEY_API_USERNAME=your_api_username
AIDA_ORANGE_MONEY_API_PASSWORD=your_api_password
AIDA_ORANGE_MONEY_CURRENCY=XOF
AIDA_ORANGE_MONEY_COUNTRY_CODE=SN

# Wave
AIDA_WAVE_ENABLED=true
AIDA_WAVE_API_URL=https://api.wave.com
AIDA_WAVE_API_KEY=your_api_key
AIDA_WAVE_API_SECRET=your_api_secret
AIDA_WAVE_CURRENCY=XOF

# Free Money
AIDA_FREE_MONEY_ENABLED=true
AIDA_FREE_MONEY_API_URL=https://api.free.sn
AIDA_FREE_MONEY_MERCHANT_ID=your_merchant_id
AIDA_FREE_MONEY_API_KEY=your_api_key
AIDA_FREE_MONEY_API_SECRET=your_api_secret
AIDA_FREE_MONEY_CURRENCY=XOF

# E-money
AIDA_EMONEY_ENABLED=true
AIDA_EMONEY_API_URL=https://api.emoney.sn
AIDA_EMONEY_MERCHANT_CODE=your_merchant_code
AIDA_EMONEY_API_KEY=your_api_key
AIDA_EMONEY_API_SECRET=your_api_secret
AIDA_EMONEY_CURRENCY=XOF

# Webhook
AIDA_WEBHOOK_ROUTE_PREFIX=aida/webhooks
AIDA_WEBHOOK_SECRET=your_webhook_secret

# Transaction
AIDA_TRANSACTION_TIMEOUT=300

# Logging
AIDA_LOGGING_ENABLED=true
AIDA_LOGGING_CHANNEL=stack
```

## Utilisation

### Initier un paiement

```php
use Weexduunx\AidaGateway\Facades\Aida;

// Utiliser le gateway par défaut
$response = Aida::pay(
    phoneNumber: '+221771234567',
    amount: 5000,
    description: 'Paiement pour commande #12345'
);

// Utiliser un gateway spécifique
$response = Aida::gateway('wave')->pay(
    phoneNumber: '+221771234567',
    amount: 5000,
    description: 'Paiement Wave'
);

// Vérifier le résultat
if ($response->isSuccessful()) {
    echo "Transaction ID: " . $response->getTransactionId();
    echo "Statut: " . $response->getStatus();

    // Récupérer l'URL de paiement si disponible
    $paymentUrl = $response->getData()['payment_url'] ?? null;

    if ($paymentUrl) {
        return redirect($paymentUrl);
    }
} else {
    echo "Erreur: " . $response->getMessage();
}
```

### Vérifier le statut d'une transaction

```php
$response = Aida::checkStatus('TRANSACTION_ID');

if ($response->isSuccessful()) {
    echo "Statut: " . $response->getStatus();
    echo "Montant: " . $response->getAmount();
}
```

### Rembourser une transaction

```php
// Remboursement complet
$response = Aida::refund('TRANSACTION_ID');

// Remboursement partiel
$response = Aida::refund('TRANSACTION_ID', 2500);

if ($response->isSuccessful()) {
    echo "Remboursement effectué";
}
```

### Obtenir les gateways disponibles

```php
$supportedGateways = Aida::getSupportedGateways();
$enabledGateways = Aida::getEnabledGateways();
```

## Webhooks

Les webhooks sont automatiquement configurés aux URLs suivantes :

- Orange Money : `/aida/webhooks/orange-money`
- Wave : `/aida/webhooks/wave`
- Free Money : `/aida/webhooks/free-money`
- E-money : `/aida/webhooks/emoney`

Configurez ces URLs dans vos dashboards respectifs des différents gateways.

### Écouter les événements

```php
use Weexduunx\AidaGateway\Events\PaymentSuccessful;
use Weexduunx\AidaGateway\Events\PaymentFailed;
use Weexduunx\AidaGateway\Events\PaymentPending;

// Dans EventServiceProvider
protected $listen = [
    PaymentSuccessful::class => [
        \App\Listeners\SendPaymentConfirmation::class,
    ],
    PaymentFailed::class => [
        \App\Listeners\NotifyPaymentFailure::class,
    ],
    PaymentPending::class => [
        \App\Listeners\LogPendingPayment::class,
    ],
];
```

### Exemple de Listener

```php
namespace App\Listeners;

use Weexduunx\AidaGateway\Events\PaymentSuccessful;

class SendPaymentConfirmation
{
    public function handle(PaymentSuccessful $event)
    {
        $transaction = $event->transaction;

        // Envoyer une notification au client
        // Mettre à jour la commande
        // etc.
    }
}
```

## Middleware de sécurité

Utilisez le middleware `SecureTransaction` pour protéger vos routes de paiement :

```php
use Weexduunx\AidaGateway\Http\Middleware\SecureTransaction;

// Dans un contrôleur
Route::post('/payment', [PaymentController::class, 'process'])
    ->middleware(SecureTransaction::class);
```

Le middleware offre :
- Protection contre les transactions en double
- Rate limiting par IP
- Validation des montants
- Logging des tentatives

## Modèle Transaction

Accédez aux transactions via le modèle Eloquent :

```php
use Weexduunx\AidaGateway\Models\Transaction;

// Récupérer toutes les transactions réussies
$successfulTransactions = Transaction::successful()->get();

// Filtrer par gateway
$waveTransactions = Transaction::byGateway('wave')->get();

// Récupérer les transactions en attente
$pendingTransactions = Transaction::pending()->get();

// Récupérer une transaction spécifique
$transaction = Transaction::where('transaction_id', 'TXN_123')->first();

// Vérifier le statut
if ($transaction->isSuccessful()) {
    echo "Transaction réussie";
}

// Obtenir le montant formaté
echo $transaction->formatted_amount; // "5,000.00 XOF"

// Obtenir le nom du gateway
echo $transaction->gateway_display_name; // "Orange Money"
```

## Gestion des erreurs

```php
use Weexduunx\AidaGateway\Exceptions\GatewayNotFoundException;
use Weexduunx\AidaGateway\Exceptions\GatewayNotEnabledException;

try {
    $response = Aida::gateway('invalid_gateway')->pay(...);
} catch (GatewayNotFoundException $e) {
    // Gateway non supporté
} catch (GatewayNotEnabledException $e) {
    // Gateway désactivé dans la configuration
}
```

## Tests

```bash
composer test
```

## Sécurité

Si vous découvrez des problèmes de sécurité, veuillez envoyer un email à l'équipe de sécurité au lieu d'utiliser l'issue tracker.

## Licence

Ce package est open-source et disponible sous la [licence MIT](LICENSE.md).

## Crédits

- [Idrissa Ndiouck aka Weex Duunx](https://github.com/weexduunx)

## Support

Pour toute question ou problème, veuillez ouvrir une issue sur GitHub.
