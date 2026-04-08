<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstitutionListController extends Controller
{
    private const SORTABLE = ['SKcode', 'AccountName', 'Gubun', 'Phone', 'EnglishName'];

    public function index(Request $request)
    {
        $query = Institution::query()->with('accountInfo');

        if ($request->filled('q')) {
            $query->search($request->string('q'));
        }

        if ($request->filled('gubun')) {
            $query->ofType($request->string('gubun'));
        }

        $sort = $request->string('sort', 'SKcode')->toString();
        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'SKcode';
        }

        $dir = strtolower($request->string('dir', 'asc')->toString()) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $dir);

        $institutions = $query->paginate(20)->withQueryString();

        $gubunOptions = Institution::query()
            ->whereNotNull('Gubun')
            ->where('Gubun', '!=', '')
            ->distinct()
            ->orderBy('Gubun')
            ->pluck('Gubun');

        return view('pages.admin.institutions.index', [
            'institutions' => $institutions,
            'gubunOptions' => $gubunOptions,
            'sort' => $sort,
            'dir' => $dir,
            'q' => $request->string('q')->toString(),
            'gubun' => $request->string('gubun')->toString(),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $query = Institution::query()->with('accountInfo');

        if ($request->filled('q')) {
            $query->search($request->string('q'));
        }

        if ($request->filled('gubun')) {
            $query->ofType($request->string('gubun'));
        }

        $query->orderBy('SKcode');

        $filename = 'institution_list_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, [
                'SKcode', '기관명', 'CO', 'TR', 'CS', 'Type', '구분',
                '기존원', '인원차', '기관연락처', '주소', '평가기관명',
            ]);

            $query->chunk(200, function ($chunk) use ($out): void {
                foreach ($chunk as $row) {
                    $info = $row->accountInfo;
                    fputcsv($out, [
                        $row->SKcode,
                        $row->AccountName,
                        $info?->CO,
                        $info?->TR,
                        $info?->CS,
                        $info?->Customer_Type,
                        $row->Gubun,
                        '',
                        '',
                        $row->Phone,
                        $row->Address,
                        $row->EnglishName,
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
