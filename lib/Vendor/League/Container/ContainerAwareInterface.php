<?php

declare(strict_types=1);

namespace BizExaminer\LearnDashExtension\Vendor\League\Container;

interface ContainerAwareInterface
{
    public function getContainer(): DefinitionContainerInterface;
    public function setContainer(DefinitionContainerInterface $container): ContainerAwareInterface;
}
