<?php

declare(strict_types=1);

namespace ModernaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Shipping\ShippingFacade;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Customer;
use Thelia\Model\ModuleQuery;

#[Route('/moderna-api/checkout', name: 'moderna_checkout_')]
class ModernaCheckoutController extends AbstractController
{
    public function __construct(
        private readonly CartFacade $cartFacade,
        private readonly ShippingFacade $shippingFacade,
        private readonly CheckoutFacade $checkoutFacade
    ) {
    }

    /**
     * Get customer addresses for delivery selection
     */
    #[Route('/api/addresses', name: 'api_addresses', methods: ['GET'])]
    public function getAddresses(Request $request): JsonResponse
    {
        $customer = $this->getCurrentCustomer($request);
        if (!$customer) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        $addresses = AddressQuery::create()
            ->filterByCustomerId($customer->getId())
            ->orderByIsDefault('DESC')
            ->find();

        $result = [];
        foreach ($addresses as $address) {
            $country = $address->getCountry();
            $title = $address->getCustomerTitle();

            $result[] = [
                'id' => $address->getId(),
                'label' => $address->getLabel() ?: '',
                'firstname' => $address->getFirstname(),
                'lastname' => $address->getLastname(),
                'company' => $address->getCompany() ?: '',
                'address1' => $address->getAddress1(),
                'address2' => $address->getAddress2() ?: '',
                'address3' => $address->getAddress3() ?: '',
                'zipcode' => $address->getZipcode(),
                'city' => $address->getCity(),
                'country' => $country ? $country->getTitle() : '',
                'countryId' => $address->getCountryId(),
                'phone' => $address->getPhone() ?: '',
                'cellphone' => $address->getCellphone() ?: '',
                'isDefault' => (bool) $address->getIsDefault(),
                'titleShort' => $title ? $title->getShort() : '',
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * Get available delivery methods for the current cart and address
     */
    #[Route('/api/delivery-methods', name: 'api_delivery_methods', methods: ['GET'])]
    public function getDeliveryMethods(Request $request): JsonResponse
    {
        $customer = $this->getCurrentCustomer($request);
        if (!$customer) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        try {
            $cart = $this->cartFacade->getOrCreateFromSession();

            // Check if cart has items
            if ($cart->getCartItems()->count() === 0) {
                return new JsonResponse([]);
            }

            // Ensure cart is associated with the customer
            if ($cart->getCustomerId() !== $customer->getId()) {
                $cart->setCustomerId($customer->getId());
                $cart->save();
            }

            // Ensure cart has currency (get default if not set)
            if (!$cart->getCurrencyId()) {
                $defaultCurrency = \Thelia\Model\CurrencyQuery::create()
                    ->filterByByDefault(1)
                    ->findOne();
                if ($defaultCurrency) {
                    $cart->setCurrencyId($defaultCurrency->getId());
                    $cart->save();
                }
            }

            // Check cart_address and determine delivery method lookup strategy
            $cartAddressId = $cart->getAddressDeliveryId();
            $country = null;

            if ($cartAddressId) {
                $cartAddress = \Thelia\Model\CartAddressQuery::create()->findPk($cartAddressId);
                if ($cartAddress && $cartAddress->getCountryId()) {
                    $country = \Thelia\Model\CountryQuery::create()->findPk($cartAddress->getCountryId());
                }
            }

            $deliveryModules = $this->shippingFacade->listValidMethods($cart, $country);

            $result = [];
            foreach ($deliveryModules as $moduleDTO) {
                $module = $moduleDTO->getDeliveryModule();
                $options = $moduleDTO->getDeliveryModuleOption();

                // Get module title from i18n (with null safety)
                $title = $module->getCode(); // Default fallback
                $description = '';

                if ($module->i18ns !== null && !empty($module->i18ns->i18ns)) {
                    $locale = $request->getLocale() ?: 'fr_FR';
                    $i18n = $module->i18ns->i18ns[$locale] ?? $module->i18ns->i18ns['en_US'] ?? reset($module->i18ns->i18ns) ?: null;
                    if ($i18n !== null) {
                        $title = $i18n->getTitle() ?: $module->getCode();
                        $description = $i18n->getDescription() ?: '';
                    }
                }

                // Take the first option (or cheapest) for this module
                $firstOption = reset($options);
                if ($firstOption) {
                    $result[] = [
                        'moduleId' => $module->getId(),
                        'code' => $firstOption->getCode(),
                        'title' => $title,
                        'description' => $description,
                        'postage' => $firstOption->getPostage(),
                        'deliveryMode' => $module->getDeliveryMode(),
                        'logoUrl' => null,
                    ];
                }
            }

            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set delivery address for the cart
     */
    #[Route('/api/set-delivery-address', name: 'api_set_delivery_address', methods: ['POST'])]
    public function setDeliveryAddress(Request $request): JsonResponse
    {
        $customer = $this->getCurrentCustomer($request);
        if (!$customer) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $addressId = (int) ($data['addressId'] ?? 0);

        if (!$addressId) {
            return new JsonResponse(['error' => 'Address ID required'], 400);
        }

        // Verify address belongs to customer
        $address = AddressQuery::create()
            ->filterById($addressId)
            ->filterByCustomerId($customer->getId())
            ->findOne();

        if (!$address) {
            return new JsonResponse(['error' => 'Address not found'], 404);
        }

        try {
            $cart = $this->cartFacade->getOrCreateFromSession();

            // Ensure cart is associated with the customer
            if ($cart->getCustomerId() !== $customer->getId()) {
                $cart->setCustomerId($customer->getId());
                $cart->save();
            }

            // Use CartFacade to set delivery address (creates cart_address entry)
            $this->cartFacade->setDeliveryAddress(new CheckoutDTO(
                cart: $cart,
                deliveryAddressId: $addressId
            ));

            // Reload cart to get updated address IDs
            $cart = $this->cartFacade->getOrCreateFromSession();

            return new JsonResponse([
                'success' => true,
                'addressId' => $addressId,
                'cartDeliveryAddressId' => $cart->getAddressDeliveryId()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set invoice address for the cart
     */
    #[Route('/api/set-invoice-address', name: 'api_set_invoice_address', methods: ['POST'])]
    public function setInvoiceAddress(Request $request): JsonResponse
    {
        $customer = $this->getCurrentCustomer($request);
        if (!$customer) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $addressId = $data['addressId'] ?? null;

        // null means same as delivery
        if ($addressId !== null) {
            $addressId = (int) $addressId;

            // Verify address belongs to customer
            $address = AddressQuery::create()
                ->filterById($addressId)
                ->filterByCustomerId($customer->getId())
                ->findOne();

            if (!$address) {
                return new JsonResponse(['error' => 'Address not found'], 404);
            }
        }

        try {
            $cart = $this->cartFacade->getOrCreateFromSession();

            // Use CartFacade to set invoice address (creates cart_address entry)
            $this->cartFacade->setInvoiceAddress(new CheckoutDTO(
                cart: $cart,
                invoiceAddressId: $addressId
            ));

            // Reload cart to get updated address IDs
            $cart = $this->cartFacade->getOrCreateFromSession();

            return new JsonResponse([
                'success' => true,
                'addressId' => $addressId,
                'cartInvoiceAddressId' => $cart->getAddressInvoiceId()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set delivery module for the cart
     */
    #[Route('/api/set-delivery-module', name: 'api_set_delivery_module', methods: ['POST'])]
    public function setDeliveryModule(Request $request): JsonResponse
    {
        $customer = $this->getCurrentCustomer($request);
        if (!$customer) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $moduleId = (int) ($data['moduleId'] ?? 0);
        $optionCode = $data['optionCode'] ?? '';

        if (!$moduleId) {
            return new JsonResponse(['error' => 'Module ID required'], 400);
        }

        try {
            $cart = $this->cartFacade->getOrCreateFromSession();
            $this->cartFacade->setDeliveryModule(new CheckoutDTO(
                cart: $cart,
                deliveryModuleId: $moduleId
            ));

            // Store option code in session
            $request->getSession()->set('deliveryModuleOption', $optionCode);

            // Recalculate postage
            $this->cartFacade->recalculatePostage($cart);

            return new JsonResponse([
                'success' => true,
                'moduleId' => $moduleId,
                'postage' => $cart->getPostage()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available payment methods
     */
    #[Route('/api/payment-methods', name: 'api_payment_methods', methods: ['GET'])]
    public function getPaymentMethods(Request $request): JsonResponse
    {
        $customer = $this->getCurrentCustomer($request);
        if (!$customer) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        try {
            $modules = ModuleQuery::create()
                ->filterByType(3) // Payment modules
                ->filterByActivate(1)
                ->orderByPosition()
                ->find();

            $locale = $request->getLocale() ?: 'fr_FR';
            $result = [];

            foreach ($modules as $module) {
                // Set locale for Propel i18n behavior
                $module->setLocale($locale);

                $moduleData = [
                    'id' => $module->getId(),
                    'code' => $module->getCode(),
                    'title' => $module->getTitle() ?: $module->getCode(),
                    'description' => $module->getDescription() ?: '',
                    'logoUrl' => null,
                    'paymentIcons' => [],
                ];

                // Special handling for CawlPayment - add enabled payment method icons
                if ($module->getCode() === 'CawlPayment') {
                    try {
                        // Get enabled methods from config directly
                        $enabledMethods = \Thelia\Model\ConfigQuery::read('cawlpayment_enabled_methods', '');

                        if (!empty($enabledMethods)) {
                            $methodCodes = array_map('trim', explode(',', $enabledMethods));

                            // Payment methods mapping
                            $paymentMethods = [
                                'visa' => ['name' => 'Visa', 'icon' => 'visa.svg'],
                                'mastercard' => ['name' => 'Mastercard', 'icon' => 'mastercard.svg'],
                                'cb' => ['name' => 'Carte Bancaire', 'icon' => 'cb.svg'],
                                'amex' => ['name' => 'American Express', 'icon' => 'amex.svg'],
                                'maestro' => ['name' => 'Maestro', 'icon' => 'maestro.svg'],
                                'applepay' => ['name' => 'Apple Pay', 'icon' => 'applepay.svg'],
                                'googlepay' => ['name' => 'Google Pay', 'icon' => 'googlepay.svg'],
                                'paypal' => ['name' => 'PayPal', 'icon' => 'paypal.svg'],
                            ];

                            foreach ($methodCodes as $code) {
                                if (isset($paymentMethods[$code])) {
                                    $moduleData['paymentIcons'][] = [
                                        'url' => '/cawlpayment/icon/' . $paymentMethods[$code]['icon'],
                                        'name' => $paymentMethods[$code]['name'],
                                        'alt' => $paymentMethods[$code]['name'],
                                    ];
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Silently ignore icon loading errors
                    }
                }

                $result[] = $moduleData;
            }

            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set payment module for the cart
     */
    #[Route('/api/set-payment-module', name: 'api_set_payment_module', methods: ['POST'])]
    public function setPaymentModule(Request $request): JsonResponse
    {
        $customer = $this->getCurrentCustomer($request);
        if (!$customer) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $moduleId = (int) ($data['moduleId'] ?? 0);

        if (!$moduleId) {
            return new JsonResponse(['error' => 'Module ID required'], 400);
        }

        try {
            $cart = $this->cartFacade->getOrCreateFromSession();
            $this->cartFacade->setPaymentModule(new CheckoutDTO(
                cart: $cart,
                paymentModuleId: $moduleId
            ));

            return new JsonResponse([
                'success' => true,
                'moduleId' => $moduleId
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cart summary with all totals
     */
    #[Route('/api/summary', name: 'api_summary', methods: ['GET'])]
    public function getSummary(Request $request): JsonResponse
    {
        try {
            $cart = $this->cartFacade->getOrCreateFromSession();

            $subtotal = 0;
            $itemCount = 0;
            foreach ($cart->getCartItems() as $item) {
                $price = $item->getPromo() ? $item->getPromoPrice() : $item->getPrice();
                $subtotal += $price * $item->getQuantity();
                $itemCount += $item->getQuantity();
            }

            // Get customer address IDs from cart_address table
            $deliveryAddressId = null;
            $invoiceAddressId = null;

            if ($cart->getAddressDeliveryId()) {
                $deliveryCartAddress = \Thelia\Model\CartAddressQuery::create()
                    ->findPk($cart->getAddressDeliveryId());
                if ($deliveryCartAddress) {
                    $deliveryAddressId = $deliveryCartAddress->getAddressId();
                }
            }

            if ($cart->getAddressInvoiceId()) {
                $invoiceCartAddress = \Thelia\Model\CartAddressQuery::create()
                    ->findPk($cart->getAddressInvoiceId());
                if ($invoiceCartAddress) {
                    $invoiceAddressId = $invoiceCartAddress->getAddressId();
                }
            }

            return new JsonResponse([
                'itemCount' => $itemCount,
                'subtotal' => $subtotal,
                'postage' => $cart->getPostage(),
                'discount' => $cart->getDiscount(),
                'total' => $subtotal + $cart->getPostage() - $cart->getDiscount(),
                'deliveryAddressId' => $deliveryAddressId,
                'invoiceAddressId' => $invoiceAddressId,
                'deliveryModuleId' => $cart->getDeliveryModuleId(),
                'paymentModuleId' => $cart->getPaymentModuleId(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate checkout and proceed to payment
     */
    #[Route('/pay', name: 'pay', methods: ['POST'])]
    public function pay(Request $request): Response
    {
        $customer = $this->getCurrentCustomer($request);
        if (!$customer) {
            return $this->redirect('/?view=login&redirect=checkout-payment');
        }

        try {
            $cart = $this->cartFacade->getOrCreateFromSession();

            // Set payment module from POST data if provided (fallback if AJAX failed)
            $paymentModuleId = (int) $request->request->get('paymentModuleId', 0);
            if ($paymentModuleId && !$cart->getPaymentModuleId()) {
                $this->cartFacade->setPaymentModule(new CheckoutDTO(
                    cart: $cart,
                    paymentModuleId: $paymentModuleId
                ));
                // Refresh cart to get updated payment module
                $cart = $this->cartFacade->getOrCreateFromSession();
            }

            // Validate all required fields
            if (!$cart->getAddressDeliveryId()) {
                return $this->redirect('/?view=checkout-delivery&error=delivery_address_required');
            }

            if (!$cart->getDeliveryModuleId()) {
                return $this->redirect('/?view=checkout-delivery&error=delivery_method_required');
            }

            if (!$cart->getPaymentModuleId()) {
                return $this->redirect('/?view=checkout-payment&error=payment_method_required');
            }

            // Process payment using CheckoutFacade
            $paymentResponse = $this->checkoutFacade->pay(new CheckoutDTO(
                cart: $cart,
                deliveryAddressId: $cart->getAddressDeliveryId(),
                invoiceAddressId: $cart->getAddressInvoiceId(),
                deliveryModuleId: $cart->getDeliveryModuleId(),
                paymentModuleId: $cart->getPaymentModuleId()
            ));

            // If payment module returns a response (e.g., redirect to external gateway), use it
            if ($paymentResponse !== null) {
                return $paymentResponse;
            }

            // Clear cart items after successful payment
            foreach ($cart->getCartItems() as $cartItem) {
                $cartItem->delete();
            }

            // Redirect to success page
            return $this->redirect('/?view=checkout-confirm');

        } catch (\Exception $e) {
            return $this->redirect('/?view=checkout-payment&error=' . urlencode($e->getMessage()));
        }
    }

    private function getCurrentCustomer(Request $request): ?Customer
    {
        $session = $request->getSession();
        $customerData = $session->get('thelia.customer_user');

        if ($customerData instanceof Customer) {
            return $customerData;
        }

        if (is_int($customerData) || is_numeric($customerData)) {
            $customer = \Thelia\Model\CustomerQuery::create()->findPk($customerData);
            if ($customer instanceof Customer) {
                $session->set('thelia.customer_user', $customer);
                return $customer;
            }
        }

        return null;
    }
}
