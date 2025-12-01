<?php

namespace App\Repositories;

use PDO;
use Exception;

class TrajetRepository implements TrajetRepositoryInterface
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
     * @return array - tableau des trajets trouvés (limité à 100 résultats)
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
        // - LIMIT 100 (limiter à 100 résultats max)

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
            LIMIT 100
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

    /**
     * Récupère les N prochaines dates avec trajets disponibles ET filtres appliqués
     * @param string $departure - lieu de départ
     * @param string $arrival - lieu d'arrivée
     * @param int $limit - nombre de dates à retourner
     * @param bool|null $economique - si true, filtre les trajets écologiques
     * @param float|null $maxPrice - prix maximum par personne
     * @param int|null $maxDuration - durée maximum en minutes
     * @param int|null $minRating - note minimale (1-5)
     * @return array - tableau des suggestions avec date et count
     */
    public function getNextAvailableDatesWithFilters(
        string $departure,
        string $arrival,
        int $limit = 3,
        ?bool $economique = null,
        ?float $maxPrice = null,
        ?int $maxDuration = null,
        ?int $minRating = null
    ): array {
        // Construire dynamiquement la requête SQL selon les filtres
        $sql = '
            SELECT 
                c.date_depart,
                COUNT(*) as count
            FROM covoiturage c
            JOIN utilisateur u ON c.conducteur_id = u.utilisateur_id
            JOIN voiture v ON c.voiture_id = v.voiture_id
            LEFT JOIN avis_fallback a ON c.covoiturage_id = a.covoiturage_id
            WHERE c.lieu_depart = :departure
            AND c.lieu_arrivee = :arrival
            AND c.date_depart >= CURDATE()
            AND c.statut = :statut
            AND c.nb_places > 0
        ';
        $params = [
            ':departure' => $departure,
            ':arrival' => $arrival,
            ':statut' => 'planifie'
        ];

        // Filtres écologiques
        if ($economique === true) {
            $sql .= ' AND c.est_ecologique = 1';
        }

        // Filtre prix max
        if ($maxPrice !== null) {
            $sql .= ' AND c.prix_personne <= :maxPrice';
            $params[':maxPrice'] = $maxPrice;
        }

        // Filtre durée max (en minutes)
        if ($maxDuration !== null) {
            $sql .= ' AND (TIME_TO_SEC(TIMEDIFF(c.heure_arrivee, c.heure_depart)) / 60) <= :maxDuration';
            $params[':maxDuration'] = $maxDuration;
        }

        // Si on filtre par rating
        if ($minRating !== null) {
            $sql .= ' GROUP BY c.date_depart HAVING AVG(a.note) >= :minRating';
            $params[':minRating'] = $minRating;
        } else {
            $sql .= ' GROUP BY c.date_depart';
        }

        $sql .= ' ORDER BY c.date_depart ASC LIMIT :limit';
        $params[':limit'] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => [
            'date' => $row['date_depart'],
            'count' => (int)$row['count']
        ], $results ?? []);
    }
    /**
     * 5. Recherche les trajets avec filtres avancés
     * @param string $departure - lieu de départ
     * @param string $arrival - lieu d'arrivée
     * @param string $date - date au format YYYY-MM-DD
     * @param bool|null $economique - si true, filtre les trajets écologiques
     * @param float|null $maxPrice - prix maximum par personne
     * @param int|null $maxDuration - durée maximum en minutes
     * @param int|null $minRating - note minimale (1-5)
     * @return array - tableau des trajets trouvés
     */


    public function searchTrajetsWithFilters(
        string $departure,
        string $arrival,
        string $date,
        ?bool $economique = null,
        ?float $maxPrice = null,
        ?int $maxDuration = null,
        ?int $minRating = null
    ): array {

        // Construire dynamiquement la requête SQL selon les filtres
        $sql = '
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
            JOIN voiture v ON c.voiture_id = v.voiture_id
            LEFT JOIN avis_fallback a ON c.covoiturage_id = a.covoiturage_id
            WHERE c.lieu_depart = :departure
            AND c.lieu_arrivee = :arrival
            AND c.date_depart = :date
            AND c.statut = :statut
            AND c.nb_places > 0
        ';
        $params = [
            ':departure' => $departure,
            ':arrival' => $arrival,
            ':date' => $date,
            ':statut' => 'planifie'
        ];

        // Filtres écologiques
        if ($economique === true) {
            $sql .= ' AND c.est_ecologique = 1';
        }

        // Filtre prix max
        if ($maxPrice !== null) {
            $sql .= ' AND c.prix_personne <= :maxPrice';
            $params[':maxPrice'] = $maxPrice;
        }

        // Filtre durée max (en minutes)
        if ($maxDuration !== null) {
            $sql .= ' AND (TIME_TO_SEC(TIMEDIFF(c.heure_arrivee, c.heure_depart)) / 60) <= :maxDuration';
            $params[':maxDuration'] = $maxDuration;
        }

        // Si on filtre par rating
        if ($minRating !== null) {
            $sql .= ' GROUP BY c.covoiturage_id HAVING AVG(a.note) >= :minRating';
            $params[':minRating'] = $minRating;
        }

        $sql .= ' ORDER BY c.heure_depart ASC LIMIT 100';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $trajets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $trajets ?? [];
    }
        /**
     * Récupère le détail complet d'un covoiturage par ID
     * @param int $id - covoiturage_id
     * @return array - détail du trajet avec conducteur, véhicule, et stats avis
     */
    public function getTrajetDetail(int $id): array
    {
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
                u.utilisateur_id,
                u.pseudo,
                v.modele,
                m.libelle AS marque,
                v.energie,
                AVG(COALESCE(a.note, 0)) AS avg_rating,
                COUNT(a.covoiturage_id) AS reviews_count
            FROM covoiturage c
            JOIN utilisateur u ON c.conducteur_id = u.utilisateur_id
            JOIN voiture v ON c.voiture_id = v.voiture_id
            JOIN marque m ON v.marque_id = m.marque_id
            LEFT JOIN avis_fallback a ON c.covoiturage_id = a.covoiturage_id
            WHERE c.covoiturage_id = :id
            GROUP BY c.covoiturage_id
            LIMIT 1
        ');

        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: [];
    }

    public function updateNbPlaces(int $covoiturageId, int $amount): bool
    {
        // 1. Validations
        if ($covoiturageId <= 0) {
            throw new Exception('Invalid trip ID');
        }

        if ($amount === 0) {
            throw new Exception('Amount cannot be zero');
        }

        try {
            // 2. Vérifier que le trajet existe ET que les places ne deviennent pas négatives
            if ($amount < 0) {
                // Si on retire des places, vérifier qu'il y en a assez
                $stmt = $this->db->prepare('
                    SELECT nb_places FROM covoiturage WHERE covoiturage_id = :id
                ');
                $stmt->execute([':id' => $covoiturageId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$result) {
                    throw new Exception('Trip not found');
                }

                // Vérifier qu'on n'essaie pas de retirer plus que ce qu'il y a
                if ($result['nb_places'] + $amount < 0) {
                    throw new Exception('Cannot remove more seats than available');
                }
            }

            // 3. Mettre à jour le nombre de places
            $updateStmt = $this->db->prepare('
                UPDATE covoiturage 
                SET nb_places = nb_places + :amount 
                WHERE covoiturage_id = :id
            ');

            $updateStmt->execute([
                ':amount' => $amount,
                ':id' => $covoiturageId
            ]);

            // Vérifier que la mise à jour a réussi
            if ($updateStmt->rowCount() === 0) {
                throw new Exception('Trip not found');
            }

            return true;

        } catch (Exception $e) {
            throw new Exception('Failed to update seats: ' . $e->getMessage());
        }
    }
}
