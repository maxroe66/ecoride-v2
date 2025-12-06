<?php

namespace App\Validators;

use Exception;

class TripValidator
{
    /**
     * Valide les données pour créer un trajet
     * @param array $data - données du formulaire
     * @param int $conducteurId - ID du chauffeur
     * @return array - données validées
     * @throws Exception si validation échoue
     */
    public static function validateCreateTrip(array $data, int $conducteurId): array
    {
        // 1. Vérifier conducteurId
        if ($conducteurId <= 0) {
            throw new Exception('Invalid driver ID');
        }

        // 2. Vérifier lieu_depart
        if (empty($data['lieu_depart']) || !is_string($data['lieu_depart'])) {
            throw new Exception('Le lieu de départ est obligatoire');
        }

        // 3. Vérifier lieu_arrivee
        if (empty($data['lieu_arrivee']) || !is_string($data['lieu_arrivee'])) {
            throw new Exception('Le lieu d\'arrivée est obligatoire');
        }

        // 4. Vérifier date_depart (format YYYY-MM-DD)
        if (empty($data['date_depart'])) {
            throw new Exception('La date de départ est obligatoire');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date_depart'])) {
            throw new Exception('Format de date invalide (attendu: YYYY-MM-DD)');
        }
        // Vérifier que la date n'est pas dans le passé
        if (strtotime($data['date_depart']) < strtotime('today')) {
            throw new Exception('La date de départ ne peut pas être dans le passé');
        }

        // 5. Vérifier heure_depart (format HH:MM)
        if (empty($data['heure_depart'])) {
            throw new Exception('L\'heure de départ est obligatoire');
        }
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['heure_depart'])) {
            throw new Exception('Format d\'heure invalide (attendu: HH:MM)');
        }

        // 6. Vérifier nb_places
        if (!isset($data['nb_places']) || !is_numeric($data['nb_places'])) {
            throw new Exception('Le nombre de places est obligatoire');
        }
        $nbPlaces = (int)$data['nb_places'];
        if ($nbPlaces <= 0) {
            throw new Exception('Le nombre de places doit être supérieur à 0');
        }
        if ($nbPlaces > 8) {
            throw new Exception('Le nombre de places ne peut pas dépasser 8');
        }

        // 7. Vérifier prix_personne
        if (!isset($data['prix_personne']) || !is_numeric($data['prix_personne'])) {
            throw new Exception('Le prix par personne est obligatoire');
        }
        $prix = (float)$data['prix_personne'];
        if ($prix < 2) {
            throw new Exception('Le prix minimum est de 2 crédits (commission plateforme)');
        }
        if ($prix > 1000) {
            throw new Exception('Le prix ne peut pas dépasser 1000 crédits');
        }

        // 8. Vérifier voiture_id
        if (empty($data['voiture_id']) || !is_numeric($data['voiture_id'])) {
            throw new Exception('Le véhicule est obligatoire');
        }
        $voitureId = (int)$data['voiture_id'];
        if ($voitureId <= 0) {
            throw new Exception('Invalid vehicle ID');
        }

        // Tout est OK, retourner les données validées et formatées
        return [
            'lieu_depart' => trim($data['lieu_depart']),
            'lieu_arrivee' => trim($data['lieu_arrivee']),
            'date_depart' => $data['date_depart'],
            'heure_depart' => substr($data['heure_depart'], 0, 5), // Garder HH:MM seulement
            'nb_places' => $nbPlaces,
            'prix_personne' => $prix,
            'voiture_id' => $voitureId,
            'conducteur_id' => $conducteurId
        ];
    }

    /**
     * Valide un ID de trajet
     * @param mixed $id - ID à valider
     * @return int - ID validé
     * @throws Exception si l'ID est invalide
     */
    public static function validateTripId($id): int
    {
        if (!$id || !is_numeric($id) || (int)$id <= 0) {
            throw new Exception('ID de trajet invalide', 400);
        }
        return (int)$id;
    }
    
}
