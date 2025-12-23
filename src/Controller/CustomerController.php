<?php

declare(strict_types=1);

namespace FlexyBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Domain\Customer\DTO\CustomerRegisterDTO;
use Thelia\Domain\Customer\Service\CustomerRegistrationService;
use Thelia\Model\CustomerQuery;

#[Route('/moderna-api/customer', name: 'moderna_customer_')]
class CustomerController extends AbstractController
{
    public function __construct(
        private readonly CustomerRegistrationService $customerRegistrationService
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validate required fields
            $errors = [];

            if (empty($data['firstname'])) {
                $errors[] = 'First name is required';
            }
            if (empty($data['lastname'])) {
                $errors[] = 'Last name is required';
            }
            if (empty($data['email'])) {
                $errors[] = 'Email is required';
            }
            if (empty($data['password'])) {
                $errors[] = 'Password is required';
            }

            if (!empty($errors)) {
                return new JsonResponse([
                    'success' => false,
                    'errors' => $errors
                ], 400);
            }

            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return new JsonResponse([
                    'success' => false,
                    'errors' => ['Please enter a valid email address']
                ], 400);
            }

            // Check if email already exists
            $existingCustomer = CustomerQuery::create()->findOneByEmail($data['email']);
            if ($existingCustomer !== null) {
                return new JsonResponse([
                    'success' => false,
                    'errors' => ['This email address is already registered']
                ], 400);
            }

            // Validate password length
            if (strlen($data['password']) < 8) {
                return new JsonResponse([
                    'success' => false,
                    'errors' => ['Password must be at least 8 characters']
                ], 400);
            }

            // Create customer using Thelia's registration service
            $customer = $this->customerRegistrationService->registerCustomer(
                new CustomerRegisterDTO(
                    firstname: trim($data['firstname']),
                    lastname: trim($data['lastname']),
                    email: strtolower(trim($data['email'])),
                    password: $data['password']
                )
            );

            return new JsonResponse([
                'success' => true,
                'customerId' => $customer->getId(),
                'message' => 'Account created successfully'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }
}
