<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace Moderna\UiComponents\Account\AddressList;

use Moderna\DTO\AddressDto;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\AddressQuery;
use Thelia\Model\CountryQuery;

/**
 * LiveComponent for managing customer addresses.
 *
 * This component handles:
 * - Listing all customer addresses
 * - Deleting addresses
 * - Setting default address
 * - Showing/hiding new address form
 * - Editing existing addresses
 *
 * Usage in Twig:
 * {{ component('Moderna:Account:AddressList') }}
 */
#[AsLiveComponent(
    name: 'Moderna:Account:AddressList',
    template: '@templates/frontOffice/moderna/components/UiComponents/Account/AddressList/AddressList.html.twig'
)]
class AddressList
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /**
     * List of addresses as arrays.
     *
     * @var array<int, array>
     */
    #[LiveProp]
    public array $addresses = [];

    /**
     * ID of address being edited (null if none).
     */
    #[LiveProp]
    public ?int $editingAddressId = null;

    /**
     * Whether to show the new address form.
     */
    #[LiveProp]
    public bool $showNewForm = false;

    /**
     * Success message to display.
     */
    #[LiveProp]
    public ?string $success = null;

    /**
     * Error message to display.
     */
    #[LiveProp]
    public ?string $error = null;

    /**
     * Pending delete address data for undo.
     */
    #[LiveProp]
    public ?array $pendingDelete = null;

    /**
     * Countries for display.
     *
     * @var array<int, array>
     */
    #[LiveProp]
    public array $countries = [];

    public function __construct(
        private readonly Session $session,
    ) {}

    /**
     * Initialize the component by loading addresses.
     */
    public function mount(): void
    {
        $this->loadAddresses();
        $this->loadCountries();
    }

    /**
     * Load countries for reference.
     */
    private function loadCountries(): void
    {
        $locale = $this->session->getLang()->getLocale();
        $countries = CountryQuery::create()
            ->filterByVisible(1)
            ->find();

        $this->countries = [];
        foreach ($countries as $country) {
            $country->setLocale($locale);
            $this->countries[$country->getId()] = [
                'id' => $country->getId(),
                'title' => $country->getTitle(),
                'isoCode' => $country->getIsocode(),
            ];
        }
    }

    /**
     * Load all customer addresses.
     */
    private function loadAddresses(): void
    {
        $customer = $this->session->getCustomerUser();
        if (!$customer) {
            $this->addresses = [];
            return;
        }

        $addresses = AddressQuery::create()
            ->filterByCustomerId($customer->getId())
            ->orderByIsDefault('desc')
            ->orderByLabel('asc')
            ->find();

        $this->addresses = [];
        foreach ($addresses as $address) {
            $this->addresses[] = AddressDto::fromAddress($address)->toArray();
        }
    }

    /**
     * Delete an address.
     */
    #[LiveAction]
    public function delete(#[LiveArg] int $addressId): void
    {
        $this->error = null;
        $this->success = null;

        $customer = $this->session->getCustomerUser();
        if (!$customer) {
            $this->error = 'You must be logged in';
            return;
        }

        $address = AddressQuery::create()->findPk($addressId);
        if (!$address || $address->getCustomerId() !== $customer->getId()) {
            $this->error = 'Address not found';
            return;
        }

        // Don't allow deleting default address if there are other addresses
        if ($address->getIsDefault()) {
            $addressCount = AddressQuery::create()
                ->filterByCustomerId($customer->getId())
                ->count();

            if ($addressCount > 1) {
                $this->error = 'Cannot delete default address. Please set another address as default first.';
                return;
            }
        }

        // Store for undo
        $this->pendingDelete = AddressDto::fromAddress($address)->toArray();

        $address->delete();
        $this->loadAddresses();
        $this->success = 'Address deleted';
    }

    /**
     * Restore a deleted address.
     */
    #[LiveAction]
    public function restoreAddress(): void
    {
        if (!$this->pendingDelete) {
            return;
        }

        $customer = $this->session->getCustomerUser();
        if (!$customer) {
            return;
        }

        try {
            $address = new \Thelia\Model\Address();
            $address->setCustomerId($customer->getId())
                ->setLabel($this->pendingDelete['label'] ?: null)
                ->setTitleId($this->pendingDelete['titleId'])
                ->setFirstname($this->pendingDelete['firstname'])
                ->setLastname($this->pendingDelete['lastname'])
                ->setCompany($this->pendingDelete['company'] ?: null)
                ->setAddress1($this->pendingDelete['address1'])
                ->setAddress2($this->pendingDelete['address2'] ?: null)
                ->setAddress3($this->pendingDelete['address3'] ?: null)
                ->setZipcode($this->pendingDelete['zipcode'])
                ->setCity($this->pendingDelete['city'])
                ->setCountryId($this->pendingDelete['countryId'])
                ->setStateId($this->pendingDelete['stateId'])
                ->setPhone($this->pendingDelete['phone'] ?: null)
                ->setCellphone($this->pendingDelete['cellphone'] ?: null)
                ->setIsDefault($this->pendingDelete['isDefault'] ? 1 : 0);

            $address->save();

            $this->pendingDelete = null;
            $this->loadAddresses();
            $this->success = 'Address restored';
        } catch (\Exception $e) {
            $this->error = 'Could not restore address';
        }
    }

    /**
     * Clear the pending delete state.
     */
    #[LiveAction]
    public function clearPendingDelete(): void
    {
        $this->pendingDelete = null;
    }

    /**
     * Set an address as default.
     */
    #[LiveAction]
    public function setDefault(#[LiveArg] int $addressId): void
    {
        $this->error = null;
        $this->success = null;

        $customer = $this->session->getCustomerUser();
        if (!$customer) {
            $this->error = 'You must be logged in';
            return;
        }

        $address = AddressQuery::create()->findPk($addressId);
        if (!$address || $address->getCustomerId() !== $customer->getId()) {
            $this->error = 'Address not found';
            return;
        }

        // Remove default from all other addresses
        AddressQuery::create()
            ->filterByCustomerId($customer->getId())
            ->filterByIsDefault(1)
            ->update(['IsDefault' => 0]);

        // Set this address as default
        $address->setIsDefault(1);
        $address->save();

        $this->loadAddresses();
        $this->success = 'Default address updated';
    }

    /**
     * Toggle the new address form visibility.
     */
    #[LiveAction]
    public function toggleNewForm(): void
    {
        $this->showNewForm = !$this->showNewForm;
        $this->editingAddressId = null;
        $this->error = null;
        $this->success = null;
    }

    /**
     * Start editing an address.
     */
    #[LiveAction]
    public function editAddress(#[LiveArg] int $addressId): void
    {
        $this->editingAddressId = $addressId;
        $this->showNewForm = false;
        $this->error = null;
        $this->success = null;
    }

    /**
     * Cancel editing.
     */
    #[LiveAction]
    public function cancelEdit(): void
    {
        $this->editingAddressId = null;
        $this->showNewForm = false;
    }

    /**
     * Handle address form submission (from child component).
     */
    #[LiveListener('addressSaved')]
    public function onAddressSaved(): void
    {
        $this->loadAddresses();
        $this->editingAddressId = null;
        $this->showNewForm = false;
        $this->success = 'Address saved successfully';
    }

    /**
     * Handle cancel from child form.
     */
    #[LiveListener('cancelAddressForm')]
    public function onCancelAddressForm(): void
    {
        $this->editingAddressId = null;
        $this->showNewForm = false;
    }

    /**
     * Get country name by ID.
     */
    public function getCountryName(int $countryId): string
    {
        return $this->countries[$countryId]['title'] ?? '';
    }

    /**
     * Check if the list is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->addresses);
    }

    /**
     * Get the number of addresses.
     */
    public function getCount(): int
    {
        return count($this->addresses);
    }
}
