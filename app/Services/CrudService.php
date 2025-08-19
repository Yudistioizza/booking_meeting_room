<?php

namespace App\Services;

class CrudService
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected string $modelClass)
    {
        //
    }

    public function create(array $data)
    {
        return $this->modelClass::create($data);
    }

    public function update(int $id, array $data)
    {
        $model = $this->modelClass::withTrashed()->findOrFail($id);
        $model->update($data);
        return $model;
    }

    public function softDelete(int $id)
    {
        return $this->modelClass::findOrFail($id)->delete();
    }

    public function forceDelete(int $id)
    {
        return $this->modelClass::onlyTrashed()->findOrFail($id)->forceDelete();
    }

    public function restore(int $id)
    {
        return $this->modelClass::onlyTrashed()->findOrFail($id)->restore();
    }
}
