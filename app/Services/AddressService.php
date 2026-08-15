<?php

namespace App\Services;

use App\Repositories\AddressRepository;
use App\Exceptions\AppError;

class AddressService
{
    public function __construct(
        protected AddressRepository $addressRepository
    ) {}

    public function getUserAddresses(string $userId): array
    {
        return $this->addressRepository->getUserAddresses($userId)->toArray();
    }

    public function getById(string $id, string $userId): array
    {
        $address = $this->addressRepository->findById($id);
        if (!$address || $address->user_id !== $userId) {
            throw AppError::notFound('Address not found');
        }
        return $address->toArray();
    }

    public function create(string $userId, array $data): array
    {
        $address = $this->addressRepository->createWithDefaultHandling($userId, $data);
        return $address->toArray();
    }

    public function update(string $id, string $userId, array $data): array
    {
        $address = $this->addressRepository->findById($id);
        if (!$address || $address->user_id !== $userId) {
            throw AppError::notFound('Address not found');
        }
        $address = $this->addressRepository->updateWithDefaultHandling($id, $userId, $data);
        return $address->toArray();
    }

    public function delete(string $id, string $userId): void
    {
        $address = $this->addressRepository->findById($id);
        if (!$address || $address->user_id !== $userId) {
            throw AppError::notFound('Address not found');
        }
        $this->addressRepository->delete($id);
    }
}
