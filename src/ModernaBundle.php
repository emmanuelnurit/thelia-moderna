<?php

declare(strict_types=1);

/*
 * Modern.A Template Bundle
 * Provides Twig extensions and UI components for the template
 */

namespace ModernaBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\ConfigQuery;

class ModernaBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Only load services if moderna template is active
        if ($this->getActiveTemplate() !== 'moderna') {
            return;
        }

        $serviceConfigurator = $container->services();

        $resourcePath = $this->getResourcePath();
        if (is_dir($resourcePath)) {
            $serviceConfigurator->load('ModernaBundle\\', $resourcePath)
                ->autowire()
                ->autoconfigure();
        }

        $uiComponentsPath = $this->getUiComponentsPath();
        if (is_dir($uiComponentsPath)) {
            $serviceConfigurator->load('ModernaBundle\\UiComponents\\', $uiComponentsPath)
                ->autowire()
                ->autoconfigure();
        }

    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Only load config if moderna template is active
        if ($this->getActiveTemplate() !== 'moderna') {
            return;
        }

        $configPath = $this->getConfigPath();
        if (is_dir($configPath)) {
            $container->import($configPath . '/*.yaml');
        }
    }

    private function getResourcePath(): string
    {
        return THELIA_TEMPLATE_DIR . TemplateDefinition::FRONT_OFFICE_SUBDIR . DS . ConfigQuery::read(TemplateDefinition::FRONT_OFFICE_CONFIG_NAME, 'default') . DS . 'src';
    }

    private function getUiComponentsPath(): string
    {
        return $this->getResourcePath() . DS . 'UiComponents';
    }

    private function getConfigPath(): string
    {
        return THELIA_TEMPLATE_DIR . TemplateDefinition::FRONT_OFFICE_SUBDIR . DS . ConfigQuery::read(TemplateDefinition::FRONT_OFFICE_CONFIG_NAME, 'default') . DS . 'config' . DS . 'packages';
    }

    private function getActiveTemplate(): string
    {
        return ConfigQuery::read(TemplateDefinition::FRONT_OFFICE_CONFIG_NAME, 'default');
    }
}
