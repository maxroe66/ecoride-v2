<?php

namespace App\Services;

class EmailService
{
    /**
     * Envoie une notification d'annulation aux participants
     * @param array $participant - données du participant (email, prenom, pseudo)
     * @param array $tripDetails - détails du trajet (date, lieu_depart, lieu_arrivee, heure_depart, prix_personne)
     * @param string $driverName - pseudo du chauffeur
     * @param float $refundAmount - montant remboursé
     * @param string|null $reason - raison de l'annulation
     * @return bool - true si email envoyé avec succès
     */
    public function sendCancellationNotification(
        array $participant,
        array $tripDetails,
        string $driverName,
        float $refundAmount,
        ?string $reason = null
    ): bool {
        // Récupérer l'email du participant
        $to = $participant['email'] ?? null;
        if (!$to) {
            return false;
        }

        $prenom = $participant['prenom'] ?? 'Passager';
        $date = $tripDetails['date_depart'] ?? '';
        $heure = $tripDetails['heure_depart'] ?? '';
        $lieuDepart = $tripDetails['lieu_depart'] ?? '';
        $lieuArrivee = $tripDetails['lieu_arrivee'] ?? '';

        // Construire le sujet
        $subject = "❌ Annulation du covoiturage du $date à $heure";

        // Construire le corps HTML de l'email
        $body = "
        <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <h2>Covoiturage Annulé</h2>
                <p>Bonjour $prenom,</p>
                <p>Nous vous informons que le chauffeur <strong>$driverName</strong> a annulé le covoiturage prévu.</p>
                
                <h3>📍 Détails du trajet:</h3>
                <ul>
                    <li><strong>Départ:</strong> $lieuDepart</li>
                    <li><strong>Arrivée:</strong> $lieuArrivee</li>
                    <li><strong>Date:</strong> $date à $heure</li>
                </ul>
                
                <h3>💰 Remboursement:</h3>
                <p>Vous avez été remboursé de <strong>$refundAmount crédits</strong></p>
        ";

        // Ajouter la raison si fournie
        if ($reason) {
            $body .= "<h3>📝 Raison:</h3><p>$reason</p>";
        }

        $body .= "
                <p>Merci de votre compréhension!</p>
                <p><em>Équipe EcoRide</em></p>
            </body>
        </html>
        ";

        // ✅ EN DEV: Logger l'email au lieu de l'envoyer
        $logMessage = "📧 Email d'annulation envoyé à: $to | Sujet: $subject | Remboursement: $refundAmount crédits";
        error_log($logMessage);
        
        // Retourner true (simuler succès)
        return true;
        
        // En PROD avec SMTP: décommenter et configurer
        // $headers = "MIME-Version: 1.0\r\n";
        // $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        // return mail($to, $subject, $body, $headers);
    }

    /**
     * Envoie une notification au chauffeur quand un passager annule
     * @param array $driver - données du chauffeur (email, prenom, pseudo)
     * @param array $tripDetails - détails du trajet
     * @param string $passengerName - pseudo du passager
     * @return bool - true si email envoyé avec succès
     */
    public function sendParticipantCancellationToDriver(
        array $driver,
        array $tripDetails,
        string $passengerName
    ): bool {
        // Récupérer l'email du chauffeur
        $to = $driver['email'] ?? null;
        if (!$to) {
            return false;
        }

        $prenom = $driver['prenom'] ?? 'Chauffeur';
        $date = $tripDetails['date_depart'] ?? '';
        $heure = $tripDetails['heure_depart'] ?? '';
        $lieuDepart = $tripDetails['lieu_depart'] ?? '';
        $lieuArrivee = $tripDetails['lieu_arrivee'] ?? '';
        $nbPlaces = $tripDetails['nb_places_disponibles'] ?? 0;

        // Construire le sujet
        $subject = "⚠️ Un passager a annulé sa participation - $date à $heure";

        // Construire le corps HTML de l'email
        $body = "
        <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <h2>Annulation de Participation</h2>
                <p>Bonjour $prenom,</p>
                <p><strong>$passengerName</strong> a annulé sa participation à votre covoiturage.</p>
                
                <h3>📍 Détails du trajet:</h3>
                <ul>
                    <li><strong>Départ:</strong> $lieuDepart</li>
                    <li><strong>Arrivée:</strong> $lieuArrivee</li>
                    <li><strong>Date:</strong> $date à $heure</li>
                </ul>
                
                <h3>🪑 Places disponibles:</h3>
                <p>Vous disposez maintenant de <strong>$nbPlaces place(s)</strong> disponible(s).</p>
                
                <p>Vous pouvez continuer votre covoiturage ou l'annuler si vous le souhaitez.</p>
                <p><em>Équipe EcoRide</em></p>
            </body>
        </html>
        ";

        // ✅ EN DEV: Logger l'email au lieu de l'envoyer
        $logMessage = "📧 Email annulation participant envoyé à: $to (chauffeur) | Participant: $passengerName annulé";
        error_log($logMessage);
        
        // Retourner true (simuler succès)
        return true;
        
        // En PROD avec SMTP: décommenter et configurer
        // $headers = "MIME-Version: 1.0\r\n";
        // $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        // return mail($to, $subject, $body, $headers);
    }

