<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace Moderna\UiComponents\Checkout\Delivery;

use Moderna\UiComponents\Checkout\CheckoutEvents;
use Propel\Runtime\Map\TableMap;
use Psr\Log\LoggerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Api\Resource\DeliveryModuleOption;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Shipping\ShippingFacade;
use Thelia\Model\Customer;

/**
 * LiveComponent for delivery address and method selection.
 *
 * This component handles:
 * - Display and selection of delivery addresses
 * - Display and selection of delivery methods/modules
 * - Toggle for new address form
 * - Communication with other checkout components
 *
 * Usage in Twig:
 * {{ component('Moderna:Checkout:Delivery') }}
 */
#[AsLiveComponent(
    name: 'Moderna:Checkout:Delivery',
    template: '@UiComponents/Checkout/Delivery/Delivery.html.twig'
)]
class Delivery
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /**
     * Customer's saved addresses.
     *
     * @var array<int, array>
     */
    #[LiveProp]
    public array $addresses = [];

    /**
     * Currently selected delivery address ID.
     */
    #[LiveProp]
    public ?int $selectedAddressId = null;

    /**
     * Available delivery modules/methods.
     *
     * @var array<string, array{code: string, title: string, moduleId: int, deliveryMode: string, postage: float}>
     */
    #[LiveProp]
    public array $deliveryModules = [];

    /**
     * Currently selected delivery module ID.
     */
    #[LiveProp]
    public ?int $selectedModuleId = null;

    /**
     * Currently selected delivery module option code.
     */
    #[LiveProp]
    public ?string $selectedOptionCode = null;

    /**
     * Invoice address ID (can be different from delivery).
     */
    #[LiveProp]
    public ?int $invoiceAddressId = null;

    /**
     * Flag to show the new address form.
     */
    #[LiveProp]
    public bool $showNewAddressForm = false;

    /**
     * Address ID being edited (if any).
     */
    #[LiveProp]
    public ?int $editingAddressId = null;

    /**
     * Error message to display.
     */
    #[LiveProp]
    public ?string $error = null;

    public function __construct(
        private readonly Session $session,
        private readonly CartFacade $cartFacade,
        private readonly ShippingFacade $shippingFacade,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Initialize component by loading addresses and delivery options.
     */
    public function mount(): void
    {
        $this->loadAddresses();
        $this->selectedAddressId = $this->cartFacade->getDeliveryAddressId();

        // If no address selected, select default
        if (null === $this->selectedAddressId && !empty($this->addresses)) {
            $defaultAddress = array_filter($this->addresses, fn ($addr) => $addr['isDefault'] ?? false);
            if (!empty($defaultAddress)) {
                $this->selectAddress(reset($defaultAddress)['id']);
            } elseif (!empty($this->addresses)) {
                $this->selectAddress($this->addresses[0]['id']);
            }
        }

        $this->invoiceAddressId = $this->cartFacade->getInvoiceAddressId();
        $this->selectedModuleId = $this->cartFacade->getDeliveryModuleId();

        $this->loadDeliveryModules();
    }

    /**
     * Load customer's saved addresses.
     */
    private function loadAddresses(): void
    {
        /** @var Customer|null $customer */
        $customer = $this->session->getCustomerUser();

        if (!$customer) {
            $this->addresses = [];

            return;
        }

        $addresses = $customer->getAddresses();
        $this->addresses = $addresses ? $addresses->toArray(null, false, TableMap::TYPE_CAMELNAME) : [];
    }

    /**
     * Load available delivery modules based on selected address.
     */
    private function loadDeliveryModules(): void
    {
        if (!$this->selectedAddressId) {
            $this->deliveryModules = [];

            return;
        }

        try {
            $cart = $this->cartFacade->getOrCreateFromSession();
            $deliveryModulesWithOption = $this->shippingFacade->listValidMethods($cart);
            $this->deliveryModules = [];
            $locale = $this->session->getLang()->getLocale();

            foreach ($deliveryModulesWithOption as $deliveryModuleWithOptionDTO) {
                $options = $deliveryModuleWithOptionDTO->getDeliveryModuleOption();
                $module = $deliveryModuleWithOptionDTO->getDeliveryModule();

                /** @var DeliveryModuleOption $option */
                foreach ($options as $option) {
                    $code = $option->getCode();
                    $moduleTitle = $module->getI18ns()->i18ns[$locale]->getTitle()
                        ?? $module->getI18ns()->i18ns['en_US']->getTitle()
                        ?? $module->getCode();

                    $this->deliveryModules[$code] = [
                        'code' => $code,
                        'title' => $moduleTitle,
                        'moduleId' => $module->getId(),
                        'deliveryMode' => $module->getDeliveryMode(),
                        'postage' => $option->getPostage(),
                        'image' => $option->getImage(),
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error loading delivery modules: '.$e->getMessage());
            $this->deliveryModules = [];
        }
    }

    /**
     * Select a delivery address.
     */
    #[LiveAction]
    public function selectAddress(#[LiveArg] int $addressId): void
    {
        $this->error = null;

        try {
            $cart = $this->cartFacade->getOrCreateFromSession();
            $this->cartFacade->setDeliveryAddress(new CheckoutDTO(
                cart: $cart,
                deliveryAddressId: $addressId,
            ));

            $this->selectedAddressId = $this->cartFacade->getDeliveryAddressId();

            // Reset delivery module when address changes
            $cart->setDeliveryModuleId(null)->save();
            $this->selectedModuleId = null;
            $this->selectedOptionCode = null;

            $this->loadDeliveryModules();

            $this->emit(CheckoutEvents::SET_DELIVERY_ADDRESS_ID, ['addressId' => $addressId]);
            $this->emit(CheckoutEvents::SYNC_SUMMARY);
        } catch (\Exception $e) {
            $this->logger->error('Error selecting address: '.$e->getMessage());
            $this->error = 'An error occurred while selecting the address';
        }
    }

    /**
     * Select a delivery module/method.
     */
    #[LiveAction]
    public function selectModule(#[LiveArg] int $moduleId, #[LiveArg] string $optionCode): void
    {
        $this->error = null;

        try {
            $cart = $this->cartFacade->getOrCreateFromSession();

            $this->cartFacade->setDeliveryModule(new CheckoutDTO(
                cart: $cart,
                deliveryModuleId: $moduleId,
            ));

            $this->session->set('deliveryModuleOption', $optionCode);

            $this->selectedModuleId = $moduleId;
            $this->selectedOptionCode = $optionCode;

            // Handle local pickup special case
            if (strtolower($optionCode) === 'localpickup') {
                $this->shippingFacade->setCustomerDefaultDeliveryAddress($cart);
            }

            $this->emit(CheckoutEvents::SET_DELIVERY_MODULE_ID, [
                'moduleId' => $moduleId,
                'optionCode' => $optionCode,
            ]);
            $this->emit(CheckoutEvents::SYNC_SUMMARY);
        } catch (\Exception $e) {
            $this->logger->error('Error selecting delivery module: '.$e->getMessage());
            $this->error = 'An error occurred while selecting the delivery method';
        }
    }

    /**
     * Toggle the new address form visibility.
     */
    #[LiveAction]
    public function toggleNewAddressForm(): void
    {
        $this->showNewAddressForm = !$this->showNewAddressForm;
        $this->editingAddressId = null;
    }

    /**
     * Start editing an existing address.
     */
    #[LiveAction]
    public function editAddress(#[LiveArg] int $addressId): void
    {
        $this->editingAddressId = $addressId;
        $this->showNewAddressForm = false;
    }

    /**
     * Handle new address added event.
     */
    #[LiveListener(CheckoutEvents::ADD_NEW_DELIVERY_ADDRESS)]
    public function onAddressAdded(#[LiveArg] ?int $addressId = null): void
    {
        $this->showNewAddressForm = false;
        $this->editingAddressId = null;
        $this->loadAddresses();

        if ($addressId) {
            $this->selectAddress($addressId);
        }
    }

    /**
     * Handle cancel address form event.
     */
    #[LiveListener('cancelAddressForm')]
    public function onCancelAddressForm(): void
    {
        $this->showNewAddressForm = false;
        $this->editingAddressId = null;
    }

    /**
     * Check if an address is currently selected.
     */
    public function isAddressSelected(int $addressId): bool
    {
        return $this->selectedAddressId === $addressId;
    }

    /**
     * Check if a delivery module is currently selected.
     */
    public function isModuleSelected(int $moduleId, string $optionCode): bool
    {
        return $this->selectedModuleId === $moduleId && $this->selectedOptionCode === $optionCode;
    }

    /**
     * Get the count of available addresses.
     */
    public function getAddressCount(): int
    {
        return count($this->addresses);
    }

    /**
     * Check if delivery options are available.
     */
    public function hasDeliveryOptions(): bool
    {
        return !empty($this->deliveryModules);
    }

    /**
     * Check if the customer has any saved addresses.
     */
    public function hasAddresses(): bool
    {
        return !empty($this->addresses);
    }

    /**
     * Get the currently selected address data.
     */
    public function getSelectedAddress(): ?array
    {
        if (!$this->selectedAddressId) {
            return null;
        }

        foreach ($this->addresses as $address) {
            if ($address['id'] === $this->selectedAddressId) {
                return $address;
            }
        }

        return null;
    }

    /**
     * Format address for display.
     */
    public function formatAddress(array $address): string
    {
        $parts = [
            $address['firstname'].' '.$address['lastname'],
            $address['address1'],
        ];

        if (!empty($address['address2'])) {
            $parts[] = $address['address2'];
        }

        $parts[] = $address['zipcode'].' '.$address['city'];

        return implode(', ', $parts);
    }
}
