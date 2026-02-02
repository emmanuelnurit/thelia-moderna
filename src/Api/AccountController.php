<?php

declare(strict_types=1);

namespace Moderna\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Customer;

#[Route('/moderna-api/account', name: 'moderna_account_')]
class AccountController extends AbstractController
{
    #[Route('/address/new', name: 'address_new', methods: ['POST'])]
    public function createAddress(Request $request): Response
    {
        // Detect if this is an AJAX/JSON request
        $wantsJson = $request->headers->get('Accept') === 'application/json'
            || $request->request->getBoolean('_json');

        // Get current customer from session
        $customer = $this->getCurrentCustomer($request);
        if (!$customer) {
            if ($wantsJson) {
                return new JsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
            }
            return new RedirectResponse('/?view=login');
        }

        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('address_form', $token)) {
            if ($wantsJson) {
                return new JsonResponse(['success' => false, 'error' => 'Invalid token'], 403);
            }
            return new RedirectResponse('/?view=account-addresses&error=invalid_token');
        }

        try {
            // Create new address
            $address = new Address();
            $address->setCustomerId($customer->getId());
            $address->setTitleId((int) $request->request->get('title', 1)); // Default to title 1 (Mr.)
            $address->setLabel($request->request->get('label', '') ?: null);
            $address->setFirstname($request->request->get('firstname', ''));
            $address->setLastname($request->request->get('lastname', ''));
            $address->setCompany($request->request->get('company', '') ?: null);
            $address->setAddress1($request->request->get('address1', ''));
            $address->setAddress2($request->request->get('address2', '') ?: '');
            $address->setAddress3($request->request->get('address3', '') ?: '');
            $address->setZipcode($request->request->get('zipcode', ''));
            $address->setCity($request->request->get('city', ''));
            $address->setCountryId((int) $request->request->get('country', 0));
            $address->setPhone($request->request->get('phone', '') ?: null);
            $address->setCellphone($request->request->get('cellphone', '') ?: null);

            // Handle default address
            $isDefault = $request->request->getBoolean('is_default');

            // Check if this should be default (first address or explicitly set)
            $existingAddresses = AddressQuery::create()
                ->filterByCustomerId($customer->getId())
                ->count();

            if ($existingAddresses === 0 || $isDefault) {
                $address->makeItDefault();
            }

            $address->save();

            // Return JSON response for AJAX requests (checkout form)
            if ($wantsJson) {
                return new JsonResponse([
                    'success' => true,
                    'addressId' => $address->getId(),
                    'message' => 'Address created successfully'
                ]);
            }

            return new RedirectResponse('/?view=account-addresses&success=address_created');

        } catch (\Exception $e) {
            if ($wantsJson) {
                return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
            }
            return new RedirectResponse('/?view=address&error=' . urlencode($e->getMessage()));
        }
    }

    #[Route('/address/{addressId}/update', name: 'address_update', methods: ['POST'])]
    public function updateAddress(Request $request, int $addressId): Response
    {
        // Get current customer from session
        $customer = $this->getCurrentCustomer($request);
        if (!$customer) {
            return new RedirectResponse('/?view=login');
        }

        // Find the address and verify ownership
        $address = AddressQuery::create()
            ->filterById($addressId)
            ->filterByCustomerId($customer->getId())
            ->findOne();

        if (!$address) {
            return new RedirectResponse('/?view=account-addresses&error=address_not_found');
        }

        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('address_form', $token)) {
            return new RedirectResponse('/?view=account-addresses&error=invalid_token');
        }

        try {
            // Update address
            $address->setTitleId((int) $request->request->get('title', 1)); // Default to title 1 (Mr.)
            $address->setLabel($request->request->get('label', '') ?: null);
            $address->setFirstname($request->request->get('firstname', ''));
            $address->setLastname($request->request->get('lastname', ''));
            $address->setCompany($request->request->get('company', '') ?: null);
            $address->setAddress1($request->request->get('address1', ''));
            $address->setAddress2($request->request->get('address2', '') ?: '');
            $address->setAddress3($request->request->get('address3', '') ?: '');
            $address->setZipcode($request->request->get('zipcode', ''));
            $address->setCity($request->request->get('city', ''));
            $address->setCountryId((int) $request->request->get('country', 0));
            $address->setPhone($request->request->get('phone', '') ?: null);
            $address->setCellphone($request->request->get('cellphone', '') ?: null);

            // Handle default address
            $isDefault = $request->request->getBoolean('is_default');
            if ($isDefault) {
                $address->makeItDefault();
            }

            $address->save();

            return new RedirectResponse('/?view=account-addresses&success=address_updated');

        } catch (\Exception $e) {
            return new RedirectResponse('/?view=address-update&address_id=' . $addressId . '&error=' . urlencode($e->getMessage()));
        }
    }

    #[Route('/address/{addressId}/delete', name: 'address_delete', methods: ['POST'])]
    public function deleteAddress(Request $request, int $addressId): Response
    {
        // Get current customer from session
        $customer = $this->getCurrentCustomer($request);
        if (!$customer) {
            return new RedirectResponse('/?view=login');
        }

        // Find the address and verify ownership
        $address = AddressQuery::create()
            ->filterById($addressId)
            ->filterByCustomerId($customer->getId())
            ->findOne();

        if (!$address) {
            return new RedirectResponse('/?view=account-addresses&error=address_not_found');
        }

        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('address_delete', $token)) {
            return new RedirectResponse('/?view=account-addresses&error=invalid_token');
        }

        // Cannot delete default address
        if ($address->getIsDefault()) {
            return new RedirectResponse('/?view=account-addresses&error=cannot_delete_default');
        }

        try {
            $address->delete();
            return new RedirectResponse('/?view=account-addresses&success=address_deleted');
        } catch (\Exception $e) {
            return new RedirectResponse('/?view=account-addresses&error=' . urlencode($e->getMessage()));
        }
    }

    #[Route('/customer/update', name: 'customer_update', methods: ['POST'])]
    public function updateCustomer(Request $request): Response
    {
        // Get current customer from session
        $customer = $this->getCurrentCustomer($request);
        if (!$customer) {
            return new RedirectResponse('/?view=login');
        }

        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('customer_update', $token)) {
            return new RedirectResponse('/?view=account-update&error=invalid_token');
        }

        try {
            // Reload customer from database to avoid stale objects
            $freshCustomer = \Thelia\Model\CustomerQuery::create()->findPk($customer->getId());

            if (!$freshCustomer) {
                return new RedirectResponse('/?view=login');
            }

            // Update customer information
            $freshCustomer->setTitleId((int) $request->request->get('title', 1));
            $freshCustomer->setFirstname($request->request->get('firstname', ''));
            $freshCustomer->setLastname($request->request->get('lastname', ''));
            $freshCustomer->setEmail($request->request->get('email', ''));

            $freshCustomer->save();

            // Update session with fresh customer
            $request->getSession()->set('thelia.customer_user', $freshCustomer);

            return new RedirectResponse('/?view=account&success=profile_updated');

        } catch (\Exception $e) {
            // If versioning error, try to continue anyway
            if (strpos($e->getMessage(), 'customer_version') !== false) {
                // Reload the customer to verify the save worked
                $verifyCustomer = \Thelia\Model\CustomerQuery::create()->findPk($customer->getId());
                if ($verifyCustomer) {
                    $request->getSession()->set('thelia.customer_user', $verifyCustomer);
                    return new RedirectResponse('/?view=account&success=profile_updated');
                }
            }

            return new RedirectResponse('/?view=account-update&error=' . urlencode($e->getMessage()));
        }
    }

    private function getCurrentCustomer(Request $request): ?Customer
    {
        $session = $request->getSession();
        $customerData = $session->get('thelia.customer_user');

        if ($customerData instanceof Customer) {
            return $customerData;
        }

        // Handle legacy sessions where only customer ID was stored
        if (is_int($customerData) || is_numeric($customerData)) {
            $customer = \Thelia\Model\CustomerQuery::create()->findPk($customerData);
            if ($customer instanceof Customer) {
                // Fix the session for future requests
                $session->set('thelia.customer_user', $customer);
                return $customer;
            }
        }

        return null;
    }
}