    /**
     * Envoie une notification de fin de trajet aux participants
     * Leur demande de valider que tout s'est bien passé
     * @param array $participant - données du participant (email, prenom, pseudo)
     * @param array $tripDetails - détails du trajet (date_depart, heure_depart, lieu_depart, lieu_arrivee, covoiturage_id)
     * @return bool - true si email envoyé avec succès
     */
    public function sendEndTripNotification(
        array $participant,
        array $tripDetails
    ): bool {
        // Récupérer l'email du participant
        $to = $participant['email'] ?? null;
        if (!$to) {
            return false;
        }

        $prenom = $participant['prenom'] ?? 'Passager';
        $pseudo = $participant['pseudo'] ?? 'Participant';
        $date = $tripDetails['date_depart'] ?? '';
        $heure = $tripDetails['heure_depart'] ?? '';
        $lieuDepart = $tripDetails['lieu_depart'] ?? '';
        $lieuArrivee = $tripDetails['lieu_arrivee'] ?? '';
        $covoiturageId = $tripDetails['covoiturage_id'] ?? '';

        // Construire le sujet
        $subject = "✅ Votre trajet est terminé - Validez votre participation";

        // Construire le corps HTML de l'email
        $body = "
        <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <h2>Trajet Terminé</h2>
                <p>Bonjour $prenom,</p>
                <p>Votre covoiturage est arrivé à destination ! Merci d'avoir participé à EcoRide.</p>
                
                <h3>📍 Détails du trajet:</h3>
                <ul>
                    <li><strong>Départ:</strong> $lieuDepart</li>
                    <li><strong>Arrivée:</strong> $lieuArrivee</li>
                    <li><strong>Date:</strong> $date à $heure</li>
                </ul>
                
                <h3>⏭️ Prochaine étape:</h3>
                <p>Veuillez vous rendre sur votre espace EcoRide pour <strong>valider que le trajet s'est bien passé</strong>.</p>
                <p>Vous pourrez également soumettre un avis et une note pour le chauffeur.</p>
                
                <p style='margin-top: 30px; color: #666;'>
                    Si le trajet ne s'est pas déroulé comme prévu, vous pourrez signaler un problème lors de la validation.
                </p>
                
                <p>Merci de votre confiance !</p>
                <p><em>Équipe EcoRide</em></p>
            </body>
        </html>
        ";

        // ✅ EN DEV: Logger l'email au lieu de l'envoyer
        $logMessage = "📧 Email fin de trajet envoyé à: $to | Trajet: $covoiturageId | Passager: $pseudo";
        error_log($logMessage);
        
        // Retourner true (simuler succès)
        return true;
        
        // En PROD avec SMTP: décommenter et configurer
        // $headers = "MIME-Version: 1.0\r\n";
        // $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        // return mail($to, $subject, $body, $headers);
    }
}
