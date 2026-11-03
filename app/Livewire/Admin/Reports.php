<?php

namespace App\Livewire\Admin;

use App\Services\ReportService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Laporan & utilisasi')]
class Reports extends Component
{
    #[Url(as: 'dari')]
    public string $from = '';
    #[Url(as: 'sampai')]
    public string $to = '';

    public function mount(): void
    {
        $this->to = $this->to ?: now()->toDateString();
        $this->from = $this->from ?: now()->subDays(29)->toDateString();
    }

    public function preset(int $days): void
    {
        $this->to = now()->toDateString();
        $this->from = now()->subDays($days - 1)->toDateString();
    }

    public function render(ReportService $reports)
    {
        try {
            $from = Carbon::parse($this->from);
            $to = Carbon::parse($this->to);
        } catch (\Throwable) {
            $from = now()->subDays(29);
            $to = now();
        }
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return view('livewire.admin.reports', ['r' => $reports->summary($from, $to), 'fromD' => $from, 'toD' => $to]);
    }
}
