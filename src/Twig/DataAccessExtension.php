<?php

/*
 * Modern.A Template - Data Access Extension
 * Provides resources(), loop(), loopCount() and attr() Twig functions
 */

namespace Moderna\Twig;

use Psr\Cache\InvalidArgumentException;
use Thelia\Api\Service\DataAccess\AttributeAccessService;
use Thelia\Api\Service\DataAccess\DataAccessService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DataAccessExtension extends AbstractExtension
{
    public function __construct(
        private readonly DataAccessService $dataAccessService,
        private readonly AttributeAccessService $attributeAccessService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('resources', [$this, 'resources']),
            new TwigFunction('loop', [$this, 'getLoop']),
            new TwigFunction('loopCount', [$this, 'getLoopCount']),
            new TwigFunction('attr', [$this, 'attribute']),
        ];
    }

    /**
     * @throws \JsonException
     * @throws InvalidArgumentException
     */
    public function resources(string $path, array $params = []): array|object|null
    {
        return $this->dataAccessService->resources($path, $params);
    }

    public function attribute(string $type, string $attributeName): mixed
    {
        $methodName = 'attribute'.ucfirst($type);
        if (!method_exists($this->attributeAccessService, $methodName)) {
            throw new \RuntimeException(sprintf('Method %s not found in %s', $methodName, DataAccessService::class));
        }

        return $this->attributeAccessService->$methodName($attributeName);
    }

    public function getLoop(string $name, string $type, array $params = []): array
    {
        return $this->dataAccessService->loop($name, $type, $params);
    }

    public function getLoopCount(string $type, array $params = []): int
    {
        return $this->dataAccessService->loopCount($type, $params);
    }
}
