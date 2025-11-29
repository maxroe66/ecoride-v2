<?php
namespace App\Repositories;

use PDO;
use Exception;

class TrajetRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Recherche les trajets disponibles
     * @param string $departure - lieu de départ
     * @param string $arrival - lieu d'arrivée
     * @param string $date - date au format YYYY-MM-DD
     * @return array - tableau des trajets trouvés
     */
    public function searchTrajets(string $departure, string $arrival, string $date): array
    {
        // 1. Préparer la requête SQL
        // Chercher les trajets où:
        // - lieu_depart = $departure
        // - lieu_arrivee = $arrival
        // - date_depart = $date
        // - statut = 'planifie' (seulement les trajets à venir)
        // - nb_places > 0 (avec places disponibles)
        
        $stmt = $this->db->prepare('
            SELECT 
                c.covoiturage_id,
                c.date_depart,
                c.heure_depart,
                c.lieu_depart,
                c.heure_arrivee,
                c.lieu_arrivee,
                c.nb_places,
                c.prix_personne,
                c.est_ecologique,
                u.pseudo,
                u.utilisateur_id
            FROM covoiturage c
            JOIN utilisateur u ON c.conducteur_id = u.utilisateur_id
            WHERE c.lieu_depart = :departure
              AND c.lieu_arrivee = :arrival
              AND c.date_depart = :date
              AND c.statut = :statut
              AND c.nb_places > 0
            ORDER BY c.heure_depart ASC
        ');

        // 2. Exécuter la requête
        $stmt->execute([
            ':departure' => $departure,
            ':arrival' => $arrival,
            ':date' => $date,
            ':statut' => 'planifie'
        ]);

        // 3. Récupérer tous les résultats
        $trajets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Retourner le tableau (peut être vide si aucun trajet)
        return $trajets ?? [];
    }
    /**
     * Récupère les N prochaines dates avec trajets disponibles
     * @param string $departure - lieu de départ
     * @param string $arrival - lieu d'arrivée
     * @param int $limit - nombre de dates à retourner
     * @return array - tableau des suggestions avec date et count
     */
    public function getNextAvailableDates(string $departure, string $arrival, int $limit = 3): array
    {
        // 1. Préparer la requête SQL
        // Chercher les PROCHAINES DATES (à partir d'aujourd'hui)
        // avec au moins 1 trajet disponible
        
        $stmt = $this->db->prepare('
            SELECT 
                c.date_depart,
                COUNT(*) as count
            FROM covoiturage c
            WHERE c.lieu_depart = :departure
            AND c.lieu_arrivee = :arrival
            AND c.date_depart >= CURDATE()
            AND c.statut = :statut
            AND c.nb_places > 0
            GROUP BY c.date_depart
            ORDER BY c.date_depart ASC
            LIMIT :limit
        ');

        // 2. Exécuter la requête
        $stmt->execute([
            ':departure' => $departure,
            ':arrival' => $arrival,
            ':statut' => 'planifie',
            ':limit' => $limit
        ]);

        // 3. Récupérer tous les résultats
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Formater le résultat
        return array_map(fn($row) => [
            'date' => $row['date_depart'],
            'count' => (int)$row['count']
        ], $results ?? []);
    }
}