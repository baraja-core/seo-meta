<?php

declare(strict_types=1);

namespace Baraja\SeoMeta;


use Baraja\SmartRouter\SmartRouter;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\PhpGenerator\ClassType;

final class SeoMetaExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition('baraja.seoMetaManager')
			->setFactory(SeoMetaManager::class)
			->setAutowired(SeoMetaManager::class);
	}


	public function afterCompile(ClassType $class): void
	{
		$builder = $this->getContainerBuilder();

		$smartRouter = $builder->getDefinitionByType(SmartRouter::class);
		assert($smartRouter instanceof ServiceDefinition);

		$seoMetaManager = $builder->getDefinitionByType(SeoMetaManager::class);
		assert($seoMetaManager instanceof ServiceDefinition);

		$class->getMethod('initialize')->addBody(
			'// seo meta.' . "\n"
			. '(function () {' . "\n"
			. "\t" . '$this->getService(?)->addAfterMatchEvent($this->getService(?));' . "\n"
			. '})();',
			[
				$smartRouter->getName(),
				$seoMetaManager->getName(),
			],
		);
	}
}
