<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function export(Request $request, ReportService $reports)
    {
        $data = $request->validate(['from' => 'required|date', 'to' => 'required|date|after_or_equal:from']);
        $from = Carbon::parse($data['from']);
        $to = Carbon::parse($data['to']);
        AuditLog::record('report.exported', null, "Ekspor CSV {$from->toDateString()} s.d. {$to->toDateString()}");

        return response()->streamDownload($reports->csv($from, $to), "booking-{$from->toDateString()}-{$to->toDateString()}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
