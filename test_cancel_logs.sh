#!/bin/bash

echo "🧪 Test d'annulation avec vérification des emails"
echo "================================================="
echo ""

# Mettre à jour quelques trajets en statut planifié
echo "1️⃣  Préparation des données de test..."
docker compose exec -T db mysql -uroot -prootpassword ecoride -e "UPDATE covoiturage SET statut='planifie' WHERE covoiturage_id=49; INSERT IGNORE INTO participation (utilisateur_id, covoiturage_id, statut, nb_places) VALUES (9, 49, 'confirmee', 1), (10, 49, 'confirmee', 1), (11, 49, 'confirmee', 1);" 2>&1 | grep -v Warning

echo ""
echo "✅ Données préparées:"
echo "   - Trajet ID: 49"
echo "   - Conducteur ID: 8"

# Récupérer les participants
PARTICIPANTS=$(docker compose exec -T db mysql -uroot -prootpassword ecoride -N -e "SELECT COUNT(*) FROM participation WHERE covoiturage_id=49 AND statut='confirmee';" 2>&1 | grep -v Warning | tail -1)
echo "   - Participants confirmés: $PARTICIPANTS"

echo ""
echo "2️⃣  Exécution du test via le conteneur PHP..."
echo ""

# Créer un fichier de test dans le conteneur
docker compose exec -T php bash -c 'cat > /tmp/test_annul.php << '"'"'PHPEOF'"'"'
<?php
define("APP_ENV", "development");
error_reporting(E_ALL);

require_once "/var/www/vendor/autoload.php";

use App\Factories\ServiceLocator as SL;

error_log("🧪 === DÉBUT DU TEST D'\''ANNULATION === ");
error_log("Trajet ID: 49, Chauffeur ID: 8");

try {
    // Services
    $cancellationService = SL::getCancellationService();
    $emailService = SL::getEmailService();
    $trajetRepo = SL::getTrajetRepository();
    $participationRepo = SL::getParticipationRepository();
    $userRepo = SL::getUserRepository();
    
    // 1. Annuler le trajet
    $result = $cancellationService->cancelTripAsDriver(49, 8, "Test annulation automatique");
    error_log("✅ Trajet annulé, remboursement: " . ($result["refunded_amount"] ?? "N/A") . " crédits");
    
    // 2. Récupérer détails
    $trajet = $trajetRepo->getTrajetDetail(49);
    $allParticipants = $participationRepo->findByTrip(49);
    $participants = array_filter($allParticipants, fn($p) => $p["statut"] === "confirmee");
    $driver = $userRepo->getUserById(8);
    $driverName = $driver ? ($driver["nom"] . " " . $driver["prenom"]) : "Le chauffeur";
    
    error_log("📧 === ENVOI DES EMAILS === ");
    error_log("Participants à notifier: " . count($participants));
    error_log("Chauffeur: " . $driverName);
    
    // 3. Envoyer emails
    foreach ($participants as $i => $participant) {
        error_log("  📧 Participant " . ($i+1) . "/" . count($participants) . " (ID:" . $participant["utilisateur_id"] . ")");
        $emailService->sendCancellationNotification(
            $participant,
            $trajet,
            $driverName,
            $trajet["prix"] ?? 0,
            "Test annulation"
        );
    }
    
    error_log("✅ === FIN DU TEST === ");
    error_log("Tous les emails ont été loggés!");
    
} catch (Exception $e) {
    error_log("❌ ERREUR: " . $e->getMessage());
    error_log($e->getTraceAsString());
}
?>
PHPEOF
php /tmp/test_annul.php' 2>&1

echo ""
echo "3️⃣  Vérification des logs PHP (avec les marqueurs 📧)..."
echo "======================================================"
sleep 2

docker compose logs php 2>&1 | grep -E "(🧪|📧|✅|❌|FIN|DÉBUT)" | tail -30

echo ""
echo "✅ Test d'annulation terminé!"
