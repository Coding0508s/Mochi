<?php

namespace App\Http\Controllers\Api;

use App\Actions\UpsertInstitutionFromExternal;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertExternalInstitutionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ExternalInstitutionController extends Controller
{
    public function upsert(UpsertExternalInstitutionRequest $request, string $sk): JsonResponse
    {
        $sk = trim(rawurldecode($sk));
        $patch = $request->validatedPatch();

        Log::info('external_institution_upsert', [
            'sk' => $sk,
            'keys' => array_keys($patch),
            'request_id' => $request->header('X-Request-Id'),
        ]);

        $result = app(UpsertInstitutionFromExternal::class)->execute($sk, $patch);

        return response()->json([
            'ok' => true,
            'sk' => $result['institution']->SKcode,
            'created' => $result['created'],
        ]);
    }
}
