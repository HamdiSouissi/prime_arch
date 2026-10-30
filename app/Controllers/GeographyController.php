<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\GeographyRepository;

/**
 * Fournit les options de géographie pour les champs dépendants du cabinet.
 */
final class GeographyController
{
    public function delegations(Request $request): Response
    {
        $governorateId = filter_var($request->query('governorate_id'), FILTER_VALIDATE_INT);
        if ($governorateId === false || $governorateId < 1) {
            return Response::json(['error' => 'invalid_governorate'], 422);
        }
        return Response::json((new GeographyRepository())->delegations((int) $governorateId));
    }

    public function postalCodes(Request $request): Response
    {
        $delegationInput = $request->query('delegation_id');
        if ($delegationInput !== null && $delegationInput !== '') {
            $delegationId = filter_var($delegationInput, FILTER_VALIDATE_INT);
            if ($delegationId === false || $delegationId < 1) {
                return Response::json(['error' => 'invalid_delegation'], 422);
            }
            return Response::json((new GeographyRepository())->postalCodes((int) $delegationId));
        }

        $governorateId = filter_var($request->query('governorate_id'), FILTER_VALIDATE_INT);
        if ($governorateId === false || $governorateId < 1) {
            return Response::json(['error' => 'invalid_governorate'], 422);
        }
        return Response::json((new GeographyRepository())->postalCodesForGovernorate((int) $governorateId));
    }
}
