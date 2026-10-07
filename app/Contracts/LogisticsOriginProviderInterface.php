<?php
declare(strict_types=1);

namespace App\Contracts;

use App\DTO\LogisticsAuthorizedOriginLine;
use App\DTO\LogisticsOriginLineQuery;

/** Reads and validates authoritative Venta/Traslado data; it does not create local copies. */
interface LogisticsOriginProviderInterface
{
    /**
     * Returns only a line authorized by its owning module and matching the query's branch,
     * warehouse, product, unit, lot and requested quantity. The result includes approved,
     * already-used and available exact decimal quantities. A VENTA result must include its
     * authoritative clientId; a TRASLADO result must have clientId=null. Client identity is
     * never accepted from LogisticsOriginLineQuery or the request. Implementations signal stable
     * LogisticsErrorCode failures (NOT_FOUND, FORBIDDEN, REFERENCE_CONFLICT,
     * QUANTITY_EXCEEDED, INVALID_STATE or CONFLICT).
     */
    public function authorizedLine(LogisticsOriginLineQuery $query): LogisticsAuthorizedOriginLine;
}
