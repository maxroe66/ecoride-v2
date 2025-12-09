#!/bin/bash

echo "🧪 Test d'annulation avec vérification des emails en logs"
echo "======================================================"
echo ""

# Mettre à jour quelques trajets en statut planifié
echo "1️⃣  Préparation des données de test..."
docker compose exec -T db mysql -uroot -prootpassword ecoride -e "UPDATE covoiturage SET statut='planifie' WHERE covoiturage_id IN (48, 49, 50); INSERT IGNORE INTO participation (utilisateur_id, covoiturage_id, statut, nb_places) VALUES (9, 48, 'confirmee', 1), (10, 48, 'confirmee', 1), (11, 48, 'confirmee', 1);" 2>&1 | grep -v Warning

echo ""
echo "✅ Données préparées:"
echo "   - Trajet ID: 48"
echo "   - Conducteur ID: 8"

# Récupérer les participants
PARTICIPANTS=$(docker compose exec -T db mysql -uroot -prootpassword ecoride -N -e "SELECT COUNT(*) FROM participation WHERE covoiturage_id=48 AND statut='confirmee';" 2>&1 | grep -v Warning | tail -1)
echo "   - Participants confirmés: $PARTICIPANTS"

echo ""
echo "2️⃣  Test d'annulation via API PHP directement..."
echo ""

# Créer un script test simple qui montre les logs
cat > /tmp/test.php <<'PHPEOF'
<?php
define('APP_ENV', 'development');
define('APP_DEBUG', true);

// Charger le autoloader
require_once '/home/max/ecoride-v2/vendor/autoload.php';

use App\Factories\ServiceLocator as SL;
use App\Services\EmailService;

error_log("🔍 Test d'annulation de trajet");
error_log("Trajet ID: 48, Chauffeur ID: 8");

try {
    // Récupérer les services
    $cancellationService = SL::getCancellationService();
    $emailService = SL::getEmailService();
    $trajetRepo = SL::getTrajetRepository();
    $participationRepo = SL::getParticipationRepository();
    $userRepo = SL::getUserRepository();
    
    // Annuler le trajet
    $result = $cancellationService->cancelTripAsDriver(48, 8, "Test annulation");
    error_log("✅ Trajet annulé, remboursement: " . ($result['refunded_amount'] ?? 'N/A'));
    
    // Récupérer les détails
    $trajet = $trajetRepo->getTrajetDetail(48);
    $allParticipants = $participationRepo->findByTrip(48);
    $participants = array_filter($allParticipants, fn($p) => $p['statut'] === 'confirmee');
    $driver = $userRepo->getUserById(8);
    $driverName = $driver ? ($driver['nom'] . ' ' . $driver['prenom']) : 'Le chauffeur';
    
    error_log("📧 Participants à notifier: " . count($participants));
    
    // Envoyer les emails
    foreach ($participants as $participant) {
        error_log("   → Envoi email à participant ID: " . $participant['utilisateur_id']);
        $emailService->sendCancellationNotification(
            $participant,
            $trajet,
            $driverName,
            $trajet['prix'] ?? 0,
            "Test annulation"
        );
    }
    
    error_log("✅ Test terminé! Vérifier les logs pour voir les emails");
    
} catch (Exception $e) {
    error_log("❌ Erreur: " . $e->getMessage());
    error_log($e->getTraceAsString());
}
?>
PHPEOF

# Exécuter en montant le répertoire du projet
docker run --rm -v /home/max/ecoride-v2:/var/www/html \
  --network ecoride-v2_default \
  -e "MYSQL_HOST=ecoride-v2-db-1" \
  -e "MYSQL_USER=root" \
  -e "MYSQL_PASSWORD=rootpassword" \
  -e "MYSQL_DATABASE=ecoride" \
  php:8.3-cli php /tmp/test.php 2>&1

echo ""
echo "3️⃣  Vérification des logs (dernières 10 lignes):"
echo "=================================================="
sleep 2
docker compose logs php 2>&1 | grep -E "(📧|✅|❌|Test|Email|🔍)" | tail -15

echo ""
echo "✅ Test d'annulation terminé!"
