<?php

declare(strict_types=1);

namespace Baraja\SeoMeta;


final class Helpers
{
	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . static::class . ' is static and cannot be instantiated.');
	}


	/**
	 * Escapes string for use everywhere inside HTML (except for comments).
	 */
	public static function escapeHtml(string $s): string
	{
		return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
	}


	/**
	 * Escapes string for use inside HTML attribute value.
	 */
	public static function escapeHtmlAttr(string $s, bool $double = true): string
	{
		if (str_contains($s, '`')   && strpbrk($s, ' <>"\'') === false) {
			$s .= ' '; // protection against innerHTML mXSS vulnerability nette/nette#1496
		}

		return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8', $double);
	}


	/**
	 * Format meta title by mask. If suffix is empty, remove with separator.
	 */
	public static function formatTitle(
		string $format,
		string $title,
		?string $separator = null,
		?string $suffix = null,
	): string {
		$separator = trim($separator ?? '|');
		$return = trim(str_replace(['{{ title }}', '{{ separator }}', '{{ suffix }}'], [$title, $separator, $suffix ?? ''], $format));
		$return = (string) preg_replace('/\s+/', ' ', trim(trim($return, $separator)));

		return mb_strlen($return, 'UTF-8') > 70 ? $title : $return;
	}
}
