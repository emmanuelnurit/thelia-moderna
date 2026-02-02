<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace Moderna\DTO;

use Thelia\Model\Address;

/**
 * Data Transfer Object for addresses.
 */
final class AddressDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly int $titleId,
        public readonly string $firstname,
        public readonly string $lastname,
        public readonly string $company,
        public readonly string $address1,
        public readonly ?string $address2,
        public readonly ?string $address3,
        public readonly string $zipcode,
        public readonly string $city,
        public readonly int $countryId,
        public readonly ?int $stateId,
        public readonly string $phone,
        public readonly ?string $cellphone,
        public readonly bool $isDefault,
    ) {}

    public static function fromAddress(Address $address): self
    {
        return new self(
            id: $address->getId(),
            label: $address->getLabel() ?? '',
            titleId: $address->getTitleId(),
            firstname: $address->getFirstname() ?? '',
            lastname: $address->getLastname() ?? '',
            company: $address->getCompany() ?? '',
            address1: $address->getAddress1() ?? '',
            address2: $address->getAddress2(),
            address3: $address->getAddress3(),
            zipcode: $address->getZipcode() ?? '',
            city: $address->getCity() ?? '',
            countryId: $address->getCountryId(),
            stateId: $address->getStateId(),
            phone: $address->getPhone() ?? '',
            cellphone: $address->getCellphone(),
            isDefault: (bool) $address->getIsDefault(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'titleId' => $this->titleId,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'company' => $this->company,
            'address1' => $this->address1,
            'address2' => $this->address2,
            'address3' => $this->address3,
            'zipcode' => $this->zipcode,
            'city' => $this->city,
            'countryId' => $this->countryId,
            'stateId' => $this->stateId,
            'phone' => $this->phone,
            'cellphone' => $this->cellphone,
            'isDefault' => $this->isDefault,
        ];
    }

    public function getFullName(): string
    {
        return trim($this->firstname . ' ' . $this->lastname);
    }

    public function getFullAddress(): string
    {
        $parts = array_filter([
            $this->address1,
            $this->address2,
            $this->address3,
            $this->zipcode . ' ' . $this->city,
        ]);

        return implode(', ', $parts);
    }
}
