<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace Moderna\UiComponents\Account\CustomerUpdate;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\CustomerQuery;
use Thelia\Model\CustomerTitleQuery;

/**
 * LiveComponent for customer profile update.
 *
 * This component handles:
 * - Customer profile display and editing
 * - Password change
 * - Form validation
 * - Success/error messages
 *
 * Usage in Twig:
 * {{ component('Moderna:Account:CustomerUpdate') }}
 */
#[AsLiveComponent(
    name: 'Moderna:Account:CustomerUpdate',
    template: '@UiComponents/Account/CustomerUpdate/CustomerUpdate.html.twig'
)]
class CustomerUpdate
{
    use DefaultActionTrait;

    /**
     * Customer title ID.
     */
    #[LiveProp(writable: true)]
    public int $titleId = 1;

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
     * Email address.
     */
    #[LiveProp(writable: true)]
    public string $email = '';

    /**
     * Current password (for validation when changing).
     */
    #[LiveProp(writable: true)]
    public string $currentPassword = '';

    /**
     * New password.
     */
    #[LiveProp(writable: true)]
    public string $newPassword = '';

    /**
     * Password confirmation.
     */
    #[LiveProp(writable: true)]
    public string $confirmPassword = '';

    /**
     * Whether password change section is visible.
     */
    #[LiveProp]
    public bool $changePassword = false;

    /**
     * Newsletter subscription.
     */
    #[LiveProp(writable: true)]
    public bool $newsletter = false;

    /**
     * Error message.
     */
    #[LiveProp]
    public ?string $error = null;

    /**
     * Success message.
     */
    #[LiveProp]
    public ?string $success = null;

    /**
     * Field-specific errors.
     *
     * @var array<string, string>
     */
    #[LiveProp]
    public array $fieldErrors = [];

    /**
     * Available customer titles.
     *
     * @var array<int, array>
     */
    #[LiveProp]
    public array $titles = [];

    public function __construct(
        private readonly Session $session,
    ) {}

    /**
     * Initialize component with current customer data.
     */
    public function mount(): void
    {
        $this->loadTitles();
        $this->loadCustomerData();
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
    }

    /**
     * Load current customer data.
     */
    private function loadCustomerData(): void
    {
        $customer = $this->session->getCustomerUser();
        if (!$customer) {
            return;
        }

        $this->titleId = $customer->getTitleId();
        $this->firstname = $customer->getFirstname() ?? '';
        $this->lastname = $customer->getLastname() ?? '';
        $this->email = $customer->getEmail() ?? '';
        $this->newsletter = (bool) $customer->getNewsletter();
    }

    /**
     * Toggle password change section visibility.
     */
    #[LiveAction]
    public function toggleChangePassword(): void
    {
        $this->changePassword = !$this->changePassword;
        if (!$this->changePassword) {
            // Clear password fields when hiding
            $this->currentPassword = '';
            $this->newPassword = '';
            $this->confirmPassword = '';
            unset($this->fieldErrors['currentPassword']);
            unset($this->fieldErrors['newPassword']);
            unset($this->fieldErrors['confirmPassword']);
        }
    }

    /**
     * Validate the form data.
     */
    private function validate(): bool
    {
        $this->fieldErrors = [];

        // Required fields
        if (empty(trim($this->firstname))) {
            $this->fieldErrors['firstname'] = 'First name is required';
        }

        if (empty(trim($this->lastname))) {
            $this->fieldErrors['lastname'] = 'Last name is required';
        }

        if (empty(trim($this->email))) {
            $this->fieldErrors['email'] = 'Email is required';
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $this->fieldErrors['email'] = 'Please enter a valid email address';
        } else {
            // Check if email is already used by another customer
            $customer = $this->session->getCustomerUser();
            $existingCustomer = CustomerQuery::create()
                ->filterByEmail($this->email)
                ->filterById($customer->getId(), \Propel\Runtime\ActiveQuery\Criteria::NOT_EQUAL)
                ->findOne();

            if ($existingCustomer) {
                $this->fieldErrors['email'] = 'This email is already in use';
            }
        }

        // Password validation (only if changing)
        if ($this->changePassword) {
            if (empty($this->currentPassword)) {
                $this->fieldErrors['currentPassword'] = 'Current password is required';
            }

            if (empty($this->newPassword)) {
                $this->fieldErrors['newPassword'] = 'New password is required';
            } elseif (strlen($this->newPassword) < 8) {
                $this->fieldErrors['newPassword'] = 'Password must be at least 8 characters';
            }

            if ($this->newPassword !== $this->confirmPassword) {
                $this->fieldErrors['confirmPassword'] = 'Passwords do not match';
            }
        }

        return empty($this->fieldErrors);
    }

    /**
     * Submit the profile update form.
     */
    #[LiveAction]
    public function submit(): void
    {
        $this->error = null;
        $this->success = null;

        $customer = $this->session->getCustomerUser();
        if (!$customer) {
            $this->error = 'You must be logged in';
            return;
        }

        if (!$this->validate()) {
            $this->error = 'Please correct the errors below';
            return;
        }

        try {
            // Verify current password if changing password
            if ($this->changePassword) {
                if (!password_verify($this->currentPassword, $customer->getPassword())) {
                    $this->fieldErrors['currentPassword'] = 'Current password is incorrect';
                    $this->error = 'Please correct the errors below';
                    return;
                }
            }

            // Update customer data
            $customer->setTitleId($this->titleId);
            $customer->setFirstname($this->firstname);
            $customer->setLastname($this->lastname);
            $customer->setEmail($this->email);
            $customer->setNewsletter($this->newsletter ? 1 : 0);

            // Update password if requested
            if ($this->changePassword && !empty($this->newPassword)) {
                $customer->setPassword(password_hash($this->newPassword, PASSWORD_BCRYPT));
            }

            $customer->save();

            // Clear password fields
            $this->currentPassword = '';
            $this->newPassword = '';
            $this->confirmPassword = '';
            $this->changePassword = false;

            $this->success = 'Your profile has been updated successfully';
        } catch (\Exception $e) {
            $this->error = 'An error occurred while updating your profile';
        }
    }

    /**
     * Check if form has errors.
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

    /**
     * Check if user is logged in.
     */
    public function isLoggedIn(): bool
    {
        return $this->session->getCustomerUser() !== null;
    }
}
