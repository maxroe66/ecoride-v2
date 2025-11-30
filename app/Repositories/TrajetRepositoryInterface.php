<?php

namespace App\Repositories;

interface TrajetRepositoryInterface
{
    public function searchTrajets(string $departure, string $arrival, string $date): array;
    public function searchTrajetsWithFilters(string $departure, string $arrival, string $date, ?bool $economique, ?float $maxPrice, ?int $maxDuration, ?int $minRating): array;
    public function getNextAvailableDates(string $departure, string $arrival, int $limit): array;
    public function getNextAvailableDatesWithFilters(string $departure, string $arrival, int $limit, ?bool $economique, ?float $maxPrice, ?int $maxDuration, ?int $minRating): array;
}
