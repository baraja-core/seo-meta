<?php

declare(strict_types=1);

namespace Baraja\SeoMeta;


use Baraja\Localization\Localization;
use Baraja\SmartRouter\AfterMatchEvent;
use Baraja\SmartRouter\MetaData;
use Baraja\SmartRouter\SmartRouter;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Http\UrlScript;

final class SeoMetaManager implements AfterMatchEvent
{
	private Cache $cache;

	private ?OgImageResolver $ogImageResolver = null;

	private ?UrlScript $url = null;

	/** @var mixed[]|null */
	private ?array $match = null;


	public function __construct(
		Storage $storage,
		private Localization $localization,
		private SmartRouter $smartRouter,
		private LinkGenerator $linkGenerator,
	) {
		$this->cache = new Cache($storage, 'baraja-seo-meta-manager');
	}


	/**
	 * @param mixed[] $match
	 */
	public function matched(UrlScript $url, array $match): void
	{
		$this->url = $url;
		$this->match = $match;
	}


	public function isOk(): bool
	{
		return $this->url !== null && $this->match !== null;
	}


	public function cleanCache(): void
	{
		$this->cache->clean([Cache::ALL => true]);
	}


	public function setOgImageResolver(OgImageResolver $resolver): void
	{
		$this->ogImageResolver = $resolver;
	}


	public function getHtml(): ?string
	{
		if ($this->url === null || $this->match === null) {
			throw new \RuntimeException('Can not compile HTML meta tags, because SeoMetaManager has not been registered to SmartRouter or router does not match this request.');
		}
		$cacheKey = $this->url->getPathInfo() . "\x00" . $this->getLocale();
		$cache = $this->cache->load($cacheKey);
		if ($cache !== null) {
			return $cache;
		}

		$meta = $this->smartRouter->getRewriter()->getMetaData($this->url->getPathInfo(), $this->getLocale());
		if ($meta->getId() === null) {
			return null;
		}

		$tags = [];
		$metaTitle = $this->getTitle();
		if ($metaTitle !== null) {
			$tags[] = '<title>' . Helpers::escapeHtml($metaTitle) . '</title>';
		} else {
			trigger_error('Possible bug: Meta title for page "/' . $this->url->getPathInfo() . '" is not available.');
		}
		$metaDescription = $meta->getMetaDescription();
		if ($metaDescription !== null) {
			$tags[] = '<meta name="description" content="' . Helpers::escapeHtmlAttr($metaDescription) . '">';
		}
		$ogTitle = $meta->getOgTitle();
		if ($ogTitle !== null) {
			$tags[] = '<meta property="og:title" content="' . Helpers::escapeHtmlAttr($ogTitle) . '">';
		}
		$ogDescription = $meta->getOgDescription();
		if ($ogDescription !== null) {
			$tags[] = '<meta property="og:description" content="' . Helpers::escapeHtmlAttr($ogDescription) . '">';
		}

		$robotsPolicy = [];
		if ($meta->isNoIndex() === true) {
			$robotsPolicy[] = 'noindex';
		}
		if ($meta->isNoFollow() === true) {
			$robotsPolicy[] = 'nofollow';
		}
		if ($robotsPolicy !== []) { // more info: https://developers.google.com/search/reference/robots_meta_tag
			$tags[] = '<meta name="robots" content="' . Helpers::escapeHtmlAttr(implode(', ', $robotsPolicy)) . '">';
		}

		$alternateParams = $this->match;
		$alternateRoute = $alternateParams['presenter'] . ':' . $alternateParams['action'];
		unset($alternateParams['presenter'], $alternateParams['action']);
		foreach ($this->localization->getAvailableLocales() as $availableLocale) {
			try {
				$alternateUrl = $this->linkGenerator->link($alternateRoute, array_merge($alternateParams, [
					'locale' => $availableLocale,
				]));
				$tags[] = '<link rel="alternate" href="' . Helpers::escapeHtmlAttr($alternateUrl) . '" hreflang="' . Helpers::escapeHtmlAttr($availableLocale) . '">';
			} catch (InvalidLinkException) {
			}
		}

		if ($this->ogImageResolver !== null) {
			$ogImageUrl = $this->ogImageResolver->getUrl($alternateRoute, $alternateParams);
			if ($ogImageUrl !== null) {
				$tags[] = '<meta property="og:image" content="' . Helpers::escapeHtmlAttr($ogImageUrl) . '">';
			}
		}

		if ($tags !== []) {
			$this->cache->save($cacheKey, $return = implode("\n", $tags), [
				Cache::EXPIRATION => '90 minutes',
			]);

			return $return;
		}

		return null;
	}


	public function getTitle(): ?string
	{
		if ($this->url === null || $this->match === null) {
			throw new \RuntimeException('Can not compile title, because SeoMetaManager has not been registered to SmartRouter or router does not match this request.');
		}

		$locale = $this->getLocale();
		$meta = $this->smartRouter->getRewriter()->getMetaData($this->url->getPathInfo(), $locale);
		$format = $this->localization->getStatus()->getLocaleToTitleFormat()[$locale] ?? '{{ title }} {{ separator }} {{ suffix }}';
		$separator = $this->localization->getStatus()->getLocaleToTitleSeparator()[$locale] ?? null;
		$suffix = $this->localization->getStatus()->getLocaleToTitleSuffix()[$locale] ?? null;

		if (($title = $meta->getMetaTitle()) !== null) {
			return Helpers::formatTitle($format, $title, $separator, $suffix);
		}
		if (
			($this->match['presenter'] === 'Homepage' || $this->match['presenter'] === 'Front:Homepage')
			&& $this->match['action'] === 'default'
		) {
			return $this->localization->getStatus()->getLocaleToSiteName()[$locale] ?? null;
		}

		return null;
	}


	public function getMetaDescription(): ?string
	{
		return $this->getMetaData()->getMetaDescription();
	}


	public function getOgTitle(): ?string
	{
		return $this->getMetaData()->getOgTitle() ?? $this->getTitle();
	}


	public function getOgDescription(): ?string
	{
		return $this->getMetaData()->getOgDescription() ?? $this->getMetaDescription();
	}


	public function isNoIndex(): bool
	{
		return $this->getMetaData()->isNoIndex();
	}


	public function isNoFollow(): bool
	{
		return $this->getMetaData()->isNoFollow();
	}


	private function getMetaData(): MetaData
	{
		if ($this->url === null) {
			throw new \RuntimeException('Can not get meta data, because SeoMetaManager has not been registered to SmartRouter or router does not match this request.');
		}

		return $this->smartRouter->getRewriter()->getMetaData($this->url->getPathInfo(), $this->getLocale());
	}


	private function getLocale(): string
	{
		return $this->match['locale'] ?? $this->localization->getLocale();
	}
}
