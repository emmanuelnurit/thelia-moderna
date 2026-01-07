<?php

declare(strict_types=1);

namespace ModernaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Model\AddressQuery;
use Thelia\Core\Translation\Translator;

#[Route('/moderna-api/account/address', name: 'moderna_address_')]
class AddressController extends AbstractController
{
    #[Route('/{id}/set-default', name: 'set_default', methods: ['POST'])]
    public function setDefault(int $id, Request $request): JsonResponse
    {
        try {
            // Get the customer ID from session
            $session = $request->getSession();
            $customer = $session->getCustomerUser();

            if (!$customer) {
                return new JsonResponse([
                    'success' => false,
                    'error' => Translator::getInstance()->trans('You must be logged in')
                ], 401);
            }

            // Find the address
            $address = AddressQuery::create()
                ->filterById($id)
                ->filterByCustomerId($customer->getId())
                ->findOne();

            if (!$address) {
                return new JsonResponse([
                    'success' => false,
                    'error' => Translator::getInstance()->trans('Address not found')
                ], 404);
            }

            // Unset all other addresses as default for this customer
            AddressQuery::create()
                ->filterByCustomerId($customer->getId())
                ->update(['IsDefault' => 0]);

            // Set this address as default
            $address->setIsDefault(1);
            $address->save();


            return new JsonResponse([
                'success' => true,
                'message' => Translator::getInstance()->trans('Address set as default successfully')
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => Translator::getInstance()->trans('An error occurred: %msg', ['%msg' => $e->getMessage()])
            ], 500);
        }
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        try {
            // Get the customer ID from session
            $session = $request->getSession();
            $customer = $session->getCustomerUser();

            if (!$customer) {
                return new JsonResponse([
                    'success' => false,
                    'error' => Translator::getInstance()->trans('You must be logged in')
                ], 401);
            }

            // Find the address
            $address = AddressQuery::create()
                ->filterById($id)
                ->filterByCustomerId($customer->getId())
                ->findOne();

            if (!$address) {
                return new JsonResponse([
                    'success' => false,
                    'error' => Translator::getInstance()->trans('Address not found')
                ], 404);
            }

            // Delete the address
            $address->delete();


            // Redirect back to addresses page
            return $this->redirect('/?view=account-addresses');

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => Translator::getInstance()->trans('An error occurred: %msg', ['%msg' => $e->getMessage()])
            ], 500);
        }
    }
}
