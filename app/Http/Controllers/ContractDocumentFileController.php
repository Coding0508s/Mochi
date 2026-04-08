<?php

namespace App\Http\Controllers;

use App\Models\ContractDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractDocumentFileController extends Controller
{
    public function download(ContractDocument $contractDocument): StreamedResponse
    {
        $disk = $contractDocument->stored_disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($contractDocument->stored_path), 404);

        return Storage::disk($disk)->download(
            $contractDocument->stored_path,
            $contractDocument->original_filename
        );
    }

    public function preview(ContractDocument $contractDocument): StreamedResponse
    {
        $disk = $contractDocument->stored_disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($contractDocument->stored_path), 404);

        return Storage::disk($disk)->response(
            $contractDocument->stored_path,
            $contractDocument->original_filename,
            ['Content-Disposition' => 'inline; filename*=UTF-8\'\''.rawurlencode($contractDocument->original_filename)]
        );
    }
}
