<?php
declare(strict_types=1);

namespace App\Contracts;

use App\DTO\LogisticsInventoryCommand;
use App\DTO\LogisticsInventoryResult;

/** Coordinates Inventory commands only; implementations must not be replaced by production fakes. */
interface LogisticsInventoryOperationsInterface
{
    public function requestReservation(LogisticsInventoryCommand $command): LogisticsInventoryResult;
    public function confirmReservation(LogisticsInventoryCommand $command): LogisticsInventoryResult;
    public function releaseReservation(LogisticsInventoryCommand $command): LogisticsInventoryResult;
    public function requestExit(LogisticsInventoryCommand $command): LogisticsInventoryResult;
    public function confirmExit(LogisticsInventoryCommand $command): LogisticsInventoryResult;
}
