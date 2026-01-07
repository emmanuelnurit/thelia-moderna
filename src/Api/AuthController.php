<?php

declare(strict_types=1);

namespace ModernaBundle\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\DefaultActionEvent;
use Thelia\Core\Event\LostPasswordEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Customer\Service\CustomerRememberMeService;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;

#[Route('/moderna-api/auth', name: 'moderna_api_auth_')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly CustomerRememberMeService $customerRememberMeService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        try {
            // Accept both JSON and form data
            $contentType = $request->headers->get('Content-Type', '');
            if (str_contains($contentType, 'application/json')) {
                $data = json_decode($request->getContent(), true) ?? [];
            } else {
                $data = $request->request->all();
            }

            $email = trim($data['email'] ?? '');
            $password = $data['password'] ?? '';
            $rememberMe = (bool) ($data['remember_me'] ?? false);

            // Validate required fields
            if (empty($email)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => Translator::getInstance()->trans('Email address is required')
                ], 400);
            }

            if (empty($password)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => Translator::getInstance()->trans('Password is required')
                ], 400);
            }

            // Find customer by email
            $customer = CustomerQuery::create()->findOneByEmail(strtolower($email));

            if ($customer === null) {
                return new JsonResponse([
                    'success' => false,
                    'error' => Translator::getInstance()->trans('Invalid email or password. Please try again.')
                ], 401);
            }

            // Verify password
            if (!$customer->checkPassword($password)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => Translator::getInstance()->trans('Invalid email or password. Please try again.')
                ], 401);
            }

            // Check if account is enabled (when email confirmation is required)
            if (ConfigQuery::isCustomerEmailConfirmationEnable() && !$customer->getEnable()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => Translator::getInstance()->trans('Your account is not yet confirmed. Please check your email for the confirmation link.'),
                    'not_confirmed' => true
                ], 403);
            }

            // Get anonymous cart BEFORE login event (which may associate it with customer)
            $session = $request->getSession();
            $anonymousCart = $this->getAnonymousCart($request);
            $anonymousCartId = $anonymousCart?->getId();

            // Get customer's saved cart BEFORE login event
            // This is the cart that was saved from a previous session
            // We exclude the current anonymous cart (if any) to avoid finding the same cart
            $savedCart = null;
            $customerCartsQuery = CartQuery::create()
                ->filterByCustomerId($customer->getId())
                ->orderByUpdatedAt('desc');

            // Exclude the anonymous cart from the search (it will be merged, not selected as saved)
            if ($anonymousCartId !== null) {
                $customerCartsQuery->filterById($anonymousCartId, \Propel\Runtime\ActiveQuery\Criteria::NOT_EQUAL);
            }

            $customerCarts = $customerCartsQuery->find();

            // First, try to find a cart with items
            foreach ($customerCarts as $cart) {
                if ($cart->countCartItems() > 0) {
                    $savedCart = $cart;
                    break;
                }
            }

            // If no cart with items, use the most recent cart (excluding anonymous)
            if ($savedCart === null && $customerCarts->count() > 0) {
                $savedCart = $customerCarts->getFirst();
            }

            // Dispatch the login event - this handles:
            // 1. Setting customer in security context
            // 2. Setting preferred language
            // 3. Associating current cart with customer
            $loginEvent = new CustomerLoginEvent($customer);
            $this->eventDispatcher->dispatch($loginEvent, TheliaEvents::CUSTOMER_LOGIN);

            // Determine final cart with merge logic
            $finalCart = $this->resolveCartsOnLogin($anonymousCart, $savedCart, $customer->getId());

            // Handle remember me cookie
            if ($rememberMe) {
                try {
                    // Ensure the customer has a remember_me_serial (old customers may not have one)
                    if (empty($customer->getRememberMeSerial())) {
                        $customer->setRememberMeSerial(uniqid('', true));
                        $customer->save();
                    }
                    // Reload customer to ensure we have fresh data after save
                    $customer->reload();
                    $this->customerRememberMeService->createRememberMeCookie($customer);
                } catch (\Exception $e) {
                    // Log error but don't fail login - remember me is optional
                    error_log('Remember me cookie error: ' . $e->getMessage());
                }
            }

            // Set final cart in session
            if ($finalCart !== null) {
                $session->set('thelia.cart_id', $finalCart->getId());
            }

            // Get cart item count for response
            $cartItemCount = 0;
            if ($finalCart !== null) {
                $cartItemCount = $finalCart->countCartItems();
            }

            return new JsonResponse([
                'success' => true,
                'message' => Translator::getInstance()->trans('Login successful'),
                'customer' => [
                    'id' => $customer->getId(),
                    'firstname' => $customer->getFirstname(),
                    'lastname' => $customer->getLastname(),
                    'email' => $customer->getEmail()
                ],
                'cart' => [
                    'item_count' => $cartItemCount
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => Translator::getInstance()->trans('An error occurred. Please try again.')
            ], 500);
        }
    }

    #[Route('/logout', name: 'logout', methods: ['POST', 'GET'])]
    public function logout(Request $request): JsonResponse
    {
        try {
            $session = $request->getSession();

            // Get customer ID before clearing (for wishlist save)
            $customerId = null;
            $customerData = $session->get('thelia.customer_user');
            if ($customerData instanceof \Thelia\Model\Customer) {
                $customerId = $customerData->getId();
            } elseif (is_numeric($customerData)) {
                $customerId = (int) $customerData;
            }

            // Clear remember me cookie FIRST using Thelia's native method
            // This uses setcookie() directly with matching parameters
            $cookieName = $this->customerRememberMeService->getRememberMeCookieName();
            if ($cookieName) {
                $this->customerRememberMeService->clearRememberMeCookie($cookieName);
            }

            // Dispatch CUSTOMER_LOGOUT event - this clears the security context
            $this->eventDispatcher->dispatch(new DefaultActionEvent(), TheliaEvents::CUSTOMER_LOGOUT);

            // Clear customer from session explicitly
            $session->remove('thelia.customer_user');

            // Also clear cart from session to force new cart on next visit
            $session->remove('thelia.cart_id');

            // Set flag to suppress remember_me auto-login for 10 seconds
            // This prevents reconnection when browser sends cookie before processing Set-Cookie header
            $session->set('suppress_remember_me_until', time() + 10);

            // Force session save
            $session->save();

            // Create response
            $response = new JsonResponse([
                'success' => true,
                'message' => Translator::getInstance()->trans('Logout successful'),
                'customer_id' => $customerId
            ]);

            // Also add cookie clearing to response headers as backup
            // Use Cookie object with exact same parameters as creation (no httpOnly, no secure, no sameSite)
            if ($cookieName) {
                $response->headers->setCookie(
                    Cookie::create($cookieName)
                        ->withValue('')
                        ->withExpires(time() - 3600)
                        ->withPath('/')
                        ->withSecure(false)
                        ->withHttpOnly(false)
                );
            }

            // Clear cart cookie to force new anonymous cart on next visit
            $cartCookieName = ConfigQuery::read('cart.cookie_name', 'thelia_cart');
            $response->headers->setCookie(
                Cookie::create($cartCookieName)
                    ->withValue('')
                    ->withExpires(time() - 3600)
                    ->withPath('/')
                    ->withSecure(false)
                    ->withHttpOnly(true)
            );

            return $response;

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => Translator::getInstance()->trans('An error occurred. Please try again.'),
                'debug_error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/check', name: 'check', methods: ['GET'])]
    public function checkAuth(Request $request): JsonResponse
    {
        try {
            // Check if customer is logged in via session
            $session = $request->getSession();
            $customerData = $session->get('thelia.customer_user');

            // Handle both object and ID (for backwards compatibility with corrupted sessions)
            if ($customerData !== null) {
                $customer = null;

                // If it's an ID (integer), fetch the customer object and fix the session
                if (is_int($customerData) || is_numeric($customerData)) {
                    $customer = CustomerQuery::create()->findPk($customerData);
                    // Fix the corrupted session by storing the object instead of ID
                    if ($customer instanceof \Thelia\Model\Customer) {
                        $session->set('thelia.customer_user', $customer);
                    } else {
                        // Invalid customer ID, clear the session
                        $session->remove('thelia.customer_user');
                    }
                } elseif ($customerData instanceof \Thelia\Model\Customer) {
                    $customer = $customerData;
                } else {
                    // Unknown type, clear corrupted session
                    $session->remove('thelia.customer_user');
                }

                if ($customer instanceof \Thelia\Model\Customer) {
                    return new JsonResponse([
                        'authenticated' => true,
                        'customer' => [
                            'id' => $customer->getId(),
                            'firstname' => $customer->getFirstname(),
                            'lastname' => $customer->getLastname(),
                            'email' => $customer->getEmail()
                        ]
                    ]);
                }
            }

            return new JsonResponse([
                'authenticated' => false
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'authenticated' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $email = trim($data['email'] ?? '');

            // Validate email
            if (empty($email)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => Translator::getInstance()->trans('Email address is required')
                ], 400);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => Translator::getInstance()->trans('Please enter a valid email address')
                ], 400);
            }

            // Check if customer exists (but don't reveal this to the user for security)
            $customer = CustomerQuery::create()->findOneByEmail(strtolower($email));

            if ($customer !== null) {
                // Dispatch the lost password event - this generates a new password and sends email
                $event = new LostPasswordEvent($email);
                $this->eventDispatcher->dispatch($event, TheliaEvents::LOST_PASSWORD);
            }

            // Always return success to prevent email enumeration
            return new JsonResponse([
                'success' => true,
                'message' => Translator::getInstance()->trans('If your email address exists in our database, you will receive a new password in a few minutes.')
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => Translator::getInstance()->trans('An error occurred. Please try again.')
            ], 500);
        }
    }

    /**
     * Get anonymous cart from cookie token (before login event modifies it)
     */
    private function getAnonymousCart(Request $request): ?Cart
    {
        $cartCookieName = ConfigQuery::read('cart.cookie_name', 'thelia_cart');
        $cartToken = $request->cookies->get($cartCookieName);

        if (empty($cartToken)) {
            return null;
        }

        $cart = CartQuery::create()
            ->filterByToken($cartToken)
            ->findOne();

        // Only return if it's truly anonymous (no customer) or has items
        if ($cart !== null && $cart->countCartItems() > 0) {
            return $cart;
        }

        return null;
    }

    /**
     * Resolve which cart to use on login, with merge logic
     */
    private function resolveCartsOnLogin(?Cart $anonymousCart, ?Cart $savedCart, int $customerId): ?Cart
    {
        // Case 1: Both carts exist and are different - merge them
        if ($savedCart !== null && $anonymousCart !== null && $savedCart->getId() !== $anonymousCart->getId()) {
            $this->mergeCarts($anonymousCart, $savedCart);

            // Delete the anonymous cart after merge
            $anonymousCart->delete();

            return $savedCart;
        }

        // Case 2: Only saved cart exists
        if ($savedCart !== null) {
            return $savedCart;
        }

        // Case 3: Only anonymous cart exists - associate with customer
        if ($anonymousCart !== null) {
            $anonymousCart->setCustomerId($customerId);
            $anonymousCart->save();
            return $anonymousCart;
        }

        // Case 4: No cart at all
        return null;
    }

    /**
     * Merge items from source cart into target cart
     * Quantities are added if same product/PSE exists
     */
    private function mergeCarts(Cart $source, Cart $target): void
    {
        foreach ($source->getCartItems() as $sourceItem) {
            // Check if same product (PSE) exists in target cart
            $existingItem = CartItemQuery::create()
                ->filterByCartId($target->getId())
                ->filterByProductSaleElementsId($sourceItem->getProductSaleElementsId())
                ->findOne();

            if ($existingItem !== null) {
                // Add quantities together
                $existingItem->setQuantity(
                    $existingItem->getQuantity() + $sourceItem->getQuantity()
                );
                $existingItem->save();
            } else {
                // Copy item to target cart
                $newItem = new CartItem();
                $newItem->setCartId($target->getId());
                $newItem->setProductId($sourceItem->getProductId());
                $newItem->setProductSaleElementsId($sourceItem->getProductSaleElementsId());
                $newItem->setQuantity($sourceItem->getQuantity());
                $newItem->setPrice($sourceItem->getPrice());
                $newItem->setPromoPrice($sourceItem->getPromoPrice());
                $newItem->setPromo($sourceItem->getPromo());
                $newItem->save();
            }
        }

        // Update target cart timestamp
        $target->setUpdatedAt(new \DateTime());
        $target->save();
    }
}
