<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Core\Database\Database;
use App\Domain\Entities\RoomType;
use App\Domain\Interfaces\Repositories\RoomTypeRepositoryInterface;

class RoomTypeRepository implements RoomTypeRepositoryInterface
{

    private Database $database;
    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function findById(int $id): ?RoomType
    {
        // TODO: Implement findById() method.
        $stmt = $this->database->query(
            "SELECT * FROM room_types WHERE id = ?",
            [$id]
        );
        $data = $stmt->fetch();
        return $data ? $this->mapToEntity($data) : null;
    }

    public function findAll(): array
    {
        // TODO: Implement findAll() method.
        $stmt = $this->database->query("SELECT * FROM room_types ORDER BY name ASC");
        // Optimized: Use fetchAll instead of while loop for better performance
        $results = $stmt->fetchAll();
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findByCapacity(int $capacity): array
    {
        // TODO: Implement findByCapacity() method.
        $stmt = $this->database->query(
            "SELECT * FROM room_types WHERE capacity >= ? ORDER BY price_per_night ASC",
            [$capacity]
        );
        // Optimized: Use fetchAll instead of while loop
        $results = $stmt->fetchAll();
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findByPriceRange(float $minPrice, float $maxPrice): array
    {
        // TODO: Implement findByPriceRange() method.
        $stmt = $this->database->query(
            "SELECT * FROM room_types WHERE price_per_night BETWEEN ? AND ? ORDER BY price_per_night ASC",
            [$minPrice, $maxPrice]
        );
        // Optimized: Use fetchAll instead of while loop
        $results = $stmt->fetchAll();
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function findByAmenity(string $amenity): array
    {
        // TODO: Implement findByAmenity() method.
        // Note: Currently uses LIKE for amenity search. For better performance in production,
        // consider adding a FULLTEXT index: ALTER TABLE room_types ADD FULLTEXT INDEX idx_amenities (amenities)
        // Then use: SELECT * FROM room_types WHERE MATCH(amenities) AGAINST(? IN BOOLEAN MODE)
        $stmt = $this->database->query(
            "SELECT * FROM room_types WHERE amenities LIKE ? ORDER BY price_per_night ASC",
            ['%' . $amenity . '%']
        );
        // Optimized: Use fetchAll instead of while loop
        $results = $stmt->fetchAll();
        return array_map([$this, 'mapToEntity'], $results);
    }

    public function save(RoomType $newRoomType): bool
    {
        // TODO: Implement save() method.

        $result = $this->database->query(
            "INSERT INTO room_types (name, description, capacity, price_per_night, amenities) VALUES (?, ?, ?, ?, ?)",
            [
                $newRoomType->getName(),
                $newRoomType->getDescription(),
                $newRoomType->getCapacity(),
                $newRoomType->getPricePerNight(),
                implode(', ', $newRoomType->getAmenities()),
            ]
        );
        return true;
    }

    public function update(RoomType $newRoomType, int $roomTypeToUpdate): bool
    {
        // TODO: Implement update() method.
        $result = $this->database->query(
            "UPDATE room_types SET name = ?, description = ?, capacity = ?, price_per_night = ?, amenities = ? WHERE id = ?",
            [
                $newRoomType->getName(),
                $newRoomType->getDescription(),
                $newRoomType->getCapacity(),
                $newRoomType->getPricePerNight(),
                implode(', ', $newRoomType->getAmenities()),
                $roomTypeToUpdate
            ]
        );
        return true;
    }

    public function delete(int $roomTypeToDelete): bool
    {
        // TODO: Implement delete() method.
        $this->database->query("DELETE FROM room_types WHERE id = ?", [$roomTypeToDelete]);
        return true;
    }

    public function exists(int $id): bool
    {
        // TODO: Implement exists() method.
        $stmt = $this->database->query("SELECT COUNT(*) as count FROM room_types WHERE id = ?", [$id]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }



    private function mapToEntity(array $data): RoomType
    {
        return new RoomType(
            $data['id'],
            $data['name'],
            $data['description'],
            $data['capacity'],
            $data['price_per_night'],
            array_map('trim', explode(', ', $data['amenities'])),
            $data['created_at'],
            $data['updated_at']
        );
    }
}