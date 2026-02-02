<?php

declare(strict_types=1);

namespace Moderna\Tests\Functional;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

/**
 * Functional tests to verify Live Components are properly configured.
 */
class LiveComponentAttributesTest extends TestCase
{
    /**
     * List of Live Component classes to verify.
     */
    private const LIVE_COMPONENTS = [
        \Moderna\UiComponents\Cart\Cart::class,
        \Moderna\UiComponents\Cart\CartDrawer::class,
        \Moderna\UiComponents\Cart\CartItem::class,
        \Moderna\UiComponents\Checkout\AddressForm\AddressForm::class,
        \Moderna\UiComponents\Checkout\Delivery\Delivery::class,
        \Moderna\UiComponents\Checkout\Payment\Payment::class,
        \Moderna\UiComponents\Checkout\PromoCode\PromoCode::class,
        \Moderna\UiComponents\Checkout\Summary\Summary::class,
        \Moderna\UiComponents\Toast\AddToCartToast::class,
        \Moderna\UiComponents\Product\VariantSelector\VariantSelector::class,
        \Moderna\UiComponents\Layout\SearchModal\SearchModal::class,
        \Moderna\UiComponents\Account\AddressList\AddressList::class,
        \Moderna\UiComponents\Account\CustomerUpdate\CustomerUpdate::class,
    ];

    /**
     * @dataProvider liveComponentProvider
     */
    public function testLiveComponentHasAsLiveComponentAttribute(string $className): void
    {
        if (!class_exists($className)) {
            $this->markTestSkipped(sprintf('Class %s does not exist', $className));
        }

        $reflection = new ReflectionClass($className);
        $attributes = $reflection->getAttributes(AsLiveComponent::class);

        $this->assertNotEmpty(
            $attributes,
            sprintf('Class %s should have #[AsLiveComponent] attribute', $className)
        );
    }

    /**
     * @dataProvider liveComponentProvider
     */
    public function testLiveComponentHasModernaNamePrefix(string $className): void
    {
        if (!class_exists($className)) {
            $this->markTestSkipped(sprintf('Class %s does not exist', $className));
        }

        $reflection = new ReflectionClass($className);
        $attributes = $reflection->getAttributes(AsLiveComponent::class);

        if (empty($attributes)) {
            $this->markTestSkipped(sprintf('Class %s does not have AsLiveComponent attribute', $className));
        }

        $attribute = $attributes[0]->newInstance();
        $name = $attribute->name;

        $this->assertStringStartsWith(
            'Moderna:',
            $name,
            sprintf('Live Component %s should have name starting with "Moderna:", got "%s"', $className, $name)
        );
    }

    /**
     * @dataProvider liveComponentProvider
     */
    public function testLiveComponentHasTemplate(string $className): void
    {
        if (!class_exists($className)) {
            $this->markTestSkipped(sprintf('Class %s does not exist', $className));
        }

        $reflection = new ReflectionClass($className);
        $attributes = $reflection->getAttributes(AsLiveComponent::class);

        if (empty($attributes)) {
            $this->markTestSkipped(sprintf('Class %s does not have AsLiveComponent attribute', $className));
        }

        $attribute = $attributes[0]->newInstance();
        $template = $attribute->template;

        $this->assertNotNull(
            $template,
            sprintf('Live Component %s should have a template defined', $className)
        );

        $this->assertStringContainsString(
            'frontOffice/moderna',
            $template,
            sprintf('Template path should contain "frontOffice/moderna", got "%s"', $template)
        );
    }

    /**
     * @dataProvider liveComponentProvider
     */
    public function testLiveComponentUsesDefaultActionTrait(string $className): void
    {
        if (!class_exists($className)) {
            $this->markTestSkipped(sprintf('Class %s does not exist', $className));
        }

        $reflection = new ReflectionClass($className);
        $traits = $reflection->getTraitNames();

        $this->assertContains(
            'Symfony\UX\LiveComponent\DefaultActionTrait',
            $traits,
            sprintf('Live Component %s should use DefaultActionTrait', $className)
        );
    }

    public static function liveComponentProvider(): array
    {
        $data = [];
        foreach (self::LIVE_COMPONENTS as $className) {
            $shortName = (new ReflectionClass($className))->getShortName();
            $data[$shortName] = [$className];
        }
        return $data;
    }

    public function testAllLiveComponentsAreRegisteredInServices(): void
    {
        $servicesPath = __DIR__ . '/../../config/packages/services.yaml';

        if (!file_exists($servicesPath)) {
            $this->markTestSkipped('services.yaml not found');
        }

        $servicesContent = file_get_contents($servicesPath);

        foreach (self::LIVE_COMPONENTS as $className) {
            if (!class_exists($className)) {
                continue;
            }

            // Extract the namespace path (e.g., Moderna\UiComponents\Cart\Cart)
            $this->assertStringContainsString(
                str_replace('\\', '\\', $className),
                $servicesContent,
                sprintf('Class %s should be registered in services.yaml', $className)
            );
        }
    }
}
