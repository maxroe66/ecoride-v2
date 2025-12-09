#!/bin/bash

echo "🧪 Test d'annulation - Version simplifiée"
echo "=========================================="
echo ""

# Récupérer les données existantes de la DB
echo "1️⃣  Récupération des données de test..."

# ID du chauffeur et du trajet
# Utiliser le premier conducteur disponible dans les données test
CHAUFFEUR_ID=8
TRAJET_ID=$(docker compose exec -T db mysql -uroot -prootpassword ecoride -N -e "SELECT covoiturage_id FROM covoiturage WHERE conducteur_id=$CHAUFFEUR_ID AND statut='planifie' LIMIT 1;" 2>&1 | grep -v Warning | tail -1)

echo "✅ Chauffeur ID: $CHAUFFEUR_ID"
echo "✅ Trajet ID: $TRAJET_ID"

# Compter les participants
PARTICIPANTS=$(docker compose exec -T db mysql -uroot -prootpassword ecoride -N -e "SELECT COUNT(*) FROM participation WHERE covoiturage_id=$TRAJET_ID AND statut='confirmee';" 2>&1 | grep -v Warning | tail -1)
echo "✅ Participants confirmés: $PARTICIPANTS"

if [ -z "$TRAJET_ID" ] || [ "$TRAJET_ID" = "0" ]; then
  echo "❌ Aucun trajet trouvé"
  exit 1
fi

# Simuler un appel d'annulation en PHP directement
echo ""
echo "2️⃣  Simulation de l'annulation (via PHP direct)..."
echo ""

# Créer un script PHP test
cat > /tmp/test_annul.php <<'EOF'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Charger l'app
require_once '/var/www/html/app/Factories/ServiceLocator.php';
require_once '/var/www/html/app/Services/AuthService.php';
require_once '/var/www/html/app/Services/CancellationService.php';
require_once '/var/www/html/app/Services/EmailService.php';
require_once '/var/www/html/app/Repositories/ParticipationRepository.php';
require_once '/var/www/html/app/Repositories/TrajetRepository.php';
require_once '/var/www/html/app/Models/User.php';

use App\Factories\ServiceLocator as SL;
use App\Services\CancellationService;
use App\Services\EmailService;

// Initialiser la BDD
define('APP_ENV', 'test');
define('APP_DEBUG', true);

// Paramètres
$tripId = (int)getenv('TRIP_ID');
$userId = (int)getenv('DRIVER_ID');

echo "🧪 Test d'annulation de trajet\n";
echo "===============================\n\n";

try {
    // 1. Annuler le trajet
    echo "1️⃣  Annulation du trajet ID=$tripId par chauffeur ID=$userId...\n";
    $cancellationService = SL::getCancellationService();
    $result = $cancellationService->cancelTripAsDriver($tripId, $userId, "Test automatique");
    
    echo "✅ Trajet annulé\n";
    echo "   Remboursement: " . ($result['refunded_amount'] ?? 'N/A') . " crédits\n\n";
    
    // 2. Envoyer les emails
    echo "2️⃣  Envoi des notifications aux participants...\n";
    $emailService = SL::getEmailService();
    $trajetRepo = SL::getTrajetRepository();
    $participationRepo = SL::getParticipationRepository();
    $userRepo = SL::getUserRepository();
    
    $trajet = $trajetRepo->getTrajetDetail($tripId);
    $allParticipants = $participationRepo->findByTrip($tripId);
    $participants = array_filter($allParticipants, fn($p) => $p['statut'] === 'confirmee');
    $driver = $userRepo->getUserById($userId);
    $driverName = $driver ? ($driver['nom'] . ' ' . $driver['prenom']) : 'Le chauffeur';
    
    echo "   Nombre de participants: " . count($allParticipants) . "\n";
    echo "   Participants confirmés: " . count($participants) . "\n";
    echo "   Chauffeur: $driverName\n\n";
    
    // 3. Boucle d'envoi d'emails
    echo "3️⃣  Envoi des emails (logs dans PHP stderr)...\n";
    foreach ($participants as $participant) {
        $emailService->sendCancellationNotification(
            $participant,
            $trajet,
            $driverName,
            $trajet['prix'] ?? 0,
            "Test automatique"
        );
    }
    
    echo "✅ Emails envoyés! (vérifier les logs)\n";
    echo "   📧 Participants notifiés: " . count($participants) . "\n\n";
    
    echo "✅ Test terminé avec succès!\n";
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
EOF

# Exécuter le script PHP dans le conteneur
echo "📧 Logs de l'exécution:"
docker compose exec -T php bash -c "cd /var/www/html && TRIP_ID=$TRAJET_ID DRIVER_ID=$CHAUFFEUR_ID php /tmp/test_annul.php" 2>&1

# Attendre un peu et montrer les logs
echo ""
echo "4️⃣  Vérification des logs PHP..."
sleep 2
docker compose logs php 2>&1 | grep -E "(📧|sendCancellation|Test annulation|error_log)" | tail -20 || echo "   (aucun log trouvé)"

echo ""
echo "✅ Test d'annulation terminé!"
