<?php

namespace App\Repositories;

use App\Models\Address;
use Illuminate\Database\Eloquent\Collection;

class AddressRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return Address::class;
    }

    public function getUserAddresses(string $userId): Collection
    {
        return Address::where('user_id', $userId)->get();
    }

    public function getUserDefault(string $userId): ?Address
    {
        return Address::where('user_id', $userId)->where('is_default', true)->first();
    }

    public function createWithDefaultHandling(string $userId, array $data): Address
    {
        if (!empty($data['is_default'])) {
            Address::where('user_id', $userId)->update(['is_default' => false]);
        }

        $data['user_id'] = $userId;
        return Address::create($data);
    }

    public function updateWithDefaultHandling(string $id, string $userId, array $data): Address
    {
        if (!empty($data['is_default'])) {
            Address::where('user_id', $userId)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $address = $this->findByIdOrFail($id);
        $address->update($data);
        return $address->fresh();
    }
}
