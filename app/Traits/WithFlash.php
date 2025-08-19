<?php

namespace App\Traits;

trait WithFlash
{
    protected function flashSuccess(string $msg): void
    {
        session()->flash('success', $msg);
    }

    protected function flashError(string $msg): void
    {
        session()->flash('error', $msg);
    }

    protected function flashInfo(string $msg): void
    {
        session()->flash('info', $msg);
    }
}
