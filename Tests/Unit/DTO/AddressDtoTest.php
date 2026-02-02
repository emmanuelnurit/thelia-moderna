<?php

declare(strict_types=1);

namespace Moderna\Tests\Unit\DTO;

use Moderna\DTO\AddressDto;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AddressDto.
 */
class AddressDtoTest extends TestCase
{
    private function createDefaultDto(): AddressDto
    {
        return new AddressDto(
            id: 1,
            label: 'Home',
            titleId: 1,
            firstname: 'John',
            lastname: 'Doe',
            company: 'ACME Corp',
            address1: '123 Main Street',
            address2: 'Apt 4B',
            address3: null,
            zipcode: '75001',
            city: 'Paris',
            countryId: 64,
            stateId: null,
            phone: '+33 1 23 45 67 89',
            cellphone: '+33 6 12 34 56 78',
            isDefault: true,
        );
    }

    public function testConstructor(): void
    {
        $dto = $this->createDefaultDto();

        $this->assertSame(1, $dto->id);
        $this->assertSame('Home', $dto->label);
        $this->assertSame(1, $dto->titleId);
        $this->assertSame('John', $dto->firstname);
        $this->assertSame('Doe', $dto->lastname);
        $this->assertSame('ACME Corp', $dto->company);
        $this->assertSame('123 Main Street', $dto->address1);
        $this->assertSame('Apt 4B', $dto->address2);
        $this->assertNull($dto->address3);
        $this->assertSame('75001', $dto->zipcode);
        $this->assertSame('Paris', $dto->city);
        $this->assertSame(64, $dto->countryId);
        $this->assertNull($dto->stateId);
        $this->assertSame('+33 1 23 45 67 89', $dto->phone);
        $this->assertSame('+33 6 12 34 56 78', $dto->cellphone);
        $this->assertTrue($dto->isDefault);
    }

    public function testToArray(): void
    {
        $dto = $this->createDefaultDto();
        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertSame(1, $array['id']);
        $this->assertSame('Home', $array['label']);
        $this->assertSame(1, $array['titleId']);
        $this->assertSame('John', $array['firstname']);
        $this->assertSame('Doe', $array['lastname']);
        $this->assertSame('ACME Corp', $array['company']);
        $this->assertSame('123 Main Street', $array['address1']);
        $this->assertSame('Apt 4B', $array['address2']);
        $this->assertNull($array['address3']);
        $this->assertSame('75001', $array['zipcode']);
        $this->assertSame('Paris', $array['city']);
        $this->assertSame(64, $array['countryId']);
        $this->assertNull($array['stateId']);
        $this->assertSame('+33 1 23 45 67 89', $array['phone']);
        $this->assertSame('+33 6 12 34 56 78', $array['cellphone']);
        $this->assertTrue($array['isDefault']);
    }

    public function testGetFullName(): void
    {
        $dto = $this->createDefaultDto();

        $this->assertSame('John Doe', $dto->getFullName());
    }

    public function testGetFullNameWithEmptyFirstname(): void
    {
        $dto = new AddressDto(
            id: 1,
            label: 'Office',
            titleId: 1,
            firstname: '',
            lastname: 'Smith',
            company: '',
            address1: '456 Business Ave',
            address2: null,
            address3: null,
            zipcode: '75008',
            city: 'Paris',
            countryId: 64,
            stateId: null,
            phone: '',
            cellphone: null,
            isDefault: false,
        );

        $this->assertSame('Smith', $dto->getFullName());
    }

    public function testGetFullNameWithEmptyLastname(): void
    {
        $dto = new AddressDto(
            id: 1,
            label: 'Office',
            titleId: 1,
            firstname: 'Jane',
            lastname: '',
            company: '',
            address1: '456 Business Ave',
            address2: null,
            address3: null,
            zipcode: '75008',
            city: 'Paris',
            countryId: 64,
            stateId: null,
            phone: '',
            cellphone: null,
            isDefault: false,
        );

        $this->assertSame('Jane', $dto->getFullName());
    }

    public function testGetFullAddress(): void
    {
        $dto = $this->createDefaultDto();

        $this->assertSame('123 Main Street, Apt 4B, 75001 Paris', $dto->getFullAddress());
    }

    public function testGetFullAddressWithAllLines(): void
    {
        $dto = new AddressDto(
            id: 1,
            label: 'Complex',
            titleId: 1,
            firstname: 'Jane',
            lastname: 'Smith',
            company: '',
            address1: 'Building A',
            address2: 'Floor 3',
            address3: 'Office 301',
            zipcode: '69001',
            city: 'Lyon',
            countryId: 64,
            stateId: null,
            phone: '',
            cellphone: null,
            isDefault: false,
        );

        $this->assertSame('Building A, Floor 3, Office 301, 69001 Lyon', $dto->getFullAddress());
    }

    public function testGetFullAddressWithMinimalData(): void
    {
        $dto = new AddressDto(
            id: 1,
            label: 'Simple',
            titleId: 1,
            firstname: 'Bob',
            lastname: 'Builder',
            company: '',
            address1: '1 Rue Simple',
            address2: null,
            address3: null,
            zipcode: '13001',
            city: 'Marseille',
            countryId: 64,
            stateId: null,
            phone: '',
            cellphone: null,
            isDefault: false,
        );

        $this->assertSame('1 Rue Simple, 13001 Marseille', $dto->getFullAddress());
    }

    public function testImmutability(): void
    {
        $dto = $this->createDefaultDto();

        $this->expectException(\Error::class);
        $dto->firstname = 'Modified';
    }

    public function testIsDefaultFalse(): void
    {
        $dto = new AddressDto(
            id: 2,
            label: 'Secondary',
            titleId: 2,
            firstname: 'Alice',
            lastname: 'Wonder',
            company: '',
            address1: '789 Other Street',
            address2: null,
            address3: null,
            zipcode: '33000',
            city: 'Bordeaux',
            countryId: 64,
            stateId: null,
            phone: '',
            cellphone: null,
            isDefault: false,
        );

        $this->assertFalse($dto->isDefault);
    }

    public function testWithStateId(): void
    {
        $dto = new AddressDto(
            id: 1,
            label: 'US Address',
            titleId: 1,
            firstname: 'Mike',
            lastname: 'Johnson',
            company: 'US Corp',
            address1: '100 Broadway',
            address2: null,
            address3: null,
            zipcode: '10001',
            city: 'New York',
            countryId: 223,
            stateId: 32,
            phone: '+1 555 123 4567',
            cellphone: null,
            isDefault: true,
        );

        $this->assertSame(223, $dto->countryId);
        $this->assertSame(32, $dto->stateId);
    }
}
