<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Aida Gateway</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #005a8b; }
        .result { margin-top: 20px; padding: 15px; border-radius: 4px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
    </style>
</head>
<body>
    <h1>🧪 Test Manuel Aida Gateway</h1>
    
    <div class="info">
        <h3>📋 Instructions de test :</h3>
        <ol>
            <li>Configurez vos clés API dans <code>config/aida-gateway.php</code></li>
            <li>Utilisez des numéros de test fournis par votre opérateur</li>
            <li>Testez avec de petits montants en mode sandbox</li>
        </ol>
    </div>

    <form id="paymentForm">
        @csrf
        
        <div class="form-group">
            <label for="gateway">Gateway de paiement :</label>
            <select name="gateway" id="gateway" required>
                <option value="">Sélectionnez un gateway</option>
                @foreach($gateways as $name => $config)
                    <option value="{{ $name }}">{{ ucwords(str_replace('_', ' ', $name)) }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="phone">Numéro de téléphone :</label>
            <input type="tel" name="phone" id="phone" placeholder="+221771234567" required>
            <small>Format international recommandé</small>
        </div>

        <div class="form-group">
            <label for="amount">Montant (XOF) :</label>
            <input type="number" name="amount" id="amount" min="100" placeholder="5000" required>
            <small>Minimum 100 XOF</small>
        </div>

        <div class="form-group">
            <label for="description">Description (optionnel) :</label>
            <textarea name="description" id="description" rows="3" placeholder="Paiement de test..."></textarea>
        </div>

        <button type="submit">🚀 Initier le paiement</button>
    </form>

    <div id="result"></div>

    <hr>

    <h2>📊 Statut des transactions récentes</h2>
    <button onclick="loadTransactions()">🔄 Charger les transactions</button>
    <div id="transactions"></div>

    <script>
        // Gestion du formulaire de paiement
        document.getElementById('paymentForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            
            document.getElementById('result').innerHTML = '<div class="info">⏳ Traitement en cours...</div>';
            
            try {
                const response = await fetch('/test-payment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('[name="_token"]').value
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('result').innerHTML = `
                        <div class="success">
                            <h3>✅ Paiement initié avec succès !</h3>
                            <p><strong>Transaction ID :</strong> ${result.transaction_id}</p>
                            <p><strong>External ID :</strong> ${result.external_id}</p>
                            <p><strong>Status :</strong> ${result.status}</p>
                            ${result.checkout_url ? `<p><a href="${result.checkout_url}" target="_blank">🔗 Ouvrir la page de paiement</a></p>` : ''}
                            <button onclick="checkStatus('${result.transaction_id}')">🔍 Vérifier le statut</button>
                        </div>
                    `;
                } else {
                    document.getElementById('result').innerHTML = `
                        <div class="error">
                            <h3>❌ Erreur</h3>
                            <p>${result.message}</p>
                        </div>
                    `;
                }
            } catch (error) {
                document.getElementById('result').innerHTML = `
                    <div class="error">
                        <h3>❌ Erreur de connexion</h3>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        });

        // Vérifier le statut d'une transaction
        async function checkStatus(transactionId) {
            try {
                const response = await fetch(`/payment-status/${transactionId}`);
                const result = await response.json();
                
                if (result.success) {
                    alert(`Statut : ${result.status}\nMontant : ${result.amount} ${result.currency}`);
                } else {
                    alert(`Erreur : ${result.message}`);
                }
            } catch (error) {
                alert(`Erreur : ${error.message}`);
            }
        }

        // Charger la liste des transactions
        async function loadTransactions() {
            try {
                const response = await fetch('/test-transactions');
                const result = await response.json();
                
                if (result.success) {
                    const html = result.transactions.data.map(t => `
                        <div style="border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 4px;">
                            <strong>${t.transaction_id}</strong> - ${t.status} - ${t.amount} ${t.currency}
                            <br><small>Gateway: ${t.gateway} | ${new Date(t.created_at).toLocaleString()}</small>
                            <br><button onclick="checkStatus('${t.transaction_id}')">Vérifier</button>
                        </div>
                    `).join('');
                    
                    document.getElementById('transactions').innerHTML = html || '<p>Aucune transaction trouvée.</p>';
                }
            } catch (error) {
                document.getElementById('transactions').innerHTML = `<p>Erreur : ${error.message}</p>`;
            }
        }
    </script>
</body>
</html>