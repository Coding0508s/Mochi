<?php

namespace App\Http\Controllers\Api;

use App\Actions\UpsertInstitutionFromExternal;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertExternalInstitutionRequest;
use App\Models\ExternalAssignmentInboundLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExternalInstitutionController extends Controller
{
    public function upsert(UpsertExternalInstitutionRequest $request, string $sk): JsonResponse
    {
        $sk = trim(rawurldecode($sk));
        $patch = $request->validatedPatch();
        $replacesSk = $request->replacesSk();

        Log::info('external_institution_upsert', [
            'sk' => $sk,
            'replaces_sk' => $replacesSk,
            'keys' => array_keys($patch),
            'request_id' => $request->header('X-Request-Id'),
        ]);

        $inboundLog = ExternalAssignmentInboundLog::query()->create([
            'sk_code' => $sk,
            'co' => $patch['co'] ?? null,
            'tr' => $patch['tr'] ?? null,
            'cs' => $patch['cs'] ?? null,
            'raw_body' => $request->all(),
            'status' => 'received',
            'received_at' => now(),
        ]);

        try {
            $result = app(UpsertInstitutionFromExternal::class)->execute($sk, $patch, $replacesSk);
        } catch (Throwable $e) {
            $inboundLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $inboundLog->update([
            'status' => 'applied',
            'applied_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'sk' => $result['institution']->SKcode,
            'created' => $result['created'],
        ]);
    }
}
