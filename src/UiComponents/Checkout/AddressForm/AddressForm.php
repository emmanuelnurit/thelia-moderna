<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace Moderna\UiComponents\Checkout\AddressForm;

use Moderna\UiComponents\Checkout\CheckoutEvents;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Addressing\Service\AddressService;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\CustomerTitleQuery;

/**
 * LiveComponent for address creation and editing.
 *
 * This component handles:
 * - Address form display with all fields
 * - Address validation
 * - Creating new addresses
 * - Editing existing addresses
 *
 * Usage in Twig:
 * {{ component('Moderna:Checkout:AddressForm') }}
 * {{ component('Moderna:Checkout:AddressForm', { addressId: 123 }) }}
 */
#[AsLiveComponent(
    name: 'Moderna:Checkout:AddressForm',
    template: '@templates/frontOffice/moderna/components/UiComponents/Checkout/AddressForm/AddressForm.html.twig'
)]
class AddressForm
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /**
     * Optional address ID for editing existing address.
     */
    #[LiveProp]
    public ?int $addressId = null;

    /**
     * Address label/name.
     */
    #[LiveProp(writable: true)]
    public string $label = '';

    /**
     * Customer title ID.
     */
    #[LiveProp(writable: true)]
    public ?int $titleId = null;

    /**
     * First name.
     */
    #[LiveProp(writable: true)]
    public string $firstname = '';

    /**
     * Last name.
     */
    #[LiveProp(writable: true)]
    public string $lastname = '';

    /**
     * Company name (optional).
     */
    #[LiveProp(writable: true)]
    public string $company = '';

    /**
     * Primary address line.
     */
    #[LiveProp(writable: true)]
    public string $address1 = '';

    /**
     * Secondary address line (optional).
     */
    #[LiveProp(writable: true)]
    public string $address2 = '';

    /**
     * Postal/ZIP code.
     */
    #[LiveProp(writable: true)]
    public string $zipcode = '';

    /**
     * City name.
     */
    #[LiveProp(writable: true)]
    public string $city = '';

    /**
     * Country ID (default: France = 64).
     */
    #[LiveProp(writable: true)]
    public int $countryId = 64;

    /**
     * Phone number (optional).
     */
    #[LiveProp(writable: true)]
    public string $phone = '';

    /**
     * Mobile/cell phone number (optional).
     */
    #[LiveProp(writable: true)]
    public string $cellphone = '';

    /**
     * Set as default address.
     */
    #[LiveProp(writable: true)]
    public bool $isDefault = false;

    /**
     * Available countries list.
     *
     * @var array<int, array{id: int, title: string, isoCode: string}>
     */
    #[LiveProp]
    public array $countries = [];

    /**
     * Available customer titles list.
     *
     * @var array<int, array{id: int, short: string, long: string}>
     */
    #[LiveProp]
    public array $titles = [];

    /**
     * Error message to display.
     */
    #[LiveProp]
    public ?string $error = null;

    /**
     * Success message to display.
     */
    #[LiveProp]
    public ?string $success = null;

    /**
     * Field-specific validation errors.
     *
     * @var array<string, string>
     */
    #[LiveProp]
    public array $fieldErrors = [];

    public function __construct(
        private readonly Session $session,
        private readonly AddressService $addressService,
    ) {
    }

    /**
     * Initialize component by loading countries, titles, and existing address data.
     */
    public function mount(): void
    {
        $this->loadCountries();
        $this->loadTitles();

        if ($this->addressId) {
            $this->loadAddressData();
        }
    }

    /**
     * Load available countries.
     */
    private function loadCountries(): void
    {
        $locale = $this->session->getLang()->getLocale();
        $countries = CountryQuery::create()
            ->filterByVisible(1)
            ->orderByPosition()
            ->find();

        $this->countries = [];
        foreach ($countries as $country) {
            $country->setLocale($locale);
            $this->countries[] = [
                'id' => $country->getId(),
                'title' => $country->getTitle(),
                'isoCode' => $country->getIsocode(),
            ];
        }
    }

    /**
     * Load available customer titles.
     */
    private function loadTitles(): void
    {
        $locale = $this->session->getLang()->getLocale();
        $titles = CustomerTitleQuery::create()
            ->orderByPosition()
            ->find();

        $this->titles = [];
        foreach ($titles as $title) {
            $title->setLocale($locale);
            $this->titles[] = [
                'id' => $title->getId(),
                'short' => $title->getShort(),
                'long' => $title->getLong(),
            ];
        }

        // Set default title if not set
        if (!$this->titleId && !empty($this->titles)) {
            $this->titleId = $this->titles[0]['id'];
        }
    }

    /**
     * Load existing address data for editing.
     */
    private function loadAddressData(): void
    {
        $address = AddressQuery::create()->findPk($this->addressId);
        if (!$address) {
            $this->error = 'Address not found';

            return;
        }

        // Verify the address belongs to the current customer
        $customer = $this->session->getCustomerUser();
        if (!$customer || $address->getCustomerId() !== $customer->getId()) {
            $this->error = 'Unauthorized access to this address';

            return;
        }

        $this->label = $address->getLabel() ?? '';
        $this->titleId = $address->getTitleId();
        $this->firstname = $address->getFirstname();
        $this->lastname = $address->getLastname();
        $this->company = $address->getCompany() ?? '';
        $this->address1 = $address->getAddress1();
        $this->address2 = $address->getAddress2() ?? '';
        $this->zipcode = $address->getZipcode();
        $this->city = $address->getCity();
        $this->countryId = $address->getCountryId();
        $this->phone = $address->getPhone() ?? '';
        $this->cellphone = $address->getCellphone() ?? '';
        $this->isDefault = (bool) $address->getIsDefault();
    }

    /**
     * Validate form data.
     */
    private function validate(): bool
    {
        $this->fieldErrors = [];

        if (empty(trim($this->firstname))) {
            $this->fieldErrors['firstname'] = 'First name is required';
        }

        if (empty(trim($this->lastname))) {
            $this->fieldErrors['lastname'] = 'Last name is required';
        }

        if (empty(trim($this->address1))) {
            $this->fieldErrors['address1'] = 'Address is required';
        }

        if (empty(trim($this->zipcode))) {
            $this->fieldErrors['zipcode'] = 'Postal code is required';
        }

        if (empty(trim($this->city))) {
            $this->fieldErrors['city'] = 'City is required';
        }

        if (!$this->countryId) {
            $this->fieldErrors['countryId'] = 'Country is required';
        }

        return empty($this->fieldErrors);
    }

    /**
     * Submit the address form.
     */
    #[LiveAction]
    public function submit(): void
    {
        $this->error = null;
        $this->success = null;

        $customer = $this->session->getCustomerUser();
        if (!$customer) {
            $this->error = 'You must be logged in to save an address';

            return;
        }

        if (!$this->validate()) {
            $this->error = 'Please correct the errors below';

            return;
        }

        try {
            if ($this->addressId) {
                // Update existing address
                $address = AddressQuery::create()->findPk($this->addressId);
                if (!$address || $address->getCustomerId() !== $customer->getId()) {
                    $this->error = 'Address not found';

                    return;
                }
            } else {
                // Create new address
                $address = new Address();
                $address->setCustomerId($customer->getId());
            }

            $address->setLabel($this->label ?: null);
            $address->setTitleId($this->titleId);
            $address->setFirstname($this->firstname);
            $address->setLastname($this->lastname);
            $address->setCompany($this->company ?: null);
            $address->setAddress1($this->address1);
            $address->setAddress2($this->address2 ?: null);
            $address->setZipcode($this->zipcode);
            $address->setCity($this->city);
            $address->setCountryId($this->countryId);
            $address->setPhone($this->phone ?: null);
            $address->setCellphone($this->cellphone ?: null);

            // Handle default address
            if ($this->isDefault) {
                // Remove default from other addresses
                AddressQuery::create()
                    ->filterByCustomerId($customer->getId())
                    ->filterByIsDefault(1)
                    ->update(['IsDefault' => 0]);

                $address->setIsDefault(1);
            }

            $address->save();

            $this->success = $this->addressId ? 'Address updated successfully' : 'Address created successfully';

            // Emit event to notify parent components
            $this->emitUp(CheckoutEvents::ADD_NEW_DELIVERY_ADDRESS, [
                'addressId' => $address->getId(),
            ]);
        } catch (\Exception $e) {
            $this->error = 'An error occurred while saving the address';
        }
    }

    /**
     * Cancel and emit event to close the form.
     */
    #[LiveAction]
    public function cancel(): void
    {
        $this->emitUp('cancelAddressForm');
    }

    /**
     * Check if form has any errors.
     */
    public function hasErrors(): bool
    {
        return !empty($this->fieldErrors) || $this->error !== null;
    }

    /**
     * Get error for a specific field.
     */
    public function getFieldError(string $field): ?string
    {
        return $this->fieldErrors[$field] ?? null;
    }
}
