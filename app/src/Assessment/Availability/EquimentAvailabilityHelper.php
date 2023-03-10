<?php
namespace Assessment\Availability;

use DateTime;
use PDO;

abstract class EquimentAvailabilityHelper {

	/**
	 * EquimentAvailabilityHelper constructor.
	 * @param PDO $oDatabaseConnection
	 */
	public function __construct(private PDO $oDatabaseConnection) {

	}

	/**
	 * Get the already opened connection to the assessment database
	 * @return PDO
	 */
	public final function getDatabaseConnection() : PDO{
		return $this->oDatabaseConnection;
	}


	public final function getEquipmentItems() : array{
		$aRows = $this->oDatabaseConnection->query("SELECT * FROM equipment ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
		return array_column($aRows, null, 'id');
	}


    public function isAvailableQuantity(int $equipment_id, int $quantity, DateTime $start, DateTime $end): bool
    {
        $query = "SELECT COALESCE(SUM(quantity), 0) as total_quantity 
                  FROM planning 
                  WHERE equipment = :equipment
                  AND NOT (start >= :end OR end <= :start)";
		
        $stmt = $this->oDatabaseConnection->prepare($query);
        $stmt->execute(['equipment' => $equipment_id, 'start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s')]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_quantity = $result['total_quantity'];
		return ($total_quantity + $quantity) <= $this->getEquipmentStock($equipment_id);
    }

    public function getShortagesResult(DateTime $start, DateTime $end): array
    {
        $query = "SELECT equipment, SUM(quantity) as total_quantity
                  FROM planning 
                  WHERE NOT (start >= :end OR end <= :start)
                  GROUP BY equipment";
        $stmt = $this->oDatabaseConnection->prepare($query);
        $stmt->execute(['start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s')]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $shortages = [];
        foreach ($result as $row) {
            $equipment_id = $row['equipment'];
            $total_quantity = $row['total_quantity'];
            $stock = $this->getEquipmentStock($equipment_id);
            if ($total_quantity > $stock) {
                $shortages[$equipment_id] = $stock - $total_quantity;
            }
        }
        return $shortages;
    }

    private function getEquipmentStock($equipment_id): int
    {
        $query = "SELECT stock FROM equipment WHERE id = :equipment";
        $stmt = $this->oDatabaseConnection->prepare($query);
        $stmt->execute(['equipment' => $equipment_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['stock'];
    }
}
