<?php

namespace App\Traits;

trait WithCrud
{
    public bool $isEditMode = false;
    public ?int $editId = null;
    public $modelToDelete;
    public $modelToForceDelete;
    public string $forceDeleteConfirmation = '';

    public function create(): void
    {
        $this->resetForm();
        $this->dispatch('open-modal', name: 'crud-form-modal');
    }

    public function edit(int $id): void
    {
        $this->resetForm();
        $this->isEditMode = true;
        $this->editId = $id;
        $this->fillForm($id);   // implement di component
        $this->dispatch('open-modal', name: 'crud-form-modal');
    }

    public function prepareDelete(int $userId): void
    {
        if ($userId === auth()->id()) {
            $this->flashError('Anda tidak dapat menghapus akun sendiri.');
            return;
        }

        $this->modelToDelete = $this->findModel($userId);
        $this->dispatch('open-modal', name: 'delete-confirmation-modal');
    }

    public function prepareForceDelete(int $userId): void
    {
        if ($userId === auth()->id()) {
            $this->flashError('Anda tidak dapat menghapus akun sendiri secara permanen.');
            return;
        }

        $this->modelToForceDelete = $this->findModel($userId, true);
        $this->forceDeleteConfirmation = '';
        $this->dispatch('open-modal', name: 'force-delete-confirmation-modal');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->dispatch('modal-close', name: 'crud-form-modal');
    }

    public function cancelDelete(): void
    {
        $this->modelToDelete = null;
        $this->dispatch('modal-close', name: 'delete-confirmation-modal');
    }

    public function cancelForceDelete(): void
    {
        $this->modelToForceDelete = null;
        $this->forceDeleteConfirmation = '';
        $this->dispatch('modal-close', name: 'force-delete-confirmation-modal');
    }

    abstract protected function findModel(int $id, bool $trashed = false);
    abstract protected function fillForm(int $id);
    abstract protected function resetForm(): void;
}
