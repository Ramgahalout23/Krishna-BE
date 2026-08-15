<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseRepository
{
    protected Model $model;

    public function __construct()
    {
        $this->model = app($this->modelClass());
    }

    abstract protected function modelClass(): string;

    public function findById(string $id): ?Model
    {
        return $this->model->find($id);
    }

    public function findByIdOrFail(string $id): Model
    {
        return $this->model->findOrFail($id);
    }

    public function all(array $relations = []): Collection
    {
        return $this->model->with($relations)->get();
    }

    public function paginate(int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        return $this->model->with($relations)->latest()->paginate($perPage);
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(string $id, array $data): Model
    {
        $record = $this->findByIdOrFail($id);
        $record->update($data);
        return $record->fresh();
    }

    public function delete(string $id): bool
    {
        return $this->findByIdOrFail($id)->delete();
    }

    public function exists(string $id): bool
    {
        return $this->model->where('id', $id)->exists();
    }

    public function count(): int
    {
        return $this->model->count();
    }

    public function findByField(string $field, mixed $value): ?Model
    {
        return $this->model->where($field, $value)->first();
    }

    public function findAllByField(string $field, mixed $value): Collection
    {
        return $this->model->where($field, $value)->get();
    }

    public function pluck(string $column, ?string $key = null): \Illuminate\Support\Collection
    {
        return $this->model->pluck($column, $key);
    }
}
