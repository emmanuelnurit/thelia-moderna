<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace Moderna\UiComponents\Checkout\Payment;

use Moderna\UiComponents\Checkout\CheckoutEvents;
use Psr\Log\LoggerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Api\Service\DataAccess\DataAccessService;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;

/**
 * LiveComponent for payment method selection.
 *
 * This component handles:
 * - Display of available payment methods
 * - Payment method selection
 * - Communication with other checkout components
 *
 * Usage in Twig:
 * {{ component('Moderna:Checkout:Payment') }}
 */
#[AsLiveComponent(
    name: 'Moderna:Checkout:Payment',
    template: '@UiComponents/Checkout/Payment/Payment.html.twig'
)]
class Payment
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /**
     * Available payment modules.
     *
     * @var array<int, array{id: int, code: string, title: string, description: string, image: string|null, valid: bool}>
     */
    #[LiveProp]
    public array $paymentModules = [];

    /**
     * Currently selected payment module ID.
     */
    #[LiveProp]
    public ?int $selectedModuleId = null;

    /**
     * Invoice address ID.
     */
    #[LiveProp]
    public ?int $invoiceAddressId = null;

    /**
     * Error message to display.
     */
    #[LiveProp]
    public ?string $error = null;

    /**
     * Flag indicating if component is loading.
     */
    #[LiveProp]
    public bool $isLoading = false;

    public function __construct(
        private readonly Session $session,
        private readonly CartFacade $cartFacade,
        private readonly DataAccessService $dataAccessService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Initialize component by loading payment modules.
     */
    public function mount(): void
    {
        $this->loadPaymentModules();
        $this->invoiceAddressId = $this->cartFacade->getInvoiceAddressId();
        $this->selectedModuleId = $this->cartFacade->getPaymentModuleId();
    }

    /**
     * Load available payment modules from the API.
     */
    private function loadPaymentModules(): void
    {
        try {
            $modules = $this->dataAccessService->resources('/api/front/payment/modules');
            $this->paymentModules = [];
            $locale = $this->session->getLang()->getLocale();

            foreach ($modules as $module) {
                // Only add valid modules
                if ($module['valid'] ?? false) {
                    $this->paymentModules[] = [
                        'id' => $module['id'],
                        'code' => $module['code'] ?? '',
                        'title' => $module['i18ns'][$locale]['title']
                            ?? $module['i18ns']['en_US']['title']
                            ?? $module['code']
                            ?? 'Unknown',
                        'description' => $module['i18ns'][$locale]['description']
                            ?? $module['i18ns']['en_US']['description']
                            ?? '',
                        'image' => $module['image'] ?? null,
                        'valid' => true,
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error loading payment modules: '.$e->getMessage());
            $this->paymentModules = [];
            $this->error = 'Unable to load payment methods';
        }
    }

    /**
     * Select a payment method.
     */
    #[LiveAction]
    public function selectPayment(#[LiveArg] int $moduleId): void
    {
        $this->error = null;
        $this->isLoading = true;

        try {
            $cart = $this->cartFacade->getOrCreateFromSession();

            $this->cartFacade->setPaymentModule(new CheckoutDTO(
                cart: $cart,
                paymentModuleId: $moduleId,
            ));

            $this->selectedModuleId = $this->cartFacade->getPaymentModuleId();

            // Emit event to notify other components
            $this->emit(CheckoutEvents::SET_PAYMENT_MODULE_ID, [
                'moduleId' => $moduleId,
            ]);

            $this->emit(CheckoutEvents::SYNC_SUMMARY);
        } catch (\Exception $e) {
            $this->logger->error('Error selecting payment module: '.$e->getMessage());
            $this->error = 'An error occurred while selecting the payment method';
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Handle delivery module selection event.
     * Payment options might change based on delivery selection.
     */
    #[LiveListener(CheckoutEvents::SET_DELIVERY_MODULE_ID)]
    public function onDeliveryModuleChanged(): void
    {
        // Reload payment modules as they might change based on delivery
        $this->loadPaymentModules();
    }

    /**
     * Check if a payment module is currently selected.
     */
    public function isModuleSelected(int $moduleId): bool
    {
        return $this->selectedModuleId === $moduleId;
    }

    /**
     * Check if any payment modules are available.
     */
    public function hasPaymentOptions(): bool
    {
        return !empty($this->paymentModules);
    }

    /**
     * Get the currently selected payment module data.
     */
    public function getSelectedModule(): ?array
    {
        if (!$this->selectedModuleId) {
            return null;
        }

        foreach ($this->paymentModules as $module) {
            if ($module['id'] === $this->selectedModuleId) {
                return $module;
            }
        }

        return null;
    }

    /**
     * Get payment module by ID.
     */
    public function getModuleById(int $moduleId): ?array
    {
        foreach ($this->paymentModules as $module) {
            if ($module['id'] === $moduleId) {
                return $module;
            }
        }

        return null;
    }

    /**
     * Get count of available payment modules.
     */
    public function getModuleCount(): int
    {
        return count($this->paymentModules);
    }

    /**
     * Check if payment selection is complete.
     */
    public function isPaymentSelected(): bool
    {
        return $this->selectedModuleId !== null;
    }

    /**
     * Get icon for payment module based on code.
     */
    public function getModuleIcon(string $code): string
    {
        $icons = [
            'paypal' => 'paypal',
            'stripe' => 'credit-card',
            'cheque' => 'file-text',
            'virement' => 'building-2',
            'bank_transfer' => 'building-2',
            'cash_on_delivery' => 'banknote',
            'cod' => 'banknote',
        ];

        return $icons[strtolower($code)] ?? 'credit-card';
    }
}
