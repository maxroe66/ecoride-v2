<?php

namespace App\Factories;

use App\Repositories\{
    UserRepository,
    TrajetRepository,
    ParticipationRepository,
    VehicleRepository,
    CreditOperationRepository,
    MarqueRepository
};
use App\Services\{
    AuthService,
    UserService,
    TripService,
    ParticipationService,
    HistoryService,
    CancellationService,
    EmailService,
    ReviewService,
    VehicleService,
    CreditOperationService
};
use PDO;

/**
 * Service Locator - Fournit un accès centralisé aux repositories et services
 * Pattern singleton pour chaque instance
 */
class ServiceLocator
{
    private static ?PDO $db = null;
    
    // Repositories
    private static ?UserRepository $userRepository = null;
    private static ?TrajetRepository $trajetRepository = null;
    private static ?ParticipationRepository $participationRepository = null;
    private static ?VehicleRepository $vehicleRepository = null;
    private static ?CreditOperationRepository $creditOperationRepository = null;
    private static ?MarqueRepository $marqueRepository = null;
    
    // Services
    private static ?AuthService $authService = null;
    private static ?UserService $userService = null;
    private static ?TripService $tripService = null;
    private static ?ParticipationService $participationService = null;
    private static ?HistoryService $historyService = null;
    private static ?CancellationService $cancellationService = null;
    private static ?EmailService $emailService = null;
    private static ?ReviewService $reviewService = null;
    private static ?VehicleService $vehicleService = null;
    private static ?CreditOperationService $creditOperationService = null;

    /**
     * Obtient la connexion PDO (singleton)
     */
    private static function getDb(): PDO
    {
        if (self::$db === null) {
            self::$db = DatabaseFactory::getConnection();
        }
        return self::$db;
    }

    // ============ REPOSITORIES ============

    public static function getUserRepository(): UserRepository
    {
        if (self::$userRepository === null) {
            self::$userRepository = new UserRepository(self::getDb());
        }
        return self::$userRepository;
    }

    public static function getTrajetRepository(): TrajetRepository
    {
        if (self::$trajetRepository === null) {
            self::$trajetRepository = new TrajetRepository(self::getDb());
        }
        return self::$trajetRepository;
    }

    public static function getParticipationRepository(): ParticipationRepository
    {
        if (self::$participationRepository === null) {
            self::$participationRepository = new ParticipationRepository(self::getDb());
        }
        return self::$participationRepository;
    }

    public static function getVehicleRepository(): VehicleRepository
    {
        if (self::$vehicleRepository === null) {
            self::$vehicleRepository = new VehicleRepository(self::getDb());
        }
        return self::$vehicleRepository;
    }

    public static function getCreditOperationRepository(): CreditOperationRepository
    {
        if (self::$creditOperationRepository === null) {
            self::$creditOperationRepository = new CreditOperationRepository(self::getDb());
        }
        return self::$creditOperationRepository;
    }

    public static function getMarqueRepository(): MarqueRepository
    {
        if (self::$marqueRepository === null) {
            self::$marqueRepository = new MarqueRepository(self::getDb());
        }
        return self::$marqueRepository;
    }

    // ============ SERVICES ============

    public static function getAuthService(): AuthService
    {
        if (self::$authService === null) {
            self::$authService = new AuthService(self::getUserRepository());
        }
        return self::$authService;
    }

    public static function getUserService(): UserService
    {
        if (self::$userService === null) {
            self::$userService = new UserService(
                self::getUserRepository(),
                self::getVehicleRepository()
            );
        }
        return self::$userService;
    }

    public static function getTripService(): TripService
    {
        if (self::$tripService === null) {
            self::$tripService = new TripService(self::getTrajetRepository());
        }
        return self::$tripService;
    }

    public static function getParticipationService(): ParticipationService
    {
        if (self::$participationService === null) {
            self::$participationService = new ParticipationService(
                self::getParticipationRepository(),
                self::getTrajetRepository(),
                self::getUserRepository()
            );
        }
        return self::$participationService;
    }

    public static function getHistoryService(): HistoryService
    {
        if (self::$historyService === null) {
            self::$historyService = new HistoryService(
                self::getTrajetRepository(),
                self::getParticipationRepository()
            );
        }
        return self::$historyService;
    }

    public static function getCancellationService(): CancellationService
    {
        if (self::$cancellationService === null) {
            self::$cancellationService = new CancellationService(
                self::getTrajetRepository(),
                self::getParticipationRepository(),
                self::getUserRepository(),
                self::getCreditOperationRepository()
            );
        }
        return self::$cancellationService;
    }

    public static function getEmailService(): EmailService
    {
        if (self::$emailService === null) {
            self::$emailService = new EmailService();
        }
        return self::$emailService;
    }

    public static function getReviewService(): ReviewService
    {
        if (self::$reviewService === null) {
            self::$reviewService = new ReviewService();
        }
        return self::$reviewService;
    }

    public static function getVehicleService(): VehicleService
    {
        if (self::$vehicleService === null) {
            self::$vehicleService = new VehicleService();
        }
        return self::$vehicleService;
    }

    public static function getCreditOperationService(): CreditOperationService
    {
        if (self::$creditOperationService === null) {
            self::$creditOperationService = new CreditOperationService();
        }
        return self::$creditOperationService;
    }

    /**
     * Réinitialise toutes les instances (utile pour les tests)
     */
    public static function reset(): void
    {
        self::$db = null;
        self::$userRepository = null;
        self::$trajetRepository = null;
        self::$participationRepository = null;
        self::$vehicleRepository = null;
        self::$creditOperationRepository = null;
        self::$marqueRepository = null;
        self::$authService = null;
        self::$userService = null;
        self::$tripService = null;
        self::$participationService = null;
        self::$historyService = null;
        self::$cancellationService = null;
        self::$emailService = null;
        self::$reviewService = null;
        self::$vehicleService = null;
    }
}
