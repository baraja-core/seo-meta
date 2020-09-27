<?php

declare(strict_types=1);

namespace Baraja\SeoMeta;


interface OgImageResolver
{
	/**
	 * @param string $route in format [Module:Presenter:action] or [Presenter:action]
	 * @param mixed[] $parameters array from router without "presenter" and "action" key
	 * @return string|null return absolute URL or null in case of image is not available
	 */
	public function getUrl(string $route, array $parameters): ?string;
}
