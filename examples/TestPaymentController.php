<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Weexduunx\AidaGateway\Facades\Aida;
use Weexduunx\AidaGateway\Models\Transaction;

/**
 * Exemple de contrôleur pour tester Aida Gateway dans une vraie app Laravel
 * 
 * Routes à ajouter dans web.php :
 * Route::get('/test-payment', [TestPaymentController::class, 'showForm']);
 * Route::post('/test-payment', [TestPaymentController::class, 'processPayment']);
 * Route::get('/payment-status/{transactionId}', [TestPaymentController::class, 'checkStatus']);
 */
class TestPaymentController extends Controller
{
    /**
     * Afficher le formulaire de test de paiement
     */
    public function showForm()
    {
        $gateways = Aida::getSupportedGateways();
        
        return view('test-payment', compact('gateways'));
    }

    /**
     * Traiter un paiement de test
     */
    public function processPayment(Request $request)
    {
        $request->validate([
            'gateway' => 'required|string',
            'phone' => 'required|string',
            'amount' => 'required|numeric|min:100',
            'description' => 'nullable|string'
        ]);

        try {
            // Initier le paiement
            $response = Aida::gateway($request->gateway)
                ->pay(
                    $request->phone,
                    $request->amount,
                    $request->description ?? 'Test payment'
                );

            if ($response->isSuccessful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Paiement initié avec succès',
                    'transaction_id' => $response->getTransactionId(),
                    'external_id' => $response->getExternalId(),
                    'checkout_url' => $response->getData()['checkout_url'] ?? null,
                    'status' => $response->getStatus()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $response->getMessage()
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifier le statut d'une transaction
     */
    public function checkStatus($transactionId)
    {
        try {
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
            
            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction non trouvée'
                ], 404);
            }

            // Vérifier le statut via l'API
            $response = Aida::gateway($transaction->gateway)
                ->checkStatus($transaction->external_id);

            return response()->json([
                'success' => true,
                'transaction_id' => $transaction->transaction_id,
                'external_id' => $transaction->external_id,
                'status' => $response->getStatus(),
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'gateway' => $transaction->gateway,
                'created_at' => $transaction->created_at,
                'updated_at' => $transaction->updated_at
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lister toutes les transactions de test
     */
    public function listTransactions()
    {
        $transactions = Transaction::latest()->paginate(10);
        
        return response()->json([
            'success' => true,
            'transactions' => $transactions
        ]);
    }
}